<?php
/**
 * API Tester class for Cova Integration
 *
 * @since 1.0.0
 */
class Cova_API_Tester {
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
        
        // Add AJAX handler for testing connection
        add_action('wp_ajax_cova_test_api_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_cova_test_api_endpoint', array($this, 'ajax_test_connection'));
    }
    
    /**
     * Display API tester page
     */
    public function display_tester_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-warning">
                <p><?php _e('This page is for testing and debugging the Cova API connection. Use the buttons below to test the API functionality.', 'cova-integration'); ?></p>
            </div>
            
            <div class="cova-tester-section">
                <h2><?php _e('API Connection Test', 'cova-integration'); ?></h2>
                
                <div class="cova-api-credentials">
                    <h3><?php _e('Current API Credentials', 'cova-integration'); ?></h3>
                    <p>
                        <?php _e('Client ID:', 'cova-integration'); ?> 
                        <code><?php echo esc_html(get_option('cova_integration_client_id', __('Not set', 'cova-integration'))); ?></code>
                    </p>
                    <p>
                        <?php _e('Username:', 'cova-integration'); ?> 
                        <code><?php echo esc_html(get_option('cova_integration_username', __('Not set', 'cova-integration'))); ?></code>
                    </p>
                    <p>
                        <?php _e('Company ID:', 'cova-integration'); ?> 
                        <code><?php echo esc_html(get_option('cova_integration_company_id', __('Not set', 'cova-integration'))); ?></code>
                    </p>
                    
                    <p><a href="<?php echo admin_url('admin.php?page=cova-integration-settings'); ?>" class="button"><?php _e('Edit Credentials', 'cova-integration'); ?></a></p>
                </div>
                
                <div class="cova-test-connection">
                    <h3><?php _e('Test API Endpoints', 'cova-integration'); ?></h3>
                    <p><?php _e('Click the buttons below to test each API endpoint:', 'cova-integration'); ?></p>
                    
                    <button id="cova-test-auth" class="button button-primary"><?php _e('Test Authentication', 'cova-integration'); ?></button>
                    <button id="cova-test-products" class="button"><?php _e('Test Products', 'cova-integration'); ?></button>
                    <button id="cova-test-inventory" class="button"><?php _e('Test Inventory', 'cova-integration'); ?></button>
                    <button id="cova-test-prices" class="button"><?php _e('Test Prices', 'cova-integration'); ?></button>
                    
                    <div id="cova-test-results" style="display: none;">
                        <h3><?php _e('Test Results', 'cova-integration'); ?></h3>
                        <div id="cova-test-output" class="cova-test-output"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .cova-tester-section {
                margin-top: 20px;
            }
            
            .cova-api-credentials, .cova-test-connection {
                background: #fff;
                border: 1px solid #ccd0d4;
                padding: 15px;
                margin-bottom: 20px;
            }
            
            .cova-test-output {
                background: #f5f5f5;
                border: 1px solid #ddd;
                padding: 15px;
                margin: 15px 0;
                max-height: 400px;
                overflow-y: auto;
                font-family: monospace;
            }
            
            .cova-test-success {
                color: #46b450;
            }
            
            .cova-test-error {
                color: #dc3232;
            }
            
            .cova-test-warning {
                color: #ffb900;
            }
        </style>
        
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                function runTest(endpoint) {
                    $('#cova-test-results').show();
                    $('#cova-test-output').html('<p><?php _e('Testing...', 'cova-integration'); ?></p>');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'cova_test_api_connection',
                            nonce: '<?php echo wp_create_nonce('cova_test_api_nonce'); ?>',
                            endpoint: endpoint
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#cova-test-output').html(response.data);
                            } else {
                                $('#cova-test-output').html('<p class="cova-test-error">' + response.data + '</p>');
                            }
                        },
                        error: function() {
                            $('#cova-test-output').html('<p class="cova-test-error"><?php _e('An error occurred during the test. Please try again.', 'cova-integration'); ?></p>');
                        }
                    });
                }
                
                $('#cova-test-auth').on('click', function() {
                    runTest('auth');
                });
                
                $('#cova-test-products').on('click', function() {
                    runTest('products');
                });
                
                $('#cova-test-inventory').on('click', function() {
                    runTest('inventory');
                });
                
                $('#cova-test-prices').on('click', function() {
                    runTest('prices');
                });
            });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for testing API connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('cova_test_api_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'cova-integration'));
            return;
        }
        
        $endpoint = isset($_POST['endpoint']) ? sanitize_text_field($_POST['endpoint']) : '';
        
        if (empty($endpoint)) {
            wp_send_json_error(__('No endpoint specified', 'cova-integration'));
            return;
        }
        
        ob_start();
        
        switch ($endpoint) {
            case 'auth':
                $this->test_authentication();
                break;
                
            case 'products':
                $this->test_products();
                break;
                
            case 'inventory':
                $this->test_inventory();
                break;
                
            case 'prices':
                $this->test_prices();
                break;
                
            default:
                echo '<p class="cova-test-error">' . __('Invalid endpoint', 'cova-integration') . '</p>';
                break;
        }
        
        $output = ob_get_clean();
        
        wp_send_json_success($output);
    }
    
    /**
     * Test authentication
     */
    private function test_authentication() {
        echo '<h4>' . __('Authentication Test', 'cova-integration') . '</h4>';
        
        $token = $this->api_client->get_token();
        
        if (is_wp_error($token)) {
            echo '<p class="cova-test-error">' . __('Authentication Failed:', 'cova-integration') . ' ' . $token->get_error_message() . '</p>';
            
            // Check if credentials exist
            if (empty(get_option('cova_integration_client_id')) || 
                empty(get_option('cova_integration_client_secret')) || 
                empty(get_option('cova_integration_username')) || 
                empty(get_option('cova_integration_password')) || 
                empty(get_option('cova_integration_company_id'))) {
                echo '<p class="cova-test-warning">' . __('One or more API credentials are missing. Please check your settings.', 'cova-integration') . '</p>';
            }
        } else {
            echo '<p class="cova-test-success">' . __('Authentication Successful! Token received.', 'cova-integration') . '</p>';
            
            // Only show first few characters of token for security
            $token_preview = substr($token, 0, 20) . '...';
            echo '<p>' . __('Token:', 'cova-integration') . ' <code>' . $token_preview . '</code></p>';
        }
    }
    
    /**
     * Test products endpoint
     */
    private function test_products() {
        echo '<h4>' . __('Products API Test', 'cova-integration') . '</h4>';
        
        $company_id = get_option('cova_integration_company_id');
        $location_id = get_option('cova_integration_location_id');
        
        if (empty($company_id)) {
            echo '<p class="cova-test-error">' . __('Company ID is not set in the plugin settings.', 'cova-integration') . '</p>';
            return;
        }
        
        if (empty($location_id)) {
            echo '<p class="cova-test-error">' . __('Location ID is not set in the plugin settings.', 'cova-integration') . '</p>';
            return;
        }
        
        $endpoint = "/dataplatform/v1/companies/{$company_id}/DetailedProductData";
        
        $args = array(
            'body' => array(
                'LocationId' => (int) $location_id,
                'IncludeProductSkusAndUpcs' => true,
                'IncludeProductSpecifications' => true,
                'IncludeClassifications' => true,
                'IncludeProductAssets' => true,
                'IncludeAvailability' => true,
                'IncludePackageDetails' => true,
                'IncludePricing' => true,
                'IncludeTaxes' => true,
                'InStockOnly' => false,
                'IncludeAllLifecycles' => true,
                'SellingRoomOnly' => false,
                'Skip' => 0,
                'Top' => 10 // Limit to 10 for testing
            )
        );
        
        $response = $this->api_client->request($endpoint, 'POST', $args);
        
        if (is_wp_error($response)) {
            echo '<p class="cova-test-error">' . __('Failed to get products: ', 'cova-integration') . $response->get_error_message() . '</p>';
            return;
        }
        
        if (empty($response['Products'])) {
            echo '<p class="cova-test-warning">' . __('Success, but no products were returned.', 'cova-integration') . '</p>';
        } else {
            $count = count($response['Products']);
            echo '<p class="cova-test-success">' . sprintf(__('Success! Retrieved %d products.', 'cova-integration'), $count) . '</p>';
            
            // Show a sample of the first product
            if ($count > 0) {
                $product = $response['Products'][0];
                echo '<h5>' . __('Sample Product Data:', 'cova-integration') . '</h5>';
                echo '<pre>';
                // Show limited fields
                $sample = array(
                    'ProductId' => isset($product['ProductId']) ? $product['ProductId'] : 'N/A',
                    'Name' => isset($product['Name']) ? $product['Name'] : 'N/A',
                    'CategoryName' => isset($product['CategoryName']) ? $product['CategoryName'] : 'N/A',
                );
                print_r($sample);
                echo '</pre>';
            }
        }
    }
    
    /**
     * Test inventory endpoint
     */
    private function test_inventory() {
        echo '<h4>' . __('Inventory API Test (Using DataPlatform)', 'cova-integration') . '</h4>';
        
        $company_id = get_option('cova_integration_company_id');
        $location_id = get_option('cova_integration_location_id');
        
        if (empty($company_id)) {
            echo '<p class="cova-test-error">' . __('Company ID is not set in the plugin settings.', 'cova-integration') . '</p>';
            return;
        }
        
        if (empty($location_id)) {
            echo '<p class="cova-test-error">' . __('Location ID is not set in the plugin settings.', 'cova-integration') . '</p>';
            return;
        }
        
        // Using DataPlatform instead of direct inventory endpoint
        $endpoint = "/dataplatform/v1/companies/{$company_id}/DetailedProductData";
        
        $args = array(
            'body' => array(
                'LocationId' => (int) $location_id,
                'IncludeProductSkusAndUpcs' => true,
                'IncludeAvailability' => true,
                'InStockOnly' => true, // Only get products in stock
                'Skip' => 0,
                'Top' => 10 // Limit to 10 for testing
            )
        );
        
        $response = $this->api_client->request($endpoint, 'POST', $args);
        
        if (is_wp_error($response)) {
            echo '<p class="cova-test-error">' . __('Failed to get inventory: ', 'cova-integration') . $response->get_error_message() . '</p>';
            return;
        }
        
        if (empty($response['Products'])) {
            echo '<p class="cova-test-warning">' . __('Success, but no products with inventory were returned.', 'cova-integration') . '</p>';
        } else {
            $count = count($response['Products']);
            echo '<p class="cova-test-success">' . sprintf(__('Success! Retrieved %d products with inventory data.', 'cova-integration'), $count) . '</p>';
            
            // Show a sample of the first product's inventory
            if ($count > 0) {
                $product = $response['Products'][0];
                echo '<h5>' . __('Sample Inventory Data:', 'cova-integration') . '</h5>';
                echo '<pre>';
                // Show limited fields
                $sample = array(
                    'ProductId' => isset($product['ProductId']) ? $product['ProductId'] : 'N/A',
                    'Name' => isset($product['Name']) ? $product['Name'] : 'N/A',
                    'QuantityOnHand' => isset($product['QuantityOnHand']) ? $product['QuantityOnHand'] : 'N/A',
                );
                print_r($sample);
                echo '</pre>';
            }
        }
    }
    
    /**
     * Test prices endpoint
     */
    private function test_prices() {
        echo '<h4>' . __('Prices API Test', 'cova-integration') . '</h4>';
        
        $company_id = get_option('cova_integration_company_id');
        $location_id = get_option('cova_integration_location_id');
        
        if (empty($company_id)) {
            echo '<p class="cova-test-error">' . __('Company ID is not set in the plugin settings.', 'cova-integration') . '</p>';
            return;
        }
        
        if (empty($location_id)) {
            echo '<p class="cova-test-error">' . __('Location ID is not set in the plugin settings.', 'cova-integration') . '</p>';
            return;
        }
        
        $endpoint = "/pricing/v1/Companies({$company_id})/ProductPrices?";
        $endpoint .= '$filter=EntityId eq ' . $location_id;
        $endpoint .= '&$skip=0&$top=10'; // Limit to 10 for testing
        
        $response = $this->api_client->request($endpoint);
        
        if (is_wp_error($response)) {
            echo '<p class="cova-test-error">' . __('Failed to get prices: ', 'cova-integration') . $response->get_error_message() . '</p>';
            return;
        }
        
        if (empty($response)) {
            echo '<p class="cova-test-warning">' . __('Success, but no price data was returned.', 'cova-integration') . '</p>';
        } else {
            $count = count($response);
            echo '<p class="cova-test-success">' . sprintf(__('Success! Retrieved %d price records.', 'cova-integration'), $count) . '</p>';
            
            // Show a sample of the first price record
            if ($count > 0) {
                $price = $response[0];
                echo '<h5>' . __('Sample Price Data:', 'cova-integration') . '</h5>';
                echo '<pre>';
                // Show limited fields
                $sample = array(
                    'Id' => isset($price['Id']) ? $price['Id'] : 'N/A',
                    'EntityId' => isset($price['EntityId']) ? $price['EntityId'] : 'N/A',
                    'CatalogItemId' => isset($price['CatalogItemId']) ? $price['CatalogItemId'] : 'N/A',
                    'RegularPrice' => isset($price['RegularPrice']) ? $price['RegularPrice'] : 'N/A',
                );
                print_r($sample);
                echo '</pre>';
            }
        }
    }
} 