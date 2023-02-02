<?php
/**
 * Plugin Name:             TheMarketer
 * Plugin URI:              https://eax.ro/woocommerce
 * Description:             TheMarketer - WooCommerce Version
 * Version:                 1.0.0
 * Author:                  EAX.Ro
 * Author URI:              https://eax.ro
 * Text Domain:             mktr
 * License:                 GPL2
 * License URI:             https://www.gnu.org/licenses/gpl-2.0.html
 * WC requires at least:    4.0.0
 * WC tested up to:         6.0.2
 *
 * @package mktr
 */


defined('ABSPATH') OR exit('No direct script access allowed');

if (!defined('MKTR'))
{
    define('MKTR', __FILE__);
}

if (!defined('MKTR_DIR'))
{
    define('MKTR_DIR', dirname(__FILE__));
}
require_once MKTR_DIR . '/vendor/autoload.php';

/** @noinspection PhpFullyQualifiedNameUsageInspection */
\Mktr\Tracker\Run::init();
