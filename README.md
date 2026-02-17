# Umami WooCommerce Tracking

Integrates [Umami Analytics](https://umami.is/) with WooCommerce to automatically track key ecommerce events via **Umami custom events**.

## Requirements

- WordPress 5.8+
- WooCommerce 6.0+
- PHP 8.0+
- An Umami Analytics instance with a Website ID

## Installation

1. Upload the `umami-woocommerce-tracking` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins → Installed Plugins**.
3. Go to **WooCommerce → Settings → Integration → Umami Tracking**.
4. Enter your **Umami Script URL** and **Website ID**.
5. Check **Enable Tracking** and save.

That's it — events will start flowing into your Umami dashboard.

## Tracked Events

| Event             | Trigger                                        | Payload Keys                                                        |
| ----------------- | ---------------------------------------------- | ------------------------------------------------------------------- |
| `product_view`    | Single product page loaded                     | `product_id`, `product_name`, `price`, `currency`, `category`       |
| `add_to_cart`     | Product added (single page, AJAX, or archive)  | `product_id`, `product_name`, `price`, `quantity`, `currency`, `category` |
| `remove_from_cart`| Item removed from cart                         | `product_id`, `product_name`, `quantity_removed`                    |
| `view_cart`       | Cart page loaded                               | `cart_total`, `total_items`, `product_ids`, `currency`              |
| `begin_checkout`  | Checkout page loaded                           | `cart_total`, `total_items`, `currency`                             |
| `purchase`        | Thank-you / order-received page loaded         | `order_id`, `total_value`, `currency`, `products` (JSON string)     |

Each event can be individually toggled on/off from the settings page.

## Example Umami Event Payloads

### product_view

```json
{
  "product_id": 42,
  "product_name": "Classic T-Shirt",
  "price": 29.99,
  "currency": "USD",
  "category": "Clothing"
}
```

### add_to_cart

```json
{
  "product_id": 42,
  "product_name": "Classic T-Shirt",
  "price": 29.99,
  "quantity": 2,
  "currency": "USD",
  "category": "Clothing"
}
```

### remove_from_cart

```json
{
  "product_id": 42,
  "product_name": "Classic T-Shirt",
  "quantity_removed": 1
}
```

### view_cart

```json
{
  "cart_total": 59.98,
  "total_items": 2,
  "product_ids": "42,55",
  "currency": "USD"
}
```

### begin_checkout

```json
{
  "cart_total": 59.98,
  "total_items": 2,
  "currency": "USD"
}
```

### purchase

```json
{
  "order_id": 1234,
  "total_value": 64.97,
  "currency": "USD",
  "products": "[{\"id\":42,\"name\":\"Classic T-Shirt\",\"quantity\":2,\"price\":29.99},{\"id\":55,\"name\":\"Cap\",\"quantity\":1,\"price\":4.99}]"
}
```

## Settings

Found at **WooCommerce → Settings → Integration → Umami Tracking**:

| Field              | Description                                                    |
| ------------------ | -------------------------------------------------------------- |
| Enable Tracking    | Master on/off switch.                                          |
| Umami Script URL   | Full URL to your Umami JS file (e.g. `https://analytics.example.com/script.js`). |
| Website ID         | The UUID from your Umami dashboard.                            |
| Debug Mode         | Logs every `umami.track()` call to the browser console.        |
| Event Toggles      | Enable/disable each event type individually.                   |

## Extensibility (Developer Hooks)

### Filters

- **`umami_wc_event_enabled`** `( bool $enabled, string $event )` — Dynamically enable/disable a specific event.
- **`umami_wc_event_data`** `( array $data, string $event_name )` — Modify any event payload before it reaches the frontend.
- **`umami_wc_event_data_{event_name}`** `( array $data )` — Modify a specific event's payload (e.g. `umami_wc_event_data_purchase`).

### Actions

- **`umami_wc_before_product_view`** `( array $data, \WC_Product $product )`
- **`umami_wc_before_add_to_cart`** `( array $data, \WC_Product $product )`
- **`umami_wc_before_remove_from_cart`** `( array $data, \WC_Product $product )`
- **`umami_wc_before_view_cart`** `( array $data )`
- **`umami_wc_before_begin_checkout`** `( array $data )`
- **`umami_wc_before_purchase`** `( array $data, \WC_Order $order )`

### Example: Add a custom field to every event

```php
add_filter( 'umami_wc_event_data', function ( array $data, string $event ): array {
    $data['site_lang'] = get_locale();
    return $data;
}, 10, 2 );
```

### Example: Disable tracking for admins

```php
add_filter( 'umami_wc_event_enabled', function ( bool $enabled ): bool {
    if ( current_user_can( 'manage_options' ) ) {
        return false;
    }
    return $enabled;
} );
```

## Caching Compatibility

- The Umami `<script>` tag is enqueued via `wp_enqueue_scripts` with a proper handle, so page-cache plugins will include it in the cached HTML.
- AJAX add-to-cart events are tracked purely client-side, bypassing page cache entirely.
- Server-side events (add to cart, remove from cart) are stored in the **WC Session** and flushed on the next non-cached page load, ensuring they survive redirects and fragment caches.
- The purchase event is gated by `_umami_wc_tracked` order meta to prevent duplicates on page refresh.

## HPOS Compatibility

The plugin declares High-Performance Order Storage (HPOS / Custom Order Tables) compatibility via `FeaturesUtil::declare_compatibility()`.

## License

GPL-2.0-or-later
