<?php
/**
 * @copyright   © EAX LEX SRL. All rights reserved.
 **/

namespace Mktr\Tracker\Routes;

use Mktr\Tracker\Api;
use Mktr\Tracker\Config;
use Mktr\Tracker\Data;
use Mktr\Tracker\Valid;

class Reviews
{
    private static $init = null;

    private static $map = array();

    public static function init()
    {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function get($f = 'fileName'){
        if (isset(self::$map[$f]))
        {
            return self::$map[$f];
        }
        return null;
    }

    public static function execute()
    {
        $t = Valid::getParam('start_date-type', date('Y-m-d'));
        $o = Api::send("product_reviews", array(
            't' => strtotime($t)
        ), false);

        $xml = simplexml_load_string($o->getContent(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $added = array();
        $data = Data::init();
        $revStore = $data->{"reviewStore".Config::getRestKey()};

        foreach ($xml->review as $value) {
            if (isset($value->review_date)) {
                if (!isset($revStore[(string) $value->review_id])) {
                    // $user = get_user_by( 'email', $value->review_email);
                    // \MailPoet\Models\Subscriber::getWooCommerceSegmentSubscriber($value->review_email)->status;
                    $add = array(
                        'comment_post_ID'      => $value->product_id, // <=== The product ID where the review will show up
                        'comment_author'       => $value->review_author,
                        'comment_author_email' => $value->review_email, // <== Important
                        'comment_author_url'   => '',
                        'comment_content'      => $value->review_text,
                        'comment_type'         => '',
                        'comment_parent'       => 0,
                        // 'user_id'              => 5, // <== Important
                        'comment_author_IP'    => '',
                        'comment_agent'        => '',
                        'comment_date'         => date('Y-m-d H:i:s'),
                        'comment_approved'     => 1,
                    );
                    $user = get_user_by('email', $value->review_email);

                    if ($user)
                    {
                        $add['user_id'] = $user->ID;
                    }

                    $comment_id = wp_insert_comment($add);

                    update_comment_meta( $comment_id, 'rating', round(((int)$value->rating / 2)) );

                    $added[(string) $value->review_id] = $comment_id;
                } else {
                    $added[(string) $value->review_id] = $data->reviewStore[(string) $value->review_id];
                }
            }
        }

        $data->{"reviewStore".Config::getRestKey()} = $added;

        $data->save();

        return $xml;
    }
}