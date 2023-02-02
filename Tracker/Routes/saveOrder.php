<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Api;
use Mktr\Tracker\Observer;
use Mktr\Tracker\Valid;

class saveOrder
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
        Valid::setParam('mime-type', 'js');

        $Order = WC()->session->get('saveOrder');
        $allGood = true;

        if (!empty($Order)) {
            foreach ($Order as $sOrder)
            {
                Api::send("save_order", $sOrder);

                if (Api::getStatus() != 200) {
                    $allGood = false;
                }

                if (!empty($sOrder['email_address']))
                {
                    $val = Observer::getEmail($sOrder['email_address']);

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
                    }

                    if (Api::getStatus() != 200) {
                        $allGood = false;
                    }
                }
            }

            if ($allGood)
            {
                WC()->session->set('saveOrder', array());
            }
        }
        return 'console.log('.$allGood.','.json_encode(Api::getInfo(), true).');';
    }
}