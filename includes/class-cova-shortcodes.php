<?php
/**
 * Cova Shortcodes
 *
 * @since 1.0.0
 */
class Cova_Shortcodes {
    /**
     * API Client
     *
     * @var Cova_API_Client
     */
    private $api_client;
    
    /**
     * Constructor
     */
    public function __construct($api_client) {
        $this->api_client = $api_client;
        
        add_shortcode('cova_products', array($this, 'products_shortcode'));
        add_shortcode('cova_inventory', array($this, 'inventory_shortcode'));
        add_shortcode('cova_test_image', array($this, 'test_image_shortcode'));
        add_shortcode('cova_process_images', array($this, 'process_images_shortcode'));
        
        // Add scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_cova_get_inventory', array($this, 'ajax_get_inventory'));
        add_action('wp_ajax_nopriv_cova_get_inventory', array($this, 'ajax_get_inventory'));
        
        // Add filter for proxying images with redirects
        add_filter('cova_process_image_url', array($this, 'process_image_url'), 10, 1);
        
        // Add AJAX handler for proxying images
        add_action('wp_ajax_cova_proxy_image', array($this, 'proxy_image'));
        add_action('wp_ajax_nopriv_cova_proxy_image', array($this, 'proxy_image'));
        
        // Add debug endpoint
        add_action('wp_ajax_cova_test_image_download', array($this, 'ajax_test_image_download'));
        add_action('wp_ajax_nopriv_cova_test_image_download', array($this, 'ajax_test_image_download'));
        
        // Add ajax handler for processing all images
        add_action('wp_ajax_cova_process_all_images', array($this, 'ajax_process_all_images'));
        add_action('wp_ajax_nopriv_cova_process_all_images', array($this, 'ajax_process_all_images'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        wp_enqueue_style(
            'cova-frontend',
            COVA_INTEGRATION_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            COVA_INTEGRATION_VERSION
        );
        
        wp_enqueue_script(
            'cova-frontend',
            COVA_INTEGRATION_PLUGIN_URL . 'assets/js/frontend.js',
            array('jquery'),
            COVA_INTEGRATION_VERSION,
            true
        );
        
        wp_localize_script('cova-frontend', 'covaParams', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('cova_frontend_nonce'),
        ));
    }
    
    /**
     * Process image URL to handle redirects
     * 
     * @param string $url Original image URL
     * @return string Processed image URL or original if processing failed
     */
    public function process_image_url($url) {
        if (empty($url)) {
            error_log("[COVA Image Processing] Empty URL provided to process_image_url");
            return '';
        }
        
        error_log("[COVA Image Processing] Original URL: " . $url);
        
        // First, try to directly load the image without proxy (for already downloaded images)
        // Check if we've already stored this image in the media library
        $attachment_id = $this->get_attachment_id_by_url($url);
        if ($attachment_id) {
            $image_url = wp_get_attachment_url($attachment_id);
            if ($image_url) {
                error_log("[COVA Image Processing] Found existing attachment, using: " . $image_url);
                return $image_url;
            }
        }
        
        // Option 1: If we don't have a local copy, try to download it now
        // This will store the image in the media library for future use
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Use a custom function to download with redirects
        $temp_file = $this->download_image_with_redirect($url);
        if ($temp_file && !is_wp_error($temp_file)) {
            $file_array = array(
                'name' => basename($url),
                'tmp_name' => $temp_file
            );
            
            $attachment_id = media_handle_sideload($file_array, 0, basename($url));
            if (!is_wp_error($attachment_id)) {
                update_post_meta($attachment_id, '_cova_asset_url', $url);
                $image_url = wp_get_attachment_url($attachment_id);
                if ($image_url) {
                    error_log("[COVA Image Processing] Successfully imported image to media library: " . $image_url);
                    return $image_url;
                }
            } else {
                error_log("[COVA Image Processing] Failed to import image: " . $attachment_id->get_error_message());
                // Clean up temp file
                @unlink($temp_file);
            }
        }
        
        // Option 2: If that fails, fall back to proxy
        // Create a proxy URL for this image
        $proxy_url = add_query_arg(
            array(
                'action' => 'cova_proxy_image',
                'url' => urlencode($url),
                'nonce' => wp_create_nonce('cova_proxy_image_' . md5($url))
            ),
            admin_url('admin-ajax.php')
        );
        
        error_log("[COVA Image Processing] Using proxy URL: " . $proxy_url);
        
        return $proxy_url;
    }
    
    /**
     * Download image with support for HTTP redirects
     *
     * @param string $url Image URL
     * @return string|WP_Error Path to downloaded temp file or WP_Error on failure
     */
    private function download_image_with_redirect($url) {
        error_log("[COVA Image Download] Downloading image from: " . $url);
        
        // Use WordPress HTTP API which handles redirects
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'redirection' => 5, // Follow up to 5 redirects
            'sslverify' => false, // Might need to be true in production
        ));
        
        if (is_wp_error($response)) {
            error_log("[COVA Image Download] Error downloading image: " . $response->get_error_message());
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log("[COVA Image Download] Download returned non-200 status code: " . $status_code);
            return new WP_Error('download_failed', 'Failed to download image. HTTP status: ' . $status_code);
        }
        
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            error_log("[COVA Image Download] Empty image data received");
            return new WP_Error('empty_image', 'Downloaded image is empty');
        }
        
        $file_extension = $this->get_file_extension_from_url($url);
        if (empty($file_extension)) {
            // Try to determine extension from content type
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            if (strpos($content_type, 'image/jpeg') !== false) {
                $file_extension = 'jpg';
            } elseif (strpos($content_type, 'image/png') !== false) {
                $file_extension = 'png';
            } elseif (strpos($content_type, 'image/gif') !== false) {
                $file_extension = 'gif';
            } else {
                $file_extension = 'jpg'; // Default
            }
        }
        
        $wp_upload_dir = wp_upload_dir();
        $temp_file = wp_tempnam();
        
        // Save the image data to a temporary file
        if (!file_put_contents($temp_file, $image_data)) {
            error_log("[COVA Image Download] Failed to write image data to temp file");
            return new WP_Error('file_save_failed', 'Could not write image to temporary file');
        }
        
        error_log("[COVA Image Download] Successfully downloaded image to: " . $temp_file);
        
        return $temp_file;
    }
    
    /**
     * Extract file extension from URL
     * 
     * @param string $url Image URL
     * @return string File extension or empty string if not found
     */
    private function get_file_extension_from_url($url) {
        $path_parts = pathinfo($url);
        return isset($path_parts['extension']) ? strtolower($path_parts['extension']) : '';
    }
    
    /**
     * Get attachment ID by image URL
     *
     * @param string $url Image URL
     * @return int|null Attachment ID or null if not found
     */
    private function get_attachment_id_by_url($url) {
        global $wpdb;
        $attachment = $wpdb->get_col($wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_cova_asset_url' AND meta_value = %s LIMIT 1", $url));
        if (!empty($attachment)) {
            return $attachment[0];
        }
        return null;
    }
    
    /**
     * Products shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function products_shortcode($atts) {
        global $wpdb;
        
        $atts = shortcode_atts(array(
            'category' => '',
            'limit' => 10,
            'columns' => 3,
        ), $atts, 'cova_products');
        
        $table_name = $wpdb->prefix . 'cova_products';
        $limit = intval($atts['limit']);
        
        // Build query
        $sql = "SELECT * FROM $table_name WHERE is_archived = 0";
        
        // Filter by category if specified
        if (!empty($atts['category'])) {
            $sql .= $wpdb->prepare(" AND category = %s", $atts['category']);
        }
        
        $sql .= " ORDER BY name ASC LIMIT %d";
        $products = $wpdb->get_results($wpdb->prepare($sql, $limit), ARRAY_A);
        
        if (empty($products)) {
            error_log("[COVA Products Shortcode] No products found");
            return '<p>' . esc_html__('No products found.', 'cova-integration') . '</p>';
        }
        
        error_log("[COVA Products Shortcode] Found " . count($products) . " products");
        
        $columns = intval($atts['columns']);
        if ($columns < 1) {
            $columns = 3;
        }
        
        ob_start();
        ?>
        <div class="cova-products-grid cova-columns-<?php echo esc_attr($columns); ?>">
            <?php foreach ($products as $product) : 
                // Extract data from JSON
                $product_data = json_decode($product['data'], true);
                $image_url = '';
                
                // Find image URL in assets
                if (!empty($product_data['Assets']) && is_array($product_data['Assets'])) {
                    error_log("[COVA Products Shortcode] Product " . $product['product_id'] . " has " . count($product_data['Assets']) . " assets");
                    
                    foreach ($product_data['Assets'] as $asset) {
                        error_log("[COVA Products Shortcode] Asset: " . print_r($asset, true));
                        
                        if (isset($asset['Type']) && $asset['Type'] === 'Image' && !empty($asset['Url'])) {
                            $image_url = $asset['Url'];
                            error_log("[COVA Products Shortcode] Found image URL: " . $image_url);
                            break;
                        }
                    }
                } else {
                    error_log("[COVA Products Shortcode] Product " . $product['product_id'] . " has no assets");
                }
                
                // Also check for HeroShotUri
                if (empty($image_url) && isset($product_data['HeroShotUri']) && !empty($product_data['HeroShotUri'])) {
                    $image_url = $product_data['HeroShotUri'];
                    error_log("[COVA Products Shortcode] Using HeroShotUri: " . $image_url);
                }
                
                if (empty($image_url)) {
                    error_log("[COVA Products Shortcode] No image found for product " . $product['product_id']);
                } else {
                    // Process the image URL to handle redirects
                    $processed_url = apply_filters('cova_process_image_url', $image_url);
                    error_log("[COVA Products Shortcode] Processed image URL: " . $processed_url);
                    $image_url = $processed_url;
                }
                
                // Get price for this product
                $price_table = $wpdb->prefix . 'cova_prices';
                $price_data = $wpdb->get_row($wpdb->prepare(
                    "SELECT regular_price FROM $price_table WHERE catalog_item_id = %s ORDER BY last_sync DESC LIMIT 1",
                    $product_data['Id']
                ));
                
                $price = $price_data ? $price_data->regular_price : null;
            ?>
                <div class="cova-product" data-product-id="<?php echo esc_attr($product['product_id']); ?>">
                    <?php if (!empty($image_url)) : ?>
                        <div class="cova-product-image">
                            <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product['name']); ?>">
                        </div>
                    <?php else: ?>
                        <div class="cova-product-image">
                            <p>No image available</p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="cova-product-details">
                        <h3 class="cova-product-title"><?php echo esc_html($product['name']); ?></h3>
                        
                        <?php if (!empty($product['description'])) : ?>
                            <div class="cova-product-description">
                                <?php echo wp_kses_post($product['description']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($price) : ?>
                            <div class="cova-product-price">
                                <?php echo esc_html(sprintf('$%0.2f', $price)); ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="cova-product-inventory" data-product-id="<?php echo esc_attr($product['product_id']); ?>">
                            <?php 
                            if (isset($product_data['QuantityOnHand'])) {
                                $stock = intval($product_data['QuantityOnHand']);
                                if ($stock > 0) {
                                    echo esc_html(sprintf(_n('%d item in stock', '%d items in stock', $stock, 'cova-integration'), $stock));
                                } else {
                                    esc_html_e('Out of stock', 'cova-integration');
                                }
                            } else {
                                esc_html_e('Loading inventory...', 'cova-integration');
                            }
                            ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.cova-product').each(function() {
                    var $product = $(this);
                    var productId = $product.data('product-id');
                    
                    // Load initial inventory
                    covaRefreshInventory(productId);
                    
                    // Setup refresh every 60 seconds
                    setInterval(function() {
                        covaRefreshInventory(productId);
                    }, 60000);
                });
                
                function covaRefreshInventory(productId) {
                    $.ajax({
                        url: covaParams.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cova_get_inventory',
                            nonce: covaParams.nonce,
                            product_id: productId
                        },
                        success: function(response) {
                            if (response.success) {
                                $('.cova-product-inventory[data-product-id="' + productId + '"]').html(response.data.html);
                            }
                        }
                    });
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Inventory shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function inventory_shortcode($atts) {
        global $wpdb;
        
        $atts = shortcode_atts(array(
            'product_id' => '',
        ), $atts, 'cova_inventory');
        
        if (empty($atts['product_id'])) {
            return '<p class="cova-error">' . esc_html__('Error: Product ID is required.', 'cova-integration') . '</p>';
        }
        
        $product_id = $atts['product_id'];
        $table_name = $wpdb->prefix . 'cova_products';
        
        // Get product data
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %s",
            $product_id
        ), ARRAY_A);
        
        if (!$product) {
            return '<p class="cova-no-inventory">' . esc_html__('No inventory information available.', 'cova-integration') . '</p>';
        }
        
        // Extract inventory data from JSON
        $product_data = json_decode($product['data'], true);
        $stock_level = isset($product_data['QuantityOnHand']) ? intval($product_data['QuantityOnHand']) : 0;
        
        ob_start();
        ?>
        <div class="cova-inventory" data-product-id="<?php echo esc_attr($product_id); ?>">
            <p class="cova-stock-level">
                <?php 
                if ($stock_level > 0) {
                    echo esc_html(sprintf(_n('%d item in stock', '%d items in stock', $stock_level, 'cova-integration'), $stock_level));
                } else {
                    esc_html_e('Out of stock', 'cova-integration');
                }
                ?>
            </p>
            <p class="cova-last-updated">
                <?php 
                esc_html_e('Last updated: ', 'cova-integration');
                echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($product['last_sync'])));
                ?>
            </p>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Setup refresh every 60 seconds
                setInterval(function() {
                    var productId = '<?php echo esc_js($product_id); ?>';
                    
                    $.ajax({
                        url: covaParams.ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cova_get_inventory',
                            nonce: covaParams.nonce,
                            product_id: productId
                        },
                        success: function(response) {
                            if (response.success) {
                                $('.cova-inventory[data-product-id="' + productId + '"]').html(response.data.html);
                            }
                        }
                    });
                }, 60000);
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for getting product inventory
     */
    public function ajax_get_inventory() {
        check_ajax_referer('cova_frontend_nonce', 'nonce');
        
        $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
        
        if (empty($product_id)) {
            wp_send_json_error(__('Product ID is required', 'cova-integration'));
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cova_products';
        
        // Get product data
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %s",
            $product_id
        ), ARRAY_A);
        
        if (!$product) {
            wp_send_json_error(__('Product not found', 'cova-integration'));
            return;
        }
        
        // Extract inventory data from JSON
        $product_data = json_decode($product['data'], true);
        $stock_level = isset($product_data['QuantityOnHand']) ? intval($product_data['QuantityOnHand']) : 0;
        
        ob_start();
        
        ?>
        <p class="cova-stock-level">
            <?php 
            if ($stock_level > 0) {
                echo esc_html(sprintf(_n('%d item in stock', '%d items in stock', $stock_level, 'cova-integration'), $stock_level));
            } else {
                esc_html_e('Out of stock', 'cova-integration');
            }
            ?>
        </p>
        <p class="cova-last-updated">
            <?php 
            esc_html_e('Last updated: ', 'cova-integration');
            echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($product['last_sync'])));
            ?>
        </p>
        <?php
        
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html,
            'stock_level' => $stock_level
        ));
    }
    
    /**
     * AJAX handler for proxying images to handle redirects
     */
    public function proxy_image() {
        // Verify nonce
        $url = isset($_GET['url']) ? urldecode($_GET['url']) : '';
        if (empty($url)) {
            error_log("[COVA Image Proxy] Empty URL provided");
            wp_die('Invalid request - no URL provided');
        }
        
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'cova_proxy_image_' . md5($url))) {
            error_log("[COVA Image Proxy] Invalid nonce for URL: " . $url);
            wp_die('Invalid request - nonce verification failed');
        }
        
        error_log("[COVA Image Proxy] Processing image URL: " . $url);
        
        // Use WordPress HTTP API to fetch the image with support for redirects
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'redirection' => 5, // Follow up to 5 redirects
            'sslverify' => false, // Might need to be true in production
        ));
        
        if (is_wp_error($response)) {
            error_log("[COVA Image Proxy] Error fetching image: " . $response->get_error_message());
            wp_die('Failed to fetch image: ' . $response->get_error_message());
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log("[COVA Image Proxy] Image fetch returned non-200 status code: " . $status_code);
            wp_die('Failed to fetch image. HTTP status: ' . $status_code);
        }
        
        // Get image content type from headers
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        error_log("[COVA Image Proxy] Image content type: " . $content_type);
        
        if (empty($content_type) || strpos($content_type, 'image/') !== 0) {
            $content_type = 'image/jpeg'; // Default to JPEG if not specified
            error_log("[COVA Image Proxy] Using default content type: image/jpeg");
        }
        
        // Get the image data
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            error_log("[COVA Image Proxy] Empty image data received");
            wp_die('Failed to fetch image: Empty response body');
        }
        
        error_log("[COVA Image Proxy] Successfully retrieved image, size: " . strlen($image_data) . " bytes");
        
        // Output the image with proper headers
        header('Content-Type: ' . $content_type);
        header('Cache-Control: max-age=86400, public'); // Cache for 24 hours
        echo $image_data;
        exit;
    }
    
    /**
     * Test image shortcode for debugging
     * 
     * @param array $atts Shortcode attributes
     * @return string Shortcode output
     */
    public function test_image_shortcode($atts) {
        $atts = shortcode_atts(array(
            'url' => 'https://ams.iqmetrix.net/images/78c97f68-1225-47a6-ba16-8ce6a9924edf',
        ), $atts, 'cova_test_image');
        
        $image_url = $atts['url'];
        $processed_url = apply_filters('cova_process_image_url', $image_url);
        
        ob_start();
        ?>
        <div class="cova-test-image">
            <h3>Image Test</h3>
            <p>Original URL: <?php echo esc_html($image_url); ?></p>
            <p>Processed URL: <?php echo esc_html($processed_url); ?></p>
            
            <h4>Direct Image Test</h4>
            <img src="<?php echo esc_url($image_url); ?>" alt="Direct Image Test" style="max-width: 300px; border: 1px solid red;">
            
            <h4>Processed Image Test</h4>
            <img src="<?php echo esc_url($processed_url); ?>" alt="Processed Image Test" style="max-width: 300px; border: 1px solid green;">
            
            <h4>Test Download</h4>
            <button id="test-download-btn" class="button">Test Direct Download</button>
            <div id="test-download-result"></div>
        </div>
        <script>
            jQuery(document).ready(function($) {
                $('#test-download-btn').on('click', function() {
                    $('#test-download-result').html('Downloading...');
                    
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'cova_test_image_download',
                            url: '<?php echo esc_js($image_url); ?>',
                            nonce: '<?php echo wp_create_nonce('cova_test_image_download'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#test-download-result').html(
                                    '<p style="color:green;">Success!</p>' +
                                    '<p>Content Type: ' + response.data.content_type + '</p>' +
                                    '<p>File Size: ' + response.data.file_size + ' bytes</p>' +
                                    '<p>Response Code: ' + response.data.response_code + '</p>' +
                                    '<img src="data:' + response.data.content_type + ';base64,' + response.data.image_data + '" style="max-width:300px;">'
                                );
                            } else {
                                $('#test-download-result').html('<p style="color:red;">Error: ' + response.data + '</p>');
                            }
                        },
                        error: function() {
                            $('#test-download-result').html('<p style="color:red;">Ajax request failed</p>');
                        }
                    });
                });
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for testing image download
     */
    public function ajax_test_image_download() {
        check_ajax_referer('cova_test_image_download', 'nonce');
        
        $url = isset($_POST['url']) ? sanitize_text_field($_POST['url']) : '';
        if (empty($url)) {
            wp_send_json_error('No URL provided');
            return;
        }
        
        error_log("[COVA Test Image Download] Testing URL: " . $url);
        
        // Use WordPress HTTP API to fetch the image with support for redirects
        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'redirection' => 5, // Follow up to 5 redirects
            'sslverify' => false, // Might need to be true in production
        ));
        
        if (is_wp_error($response)) {
            error_log("[COVA Test Image Download] Error: " . $response->get_error_message());
            wp_send_json_error($response->get_error_message());
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $content_type = wp_remote_retrieve_header($response, 'content-type');
        $body = wp_remote_retrieve_body($response);
        
        error_log("[COVA Test Image Download] Response Code: " . $response_code);
        error_log("[COVA Test Image Download] Content Type: " . $content_type);
        error_log("[COVA Test Image Download] Body Size: " . strlen($body) . " bytes");
        
        wp_send_json_success(array(
            'response_code' => $response_code,
            'content_type' => $content_type,
            'file_size' => strlen($body),
            'image_data' => base64_encode($body)
        ));
    }
    
    /**
     * Process images shortcode - allows admin to pre-process all product images
     * 
     * @return string Shortcode output
     */
    public function process_images_shortcode($atts) {
        if (!current_user_can('manage_options')) {
            return '<p>You do not have permission to use this shortcode.</p>';
        }
        
        ob_start();
        ?>
        <div class="cova-process-images">
            <h3>Process All Product Images</h3>
            <p>This tool will download and cache all product images from the Cova API. This may take some time depending on how many products you have.</p>
            
            <button id="process-images-btn" class="button button-primary">Process All Images</button>
            
            <div id="process-results" style="margin-top: 20px; display: none;">
                <h4>Processing Results</h4>
                <div id="progress-container">
                    <div id="progress-bar" style="background-color: #0073aa; height: 20px; width: 0%;"></div>
                </div>
                <p id="progress-text">0% complete</p>
                <div id="process-log"></div>
            </div>
        </div>
        
        <script>
            jQuery(document).ready(function($) {
                $('#process-images-btn').on('click', function() {
                    $(this).prop('disabled', true);
                    $('#process-results').show();
                    $('#process-log').empty();
                    
                    processImages();
                });
                
                function processImages() {
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'cova_process_all_images',
                            nonce: '<?php echo wp_create_nonce('cova_process_all_images'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                var percent = response.data.progress;
                                $('#progress-bar').css('width', percent + '%');
                                $('#progress-text').text(percent + '% complete');
                                
                                if (response.data.message) {
                                    $('#process-log').prepend('<p>' + response.data.message + '</p>');
                                }
                                
                                if (response.data.complete) {
                                    $('#process-log').prepend('<p><strong>Processing complete!</strong> Processed ' + response.data.total_processed + ' images.</p>');
                                    $('#process-images-btn').prop('disabled', false);
                                } else {
                                    // Continue processing
                                    setTimeout(processImages, 1000);
                                }
                            } else {
                                $('#process-log').prepend('<p style="color:red;">Error: ' + response.data + '</p>');
                                $('#process-images-btn').prop('disabled', false);
                            }
                        },
                        error: function() {
                            $('#process-log').prepend('<p style="color:red;">Ajax request failed</p>');
                            $('#process-images-btn').prop('disabled', false);
                        }
                    });
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for processing all product images
     */
    public function ajax_process_all_images() {
        check_ajax_referer('cova_process_all_images', 'nonce');
        
        global $wpdb;
        
        // Get the current progress
        $processed_images = get_option('cova_processed_images', array());
        $current_offset = get_option('cova_image_processing_offset', 0);
        $batch_size = 5; // Process 5 products at a time
        
        // Get total number of products
        $table_name = $wpdb->prefix . 'cova_products';
        $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_archived = 0");
        
        if ($current_offset >= $total_products) {
            // Reset for next time
            update_option('cova_image_processing_offset', 0);
            
            wp_send_json_success(array(
                'complete' => true,
                'progress' => 100,
                'total_processed' => count($processed_images),
                'message' => 'All images have been processed.'
            ));
            return;
        }
        
        // Get the next batch of products
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE is_archived = 0 ORDER BY id LIMIT %d OFFSET %d",
            $batch_size, $current_offset
        ), ARRAY_A);
        
        $message = '';
        $newly_processed = 0;
        
        foreach ($products as $product) {
            $product_data = json_decode($product['data'], true);
            $image_urls = array();
            
            // Get HeroShotUri if available
            if (!empty($product_data['HeroShotUri'])) {
                $image_urls[] = $product_data['HeroShotUri'];
            }
            
            // Get image URLs from Assets
            if (!empty($product_data['Assets']) && is_array($product_data['Assets'])) {
                foreach ($product_data['Assets'] as $asset) {
                    if (isset($asset['Type']) && $asset['Type'] === 'Image' && !empty($asset['Url'])) {
                        $image_urls[] = $asset['Url'];
                    }
                }
            }
            
            // Process each image URL
            foreach ($image_urls as $url) {
                if (in_array($url, $processed_images)) {
                    continue; // Skip already processed images
                }
                
                $image_id = $this->download_and_cache_image($url, $product['name']);
                
                if ($image_id && !is_wp_error($image_id)) {
                    $processed_images[] = $url;
                    $newly_processed++;
                    $message .= "Processed image for product: " . esc_html($product['name']) . "<br>";
                }
            }
        }
        
        // Update the offset and processed images
        $current_offset += $batch_size;
        update_option('cova_image_processing_offset', $current_offset);
        update_option('cova_processed_images', $processed_images);
        
        // Calculate progress percentage
        $progress = min(100, round(($current_offset / $total_products) * 100));
        
        wp_send_json_success(array(
            'complete' => false,
            'progress' => $progress,
            'total_processed' => count($processed_images),
            'newly_processed' => $newly_processed,
            'message' => $message
        ));
    }
    
    /**
     * Download and cache an image
     * 
     * @param string $url Image URL
     * @param string $title Image title
     * @return int|WP_Error Attachment ID on success, WP_Error on failure
     */
    private function download_and_cache_image($url, $title = '') {
        if (empty($url)) {
            return new WP_Error('empty_url', 'Empty image URL');
        }
        
        // Check if we've already stored this image in the media library
        $attachment_id = $this->get_attachment_id_by_url($url);
        if ($attachment_id) {
            return $attachment_id;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Use a custom function to download with redirects
        $temp_file = $this->download_image_with_redirect($url);
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }
        
        if (!$temp_file) {
            return new WP_Error('download_failed', 'Failed to download image');
        }
        
        $file_array = array(
            'name' => basename($url),
            'tmp_name' => $temp_file
        );
        
        // Use media_handle_sideload to create an attachment
        $attachment_id = media_handle_sideload($file_array, 0, $title);
        
        if (is_wp_error($attachment_id)) {
            @unlink($temp_file);
            return $attachment_id;
        }
        
        // Store the original URL as meta data
        update_post_meta($attachment_id, '_cova_asset_url', $url);
        
        return $attachment_id;
    }
} 