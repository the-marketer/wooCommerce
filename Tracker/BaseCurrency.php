<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      theMarketer
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker;

/**
 * Feeds are generated server-side (route or cron), where multi-currency and
 * GeoIP plugins would otherwise pick a currency from the server IP. Only the
 * currency and country are forced; other price filters (discounts, role or
 * B2B pricing) stay in place so the feed matches the shop.
 */
class BaseCurrency
{
    private static $applied = false;

    public static function apply($country = null)
    {
        if (self::$applied) {
            return;
        }
        self::$applied = true;

        $baseCurrency = get_option('woocommerce_currency');

        if ($country !== null && strlen($country) === 2) {
            $baseCountry = strtoupper($country);
        } else {
            $base = function_exists('wc_get_base_location') ? wc_get_base_location() : array();
            $baseCountry = isset($base['country']) && !empty($base['country'])
                ? $base['country']
                : substr((string) get_option('woocommerce_default_country'), 0, 2);
        }

        if (!empty($baseCountry)) {
            add_filter('woocommerce_geolocate_ip', function () use ($baseCountry) {
                return $baseCountry;
            }, 9999);
            add_filter('woocommerce_geolocation_ip_country', function () use ($baseCountry) {
                return $baseCountry;
            }, 9999);
            add_filter('woocommerce_customer_default_location', function () use ($baseCountry) {
                return $baseCountry;
            }, 9999);
            // WPML / WooCommerce Multilingual & Multicurrency (WCML)
            add_filter('wcml_geolocation_get_user_country', function ($country, $all) use ($baseCountry) {
                return $baseCountry;
            }, 9999, 2);
        }

        add_filter('woocommerce_currency', function () use ($baseCurrency) {
            return $baseCurrency;
        }, 9999);

        // WPML / WooCommerce Multilingual & Multicurrency (WCML)
        add_filter('wcml_client_currency', function () use ($baseCurrency) {
            return $baseCurrency;
        }, 9999);

        // Aelia Currency Switcher
        add_filter('wc_aelia_cs_selected_currency', function () use ($baseCurrency) {
            return $baseCurrency;
        }, 9999);

        // CURCY - Multi Currency for WooCommerce (VillaTheme)
        add_filter('wmc_get_current_currency', function () use ($baseCurrency) {
            return $baseCurrency;
        }, 9999);

        add_filter('woocommerce_multicurrency_forced_currency', function () use ($baseCurrency) {
            return $baseCurrency;
        }, 9999);

        // FOX / WOOCS - Currency Switcher Professional for WooCommerce
        if (isset($GLOBALS['WOOCS']) && is_object($GLOBALS['WOOCS'])) {
            if (method_exists($GLOBALS['WOOCS'], 'set_currency')) {
                $GLOBALS['WOOCS']->set_currency($baseCurrency);
            }
            $GLOBALS['WOOCS']->current_currency = $baseCurrency;
            $GLOBALS['WOOCS']->default_currency = $baseCurrency;
            $GLOBALS['WOOCS']->is_multiple_allowed = false;
        }
    }
}
