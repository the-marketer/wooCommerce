# Changelog
All notable changes to this project are documented in this file.
## 1.5.7
New setting: Cache compatible mode, under Extra Settings, off by default. When it is on, every visitor gets the same page HTML and no Set-Cookie header, so the pages can be cached by page caches such as LiteSpeed Cache, FlyingPress or Cloudflare. The visitor id is created in the browser, and cart, wishlist, order and email events are fetched with a POST request only when there is something to send. A plain page view makes no request to the shop.
Visitor ids are now UUID v4 in both modes.
The tracking script is served from a stable URL, assets/mktr.js?ver=<timestamp>, and is written in one step, so a cached page never points to a missing file. The script is removed when tracking is turned off.
Orders are sent to theMarketer server-side, from woocommerce_checkout_order_processed, the Store API, woocommerce_new_order, woocommerce_payment_complete and woocommerce_order_status_changed, so an order no longer depends on the customer reaching the thank-you page. Gateway webhooks, admin and WP-CLI orders are covered as well.
Delivery state is tracked in a new table, {prefix}mktr_order_sync. An order is sent once: a status change afterwards carries no new save_order payload, since order_status travels on update_order_status.
Undelivered orders are retried hourly on the MKTR_ORDER_SYNC schedule, and through /mktr/api/OrderSync/?key=<REST key> for shops running a real cron. Retries are ordered least-recently-tried first, so an order that is not yet complete cannot hold up the rest of the queue. Entries expire after 7 days, delivered ones are remembered for 30.
The save_order request now runs on shutdown, after the response has been sent to the customer.
Fixed the newsletter opt-in being silently cleared when the classic checkout redraws itself after a shipping change; the posted value is read back out of post_data.
Added the opt-in checkbox to the block checkout, registered on woocommerce_init as woocommerce_register_additional_checkout_field requires.
Fixed the opt-in order meta being written with update_post_meta, which does not reach the order when HPOS is enabled.
The opt-in add_subscriber call is deferred to shutdown, so it no longer adds up to 3 seconds to the checkout.
Fixed the session order queue keeping every order when a single one failed, an undefined variable in the subscriber path, and a notice for guest orders with no user account.
Fixed products and variations reaching the feed with sale_price 0 when Woo Discount Rules is active. Woo Discount Rules returns false for a product no rule applies to; that is now treated as no discount and the base price is kept.
Temporary: theMarketer rejects an order with 422 when lastname is empty, so a name with no second part is sent with firstname repeated. To be removed once the API accepts an empty lastname.
Fix: product feed always uses the shop base currency when generated server-side or via cron, instead of converting prices based on the server IP (multi-currency / GeoIP plugins).
Fix: product feed sale_price now reflects the single-unit price for Woo Discount Rules (quantity 1), preventing bulk/quantity rules from appearing as a product discount when no sale price is set.
Fix: XML feeds are served as application/xml instead of application/xhtml+xml.
Fix: imported reviews: the sync keeps a cursor and only moves it once every review in the window was handled; the first run after the update starts one day back, older reviews are imported with start_date.
Fix: imported reviews are stored as WooCommerce reviews on the parent product (a variation id or SKU resolves to its parent), with the verified flag set only for customers who bought the product.
Fix: reviews the merchant moved to trash or marked as spam are no longer imported again as approved.
Fix: a review sent without a rating no longer counts as one star; it stays unrated and out of the product average.
Fix: a review that fails 3 times no longer holds the sync; the result of the last review run is kept in the Cron route response (last_review_stats).
Fix: an invalid start_date on the Reviews route returns an error instead of calling the API.
Fix: the product_reviews request has a 30 second timeout instead of 3.
Fix: feeds generated server-side keep the shop's state (for example US:CA) in the default customer location, so per-state taxes still apply.
Fix: the first review sync after the update saves the start of its window and reuses it until the sync succeeds, so a review that fails is asked for again on the next run.
Fix: a review saved without its rating or id that could not be removed is repaired on the next run, instead of being skipped as a duplicate.

## 1.5.6
Update: bulk order status changes.

## 1.5.5
Improved removed subscriber implementation.

## 1.5.4
Add subscription checkbox on checkout page.
Add email capture feature for checkout page.

## 1.5.3
Support for geolocation with WPML Multilingual & Multicurrency for WooCommerce.

## 1.5.0
Remove Composer AutoLoad

## 1.4.9
Normalized file path handling and working directory usage across the plugin
Ensured data and log saves set the correct working directory
Fixed JS refresh to write assets to the proper folder
Refactored to use FileSystem for reading/writing plugin bootstrap
Enhanced debug data in Events and Logs, including customer and cart info.
Improved JS event loading with jQuery fallback and better event triggers.
Updated order status handling and error logging during initialization.
Changed JSON output to be pretty-printed and bumped version to v1.4.9.
Added support for multiple language plugins in Config and improved detection logic by introducing a checkLanguages method.
Expanded supported language codes, included base_url in event data, and updated JS to use the dynamic base_url.

## 1.4.8
Improved plugin's security, stability and performance

## 1.4.7
Added functionality for automatically adding products to the shopping cart
Added functionality for automatically applying discount codes in the checkout
 
## 1.4.0
Redesigned plugin admin interface

## 1.3.0
Improved product feed generation performance
Various bugfixes

## 1.2.0
Various bugfixes and improvements

## 1.1.0
Various bugfixes and improvements

## 1.0
Initial release
