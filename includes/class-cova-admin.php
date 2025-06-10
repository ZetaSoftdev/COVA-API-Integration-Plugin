<?php
/**
 * Admin settings page for Cova Integration
 *
 * @since 1.0.0
 */
class Cova_Admin {
    /**
     * API Client instance
     *
     * @var Cova_API_Client
     */
    private $api_client;
    
    /**
     * Initialize the class
     */
    public function __construct() {
        $this->api_client = new Cova_API_Client();
        
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_cova_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_cova_clear_all_products', array($this, 'ajax_clear_all_products'));
        add_action('wp_ajax_cova_force_detailed_sync', array($this, 'ajax_force_detailed_sync'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_cova_force_sync', array($this, 'ajax_force_sync'));
        add_action('wp_ajax_cova_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_cova_process_single_image', array($this, 'ajax_process_single_image'));
        add_action('wp_ajax_cova_process_all_images', array($this, 'ajax_process_all_images'));
        add_action('wp_ajax_cova_reset_processed_images', array($this, 'ajax_reset_processed_images'));
        add_action('wp_ajax_cova_debug_image_process', array($this, 'ajax_debug_image_process'));
        add_action('wp_ajax_cova_check_image_redirects', array($this, 'ajax_check_image_redirects'));
        add_filter('manage_cova_products_columns', array($this, 'add_product_image_column'));
        add_action('manage_cova_products_custom_column', array($this, 'display_product_image_column'), 10, 2);
    }
    
    /**
     * Add admin menu pages
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Cova Integration', 'cova-integration'),
            __('Cova Integration', 'cova-integration'),
            'manage_options',
            'cova-integration',
            array($this, 'display_dashboard_page'),
            'dashicons-store',
            25
        );
        
        add_submenu_page(
            'cova-integration',
            __('Dashboard', 'cova-integration'),
            __('Dashboard', 'cova-integration'),
            'manage_options',
            'cova-integration',
            array($this, 'display_dashboard_page')
        );
        
        add_submenu_page(
            'cova-integration',
            __('Settings', 'cova-integration'),
            __('Settings', 'cova-integration'),
            'manage_options',
            'cova-integration-settings',
            array($this, 'display_settings_page')
        );
        
        add_submenu_page(
            'cova-integration',
            __('Products', 'cova-integration'),
            __('Products', 'cova-integration'),
            'manage_options',
            'cova-integration-products',
            array($this, 'display_products_page')
        );
        
        add_submenu_page(
            'cova-integration',
            __('Images', 'cova-integration'),
            __('Images', 'cova-integration'),
            'manage_options',
            'cova-images',
            array($this, 'display_images_page')
        );
        
        // WooCommerce integration is added by the Cova_WooCommerce class
        
        add_submenu_page(
            'cova-integration',
            __('Error Logs', 'cova-integration'),
            __('Error Logs', 'cova-integration'),
            'manage_options',
            'cova-integration-logs',
            array($this, 'display_logs_page')
        );
        
        // API Tester page
        add_submenu_page(
            'cova-integration',
            __('API Tester', 'cova-integration'),
            __('API Tester', 'cova-integration'),
            'manage_options',
            'cova-integration-api-tester',
            array($this, 'display_api_tester_page')
        );
        
        // Add new debug submenu
        add_submenu_page(
            'cova-integration',
            __('Debug Tools', 'cova-integration'),
            __('Debug Tools', 'cova-integration'),
            'manage_options',
            'cova-debug-tools',
            array($this, 'display_debug_tools_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('cova_integration_settings', 'cova_integration_client_id', array(
            'default' => 'SeatoSky.SeatoSky',
        ));
        register_setting('cova_integration_settings', 'cova_integration_client_secret', array(
            'sanitize_callback' => array($this, 'encrypt_sensitive_data'),
            'default' => 'asIhtUi91ZIwPXHRnwfgMLLz',
        ));
        register_setting('cova_integration_settings', 'cova_integration_username', array(
            'default' => 'SeatoSky.COVA.APIUser.SeatoSky',
        ));
        register_setting('cova_integration_settings', 'cova_integration_password', array(
            'sanitize_callback' => array($this, 'encrypt_sensitive_data'),
            'default' => 'XrE4XIU@2%',
        ));
        register_setting('cova_integration_settings', 'cova_integration_company_id', array(
            'default' => '293892',
        ));
        register_setting('cova_integration_settings', 'cova_integration_location_id', array(
            'default' => '293894',
        ));
        register_setting('cova_integration_settings', 'cova_integration_sync_interval', array(
            'default' => 15,
        ));
        
        // Add integrator ID for order submission
        register_setting('cova_integration_settings', 'cova_integration_integrator_id', array(
            'default' => 'e159d785-9a75-4686-8e12-69bb4fb0e992',
        ));
        
        add_settings_section(
            'cova_integration_api_settings',
            __('API Credentials', 'cova-integration'),
            array($this, 'api_settings_section_callback'),
            'cova_integration_settings'
        );
        
        add_settings_field(
            'cova_integration_client_id',
            __('Client ID', 'cova-integration'),
            array($this, 'client_id_render'),
            'cova_integration_settings',
            'cova_integration_api_settings'
        );
        
        add_settings_field(
            'cova_integration_client_secret',
            __('Client Secret', 'cova-integration'),
            array($this, 'client_secret_render'),
            'cova_integration_settings',
            'cova_integration_api_settings'
        );
        
        add_settings_field(
            'cova_integration_username',
            __('Username', 'cova-integration'),
            array($this, 'username_render'),
            'cova_integration_settings',
            'cova_integration_api_settings'
        );
        
        add_settings_field(
            'cova_integration_password',
            __('Password', 'cova-integration'),
            array($this, 'password_render'),
            'cova_integration_settings',
            'cova_integration_api_settings'
        );
        
        add_settings_field(
            'cova_integration_company_id',
            __('Company ID', 'cova-integration'),
            array($this, 'company_id_render'),
            'cova_integration_settings',
            'cova_integration_api_settings'
        );
        
        add_settings_field(
            'cova_integration_location_id',
            __('Location ID', 'cova-integration'),
            array($this, 'location_id_render'),
            'cova_integration_settings',
            'cova_integration_api_settings'
        );
        
        add_settings_field(
            'cova_integration_integrator_id',
            __('Integrator ID', 'cova-integration'),
            array($this, 'integrator_id_render'),
            'cova_integration_settings',
            'cova_integration_api_settings'
        );
        
        add_settings_section(
            'cova_integration_general_settings',
            __('General Settings', 'cova-integration'),
            array($this, 'general_settings_section_callback'),
            'cova_integration_settings'
        );
        
        add_settings_field(
            'cova_integration_sync_interval',
            __('Sync Interval (minutes)', 'cova-integration'),
            array($this, 'sync_interval_render'),
            'cova_integration_settings',
            'cova_integration_general_settings'
        );
    }
    
    /**
     * Encrypt sensitive data
     *
     * @param string $value Value to encrypt
     * @return string Encrypted value
     */
    public function encrypt_sensitive_data($value) {
        if (empty($value)) {
            return '';
        }
        
        // For a real implementation, you should use a proper encryption method
        // This is a simple placeholder - in production, use WordPress's encryption functions
        // or a dedicated encryption library
        return base64_encode($value);
    }
    
    /**
     * Decrypt sensitive data
     *
     * @param string $value Value to decrypt
     * @return string Decrypted value
     */
    public function decrypt_sensitive_data($value) {
        if (empty($value)) {
            return '';
        }
        
        // Matching the placeholder encryption above
        return base64_decode($value);
    }
    
    /**
     * API Settings section callback
     */
    public function api_settings_section_callback() {
        echo '<p>' . __('Enter your Cova API credentials below. These are required to connect to the Cova API.', 'cova-integration') . '</p>';
    }
    
    /**
     * General Settings section callback
     */
    public function general_settings_section_callback() {
        echo '<p>' . __('Configure general plugin settings.', 'cova-integration') . '</p>';
    }
    
    /**
     * Client ID field render
     */
    public function client_id_render() {
        $client_id = get_option('cova_integration_client_id');
        echo '<input type="text" name="cova_integration_client_id" value="' . esc_attr($client_id) . '" class="regular-text">';
    }
    
    /**
     * Client Secret field render
     */
    public function client_secret_render() {
        $client_secret = get_option('cova_integration_client_secret');
        $client_secret = $this->decrypt_sensitive_data($client_secret);
        echo '<input type="password" name="cova_integration_client_secret" value="' . esc_attr($client_secret) . '" class="regular-text">';
    }
    
    /**
     * Username field render
     */
    public function username_render() {
        $username = get_option('cova_integration_username');
        echo '<input type="text" name="cova_integration_username" value="' . esc_attr($username) . '" class="regular-text">';
    }
    
    /**
     * Password field render
     */
    public function password_render() {
        $password = get_option('cova_integration_password');
        $password = $this->decrypt_sensitive_data($password);
        echo '<input type="password" name="cova_integration_password" value="' . esc_attr($password) . '" class="regular-text">';
    }
    
    /**
     * Company ID field render
     */
    public function company_id_render() {
        $company_id = get_option('cova_integration_company_id');
        echo '<input type="text" name="cova_integration_company_id" value="' . esc_attr($company_id) . '" class="regular-text">';
    }
    
    /**
     * Location ID field render
     */
    public function location_id_render() {
        $location_id = get_option('cova_integration_location_id');
        echo '<input type="text" name="cova_integration_location_id" value="' . esc_attr($location_id) . '" class="regular-text">';
        echo '<p class="description">' . __('Enter the Location ID of your Cova retail location.', 'cova-integration') . '</p>';
    }
    
    /**
     * Integrator ID field render
     */
    public function integrator_id_render() {
        $integrator_id = get_option('cova_integration_integrator_id');
        echo '<input type="text" name="cova_integration_integrator_id" value="' . esc_attr($integrator_id) . '" class="regular-text">';
        echo '<p class="description">' . __('Required for order submission to Cova. This ID identifies your integration with Cova.', 'cova-integration') . '</p>';
    }
    
    /**
     * Sync Interval field render
     */
    public function sync_interval_render() {
        $sync_interval = get_option('cova_integration_sync_interval', 15);
        echo '<input type="number" min="5" step="1" name="cova_integration_sync_interval" value="' . esc_attr($sync_interval) . '">';
    }
    
    /**
     * Display dashboard page
     */
    public function display_dashboard_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="cova-workflow-guide">
                <h2><?php _e('Getting Started with Cova Integration', 'cova-integration'); ?></h2>
                <p><?php _e('Follow these steps to integrate your Cova products with your WordPress site:', 'cova-integration'); ?></p>
                
                <div class="cova-workflow-steps">
                    <div class="cova-workflow-step">
                        <div class="cova-step-number">1</div>
                        <div class="cova-step-content">
                            <h3><?php _e('Configure API Settings', 'cova-integration'); ?></h3>
                            <p><?php _e('Enter your Cova API credentials in the Settings tab. Make sure to test the connection.', 'cova-integration'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=cova-integration-settings'); ?>" class="button"><?php _e('Go to Settings', 'cova-integration'); ?></a>
                        </div>
                    </div>
                    
                    <div class="cova-workflow-step">
                        <div class="cova-step-number">2</div>
                        <div class="cova-step-content">
                            <h3><?php _e('Sync Products from Cova', 'cova-integration'); ?></h3>
                            <p><?php _e('Sync your product catalog from Cova. This will fetch all products, pricing, and inventory data.', 'cova-integration'); ?></p>
                            <button id="cova-force-sync" class="button button-primary"><?php _e('Sync Products Now', 'cova-integration'); ?></button>
                        </div>
                    </div>
                    
                    <div class="cova-workflow-step">
                        <div class="cova-step-number">3</div>
                        <div class="cova-step-content">
                            <h3><?php _e('Select Products to Display/Sync', 'cova-integration'); ?></h3>
                            <p><?php _e('Go to the Products tab to view all your Cova products and select which ones to display on your site.', 'cova-integration'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=cova-integration-products'); ?>" class="button"><?php _e('Manage Products', 'cova-integration'); ?></a>
                        </div>
                    </div>
                    
                    <div class="cova-workflow-step">
                        <div class="cova-step-number">4</div>
                        <div class="cova-step-content">
                            <h3><?php _e('Sync with WooCommerce (Optional)', 'cova-integration'); ?></h3>
                            <p><?php _e('If you use WooCommerce, you can sync your Cova products to WooCommerce products.', 'cova-integration'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=cova-integration-woocommerce'); ?>" class="button"><?php _e('WooCommerce Settings', 'cova-integration'); ?></a>
                        </div>
                    </div>
                    
                    <div class="cova-workflow-step">
                        <div class="cova-step-number">5</div>
                        <div class="cova-step-content">
                            <h3><?php _e('Display Products on Your Site', 'cova-integration'); ?></h3>
                            <p><?php _e('Use shortcodes to display your products on any page or post:', 'cova-integration'); ?></p>
                            <code>[cova_products]</code>
                            <p><?php _e('With options:', 'cova-integration'); ?></p>
                            <code>[cova_products category="flower" limit="10" columns="3"]</code>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="cova-dashboard-grid">
                <div class="cova-dashboard-card">
                    <h2><?php _e('API Connection Status', 'cova-integration'); ?></h2>
                    <div id="cova-connection-status">
                        <?php
                        $token = $this->api_client->get_token();
                        if (is_wp_error($token)) {
                            echo '<div class="cova-status-error">';
                            echo '<span class="dashicons dashicons-no"></span> ';
                            echo __('Not Connected', 'cova-integration');
                            echo '</div>';
                        } else {
                            echo '<div class="cova-status-success">';
                            echo '<span class="dashicons dashicons-yes"></span> ';
                            echo __('Connected', 'cova-integration');
                            echo '</div>';
                        }
                        ?>
                    </div>
                    <button id="cova-test-connection" class="button button-primary"><?php _e('Test Connection', 'cova-integration'); ?></button>
                </div>
                
                <div class="cova-dashboard-card">
                    <h2><?php _e('Data Sync Status', 'cova-integration'); ?></h2>
                    <p><?php _e('Last sync:', 'cova-integration'); ?> 
                        <?php 
                        $last_sync = get_option('cova_last_sync_time', 0);
                        if ($last_sync > 0) {
                            echo human_time_diff($last_sync, current_time('timestamp')) . ' ' . __('ago', 'cova-integration');
                        } else {
                            _e('Never', 'cova-integration');
                        }
                        ?>
                    </p>
                    <p><?php _e('Next scheduled sync:', 'cova-integration'); ?> 
                        <?php 
                        $next_sync = wp_next_scheduled('cova_integration_sync_event');
                        if ($next_sync) {
                            echo human_time_diff(current_time('timestamp'), $next_sync) . ' ' . __('from now', 'cova-integration');
                        } else {
                            _e('Not scheduled', 'cova-integration');
                        }
                        ?>
                    </p>
                    <button id="cova-force-sync" class="button button-primary"><?php _e('Sync Now', 'cova-integration'); ?></button>
                </div>
                
                <div class="cova-dashboard-card">
                    <h2><?php _e('Product Stats', 'cova-integration'); ?></h2>
                    <?php
                    global $wpdb;
                    $products_table = $wpdb->prefix . 'cova_products';
                    $total_products = $wpdb->get_var("SELECT COUNT(*) FROM $products_table");
                    $active_products = $wpdb->get_var("SELECT COUNT(*) FROM $products_table WHERE is_archived = 0");
                    
                    // Count WooCommerce synced products
                    $wc_synced_products = 0;
                    if (class_exists('WooCommerce')) {
                        $wc_synced_products = $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_cova_product_id'");
                    }
                    ?>
                    <p><?php echo sprintf(__('Total Products: %d', 'cova-integration'), $total_products); ?></p>
                    <p><?php echo sprintf(__('Active Products: %d', 'cova-integration'), $active_products); ?></p>
                    <p><?php echo sprintf(__('WooCommerce Synced: %d', 'cova-integration'), $wc_synced_products); ?></p>
                    <a href="<?php echo admin_url('admin.php?page=cova-integration-products'); ?>" class="button"><?php _e('View Products', 'cova-integration'); ?></a>
                </div>
            </div>
            
            <style>
                .cova-workflow-guide {
                    margin-bottom: 30px;
                    background: #fff;
                    padding: 20px;
                    border-radius: 5px;
                    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                }
                
                .cova-workflow-steps {
                    margin-top: 20px;
                }
                
                .cova-workflow-step {
                    display: flex;
                    margin-bottom: 15px;
                    border-bottom: 1px solid #f0f0f0;
                    padding-bottom: 15px;
                }
                
                .cova-step-number {
                    width: 30px;
                    height: 30px;
                    background: #2271b1;
                    color: #fff;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    font-weight: bold;
                    margin-right: 15px;
                    flex-shrink: 0;
                }
                
                .cova-step-content {
                    flex-grow: 1;
                }
                
                .cova-step-content h3 {
                    margin-top: 0;
                    margin-bottom: 10px;
                }
                
                .cova-dashboard-grid {
                    display: grid;
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                    gap: 20px;
                }
                
                .cova-dashboard-card {
                    background: #fff;
                    padding: 20px;
                    border-radius: 5px;
                    box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                }
                
                .cova-status-success {
                    color: #46b450;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                
                .cova-status-error {
                    color: #dc3232;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
            </style>
            
            <script type="text/javascript">
                jQuery(document).ready(function($) {
                    // Check if WooCommerce exists
                    var wooCommerceExists = <?php echo class_exists('WooCommerce') ? 'true' : 'false'; ?>;
                    
                    $('#cova-test-connection').on('click', function() {
                        var $button = $(this);
                        $button.prop('disabled', true).text('<?php _e('Testing...', 'cova-integration'); ?>');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'cova_test_connection',
                                nonce: '<?php echo wp_create_nonce('cova_test_connection_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    $('#cova-connection-status').html('<div class="cova-status-success"><span class="dashicons dashicons-yes"></span> <?php _e('Connected', 'cova-integration'); ?></div>');
                                } else {
                                    $('#cova-connection-status').html('<div class="cova-status-error"><span class="dashicons dashicons-no"></span> <?php _e('Connection Failed', 'cova-integration'); ?></div>');
                                }
                                $button.prop('disabled', false).text('<?php _e('Test Connection', 'cova-integration'); ?>');
                            },
                            error: function() {
                                $('#cova-connection-status').html('<div class="cova-status-error"><span class="dashicons dashicons-no"></span> <?php _e('Connection Failed', 'cova-integration'); ?></div>');
                                $button.prop('disabled', false).text('<?php _e('Test Connection', 'cova-integration'); ?>');
                            }
                        });
                    });
                    
                    $('#cova-force-sync').on('click', function() {
                        var $button = $(this);
                        $button.prop('disabled', true).text('<?php _e('Syncing...', 'cova-integration'); ?>');
                        
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'cova_force_sync',
                                nonce: '<?php echo wp_create_nonce('cova_force_sync_nonce'); ?>'
                            },
                            success: function(response) {
                                if (response.success) {
                                    alert('<?php _e('Sync completed successfully!', 'cova-integration'); ?>');
                                    location.reload();
                                } else {
                                    alert('<?php _e('Sync failed. Please check the logs.', 'cova-integration'); ?>');
                                    $button.prop('disabled', false).text('<?php _e('Sync Now', 'cova-integration'); ?>');
                                }
                            },
                            error: function() {
                                alert('<?php _e('Sync failed. Please check the logs.', 'cova-integration'); ?>');
                                $button.prop('disabled', false).text('<?php _e('Sync Now', 'cova-integration'); ?>');
                            }
                        });
                    });
                });
            </script>
        </div>
        <?php
    }
    
    /**
     * Display settings page
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('cova_integration_settings');
                do_settings_sections('cova_integration_settings');
                submit_button();
                ?>
                <p>
                    <button type="button" id="cova-test-connection" class="button button-secondary"><?php _e('Test Connection', 'cova-integration'); ?></button>
                </p>
            </form>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#cova-test-connection').on('click', function() {
                    var $button = $(this);
                    $button.prop('disabled', true).text('<?php _e('Testing...', 'cova-integration'); ?>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cova_test_connection',
                            nonce: '<?php echo wp_create_nonce('cova_test_connection_nonce'); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('<?php _e('Connection successful!', 'cova-integration'); ?>');
                            } else {
                                alert('<?php _e('Connection failed: ', 'cova-integration'); ?>' + response.data);
                            }
                            $button.prop('disabled', false).text('<?php _e('Test Connection', 'cova-integration'); ?>');
                        },
                        error: function() {
                            alert('<?php _e('Connection test failed. Please try again.', 'cova-integration'); ?>');
                            $button.prop('disabled', false).text('<?php _e('Test Connection', 'cova-integration'); ?>');
                        }
                    });
                });
            });
        </script>
        <?php
    }
    
    /**
     * Extract image URLs from product data
     * 
     * @param array $product_data Product data array
     * @return array Array of image URLs
     */
    private function extract_image_urls_from_product($product_data) {
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
        
        return $image_urls;
    }

    /**
     * Display products page
     */
    public function display_products_page() {
        global $wpdb;
        
        // Handle bulk actions
        if (isset($_POST['cova_sync_products']) && isset($_POST['product_ids']) && is_array($_POST['product_ids'])) {
            check_admin_referer('cova_sync_products_action', 'cova_sync_products_nonce');
            
            $product_ids = array_map('sanitize_text_field', $_POST['product_ids']);
            
            if (!empty($product_ids)) {
                // Sync selected products with WooCommerce
                if (class_exists('Cova_WooCommerce')) {
                    $woocommerce = new Cova_WooCommerce();
                    $synced = $woocommerce->sync_selected_products($product_ids);
                    
                    if ($synced) {
                        echo '<div class="notice notice-success"><p>' . 
                            sprintf(__('Successfully synced %d products with WooCommerce.', 'cova-integration'), count($product_ids)) . 
                            '</p></div>';
                    } else {
                        echo '<div class="notice notice-error"><p>' . 
                            __('Failed to sync products with WooCommerce. Please check the logs.', 'cova-integration') . 
                            '</p></div>';
                    }
                } else {
                    echo '<div class="notice notice-error"><p>' . 
                        __('WooCommerce is not active. Please activate WooCommerce to sync products.', 'cova-integration') . 
                        '</p></div>';
                }
            }
        }
        
        // Get all categories
        $table_name = $wpdb->prefix . 'cova_products';
        $categories = $wpdb->get_col("SELECT DISTINCT category FROM $table_name WHERE category != '' ORDER BY category ASC");
        
        // Filter by category
        $category_filter = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : '';
        
        // Build query
        $sql = "SELECT * FROM $table_name WHERE is_archived = 0";
        
        if (!empty($category_filter)) {
            $sql .= $wpdb->prepare(" AND category = %s", $category_filter);
        }
        
        $sql .= " ORDER BY name ASC";
        
        // Pagination
        $items_per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_archived = 0" . 
                        (!empty($category_filter) ? $wpdb->prepare(" AND category = %s", $category_filter) : ""));
        
        $offset = ($current_page - 1) * $items_per_page;
        $sql .= " LIMIT $items_per_page OFFSET $offset";
        
        $products = $wpdb->get_results($sql, ARRAY_A);
        
        // Total pages
        $total_pages = ceil($total_items / $items_per_page);
        
        // Generate nonces for image processing
        $process_single_image_nonce = wp_create_nonce('cova_process_single_image_nonce');
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div id="cova-products-message" class="notice" style="display:none;">
                <p></p>
            </div>

            <script type="text/javascript">
                window.cova_admin_nonces = window.cova_admin_nonces || {};
                window.cova_admin_nonces.process_single_image = '<?php echo $process_single_image_nonce; ?>';
                window.cova_admin_nonces.sync_woocommerce = '<?php echo wp_create_nonce('cova_sync_woocommerce_nonce'); ?>';
                window.cova_admin_nonces.clear_products = '<?php echo wp_create_nonce('cova_clear_products_nonce'); ?>';
                window.cova_admin_nonces.force_detailed_sync = '<?php echo wp_create_nonce('cova_force_detailed_sync_nonce'); ?>';
                // Add nonce for category selection modal to the admin page
                window.cova_admin_nonces.category_selection = '<?php echo wp_create_nonce('cova_category_selection_nonce'); ?>';
            </script>
            
            <div class="cova-products-controls">
                <div class="row">
                    <div class="col">
                        <form method="get">
                            <input type="hidden" name="page" value="cova-integration-products" />
                            
                            <select name="category">
                                <option value=""><?php _e('All Categories', 'cova-integration'); ?></option>
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?php echo esc_attr($category); ?>" <?php selected($category_filter, $category); ?>>
                                        <?php echo esc_html($category); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            
                            <input type="submit" class="button" value="<?php _e('Filter', 'cova-integration'); ?>" />
                        </form>
                    </div>
                    <div class="col">
                        <button id="cova-comprehensive-sync" class="button button-primary" style="margin-top: 10px; margin-right: 10px;">
                            <?php _e('Sync Products & Inventory', 'cova-integration'); ?>
                        </button>
                        <button id="cova-clear-all-products" class="button" style="margin-top: 10px; background-color: #d63638; color: white; border-color: #d63638;">
                            <?php _e('Clear All Products', 'cova-integration'); ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <?php if (empty($products)) : ?>
                <p><?php _e('No products found.', 'cova-integration'); ?></p>
            <?php else : ?>
                <form method="post">
                    <?php wp_nonce_field('cova_sync_products_action', 'cova_sync_products_nonce'); ?>
                    
                    <div class="tablenav top">
                        <div class="alignleft actions bulkactions">
                            <input type="submit" name="cova_sync_products" class="button button-primary" value="<?php _e('Sync Selected with WooCommerce', 'cova-integration'); ?>" />
                        </div>
                        
                        <div class="tablenav-pages">
                <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page
                            ));
                            ?>
                        </div>
                        <br class="clear" />
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all-1" />
                                </td>
                                <th><?php _e('Name', 'cova-integration'); ?></th>
                                <th><?php _e('Category', 'cova-integration'); ?></th>
                                <th><?php _e('SKU', 'cova-integration'); ?></th>
                                <th><?php _e('WooCommerce Status', 'cova-integration'); ?></th>
                                <th><?php _e('Price', 'cova-integration'); ?></th>
                                <th><?php _e('Image', 'cova-integration'); ?></th>
                                <th><?php _e('Image URLs', 'cova-integration'); ?></th>
                                <th><?php _e('Redirected URLs', 'cova-integration'); ?></th>
                                <th><?php _e('Available', 'cova-integration'); ?></th>
                                <th><?php _e('Stock', 'cova-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product) : 
                                $product_data = json_decode($product['data'], true);
                                
                                // Get WooCommerce product ID if exists
                                $wc_product_id = $wpdb->get_var($wpdb->prepare(
                                    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_cova_product_id' AND meta_value = %s LIMIT 1",
                                    $product['product_id']
                                ));
                                
                                // Get price
                                $price_table = $wpdb->prefix . 'cova_prices';
                                $price_data = null;

                                // Check if product data has Id, if not try other possible ID fields
                                $product_id_for_price = null;
                                if (isset($product_data['Id'])) {
                                    $product_id_for_price = $product_data['Id'];
                                } elseif (isset($product_data['ProductId'])) {
                                    $product_id_for_price = $product_data['ProductId'];
                                } elseif (isset($product_data['CatalogItemId'])) {
                                    $product_id_for_price = $product_data['CatalogItemId'];
                } else {
                                    // Fallback to the stored product ID
                                    $product_id_for_price = $product['product_id'];
                                }

                                if (!empty($product_id_for_price)) {
                                    $price_data = $wpdb->get_row($wpdb->prepare(
                                        "SELECT regular_price FROM $price_table WHERE catalog_item_id = %s ORDER BY last_sync DESC LIMIT 1",
                                        $product_id_for_price
                                    ));
                                }

                                $price = $price_data ? $price_data->regular_price : 0;
                                
                                // Get stock directly from products table
                                $stock = isset($product['stock_quantity']) ? (int)$product['stock_quantity'] : 0;
                                $is_in_stock = isset($product['is_in_stock']) ? (bool)$product['is_in_stock'] : false;
                                
                                // If values aren't in the products table, try to get from inventory table
                                if ($stock <= 0) {
                                    $inventory_table = $wpdb->prefix . 'cova_inventory';
                                    $product_id_for_inventory = !empty($product_id_for_price) ? $product_id_for_price : $product['product_id'];
                                    
                                    if ($wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") == $inventory_table) {
                                        $inventory_data = $wpdb->get_row($wpdb->prepare(
                                            "SELECT SUM(quantity) as total_quantity FROM $inventory_table WHERE product_id = %s",
                                            $product_id_for_inventory
                                        ));
                                        
                                        if ($inventory_data && !is_null($inventory_data->total_quantity)) {
                                            $stock = (int)$inventory_data->total_quantity;
                                            $is_in_stock = ($stock > 0);
                                        }
                                    }
                                }
                                
                                // Get SKU safely
                                $sku = '';
                                if (isset($product_data['Skus']) && is_array($product_data['Skus']) && !empty($product_data['Skus'])) {
                                    $sku = isset($product_data['Skus'][0]['Value']) ? $product_data['Skus'][0]['Value'] : '';
                                } elseif (isset($product_data['SKU'])) {
                                    $sku = $product_data['SKU'];
                                } elseif (isset($product_data['CatalogSku'])) {
                                    $sku = $product_data['CatalogSku'];
                                }

                                // Get product image URLs
                                $image_urls = $this->extract_image_urls_from_product($product_data);
                                $primary_image_url = !empty($image_urls) ? $image_urls[0] : '';
                                
                                // Check if image is already cached
                                $attachment_id = $primary_image_url ? $this->get_attachment_id_by_url($primary_image_url) : null;
                            ?>
                                <tr>
                                    <th scope="row" class="check-column">
                                        <input type="checkbox" name="product_ids[]" value="<?php echo esc_attr($product['product_id']); ?>" />
                                    </th>
                                    <td>
                                        <strong><?php echo esc_html($product['name']); ?></strong>
                                        <div class="row-actions">
                                            <span class="view">
                                                <?php if ($wc_product_id) : ?>
                                                    <a href="<?php echo esc_url(get_edit_post_link($wc_product_id)); ?>"><?php _e('View in WooCommerce', 'cova-integration'); ?></a>
                                                <?php else : ?>
                                                    <a href="javascript:void(0);" class="sync-single-product" data-product-id="<?php echo esc_attr($product['product_id']); ?>"><?php _e('Sync to WooCommerce', 'cova-integration'); ?></a>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($product['category']); ?></td>
                                    <td><?php echo esc_html($sku); ?></td>
                                    <td>
                                        <?php if ($wc_product_id) : ?>
                                            <span class="dashicons dashicons-yes" style="color: green;"></span>
                                            <?php _e('Synced', 'cova-integration'); ?>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-no" style="color: red;"></span>
                                            <?php _e('Not Synced', 'cova-integration'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($price > 0) : ?>
                                            <?php echo esc_html(sprintf('$%0.2f', $price)); ?>
                                        <?php else : ?>
                                            <span class="na"><?php _e('N/A', 'cova-integration'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($primary_image_url): ?>
                                            <?php if ($attachment_id): ?>
                                                <?php echo wp_get_attachment_image($attachment_id, array(50, 50)); ?>
                                                <div class="image-status success"><?php _e('Image cached', 'cova-integration'); ?></div>
                                            <?php else: ?>
                                                <div class="missing-image">
                                                    <span class="dashicons dashicons-format-image"></span>
                                                    <button type="button" class="button process-product-image" 
                                                        data-product-id="<?php echo esc_attr($product['product_id']); ?>" 
                                                        data-image-url="<?php echo esc_attr($primary_image_url); ?>">
                                                        <?php _e('Process Image', 'cova-integration'); ?>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="na"><?php _e('No image', 'cova-integration'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="image-urls-column">
                                        <?php if (!empty($image_urls)) : ?>
                                            <div class="image-urls-container">
                                                <?php foreach ($image_urls as $index => $url) : 
                                                    $is_igmetrix = (strpos($url, 'igmetrix.net') !== false);
                                                    $url_class = $is_igmetrix ? 'igmetrix-url' : 'regular-url';
                                                ?>
                                                    <div class="image-url-item <?php echo $url_class; ?>">
                                                        <span class="url-label">
                                                            <?php echo $index === 0 ? 'Hero:' : 'Asset ' . $index . ':'; ?>
                                                        </span>
                                                        <a href="<?php echo esc_url($url); ?>" target="_blank" class="image-url-link">
                                                            <?php echo esc_html(substr($url, 0, 50)) . (strlen($url) > 50 ? '...' : ''); ?>
                                                        </a>
                                                        <?php if ($is_igmetrix) : ?>
                                                            <span class="igmetrix-warning" title="This igmetrix.net URL typically returns 500 errors">⚠️</span>
                                                        <?php endif; ?>
                                                        <button type="button" class="button button-small copy-url-btn" 
                                                            data-url="<?php echo esc_attr($url); ?>" 
                                                            title="Copy URL to clipboard">
                                                            📋
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else : ?>
                                            <span class="na"><?php _e('No URLs', 'cova-integration'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="redirected-urls-column">
                                        <?php if (!empty($image_urls)) : ?>
                                            <div class="final-urls-container">
                                                <?php foreach ($image_urls as $index => $url) : 
                                                    $label = $index === 0 ? 'Hero' : 'Asset ' . $index;
                                                    $final_url = $this->get_final_url($url);
                                                ?>
                                                    <div class="final-url-item">
                                                        <span class="url-label"><?php echo esc_html($label); ?>:</span>
                                                        <a href="<?php echo esc_url($final_url); ?>" target="_blank" class="final-url-link">
                                                            <?php echo esc_html($final_url); ?>
                                                        </a>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else : ?>
                                            <span class="na"><?php _e('No URLs', 'cova-integration'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_in_stock) : ?>
                                            <span class="dashicons dashicons-yes" style="color: green;"></span>
                                            <?php _e('Yes', 'cova-integration'); ?>
                                        <?php else : ?>
                                            <span class="dashicons dashicons-no" style="color: red;"></span>
                                            <?php _e('No', 'cova-integration'); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($stock > 0) : ?>
                                            <span class="stock-quantity"><?php echo esc_html($stock); ?></span>
                                        <?php else : ?>
                                            <span class="stock-quantity out-of-stock">0</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td class="manage-column column-cb check-column">
                                    <input type="checkbox" id="cb-select-all-2" />
                                </td>
                                <th><?php _e('Name', 'cova-integration'); ?></th>
                                <th><?php _e('Category', 'cova-integration'); ?></th>
                                <th><?php _e('SKU', 'cova-integration'); ?></th>
                                <th><?php _e('WooCommerce Status', 'cova-integration'); ?></th>
                                <th><?php _e('Price', 'cova-integration'); ?></th>
                                <th><?php _e('Image', 'cova-integration'); ?></th>
                                <th><?php _e('Image URLs', 'cova-integration'); ?></th>
                                <th><?php _e('Redirected URLs', 'cova-integration'); ?></th>
                                <th><?php _e('Available', 'cova-integration'); ?></th>
                                <th><?php _e('Stock', 'cova-integration'); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                
                    <div class="tablenav bottom">
                        <div class="alignleft actions bulkactions">
                            <input type="submit" name="cova_sync_products" class="button button-primary" value="<?php _e('Sync Selected with WooCommerce', 'cova-integration'); ?>" />
            </div>
                        
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $current_page
                            ));
                            ?>
        </div>
                    </div>
                </form>
            <?php endif; ?>
        </div>
        
        <style>
            .stock-quantity {
                font-weight: bold;
                display: inline-block;
                padding: 3px 8px;
                border-radius: 3px;
                background-color: #e7f7e7;
                color: #0a6b0a;
            }
            
            .stock-quantity.out-of-stock {
                background-color: #f8e7e7;
                color: #a00;
            }
            
            /* Add column highlighting for better readability */
            .wp-list-table td:nth-child(7), /* Image column */
            .wp-list-table td:nth-child(8), /* Available column */
            .wp-list-table td:nth-child(9) /* Stock column */ {
                background-color: rgba(0, 0, 0, 0.02);
            }
            
            /* Add border to highlight stock columns */
            .wp-list-table th:nth-child(7),
            .wp-list-table th:nth-child(8),
            .wp-list-table th:nth-child(9) {
                border-bottom: 2px solid #0073aa;
            }
            
            /* Style for N/A text */
            .na {
                color: #999;
                font-style: italic;
            }

            /* Styling for the missing image section */
            .missing-image {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 5px;
            }

            .missing-image .dashicons {
                font-size: 24px;
                color: #ccc;
            }

            .process-product-image {
                font-size: 11px;
                padding: 0 5px;
                height: 24px;
                line-height: 22px;
            }

            /* Image status message styles */
            .image-status {
                font-size: 11px;
                margin-top: 5px;
                padding: 2px 5px;
                text-align: center;
            }
        </style>
        <?php
    }
    
    /**
     * Display the logs page
     */
    public function display_logs_page() {
        global $wpdb;
        
        // Clear logs if requested
        if (isset($_GET['clear']) && $_GET['clear'] === '1' && check_admin_referer('cova_clear_logs')) {
            $table_name = $wpdb->prefix . 'cova_error_logs';
            $wpdb->query("TRUNCATE TABLE $table_name");
            
            echo '<div class="notice notice-success"><p>' . __('Logs cleared successfully!', 'cova-integration') . '</p></div>';
        }
        
        // Get logs from database
        $table_name = $wpdb->prefix . 'cova_error_logs';
        $logs = $wpdb->get_results(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100",
            ARRAY_A
        );
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="cova-section">
                <h2><?php _e('Error Logs', 'cova-integration'); ?></h2>
                
                <?php if (empty($logs)) : ?>
                    <p><?php _e('No logs found.', 'cova-integration'); ?></p>
                <?php else : ?>
                    <div class="cova-logs-controls">
                        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cova-integration-logs&clear=1'), 'cova_clear_logs'); ?>" class="button"><?php _e('Clear Logs', 'cova-integration'); ?></a>
                    </div>
                    
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php _e('Time', 'cova-integration'); ?></th>
                                <th><?php _e('Type', 'cova-integration'); ?></th>
                                <th><?php _e('Message', 'cova-integration'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log) : ?>
                                <tr>
                                    <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['created_at']))); ?></td>
                                    <td><?php echo esc_html(ucfirst($log['type'])); ?></td>
                                    <td><?php echo esc_html($log['message']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Display API tester page
     */
    public function display_api_tester_page() {
        // Create and call the API Tester's display method
        $api_tester = new Cova_API_Tester();
        $api_tester->display_tester_page();
    }
    
    /**
     * Test connection AJAX handler
     */
    public function test_connection() {
        check_ajax_referer('cova_test_connection_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $token = $this->api_client->get_token();
        
        if (is_wp_error($token)) {
            wp_send_json_error($token->get_error_message());
        } else {
            update_option('cova_last_connection_test', current_time('timestamp'));
            wp_send_json_success();
        }
    }
    
    /**
     * AJAX handler for clearing all products
     */
    public function ajax_clear_all_products() {
        check_ajax_referer('cova_clear_products_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cova-integration'));
            return;
        }
        
        global $wpdb;
        
        // Clear products table (except for archived products)
        $product_table = $wpdb->prefix . 'cova_products';
        $products_deleted = $wpdb->query("DELETE FROM $product_table WHERE is_archived = 0");
        
        // Also clear WooCommerce products if that class exists
        $wc_products_deleted = 0;
        if (class_exists('Cova_WooCommerce')) {
            $woocommerce = new Cova_WooCommerce();
            $wc_products_deleted = $woocommerce->clear_cova_products();
        }
        
        wp_send_json_success(array(
            'products_deleted' => $products_deleted,
            'wc_products_deleted' => $wc_products_deleted,
            'message' => sprintf(
                __('Successfully cleared %d products and %d WooCommerce products.', 'cova-integration'),
                $products_deleted,
                $wc_products_deleted
            )
        ));
    }
    
    /**
     * AJAX handler for forcing detailed product sync
     */
    public function ajax_force_detailed_sync() {
        check_ajax_referer('cova_force_detailed_sync_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cova-integration'));
            return;
        }
        $result = $this->api_client->force_detailed_product_sync();
        // Always return success if result is true, even if there were warnings or minor issues
        if ($result) {
            wp_send_json_success();
        } else {
            // Only return error if the sync completely failed
            wp_send_json_error(__('Failed to sync detailed product data. Please check the logs.', 'cova-integration'));
        }
    }
    
    /**
     * Add custom column to products list
     * 
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_product_image_column($columns) {
        $new_columns = array();
        
        foreach($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add image column after name column
            if ($key === 'name') {
                $new_columns['image'] = __('Image', 'cova-integration');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Display product image column content
     * 
     * @param string $column Column name
     * @param int $post_id Post ID
     */
    public function display_product_image_column($column, $post_id) {
        if ($column === 'image') {
            global $wpdb;
            
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cova_products WHERE id = %d",
                $post_id
            ), ARRAY_A);
            
            if (!$product) {
                echo '<span>—</span>';
                return;
            }
            
            $product_data = json_decode($product['data'], true);
            $image_url = '';
            
            // Try to find the image URL
            if (!empty($product_data['HeroShotUri'])) {
                $image_url = $product_data['HeroShotUri'];
            } elseif (!empty($product_data['Assets']) && is_array($product_data['Assets'])) {
                foreach ($product_data['Assets'] as $asset) {
                    if (isset($asset['Type']) && $asset['Type'] === 'Image' && !empty($asset['Url'])) {
                        $image_url = $asset['Url'];
                        break;
                    }
                }
            }
            
            if (empty($image_url)) {
                echo '<span>No image available</span>';
                return;
            }
            
            // Check if image is already cached
            $attachment_id = $this->get_attachment_id_by_url($image_url);
            
            if ($attachment_id) {
                $image = wp_get_attachment_image($attachment_id, array(50, 50));
                echo $image;
                echo '<br><span class="dashicons dashicons-yes-alt" style="color:green;"></span> <small>Cached</small>';
            } else {
                echo '<span>Image needs processing</span>';
                echo '<br><button class="button button-small process-image" data-product-id="' . esc_attr($product['product_id']) . '" data-image-url="' . esc_attr($image_url) . '">Process Image</button>';
            }
        }
    }
    
    /**
     * Display images management page
     */
    public function display_images_page() {
        // Check user capabilities
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'cova-integration'));
        }
        
        // Generate nonces
        $process_all_images_nonce = wp_create_nonce('cova_process_all_images_nonce');
        $process_single_image_nonce = wp_create_nonce('cova_process_single_image_nonce');
        $reset_processed_images_nonce = wp_create_nonce('cova_reset_processed_images_nonce');
        
        // Add global JavaScript object with nonces
        ?>
        <script type="text/javascript">
            window.cova_admin_nonces = {
                process_all_images: '<?php echo $process_all_images_nonce; ?>',
                process_single_image: '<?php echo $process_single_image_nonce; ?>',
                reset_processed_images: '<?php echo $reset_processed_images_nonce; ?>'
            };
        </script>
        
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="cova-card">
                <h2><?php _e('Product Image Processing', 'cova-integration'); ?></h2>
                <p><?php _e('Use this tool to download and cache all product images from the Cova API. This process may take some time depending on how many products you have.', 'cova-integration'); ?></p>
                
                <button id="process-all-images" class="button button-primary"><?php _e('Process All Images', 'cova-integration'); ?></button>
                <input type="hidden" id="cova_process_all_images_nonce" value="<?php echo $process_all_images_nonce; ?>">
                <input type="hidden" id="cova_process_single_image_nonce" value="<?php echo $process_single_image_nonce; ?>">
                <input type="hidden" id="cova_reset_processed_images_nonce" value="<?php echo $reset_processed_images_nonce; ?>">
                
                <div id="image-processing-progress" style="margin-top: 20px; display: none;">
                    <h3><?php _e('Processing Progress', 'cova-integration'); ?></h3>
                    <div class="progress-bar-container" style="height: 20px; width: 100%; background-color: #f1f1f1; border-radius: 3px; margin-bottom: 10px;">
                        <div id="progress-bar" style="height: 20px; width: 0; background-color: #0073aa; border-radius: 3px;"></div>
                    </div>
                    <p id="progress-status"><?php _e('0% complete', 'cova-integration'); ?></p>
                    <div id="progress-log" style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; margin-top: 10px;"></div>
                </div>
            </div>
            
            <div class="cova-card">
                <h2><?php _e('Cached Images Statistics', 'cova-integration'); ?></h2>
                <?php
                // Get statistics
                $processed_images = get_option('cova_processed_images', array());
                $total_products = $this->get_total_products();
                $total_processed = count($processed_images);
                $percent_processed = $total_products > 0 ? round(($total_processed / $total_products) * 100) : 0;
                ?>
                <p><?php printf(__('Total products: %d', 'cova-integration'), $total_products); ?></p>
                <p><?php printf(__('Images processed: %d', 'cova-integration'), $total_processed); ?></p>
                <p><?php printf(__('Completion: %d%%', 'cova-integration'), $percent_processed); ?></p>
                
                <?php if ($total_processed > 0): ?>
                <p><button id="reset-processed-images" class="button"><?php _e('Reset Processed Images Data', 'cova-integration'); ?></button></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Alternative approach for difficult igmetrix URLs - create a placeholder image
     * 
     * @param string $product_name Product name for the image
     * @return int|WP_Error Attachment ID or WP_Error
     */
    private function create_placeholder_image($product_name) {
        error_log('[COVA Placeholder] Creating placeholder image for: ' . $product_name);
        
        // Include required files
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Get placeholder image path (generate a simple colored square)
        $placeholder_path = $this->generate_colored_placeholder($product_name);
        
        if (is_wp_error($placeholder_path)) {
            return $placeholder_path;
        }
        
        // Create a file array for media_handle_sideload
        $file_array = array(
            'name' => sanitize_file_name($product_name . '-placeholder.jpg'),
            'tmp_name' => $placeholder_path
        );
        
        // Use media_handle_sideload to create an attachment
        $attachment_id = media_handle_sideload($file_array, 0, $product_name . ' (Placeholder)');
        
        // Remove the temporary file
        @unlink($placeholder_path);
        
        if (is_wp_error($attachment_id)) {
            error_log('[COVA Placeholder] Error creating attachment: ' . $attachment_id->get_error_message());
            return $attachment_id;
        }
        
        // Add metadata to indicate this is a placeholder
        update_post_meta($attachment_id, '_cova_is_placeholder', '1');
        update_post_meta($attachment_id, '_cova_product_name', $product_name);
        
        error_log('[COVA Placeholder] Successfully created placeholder, attachment ID: ' . $attachment_id);
        return $attachment_id;
    }
    
    /**
     * Generate a colored placeholder image based on product name
     * 
     * @param string $text Text to display on the image
     * @return string|WP_Error Path to the temporary image file or WP_Error
     */
    private function generate_colored_placeholder($text) {
        if (!function_exists('imagecreate')) {
            return new WP_Error('gd_missing', 'GD library is not available');
        }
        
        // Create a unique temporary file
        $temp_file = wp_tempnam('cova-placeholder-');
        
        // Create a 400x400 image
        $width = 400;
        $height = 400;
        
        // Create image
        $image = imagecreatetruecolor($width, $height);
        
        // Generate a color based on the text (for consistent colors per product)
        $hash = md5($text);
        $r = hexdec(substr($hash, 0, 2));
        $g = hexdec(substr($hash, 2, 2));
        $b = hexdec(substr($hash, 4, 2));
        
        // Make the color lighter (better for background)
        $r = min(255, $r + 100);
        $g = min(255, $g + 100);
        $b = min(255, $b + 100);
        
        // Background color
        $bg_color = imagecolorallocate($image, $r, $g, $b);
        
        // Text color (white or black depending on background brightness)
        $brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        $text_color = ($brightness > 128) ? 
            imagecolorallocate($image, 0, 0, 0) : // Black text on light background
            imagecolorallocate($image, 255, 255, 255); // White text on dark background
        
        // Fill the background
        imagefill($image, 0, 0, $bg_color);
        
        // Draw a border
        $border_color = imagecolorallocate($image, max(0, $r - 50), max(0, $g - 50), max(0, $b - 50));
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $border_color);
        
        // Prepare text (use first letter of each word)
        $words = explode(' ', $text);
        $initials = '';
        foreach ($words as $word) {
            if (!empty($word)) {
                $initials .= strtoupper(substr($word, 0, 1));
                if (strlen($initials) >= 3) break; // Limit to 3 characters
            }
        }
        
        // If no initials were extracted, use "CP" for Cova Product
        if (empty($initials)) {
            $initials = 'CP';
        }
        
        // Draw text - calculate font size based on image dimensions
        $font_size = min($width, $height) / 4;
        
        // Only try to use GD's built-in functions that are widely available
        // Get text dimensions
        $text_width = imagefontwidth(5) * strlen($initials);
        $text_height = imagefontheight(5);
        
        // Center the text
        $x = ($width - $text_width) / 2;
        $y = ($height - $text_height) / 2;
        
        // Draw the text
        imagestring($image, 5, $x, $y, $initials, $text_color);
        
        // Add "COVA Product" text at bottom
        $info_text = "COVA Product";
        $info_width = imagefontwidth(2) * strlen($info_text);
        $info_x = ($width - $info_width) / 2;
        imagestring($image, 2, $info_x, $height - 20, $info_text, $text_color);
        
        // Save the image
        imagejpeg($image, $temp_file, 90);
        
        // Free memory
        imagedestroy($image);
        
        if (!file_exists($temp_file)) {
            return new WP_Error('image_save_failed', 'Failed to save placeholder image');
        }
        
        return $temp_file;
    }
    
    /**
     * AJAX handler for processing a single image
     */
    public function ajax_process_single_image() {
        // Disable all PHP error display during this request
        error_reporting(0);
        @ini_set('display_errors', 0);
        
        // Set appropriate headers first, before any output
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Log the beginning of the request with useful debugging info
        $debug_start = [
            'time' => current_time('mysql'),
            'request_uri' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'post_size' => count($_POST),
            'product_id' => $_POST['product_id'] ?? 'not set',
            'image_url' => $_POST['image_url'] ?? 'not set',
        ];
        error_log('[COVA Debug] Process start: ' . json_encode($debug_start));
        
        // Clear any previous output completely
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Try to clean up and fix the response before outputting it
        try {
            // Basic validation first
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cova_process_single_image_nonce')) {
                $this->json_output(['success' => false, 'data' => 'Security check failed']);
                exit;
            }
            
            if (!current_user_can('manage_options')) {
                $this->json_output(['success' => false, 'data' => 'Insufficient permissions']);
                exit;
            }
            
            $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
            $image_url = isset($_POST['image_url']) ? esc_url_raw($_POST['image_url']) : '';
            
            if (empty($product_id) || empty($image_url)) {
                $this->json_output(['success' => false, 'data' => 'Missing required parameters']);
                exit;
            }
            
            error_log('[COVA Image] Processing URL: ' . $image_url);
            
            // GET PRODUCT INFO
            global $wpdb;
            $product = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}cova_products WHERE product_id = %s",
                $product_id
            ), ARRAY_A);
            
            if (!$product) {
                $this->json_output(['success' => false, 'data' => 'Product not found']);
                exit;
            }
            
            // Check if this is an igmetrix URL which we know will fail with 500 error
            $is_igmetrix_url = (strpos($image_url, 'igmetrix.net') !== false);
            
            if ($is_igmetrix_url) {
                // For igmetrix URLs, just return a message that we're skipping the image
                error_log('[COVA Image] Detected igmetrix.net URL - skipping image processing for: ' . $product['name']);
                
                // Mark as processed to avoid repeated attempts
                $processed_images = get_option('cova_processed_images', array());
                if (!in_array($image_url, $processed_images)) {
                    $processed_images[] = $image_url;
                    update_option('cova_processed_images', $processed_images);
                }
                
                // Send success response with a note about skipping
                $this->json_output([
                    'success' => true, 
                    'data' => [
                        'message' => 'Image processing skipped. The igmetrix server returns 500 errors.',
                        'skipped' => true
                    ]
                ]);
                exit;
            } else {
                // For non-igmetrix URLs, try to download the image
                $attachment_id = $this->download_and_cache_image($image_url, $product['name']);
                
                if (is_wp_error($attachment_id)) {
                    error_log('[COVA Image] Failed to download image: ' . $attachment_id->get_error_message());
                    
                    // Just return a message that download failed
                    $this->json_output([
                        'success' => false, 
                        'data' => 'Failed to download image: ' . $attachment_id->get_error_message()
                    ]);
                    exit;
                }
                
                // If we got here, the download succeeded
                $this->json_output([
                    'success' => true, 
                    'data' => [
                        'attachment_id' => $attachment_id,
                        'image_url' => wp_get_attachment_url($attachment_id),
                        'message' => 'Successfully downloaded and cached image.'
                    ]
                ]);
                exit;
            }
            
        } catch (Exception $e) {
            error_log('[COVA Image] Fatal exception: ' . $e->getMessage());
            $this->json_output(['success' => false, 'data' => 'Exception: ' . $e->getMessage()]);
            exit;
        }
    }
    
    /**
     * Output JSON with clean error handling
     *
     * @param array $data The data to output as JSON
     */
    private function json_output($data) {
        // Make absolutely sure we've cleared any output
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Check if headers have already been sent
        if (headers_sent($file, $line)) {
            error_log("[COVA JSON] Headers already sent in $file on line $line");
        }
        
        // Ensure content type is set
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        
        // Encode and output
        echo json_encode($data);
        exit;
    }
    
    /**
     * AJAX handler for capturing debug information from failed requests
     */
    public function ajax_capture_image_debug() {
        // Just record everything exactly as it comes in
        $debug_data = array(
            'post_data' => $_POST,
            'server' => $_SERVER,
            'time' => current_time('mysql'),
            'raw_post' => file_get_contents('php://input')
        );
        
        // Save to an option for review
        update_option('cova_image_debug_' . time(), $debug_data);
        
        // Echo something simple
        echo 'Debug data captured';
        exit;
    }
    
    /**
     * Display error debug page
     */
    public function display_error_debug_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Cova Image Debug', 'cova-integration'); ?></h1>
            
            <div class="cova-card">
                <h2><?php _e('Last JSON Error', 'cova-integration'); ?></h2>
                <?php
                $last_error = get_option('cova_last_json_error', '');
                if (!empty($last_error)) {
                    echo '<div class="cova-debug-box">';
                    echo '<pre>' . esc_html($last_error) . '</pre>';
                    echo '</div>';
                    
                    // Add button to clear
                    echo '<p><button id="clear-json-error" class="button">Clear Error Log</button></p>';
                } else {
                    echo '<p>No errors recorded.</p>';
                }
                ?>
            </div>
            
            <div class="cova-card">
                <h2><?php _e('Debug Capture', 'cova-integration'); ?></h2>
                <p>This tool will help capture the exact server response for debugging.</p>
                
                <button id="test-debug-capture" class="button button-primary">Test Debug Capture</button>
                <button id="test-image-process" class="button">Test Image Process</button>
                
                <div id="debug-result" class="cova-debug-box" style="margin-top: 20px; display: none;"></div>
            </div>
            
            <div class="cova-card">
                <h2><?php _e('Pending Images', 'cova-integration'); ?></h2>
                <?php
                $pending_images = get_option('cova_pending_images', array());
                if (!empty($pending_images)) {
                    echo '<p>' . count($pending_images) . ' images pending background processing.</p>';
                    echo '<table class="wp-list-table widefat fixed striped">';
                    echo '<thead><tr><th>Product</th><th>Image URL</th><th>Placeholder ID</th><th>Timestamp</th></tr></thead>';
                    echo '<tbody>';
                    foreach ($pending_images as $url => $data) {
                        echo '<tr>';
                        echo '<td>' . esc_html($data['product_name']) . '</td>';
                        echo '<td>' . esc_html($url) . '</td>';
                        echo '<td>' . esc_html($data['placeholder_id']) . '</td>';
                        echo '<td>' . esc_html(date('Y-m-d H:i:s', $data['timestamp'])) . '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table>';
                    
                    // Add button to clear
                    echo '<p><button id="clear-pending-images" class="button">Clear Pending Images</button></p>';
                } else {
                    echo '<p>No pending images.</p>';
                }
                ?>
            </div>
        </div>
        
        <style>
            .cova-debug-box {
                background: #f8f9fa;
                border: 1px solid #ddd;
                padding: 15px;
                overflow: auto;
                max-height: 400px;
                margin-top: 10px;
            }
            
            .cova-debug-box pre {
                white-space: pre-wrap;
                margin: 0;
            }
        </style>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#test-debug-capture').on('click', function() {
                    var $button = $(this);
                    $button.prop('disabled', true).text('Testing...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cova_capture_image_debug',
                            test_data: 'This is a test',
                            nonce: '<?php echo wp_create_nonce('cova_debug_nonce'); ?>'
                        },
                        success: function(response) {
                            $('#debug-result').show().html('<pre>' + response + '</pre>');
                            $button.prop('disabled', false).text('Test Debug Capture');
                        },
                        error: function(xhr, status, error) {
                            $('#debug-result').show().html('<pre style="color:red">Error: ' + error + '\n\nResponse: ' + xhr.responseText + '</pre>');
                            $button.prop('disabled', false).text('Test Debug Capture');
                        }
                    });
                });
                
                $('#test-image-process').on('click', function() {
                    var $button = $(this);
                    $button.prop('disabled', true).text('Testing...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        dataType: 'json',
                        data: {
                            action: 'cova_process_single_image',
                            product_id: 'test_product',
                            image_url: 'https://example.com/test.jpg',
                            nonce: '<?php echo wp_create_nonce('cova_process_single_image_nonce'); ?>'
                        },
                        success: function(response) {
                            $('#debug-result').show().html('<pre>' + JSON.stringify(response, null, 2) + '</pre>');
                            $button.prop('disabled', false).text('Test Image Process');
                        },
                        error: function(xhr, status, error) {
                            $('#debug-result').show().html('<pre style="color:red">Error: ' + error + '\n\nResponse: ' + xhr.responseText + '</pre>');
                            $button.prop('disabled', false).text('Test Image Process');
                            
                            // Also capture this error
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'cova_capture_image_debug',
                                    error_data: error,
                                    response_text: xhr.responseText,
                                    nonce: '<?php echo wp_create_nonce('cova_debug_nonce'); ?>'
                                }
                            });
                        }
                    });
                });
                
                $('#clear-json-error').on('click', function() {
                    if (confirm('Are you sure you want to clear the JSON error log?')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'cova_clear_json_error',
                                nonce: '<?php echo wp_create_nonce('cova_debug_nonce'); ?>'
                            },
                            success: function() {
                                location.reload();
                            }
                        });
                    }
                });
                
                $('#clear-pending-images').on('click', function() {
                    if (confirm('Are you sure you want to clear all pending images?')) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            data: {
                                action: 'cova_clear_pending_images',
                                nonce: '<?php echo wp_create_nonce('cova_debug_nonce'); ?>'
                            },
                            success: function() {
                                location.reload();
                            }
                        });
                    }
                });
            });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for processing all product images
     */
    public function ajax_process_all_images() {
        // Prevent any output before our JSON response
        @ob_clean();
        
        check_ajax_referer('cova_process_all_images_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        global $wpdb;
        
        // Get the current progress
        $processed_images = get_option('cova_processed_images', array());
        $current_offset = get_option('cova_image_processing_offset', 0);
        $batch_size = 3; // Reduce batch size to avoid timeouts with problematic URLs
        
        // Get total number of products
        $total_products = $this->get_total_products();
        
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
            "SELECT * FROM {$wpdb->prefix}cova_products WHERE is_archived = 0 ORDER BY id LIMIT %d OFFSET %d",
            $batch_size, $current_offset
        ), ARRAY_A);
        
        $message = '';
        $newly_processed = 0;
        $errors = array();
        
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
                
                // Handle igmetrix URLs specially
                $is_igmetrix = (strpos($url, 'igmetrix.net') !== false);
                $image_id = null;
                
                if ($is_igmetrix) {
                    error_log('[COVA Batch] Processing igmetrix URL: ' . $url);
                    
                    // Extract image filename
                    $image_filename = basename($url);
                    
                    // Try normal download first
                    $image_id = $this->download_and_cache_image($url, $product['name']);
                    
                    // If that fails, try with direct cURL
                    if (is_wp_error($image_id) && function_exists('curl_init')) {
                        error_log('[COVA Batch] First attempt failed, trying direct cURL');
                        $temp_file = $this->download_with_curl($url);
                        
                        if (!is_wp_error($temp_file)) {
                            require_once(ABSPATH . 'wp-admin/includes/media.php');
                            require_once(ABSPATH . 'wp-admin/includes/file.php');
                            require_once(ABSPATH . 'wp-admin/includes/image.php');
                            
                            $file_array = array(
                                'name' => $image_filename,
                                'tmp_name' => $temp_file
                            );
                            
                            $image_id = media_handle_sideload($file_array, 0, $product['name']);
                            
                            if (is_wp_error($image_id)) {
                                @unlink($temp_file);
                                $errors[] = 'Failed to create attachment for ' . $product['name'] . ': ' . $image_id->get_error_message();
                                error_log('[COVA Batch] Error creating attachment: ' . $image_id->get_error_message());
                            } else {
                                // Store the original URL as meta data
                                update_post_meta($image_id, '_cova_asset_url', $url);
                            }
                        } else {
                            $errors[] = 'cURL download failed for ' . $product['name'] . ': ' . $temp_file->get_error_message();
                        }
                    }
                } else {
                    // Standard download for non-igmetrix URLs
                    $image_id = $this->download_and_cache_image($url, $product['name']);
                }
                
                if ($image_id && !is_wp_error($image_id)) {
                    $processed_images[] = $url;
                    $newly_processed++;
                    $message .= "Processed image for product: " . esc_html($product['name']) . "<br>";
                    error_log('[COVA Batch] Successfully processed image for: ' . $product['name']);
                } else if (is_wp_error($image_id)) {
                    $errors[] = 'Failed to process image for ' . $product['name'] . ': ' . $image_id->get_error_message();
                    error_log('[COVA Batch] Error: ' . $image_id->get_error_message());
                }
            }
        }
        
        // Update the offset and processed images
        $current_offset += $batch_size;
        update_option('cova_image_processing_offset', $current_offset);
        update_option('cova_processed_images', $processed_images);
        
        // Calculate progress percentage
        $progress = min(100, round(($current_offset / $total_products) * 100));
        
        // Add error messages to the response if any
        if (!empty($errors)) {
            $message .= "<br><strong>Errors:</strong><br>" . implode("<br>", $errors);
        }
        
        wp_send_json_success(array(
            'complete' => false,
            'progress' => $progress,
            'total_processed' => count($processed_images),
            'newly_processed' => $newly_processed,
            'message' => $message
        ));
        exit; // Make sure to exit after sending JSON response
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
    
    /**
     * Download image with support for HTTP redirects
     *
     * @param string $url Image URL
     * @return string|WP_Error Path to downloaded temp file or WP_Error on failure
     */
    private function download_image_with_redirect($url) {
        // Add detailed error logging
        error_log('[COVA Image Debug] Attempting to download: ' . $url);

        // Check if URL is from igmetrix.net which seems to have special requirements
        $is_igmetrix = (strpos($url, 'igmetrix.net') !== false);
        
        // Set proper headers for igmetrix server which may be rejecting requests without proper headers
        $headers = array(
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Connection' => 'keep-alive',
            'Referer' => 'https://www.cova.com/'
        );
        
        // Use WordPress HTTP API which handles redirects
        $response = wp_remote_get($url, array(
            'timeout' => 60, // Increase timeout
            'redirection' => 10, // Increase redirects
            'sslverify' => false, // Set to false for troubleshooting
            'headers' => $headers,
            'httpversion' => '1.1',
        ));
        
        if (is_wp_error($response)) {
            error_log('[COVA Image Debug] WP Error: ' . $response->get_error_message());
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            error_log('[COVA Image Debug] Bad status code: ' . $status_code);
            
            // For igmetrix.net, try an alternative approach using cURL directly
            if ($is_igmetrix && function_exists('curl_init')) {
                error_log('[COVA Image Debug] Trying alternative cURL approach for igmetrix');
                return $this->download_with_curl($url);
            }
            
            return new WP_Error('download_failed', 'Failed to download image. HTTP status: ' . $status_code);
        }
        
        $image_data = wp_remote_retrieve_body($response);
        if (empty($image_data)) {
            error_log('[COVA Image Debug] Empty image data returned');
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
        
        $temp_file = wp_tempnam('cova-image-');
        
        // Save the image data to a temporary file
        if (!file_put_contents($temp_file, $image_data)) {
            error_log('[COVA Image Debug] Failed to write image to temp file');
            return new WP_Error('file_save_failed', 'Could not write image to temporary file');
        }
        
        error_log('[COVA Image Debug] Successfully downloaded image to: ' . $temp_file);
        return $temp_file;
    }
    
    /**
     * Alternative download method using cURL directly
     * 
     * @param string $url Image URL
     * @return string|WP_Error Path to downloaded temp file or WP_Error on failure
     */
    private function download_with_curl($url) {
        if (!function_exists('curl_init')) {
            error_log('[COVA cURL] cURL not available on this server');
            return new WP_Error('curl_not_available', 'cURL is not available on this server');
        }
        
        // Log the attempt
        error_log('[COVA cURL] Attempting to download: ' . $url);
        
        // Create a unique filename for debugging purposes
        $temp_file = wp_tempnam('cova-image-debug-');
        
        // Try to save the debug info
        $debug_info = array(
            'url' => $url,
            'time' => current_time('mysql'),
            'temp_file' => $temp_file
        );
        error_log('[COVA cURL Debug] ' . json_encode($debug_info));
        
        // If using a proxy, try to detect it
        $proxy = getenv('HTTP_PROXY') ?: getenv('http_proxy');
        if ($proxy) {
            error_log('[COVA cURL] Proxy detected: ' . $proxy);
        }
        
        // Generate a random user agent
        $user_agents = array(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.1 Safari/605.1.15',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:94.0) Gecko/20100101 Firefox/94.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 15_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.1 Mobile/15E148 Safari/604.1'
        );
        $user_agent = $user_agents[array_rand($user_agents)];
        
        // Configure cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        // Set headers that mimic a browser
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'User-Agent: ' . $user_agent,
            'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Accept-Encoding: gzip, deflate, br',
            'Connection: keep-alive',
            'Referer: https://www.cova.com/',
            'Cache-Control: no-cache',
            'Pragma: no-cache'
        ));
        
        // Enable automatic decompression of responses
        curl_setopt($ch, CURLOPT_ENCODING, '');
        
        // For igmetrix URLs, try to use special handling
        if (strpos($url, 'igmetrix.net') !== false) {
            // Try to get a session cookie first by making a request to the main site
            $cookie_jar = tempnam(sys_get_temp_dir(), "CURLCOOKIE");
            curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar);
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
            
            // Using HTTP/1.1 might help
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
            
            // For igmetrix, we'll also try setting specific cookies that might be required
            curl_setopt($ch, CURLOPT_COOKIE, 'session_id=123456789; visitor=true; has_access=1');
        }
        
        // Enable verbose debugging
        $verbose_log = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_VERBOSE, true);
        curl_setopt($ch, CURLOPT_STDERR, $verbose_log);
        
        // Execute cURL request
        $image_data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $error = curl_error($ch);
        $error_no = curl_errno($ch);
        $info = curl_getinfo($ch);
        
        // Get verbose information
        rewind($verbose_log);
        $verbose_output = stream_get_contents($verbose_log);
        fclose($verbose_log);
        
        curl_close($ch);
        
        // Log detailed debug information
        $debug_response = array(
            'http_code' => $http_code,
            'content_type' => $content_type,
            'error' => $error,
            'error_no' => $error_no,
            'content_length' => strlen($image_data),
            'effective_url' => $info['url'] ?? '',
            'redirect_count' => $info['redirect_count'] ?? 0,
            'redirect_url' => $info['redirect_url'] ?? '',
            'verbose_log_sample' => substr($verbose_output, 0, 500) // First 500 chars
        );
        error_log('[COVA cURL Response] ' . json_encode($debug_response));
        
        if ($error) {
            error_log('[COVA cURL] Error: ' . $error . ' (Code: ' . $error_no . ')');
            return new WP_Error('curl_error', 'cURL error: ' . $error);
        }
        
        if ($http_code !== 200) {
            error_log('[COVA cURL] Bad status code: ' . $http_code . ' for URL: ' . $url);
            
            // Special handling for 403 status (forbidden)
            if ($http_code === 403) {
                error_log('[COVA cURL] 403 Forbidden error, trying with alternative approach');
                
                // Try using file_get_contents as a last resort for igmetrix URLs
                if (strpos($url, 'igmetrix.net') !== false && ini_get('allow_url_fopen')) {
                    error_log('[COVA cURL] Trying file_get_contents as last resort');
                    
                    $context = stream_context_create(array(
                        'http' => array(
                            'header' => 'User-Agent: ' . $user_agent . "\r\n" .
                                        'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8' . "\r\n" .
                                        'Referer: https://www.cova.com/' . "\r\n"
                        )
                    ));
                    
                    $alt_image_data = @file_get_contents($url, false, $context);
                    
                    if ($alt_image_data !== false && !empty($alt_image_data)) {
                        error_log('[COVA cURL] file_get_contents succeeded, data length: ' . strlen($alt_image_data));
                        if (file_put_contents($temp_file, $alt_image_data)) {
                            return $temp_file;
                        }
                    }
                    
                    error_log('[COVA cURL] file_get_contents also failed');
                }
            }
            
            return new WP_Error('download_failed', 'Failed to download image. HTTP status: ' . $http_code);
        }
        
        if (empty($image_data)) {
            error_log('[COVA cURL] Empty image data');
            return new WP_Error('empty_image', 'Downloaded image is empty');
        }
        
        // Save the image data to the temporary file
        if (!file_put_contents($temp_file, $image_data)) {
            error_log('[COVA cURL] Failed to write image data to temp file');
            return new WP_Error('file_save_failed', 'Could not write image to temporary file');
        }
        
        // Check if the file has valid image content
        $image_size = @getimagesize($temp_file);
        if ($image_size === false) {
            $file_content_sample = file_get_contents($temp_file, false, null, 0, 100);
            error_log('[COVA cURL] Invalid image file. Content sample: ' . bin2hex($file_content_sample));
            @unlink($temp_file); // Clean up
            return new WP_Error('invalid_image', 'Downloaded file is not a valid image');
        }
        
        error_log('[COVA cURL] Successfully downloaded image to: ' . $temp_file);
        return $temp_file;
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
     * Get total number of products
     * 
     * @return int Number of products
     */
    private function get_total_products() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cova_products WHERE is_archived = 0");
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'cova-') === false) {
            return;
        }
        
        wp_enqueue_style(
            'cova-admin-styles',
            COVA_INTEGRATION_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            COVA_INTEGRATION_VERSION
        );
        
        wp_enqueue_script(
            'cova-admin-scripts',
            COVA_INTEGRATION_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            COVA_INTEGRATION_VERSION,
            true
        );
        
        wp_localize_script('cova-admin-js', 'cova_admin_nonces', array(
            'process_single' => wp_create_nonce('cova_process_single_nonce'),
            'process_all' => wp_create_nonce('cova_process_all_nonce'),
            'reset_processed' => wp_create_nonce('cova_reset_processed_nonce'),
            'debug_image' => wp_create_nonce('cova_debug_image_nonce'),
            'check_redirects' => wp_create_nonce('cova_check_redirects_nonce'),
        ));
    }
    
    /**
     * AJAX handler for resetting processed images data
     */
    public function ajax_reset_processed_images() {
        // Prevent any output before our JSON response
        @ob_clean();
        
        check_ajax_referer('cova_reset_processed_images_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        // Reset the processed images list and offset
        update_option('cova_processed_images', array());
        update_option('cova_image_processing_offset', 0);
        
        wp_send_json_success('Processed images data has been reset');
        exit; // Make sure to exit after sending JSON response
    }
    
    /**
     * AJAX handler for force sync
     */
    public function ajax_force_sync() {
        check_ajax_referer('cova_force_sync_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        $api_client = new Cova_API_Client();
        $api_client->sync_products();
        $api_client->sync_prices();
        $api_client->sync_inventory();
        
        update_option('cova_last_sync_time', current_time('timestamp'));
        
        wp_send_json_success('Data synchronized successfully');
    }
    
    /**
     * AJAX handler for clearing logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('cova_clear_logs_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'cova_error_logs';
        $wpdb->query("TRUNCATE TABLE $table_name");
        
        wp_send_json_success('Logs cleared successfully');
    }
    
    /**
     * AJAX handler for debugging image processing issues
     */
    public function ajax_debug_image_process() {
        // Turn off all error reporting during this request
        error_reporting(0);
        ini_set('display_errors', 0);
        
        // Start by writing to the error log
        error_log('COVA DEBUG: Starting debug process');
        
        // Save the raw request data
        $raw_post = file_get_contents('php://input');
        error_log('COVA DEBUG RAW POST: ' . $raw_post);
        
        // Save the POST data
        error_log('COVA DEBUG POST: ' . json_encode($_POST));
        
        // Create a response that won't be affected by any WordPress hooks
        header('Content-Type: text/plain');
        echo "DEBUG RESPONSE START\n";
        echo "Time: " . date('Y-m-d H:i:s') . "\n";
        echo "Raw POST data length: " . strlen($raw_post) . "\n";
        echo "POST variables: " . count($_POST) . "\n";
        
        // Try running the image process
        try {
            echo "Testing direct image download...\n";
            
            // Get parameters
            $url = isset($_POST['url']) ? $_POST['url'] : 'https://example.com/test.jpg';
            
            // Try different methods
            echo "Testing curl download...\n";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            // Add headers
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                'Accept: image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Accept-Encoding: gzip, deflate, br',
                'Connection: keep-alive',
                'Referer: https://www.cova.com/',
                'Cache-Control: no-cache',
                'Pragma: no-cache'
            ));
            
            // Execute the request
            $curl_result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            
            echo "cURL result: " . ($curl_result ? "Success" : "Failed") . "\n";
            echo "HTTP code: " . $http_code . "\n";
            echo "cURL error: " . $error . "\n";
            
            curl_close($ch);
            
            // Test file_get_contents
            echo "Testing file_get_contents...\n";
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'GET',
                    'header' => 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                    'timeout' => 30,
                    'follow_location' => 1,
                    'max_redirects' => 10
                )
            ));
            
            $file_result = @file_get_contents($url, false, $context);
            echo "file_get_contents result: " . ($file_result ? "Success" : "Failed") . "\n";
            
            // Test WordPress HTTP API
            echo "Testing WordPress HTTP API...\n";
            $wp_result = wp_remote_get($url, array(
                'timeout' => 30,
                'redirection' => 10,
                'sslverify' => false,
                'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36'
            ));
            
            if (is_wp_error($wp_result)) {
                echo "WordPress HTTP API error: " . $wp_result->get_error_message() . "\n";
            } else {
                echo "WordPress HTTP API status: " . wp_remote_retrieve_response_code($wp_result) . "\n";
                echo "WordPress HTTP API content length: " . strlen(wp_remote_retrieve_body($wp_result)) . "\n";
            }
            
        } catch (Exception $e) {
            echo "Exception: " . $e->getMessage() . "\n";
        }
        
        echo "DEBUG RESPONSE END\n";
        exit;
    }

    /**
     * Display debug tools page
     */
    public function display_debug_tools_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'cova-integration'));
        }
        
        // Get a real product from the database for testing
        global $wpdb;
        $real_product = $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}cova_products WHERE is_archived = 0 LIMIT 1",
            ARRAY_A
        );
        
        $product_id = '';
        $image_url = '';
        
        if ($real_product) {
            $product_id = $real_product['product_id'];
            $product_data = json_decode($real_product['data'], true);
            
            // Try to extract image URL
            if (!empty($product_data['HeroShotUri'])) {
                $image_url = $product_data['HeroShotUri'];
            } elseif (!empty($product_data['Assets']) && is_array($product_data['Assets'])) {
                foreach ($product_data['Assets'] as $asset) {
                    if (isset($asset['Type']) && $asset['Type'] === 'Image' && !empty($asset['Url'])) {
                        $image_url = $asset['Url'];
                        break;
                    }
                }
            }
        }
        
        // Get more products with igmetrix URLs for testing
        $igmetrix_products = [];
        $products = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}cova_products WHERE is_archived = 0 LIMIT 10",
            ARRAY_A
        );
        
        if ($products) {
            foreach ($products as $product) {
                $product_data = json_decode($product['data'], true);
                $product_images = [];
                
                // Try to extract all image URLs
                if (!empty($product_data['HeroShotUri'])) {
                    $product_images[] = $product_data['HeroShotUri'];
                }
                
                if (!empty($product_data['Assets']) && is_array($product_data['Assets'])) {
                    foreach ($product_data['Assets'] as $asset) {
                        if (isset($asset['Type']) && $asset['Type'] === 'Image' && !empty($asset['Url'])) {
                            $product_images[] = $asset['Url'];
                        }
                    }
                }
                
                // Only add products with igmetrix URLs
                foreach ($product_images as $url) {
                    if (strpos($url, 'igmetrix.net') !== false) {
                        $igmetrix_products[] = [
                            'id' => $product['product_id'],
                            'name' => $product['name'],
                            'url' => $url
                        ];
                        break;
                    }
                }
            }
        }
        
        ?>
        <div class="wrap">
            <h1><?php _e('Cova Debug Tools', 'cova-integration'); ?></h1>
            
            <div class="cova-card">
                <h2><?php _e('Image Processing Debug', 'cova-integration'); ?></h2>
                <p><?php _e('Use these tools to debug image processing issues.', 'cova-integration'); ?></p>
                
                <div class="cova-debug-section">
                    <h3><?php _e('Test Image URL', 'cova-integration'); ?></h3>
                    <p><?php _e('Enter an image URL to test downloading it directly.', 'cova-integration'); ?></p>
                    
                    <input type="text" id="debug-image-url" class="regular-text" placeholder="https://example.com/image.jpg" value="<?php echo isset($_GET['url']) ? esc_attr($_GET['url']) : esc_attr($image_url); ?>">
                    <button id="test-image-download" class="button button-primary"><?php _e('Test Download', 'cova-integration'); ?></button>
                    
                    <div id="debug-result" style="margin-top: 20px; display: none;">
                        <h4><?php _e('Results', 'cova-integration'); ?></h4>
                        <pre class="debug-output"></pre>
                    </div>
                </div>
                
                <div class="cova-debug-section">
                    <h3><?php _e('Available igmetrix Test Products', 'cova-integration'); ?></h3>
                    <p><?php _e('The following products have igmetrix image URLs that can be used for testing.', 'cova-integration'); ?></p>
                    
                    <?php if (!empty($igmetrix_products)): ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Product ID', 'cova-integration'); ?></th>
                                    <th><?php _e('Name', 'cova-integration'); ?></th>
                                    <th><?php _e('Image URL', 'cova-integration'); ?></th>
                                    <th><?php _e('Actions', 'cova-integration'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($igmetrix_products as $product): ?>
                                    <tr>
                                        <td><?php echo esc_html($product['id']); ?></td>
                                        <td><?php echo esc_html($product['name']); ?></td>
                                        <td>
                                            <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo esc_html($product['url']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <button class="button use-for-test" data-url="<?php echo esc_attr($product['url']); ?>" data-product-id="<?php echo esc_attr($product['id']); ?>">
                                                <?php _e('Use for Test', 'cova-integration'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p><?php _e('No products with igmetrix URLs found.', 'cova-integration'); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="cova-debug-section">
                    <h3><?php _e('Error Log', 'cova-integration'); ?></h3>
                    <p><?php _e('Recent errors related to image processing.', 'cova-integration'); ?></p>
                    
                    <?php
                    // Get errors from the log
                    $errors = $this->get_image_processing_errors();
                    if (!empty($errors)) {
                        echo '<div class="debug-log-container">';
                        echo '<pre>' . esc_html(implode("\n", $errors)) . '</pre>';
                        echo '</div>';
                    } else {
                        echo '<p>' . __('No recent errors found.', 'cova-integration') . '</p>';
                    }
                    ?>
                </div>
                
                <div class="cova-debug-section">
                    <h3><?php _e('Raw Server Response', 'cova-integration'); ?></h3>
                    <p><?php _e('Capture the raw server response when processing an image.', 'cova-integration'); ?></p>
                    
                    <div id="test-product-data">
                        <div style="margin-bottom: 10px;">
                            <strong><?php _e('Product ID:', 'cova-integration'); ?></strong>
                            <input type="text" id="test-product-id" value="<?php echo esc_attr($product_id); ?>" class="regular-text">
                        </div>
                        <div style="margin-bottom: 10px;">
                            <strong><?php _e('Image URL:', 'cova-integration'); ?></strong>
                            <input type="text" id="test-image-url-raw" value="<?php echo esc_attr($image_url); ?>" class="regular-text">
                        </div>
                    </div>
                    
                    <button id="capture-raw-response" class="button button-primary"><?php _e('Capture Response', 'cova-integration'); ?></button>
                    
                    <div id="raw-response-result" style="margin-top: 20px; display: none;">
                        <h4><?php _e('Raw Response', 'cova-integration'); ?></h4>
                        <pre class="debug-output"></pre>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .cova-card {
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0,0,0,.04);
                margin-bottom: 20px;
                padding: 20px;
            }
            
            .cova-debug-section {
                margin-bottom: 30px;
                padding-bottom: 30px;
                border-bottom: 1px solid #eee;
            }
            
            .cova-debug-section:last-child {
                border-bottom: none;
                padding-bottom: 0;
            }
            
            .debug-log-container,
            .debug-output {
                background: #f8f9fa;
                border: 1px solid #ddd;
                padding: 15px;
                overflow: auto;
                max-height: 400px;
                font-family: monospace;
                white-space: pre-wrap;
            }
        </style>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.use-for-test').on('click', function() {
                    var url = $(this).data('url');
                    var productId = $(this).data('product-id');
                    
                    $('#debug-image-url').val(url);
                    $('#test-image-url-raw').val(url);
                    $('#test-product-id').val(productId);
                    
                    // Scroll to top of the page
                    $('html, body').animate({
                        scrollTop: 0
                    }, 500);
                });
                
                $('#test-image-download').on('click', function() {
                    var $button = $(this);
                    var url = $('#debug-image-url').val();
                    
                    if (!url) {
                        alert('Please enter an image URL');
                        return;
                    }
                    
                    $button.prop('disabled', true).text('Testing...');
                    $('#debug-result').hide();
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cova_debug_image_process',
                            url: url,
                            nonce: '<?php echo wp_create_nonce('cova_debug_nonce'); ?>'
                        },
                        success: function(response) {
                            $('#debug-result').show();
                            $('#debug-result .debug-output').text(response);
                            $button.prop('disabled', false).text('Test Download');
                        },
                        error: function(xhr, status, error) {
                            $('#debug-result').show();
                            $('#debug-result .debug-output').text('Error: ' + error + '\n\nResponse: ' + xhr.responseText);
                            $button.prop('disabled', false).text('Test Download');
                        }
                    });
                });
                
                $('#capture-raw-response').on('click', function() {
                    var $button = $(this);
                    var productId = $('#test-product-id').val();
                    var imageUrl = $('#test-image-url-raw').val();
                    
                    if (!productId || !imageUrl) {
                        alert('Please enter both product ID and image URL');
                        return;
                    }
                    
                    $button.prop('disabled', true).text('Capturing...');
                    $('#raw-response-result').hide();
                    
                    // Use XMLHttpRequest for greater control
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', ajaxurl, true);
                    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    xhr.responseType = 'text';  // We want the raw text
                    
                    xhr.onload = function() {
                        var rawResponse = 'Status: ' + xhr.status + '\n\n';
                        rawResponse += 'Headers:\n';
                        
                        var headers = xhr.getAllResponseHeaders().split('\r\n');
                        for (var i = 0; i < headers.length; i++) {
                            if (headers[i]) {
                                rawResponse += headers[i] + '\n';
                            }
                        }
                        
                        rawResponse += '\nBody:\n' + xhr.responseText;
                        
                        $('#raw-response-result').show();
                        $('#raw-response-result .debug-output').text(rawResponse);
                        $button.prop('disabled', false).text('Capture Response');
                    };
                    
                    xhr.onerror = function() {
                        $('#raw-response-result').show();
                        $('#raw-response-result .debug-output').text('XHR Error occurred');
                        $button.prop('disabled', false).text('Capture Response');
                    };
                    
                    // Send a test process_single_image request with real product ID and URL
                    xhr.send('action=cova_process_single_image&product_id=' + encodeURIComponent(productId) + 
                             '&image_url=' + encodeURIComponent(imageUrl) + 
                             '&nonce=<?php echo wp_create_nonce('cova_process_single_image_nonce'); ?>');
                });
            });
        </script>
        <?php
    }
    
    /**
     * Get recent image processing errors from the log
     * 
     * @return array Array of error log entries
     */
    private function get_image_processing_errors() {
        $errors = array();
        
        // Only attempt to read the error log if it exists and is readable
        $error_log_path = ini_get('error_log');
        if (file_exists($error_log_path) && is_readable($error_log_path)) {
            $log_content = file_get_contents($error_log_path);
            if ($log_content) {
                // Get last 100 lines
                $lines = explode("\n", $log_content);
                $lines = array_slice($lines, -100);
                
                // Filter for COVA related errors
                foreach ($lines as $line) {
                    if (strpos($line, '[COVA') !== false) {
                        $errors[] = $line;
                    }
                }
            }
        }
        
        // If we couldn't get the error log, create a note about it
        if (empty($errors)) {
            $errors[] = 'Could not access error log at: ' . $error_log_path;
            $errors[] = 'Recent errors will be displayed here.';
        }
        
        return $errors;
    }
    
    /**
     * AJAX handler for checking image URL redirects
     */
    public function ajax_check_image_redirects() {
        // Disable error display
        error_reporting(0);
        @ini_set('display_errors', 0);
        
        // Set JSON headers
        header('Content-Type: application/json');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Clear any previous output
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cova_check_redirects_nonce')) {
                $this->json_output(['success' => false, 'data' => 'Security check failed']);
                exit;
            }
            
            if (!current_user_can('manage_options')) {
                $this->json_output(['success' => false, 'data' => 'Insufficient permissions']);
                exit;
            }
            
            $product_id = isset($_POST['product_id']) ? sanitize_text_field($_POST['product_id']) : '';
            $image_urls = isset($_POST['image_urls']) ? json_decode(stripslashes($_POST['image_urls']), true) : [];
            
            if (empty($product_id) || empty($image_urls) || !is_array($image_urls)) {
                $this->json_output(['success' => false, 'data' => 'Missing required parameters']);
                exit;
            }
            
            error_log('[COVA Redirects] Checking redirects for product: ' . $product_id);
            
            $redirect_results = [];
            
            foreach ($image_urls as $index => $url) {
                $label = $index === 0 ? 'Hero' : 'Asset ' . $index;
                
                error_log('[COVA Redirects] Checking URL: ' . $url);
                
                $final_url = $this->follow_url_redirects($url);
                
                $redirect_results[] = [
                    'label' => $label,
                    'original_url' => $url,
                    'final_url' => $final_url,
                    'is_redirected' => ($url !== $final_url),
                    'is_igmetrix' => (strpos($url, 'igmetrix.net') !== false),
                    'final_is_igmetrix' => (strpos($final_url, 'igmetrix.net') !== false)
                ];
            }
            
            error_log('[COVA Redirects] Results: ' . json_encode($redirect_results));
            
            $this->json_output([
                'success' => true, 
                'data' => [
                    'product_id' => $product_id,
                    'redirects' => $redirect_results
                ]
            ]);
            exit;
            
        } catch (Exception $e) {
            error_log('[COVA Redirects] Exception: ' . $e->getMessage());
            $this->json_output(['success' => false, 'data' => 'Exception: ' . $e->getMessage()]);
            exit;
        }
    }
    
    /**
     * Follow URL redirects to find the final destination using robust cURL method
     *
     * @param string $url The URL to follow
     * @param int $max_redirects Maximum number of redirects to follow
     * @return string The final URL after following redirects
     */
    private function follow_url_redirects($url, $max_redirects = 10) {
        error_log('[COVA Redirects] Starting redirect check for: ' . $url);
        
        // First try the robust cURL method if available
        if (function_exists('curl_init')) {
            $ch = curl_init();
            
            // Configure cURL for following redirects
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // Follow redirects automatically
            curl_setopt($ch, CURLOPT_MAXREDIRS, $max_redirects); // Max redirects to follow
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_NOBODY, true); // HEAD request only (no body content)
            
            // Set proper headers to avoid being blocked
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36',
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1',
            ));
            
            // Execute the request
            $result = curl_exec($ch);
            
            if ($result !== false) {
                // Get the effective URL (final URL after all redirects)
                $final_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $redirect_count = curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
                
                error_log('[COVA Redirects] cURL result - Final URL: ' . $final_url . ', HTTP Code: ' . $http_code . ', Redirect Count: ' . $redirect_count);
                
                curl_close($ch);
                
                // Return the final URL if we got a valid response
                if (!empty($final_url) && $http_code < 400) {
                    return $final_url;
                }
            } else {
                $curl_error = curl_error($ch);
                error_log('[COVA Redirects] cURL error: ' . $curl_error);
                curl_close($ch);
            }
        }
        
        // Fallback to WordPress HTTP API method if cURL fails
        error_log('[COVA Redirects] Falling back to WordPress HTTP API method');
        
        $redirect_count = 0;
        $current_url = $url;
        
        while ($redirect_count < $max_redirects) {
            // Use WordPress HTTP API to get headers only
            $response = wp_remote_head($current_url, [
                'timeout' => 30,
                'redirection' => 0, // Don't follow redirects automatically
                'sslverify' => false,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.110 Safari/537.36'
                ]
            ]);
            
            if (is_wp_error($response)) {
                error_log('[COVA Redirects] WordPress HTTP API error: ' . $response->get_error_message());
                break;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            error_log('[COVA Redirects] URL: ' . $current_url . ' - Status: ' . $status_code);
            
            // Check if it's a redirect status code
            if (in_array($status_code, [301, 302, 303, 307, 308])) {
                $location = wp_remote_retrieve_header($response, 'location');
                
                if ($location) {
                    // Handle relative URLs
                    if (strpos($location, 'http') !== 0) {
                        $parsed_url = parse_url($current_url);
                        if ($location[0] === '/') {
                            // Absolute path
                            $location = $parsed_url['scheme'] . '://' . $parsed_url['host'] . $location;
                        } else {
                            // Relative path
                            $location = $parsed_url['scheme'] . '://' . $parsed_url['host'] . dirname($parsed_url['path']) . '/' . $location;
                        }
                    }
                    
                    error_log('[COVA Redirects] Redirected to: ' . $location);
                    $current_url = $location;
                    $redirect_count++;
                } else {
                    // No location header, stop following
                    break;
                }
            } else {
                // Not a redirect, we've reached the final URL
                break;
            }
        }
        
        if ($redirect_count >= $max_redirects) {
            error_log('[COVA Redirects] Max redirects reached for: ' . $url);
        }
        
        return $current_url;
    }

    /**
     * Get the final URL after following all redirects using simple cURL approach
     *
     * @param string $url The initial URL to check
     * @return string|false The final URL, or false on failure
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
} 