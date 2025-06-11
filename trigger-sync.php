<?php
/**
 * Script to trigger a forced sync with the Cova API
 * This is a standalone script that loads WordPress and then executes the API sync
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once('../wp-load.php');

echo "Starting Cova API sync...\n";

// Check if the API client class exists
if (!class_exists('Cova_API_Client')) {
    echo "Error: Cova_API_Client class not found. Please ensure the plugin is activated.\n";
    exit;
}

// Create an instance of the API client
$api_client = new Cova_API_Client();

// Log the current time
echo "Sync started at " . date('Y-m-d H:i:s') . "\n";

// Execute the force_detailed_product_sync method
$result = $api_client->force_detailed_product_sync();

// Output the result
if ($result) {
    echo "Sync completed successfully!\n";
} else {
    echo "Sync failed. Check the error logs for details.\n";
}

// Query the database to see the results
global $wpdb;

// Check product count
$products_table = $wpdb->prefix . 'cova_products';
$product_count = $wpdb->get_var("SELECT COUNT(*) FROM $products_table");
echo "Total products: $product_count\n";

// Check for products with stock
$in_stock_count = $wpdb->get_var("SELECT COUNT(*) FROM $products_table WHERE is_in_stock = 1");
echo "Products in stock: $in_stock_count\n";

// Check stock quantities
$stock_query = $wpdb->get_results("SELECT name, stock_quantity FROM $products_table ORDER BY stock_quantity DESC LIMIT 5", ARRAY_A);
echo "Top 5 products by stock quantity:\n";
foreach ($stock_query as $product) {
    echo "- " . $product['name'] . ": " . $product['stock_quantity'] . "\n";
}

// Check prices
$prices_table = $wpdb->prefix . 'cova_prices';
$price_count = $wpdb->get_var("SELECT COUNT(*) FROM $prices_table");
echo "Total price records: $price_count\n";

$price_query = $wpdb->get_results("
    SELECT p.name, pr.regular_price 
    FROM $prices_table pr
    JOIN $products_table p ON pr.catalog_item_id = p.product_id
    ORDER BY pr.regular_price DESC
    LIMIT 5
", ARRAY_A);

echo "Top 5 products by price:\n";
foreach ($price_query as $product) {
    echo "- " . $product['name'] . ": $" . $product['regular_price'] . "\n";
}

echo "Done.\n"; 