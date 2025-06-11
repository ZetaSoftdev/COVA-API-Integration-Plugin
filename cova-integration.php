<?php
/**
 * Plugin Name: Cova Integration
 * Plugin URI: https://www.zetasoft.org/
 * Description: Integrates WordPress with the Cova API for cannabis retail, enabling the display of product information, inventory, and pricing from your Cova retail system.
 * Version: 1.0.0
 * Author: Azeem Ushan
 * Author URI: https://linkedin.com/in/azeemushan
 * Text Domain: cova-integration
 * Domain Path: /languages
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('COVA_INTEGRATION_VERSION', '1.0.0');
define('COVA_INTEGRATION_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('COVA_INTEGRATION_PLUGIN_URL', plugin_dir_url(__FILE__));
define('COVA_INTEGRATION_PLUGIN_FILE', __FILE__);
define('COVA_INTEGRATION_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once COVA_INTEGRATION_PLUGIN_DIR . 'includes/class-cova-api-client.php';
require_once COVA_INTEGRATION_PLUGIN_DIR . 'includes/class-cova-admin.php';
require_once COVA_INTEGRATION_PLUGIN_DIR . 'includes/class-cova-shortcodes.php';
require_once COVA_INTEGRATION_PLUGIN_DIR . 'includes/class-cova-woocommerce.php';
require_once COVA_INTEGRATION_PLUGIN_DIR . 'includes/class-cova-api-tester.php';

/**
 * Main Cova Integration class
 */
class Cova_Integration {
    /**
     * The single instance of the class
     *
     * @var Cova_Integration
     */
    protected static $_instance = null;

    /**
     * API Client instance
     *
     * @var Cova_API_Client
     */
    public $api_client = null;

    /**
     * Admin instance
     *
     * @var Cova_Admin
     */
    public $admin = null;

    /**
     * Shortcodes instance
     *
     * @var Cova_Shortcodes
     */
    public $shortcodes = null;

    /**
     * WooCommerce instance
     *
     * @var Cova_WooCommerce
     */
    public $woocommerce = null;

    /**
     * Main Cova_Integration Instance
     *
     * Ensures only one instance is loaded or can be loaded.
     *
     * @return Cova_Integration - Main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
        
        // Initialize components
        $this->api_client = new Cova_API_Client();
        $this->admin = new Cova_Admin();
        $this->shortcodes = new Cova_Shortcodes($this->api_client);
        
        // Initialize API Tester
        new Cova_API_Tester();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(COVA_INTEGRATION_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(COVA_INTEGRATION_PLUGIN_FILE, array($this, 'deactivate'));
        
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));
        add_action('plugins_loaded', array($this, 'init_woocommerce_integration'));
    }

    /**
     * Initialize WooCommerce integration
     */
    public function init_woocommerce_integration() {
        // Initialize WooCommerce integration if WooCommerce is active
        if (class_exists('WooCommerce')) {
            // Ensure the WooCommerce class is loaded
            if (!class_exists('Cova_WooCommerce')) {
                require_once COVA_INTEGRATION_PLUGIN_DIR . 'includes/class-cova-woocommerce.php';
            }
            
            $this->woocommerce = new Cova_WooCommerce();
            
            // Register the AJAX handler for WooCommerce sync
            add_action('wp_ajax_cova_sync_products_with_woocommerce', array($this->woocommerce, 'ajax_sync_products_with_woocommerce'));
        }
    }

    /**
     * Create required database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Products table
        $table_name = $wpdb->prefix . 'cova_products';
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id varchar(255) NOT NULL,
            name varchar(255) NOT NULL,
            master_product_id varchar(255),
            category varchar(255),
            catalog_sku varchar(255),
            description longtext,
            is_archived tinyint(1) DEFAULT 0,
            created_date datetime,
            updated_date datetime,
            data longtext,
            last_sync datetime,
            PRIMARY KEY  (id),
            UNIQUE KEY product_id (product_id),
            KEY category (category),
            KEY is_archived (is_archived)
        ) $charset_collate;";
        
        // Prices table
        $prices_table = $wpdb->prefix . 'cova_prices';
        
        $sql .= "CREATE TABLE $prices_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            price_id varchar(255) NOT NULL,
            entity_id varchar(255) NOT NULL,
            catalog_item_id varchar(255) NOT NULL,
            regular_price decimal(10,2) DEFAULT 0,
            at_tier_price decimal(10,2) DEFAULT 0,
            tier_name varchar(255) DEFAULT '',
            data longtext,
            last_sync datetime,
            PRIMARY KEY  (id),
            UNIQUE KEY price_id (price_id),
            KEY catalog_item_id (catalog_item_id)
        ) $charset_collate;";
        
        // Inventory table
        $inventory_table = $wpdb->prefix . 'cova_inventory';
        
        $sql .= "CREATE TABLE $inventory_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            inventory_id varchar(255) NOT NULL,
            product_id varchar(255) NOT NULL,
            location_id varchar(255) NOT NULL,
            quantity int(11) DEFAULT 0,
            available_quantity int(11) DEFAULT 0,
            reserved_quantity int(11) DEFAULT 0,
            data longtext,
            last_sync datetime,
            PRIMARY KEY  (id),
            UNIQUE KEY inventory_id (inventory_id),
            KEY product_id (product_id),
            KEY location_id (location_id)
        ) $charset_collate;";
        
        // Error log table
        $log_table = $wpdb->prefix . 'cova_error_logs';
        
        $sql .= "CREATE TABLE $log_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message text NOT NULL,
            type varchar(10) DEFAULT 'error',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY type (type)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Activation function
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Set up default options
        $default_options = array(
            'client_id' => '',
            'client_secret' => '',
            'username' => '',
            'password' => '',
            'company_id' => '',
            'location_id' => '',
            'token_cache_time' => 43200, // 12 hours in seconds
            'sync_interval' => 'hourly',
            'last_sync' => 0,
            'enable_age_verification' => 'yes',
            'min_age' => 21,
        );
        
        // Only add options if they don't exist
        foreach ($default_options as $key => $value) {
            if (get_option('cova_integration_' . $key) === false) {
                add_option('cova_integration_' . $key, $value);
            }
        }

        // Clear any existing scheduled events
        wp_clear_scheduled_hook('cova_integration_sync');
        
        // Schedule sync event
        if (!wp_next_scheduled('cova_integration_sync')) {
            wp_schedule_event(time(), 'hourly', 'cova_integration_sync');
        }
        
        // Set a flag for redirect to settings page
        set_transient('cova_integration_activated', true, 30);
    }

    /**
     * Deactivation function
     */
    public function deactivate() {
        // Clean up scheduled events
        wp_clear_scheduled_hook('cova_integration_sync');
    }

    /**
     * Load plugin text domain
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain('cova-integration', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
}

/**
 * Returns the main instance of Cova_Integration
 *
 * @return Cova_Integration
 */
function Cova_Integration() {
    return Cova_Integration::instance();
}

// Initialize the plugin
Cova_Integration();

// Register action hooks
add_action('cova_integration_sync_event', 'cova_integration_sync_data');
add_action('wp_ajax_cova_clear_logs', 'cova_integration_clear_logs');
add_action('wp_ajax_cova_force_sync', 'cova_integration_force_sync');
add_filter('cova_frontend_params', 'cova_integration_frontend_params');

// Register sync callback
add_action('cova_integration_sync', 'cova_integration_sync_callback');

/**
 * Callback function for the cova_integration_sync scheduled event
 */
function cova_integration_sync_callback() {
    $api_client = Cova_Integration()->api_client;
    
    // Log that sync has started
    error_log('Cova Integration - Starting scheduled sync');
    
    // Sync products
    $api_client->sync_products();
    
    // Sync prices
    $api_client->sync_prices();
    
    // Update last sync time
    update_option('cova_integration_last_sync', time());
    
    // Log that sync has completed
    error_log('Cova Integration - Scheduled sync completed');
}

/**
 * Check if WooCommerce is active
 *
 * @return bool Whether WooCommerce is active
 */
function cova_is_woocommerce_active() {
    return class_exists('WooCommerce');
}

/**
 * Add admin notice if WooCommerce is not active
 */
function cova_woocommerce_notice() {
    if (!cova_is_woocommerce_active() && get_option('cova_integration_wc_sync_enabled', true)) {
        ?>
        <div class="notice notice-warning is-dismissible">
            <p><?php _e('Cova Integration: WooCommerce integration is enabled but WooCommerce is not active. Please install and activate WooCommerce to use this feature.', 'cova-integration'); ?></p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'cova_woocommerce_notice');

/**
 * Add frontend parameters for age verification
 *
 * @param array $params Parameters
 * @return array Modified parameters
 */
function cova_integration_frontend_params($params) {
    $params['age_verification_enabled'] = get_option('cova_integration_age_verification_enabled', true);
    $params['age_verification_title'] = get_option('cova_integration_age_verification_title', 'Age Verification');
    $params['age_verification_message'] = get_option('cova_integration_age_verification_message', 'You must be 21 years or older to view this content.');
    $params['age_verification_confirm'] = get_option('cova_integration_age_verification_confirm', 'I am 21 or older');
    $params['age_verification_decline'] = get_option('cova_integration_age_verification_decline', 'I am under 21');
    $params['age_verification_redirect_url'] = get_option('cova_integration_age_verification_redirect_url', '');
    
    return $params;
}

/**
 * Clear error logs
 */
function cova_integration_clear_logs() {
    check_ajax_referer('cova_clear_logs_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'cova_error_logs';
    $wpdb->query("TRUNCATE TABLE $table_name");
    
    wp_send_json_success();
}

/**
 * Force data sync
 */
function cova_integration_force_sync() {
    check_ajax_referer('cova_force_sync_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Insufficient permissions');
        return;
    }
    
    cova_integration_sync_data();
    wp_send_json_success();
}

/**
 * Sync data from Cova API
 */
function cova_integration_sync_data() {
    $api_client = new Cova_API_Client();
    
    // Sync products
    $api_client->sync_products();
    
    // Sync prices
    $api_client->sync_prices();
    
    // Update last sync time
    update_option('cova_last_sync_time', current_time('timestamp'));
} 