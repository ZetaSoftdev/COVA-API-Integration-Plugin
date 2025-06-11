<?php
/**
 * WooCommerce integration for Cova API
 *
 * @since 1.0.0
 */
class Cova_WooCommerce {
    /**
     * API Client
     *
     * @var Cova_API_Client
     */
    private $api_client;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new Cova_API_Client();
        
        // Add hooks for syncing
        add_action('cova_integration_sync_event', array($this, 'sync_products_with_woocommerce'));
        add_action('wp_ajax_cova_sync_products_with_woocommerce', array($this, 'ajax_sync_products_with_woocommerce'));
        add_action('wp_ajax_cova_clear_woocommerce_products', array($this, 'ajax_clear_woocommerce_products'));
        add_action('wp_ajax_cova_get_category_selection_modal', array($this, 'ajax_get_category_selection_modal'));
        
        // Real-time inventory webhook endpoint
        add_action('wp_ajax_nopriv_cova_inventory_webhook', array($this, 'process_inventory_webhook'));
        add_action('wp_ajax_cova_inventory_webhook', array($this, 'process_inventory_webhook'));
        
        // Add settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add admin page (only add WooCommerce Sync, not duplicating others)
        add_action('admin_menu', array($this, 'add_admin_submenu'), 30);
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('cova_integration_woocommerce_settings', 'cova_integration_wc_sync_enabled', array(
            'default' => true,
        ));
        register_setting('cova_integration_woocommerce_settings', 'cova_integration_wc_auto_publish', array(
            'default' => false,
        ));
        register_setting('cova_integration_woocommerce_settings', 'cova_integration_wc_import_images', array(
            'default' => true,
        ));
        register_setting('cova_integration_woocommerce_settings', 'cova_integration_wc_default_category', array(
            'default' => '',
        ));
        register_setting('cova_integration_woocommerce_settings', 'cova_integration_wc_category_mapping', array(
            'default' => array(),
        ));
        register_setting('cova_integration_woocommerce_settings', 'cova_integration_wc_clear_before_sync', array(
            'default' => false,
        ));
        
        add_settings_section(
            'cova_integration_wc_settings',
            __('WooCommerce Integration Settings', 'cova-integration'),
            array($this, 'wc_settings_section_callback'),
            'cova_integration_woocommerce_settings'
        );
        
        add_settings_field(
            'cova_integration_wc_sync_enabled',
            __('Enable WooCommerce Sync', 'cova-integration'),
            array($this, 'wc_sync_enabled_render'),
            'cova_integration_woocommerce_settings',
            'cova_integration_wc_settings'
        );
        
        add_settings_field(
            'cova_integration_wc_auto_publish',
            __('Auto Publish Products', 'cova-integration'),
            array($this, 'wc_auto_publish_render'),
            'cova_integration_woocommerce_settings',
            'cova_integration_wc_settings'
        );
        
        add_settings_field(
            'cova_integration_wc_import_images',
            __('Import Product Images', 'cova-integration'),
            array($this, 'wc_import_images_render'),
            'cova_integration_woocommerce_settings',
            'cova_integration_wc_settings'
        );
        
        add_settings_field(
            'cova_integration_wc_clear_before_sync',
            __('Clear Products Before Sync', 'cova-integration'),
            array($this, 'wc_clear_before_sync_render'),
            'cova_integration_woocommerce_settings',
            'cova_integration_wc_settings'
        );
        
        add_settings_field(
            'cova_integration_wc_default_category',
            __('Default WooCommerce Category', 'cova-integration'),
            array($this, 'wc_default_category_render'),
            'cova_integration_woocommerce_settings',
            'cova_integration_wc_settings'
        );
        
        add_settings_field(
            'cova_integration_wc_category_mapping',
            __('Category Mapping', 'cova-integration'),
            array($this, 'wc_category_mapping_render'),
            'cova_integration_woocommerce_settings',
            'cova_integration_wc_settings'
        );
    }
    
    /**
     * WooCommerce settings section callback
     */
    public function wc_settings_section_callback() {
        echo '<p>' . __('Configure how Cova products are synchronized with WooCommerce.', 'cova-integration') . '</p>';
    }
    
    /**
     * Sync enabled field render
     */
    public function wc_sync_enabled_render() {
        $sync_enabled = get_option('cova_integration_wc_sync_enabled', true);
        echo '<input type="checkbox" name="cova_integration_wc_sync_enabled" value="1" ' . checked($sync_enabled, true, false) . '>';
        echo '<p class="description">' . __('When enabled, Cova products will be synced to WooCommerce products.', 'cova-integration') . '</p>';
    }
    
    /**
     * Auto publish field render
     */
    public function wc_auto_publish_render() {
        $auto_publish = get_option('cova_integration_wc_auto_publish', false);
        echo '<input type="checkbox" name="cova_integration_wc_auto_publish" value="1" ' . checked($auto_publish, true, false) . '>';
        echo '<p class="description">' . __('When enabled, new products will be automatically published. Otherwise, they will be created as drafts.', 'cova-integration') . '</p>';
    }
    
    /**
     * Import images field render
     */
    public function wc_import_images_render() {
        $import_images = get_option('cova_integration_wc_import_images', true);
        echo '<input type="checkbox" name="cova_integration_wc_import_images" value="1" ' . checked($import_images, true, false) . '>';
        echo '<p class="description">' . __('When enabled, product images from Cova will be imported to the WordPress media library.', 'cova-integration') . '</p>';
    }
    
    /**
     * Clear before sync field render
     */
    public function wc_clear_before_sync_render() {
        $clear_before_sync = get_option('cova_integration_wc_clear_before_sync', false);
        echo '<input type="checkbox" name="cova_integration_wc_clear_before_sync" value="1" ' . checked($clear_before_sync, true, false) . '>';
        echo '<p class="description">' . __('When enabled, all Cova-related products will be cleared before syncing.', 'cova-integration') . '</p>';
    }
    
    /**
     * Default category field render
     */
    public function wc_default_category_render() {
        $default_category = get_option('cova_integration_wc_default_category', '');
        $dropdown_args = array(
            'name' => 'cova_integration_wc_default_category',
            'taxonomy' => 'product_cat',
            'show_option_none' => __('-- Select Category --', 'cova-integration'),
            'option_none_value' => '',
            'selected' => $default_category,
            'hide_empty' => false,
        );
        wp_dropdown_categories($dropdown_args);
        echo '<p class="description">' . __('Default WooCommerce category for products without a mapped category.', 'cova-integration') . '</p>';
    }
    
    /**
     * Category mapping field render
     */
    public function wc_category_mapping_render() {
        $category_mapping = get_option('cova_integration_wc_category_mapping', array());
        $cova_categories = $this->get_cova_categories();
        
        if (empty($cova_categories)) {
            echo '<p>' . __('No Cova categories found. Please sync product data first.', 'cova-integration') . '</p>';
            return;
        }
        
        echo '<table class="form-table"><tbody>';
        
        foreach ($cova_categories as $cova_category) {
            $selected_category = isset($category_mapping[$cova_category]) ? $category_mapping[$cova_category] : '';
            
            echo '<tr>';
            echo '<th scope="row">' . esc_html($cova_category) . '</th>';
            echo '<td>';
            
            $dropdown_args = array(
                'name' => 'cova_integration_wc_category_mapping[' . esc_attr($cova_category) . ']',
                'taxonomy' => 'product_cat',
                'show_option_none' => __('-- Select Category --', 'cova-integration'),
                'option_none_value' => '',
                'selected' => $selected_category,
                'hide_empty' => false,
            );
            wp_dropdown_categories($dropdown_args);
            
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * Get unique categories from Cova products
     *
     * @return array List of unique categories
     */
    private function get_cova_categories() {
        $products = $this->api_client->getProducts();
        
        if (is_wp_error($products) || empty($products)) {
            return array();
        }
        
        $categories = array();
        
        foreach ($products as $product) {
            if (isset($product['Category']) && !empty($product['Category']) && !in_array($product['Category'], $categories)) {
                $categories[] = $product['Category'];
            }
        }
        
        sort($categories);
        
        return $categories;
    }
    
    /**
     * Add admin submenu page
     */
    public function add_admin_submenu() {
        add_submenu_page(
            'cova-integration',
            __('WooCommerce Sync', 'cova-integration'),
            __('WooCommerce Sync', 'cova-integration'),
            'manage_options',
            'cova-integration-woocommerce',
            array($this, 'display_woocommerce_page')
        );
    }
    
    /**
     * Display WooCommerce sync page
     */
    public function display_woocommerce_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'cova-integration'));
        }
        
        if (!class_exists('WooCommerce')) {
            echo '<div class="wrap"><h1>' . esc_html(get_admin_page_title()) . '</h1>';
            echo '<div class="notice notice-error"><p>' . __('WooCommerce is not installed or activated. Please install and activate WooCommerce to use this feature.', 'cova-integration') . '</p></div>';
            echo '</div>';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="cova-sync-status">
                <h3><?php _e('Synchronization Status', 'cova-integration'); ?></h3>
                <p>
                    <?php 
                    $last_sync = get_option('cova_wc_last_sync_time', 0);
                    if ($last_sync > 0) {
                        echo sprintf(
                            __('Last WooCommerce sync: %s ago', 'cova-integration'),
                            human_time_diff($last_sync, current_time('timestamp'))
                        );
                    } else {
                        _e('Products have not been synchronized with WooCommerce yet.', 'cova-integration');
                    }
                    ?>
                </p>
                
                <p>
                    <button id="cova-sync-woocommerce" class="button button-primary"><?php _e('Sync Products Now', 'cova-integration'); ?></button>
                    <button id="cova-clear-woocommerce" class="button"><?php _e('Clear All Products', 'cova-integration'); ?></button>
                </p>
                
                <div id="cova-sync-progress" style="display: none;">
                    <p class="cova-sync-message"><?php _e('Syncing products...', 'cova-integration'); ?></p>
                    <progress id="cova-sync-progress-bar" value="0" max="100"></progress>
                </div>
                
                <div id="cova-clear-result" class="notice notice-success" style="display: none; margin-top: 10px;">
                    <p></p>
                </div>
            </div>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('cova_integration_woocommerce_settings');
                ?>
                
                <h2><?php _e('Sync Settings', 'cova-integration'); ?></h2>
                <p class="description"><?php _e('Configure how Cova products are synchronized with WooCommerce.', 'cova-integration'); ?></p>
                
                <?php $this->display_woocommerce_settings_without_mapping(); ?>
                
                <h2><?php _e('Category Mapping', 'cova-integration'); ?></h2>
                <p class="description"><?php _e('Map Cova product categories to WooCommerce categories.', 'cova-integration'); ?></p>
                
                <?php $this->wc_category_mapping_render(); ?>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Handle sync button click
                $('#cova-sync-woocommerce').on('click', function() {
                    var $button = $(this);
                    var $progress = $('#cova-sync-progress');
                    var $progressBar = $('#cova-sync-progress-bar');
                    var $result = $('#cova-clear-result');
                    
                    // Disable button and show progress
                    $button.prop('disabled', true);
                    $progress.show();
                    $result.hide();
                    
                    // Start sync
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cova_sync_products_with_woocommerce',
                            nonce: '<?php echo wp_create_nonce('cova_sync_woocommerce_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.removeClass('notice-error').addClass('notice-success');
                                $result.find('p').text('<?php _e('Products synchronized successfully!', 'cova-integration'); ?>');
                            } else {
                                $result.removeClass('notice-success').addClass('notice-error');
                                $result.find('p').text(response.data || '<?php _e('An error occurred during synchronization.', 'cova-integration'); ?>');
                            }
                        },
                        error: function() {
                            $result.removeClass('notice-success').addClass('notice-error');
                            $result.find('p').text('<?php _e('An error occurred. Please try again.', 'cova-integration'); ?>');
                        },
                        complete: function() {
                            $button.prop('disabled', false);
                            $progress.hide();
                            $result.show();
                        }
                    });
                });
                
                // Handle clear button click
                $('#cova-clear-woocommerce').on('click', function() {
                    if (!confirm('<?php _e('Are you sure you want to clear all Cova products from WooCommerce? This action cannot be undone.', 'cova-integration'); ?>')) {
                        return;
                    }
                    
                    var $button = $(this);
                    var $result = $('#cova-clear-result');
                    
                    $button.prop('disabled', true);
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cova_clear_woocommerce_products',
                            nonce: '<?php echo wp_create_nonce('cova_clear_woocommerce_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                $result.removeClass('notice-error').addClass('notice-success');
                                $result.find('p').text('<?php _e('All Cova products have been cleared from WooCommerce.', 'cova-integration'); ?>');
                            } else {
                                $result.removeClass('notice-success').addClass('notice-error');
                                $result.find('p').text(response.data || '<?php _e('An error occurred while clearing products.', 'cova-integration'); ?>');
                            }
                        },
                        error: function() {
                            $result.removeClass('notice-success').addClass('notice-error');
                            $result.find('p').text('<?php _e('An error occurred. Please try again.', 'cova-integration'); ?>');
                        },
                        complete: function() {
                            $button.prop('disabled', false);
                            $result.show();
                        }
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Display WooCommerce settings without category mapping
     */
    private function display_woocommerce_settings_without_mapping() {
        ?>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Enable WooCommerce Sync', 'cova-integration'); ?></th>
                <td>
                    <?php $this->wc_sync_enabled_render(); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Auto Publish Products', 'cova-integration'); ?></th>
                <td>
                    <?php $this->wc_auto_publish_render(); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Import Product Images', 'cova-integration'); ?></th>
                <td>
                    <?php $this->wc_import_images_render(); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Clear Products Before Sync', 'cova-integration'); ?></th>
                <td>
                    <?php $this->wc_clear_before_sync_render(); ?>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Default WooCommerce Category', 'cova-integration'); ?></th>
                <td>
                    <?php $this->wc_default_category_render(); ?>
                </td>
            </tr>
        </table>
        <?php
    }
    
    /**
     * AJAX handler for syncing products with WooCommerce
     */
    public function ajax_sync_products_with_woocommerce() {
        check_ajax_referer('cova_sync_woocommerce_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cova-integration'));
            return;
        }
        // Check if specific product IDs were passed
        if (isset($_POST['product_ids']) && !empty($_POST['product_ids'])) {
            $product_ids = is_array($_POST['product_ids']) ? 
                array_map('sanitize_text_field', $_POST['product_ids']) : 
                [sanitize_text_field($_POST['product_ids'])];
            
            // Check if category is being explicitly set for this product
            $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
            
            $result = $this->sync_selected_products($product_ids, $last_error, $category_id);
            if ($result) {
                wp_send_json_success();
            } else {
                $error_message = $last_error ? $last_error : __('Failed to sync selected products. Please check the logs.', 'cova-integration');
                wp_send_json_error($error_message);
            }
        } else {
            // Sync all products
            $result = $this->sync_products_with_woocommerce(true);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_success();
            }
        }
    }
    
    /**
     * AJAX handler for getting product category selection modal
     */
    public function ajax_get_category_selection_modal() {
        check_ajax_referer('cova_category_selection_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cova-integration'));
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
        
        if (empty($product_id)) {
            wp_send_json_error(__('Product ID is required', 'cova-integration'));
            return;
        }
        
        // Get product info
        global $wpdb;
        $table_name = $wpdb->prefix . 'cova_products';
        $product = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE product_id = %s",
            $product_id
        ), ARRAY_A);
        
        if (!$product) {
            wp_send_json_error(__('Product not found', 'cova-integration'));
            return;
        }
        
        // Get the WooCommerce categories
        $categories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ));
        
        ob_start();
        ?>
        <div class="cova-category-selection-modal">
            <h3><?php echo sprintf(__('Select Category for "%s"', 'cova-integration'), esc_html($product['name'])); ?></h3>
            
            <p><strong><?php _e('Product Category:', 'cova-integration'); ?></strong> <?php echo esc_html($product['category']); ?></p>
            
            <p><?php _e('Select a WooCommerce category for this product:', 'cova-integration'); ?></p>
            
            <select id="cova-product-category-select">
                <option value=""><?php _e('-- Use Default Category --', 'cova-integration'); ?></option>
                <?php foreach ($categories as $category) : ?>
                <option value="<?php echo esc_attr($category->term_id); ?>"><?php echo esc_html($category->name); ?></option>
                <?php endforeach; ?>
            </select>
            
            <div class="cova-modal-actions">
                <button id="cova-sync-with-category" class="button button-primary" data-product-id="<?php echo esc_attr($product_id); ?>">
                    <?php _e('Sync Product', 'cova-integration'); ?>
                </button>
                <button id="cova-cancel-category-selection" class="button">
                    <?php _e('Cancel', 'cova-integration'); ?>
                </button>
            </div>
        </div>
        <?php
        
        $html = ob_get_clean();
        
        wp_send_json_success(array(
            'html' => $html
        ));
    }
    
    /**
     * AJAX handler for clearing WooCommerce products
     */
    public function ajax_clear_woocommerce_products() {
        check_ajax_referer('cova_clear_woocommerce_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cova-integration'));
            return;
        }
        
        $count = $this->clear_cova_products();
        
        wp_send_json_success(array(
            'count' => $count,
            'message' => sprintf(
                __('Successfully cleared %d Cova-related products from WooCommerce.', 'cova-integration'),
                $count
            )
        ));
    }
    
    /**
     * Clear all Cova-related products from WooCommerce
     *
     * @return int Number of products removed
     */
    private function clear_cova_products() {
        global $wpdb;
        
        // Find all products with Cova product ID
        $query = "
            SELECT p.ID
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'product'
            AND pm.meta_key = '_cova_product_id'
        ";
        
        $product_ids = $wpdb->get_col($query);
        $count = count($product_ids);
        
        if (empty($product_ids)) {
            return 0;
        }
        
        // Delete each product and its meta data
        foreach ($product_ids as $product_id) {
            wp_delete_post($product_id, true); // Force delete
        }
        
        $this->log_error(sprintf(
            'Cleared %d Cova-related products from WooCommerce.',
            $count
        ));
        
        return $count;
    }
    
    /**
     * Synchronize Cova products with WooCommerce
     *
     * @param bool $forced Whether this is a forced manual sync
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    public function sync_products_with_woocommerce($forced = false) {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return new WP_Error('woocommerce_not_active', __('WooCommerce is not active', 'cova-integration'));
        }
        
        // Check if sync is enabled
        $sync_enabled = get_option('cova_integration_wc_sync_enabled', true);
        if (!$sync_enabled && !$forced) {
            return true; // Skip silently if not forced
        }
        
        // Check if products should be cleared before sync
        $clear_before_sync = get_option('cova_integration_wc_clear_before_sync', false);
        if ($clear_before_sync) {
            $this->clear_cova_products();
        }
        
        // Get products from Cova API
        $products = $this->api_client->getProducts();
        
        if (is_wp_error($products)) {
            return $products;
        }
        
        if (empty($products)) {
            return new WP_Error('no_products', __('No products found in Cova API', 'cova-integration'));
        }
        
        // Get inventory data
        $inventory = $this->api_client->getInventory();
        if (is_wp_error($inventory)) {
            $inventory = array();
        }
        
        // Get prices data
        $prices = $this->api_client->getPrices();
        if (is_wp_error($prices)) {
            $prices = array();
        }
        
        // Get settings
        $auto_publish = get_option('cova_integration_wc_auto_publish', false);
        $import_images = get_option('cova_integration_wc_import_images', true);
        $default_category = get_option('cova_integration_wc_default_category', '');
        $category_mapping = get_option('cova_integration_wc_category_mapping', array());
        
        // Process each product
        $synced_count = 0;
        $updated_count = 0;
        $skipped_count = 0;
        $error_count = 0;
        $fixed_numeric_skus = 0;
        
        $cova_product_ids = array();
        
        foreach ($products as $product) {
            $cova_id = isset($product['Id']) ? $product['Id'] : null;
            
            if (empty($cova_id)) {
                $skipped_count++;
                continue;
            }
            
            $cova_product_ids[] = $cova_id;
            
            // Find inventory for this product
            $product_inventory = null;
            foreach ($inventory as $inv_item) {
                if (isset($inv_item['ProductId']) && $inv_item['ProductId'] == $cova_id) {
                    $product_inventory = $inv_item;
                    break;
                }
            }
            
            // Find price for this product
            $product_price = 0;
            foreach ($prices as $price_item) {
                if (isset($price_item['ProductId']) && $price_item['ProductId'] == $cova_id) {
                    $product_price = $price_item['Price'];
                    break;
                }
            }
            
            // Extract or verify SKU
            $sku = $this->extract_proper_sku_from_product($product);
            
            // Only check for existing product if we're not clearing before sync
            $wc_product_id = null;
            if (!$clear_before_sync) {
                // Check if this product already exists in WooCommerce
                $wc_product_id = $this->get_wc_product_id_by_cova_id($cova_id);
            }
            
            if ($wc_product_id) {
                // Get the current product
                $wc_product = wc_get_product($wc_product_id);
                
                // Check if current SKU is a numeric ID that needs fixing
                $current_sku = $wc_product->get_sku();
                $needs_sku_fix = is_numeric($current_sku) && strlen($current_sku) >= 6 && substr($current_sku, 0, 3) == '100';
                
                if ($needs_sku_fix && !empty($sku)) {
                    $this->log_error('Fixing numeric SKU ' . $current_sku . ' to ' . $sku . ' for product: ' . $wc_product->get_name());
                    $fixed_numeric_skus++;
                }
                
                // Update existing product
                $result = $this->update_wc_product($wc_product_id, $product, $product_inventory, $product_price, $import_images);
                if ($result) {
                    $updated_count++;
                    // Success: do not log as error
                } else {
                    $error_count++;
                    $last_error = 'Failed to update WooCommerce product for ID: ' . $cova_id;
                    $this->log_error('Failed to update WooCommerce product');
                }
            } else {
                // Create new product
                $result = $this->create_wc_product($product, $product_inventory, $product_price, $auto_publish, $import_images, $default_category, $category_mapping);
                // Check if result is a valid product ID and product exists
                if (is_int($result) && get_post($result) && get_post_type($result) === 'product') {
                    $synced_count++;
                    // Success: do not log as error
                } else {
                    $error_count++;
                    $last_error = is_wp_error($result) ? $result->get_error_message() : 'Failed to create WooCommerce product for ID: ' . $cova_id;
                    $this->log_error('Failed to create WooCommerce product. Result: ' . print_r($result, true));
                }
            }
        }
        
        // Log the results
        $this->log_sync_results(array(
            'synced' => $synced_count,
            'updated' => $updated_count,
            'fixed_numeric_skus' => $fixed_numeric_skus,
            'skipped' => $skipped_count,
            'errors' => $error_count,
            'total' => count($products),
            'cleared' => $clear_before_sync ? 'yes' : 'no'
        ));
        
        // Update last sync time
        update_option('cova_wc_last_sync_time', current_time('timestamp'));
        
        return true;
    }

    /**
     * Extract a proper SKU from Cova product data
     * 
     * @param array $product Cova product data
     * @return string The extracted SKU or empty string if none found
     */
    private function extract_proper_sku_from_product($product) {
        $sku = '';
        
        // Direct SKU fields - try these first
        $sku_fields = [
            'SKU', 'Sku', 'sku', 
            'CatalogSku', 'catalogSku', 'ItemLookupCode',
            'ItemCode', 'Code', 'ProductCode', 'LookupCode',
            'InventoryCode', 'Id'
        ];
        
        foreach ($sku_fields as $field) {
            if (isset($product[$field]) && !empty($product[$field])) {
                $sku = $product[$field];
                // Special handling: If the SKU looks like a GUID/UUID or a numeric ID, it's probably not a real SKU
                if (is_numeric($sku) && strlen($sku) >= 6 && substr($sku, 0, 3) == '100') {
                    $this->log_info("Found numeric ID-like SKU in $field: $sku - will keep looking for a better SKU");
                    continue; // This looks like an internal ID, not a real SKU, keep looking
                }
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sku)) {
                    $this->log_info("Found GUID-like SKU in $field: $sku - will keep looking for a better SKU");
                    continue; // This looks like a GUID, not a real SKU, keep looking
                }
                $this->log_info("Found SKU in $field: $sku");
                break;
            }
        }
        
        // If we found a good SKU, return it
        if (!empty($sku) && !is_numeric($sku) && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sku)) {
            return $sku;
        }
        
        // Check in Skus array if present
        if (isset($product['Skus']) && is_array($product['Skus']) && !empty($product['Skus'])) {
            foreach ($product['Skus'] as $skuItem) {
                if (isset($skuItem['Value']) && !empty($skuItem['Value'])) {
                    $sku = $skuItem['Value'];
                    $this->log_info("Found SKU in Skus array: $sku");
                    // Only use if it doesn't look like an internal ID
                    if (!is_numeric($sku) || strlen($sku) < 6) {
                        return $sku;
                    }
                }
            }
        }
        
        // Based on the screenshots, many of the SKUs are in alphanumeric format like Y5KFVL3L, KM3EWEWJ, etc.
        // Look for fields with values that match this pattern
        foreach ($product as $key => $value) {
            if (is_string($value) && !empty($value) && 
                !is_numeric($value) && 
                preg_match('/^[A-Z0-9]{5,12}$/i', $value) && 
                !preg_match('/COVA/i', $value)) {
                
                $sku = $value;
                $this->log_info("Found alphanumeric SKU-like string in $key: $sku");
                return $sku;
            }
        }
        
        // If we still haven't found a good SKU and we previously found a numeric ID, use it as last resort
        if (empty($sku)) {
            foreach ($sku_fields as $field) {
                if (isset($product[$field]) && !empty($product[$field])) {
                    $sku = $product[$field];
                    $this->log_info("Using fallback SKU from $field: $sku");
                    break;
                }
            }
        }
        
        return $sku;
    }
    
    /**
     * Get WooCommerce product ID by Cova ID
     *
     * @param string $cova_id Cova product ID
     * @return int|null WooCommerce product ID or null if not found
     */
    private function get_wc_product_id_by_cova_id($cova_id) {
        $args = array(
            'post_type' => 'product',
            'post_status' => array('publish', 'draft'),
            'meta_query' => array(
                array(
                    'key' => '_cova_product_id',
                    'value' => $cova_id,
                ),
            ),
            'fields' => 'ids',
            'posts_per_page' => 1,
        );
        
        $products = get_posts($args);
        
        if (!empty($products)) {
            return $products[0];
        }
        
        return null;
    }
    
    /**
     * Create a new WooCommerce product from Cova product data
     *
     * @param array $product Cova product data
     * @param array|null $inventory Cova inventory data
     * @param float $price Cova price value
     * @param bool $auto_publish Whether to publish the product automatically
     * @param bool $import_images Whether to import product images
     * @param int $default_category Default category ID
     * @param array $category_mapping Category mapping
     * @return int|WP_Error Product ID on success, WP_Error on failure
     */
    private function create_wc_product($product, $inventory, $price, $auto_publish, $import_images, $default_category, $category_mapping) {
        $name = isset($product['Name']) ? $product['Name'] : '';
        $description = isset($product['LongDescription']) ? $product['LongDescription'] : (isset($product['Description']) ? $product['Description'] : '');
        $short_description = isset($product['ShortDescription']) ? $product['ShortDescription'] : '';
        
        // Use our improved SKU extraction function
        $sku = $this->extract_proper_sku_from_product($product);
        
        $cova_id = isset($product['Id']) ? $product['Id'] : '';
        $cova_category = isset($product['Category']) ? $product['Category'] : '';
        $assets = isset($product['Assets']) ? $product['Assets'] : array();
        $hero_shot_uri = isset($product['HeroShotUri']) ? $product['HeroShotUri'] : null;
        
        if (empty($name) || empty($cova_id)) {
            return new WP_Error('invalid_product', __('Invalid product data', 'cova-integration'));
        }
        
        // Create product
        $wc_product = new WC_Product();
        $wc_product->set_name($name);
        $wc_product->set_description($description);
        $wc_product->set_short_description($short_description);
        
        // Set SKU - ensure it's not empty
        if (!empty($sku) && (!is_numeric($sku) || strlen($sku) < 6)) {
            $wc_product->set_sku($sku);
        } else {
            // If no SKU found, use Cova ID as a fallback SKU
            $wc_product->set_sku('COVA-' . $cova_id);
            // Log the missing SKU issue
            $this->log_error("No valid SKU found for product '$name' (ID: $cova_id), using COVA ID as fallback");
        }
        
        $wc_product->set_status($auto_publish ? 'publish' : 'draft');
        $wc_product->set_catalog_visibility('visible');
        $wc_product->set_sold_individually(false);
        $wc_product->set_virtual(false);
        
        // Set price - Cova prices may be in cents, so divide by 100 if > 100
        $price_value = floatval($price);
        
        if ($price_value > 0) {
            $wc_product->set_regular_price(number_format($price_value, 2, '.', ''));
        }
        
        // Set inventory
        if (!empty($inventory) && isset($inventory['QuantityOnHand'])) {
            $stock = intval($inventory['QuantityOnHand']);
            $wc_product->set_manage_stock(true);
            $wc_product->set_stock_quantity($stock);
            $wc_product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
        } else {
            $wc_product->set_manage_stock(false);
            $wc_product->set_stock_status('instock');
        }
        
        // Save product to get ID
        $wc_product_id = $wc_product->save();
        
        if (is_wp_error($wc_product_id)) {
            return $wc_product_id;
        }
        
        // Save Cova product ID as metadata
        update_post_meta($wc_product_id, '_cova_product_id', $cova_id);
        
        // Also save the SKU as metadata for future reference
        if (!empty($sku)) {
            update_post_meta($wc_product_id, '_cova_original_sku', $sku);
        }
        
        // Set category
        $category_id = '';
        
        if (!empty($cova_category) && isset($category_mapping[$cova_category])) {
            $category_id = $category_mapping[$cova_category];
        } elseif (!empty($default_category)) {
            $category_id = $default_category;
        }
        
        if (!empty($category_id)) {
            wp_set_object_terms($wc_product_id, intval($category_id), 'product_cat');
        }
        
        // Import images from Assets array
        if ($import_images && (!empty($assets) || !empty($hero_shot_uri))) {
            $this->import_product_images_from_assets($wc_product_id, $assets, $name, $hero_shot_uri);
        }
        
        return $wc_product_id;
    }
    
    /**
     * Update an existing WooCommerce product with Cova product data
     *
     * @param int $wc_product_id WooCommerce product ID
     * @param array $product Cova product data
     * @param array|null $inventory Cova inventory data
     * @param float $price Cova price value
     * @param bool $import_images Whether to import product images
     * @return bool|WP_Error True on success, WP_Error on failure
     */
    private function update_wc_product($wc_product_id, $product, $inventory, $price, $import_images) {
        $wc_product = wc_get_product($wc_product_id);
        
        if (!$wc_product) {
            return new WP_Error('product_not_found', __('WooCommerce product not found', 'cova-integration'));
        }
        
        $name = isset($product['Name']) ? $product['Name'] : '';
        $description = isset($product['LongDescription']) ? $product['LongDescription'] : (isset($product['Description']) ? $product['Description'] : '');
        $short_description = isset($product['ShortDescription']) ? $product['ShortDescription'] : '';
        
        // Use our improved SKU extraction function
        $sku = $this->extract_proper_sku_from_product($product);
        
        $assets = isset($product['Assets']) ? $product['Assets'] : array();
        $hero_shot_uri = isset($product['HeroShotUri']) ? $product['HeroShotUri'] : null;
        
        // Update basic info
        if (!empty($name)) {
            $wc_product->set_name($name);
        }
        if (!empty($description)) {
            $wc_product->set_description($description);
        }
        if (!empty($short_description)) {
            $wc_product->set_short_description($short_description);
        }
        
        // Update SKU - ensure it's not empty and not a numeric ID
        if (!empty($sku) && (!is_numeric($sku) || strlen($sku) < 6)) {
            $current_sku = $wc_product->get_sku();
            
            // Check if current SKU is a numeric ID (likely incorrect)
            $needs_sku_fix = is_numeric($current_sku) && strlen($current_sku) >= 6 && substr($current_sku, 0, 3) == '100';
            
            if (empty($current_sku) || $needs_sku_fix || $current_sku !== $sku) {
                $wc_product->set_sku($sku);
                $this->log_info('Updated SKU from ' . $current_sku . ' to ' . $sku . ' for product ID: ' . $wc_product_id);
            }
            
            // Update the stored original SKU
            update_post_meta($wc_product_id, '_cova_original_sku', $sku);
        } else {
            // If no SKU is found and current SKU is empty, use Cova ID as fallback
            $current_sku = $wc_product->get_sku();
            if (empty($current_sku)) {
                $cova_id = get_post_meta($wc_product_id, '_cova_product_id', true);
                if (!empty($cova_id)) {
                    $wc_product->set_sku('COVA-' . $cova_id);
                    // Log the missing SKU issue
                    $this->log_error("No valid SKU found for product during update (ID: $cova_id), using COVA ID as fallback");
                }
            }
        }
        
        // Set price - Cova prices may be in cents, so divide by 100 if > 100
        $price_value = floatval($price);
        
        if ($price_value > 0) {
            $wc_product->set_regular_price(number_format($price_value, 2, '.', ''));
        }
        
        // Update inventory
        if (!empty($inventory) && isset($inventory['QuantityOnHand'])) {
            $stock = intval($inventory['QuantityOnHand']);
            $wc_product->set_manage_stock(true);
            $wc_product->set_stock_quantity($stock);
            $wc_product->set_stock_status($stock > 0 ? 'instock' : 'outofstock');
        }
        
        // Save changes
        $wc_product->save();
        
        // Import images from Assets array
        if ($import_images && (!empty($assets) || !empty($hero_shot_uri))) {
            $this->import_product_images_from_assets($wc_product_id, $assets, $name, $hero_shot_uri);
        }
        
        return true;
    }
    
    /**
     * Import product images from COVA Assets array
     *
     * @param int $product_id WooCommerce product ID
     * @param array $assets COVA Assets array
     * @param string $product_name Product name for alt text fallback
     */
    private function import_product_images_from_assets($product_id, $assets, $product_name, $hero_shot_uri = null) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $gallery_ids = array();
        $featured_set = false;
        
        // Log start of import
        error_log('[COVA WooCommerce] Starting image import for product ID: ' . $product_id . ' with ' . count($assets) . ' assets and HeroShot: ' . ($hero_shot_uri ? 'Yes' : 'No'));
        
        // 1. Set HeroShotUri as featured image if available
        if (!empty($hero_shot_uri)) {
            // Skip igmetrix URLs which are known to return 500 errors
            if (strpos($hero_shot_uri, 'igmetrix.net') !== false) {
                error_log('[COVA WooCommerce] Skipping igmetrix.net hero image URL (known to return 500 errors): ' . $hero_shot_uri);
            } else {
                $hero_final_url = $this->get_final_url($hero_shot_uri);
                
                // Check if already attached by original or final URL
                $existing = $this->get_attachment_id_by_url($hero_shot_uri);
                if (!$existing) {
                    $existing = $this->get_attachment_id_by_url($hero_final_url);
                }
                
                if ($existing) {
                    set_post_thumbnail($product_id, $existing);
                    $featured_set = true;
                    error_log('[COVA WooCommerce] Using existing hero image (ID: ' . $existing . ') for: ' . $hero_final_url);
                } else {
                    // Download from final URL
                    $temp_file = $this->download_image_with_redirect($hero_shot_uri);
                    if ($temp_file && !is_wp_error($temp_file)) {
                        $file_array = array(
                            'name' => basename($hero_final_url),
                            'tmp_name' => $temp_file
                        );
                        
                        $attachment_id = media_handle_sideload($file_array, $product_id, $product_name);
                        if (!is_wp_error($attachment_id)) {
                            // Store both original and final URLs for tracking
                            update_post_meta($attachment_id, '_cova_original_url', $hero_shot_uri);
                            update_post_meta($attachment_id, '_cova_final_url', $hero_final_url);
                            update_post_meta($attachment_id, '_cova_heroshot_uri', $hero_shot_uri);
                            set_post_thumbnail($product_id, $attachment_id);
                            $featured_set = true;
                            error_log('[COVA WooCommerce] Successfully imported hero image (ID: ' . $attachment_id . ') from: ' . $hero_final_url);
                        } else {
                            error_log("[COVA WooCommerce] Failed to sideload HeroShotUri image: $hero_shot_uri | Final URL: $hero_final_url | Error: " . $attachment_id->get_error_message());
                            @unlink($temp_file);
                        }
                    } else {
                        $error_message = is_wp_error($temp_file) ? $temp_file->get_error_message() : "Failed to download image";
                        error_log("[COVA WooCommerce] Failed to download HeroShotUri image: $hero_shot_uri | Final URL: $hero_final_url | Error: " . $error_message);
                    }
                }
            }
        }
        
        // 2. Process Assets array
        error_log('[COVA WooCommerce] Processing ' . count($assets) . ' assets for product ID: ' . $product_id);
        foreach ($assets as $i => $asset) {
            $asset_id = isset($asset['AssetId']) ? $asset['AssetId'] : (isset($asset['Id']) ? $asset['Id'] : null);
            $alt = isset($asset['AltText']) ? $asset['AltText'] : $product_name;
            $uri = isset($asset['Uri']) ? $asset['Uri'] : null;
            $name = isset($asset['Name']) ? $asset['Name'] : null;
            $ext = isset($asset['FileExtension']) ? $asset['FileExtension'] : 'jpg';
            $width = 1200;
            $height = 1200;
            
            if (!$asset_id && !$uri) {
                error_log("[COVA WooCommerce] Missing AssetId and Uri in asset: " . print_r($asset, true));
                continue;
            }
            
            // Prefer Uri if available
            $image_url = null;
            if (!empty($uri)) {
                $image_url = $uri;
                // If Uri does not end with an image extension, append extension from Name
                if (!preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $image_url)) {
                    if ($name && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $name, $matches)) {
                        $image_url .= $matches[0];
                    } else if ($ext) {
                        $image_url .= '.' . $ext;
                    }
                }
            } elseif ($asset_id) {
                $image_url = "https://amsprod.blob.core.windows.net/assets/{$asset_id}_{$width}_{$height}.{$ext}";
            }
            
            if (!$image_url) continue;
            
            // Skip igmetrix URLs which are known to return 500 errors
            if (strpos($image_url, 'igmetrix.net') !== false) {
                error_log('[COVA WooCommerce] Skipping igmetrix.net asset URL (known to return 500 errors): ' . $image_url);
                continue;
            }
            
            // Get final URL
            $final_url = $this->get_final_url($image_url);
            error_log('[COVA WooCommerce] Asset URL for product ID ' . $product_id . ': ' . $image_url . ' redirects to: ' . $final_url);
            
            // Check if already attached by original or final URL
            $existing = $this->get_attachment_id_by_url($image_url);
            if (!$existing) {
                $existing = $this->get_attachment_id_by_url($final_url);
            }
            
            if ($existing) {
                if (!$featured_set) {
                    set_post_thumbnail($product_id, $existing);
                    $featured_set = true;
                    error_log('[COVA WooCommerce] Using existing image as featured (ID: ' . $existing . ') for: ' . $final_url);
                } else {
                    $gallery_ids[] = $existing;
                    error_log('[COVA WooCommerce] Using existing image as gallery (ID: ' . $existing . ') for: ' . $final_url);
                }
                continue;
            }
            
            // Download from final URL
            $temp_file = $this->download_image_with_redirect($image_url);
            if ($temp_file && !is_wp_error($temp_file)) {
                $file_array = array(
                    'name' => basename($final_url),
                    'tmp_name' => $temp_file
                );
                
                $attachment_id = media_handle_sideload($file_array, $product_id, $alt);
                if (!is_wp_error($attachment_id)) {
                    // Store both original and final URLs for tracking
                    update_post_meta($attachment_id, '_cova_original_url', $image_url);
                    update_post_meta($attachment_id, '_cova_final_url', $final_url);
                    update_post_meta($attachment_id, '_cova_asset_id', $asset_id);
                    update_post_meta($attachment_id, '_cova_asset_url', $image_url); // Keep for backward compatibility
                    
                    if (!$featured_set) {
                        set_post_thumbnail($product_id, $attachment_id);
                        $featured_set = true;
                        error_log('[COVA WooCommerce] Successfully imported image as featured (ID: ' . $attachment_id . ') from: ' . $final_url);
                    } else {
                        $gallery_ids[] = $attachment_id;
                        error_log('[COVA WooCommerce] Successfully imported image as gallery (ID: ' . $attachment_id . ') from: ' . $final_url);
                    }
                } else {
                    error_log("[COVA WooCommerce] Failed to sideload image: $image_url | Final URL: $final_url | Error: " . $attachment_id->get_error_message());
                    $this->log_error("[COVA WooCommerce] Failed to sideload image: $image_url | Final URL: $final_url | Error: " . $attachment_id->get_error_message());
                    @unlink($temp_file);
                }
            } else {
                $error_message = is_wp_error($temp_file) ? $temp_file->get_error_message() : "Failed to download image";
                error_log("[COVA WooCommerce] Failed to download image: $image_url | Final URL: $final_url | Error: " . $error_message);
                $this->log_error("[COVA WooCommerce] Failed to download image: $image_url | Final URL: $final_url | Error: " . $error_message);
            }
        }
        
        // Set gallery images
        if (!empty($gallery_ids)) {
            $gallery_ids_str = implode(',', $gallery_ids);
            update_post_meta($product_id, '_product_image_gallery', $gallery_ids_str);
            error_log('[COVA WooCommerce] Set gallery images for product ID ' . $product_id . ': ' . $gallery_ids_str);
        } else {
            error_log('[COVA WooCommerce] No gallery images set for product ID ' . $product_id);
        }
    }
    
    /**
     * Get the final URL after following all redirects using simple cURL approach
     *
     * @param string $url The initial URL to check
     * @return string The final URL, or original URL on failure
     */
    private function get_final_url($url) {
        if (empty($url) || !function_exists('curl_init')) {
            return $url; // Return original URL if cURL not available
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true); // We want headers
        curl_setopt($ch, CURLOPT_NOBODY, true); // We don't need body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10); // Limit redirects for safety
        curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Timeout after 15 seconds
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // Set proper headers
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36'
        ));
        
        curl_exec($ch);

        if (!curl_errno($ch)) {
            $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);
            return $final_url;
        } else {
            curl_close($ch);
            return $url; // Return original URL on error
        }
    }

    /**
     * Download image with support for HTTP redirects
     *
     * @param string $url Image URL
     * @return string|WP_Error Path to downloaded temp file or WP_Error on failure
     */
    private function download_image_with_redirect($url) {
        // First, get the final URL after following all redirects
        $final_url = $this->get_final_url($url);
        
        error_log('[COVA WooCommerce] Downloading image - Original: ' . $url . ' | Final: ' . $final_url);
        
        // Use WordPress HTTP API to download from the final URL
        $response = wp_remote_get($final_url, array(
            'timeout' => 30,
            'redirection' => 0, // Don't follow redirects since we already have the final URL
            'sslverify' => false,
            'headers' => array(
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36'
            )
        ));
        
        if (is_wp_error($response)) {
            error_log('[COVA WooCommerce] Failed to download image: ' . $response->get_error_message());
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('[COVA WooCommerce] Bad HTTP status: ' . $status_code . ' for URL: ' . $final_url);
            return new WP_Error('download_failed', 'Failed to download image. HTTP status: ' . $status_code);
        }
        
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            error_log('[COVA WooCommerce] Empty image data for URL: ' . $final_url);
            return new WP_Error('empty_image', 'Downloaded image is empty');
        }
        
        $temp_file = wp_tempnam($final_url);
        
        // Save the image data to a temporary file
        if (!file_put_contents($temp_file, $image_data)) {
            error_log('[COVA WooCommerce] Failed to save temp file for URL: ' . $final_url);
            return new WP_Error('file_save_failed', 'Could not write image to temporary file');
        }
        
        error_log('[COVA WooCommerce] Successfully downloaded image to: ' . $temp_file);
        return $temp_file;
    }
    
    /**
     * Get attachment ID by image URL (checks multiple URL metadata keys)
     *
     * @param string $url Image URL
     * @return int|null Attachment ID or null if not found
     */
    private function get_attachment_id_by_url($url) {
        global $wpdb;
        
        // Check multiple meta keys to find existing attachments
        $meta_keys = array(
            '_cova_final_url',      // New final URL key (highest priority)
            '_cova_original_url',   // New original URL key  
            '_cova_asset_url',      // Legacy key for backward compatibility
            '_cova_heroshot_uri'    // Hero shot URI key
        );
        
        foreach ($meta_keys as $meta_key) {
            $attachment = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s LIMIT 1", 
                $meta_key, 
                $url
            ));
            
            if (!empty($attachment)) {
                error_log('[COVA WooCommerce] Found existing attachment ID: ' . $attachment[0] . ' for URL: ' . $url . ' (meta key: ' . $meta_key . ')');
                return $attachment[0];
            }
        }
        
        return null;
    }
    
    /**
     * Log sync results
     *
     * @param array $results Sync results
     */
    private function log_sync_results($results) {
        $logs = get_option('cova_wc_sync_logs', array());
        
        $logs[] = array(
            'time' => current_time('mysql'),
            'results' => $results,
        );
        
        // Keep only the last 50 log entries
        if (count($logs) > 50) {
            $logs = array_slice($logs, -50);
        }
        
        update_option('cova_wc_sync_logs', $logs);
    }
    
    /**
     * Sync selected products to WooCommerce
     *
     * @param array $product_ids Array of Cova product IDs to sync
     * @param string &$last_error Reference to store the last error message
     * @param int $specific_category_id Optional specific category ID to use
     * @return bool True on success, false on failure
     */
    public function sync_selected_products($product_ids, &$last_error = null, $specific_category_id = 0) {
        global $wpdb;
        if (!class_exists('WooCommerce')) {
            $this->log_error('WooCommerce is not installed or activated.');
            $last_error = 'WooCommerce is not installed or activated.';
            return false;
        }
        if (empty($product_ids)) {
            $this->log_error('No products selected for sync.');
            $last_error = 'No products selected for sync.';
            return false;
        }
        // Get settings
        $auto_publish = get_option('cova_integration_wc_auto_publish', false);
        $import_images = get_option('cova_integration_wc_import_images', true);
        $category_mapping = get_option('cova_integration_wc_category_mapping', array());
        $default_category = get_option('cova_integration_wc_default_category', '');
        
        $success_count = 0;
        $updated_count = 0;
        $error_count = 0;
        $last_error = null;
        
        foreach ($product_ids as $product_id) {
            // Log the sync attempt
            $this->log_info('Attempting to sync product ID: ' . $product_id);
            
            // First try to get product from local database
            $table_name = $wpdb->prefix . 'cova_products';
            $product_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE product_id = %s",
                $product_id
            ), ARRAY_A);
            
            if (!$product_data) {
                $this->log_error('Product not found in local database for ID: ' . $product_id);
                $last_error = 'Product not found in local database for ID: ' . $product_id;
                $error_count++;
                continue;
            }
            
            // Make sure we have the raw data
            $original_data = isset($product_data['data']) ? $product_data['data'] : null;
            
            // Decode the stored JSON data
            $product_data = json_decode($product_data['data'], true);
            if (empty($product_data)) {
                $this->log_error('Invalid product data in database for ID: ' . $product_id);
                $last_error = 'Invalid product data in database for ID: ' . $product_id;
                $error_count++;
                continue;
            }
            
            // Log the top-level product data keys for debugging
            $keys = array_keys($product_data);
            $this->log_info('Product data keys for ID ' . $product_id . ': ' . implode(', ', $keys));
            
            // Use our improved SKU extraction function
            $sku = $this->extract_proper_sku_from_product($product_data);
            
            // Log the found SKU
            if (!empty($sku)) {
                $this->log_info('Found SKU for product ID ' . $product_id . ': ' . $sku);
            } else {
                $this->log_error('No SKU found for product ID ' . $product_id);
            }
            
            // Extract category from various possible locations in the product data
            $cova_category = '';
            if (isset($product_data['ClassificationName'])) {
                $cova_category = $product_data['ClassificationName'];
            } elseif (isset($product_data['ClassificationPath'])) {
                $cova_category = $product_data['ClassificationPath'];
            } elseif (isset($product_data['Category'])) {
                $cova_category = $product_data['Category'];
            } elseif (isset($product_data['category'])) {
                $cova_category = $product_data['category'];
            } elseif (isset($product_data['Categories']) && is_array($product_data['Categories'])) {
                $cova_category = $product_data['Categories'][0] ?? '';
            } elseif (isset($product_data['categories']) && is_array($product_data['categories'])) {
                $cova_category = $product_data['categories'][0] ?? '';
            } elseif (isset($product_data['Classifications']) && is_array($product_data['Classifications'])) {
                // Extract the category name from the first classification
                if (!empty($product_data['Classifications'][0]) && isset($product_data['Classifications'][0]['Name'])) {
                    $cova_category = $product_data['Classifications'][0]['Name'];
                }
            }
            
            // Map ProductId to Id if needed
            if (isset($product_data['ProductId']) && !isset($product_data['Id'])) {
                $product_data['Id'] = $product_data['ProductId'];
            }
            
            // Map MasterProductName or Name if needed
            if (isset($product_data['Name'])) {
                // already present
            } elseif (isset($product_data['MasterProductName'])) {
                $product_data['Name'] = $product_data['MasterProductName'];
            }
            
            // Get inventory from local database
            $inventory_table = $wpdb->prefix . 'cova_inventory';
            $inventory = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $inventory_table WHERE product_id = %s ORDER BY last_sync DESC LIMIT 1",
                $product_id
            ), ARRAY_A);
            
            if (!$inventory) {
                $this->log_error('Warning: No inventory found for product ID: ' . $product_id);
            }
            
            // Get price from local database
            $prices_table = $wpdb->prefix . 'cova_prices';
            $price_data = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $prices_table WHERE catalog_item_id = %s ORDER BY last_sync DESC LIMIT 1",
                $product_id
            ), ARRAY_A);
            
            $price = $price_data ? $price_data['regular_price'] : 0;
            
            // Check if product already exists in WooCommerce
            $wc_product_id = $this->get_wc_product_id_by_cova_id($product_id);
            
            if ($wc_product_id) {
                // Get the current product
                $wc_product = wc_get_product($wc_product_id);
                $current_sku = $wc_product ? $wc_product->get_sku() : '';
                
                $this->log_info('Updating existing WooCommerce product ID: ' . $wc_product_id . ' with current SKU: ' . $current_sku);
                
                // Update existing product
                $result = $this->update_wc_product($wc_product_id, $product_data, $inventory, $price, $import_images);
                if ($result) {
                    $updated_count++;
                    $this->log_info('Successfully updated WooCommerce product ID: ' . $wc_product_id);
                } else {
                    $error_count++;
                    $last_error = 'Failed to update WooCommerce product for ID: ' . $product_id;
                    $this->log_error('Failed to update WooCommerce product');
                }
            } else {
                $this->log_info('Creating new WooCommerce product for Cova ID: ' . $product_id);
                
                // For individual sync, use the specific category if provided, otherwise use category mapping
                $category_to_use = $default_category;
                
                if ($specific_category_id > 0) {
                    $category_to_use = $specific_category_id;
                } elseif (!empty($cova_category)) {
                    if (isset($category_mapping[$cova_category])) {
                        $category_to_use = $category_mapping[$cova_category];
                    }
                }
                
                // Ensure the SKU is set in the product data before creation
                if (!empty($sku)) {
                    if (!isset($product_data['SKU']) || empty($product_data['SKU'])) {
                        $product_data['SKU'] = $sku;
                        $this->log_info('Set SKU in product data before creation: ' . $sku);
                    }
                }
                
                // Create new product
                $result = $this->create_wc_product($product_data, $inventory, $price, $auto_publish, $import_images, $category_to_use, $category_mapping);
                
                // Check if result is a valid product ID and product exists
                if (is_int($result) && get_post($result) && get_post_type($result) === 'product') {
                    $success_count++;
                    $this->log_info('Successfully created WooCommerce product ID: ' . $result);
                    
                    // Double-check that the SKU was set correctly
                    $new_product = wc_get_product($result);
                    if ($new_product) {
                        $final_sku = $new_product->get_sku();
                        if (empty($final_sku) || $final_sku !== $sku) {
                            $this->log_error('SKU mismatch after creation. Expected: ' . $sku . ', Got: ' . $final_sku);
                            if (!empty($sku)) {
                                $new_product->set_sku($sku);
                                $new_product->save();
                                $this->log_info('Fixed SKU after creation to: ' . $sku);
                            }
                        }
                    }
                } else {
                    $error_count++;
                    $last_error = is_wp_error($result) ? $result->get_error_message() : 'Failed to create WooCommerce product for ID: ' . $product_id;
                    $this->log_error('Failed to create WooCommerce product. Result: ' . print_r($result, true));
                }
            }
        }
        
        $this->log_sync_results(array(
            'success' => $success_count,
            'updated' => $updated_count,
            'error' => $error_count,
            'total' => count($product_ids),
            'individual_sync' => true
        ));
        
        return ($success_count > 0 || $updated_count > 0);
    }
    
    /**
     * Log error message
     *
     * @param string $message Error message
     */
    private function log_error($message) {
        global $wpdb;
        
        // Log to error log
        error_log('Cova WooCommerce Error: ' . $message);
        
        // Log to database
        $table_name = $wpdb->prefix . 'cova_error_logs';
        $wpdb->insert(
            $table_name,
            array(
                'message' => $message,
                'type' => 'error',
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Process inventory webhook from Cova API
     * Handles real-time inventory updates and updates WooCommerce product stock
     * 
     * This webhook endpoint receives inventory updates from Cova in real-time.
     * When a product's inventory changes in Cova (especially when it reaches zero),
     * this webhook immediately updates the WooCommerce product stock to prevent overselling.
     * 
     * The expected webhook format depends on the Cova API version, but this handler
     * is designed to be flexible and handle multiple possible payload formats.
     * 
     * For security, the webhook can be configured with a secret key that Cova will use
     * to sign the request. This endpoint verifies that signature before processing.
     */
    public function process_inventory_webhook() {
        // Log the webhook request
        $this->log_info('Received inventory webhook request');
        
        // Verify webhook authenticity
        $webhook_secret = get_option('cova_integration_webhook_secret', '');
        $signature = isset($_SERVER['HTTP_X_COVA_SIGNATURE']) ? sanitize_text_field($_SERVER['HTTP_X_COVA_SIGNATURE']) : '';
        
        // Get the raw POST data
        $payload = file_get_contents('php://input');
        
        // If webhook secret is configured, verify signature
        if (!empty($webhook_secret) && !empty($signature)) {
            $calculated_signature = hash_hmac('sha256', $payload, $webhook_secret);
            
            if (!hash_equals($calculated_signature, $signature)) {
                $this->log_error('Invalid webhook signature');
                wp_send_json_error('Invalid signature', 403);
                return;
            }
        }
        
        // Parse the payload
        $data = json_decode($payload, true);
        
        if (empty($data)) {
            $this->log_error('Empty or invalid webhook payload');
            wp_send_json_error('Invalid payload', 400);
            return;
        }
        
        // Log the payload for debugging
        $this->log_info('Processing inventory webhook payload', array(
            'payload_sample' => json_encode(array_slice($data, 0, 5))
        ));
        
        // Process inventory updates
        $this->process_realtime_inventory_updates($data);
        
        // Send success response
        wp_send_json_success('Inventory updates processed');
    }
    
    /**
     * Process real-time inventory updates and update WooCommerce products
     * 
     * This is the core function that processes inventory data from Cova and
     * updates both the local database and WooCommerce products in real-time.
     * 
     * The function handles the following steps:
     * 1. Extracts inventory items from various possible payload formats
     * 2. For each inventory item:
     *    - Identifies the product by its ID
     *    - Extracts quantity information
     *    - Updates the inventory record in the database
     *    - Updates the product's stock status in the products table
     *    - Updates the corresponding WooCommerce product if it exists
     * 
     * For WooCommerce products, it:
     * - Sets the exact stock quantity
     * - Updates the stock status to "in stock" or "out of stock" based on quantity
     * - Saves the product to trigger WooCommerce's stock management
     * 
     * This ensures that when a product sells out in the physical store,
     * it immediately becomes unavailable in the online store as well.
     * 
     * @param array $data Inventory data from webhook
     * @return bool True on success, false on failure
     */
    private function process_realtime_inventory_updates($data) {
        global $wpdb;
        
        // Get inventory items from the payload
        $inventory_items = array();
        
        // Handle different payload formats
        if (isset($data['Items'])) {
            $inventory_items = $data['Items'];
        } elseif (isset($data['inventory'])) {
            $inventory_items = $data['inventory'];
        } elseif (isset($data['Inventory'])) {
            $inventory_items = $data['Inventory'];
        } elseif (isset($data['item'])) {
            // Single item update
            $inventory_items = array($data['item']);
        } elseif (is_array($data) && isset($data[0])) {
            // Array of inventory items
            $inventory_items = $data;
        }
        
        if (empty($inventory_items)) {
            $this->log_error('No inventory items found in webhook payload');
            return false;
        }
        
        $updated_count = 0;
        $products_table = $wpdb->prefix . 'cova_products';
        $inventory_table = $wpdb->prefix . 'cova_inventory';
        
        foreach ($inventory_items as $item) {
            // Extract product ID from various possible fields
            $product_id = null;
            if (!empty($item['ProductId'])) {
                $product_id = $item['ProductId'];
            } elseif (!empty($item['CatalogItemId'])) {
                $product_id = $item['CatalogItemId'];
            } elseif (!empty($item['Id'])) {
                $product_id = $item['Id'];
            } elseif (!empty($item['EntityId'])) {
                $product_id = $item['EntityId'];
            }
            
            if (empty($product_id)) {
                continue;
            }
            
            // Extract quantity information
            $quantity = 0;
            if (isset($item['QuantityOnHand'])) {
                $quantity = intval($item['QuantityOnHand']);
            } elseif (isset($item['Quantity'])) {
                $quantity = intval($item['Quantity']);
            } elseif (isset($item['StockCount'])) {
                $quantity = intval($item['StockCount']);
            } elseif (isset($item['OnHand'])) {
                $quantity = intval($item['OnHand']);
            } elseif (isset($item['InStockQuantity'])) {
                $quantity = intval($item['InStockQuantity']);
            }
            
            // Get location ID
            $location_id = isset($item['LocationId']) ? $item['LocationId'] : $this->api_client->location_id;
            
            // Determine available and reserved quantities
            $available = isset($item['AvailableQuantity']) ? intval($item['AvailableQuantity']) : $quantity;
            $reserved = isset($item['ReservedQuantity']) ? intval($item['ReservedQuantity']) : 0;
            if (isset($item['OnOrderQuantity'])) {
                $reserved = intval($item['OnOrderQuantity']);
            }
            
            // Create inventory ID
            $inventory_id = isset($item['Id']) ? $item['Id'] : md5($product_id . '_' . $location_id);
            
            // Update the inventory in our database
            $current_time = current_time('mysql');
            
            // Check if inventory record exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $inventory_table WHERE inventory_id = %s",
                $inventory_id
            ));
            
            if ($exists) {
                // Update existing record
                $wpdb->update(
                    $inventory_table,
                    array(
                        'quantity' => $quantity,
                        'available_quantity' => $available,
                        'reserved_quantity' => $reserved,
                        'data' => json_encode($item),
                        'last_sync' => $current_time
                    ),
                    array('inventory_id' => $inventory_id)
                );
            } else {
                // Insert new record
                $wpdb->insert(
                    $inventory_table,
                    array(
                        'inventory_id' => $inventory_id,
                        'product_id' => $product_id,
                        'location_id' => $location_id,
                        'quantity' => $quantity,
                        'available_quantity' => $available,
                        'reserved_quantity' => $reserved,
                        'data' => json_encode($item),
                        'last_sync' => $current_time
                    )
                );
            }
            
            // Update the products table stock status
            $wpdb->update(
                $products_table,
                array(
                    'is_in_stock' => ($quantity > 0) ? 1 : 0,
                    'stock_quantity' => $quantity,
                    'last_sync' => $current_time
                ),
                array('product_id' => $product_id)
            );
            
            // Now update the corresponding WooCommerce product if it exists
            $wc_product_id = $this->get_wc_product_id_by_cova_id($product_id);
            
            if ($wc_product_id) {
                $wc_product = wc_get_product($wc_product_id);
                
                if ($wc_product) {
                    // Update stock status and quantity
                    $wc_product->set_stock_quantity($quantity);
                    $wc_product->set_stock_status($quantity > 0 ? 'instock' : 'outofstock');
                    $wc_product->save();
                    
                    $this->log_info("Updated WooCommerce product stock for ID: $wc_product_id (Cova ID: $product_id)", array(
                        'quantity' => $quantity,
                        'stock_status' => $quantity > 0 ? 'instock' : 'outofstock'
                    ));
                    
                    $updated_count++;
                }
            }
        }
        
        $this->log_info("Real-time inventory sync completed. Updated $updated_count WooCommerce products");
        
        return true;
    }
    
    /**
     * Log informational message
     *
     * @param string $message Log message
     * @param array $context Additional context data
     */
    private function log_info($message, $context = array()) {
        global $wpdb;
        
        $log_message = '[' . date('Y-m-d H:i:s') . '] INFO: ' . $message;
        error_log('Cova Integration: ' . $log_message);
        
        // Only log to database if debug logging is enabled
        if (get_option('cova_integration_debug_logging', false)) {
            $table_name = $wpdb->prefix . 'cova_error_logs';
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'message' => $message,
                        'context' => !empty($context) ? json_encode($context) : null,
                        'type' => 'info',
                        'created_at' => current_time('mysql')
                    )
                );
            }
        }
    }
} 