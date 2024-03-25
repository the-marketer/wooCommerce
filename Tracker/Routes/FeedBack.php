<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Valid;

class FeedBack
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
        Valid::setParam('mime-type', 'json');
        if (isset($_POST['message'])) {
            $d = \wp_remote_post('https://connector.themarketer.com/feedback/add',
                array(
                    'method'      => 'POST',
                    'timeout'     => 5,
                    'user-agent'  => 'mktr:' . \get_bloginfo( 'url' ),
                    'body' => \Mktr\Tracker\Run::platform()
                )
            );
        }
        return array('status' => 'succes');
    }
}