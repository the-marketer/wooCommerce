<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     http://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Model\Order;
use Mktr\Tracker\Config;
class Orders
{
    private static $init = null;

    private static $map = array(
        "fileName" => "orders",
        "secondName" => "order"
    );

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function get($f = 'fileName')
    {
        if (isset(self::$map[$f])) {
            return self::$map[$f];
        }
        return null;
    }

    public static function execute()
    {
        $start_date = Config::GET('start_date');
        $page = Config::GET('page');
        $args = array(
            'date_created' => '>' . $start_date,
            'order' => 'DESC',
            'orderby' => 'date',
            'paginate' => true,
            'return' => 'ids',
            'paged' => 1,
        );

        $stop = false;

        if ($page !== null) {
            $stop = true;
            $args['paged'] = $page;
        }

        $get = array();
        $toSkip = array();
        do {
            $orders = wc_get_orders($args);

            if ($stop) {
                $pages = 0;
            } else {
                $pages = $orders->total;
            }

            foreach ($orders->orders as $val) {
                Order::getById($val);

                if (isset($toSkip[$val])) {
                    unset($toSkip[$val]);
                } else {
                    if (Order::getParentId()) {
                        $ref = Order::getTotal();
                        $toSkip[Order::getParentId()] = true;

                        Order::getById(Order::getParentId());
                        Order::setRefund($ref);
                    }
                    $get[] = Order::toExtraArray();
                }
            }
            $args['paged']++;
        } while (0 < $pages);

        return $get;
    }
}
