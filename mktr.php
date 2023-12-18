<?php
/**
 * Plugin Name:             TheMarketer
 * Plugin URI:              https://themarketer.com/integrations/woocommerce
 * Description:             TheMarketer - WooCommerce Version
 * Version:                 1.2.8
 * Requires at least:       4.6
 * Requires PHP:            5.6
 * Author:                  themarketer.com
 * Author URI:              https://themarketer.com
 * Text Domain:             mktr
 * License:                 GPL2
 * License URI:             https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least:    4.0.0
 * WC tested up to:         8.4.0
 *
 * @package mktr
 */


defined('ABSPATH') OR exit('No direct script access allowed');

if (!defined('MKTR')) {
    define('MKTR', __FILE__);
}

if (!defined('MKTR_DIR')) {
    define('MKTR_DIR', dirname(__FILE__));
}

if (!defined('MKTR_BASE')) {
    define('MKTR_BASE', plugin_basename(MKTR));
}

if (!defined('MKTR_DEBUG')) {
    define('MKTR_DEBUG', false);
}
require_once MKTR_DIR . '/vendor/autoload.php';

/** @noinspection PhpFullyQualifiedNameUsageInspection */
\Mktr\Tracker\Run::init();
