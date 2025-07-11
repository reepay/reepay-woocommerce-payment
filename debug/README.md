# Reepay Analytics Debug Tools

This folder contains debug tools to help diagnose and fix issues with WooCommerce Analytics and Reepay orders.

## 📁 Files Overview

### Core Debug Files

- **`index.php`** - Main loader and overview page for all debug tools
- **`enable-debug.php`** - Enables debug features and SQL query logging
- **`sql-debug.php`** - SQL query analysis and database debugging
- **`analytics-debug.php`** - WooCommerce Analytics specific debugging
- **`test-analytics-fix.php`** - Test the analytics fix implementation

## 🚀 Getting Started

### 1. Enable Debug Mode
Add these lines to your `wp-config.php` file:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('SAVEQUERIES', true);
```

### 2. Access Debug Tools
Go to **WooCommerce > 🔧 Debug Tools** in your WordPress admin to see the overview page.

### 3. Available Tools

#### 📊 Debug Dashboard (`reepay-debug-dashboard`)
- Real-time SQL query monitoring
- Debug status overview
- Quick actions and navigation
- Recent analytics activity

#### 🔍 SQL Debug (`debug-reepay-sql`)
- Database table analysis
- SQL query capture and analysis
- Order counts by status
- Manual analytics sync testing

#### 📈 Analytics Debug (`debug-woocommerce-analytics`)
- WooCommerce Analytics status check
- Hook and callback analysis
- Data comparison between tables
- Sync method testing

#### 🧪 Test Analytics Fix (`test-reepay-analytics`)
- Test the implemented analytics fix
- Force sync individual orders
- Performance comparison
- SQL query debugging

## 🔧 Debug Features

### SQL Query Logging
The debug tools automatically capture SQL queries related to:
- WooCommerce orders (`shop_order`)
- Analytics tables (`wc_order_stats`)
- Order metadata (`postmeta`)
- Payment methods

### Real-time Monitoring
The Debug Dashboard provides real-time monitoring of:
- SQL queries as they happen
- Order status changes
- Analytics sync operations

### Force Sync Options
Multiple methods to force analytics sync:
- Direct DataStore sync
- WooCommerce update hooks
- Scheduled imports
- Cache clearing

## 📋 Common Issues & Solutions

### Issue: Orders not appearing in Analytics
**Diagnosis:**
1. Check if `wc_order_stats` table exists
2. Verify order status is not excluded
3. Check if `date_paid` is set
4. Verify hooks are properly triggered

**Solutions:**
1. Use "Force Sync" buttons in debug tools
2. Clear analytics cache
3. Re-import analytics data

### Issue: SQL queries not captured
**Diagnosis:**
- Check if `SAVEQUERIES` is enabled
- Verify `WP_DEBUG` is true

**Solution:**
Add debug constants to `wp-config.php`

### Issue: Analytics hooks not working
**Diagnosis:**
1. Check hook registration in Analytics Debug
2. Verify WooCommerce Analytics is enabled
3. Check for plugin conflicts

**Solution:**
Use the Analytics Sync class to manually trigger hooks

## 🛠️ Technical Details

### Database Tables Monitored
- `wp_posts` (orders)
- `wp_postmeta` (order metadata)
- `wp_wc_order_stats` (analytics data)
- `wp_wc_order_product_lookup`
- `wp_wc_customer_lookup`

### Key Hooks Monitored
- `woocommerce_update_order`
- `woocommerce_order_status_changed`
- `woocommerce_payment_complete`
- `woocommerce_analytics_update_order_stats`

### Classes Used
- `\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore`
- `\Automattic\WooCommerce\Internal\Admin\Schedulers\OrdersScheduler`
- `\Automattic\WooCommerce\Admin\API\Reports\Cache`

## 🔒 Security

All debug tools:
- Require `manage_woocommerce` capability
- Use WordPress nonces for AJAX requests
- Only load when `WP_DEBUG` is enabled
- Sanitize all output

## 📝 Usage Examples

### Check if an order is in analytics:
```php
global $wpdb;
$count = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}wc_order_stats WHERE order_id = %d",
    $order_id
) );
$in_analytics = $count > 0;
```

### Force sync an order:
```php
if ( class_exists( '\Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore' ) ) {
    \Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore::sync_order( $order_id );
}
```

### Trigger analytics update:
```php
do_action( 'woocommerce_update_order', $order_id );
```

## 🚨 Important Notes

- **Only use in development/staging environments**
- Debug tools can impact performance
- Always disable debug mode in production
- Some operations may take time with large datasets

## 📞 Support

If you encounter issues with the debug tools:
1. Check WordPress error logs
2. Verify all debug constants are set
3. Ensure WooCommerce Analytics is enabled
4. Test with minimal plugins active
