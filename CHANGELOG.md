# Changelog
All notable changes to this project are documented in this file.
## 1.5.7
Fix: product feed always uses the shop base currency when generated server-side or via cron, instead of converting prices based on the server IP (multi-currency / GeoIP plugins).

## 1.5.6
Update: bulk order status changes.

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
