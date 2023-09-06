<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker;

use Mktr\Tracker\Model\Cron;

class Run
{
    private static $add = null;
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

        add_filter('woocommerce_add_to_cart_product_id', array($this, 'filter_add_to_cart'));

        add_action('wp_ajax_woocommerce_add_to_cart', array( $this, 'add_to_cart' ));
        add_action('wp_ajax_nopriv_woocommerce_add_to_cart', array( $this, 'add_to_cart' ));

        add_action('wp_ajax_basel_ajax_add_to_cart', array($this, 'add_to_cart'), 1);
        add_action('wp_ajax_nopriv_basel_ajax_add_to_cart', array($this, 'add_to_cart'), 1);

        add_action('wp_ajax_nopriv_woodmart_ajax_add_to_cart', array($this, 'add_to_cart'));
        add_action('wp_ajax_woodmart_ajax_add_to_cart', array($this, 'add_to_cart'));

        add_action('wp_ajax_nopriv_woodmart_add_to_wishlist', array($this, 'add_to_wishlist'));
        add_action('wp_ajax_woodmart_add_to_wishlist', array($this, 'add_to_wishlist'));

        add_action('wp_ajax_nopriv_woodmart_remove_from_wishlist', array($this, 'remove_from_wishlist'));
        add_action('wp_ajax_woodmart_remove_from_wishlist', array($this, 'remove_from_wishlist'));

        add_action('wp_ajax_nopriv_add_to_wishlist', array($this, 'add_to_wishlist_yith'));
        add_action('wp_ajax_add_to_wishlist', array($this, 'add_to_wishlist_yith'));

        add_action('wp_ajax_nopriv_delete_item', array($this, 'remove_from_wishlist_item'));
        add_action('wp_ajax_delete_item', array($this, 'remove_from_wishlist_item'));

        add_action('wp_ajax_nopriv_remove_from_wishlist', array($this, 'remove_from_wishlist_yith'));
        add_action('wp_ajax_remove_from_wishlist', array($this, 'remove_from_wishlist_yith'));

        // add_action('wp_ajax_woodmart_ajax_add_to_cart', array(self::init(), 'test'));
        // add_action('woocommerce_loaded', function (){  });
        add_action('MKTR_CRON', array($this, "cronAction"));
    }

    public function add_to_wishlist_yith()
    {
        $product_id = Config::REQUEST('add_to_wishlist');
        if ($product_id !== null) {
            Observer::addToWishlist($product_id, 0);
        }
    }

    public function remove_from_wishlist_yith()
    {
        $product_id = Config::REQUEST('remove_from_wishlist');
        if ($product_id !== null) {
            Observer::removeFromWishlist($product_id, 0);
        }
    }

    public function remove_from_wishlist_item()
    {
        $product_id = null;
        $fragments = isset( $_REQUEST['fragments'] ) ? wc_clean( $_REQUEST['fragments'] ) : [];
        
        foreach($fragments as $v)
        {
            if(isset($v['product_id'])) {
                $product_id = $v['product_id'];
                break;
            }
        }
       
        if ($product_id !== null) {
            Observer::removeFromWishlist($product_id, 0);
        }
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
        if (self::$add === null) {
            $addToCart = Config::REQUEST('add-to-cart');
            if ($addToCart !== null) {
                self::$add = true;
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
    }

    public function filter_add_to_cart($product_id)
    {
        if (self::$add === null) {
            $addToCart = Config::REQUEST('add-to-cart');
            if ($addToCart !== null) {
                self::$add = true;
                $quantity = Config::REQUEST('quantity');
                $variation_id = Config::REQUEST('variation_id');
                Observer::addToCart(
                    $addToCart,
                    $quantity,
                    $variation_id !== null ? $variation_id : 0
                );
            }
        }
        return $product_id;
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
