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

class Front
{
    private static $init = null;

    public static $Page = false;

    public static $RemoveCartEvent = true;
    public static $saveOrderEvent = true;
    public static $addCartEvent = true;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function loadFront()
    {
        if (Config::getOnboarding() === 2 && Config::getStatus() === 1 && !empty(Config::getKey())) {
            add_action('template_redirect', array(self::init(), 'routeCheck'));
            add_action('wp_login', array(self::init(), 'registerOrLogIn'), 10, 2);
            add_action('user_register', array(self::init(), 'registerOrLogIn'), 10, 2);

            // add_action('woocommerce_loaded', array(self::init(), 'LoadSession'));
            
            add_action('woocommerce_loaded', array(self::init(), 'loadModule'));
            add_action('woocommerce_update_order', array(Observer::init(), 'orderUpApi'), 10, 2);
        } else {
            add_action('template_redirect', array(self::init(), 'routeCheck'));
        }

        //add_action('shutdown', array($this, 'sd'), 0);
    }

    public function registerOrLogIn($user_login, $user = null)
    {
        Observer::registerOrLogIn($user_login, $user);
    }

    public function saveOrder($orderId = null)
    {
        if ($orderId !== null && self::$saveOrderEvent) {
            Config::session()->set("mktr_cart", []);
            self::$saveOrderEvent = false;
            Observer::saveOrder($orderId);
        }
    }

    public function saveOrder1($orderId = null, $checkout = null )
    {
        // Logs::debug($orderId, 'saveOrder1');
        if ($orderId !== null && self::$saveOrderEvent) {
            Config::session()->set("mktr_cart", []);
            self::$saveOrderEvent = false;
            Observer::saveOrder($orderId);
        }
    }

    public function loadModule() {

        add_action('woocommerce_before_thankyou', array(self::init(), 'saveOrder'));
        add_action('woocommerce_thankyou', array(self::init(), 'saveOrder'));
        add_action('woocommerce_new_order', array(self::init(), 'saveOrder'), 10, 2);

        add_action('wp_head', array(Events::init(), 'loader'));
        add_action('wp_enqueue_scripts', array(Events::init(), 'initEvents') );

        if (Config::getOptinCheckout() === 1) {
            $position = Config::getOptinPosition();
            if (!empty($position)) {
                add_action($position, array(self::init(), 'displayOptinCheckbox'));
            }
            add_action('woocommerce_checkout_update_order_meta', array(self::init(), 'saveOptinCheckbox'));

            /* The block checkout runs none of the hooks above, so it needs its own
               field, and the API that registers it only accepts fields on
               woocommerce_init. */
            add_action('woocommerce_init', array(self::init(), 'registerBlockOptin'));
            add_action('woocommerce_store_api_checkout_order_processed', array(self::init(), 'saveBlockOptin'));
        }

        // add_filter('woocommerce_create_order', array(self::init(), 'saveOrder1'), 10, 2 );

        // AddToCart events
        // add_action('woocommerce_add_to_cart', array(self::init(), 'AddCartEvent'), 40, 4);
        // add_action('woocommerce_remove_cart_item', array(self::init(), 'RemoveCartEvent'), 10, 2);
        // add_filter('woocommerce_cart_item_removed_title', array(self::init(), 'RemoveCartEventFilter'), 10, 2);

        // AddToCart while AJAX is enabled
        // add_action('woocommerce_ajax_added_to_cart',  array($this, 'AddCartEvent'));
        // add_action('wp_footer', array(Events::init(), 'loadEvents'));
        
        /*
        add_filter('woocommerce_email_enabled_customer_new_account', function ($status) {
            if (Config::getOptIn() == 0) {
                return $status;
            }
            return false;
        });
        */
    }

    /** @noinspection PhpUnusedParameterInspection */
    public static function AddCartEvent($frg = null, $product_id = null, $quantity = null, $variation_id = null)
    {
        if (self::$addCartEvent) {
            self::$addCartEvent = false;
            Observer::addToCart(
                $product_id === null ? Config::POST('product_id') : $product_id,
                $quantity === null ? Config::POST('quantity') : $quantity,
                $variation_id === null ? 0 : $variation_id
            );
        }
    }

    public static function RemoveCartEvent($item, $cart = null)
    {
        if (self::$RemoveCartEvent) {
            self::$RemoveCartEvent = false;
            $cart = $cart->cart_contents[$item];
            Observer::removeFromCart($cart['product_id'], $cart['quantity'], $cart['variation_id']);
        }
    }

    public static function RemoveCartEventFilter($item, $cart = null)
    {
        if (self::$RemoveCartEvent) {
            self::$RemoveCartEvent = false;
            Observer::removeFromCart($cart['product_id'], $cart['quantity'], $cart['variation_id']);
            return $item;
        }
    }

    public function routeCheck()
    {
        if (isset($_COOKIE['mktr'])) {
            $mktr_cookie = sanitize_text_field(wp_unslash($_COOKIE['mktr']));
            Observer::emailAndPhone($mktr_cookie);
            setcookie("mktr", '', 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
            unset($_COOKIE['mktr']);
        }

        self::$Page = get_query_var(Config::$name, false);

        if (self::$Page === false) {
            $p = array();
            $path = '';
            if (isset($_SERVER['REQUEST_URI'])) {
                $path = parse_url(sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])), PHP_URL_PATH);
            }
            preg_match("/([^\/]+)\/([^\/]+)\/([^\/]+)/i", $path, $p);

            $ch = array( Config::$name => false, 'api' => false );

            unset($p[0]);
            foreach ($p as $v) {
                if (!empty($v)) {
                    if ($ch[Config::$name] && $ch['api']) {
                        self::$Page = $v;
                    } elseif ($v === Config::$name) {
                        $ch[Config::$name] = true;
                    } elseif ($v === 'api') {
                        $ch['api'] = true;
                    }
                }
            }
        }

        if (self::$Page !== false) {
            Route::checkPage(self::$Page);
        }
    }

    public static function displayOptinCheckbox()
    {
        $message = Config::getOptinMessage();
        if (empty($message)) {
            $message = 'I would like to receive exclusive emails with discounts and product information';
        }

        woocommerce_form_field('mktr_optin_subscribe', array(
            'type'  => 'checkbox',
            'class' => array('mktr-optin-checkbox form-row-wide'),
            'label' => esc_html($message),
        ), self::optinChecked());
    }

    /**
     * update_order_review redraws the payment fragment, so the field has to restore
     * itself or the customer's tick is silently lost. There the posted values arrive
     * serialised in post_data, not as normal fields.
     */
    public static function optinChecked()
    {
        if (isset($_POST['mktr_optin_subscribe'])) {
            return 1;
        }

        if (isset($_POST['post_data']) && is_string($_POST['post_data'])) {
            /* Not sanitized as a whole: that strips percent encoding and corrupts the
               query string. Only the presence of our own key is read out of it. */
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            parse_str(wp_unslash($_POST['post_data']), $posted);
            return isset($posted['mktr_optin_subscribe']) ? 1 : 0;
        }

        return 0;
    }

    public static function saveOptinCheckbox($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $optin_value = self::optinChecked();

        /* Not update_post_meta: with HPOS the order does not live in wp_posts, and
           the plugin declares itself compatible with it. */
        $order->update_meta_data('_mktr_optin_subscribe', $optin_value);
        $order->save_meta_data();

        $user_id = $order->get_user_id();
        if ($user_id > 0) {
            update_user_meta($user_id, '_mktr_optin_subscribe', $optin_value);
        }

        if ($optin_value === 1) {
            self::subscribeFromOrder($order);
        }
    }

    /** Block checkout field. The API needs a namespaced id. */
    public static function registerBlockOptin()
    {
        if (!function_exists('woocommerce_register_additional_checkout_field')) {
            return;
        }

        $message = Config::getOptinMessage();
        if (empty($message)) {
            $message = 'I would like to receive exclusive emails with discounts and product information';
        }

        woocommerce_register_additional_checkout_field(array(
            'id'       => 'mktr/optin-subscribe',
            'label'    => $message,
            'location' => 'order',
            'type'     => 'checkbox',
        ));
    }

    public static function saveBlockOptin($order = null)
    {
        if (!is_object($order) || !method_exists($order, 'get_meta')) {
            return;
        }

        /* 0 as well as 1, so the meta means the same thing on both checkouts. */
        $optin_value = $order->get_meta('_wc_other/mktr/optin-subscribe') ? 1 : 0;

        $order->update_meta_data('_mktr_optin_subscribe', $optin_value);
        $order->save_meta_data();

        $user_id = $order->get_user_id();
        if ($user_id > 0) {
            update_user_meta($user_id, '_mktr_optin_subscribe', $optin_value);
        }

        if ($optin_value === 1) {
            self::subscribeFromOrder($order);
        }
    }

    private static function subscribeFromOrder($order)
    {
        $email = $order->get_billing_email();

        if (empty($email)) {
            return;
        }

        $name = array();
        if (!empty($order->get_billing_first_name())) {
            $name[] = $order->get_billing_first_name();
        }
        if (!empty($order->get_billing_last_name())) {
            $name[] = $order->get_billing_last_name();
        }

        $info = array(
            "email" => $email,
            "name" => !empty($name) ? implode(" ", $name) : explode("@", $email)[0],
        );

        $phone = $order->get_billing_phone();
        if (!empty($phone)) {
            $info["phone"] = $phone;
        }

        /* Both callers run while the customer is waiting for the checkout to finish,
           and Api::send blocks for up to 3 seconds. */
        Defer::api("add_subscriber", $info, 'optin_add_subscriber');
    }
}
