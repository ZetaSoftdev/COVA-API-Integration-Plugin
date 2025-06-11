<?php
/**
 * Cova API Client
 *
 * Handles the communication with the Cova API
 *
 * @package Cova_Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cova API Client class
 */
class Cova_API_Client {

    /**
     * Authentication URL
     *
     * @var string
     */
    private $auth_url = 'https://accounts.iqmetrix.net/v1/oauth2/token';
    
    /**
     * API base URL
     *
     * @var string
     */
    private $api_base_url = 'https://api.covasoft.net';
    
    /**
     * Client ID
     *
     * @var string
     */
    private $client_id;
    
    /**
     * Client Secret
     *
     * @var string
     */
    private $client_secret;
    
    /**
     * Username
     *
     * @var string
     */
    private $username;
    
    /**
     * Password
     *
     * @var string
     */
    private $password;
    
    /**
     * Company ID
     *
     * @var string
     */
    private $company_id;
    
    /**
     * Location ID
     *
     * @var string
     */
    private $location_id;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Load API credentials from options
        $this->client_id = get_option('cova_integration_client_id', '');
        $this->client_secret = $this->decrypt(get_option('cova_integration_client_secret', ''));
        $this->username = get_option('cova_integration_username', '');
        $this->password = $this->decrypt(get_option('cova_integration_password', ''));
        $this->company_id = get_option('cova_integration_company_id', '');
        $this->location_id = get_option('cova_integration_location_id', '');
    }
    
    /**
     * Get an access token from the API
     *
     * @return string|WP_Error Access token or WP_Error on failure
     */
    public function get_token() {
        // Check for cached token
        $token_data = get_transient('cova_integration_token');
        
        if (false !== $token_data) {
            return $token_data;
        }
        
        // No valid cached token, request a new one
        $auth_data = array(
                'grant_type' => 'password',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'username' => $this->username,
            'password' => $this->password
        );
        
        $response = wp_remote_post($this->auth_url, array(
            'method' => 'POST',
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'body' => $auth_data,
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            $this->log_error('Authentication failed: ' . $response->get_error_message());
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);
        
        if (401 === $response_code) {
            $error_message = isset($response_data['error_description']) ? $response_data['error_description'] : 'Authentication failed';
            $this->log_error('Authentication failed: ' . $error_message);
            return new WP_Error('authentication_failed', $error_message, array('status' => $response_code));
        }
        
        if (200 !== $response_code) {
            $error_message = isset($response_data['error_description']) ? $response_data['error_description'] : 'Unknown error';
            $this->log_error('Authentication failed: ' . $error_message);
            return new WP_Error('authentication_failed', $error_message, array('status' => $response_code));
        }
        
        if (!isset($response_data['access_token'])) {
            $this->log_error('Authentication failed: No access token in response');
            return new WP_Error('authentication_failed', 'No access token in response', array('status' => $response_code));
        }
        
        // Cache the token
        $cache_time = get_option('cova_integration_token_cache_time', 43200); // Default 12 hours
        set_transient('cova_integration_token', $response_data['access_token'], $cache_time);
        
        return $response_data['access_token'];
    }

    /**
     * Make a request to the Cova API
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $args Request arguments
     * @return array|WP_Error Response data or WP_Error on failure
     */
    public function request($endpoint, $method = 'GET', $args = array()) {
        // Get token
        $token = $this->get_token();
        
        if (is_wp_error($token)) {
            return $token;
        }
        
        // Set up request
        $url = $this->api_base_url . $endpoint;
        
        $request_args = array(
            'method' => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        );
        
        // Add body if provided
        if (!empty($args['body']) && is_array($args['body'])) {
            $request_args['body'] = json_encode($args['body']);
        }
        
        // Log the request we're making
        $this->log_info("Making API request to: " . $url);
        $this->log_info("Request method: " . $method);
        $this->log_info("Request body: " . (isset($request_args['body']) ? $request_args['body'] : 'None'));
        
        // Send request
        $response = wp_remote_request($url, $request_args);
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->log_error('API request failed: ' . $error_message);
            return $response;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_headers = wp_remote_retrieve_headers($response);
        
        // Log response code and headers
        $this->log_info("API response code: " . $response_code);
        
        // Handle token expiration
        if (401 === $response_code) {
            // Token might have expired, clear cache and try again
            $this->log_info("Authentication failed (401), refreshing token and retrying");
            delete_transient('cova_integration_token');
            return $this->request($endpoint, $method, $args);
        }
        
        // Parse response
        $response_data = json_decode($response_body, true);
        
        if (is_null($response_data) && !empty($response_body)) {
            $this->log_error('API response is not valid JSON: ' . substr($response_body, 0, 500));
            return new WP_Error('invalid_response', 'API response is not valid JSON', array('status' => $response_code, 'body' => $response_body));
        }
        
        if ($response_code >= 400) {
            $error_message = '';
            if (is_array($response_data) && isset($response_data['message'])) {
                $error_message = $response_data['message'];
            } elseif (is_array($response_data) && isset($response_data['Message'])) {
                $error_message = $response_data['Message'];
            } elseif (is_array($response_data) && isset($response_data['error']) && isset($response_data['error']['message'])) {
                $error_message = $response_data['error']['message'];
            } else {
                $error_message = 'Endpoint: ' . $endpoint . ' | HTTP Status: ' . $response_code;
            }
            
            $this->log_error('API request failed: ' . $error_message);
            $this->log_error('Response body: ' . substr($response_body, 0, 1000));
            
            return new WP_Error('request_failed', $error_message, array(
                'status' => $response_code, 
                'body' => $response_body,
                'endpoint' => $endpoint,
                'method' => $method,
                'args' => $args
            ));
        }
        
        return $response_data;
    }

    /**
     * Get products updated since a given timestamp
     *
     * @param string $updated_since ISO 8601 timestamp (default: 30 days ago)
     * @return array|WP_Error Products data or WP_Error on failure
     */
    public function get_products_since($updated_since = '') {
        // If no timestamp provided, use 30 days ago
        if (empty($updated_since)) {
            $updated_since = gmdate('Y-m-d\TH:i:s.000\Z', strtotime('-30 days'));
        }
        
        $endpoint = "/dataplatform/v1/companies/{$this->company_id}/DetailedProductData/UpdatedAsOf/{$updated_since}";
        
        $args = array(
            'body' => array(
                'LocationId' => (int) $this->location_id,
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
                'Top' => 500
            )
        );
        
        // Log the request we're about to make for debugging
        $this->log_info("Making DetailedProductData/UpdatedAsOf request with parameters: " . json_encode($args['body']));
        
        return $this->request($endpoint, 'POST', $args);
    }

    /**
     * Get all products
     *
     * @param int $skip Number of records to skip for pagination
     * @param int $top Number of records to return
     * @param bool $in_stock_only Whether to only return products with stock
     * @return array|WP_Error Products data or WP_Error on failure
     */
    public function get_all_products($skip = 0, $top = 500, $in_stock_only = false) {
        $endpoint = "/dataplatform/v1/companies/{$this->company_id}/DetailedProductData";
        
        $args = array(
            'body' => array(
                'LocationId' => (int) $this->location_id,
                'IncludeProductSkusAndUpcs' => true,
                'IncludeProductSpecifications' => true,
                'IncludeClassifications' => true,
                'IncludeProductAssets' => true,
                'IncludeAvailability' => true,
                'IncludePackageDetails' => true,
                'IncludePricing' => true,
                'IncludeTaxes' => true,
                'InStockOnly' => $in_stock_only,
                'IncludeAllLifecycles' => true,
                'SellingRoomOnly' => false,
                'Skip' => $skip,
                'Top' => $top
            )
        );
        
        // Log the request we're about to make for debugging
        $this->log_info("Making DetailedProductData request with parameters: " . json_encode($args['body']));
        
        return $this->request($endpoint, 'POST', $args);
    }

    /**
     * Get product prices
     *
     * @param int $skip Number of records to skip for pagination
     * @param int $top Number of records to return
     * @return array|WP_Error Prices data or WP_Error on failure
     */
    public function get_product_prices($skip = 0, $top = 1000) {
        $endpoint = "/pricing/v1/Companies({$this->company_id})/ProductPrices?";
        $endpoint .= '$filter=EntityId eq ' . $this->location_id;
        $endpoint .= '&$skip=' . $skip;
        $endpoint .= '&$top=' . $top;
        
        return $this->request($endpoint);
    }

    /**
     * Get tax rates
     *
     * @return array|WP_Error Tax rates data or WP_Error on failure
     */
    public function get_tax_rates() {
        $endpoint = "/taxes/v1/Companies({$this->company_id})/TaxRates";
        
        return $this->request($endpoint);
    }

    /**
     * Get tax pricing configuration
     *
     * @return array|WP_Error Tax pricing configuration data or WP_Error on failure
     */
    public function get_tax_pricing_configuration() {
        $endpoint = "/taxes/v1/Companies({$this->company_id})/TaxPricingConfiguration";
        
        return $this->request($endpoint);
    }

    /**
     * Sync products from the API
     *
     * @return bool True on success, false on failure
     */
    public function sync_products() {
        global $wpdb;
        
        $this->log_info('Starting product sync');
        
        // Get the last sync time
        $last_sync = get_option('cova_integration_last_sync', 0);
        
        // If this is the first sync, do a full sync
        if (empty($last_sync)) {
            $result = $this->sync_all_products();
            if (is_wp_error($result)) {
                $this->log_error('Failed to sync all products: ' . $result->get_error_message());
                return false;
            }
        } else {
            // Otherwise, do an incremental sync
            $updated_since = gmdate('Y-m-d\TH:i:s.000\Z', $last_sync);
            $products_data = $this->get_products_since($updated_since);
            
            if (is_wp_error($products_data)) {
                $this->log_error('Failed to sync products: ' . $products_data->get_error_message());
                return false;
            }
            
            // Process products
            if (!empty($products_data['Products'])) {
                $this->process_products($products_data['Products']);
            }
        }
        
        // Now also sync inventory data
        $this->sync_inventory();
        
        $this->log_info('Product sync completed');
        
        return true;
    }

    /**
     * Sync all products with the local database
     *
     * @return bool True on success, false on failure
     */
    public function sync_all_products() {
        global $wpdb;
        
        // Get products in batches
        $skip = 0;
        $top = 500;
        $total_products = 0;
        
        do {
            $products_data = $this->get_all_products($skip, $top);
            
            if (is_wp_error($products_data)) {
                $this->log_error('Failed to sync all products: ' . $products_data->get_error_message());
                return false;
            }
            
            $products = !empty($products_data['Products']) ? $products_data['Products'] : array();
            $count = count($products);
            $total_products += $count;
            
            // Process this batch of products
            if ($count > 0) {
                $this->process_products($products);
            }
            
            // Move to next batch
            $skip += $top;
            
            // Continue until we get fewer products than requested
        } while ($count >= $top);
        
        $this->log_info('Synced ' . $total_products . ' products');
        
        return true;
    }

    /**
     * Sync product prices with the local database
     *
     * @return bool True on success, false on failure
     */
    public function sync_prices() {
        global $wpdb;
        
        // Get prices in batches
        $skip = 0;
        $top = 1000;
        $total_prices = 0;
        
        do {
            $prices_data = $this->get_product_prices($skip, $top);
            
            if (is_wp_error($prices_data)) {
                $this->log_error('Failed to sync prices: ' . $prices_data->get_error_message());
                return false;
            }
            
            $prices = !empty($prices_data) ? $prices_data : array();
            $count = count($prices);
            $total_prices += $count;
            
            // Process this batch of prices
            if ($count > 0) {
                $this->process_prices($prices);
            }
            
            // Move to next batch
            $skip += $top;
            
            // Continue until we get fewer prices than requested
        } while ($count >= $top);
        
        $this->log_info('Synced ' . $total_prices . ' prices');
        
        return true;
    }

    /**
     * Sync inventory data from the API
     *
     * @return bool True on success, false on failure
     */
    public function sync_inventory() {
        global $wpdb;
        
        $this->log_info('Starting inventory sync');
        
        // Check if we have a location ID
        if (empty($this->location_id)) {
            $this->log_error('Location ID is required for inventory sync');
            return false;
        }
        
        // Make sure the inventory table exists
        $inventory_table = $wpdb->prefix . 'cova_inventory';
        if ($wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") != $inventory_table) {
            $this->log_error('Inventory table does not exist');
            return false;
        }
        
        // First try to get inventory from products that have availability data
        $products_table = $wpdb->prefix . 'cova_products';
        $products_with_availability = array();
        $availability_found = false;
        
        // Get products from the database
        $products = $wpdb->get_results("SELECT product_id, data FROM $products_table WHERE is_archived = 0", ARRAY_A);
        
        if (!empty($products)) {
            foreach ($products as $product) {
                $product_data = json_decode($product['data'], true);
                
                // Check if the product has availability data
                if (!empty($product_data['Availability']) && is_array($product_data['Availability'])) {
                    foreach ($product_data['Availability'] as $availability) {
                        // Make sure it has the key fields
                        if (isset($availability['InStockQuantity']) && isset($availability['LocationId'])) {
                            // Add product ID to the availability entry for later processing
                            $availability['ProductId'] = $product['product_id'];
                            $products_with_availability[] = $availability;
                            $availability_found = true;
                        }
                    }
                }
            }
        }
        
        // If we found availability data in the products, process it
        if ($availability_found) {
            $this->log_info('Found availability data in products, processing ' . count($products_with_availability) . ' entries');
            $this->process_inventory($products_with_availability);
            return true;
        }
        
        // If we didn't find availability in products, try the supply chain API as a fallback
        $this->log_info('No availability data found in products, trying Supply Chain API');
        
        $endpoint = "/SupplyChain/v1/companies/{$this->company_id}/location/{$this->location_id}/inventory";
        $response = $this->request($endpoint);
        
        if (is_wp_error($response) || empty($response)) {
            $this->log_error('Error getting inventory data from Supply Chain API, trying DataPlatform API');
            
            // Try Cova Data Platform API as fallback
            $endpoint = "/DataPlatform/Inventory/v1/Companies({$this->company_id})/Locations({$this->location_id})/CatalogItems";
            $response = $this->request($endpoint);
            
            if (is_wp_error($response) || empty($response)) {
                // Try another format
                $endpoint = "/Inventory/v1/Companies/{$this->company_id}/Inventory/Locations/{$this->location_id}";
                $response = $this->request($endpoint);
                
                if (is_wp_error($response) || empty($response)) {
                    $this->log_error('Failed to get inventory data from all APIs');
                    return false;
                }
            }
        }
        
        // Process inventory data - check different response formats
        if (!empty($response['Items'])) {
            $this->process_inventory($response['Items']);
        } elseif (!empty($response['inventory'])) {
            $this->process_inventory($response['inventory']);
        } elseif (!empty($response['Inventory'])) {
            $this->process_inventory($response['Inventory']);
        } elseif (is_array($response) && isset($response[0])) {
            // Response is directly an array of inventory items
            $this->process_inventory($response);
        } else {
            $this->log_error('Unexpected inventory data format');
            return false;
        }
        
        $this->log_info('Inventory sync completed');
        
        return true;
    }

    /**
     * Process products data and store in database
     *
     * @param array $products Array of product data
     * @return int Number of products processed
     */
    private function process_products($products) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cova_products';
        $inventory_table = $wpdb->prefix . 'cova_inventory';
        $processed_count = 0;
        $current_time = current_time('mysql');
        
        // Ensure inventory table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") != $inventory_table) {
            $this->create_inventory_table();
        }
        
        // Check if we need to update the schema to include stock fields
        $has_stock_columns = $wpdb->get_var("SHOW COLUMNS FROM $table_name LIKE 'is_in_stock'");
        if (!$has_stock_columns) {
            $this->log_info('Adding stock columns to products table');
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN is_in_stock TINYINT(1) DEFAULT 0");
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN stock_quantity INT DEFAULT 0");
            $wpdb->query("ALTER TABLE $table_name ADD INDEX is_in_stock (is_in_stock)");
        }
        
        foreach ($products as $product) {
            // Skip if no product ID
            if (empty($product['ProductId'])) {
                $this->log_error("Missing ProductId in product data", ['data_sample' => json_encode(array_slice($product, 0, 5))]);
                continue;
            }
            
            // Extract the product data
            $product_id = isset($product['ProductId']) ? $product['ProductId'] : '';
            $name = isset($product['Name']) ? sanitize_text_field($product['Name']) : '';
            $master_product_id = isset($product['MasterProductId']) ? $product['MasterProductId'] : '';
            $category = isset($product['CategoryName']) ? sanitize_text_field($product['CategoryName']) : '';
            if (empty($category) && isset($product['Categories'][0]['Name'])) {
                $category = sanitize_text_field($product['Categories'][0]['Name']);
            }
            if (empty($category) && isset($product['ClassificationName'])) {
                $category = sanitize_text_field($product['ClassificationName']);
            }
            
            $catalog_sku = '';
            if (isset($product['Skus']) && is_array($product['Skus']) && !empty($product['Skus'])) {
                $catalog_sku = isset($product['Skus'][0]['Value']) ? sanitize_text_field($product['Skus'][0]['Value']) : '';
            } elseif (isset($product['CatalogSku'])) {
                $catalog_sku = sanitize_text_field($product['CatalogSku']);
            }
            
            $description = isset($product['LongDescription']) ? wp_kses_post($product['LongDescription']) : '';
            if (empty($description) && isset($product['Description'])) {
                $description = wp_kses_post($product['Description']);
            }
            
            $is_archived = isset($product['IsArchived']) ? (int) $product['IsArchived'] : 0;
            $created_date = isset($product['CreatedDateUtc']) ? $product['CreatedDateUtc'] : null;
            $updated_date = isset($product['UpdatedDateUtc']) ? $product['UpdatedDateUtc'] : null;
            
            // Initialize stock values with defaults
            $stock_quantity = 0;
            $is_in_stock = 0;
            
            // Process availability data - first check direct Availability array
            if (!empty($product['Availability']) && is_array($product['Availability'])) {
                $this->log_info("Processing Availability data for product $product_id", [
                    'availability_count' => count($product['Availability'])
                ]);
                
                foreach ($product['Availability'] as $availability) {
                    if (isset($availability['InStockQuantity'])) {
                        // Get stock quantity values
                        $quantity = (int)$availability['InStockQuantity'];
                        $available = isset($availability['OnOrderQuantity']) ? $quantity - (int)$availability['OnOrderQuantity'] : $quantity;
                        $reserved = isset($availability['OnOrderQuantity']) ? (int)$availability['OnOrderQuantity'] : 0;
                        
                        // Update total stock quantity for this product
                        $stock_quantity += $quantity;
                        
                        // If any location has stock, product is in stock
                        if ($quantity > 0) {
                            $is_in_stock = 1;
                        }
                        
                        // Store inventory data if we have location information
                        if (isset($availability['LocationId'])) {
                            $location_id = $availability['LocationId'];
                            $room_id = isset($availability['RoomId']) ? $availability['RoomId'] : 0;
                            $inventory_id = md5($product_id . '_' . $location_id . '_' . $room_id);
                            
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
                                        'data' => json_encode($availability),
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
                                        'data' => json_encode($availability),
                                        'last_sync' => $current_time
                                    )
                                );
                            }
                        }
                    }
                }
            } else {
                // Availability array is empty - log that fact for debugging
                $this->log_info("No Availability data for product $product_id - checking alternative fields");
                
                // Check for stock data in the Pricing section
                if (!empty($product['Pricing']) && is_array($product['Pricing'])) {
                    foreach ($product['Pricing'] as $pricing) {
                        if (isset($pricing['InStockQuantity'])) {
                            $stock_quantity += (int)$pricing['InStockQuantity'];
                            if ($pricing['InStockQuantity'] > 0) {
                                $is_in_stock = 1;
                            }
                            $this->log_info("Found stock quantity in Pricing data: " . $pricing['InStockQuantity']);
                        }
                    }
                }
                
                // Check for QuantityOnHand directly on the product
                if (isset($product['QuantityOnHand'])) {
                    $stock_quantity = (int)$product['QuantityOnHand'];
                    $is_in_stock = ($stock_quantity > 0) ? 1 : 0;
                    $this->log_info("Found QuantityOnHand: $stock_quantity for product $product_id");
                }
            }
            
            // Extract price information
            $price = 0;
            if (!empty($product['Pricing']) && is_array($product['Pricing'])) {
                foreach ($product['Pricing'] as $pricing) {
                    if (isset($pricing['Price']) && $pricing['Price'] > 0) {
                        $price = (float)$pricing['Price'];
                        // Store price in the prices table
                        $this->store_product_price($product_id, $price, $pricing);
                        break; // Just use the first valid price
                    }
                }
            }
            
            // Image URL
            $image_url = '';
            if (!empty($product['Assets']) && is_array($product['Assets'])) {
                foreach ($product['Assets'] as $asset) {
                    if (isset($asset['Type']) && $asset['Type'] === 'Image' && !empty($asset['Url'])) {
                        $image_url = $asset['Url'];
                        break;
                    }
                }
            }
            
            // Convert dates to MySQL format
            if ($created_date) {
                $created_date = date('Y-m-d H:i:s', strtotime($created_date));
            }
            
            if ($updated_date) {
                $updated_date = date('Y-m-d H:i:s', strtotime($updated_date));
            }
            
            // Prepare the data
            $data = array(
                'product_id' => $product_id,
                'name' => $name,
                'master_product_id' => $master_product_id,
                'category' => $category,
                'catalog_sku' => $catalog_sku,
                'description' => $description,
                'is_archived' => $is_archived,
                'is_in_stock' => $is_in_stock,
                'stock_quantity' => $stock_quantity,
                'created_date' => $created_date,
                'updated_date' => $updated_date,
                'data' => json_encode($product),
                'last_sync' => current_time('mysql')
            );
            
            // Check if the product already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE product_id = %s",
                $product_id
            ));
            
            if ($exists) {
                // Update existing product
                $result = $wpdb->update(
                    $table_name,
                    $data,
                    array('product_id' => $product_id)
                );
                
                if (false === $result) {
                    $this->log_error("Failed to update product: {$product_id}. Error: " . $wpdb->last_error);
                } else {
                    $processed_count++;
                }
            } else {
                // Insert new product
                $result = $wpdb->insert($table_name, $data);
                
                if (false === $result) {
                    $this->log_error("Failed to insert product: {$product_id}. Error: " . $wpdb->last_error);
                } else {
                    $processed_count++;
                }
            }
        }
        
        $this->log_info("Processed {$processed_count} products");
        
        return $processed_count;
    }
    
    /**
     * Store product price in the prices table
     *
     * @param string $product_id The product ID
     * @param float $price The price value
     * @param array $pricing_data The full pricing data from the API
     * @return bool True on success, false on failure
     */
    private function store_product_price($product_id, $price, $pricing_data) {
        global $wpdb;
        
        $price_table = $wpdb->prefix . 'cova_prices';
        $current_time = current_time('mysql');
        
        // Generate a unique price ID if one isn't provided
        $price_id = isset($pricing_data['Id']) ? $pricing_data['Id'] : md5($product_id . '_' . $price . '_' . time());
        
        // Extract entity ID (location)
        $entity_id = isset($pricing_data['LocationId']) ? $pricing_data['LocationId'] : $this->location_id;
        
        // Prepare data for insertion/update
        $price_data = array(
            'price_id' => $price_id,
            'entity_id' => $entity_id,
            'catalog_item_id' => $product_id,
            'regular_price' => $price,
            'at_tier_price' => isset($pricing_data['SalePrice']) ? (float)$pricing_data['SalePrice'] : $price,
            'tier_name' => isset($pricing_data['TierName']) ? sanitize_text_field($pricing_data['TierName']) : '',
            'data' => json_encode($pricing_data),
            'last_sync' => $current_time
        );
        
        // Check if price record exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $price_table WHERE price_id = %s",
            $price_id
        ));
        
        if ($exists) {
            // Update existing record
            $result = $wpdb->update(
                $price_table,
                $price_data,
                array('price_id' => $price_id)
            );
        } else {
            // Insert new record
            $result = $wpdb->insert($price_table, $price_data);
        }
        
        if (false === $result) {
            $this->log_error("Failed to store price for product: {$product_id}. Error: " . $wpdb->last_error);
            return false;
        }
        
        return true;
    }

    /**
     * Process prices data and store in database
     *
     * @param array $prices Array of price data
     */
    private function process_prices($prices) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cova_prices';
        $processed_count = 0;
        
        foreach ($prices as $price) {
            // Skip if no price ID
            if (empty($price['Id'])) {
                continue;
            }
            
            // Extract the price data
            $price_id = isset($price['Id']) ? $price['Id'] : '';
            $entity_id = isset($price['EntityId']) ? $price['EntityId'] : '';
            $catalog_item_id = isset($price['CatalogItemId']) ? $price['CatalogItemId'] : '';
            $regular_price = isset($price['RegularPrice']) ? floatval($price['RegularPrice']) : 0;
            $at_tier_price = isset($price['AtTierPrice']) ? floatval($price['AtTierPrice']) : 0;
            
            // Pricing Tier
            $tier_name = '';
            if (!empty($price['PricingTier']) && isset($price['PricingTier']['TierName'])) {
                $tier_name = sanitize_text_field($price['PricingTier']['TierName']);
            }
            
            // Prepare the data
            $data = array(
                'price_id' => $price_id,
                'entity_id' => $entity_id,
                'catalog_item_id' => $catalog_item_id,
                'regular_price' => $regular_price,
                'at_tier_price' => $at_tier_price,
                'tier_name' => $tier_name,
                'data' => json_encode($price),
                'last_sync' => current_time('mysql')
            );
            
            // Check if the price already exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE price_id = %s",
                $price_id
            ));
            
            if ($exists) {
                // Update existing price
                $result = $wpdb->update(
                    $table_name,
                    $data,
                    array('price_id' => $price_id)
                );
                
                if (false === $result) {
                    $this->log_error("Failed to update price: {$price_id}. Error: " . $wpdb->last_error);
                } else {
                    $processed_count++;
                }
            } else {
                // Insert new price
                $result = $wpdb->insert($table_name, $data);
                
                if (false === $result) {
                    $this->log_error("Failed to insert price: {$price_id}. Error: " . $wpdb->last_error);
                } else {
                    $processed_count++;
                }
            }
        }
        
        $this->log_info("Processed {$processed_count} prices");
        
        return $processed_count;
    }

    /**
     * Process inventory data and save to database
     *
     * @param array $inventory_data Inventory data from API
     * @return bool True on success, false on failure
     */
    private function process_inventory($inventory_data) {
        global $wpdb;
        
        $inventory_table = $wpdb->prefix . 'cova_inventory';
        $current_time = current_time('mysql');
        $processed = 0;
        
        foreach ($inventory_data as $item) {
            // Check various possible ID fields
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
            
            // Skip if no product ID found
            if (empty($product_id)) {
                continue;
            }
            
            $inventory_id = isset($item['Id']) ? $item['Id'] : md5($product_id . '_' . $this->location_id);
            
            // Check various possible quantity fields
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
            $location_id = isset($item['LocationId']) ? $item['LocationId'] : $this->location_id;
            
            // Determine available and reserved quantities
            $available = isset($item['AvailableQuantity']) ? intval($item['AvailableQuantity']) : $quantity;
            $reserved = isset($item['ReservedQuantity']) ? intval($item['ReservedQuantity']) : 0;
            if (isset($item['OnOrderQuantity'])) {
                $reserved = intval($item['OnOrderQuantity']);
            }
            
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
            
            $processed++;
        }
        
        $this->log_info("Processed $processed inventory records");
        
        return true;
    }

    /**
     * Encrypt sensitive data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data
     */
    public function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        // Use base64 encoding for simple encryption
        return base64_encode($data);
    }

    /**
     * Decrypt sensitive data
     *
     * @param string $data Data to decrypt
     * @return string Decrypted data
     */
    public function decrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        // Use base64 decoding for simple decryption
        return base64_decode($data);
    }

    /**
     * Log error message
     *
     * @param string $message Error message
     * @param array $context Additional context data for the log
     */
    private function log_error($message, $context = array()) {
        global $wpdb;
        
        // Build a more detailed log message with context
        $detailed_message = $message;
        
        if (!empty($context)) {
            $context_str = json_encode($context, JSON_PRETTY_PRINT);
            $detailed_message .= " | Context: " . $context_str;
        }
        
        // Add timestamp and location
        $log_message = '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $detailed_message;
        
        // Log to error log
        error_log('Cova Integration Error: ' . $detailed_message);
        
        // Check if error logs table exists
        $table_name = $wpdb->prefix . 'cova_error_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            // Table doesn't exist, create it
            $this->create_error_logs_table();
        }
        
        // Log to database with enhanced context
        $wpdb->insert(
            $table_name,
            array(
            'message' => $message,
                'context' => !empty($context) ? json_encode($context) : null,
                'type' => 'error',
                'created_at' => current_time('mysql')
            )
        );
    }
    
    /**
     * Create error logs table
     */
    private function create_error_logs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cova_error_logs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message text NOT NULL,
            context longtext,
            type varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Log info message
     *
     * @param string $message Info message
     * @param array $context Additional context data for the log
     */
    private function log_info($message, $context = array()) {
        global $wpdb;
        
        // Build a more detailed log message with context
        $detailed_message = $message;
        
        if (!empty($context)) {
            $context_str = json_encode($context, JSON_PRETTY_PRINT);
            $detailed_message .= " | Context: " . $context_str;
        }
        
        // Add timestamp and location
        $log_message = '[' . date('Y-m-d H:i:s') . '] INFO: ' . $detailed_message;
        
        // Log to error log
        error_log('Cova Integration Info: ' . $detailed_message);
        
        // Log to database
        $table_name = $wpdb->prefix . 'cova_error_logs';
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
    
    /**
     * Get products from the local database
     * 
     * @param bool $active_only Whether to return only active products
     * @return array Array of products
     */
    public function getProducts($active_only = true) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'cova_products';
        
        $query = "SELECT * FROM $table_name";
        
        if ($active_only) {
            $query .= " WHERE is_archived = 0";
        }
        
        $query .= " ORDER BY name ASC";
        
        $products = $wpdb->get_results($query, ARRAY_A);
        
        // If no products found in database, try to sync
        if (empty($products)) {
            $this->log_info('No products found in database, attempting to sync');
            $this->sync_products();
            
            // Try again
            $products = $wpdb->get_results($query, ARRAY_A);
        }
        
        // Process each product to extract data from JSON
        $processed_products = array();
        foreach ($products as $product) {
            $product_data = json_decode($product['data'], true);
            
            // Ensure product data exists
            if (!empty($product_data)) {
                // Ensure we have a proper product structure with all required fields
                if (empty($product_data['Id'])) {
                    $product_data['Id'] = $product['product_id'];
                }
                
                if (empty($product_data['Name'])) {
                    $product_data['Name'] = $product['name'];
                }
                
                if (empty($product_data['Category'])) {
                    $product_data['Category'] = $product['category'];
                }
                
                $processed_products[] = $product_data;
            }
        }
        
        return $processed_products;
    }
    
    /**
     * Get inventory data from the local database
     * 
     * @return array Array of inventory data
     */
    public function getInventory() {
        global $wpdb;
        
        $inventory_table = $wpdb->prefix . 'cova_inventory';
        
        // Check if the table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$inventory_table'") != $inventory_table) {
            $this->log_error('Inventory table does not exist');
            return array();
        }
        
        $query = "SELECT * FROM $inventory_table ORDER BY last_sync DESC";
        
        $inventory = $wpdb->get_results($query, ARRAY_A);
        
        // Process each inventory item to extract data from JSON
        $processed_inventory = array();
        foreach ($inventory as $item) {
            $data = json_decode($item['data'], true);
            
            // Ensure we have a proper inventory structure
            if (empty($data)) {
                $data = array(
                    'ProductId' => $item['product_id'],
                    'LocationId' => $item['location_id'],
                    'QuantityOnHand' => $item['quantity'],
                    'AvailableQuantity' => $item['available_quantity'],
                    'ReservedQuantity' => $item['reserved_quantity']
                );
            }
            
            $processed_inventory[] = $data;
        }
        
        return $processed_inventory;
    }
    
    /**
     * Get prices from the local database
     * 
     * @return array Array of price data
     */
    public function getPrices() {
        global $wpdb;
        
        $price_table = $wpdb->prefix . 'cova_prices';
        
        $query = "SELECT * FROM $price_table ORDER BY last_sync DESC";
        
        $prices = $wpdb->get_results($query, ARRAY_A);
        
        // Process each price item
        $processed_prices = array();
        foreach ($prices as $price) {
            // Convert to a format the WooCommerce class expects
            $processed_price = array(
                'ProductId' => $price['catalog_item_id'],
                'Price' => $price['regular_price']
            );
            
            $processed_prices[] = $processed_price;
        }
        
        return $processed_prices;
    }

    /**
     * Directly get detailed product data and save to database
     * 
     * @return bool True on success, false on failure
     */
    public function force_detailed_product_sync() {
        global $wpdb;
        $this->log_info('Starting forced detailed product sync');
        unset($GLOBALS['cova_debug_logged']);
        $endpoint = "/dataplatform/v1/companies/{$this->company_id}/DetailedProductData";
        $skip = 0;
        $top = 500;
        $total_products = 0;
        do {
            $args = array(
                'body' => array(
                    'LocationId' => (int) $this->location_id,
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
                    'Skip' => $skip,
                    'Top' => $top
                )
            );
            $this->log_info("Making DetailedProductData request with parameters: " . json_encode($args['body']));
            $data = $this->request($endpoint, 'POST', $args);
            if (is_wp_error($data)) {
                $this->log_error('Failed to get detailed product data: ' . $data->get_error_message(), [
                    'endpoint' => $endpoint,
                    'args' => $args,
                    'error_data' => $data->get_error_data()
                ]);
                return false;
            }
            if (empty($data['Products'])) {
                break;
            }
            $count = count($data['Products']);
            $total_products += $count;
            $this->log_info('Received ' . $count . ' products from DetailedProductData API (skip=' . $skip . ')');
            $this->process_products($data['Products']);
            $skip += $top;
        } while ($count >= $top);
        $this->log_info('Forced detailed product sync completed. Total products synced: ' . $total_products);
        return true;
    }

    /**
     * Create inventory table if it doesn't exist
     */
    private function create_inventory_table() {
        global $wpdb;
        
        $inventory_table = $wpdb->prefix . 'cova_inventory';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE $inventory_table (
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        $this->log_info("Created inventory table: $inventory_table");
    }

    /**
     * Create database tables for plugin
     */
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Create products table
        $products_table = $wpdb->prefix . 'cova_products';
        $sql = "CREATE TABLE $products_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            product_id varchar(255) NOT NULL,
            name varchar(255) NOT NULL,
            master_product_id varchar(255),
            category varchar(255),
            catalog_sku varchar(255),
            description longtext,
            is_archived tinyint(1) DEFAULT 0,
            is_in_stock tinyint(1) DEFAULT 0,
            stock_quantity int DEFAULT 0,
            created_date datetime,
            updated_date datetime,
            data longtext,
            last_sync datetime,
            PRIMARY KEY  (id),
            UNIQUE KEY product_id (product_id),
            KEY category (category),
            KEY catalog_sku (catalog_sku),
            KEY is_archived (is_archived),
            KEY is_in_stock (is_in_stock)
        ) $charset_collate;";
        
        // Create prices table
        $prices_table = $wpdb->prefix . 'cova_prices';
        $sql .= "CREATE TABLE $prices_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            price_id varchar(255) NOT NULL,
            entity_id varchar(255),
            catalog_item_id varchar(255) NOT NULL,
            regular_price decimal(15,2) DEFAULT 0,
            at_tier_price decimal(15,2) DEFAULT 0,
            tier_name varchar(255),
            data longtext,
            last_sync datetime,
            PRIMARY KEY  (id),
            UNIQUE KEY price_id (price_id),
            KEY catalog_item_id (catalog_item_id)
        ) $charset_collate;";
        
        // Create inventory table
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
        
        // Create error logs table
        $error_logs_table = $wpdb->prefix . 'cova_error_logs';
        $sql .= "CREATE TABLE $error_logs_table (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            message text NOT NULL,
            context longtext,
            type varchar(20) NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY type (type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Get a single product by ProductId from COVA API
     *
     * @param string $product_id The COVA ProductId
     * @return array|null Product data array or null if not found
     */
    public function getProductById($product_id) {
        if (empty($product_id)) {
            $this->log_error('getProductById called with empty product_id');
            return null;
        }
        $endpoint = "/dataplatform/v1/companies/{$this->company_id}/DetailedProductData/ByProductIdList";
        $args = array(
            'body' => array(
                'LocationId' => (int) $this->location_id,
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
                'ProductIds' => array($product_id)
            )
        );
        $this->log_info("Fetching product by ID from COVA: $product_id");
        $data = $this->request($endpoint, 'POST', $args);
        if (is_wp_error($data) || empty($data['Products']) || !is_array($data['Products'])) {
            $this->log_error('getProductById: No product found or API error for product_id: ' . $product_id);
            return null;
        }
        return $data['Products'][0];
    }
} 