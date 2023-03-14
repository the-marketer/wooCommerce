<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker;

use Mktr\Tracker\Model\Cron;

class Run
{
    private static $init = null;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public function __construct()
    {
        register_deactivation_hook(__FILE__, [$this, 'unInstall']);
        add_action('init', array($this, 'addRoute'), 0);

        if (is_admin()) {
            Admin::loadAdmin();
        } else {
            Front::loadFront();
        }

        add_action('wp_ajax_nopriv_woodmart_ajax_add_to_cart', array($this, 'add_to_cart'));
        add_action('wp_ajax_woodmart_ajax_add_to_cart', array($this, 'add_to_cart'));

        add_action('wp_ajax_nopriv_woodmart_add_to_wishlist', array($this, 'add_to_wishlist'));
        add_action('wp_ajax_woodmart_add_to_wishlist', array($this, 'add_to_wishlist'));

        add_action('wp_ajax_nopriv_woodmart_remove_from_wishlist', array($this, 'remove_from_wishlist'));
        add_action('wp_ajax_woodmart_remove_from_wishlist', array($this, 'remove_from_wishlist'));

        # add_action('wp_ajax_woodmart_ajax_add_to_cart', array(self::init(), 'test'));
        // add_action('woocommerce_loaded', function (){  });
        add_action('MKTR_CRON', array($this, "cronAction"));
    }

    public function remove_from_wishlist()
    {
        $product_id = Config::REQUEST('product_id');
        if ($product_id !== null) {
            Observer::removeFromWishlist($product_id, 0);
        }
    }

    public function add_to_wishlist()
    {
        $product_id = Config::REQUEST('product_id');
        if ($product_id !== null) {
            Observer::addToWishlist($product_id, 0);
        }
    }

    public function add_to_cart()
    {
        $addToCart = Config::REQUEST('add-to-cart');
        if ($addToCart !== null) {
            $quantity = Config::REQUEST('quantity');
            if (is_array($quantity)) {
                foreach ($quantity as $var=>$val) {
                    if (!empty($val)) {
                        Observer::addToCart(
                            $addToCart,
                            $val,
                            $var
                        );
                    }
                }
            } else {
                $variation_id = Config::REQUEST('variation_id');
                Observer::addToCart(
                    $addToCart,
                    $quantity,
                    $variation_id !== null ? $variation_id : 0
                );
            }
        }
    }

    public function cronAction()
    {
        Cron::cronAction();
    }

    public function addRoute()
    {
        add_rewrite_tag('%'.Config::$name.'%', '([^&]+)');

        /* Todo: AddToActivate */
        add_rewrite_rule(
            Config::$name.'/([^/]+)/([^/]+)/?',
            'index.php?'.Config::$name.'=$matches[2]',
            'top'
        );
    }

    public function unInstall()
    {
        wp_clear_scheduled_hook('MKTR_CRON');
    }
}
