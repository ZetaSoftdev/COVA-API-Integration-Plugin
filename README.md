# Cova Integration for WordPress

This plugin integrates WordPress with the Cova API for cannabis retail, enabling the display of product information, inventory, and pricing from your Cova retail system directly on your WordPress website.

## Features

- Real-time product, inventory, and pricing data from your Cova retail system
- Admin dashboard to manage API credentials and view data status
- Shortcodes to display products and inventory on the frontend
- WooCommerce integration to sync Cova products as WooCommerce products
- Real-time inventory sync with Cova to prevent overselling
- Age verification modal for adult content
- Automatic data syncing with configurable intervals
- Error logging and monitoring
- Secure API credential storage

## Installation

1. Upload the entire `cova-integration` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to "Cova Integration" in the WordPress admin menu
4. Enter your Cova API credentials in the Settings tab

## Configuration

### API Credentials

You'll need the following credentials to connect to the Cova API:

- Client ID
- Client Secret
- Username
- Password
- Company ID

These should be provided by your Cova representative.

### Shortcodes

#### Display Products

```
[cova_products category="flower" limit="10" columns="3"]
```

Parameters:
- `category`: (optional) Filter products by category
- `limit`: (optional) Number of products to display (default: 10)
- `columns`: (optional) Number of columns in the grid (default: 3)

#### Display Inventory for a Product

```
[cova_inventory product_id="123"]
```

Parameters:
- `product_id`: (required) The ID of the product to display inventory for

## WooCommerce Integration

This plugin can synchronize Cova products with WooCommerce. To use this feature:

1. Install and activate WooCommerce
2. Go to Cova Integration > WooCommerce Sync
3. Configure your sync settings
4. Map Cova product categories to WooCommerce categories
5. Click "Sync Products Now" to manually sync products, or wait for the scheduled sync

### WooCommerce Sync Features

- Create new WooCommerce products from Cova products
- Update existing WooCommerce products with changes from Cova
- Sync inventory levels from Cova to WooCommerce
- Map Cova product categories to WooCommerce categories
- Import product images to the WordPress media library
- Option to auto-publish new products or create them as drafts
- Real-time stock updates

## Real-Time Inventory Sync

The plugin supports real-time inventory updates through Cova's webhook system:

### Features

- **Monitors SKU stock levels directly from Cova** - Get immediate updates when inventory changes
- **Automatic status updates** - If a product sells out in Cova (stock reaches 0), it immediately updates the stock status in WooCommerce to "Out of Stock"
- **Prevents overselling** - Ensures there's no mismatch in stock visibility between your physical and online store

### Configuration

To set up real-time inventory sync:

1. Go to Cova Integration > Settings > Webhook Settings
2. Enable the "Real-Time Inventory Updates" option
3. Set a secure Webhook Secret key (or generate one with the provided button)
4. Copy the webhook URL provided in the settings
5. Contact Cova support to configure webhooks for your account, providing the webhook URL and secret

### How It Works

When inventory changes in your Cova retail system:

1. Cova sends a webhook to your WordPress site with the updated inventory data
2. The plugin verifies the webhook's authenticity using the secret key
3. If verified, the plugin updates the inventory in both the Cova Integration database and WooCommerce
4. Products with zero inventory are automatically marked as "Out of Stock" in WooCommerce

This ensures your online inventory always reflects your actual in-store availability, reducing the risk of selling products that are no longer in stock.

## Data Synchronization

By default, the plugin syncs data from the Cova API hourly. You can force a manual sync from the Dashboard page.

## Age Verification

The plugin includes an age verification modal that will display to users on their first visit. This behavior can be configured or disabled in the Settings.

## Customization

### CSS

You can customize the appearance of the product grid and age verification modal by adding custom CSS to your theme.

### Templates

To override the default templates, copy the template files from the plugin's templates directory to your theme's directory in a folder named `cova-integration`.

## Support

For support, please contact your plugin provider or Cova representative.

## License

This plugin is proprietary software. Unauthorized distribution is prohibited. 