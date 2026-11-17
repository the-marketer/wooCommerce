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
        $data = Data::init();

        $startParam = Valid::getParam('start_date', null);
        $lastSync = (int) $data->last_review_sync;

        if ($startParam !== null) {
            $t = strtotime($startParam);
        } elseif ($lastSync > 0) {
            $t = $lastSync - DAY_IN_SECONDS;
        } else {
            $t = strtotime('-1 year');
        }

        $o = Api::send("product_reviews", array(
            't' => $t
        ), false);

        $stats = array(
            'url'              => Api::getUrl(),
            'http'             => Api::getStatus(),
            't'                => $t,
            't_readable'       => gmdate('Y-m-d H:i:s', $t),
            'received'         => 0,
            'inserted'         => 0,
            'skipped_exists'   => 0,
            'skipped_product'  => 0,
            'skipped_no_date'  => 0,
            'errors'           => 0,
        );

        $body = $o ? $o->getContent() : '';
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            $stats['errors']++;
            self::log('XML parse failed', $stats);
            return false;
        }

        foreach ($xml->review as $value) {
            $stats['received']++;

            if (!isset($value->review_date)) {
                $stats['skipped_no_date']++;
                continue;
            }

            $reviewId = (string) $value->review_id;
            $email    = (string) $value->review_email;
            $text     = (string) $value->review_text;

            $productId = (int) $value->product_id;
            if (get_post_type($productId) !== 'product') {
                $bySku = function_exists('wc_get_product_id_by_sku')
                    ? wc_get_product_id_by_sku((string) $value->product_sku)
                    : 0;

                if ($bySku && get_post_type($bySku) === 'product') {
                    $productId = (int) $bySku;
                } else {
                    $stats['skipped_product']++;
                    self::log('Product not found', array(
                        'review_id'   => $reviewId,
                        'product_id'  => (string) $value->product_id,
                        'product_sku' => (string) $value->product_sku,
                        'email'       => $email,
                    ));
                    continue;
                }
            }

            $dup = 0;
            if ($reviewId !== '') {
                $dup = (int) get_comments(array(
                    'meta_key'   => 'mktr_review_id',
                    'meta_value' => $reviewId,
                    'count'      => true,
                ));
            }
            if ($dup === 0) {
                $legacy = get_comments(array(
                    'author_email' => $email,
                    'search'       => $text,
                    'post_id'      => $productId,
                ));
                $dup = empty($legacy) ? 0 : count($legacy);
            }

            if ($dup > 0) {
                $stats['skipped_exists']++;
                continue;
            }

            $rating = (int) round((int) $value->rating / 2);
            $rating = max(1, min(5, $rating));

            $add = array(
                'comment_post_ID'      => $productId,
                'comment_author'       => (string) $value->review_author,
                'comment_author_email' => $email,
                'comment_author_url'   => '',
                'comment_content'      => $text,
                'comment_type'         => 'review',
                'comment_parent'       => 0,
                'comment_author_IP'    => '',
                'comment_agent'        => '',
                'comment_date'         => (string) $value->review_date,
                'comment_approved'     => 1,
            );

            $user = get_user_by('email', $email);
            if ($user) {
                $add['user_id'] = $user->ID;
            }

            $comment_id = wp_insert_comment($add);

            if (!$comment_id) {
                $stats['errors']++;
                self::log('wp_insert_comment failed', array(
                    'review_id'  => $reviewId,
                    'product_id' => $productId,
                    'email'      => $email,
                ));
                continue;
            }

            update_comment_meta($comment_id, 'rating', $rating);
            update_comment_meta($comment_id, 'verified', 1);
            if ($reviewId !== '') {
                update_comment_meta($comment_id, 'mktr_review_id', $reviewId);
            }

            if (class_exists('\WC_Comments')) {
                \WC_Comments::clear_transients($productId);
            }

            $stats['inserted']++;
        }

        $data->last_review_sync = time();
        $data->save();

        self::log('Reviews sync finished', $stats);

        return $xml;
    }

    private static function log($message, $context = array())
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Mktr Reviews] ' . $message . ' ' . wp_json_encode($context));
        }
    }
}