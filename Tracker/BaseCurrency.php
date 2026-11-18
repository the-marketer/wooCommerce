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
 *
 * WP-Cron runs other scheduled tasks in the same request, so the cron caller
 * undoes everything with restore() once the feed is written.
 */
class BaseCurrency
{
    private static $applied = false;
    private static $filters = array();
    private static $woocs = null;

    public static function apply($country = null)
    {
        if (self::$applied) {
            return;
        }
        self::$applied = true;

        $baseCurrency = get_option('woocommerce_currency');

        /* woocommerce_customer_default_location expects "CC:ST", so the shop's state is
           kept when no country is forced; per-state tax rates then still apply. */
        $baseLocation = null;

        if ($country !== null && strlen($country) === 2) {
            $baseCountry = strtoupper($country);
        } else {
            $defaultCountry = (string) get_option('woocommerce_default_country');
            if (strpos($defaultCountry, ':') !== false) {
                $baseLocation = $defaultCountry;
            }

            $base = function_exists('wc_get_base_location') ? wc_get_base_location() : array();
            $baseCountry = isset($base['country']) && !empty($base['country'])
                ? $base['country']
                : substr($defaultCountry, 0, 2);
        }

        if (!empty($baseCountry)) {
            $forceCountry = function () use ($baseCountry) {
                return $baseCountry;
            };
            self::addFilter('woocommerce_geolocate_ip', $forceCountry);
            self::addFilter('woocommerce_geolocation_ip_country', $forceCountry);
            $baseLocation = $baseLocation !== null ? $baseLocation : $baseCountry;
            self::addFilter('woocommerce_customer_default_location', function () use ($baseLocation) {
                return $baseLocation;
            });
            // WPML / WooCommerce Multilingual & Multicurrency (WCML)
            self::addFilter('wcml_geolocation_get_user_country', $forceCountry, 2);
        }

        $currency = function () use ($baseCurrency) {
            return $baseCurrency;
        };
        self::addFilter('woocommerce_currency', $currency);
        // WPML / WooCommerce Multilingual & Multicurrency (WCML)
        self::addFilter('wcml_client_currency', $currency);
        // Aelia Currency Switcher
        self::addFilter('wc_aelia_cs_selected_currency', $currency);
        // CURCY - Multi Currency for WooCommerce (VillaTheme)
        self::addFilter('wmc_get_current_currency', $currency);
        self::addFilter('woocommerce_multicurrency_forced_currency', $currency);

        // FOX / WOOCS - Currency Switcher Professional for WooCommerce
        if (isset($GLOBALS['WOOCS']) && is_object($GLOBALS['WOOCS'])) {
            $woocs = $GLOBALS['WOOCS'];
            self::$woocs = array(
                'current_currency' => isset($woocs->current_currency) ? $woocs->current_currency : null,
                'default_currency' => isset($woocs->default_currency) ? $woocs->default_currency : null,
                'is_multiple_allowed' => isset($woocs->is_multiple_allowed) ? $woocs->is_multiple_allowed : null,
            );

            if (method_exists($woocs, 'set_currency')) {
                $woocs->set_currency($baseCurrency);
            }
            $woocs->current_currency = $baseCurrency;
            $woocs->default_currency = $baseCurrency;
            $woocs->is_multiple_allowed = false;
        }
    }

    public static function restore()
    {
        if (!self::$applied) {
            return;
        }

        foreach (self::$filters as $filter) {
            remove_filter($filter[0], $filter[1], 9999);
        }
        self::$filters = array();

        if (self::$woocs !== null && isset($GLOBALS['WOOCS']) && is_object($GLOBALS['WOOCS'])) {
            $woocs = $GLOBALS['WOOCS'];
            if (method_exists($woocs, 'set_currency') && self::$woocs['current_currency'] !== null) {
                $woocs->set_currency(self::$woocs['current_currency']);
            }
            foreach (self::$woocs as $property => $value) {
                $woocs->{$property} = $value;
            }
        }
        self::$woocs = null;
        self::$applied = false;
    }

    private static function addFilter($hook, $callback, $args = 1)
    {
        add_filter($hook, $callback, 9999, $args);
        self::$filters[] = array($hook, $callback);
    }
}
