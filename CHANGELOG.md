# Changelog
All notable changes to this project are documented in this file.
## 1.5.7
Orders are sent to theMarketer server-side, from woocommerce_checkout_order_processed, the Store API, woocommerce_new_order, woocommerce_payment_complete and woocommerce_order_status_changed, so an order no longer depends on the customer reaching the thank-you page. Gateway webhooks, admin and WP-CLI orders are covered as well.
Delivery state is tracked in a new table, {prefix}mktr_order_sync. An order is sent once: a status change afterwards carries no new save_order payload, since order_status travels on update_order_status.
Undelivered orders are retried hourly on the MKTR_ORDER_SYNC schedule, and through /mktr/api/OrderSync/?key=<REST key> for shops running a real cron. Retries are ordered least-recently-tried first, so an order that is not yet complete cannot hold up the rest of the queue. Entries expire after 7 days, delivered ones are remembered for 30.
The save_order request now runs on shutdown, after the response has been sent to the customer.
Fixed the newsletter opt-in being silently cleared when the classic checkout redraws itself after a shipping change; the posted value is read back out of post_data.
Added the opt-in checkbox to the block checkout, registered on woocommerce_init as woocommerce_register_additional_checkout_field requires.
Fixed the opt-in order meta being written with update_post_meta, which does not reach the order when HPOS is enabled.
The opt-in add_subscriber call is deferred to shutdown, so it no longer adds up to 3 seconds to the checkout.
Fixed the session order queue keeping every order when a single one failed, an undefined variable in the subscriber path, and a notice for guest orders with no user account.
Temporary: theMarketer rejects an order with 422 when lastname is empty, so a name with no second part is sent with firstname repeated. To be removed once the API accepts an empty lastname.

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
