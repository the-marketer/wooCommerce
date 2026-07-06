<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker;

use Mktr\Tracker\Routes\Cron;
use Mktr\Tracker\Routes\Feed;
use Mktr\Tracker\Routes\Brands;
use Mktr\Tracker\Routes\Orders;
use Mktr\Tracker\Routes\Reviews;
use Mktr\Tracker\Routes\Contacts;
use Mktr\Tracker\Routes\MailPoet;
use Mktr\Tracker\Routes\GForms;
use Mktr\Tracker\Routes\Category;
use Mktr\Tracker\Routes\FeedBack;
use Mktr\Tracker\Routes\setEmail;
use Mktr\Tracker\Routes\refreshJS;
use Mktr\Tracker\Routes\saveOrder;
use Mktr\Tracker\Routes\loadEvents;
use Mktr\Tracker\Routes\clearEvents;
use Mktr\Tracker\Routes\CodeGenerator;

class Route
{
    private static $init = null;

    private static $check = array(
        'Orders' => array(
            'key' => 'Required|Key|allow_export',
            'start_date' => 'Required|DateCheck|StartDate',
            'end_date' => null,
            'page' => null,
            'customerId' => null
        ),
        'CodeGenerator' => array(
            'key' => 'Required|Key',
            'expiration_date' => 'DateCheck',
            'value' => 'Required|Int',
            'type' => "Required|RuleCheck"
        ),
        'Reviews' => array(
            'key' => 'Required|Key',
            //'start_date' => 'Required|DateCheck|StartDate'
        ),
        'Contacts' => array(
            'key' => 'Required|Key'
        ),
        'MailPoet' => array(
            'key' => 'Required|Key'
        ),
        'GForms' => array(
            'key' => 'Required|Key'
        ),
        'Feed' => array(
            'key' => 'Required|Key'
        ),
        'Brands' => array(
            'key' => 'Required|Key'
        ),
        'Category' => array(
            'key' => 'Required|Key'
        ),
        'Cron' => array(
            'key' => 'Required|Key'
        ),
        'refreshJS' => array(
            'key' => 'Required|Key'
        )
    );

    private static $defMime = array(
        'Orders' => 'json',
        'CodeGenerator' => 'json',
        'Reviews' => 'json',
        'Contacts' => 'xml',
        'MailPoet' => 'xml',
        'GForms' => 'xml',
        'Feed' => 'xml',
        'Brands' => 'xml',
        'Category' =>'xml',
        'loadEvents' => 'json',
        'clearEvents' => 'js',
        'setEmail' => 'js',
        'saveOrder' => 'js',
        'Cron' => 'json',
        'FeedBack' => 'json',
        'refreshJS' => 'json'
    );

    private static $isStatic = array(
        'Orders' => true,
        'Feed' => true,
        'Brands' => true,
        'Category' => true
    );

    private static $allMethods = null;

    public static function init() {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function checkPage($p)
    {
        if (self::$allMethods == null)
        {
            foreach (get_class_methods(self::init()) as $value) {
                self::$allMethods[strtolower($value)] = $value;
            }
        }

        $p = strtolower($p);

        if(isset(self::$allMethods[$p]))
        {
            $page = self::$allMethods[$p];

            self::check($page);

            if (!Valid::status()) {
                ob_start();
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is XML/JSON, escaping not needed
                echo Valid::Output('status', Valid::error());
                ob_end_flush();
            }
            exit();
        }
    }

    /** @noinspection PhpReturnValueOfMethodIsNeverUsedInspection */
    private static function check($name)
    {
        if (isset(self::$defMime[$name]))
        {
            Valid::getParam('mime-type', self::$defMime[$name]);
        }

        if (isset(self::$check[$name]) && !Valid::check(self::$check[$name])->status())
        {
            return false;
        }

        global $wp_query;
        $wp_query->is_feed = true;
        
        if (in_array($name,['Orders', 'Feed', 'Brands', 'Category'])) {
            wp_raise_memory_limit('cron');
        }

        if (in_array($name, ['Orders', 'Feed', 'Brands', 'Category'])) {
            $baseCurrency = get_option('woocommerce_currency');

            $baseCountry = Valid::getParam('country');
            if ($baseCountry !== null && strlen($baseCountry) === 2) {
                $baseCountry = strtoupper($baseCountry);
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
                add_filter('wcml_geolocation_get_user_country', function ($country, $all) use ($baseCountry) {
                    return $baseCountry;
                }, 9999, 2);
            }

            add_filter('woocommerce_currency', function () use ($baseCurrency) {
                return $baseCurrency;
            }, 9999);

            add_filter('wcml_client_currency', function () use ($baseCurrency) {
                return $baseCurrency;
            }, 9999);

            add_filter('wc_aelia_cs_selected_currency', function () use ($baseCurrency) {
                return $baseCurrency;
            }, 9999);

            add_filter('wmc_get_current_currency', function () use ($baseCurrency) {
                return $baseCurrency;
            }, 9999);
            add_filter('woocommerce_multicurrency_forced_currency', function () use ($baseCurrency) {
                return $baseCurrency;
            }, 9999);

            if (isset($GLOBALS['WOOCS']) && is_object($GLOBALS['WOOCS'])) {
                if (method_exists($GLOBALS['WOOCS'], 'set_currency')) {
                    $GLOBALS['WOOCS']->set_currency($baseCurrency);
                }
                $GLOBALS['WOOCS']->current_currency = $baseCurrency;
                $GLOBALS['WOOCS']->default_currency = $baseCurrency;
                $GLOBALS['WOOCS']->is_multiple_allowed = false;
            }

            $priceHooks = array(
                'woocommerce_product_get_price',
                'woocommerce_product_get_regular_price',
                'woocommerce_product_get_sale_price',
                'woocommerce_product_variation_get_price',
                'woocommerce_product_variation_get_regular_price',
                'woocommerce_product_variation_get_sale_price',
                'woocommerce_variation_prices_price',
                'woocommerce_variation_prices_regular_price',
                'woocommerce_variation_prices_sale_price',
                'woocommerce_get_variation_prices_hash',
                'raw_woocommerce_price',
            );
            foreach ($priceHooks as $priceHook) {
                remove_all_filters($priceHook);
            }
        }

        $run = self::$name();
        ob_start();
        ob_clean();
        if (isset(self::$isStatic[$name]))
        {

            $script = '';
            $read = Valid::getParam('read');
            $file = Valid::getParam('file');
            $lang = Valid::getParam('lang');

            $start_date = Valid::getParam('start_date');

            if ($start_date !== null) {
                $script = '.'. base64_encode($start_date);
            }
            
            if ( defined( 'ICL_SITEPRESS_VERSION' ) ) { // wpml current  language.
				global $sitepress;
				$lang = $sitepress->get_current_language();
			}

            if ($lang !== null) {
                $script = $script . '.' . $lang;
                //global $sitepress;
                //if ($sitepress !== null) {
                //  $sitepress->switch_lang($lang);
                //}
            }

            $fileName = $run->get('fileName').$script.".".Valid::getParam('mime-type',Config::defMime);

            if ($file !== null) {
                header('Content-Disposition: attachment; filename=' . $fileName);
            }

            FileSystem::setWorkDirectory();
            if ($read !== null && FileSystem::fileExists($fileName)) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is XML/JSON, escaping not needed
                echo Valid::Output(FileSystem::readFile($fileName));
            } else {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is XML/JSON, escaping not needed
                echo Valid::Output($run->get('fileName'), array($run->get('secondName') => $run->execute()));
                FileSystem::writeFile($fileName, Valid::getOutPut());
            }
        } else {
            if ($run->get('fileName') !== null) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is XML/JSON, escaping not needed
                echo Valid::Output($run->get('fileName'), array($run->get('secondName') => $run->execute()));
            } else {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output is XML/JSON, escaping not needed
                echo Valid::Output($run->execute());
            }
        }
        ob_end_flush();
        exit();
        // return true;
    }

    /* Pages */

    /** @noinspection PhpUnused */
    private static function Feed()
    {
        return Feed::init();
    }

    /** @noinspection PhpUnused */
    private static function CodeGenerator()
    {
        return CodeGenerator::init();
    }

    public static function Orders()
    {
        return Orders::init();
    }

    public static function Category()
    {
        return Category::init();
    }

    public static function Cron()
    {
        return Cron::init();
    }

    public static function Brands()
    {
        return Brands::init();
    }
    public static function Reviews()
    {
        return Reviews::init();
    }

    public static function Contacts()
    {
        return Contacts::init();
    }

    public static function MailPoet()
    {
        return MailPoet::init();
    }

    public static function GForms()
    {
        return GForms::init();
    }

    /** @noinspection PhpUnused */
    public static function loadEvents()
    {
        return loadEvents::init();
    }

    /** @noinspection PhpUnused */
    public static function clearEvents() {
        return clearEvents::init();
    }

    public static function setEmail()
    {
        return setEmail::init();
    }

    /** @noinspection PhpUnused */
    public static function saveOrder()
    {
        return saveOrder::init();
    }
    /** @noinspection PhpUnused */
    public static function FeedBack()
    {
        return FeedBack::init();
    }

    /** @noinspection PhpUnused */
    public static function refreshJS()
    {
        return refreshJS::init();
    }
}