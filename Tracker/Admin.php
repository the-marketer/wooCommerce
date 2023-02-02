<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker;

class Admin
{
    private static $init = null;

    private static $notice = array();

    public static function init() {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function loadAdmin() {
        Form::initProcess();

        if (Config::getStatus() === 1 && (Config::getCronFeed() === 1 || Config::getCronReview() === 1)) {
            if (!wp_next_scheduled( 'MKTR_CRON' )) {
                wp_schedule_event(time(), 'hourly', 'MKTR_CRON');
            }
        }

        add_filter('plugin_action_links_'.Config::getPluginBase(), array(self::init(), 'action_links'));
        add_filter('plugin_row_meta', array(self::init(), 'extra_links'), 10, 2);
        add_action('admin_menu', array(self::init(), 'menu'));
        add_action('admin_notices', array(self::init(), 'notice'));

        add_action('woocommerce_order_edit_status', array(Observer::init(), 'orderUp'), 10, 2 );
    }

    public static function addNotice($notice)
    {
        self::$notice[] = is_array($notice) ? $notice : array('message' => $notice);
        return self::init();
    }

    public static function notice() {
        if (!empty(self::$notice)) {
            $out = array();

            /*if (Config::getStatus() == 1 && empty(Config::getKey()))
            {
                $out[] = '<div class="updated notice notice-success is-dismissible"><p>theMarketer is almost ready. <a href="'.admin_url('admin.php?page=mktr_tracker').'">Click Here</a></p></div>';
            }*/
            /* notice-error | notice-success | notice-fail | notice-info*/
            foreach (self::$notice as $value)
            {
                $out[] = '<div class="notice notice-'.
                    (isset($value['type']) ? $value['type'] : 'success').
                    ' is-dismissible"><p>'.$value['message'].'</p></div>';
            }

            echo implode(PHP_EOL,$out);
        }
    }

    public static function action_links($links)
    {
        return array_merge(
            array (
                'settings' => '<a href="'. admin_url('admin.php?page=mktr_tracker') . '" target="_blank"> Settings</a>'
            ), $links);
    }

    public static function extra_links( $links, $file ) {
        if (Config::getPluginBase() !== $file ) {
            return $links;
        }

        // $links['settings'] = '<a href="'. admin_url('admin.php?page=mktr_tracker') . '"> Settings</a>';

        foreach ($links as $k=>$v)
        {
            $links[$k] = str_replace('">','" target="_blank">',$v);
        }

        return $links;
    }

    public static function menu()
    {
        add_menu_page('TheMarketer',
            'TheMarketer',
            null,
            'mktr',
            null,
            Config::getSVG(),
            7
        );

        add_submenu_page(
            'mktr',
            'Tracker',
            'Tracker',
            'manage_options',
            'mktr_tracker',
            array(self::init(), 'tracker'));

        if(Config::Google)
        {
            add_submenu_page(
                'mktr',
                'Google',
                'Google',
                'manage_options',
                'mktr_google',
                array(self::init(), 'google'));
        }
    }

    public static function google()
    {
        Form::formFields(
            array(
                'tit-set' => array(
                    'title'     => '<img style="height:20px;padding:0px;vertical-align: middle;" src="'.Config::getSVG().'" alt="TheMarketer"> Main Settings',
                    'type'      => 'title',
                ),
                'google_status' => array(
                    'title'     => 'Status',
                    'type'      => 'select'
                ),
                'google_tagCode' => array(
                    'title'     => 'Tag CODE *',
                    'type'      => 'text',
                    'holder'    => 'Tag CODE'
                ),
            )
        );

        echo Form::getForm();
    }

    public static function tracker()
    {
        Form::formFields(
            array(
                'tit-set' => array(
                    'title'     => '<img style="height:20px;padding:0px;vertical-align: middle;" src="'.Config::getSVG().'" alt="TheMarketer"> Main Settings',
                    'type'      => 'title',
                ),
                'status' => array(
                    'title'     => 'Status',
                    'type'      => 'select'
                ),
                /* Account Settings */
                'tracking_key' => array(
                    'title'     => 'Tracking API Key *',
                    'type'      => 'text',
                    'holder'    => 'Your Tracking API Key.'
                ),
                'rest_key' => array(
                    'title'     => 'REST API Key *',
                    'type'      => 'text',
                    'holder'    => 'Your REST API Key.'
                ),
                'customer_id' => array(
                    'title'     => 'Customer ID *',
                    'type'      => 'text',
                    'holder'    => 'Your Customer ID.'
                ),
                'tit-sett' => array(
                    'title'     => '',
                    'type'      => 'title',
                ),
                /* Cron Settings */
                'cron_feed' => array(
                    'title'     => 'Activate Cron Feed',
                    'type'      => 'select'
                ),
                'update_feed' => array(
                    'title'     => 'Cron Update feed every (hours)',
                    'type'      => 'text',
                    'description' => 'Set number of hours'
                ),
                'cron_review' => array(
                    'title'     => 'Activate Cron Review',
                    'type'      => 'select'
                ),
                'update_review' => array(
                    'title'     => 'Cron Update Review every (hours)',
                    'type'      => 'text',
                    'description' => 'Set number of hours'
                ),
                /* Extra Settings */
                'opt_in' => array(
                    'title'     => 'Double opt-in setting',
                    'type'      => 'select',
                    'options' => array(
                        array('value' => 0, 'label' => 'WebSite'),
                        array('value' => 1, 'label' => 'The Marketer')
                    )
                ),
                'push_status' => array(
                    'title'     => 'Push Notification',
                    'type'      => 'select'
                ),
                'default_stock' => array(
                    'title'     => 'Default Stock if negative Stock Value',
                    'type'      => 'select',
                    'options' => array(
                        array('value' => 0, 'label' => 'Out of Stock'),
                        array('value' => 1, 'label' => 'In Stock'),
                        array('value' => 2, 'label' => 'In supplier stock')
                    )
                ),
                'allow_export' => array(
                    'title'     => 'Allow orders export',
                    'type'      => 'select'
                ),
                'selectors' => array(
                    'title'     => 'Trigger Selectors',
                    'type'      => 'text',
                    'description' => 'Buttons that will trigger events Like AddToCart'
                ),
                /* Attribute Settings */
                'tit-set2' => array(
                    'title'     => '<img style="height:20px;padding:0px;vertical-align: middle;" src="'.Config::getSVG().'" alt="TheMarketer"> Attribute Settings',
                    'type'      => 'title',
                ),
                'brand' => array(
                    'title'     => 'Brand Attribute',
                    'type'      => 'text',
                    'description' => ''
                ),
                'color' => array(
                    'title'     => 'Color Attribute',
                    'type'      => 'text',
                    'description' => ''
                ),
                'size' => array(
                    'title'     => 'Size Attribute',
                    'type'      => 'text',
                    'description' => ''
                ),
            )
        );

        echo Form::getForm();
    }
}
