<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker;

class Front
{
    private static $init = null;

    public static $Page = false;

    public static function init() {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function loadFront()
    {
        if (Config::getStatus() === 1 && !empty(Config::getKey()))
        {
            add_action('template_redirect', array(self::init(), 'routeCheck'));
            add_action('wp_login', array(self::init(), 'registerOrLogIn'), 10, 2);
            add_action('user_register', array(self::init(), 'registerOrLogIn'), 10, 2 );
            // add_action('woocommerce_loaded', array(self::init(), 'LoadSession'));
            add_action('woocommerce_loaded', array(self::init(), 'loadModule'));
        }

        //add_action('shutdown', array($this, 'sd'), 0);
    }


    public function registerOrLogIn($user_login, $user = null) {
        Observer::registerOrLogIn($user_login, $user);
    }

    public function saveOrder($orderId) {
        Observer::saveOrder($orderId);
    }

    public function loadModule()
    {

        if(Config::Google)
        {
            add_action('wp_head', array(self::init(), 'google_head'));
            add_action('wp_footer', array(self::init(), 'google_body'));
        }

        add_action('woocommerce_before_thankyou', array(self::init(), 'saveOrder'));

        // AddToCart events
        add_action('woocommerce_add_to_cart', array(self::init(), 'AddCartEvent'), 40, 4 );
        add_action('woocommerce_remove_cart_item', array(self::init(), 'RemoveCartEvent'), 10, 2);
        add_filter('woocommerce_cart_item_removed_title', array(self::init(), 'RemoveCartEventFilter'), 10, 2);

        // AddToCart while AJAX is enabled
        // add_action('woocommerce_ajax_added_to_cart',  array($this, 'AddCartEvent'));

        add_action('wp_head', array(Events::init(), 'loader'));
        add_action('wp_footer', array(Events::init(), 'loadEvents'));
        add_action('wp_footer', array(self::init(), 'addToCart'));
        add_filter('woocommerce_email_enabled_customer_new_account', function ($status){
            if (Config::getOptIn() == 0) {
                return $status;
            }
            return false;
        });

    }

    /** @noinspection PhpUnusedParameterInspection */
    public static function AddCartEvent($frg = null, $product_id = null, $quantity = null, $variation_id = null)
    {
        Observer::addToCart(
            $product_id === null ? $_POST['product_id'] : $product_id,
            $quantity === null ? $_POST['quantity'] : $quantity,
            $variation_id === null ? 0 : $variation_id);
    }

    public static function RemoveCartEvent($item, $cart = null) {
        $cart = $cart->cart_contents[$item];
        Observer::removeFromCart($cart['product_id'], $cart['quantity'],$cart['variation_id']);
    }

    public static function RemoveCartEventFilter($item, $cart = null) {
        Observer::removeFromCart($cart['product_id'], $cart['quantity'],$cart['variation_id']);
        return $item;
    }

    public function routeCheck() {

        if (isset($_COOKIE['mktr']))
        {
            Observer::emailAndPhone($_COOKIE['mktr']);
            setcookie("mktr", null, 0);
            unset($_COOKIE['mktr']);
        }

        self::$Page = get_query_var(Config::$name, false);

        if (self::$Page === false) {
            $p = array();
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            preg_match("/([^\/]+)\/([^\/]+)\/([^\/]+)/i", $path, $p);

            $ch = array(
                Config::$name => false,
                'api' => false
            );

            unset($p[0]);
            foreach ($p as $v) {
                if (!empty($v)){
                    if ($ch[Config::$name] && $ch['api']) { self::$Page = $v; } else
                        if ($v === Config::$name) { $ch[Config::$name] = true; } else
                            if ($v === 'api') { $ch['api'] = true; }
                }
            }
        }

        if (self::$Page !== false)
        {
            Route::checkPage(self::$Page);
        }
    }


    public static function google_head()
    {
        $key = Config::getValue('google_tagCode');

        if (!empty($key)) {
            echo  "<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
    })(window,document,'script','dataLayer','".$key."');</script>
<!-- End Google Tag Manager -->";
        }
    }

    public static function google_body()
    {
        $key = Config::getValue('google_tagCode');
        if (!empty($key)) {
            echo '<!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id='.$key.'" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->';
        }
    }

    public static function addToCart()
    {
        echo '<script type="text/javascript">
        (function($) {
            let MktrLoadEvents = true;
            
			let AddMktrEvents = function () {
                (function(){ 
				let add = document.createElement("script");
                    add.async = true;
                    add.src = "' .Config::getBaseURL(). 'mktr/api/loadEvents/";
                let s = document.getElementsByTagName("script")[0];
                    s.parentNode.insertBefore(add,s);
                })(); MktrLoadEvents = true;
			};
			
			let LoadEventsMktr = function() { if (MktrLoadEvents) { MktrLoadEvents = false; setTimeout(AddMktrEvents, 1000); } };            
            
            $(document.body).on("added_to_cart", LoadEventsMktr);
            $(document.body).on("removed_from_cart", LoadEventsMktr);
            $(document.body).on("click", "'.Config::getSelectors().'", LoadEventsMktr);
        })(jQuery); </script>';
    }
}
