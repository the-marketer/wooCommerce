<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Api;
use Mktr\Tracker\Valid;

class setEmail
{
    private static $init = null;

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }
    public static function execute()
    {
        if (!function_exists( 'is_plugin_active' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        Valid::setParam('mime-type', 'js');

        $em = WC()->session->get('setEmail');

        $allGood = true;
        //$installed_plugins = get_plugins();
        $plug = 'mailpoet/mailpoet.php';

        // $installed = array_key_exists($plug , $installed_plugins ) || in_array($plug, $installed_plugins, true );
        $active    = is_plugin_active($plug);
        // $installed &&

        if ($active) {
            foreach ($em as $val) {
                $info = array(
                    "email" => $val['email_address']
                );

                $status = \MailPoet\Models\Subscriber::getWooCommerceSegmentSubscriber($val['email_address'])->status;

                if ($status === \MailPoet\Models\Subscriber::STATUS_SUBSCRIBED)
                {
                    $name = array();

                    if (!empty($val['firstname']))
                    {
                        $name[] = $val['firstname'];
                    }

                    if (!empty($val['lastname']))
                    {
                        $name[] = $val['lastname'];
                    }

                    if (empty($name))
                    {
                        $info["name"] = explode("@", $val['email_address'])[0];
                    } else {
                        $info["name"] = implode(" ", $name);
                    }
                    $user = get_user_by('email', $val['email_address']);
                    $phone = get_user_meta($user->ID, 'billing_phone', true);

                    if (!empty($phone)) {
                        $info["phone"] = $phone;
                    }

                    Api::send("add_subscriber", $info);
                } else {
                    Api::send("remove_subscriber", $info);
                }

                if (Api::getStatus() != 200) {
                    $allGood = false;
                }
            }
        }


        if ($allGood)
        {
            WC()->session->set('setPhone', array());
            WC()->session->set('setEmail', array());
        }

        return 'console.log('.$allGood.');';
    }
}