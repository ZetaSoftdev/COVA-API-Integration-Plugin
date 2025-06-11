<?php
/**
 * Repair SKUs for Cova WooCommerce Products
 * 
 * This script fixes missing or incorrect SKUs in WooCommerce products imported from Cova.
 * It will identify all WooCommerce products with Cova product IDs and update their SKUs
 * based on the information in the Cova database tables.
 * 
 * Usage: Run this script directly via the browser or with PHP CLI.
 */

// Load WordPress
define('WP_USE_THEMES', false);
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Security check - only allow admin users to run this script
if (!current_user_can('manage_options')) {
    die('You do not have sufficient permissions to access this page.');
}

// Set execution time to unlimited - this might take a while for large inventories
set_time_limit(0);

// Start tracking stats
$stats = array(
    'total' => 0,
    'updated' => 0,
    'already_ok' => 0,
    'missing_cova_data' => 0,
    'errors' => 0,
    'numeric_skus_fixed' => 0,
);

echo '<h1>Cova WooCommerce SKU Repair Tool</h1>';
echo '<p>Scanning and repairing missing and incorrect SKUs...</p>';

// Get all WooCommerce products that have a Cova product ID
$args = array(
    'post_type' => 'product',
    'post_status' => array('publish', 'draft'),
    'meta_query' => array(
        array(
            'key' => '_cova_product_id',
            'compare' => 'EXISTS',
        ),
    ),
    'posts_per_page' => -1,
);

$products = get_posts($args);
$stats['total'] = count($products);

echo '<p>Found ' . $stats['total'] . ' WooCommerce products with Cova IDs.</p>';

// Process each product
foreach ($products as $product) {
    $wc_product_id = $product->ID;
    $wc_product = wc_get_product($wc_product_id);
    
    if (!$wc_product) {
        $stats['errors']++;
        echo '<p style="color:red;">Error: Unable to load WooCommerce product #' . $wc_product_id . '</p>';
        continue;
    }
    
    // Get the current SKU
    $current_sku = $wc_product->get_sku();
    
    // Get the Cova product ID
    $cova_id = get_post_meta($wc_product_id, '_cova_product_id', true);
    
    echo '<p>Processing: ' . $wc_product->get_name() . ' (WC ID: ' . $wc_product_id . ', Cova ID: ' . $cova_id . ', Current SKU: ' . ($current_sku ? $current_sku : 'Empty') . ')</p>';
    
    // Check if the current SKU is numeric and likely an internal ID rather than a proper SKU
    $needs_sku_fix = empty($current_sku) || 
                    strpos($current_sku, 'COVA-') === 0 || 
                    (is_numeric($current_sku) && strlen($current_sku) >= 6 && substr($current_sku, 0, 3) == '100');
    
    // If the SKU looks valid and isn't numeric, consider it already set correctly
    if (!$needs_sku_fix) {
        $stats['already_ok']++;
        echo '<p style="color:green;">✓ SKU already set properly: ' . $current_sku . '</p>';
        continue;
    }
    
    // Lookup the product in the Cova products table
    global $wpdb;
    $table_name = $wpdb->prefix . 'cova_products';
    $cova_product = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE product_id = %s",
        $cova_id
    ), ARRAY_A);
    
    if (!$cova_product) {
        $stats['missing_cova_data']++;
        echo '<p style="color:orange;">⚠ Cova product data not found for ID: ' . $cova_id . '</p>';
        continue;
    }
    
    // Extract original data and decode JSON
    $original_data = $cova_product['data'];
    $product_data = json_decode($original_data, true);
    
    // Dump the first part of the data for debugging
    echo '<div style="background-color: #f8f8f8; padding: 10px; margin-bottom: 10px; font-family: monospace; max-height: 100px; overflow: auto;">';
    echo '<p><strong>Data Sample:</strong> ';
    echo substr(print_r($product_data, true), 0, 500) . '...</p>';
    echo '</div>';
    
    // Initialize SKU variable
    $sku = '';
    
    // Try to extract SKU from various fields
    if (isset($product_data['SKU']) && !empty($product_data['SKU'])) {
        $sku = $product_data['SKU'];
        echo '<p>Found SKU in SKU field: ' . $sku . '</p>';
    } elseif (isset($product_data['Sku']) && !empty($product_data['Sku'])) {
        $sku = $product_data['Sku'];
        echo '<p>Found SKU in Sku field: ' . $sku . '</p>';
    } elseif (isset($product_data['sku']) && !empty($product_data['sku'])) {
        $sku = $product_data['sku'];
        echo '<p>Found SKU in sku field: ' . $sku . '</p>';
    } elseif (isset($product_data['CatalogSku']) && !empty($product_data['CatalogSku'])) {
        $sku = $product_data['CatalogSku'];
        echo '<p>Found SKU in CatalogSku field: ' . $sku . '</p>';
    } elseif (isset($product_data['catalogSku']) && !empty($product_data['catalogSku'])) {
        $sku = $product_data['catalogSku'];
        echo '<p>Found SKU in catalogSku field: ' . $sku . '</p>';
    } elseif (isset($product_data['ItemLookupCode']) && !empty($product_data['ItemLookupCode'])) {
        $sku = $product_data['ItemLookupCode'];
        echo '<p>Found SKU in ItemLookupCode field: ' . $sku . '</p>';
    } elseif (isset($product_data['Skus']) && is_array($product_data['Skus']) && !empty($product_data['Skus'])) {
        // Look for the first SKU in the Skus array
        foreach ($product_data['Skus'] as $skuItem) {
            if (isset($skuItem['Value']) && !empty($skuItem['Value'])) {
                $sku = $skuItem['Value'];
                echo '<p>Found SKU in Skus array: ' . $sku . '</p>';
                break;
            }
        }
    }
    
    // If still no SKU, try regex patterns on raw data
    if (empty($sku) && !empty($original_data)) {
        echo '<p>Trying regex patterns on raw data</p>';
        if (preg_match('/"SKU"\s*:\s*"([^"]+)"/', $original_data, $matches)) {
            $sku = $matches[1];
            echo '<p>Found SKU via regex (SKU): ' . $sku . '</p>';
        } elseif (preg_match('/"Sku"\s*:\s*"([^"]+)"/', $original_data, $matches)) {
            $sku = $matches[1];
            echo '<p>Found SKU via regex (Sku): ' . $sku . '</p>';
        } elseif (preg_match('/"sku"\s*:\s*"([^"]+)"/', $original_data, $matches)) {
            $sku = $matches[1];
            echo '<p>Found SKU via regex (sku): ' . $sku . '</p>';
        } elseif (preg_match('/"CatalogSku"\s*:\s*"([^"]+)"/', $original_data, $matches)) {
            $sku = $matches[1];
            echo '<p>Found SKU via regex (CatalogSku): ' . $sku . '</p>';
        } elseif (preg_match('/"ItemLookupCode"\s*:\s*"([^"]+)"/', $original_data, $matches)) {
            $sku = $matches[1];
            echo '<p>Found SKU via regex (ItemLookupCode): ' . $sku . '</p>';
        }
    }
    
    // Search for fields with a value that looks like a SKU (alphanumeric mix)
    if (empty($sku) || is_numeric($sku)) {
        echo '<p>Searching for SKU-like fields in the data...</p>';
        foreach ($product_data as $key => $value) {
            if (is_string($value) && !empty($value) && !is_numeric($value) && preg_match('/^[A-Z0-9]{5,12}$/i', $value)) {
                echo '<p>Found potential SKU in field "' . $key . '": ' . $value . '</p>';
                if (empty($sku) || is_numeric($sku)) {
                    $sku = $value;
                    echo '<p>Using this value as SKU</p>';
                }
            }
        }
    }
    
    // If current SKU is numeric and we found a non-numeric one, mark it for special stats
    $is_numeric_sku_fix = is_numeric($current_sku) && !empty($sku) && !is_numeric($sku);
    
    // Check if we found a valid SKU
    if (!empty($sku) && (!is_numeric($sku) || strlen($sku) < 6)) {
        // Update the WooCommerce product SKU
        $wc_product->set_sku($sku);
        $wc_product->save();
        
        // Also update the _cova_original_sku meta
        update_post_meta($wc_product_id, '_cova_original_sku', $sku);
        
        if ($is_numeric_sku_fix) {
            $stats['numeric_skus_fixed']++;
            echo '<p style="color:blue; font-weight: bold;">✓ Fixed numeric SKU: ' . $current_sku . ' → ' . $sku . '</p>';
        } else {
            $stats['updated']++;
            echo '<p style="color:blue;">✓ Updated SKU: ' . $sku . '</p>';
        }
    } else {
        // If no valid SKU found, set fallback SKU using Cova ID
        $fallback_sku = 'COVA-' . $cova_id;
        
        // Only update if current SKU is empty or different from fallback or is a numeric ID
        if (empty($current_sku) || $current_sku !== $fallback_sku || is_numeric($current_sku)) {
            $wc_product->set_sku($fallback_sku);
            $wc_product->save();
            
            $stats['updated']++;
            echo '<p style="color:orange;">⚠ No valid SKU found, set fallback: ' . $fallback_sku . '</p>';
        } else {
            $stats['already_ok']++;
            echo '<p style="color:green;">✓ Fallback SKU already set: ' . $current_sku . '</p>';
        }
    }
    
    echo '<hr>';
}

// Display stats
echo '<h2>Results</h2>';
echo '<ul>';
echo '<li>Total products processed: ' . $stats['total'] . '</li>';
echo '<li>Products updated with proper SKUs: ' . $stats['updated'] . '</li>';
echo '<li>Numeric SKUs fixed: ' . $stats['numeric_skus_fixed'] . '</li>';
echo '<li>Products already had correct SKUs: ' . $stats['already_ok'] . '</li>';
echo '<li>Products missing Cova data: ' . $stats['missing_cova_data'] . '</li>';
echo '<li>Errors: ' . $stats['errors'] . '</li>';
echo '</ul>';

echo '<p>SKU repair process completed.</p>';
echo '<p><a href="' . admin_url('admin.php?page=cova-integration-products') . '">Return to Cova Products</a></p>'; 