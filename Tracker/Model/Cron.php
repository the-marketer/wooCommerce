<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker\Model;

use Mktr\Tracker\Config;
use Mktr\Tracker\Data;
use Mktr\Tracker\FileSystem;
use Mktr\Tracker\Routes\Feed;
use Mktr\Tracker\Routes\Reviews;
use Mktr\Tracker\Valid;

class Cron
{
    private static $init = null;
    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function cronAction() {

        $data = Data::init();
        $upFeed = $data->update_feed;
        $upReview = $data->update_review;

        if (Config::getStatus() != 0) {

            if (Config::getCronFeed() != 0 && $upFeed < time()) {

                $run = Feed::init();

                $fileName = $run->get('fileName').".".Valid::getParam('mime-type',Config::defMime);

                Valid::Output($run->get('fileName'), array( $run->get('secondName') => $run->execute()));

                FileSystem::writeFile($fileName, Valid::getOutPut());

                $data->update_feed = strtotime("+".Config::getUpdateFeed()." hour");
            }

            if (Config::getCronReview() != 0 && $upReview < time()) {

                Reviews::execute();

                $data->update_review = strtotime("+".Config::getUpdateReview()." hour");
            }
        }

        $data->save();
    }
}
