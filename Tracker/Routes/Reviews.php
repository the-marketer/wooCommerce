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
    /* Imported reviews are stored as "review"; older plugin versions stored them as plain comments. */
    const COMMENT_TYPES = array('review', 'comment');

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

        $requestedAt = time();
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

        if ((int) $stats['http'] !== 200) {
            $stats['errors']++;
            self::log('Unexpected HTTP status', $stats);
            return false;
        }

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

            $productId = self::reviewableProduct((int) $value->product_id);
            if ($productId === 0) {
                $bySku = function_exists('wc_get_product_id_by_sku')
                    ? self::reviewableProduct((int) wc_get_product_id_by_sku((string) $value->product_sku))
                    : 0;

                if ($bySku !== 0) {
                    $productId = $bySku;
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
                /* WooCommerce leaves product reviews out of get_comments() unless the type is asked for. */
                $dup = (int) get_comments(array(
                    'type__in'   => self::COMMENT_TYPES,
                    'meta_key'   => 'mktr_review_id',
                    'meta_value' => $reviewId,
                    'count'      => true,
                ));
            }
            if ($dup === 0 && self::hasLegacyCopy($productId, $email, $text)) {
                $dup = 1;
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

            $meta = array('rating' => $rating, 'verified' => self::boughtProduct($email, $user ? $user->ID : 0, $productId) ? 1 : 0);
            if ($reviewId !== '') {
                $meta['mktr_review_id'] = $reviewId;
            }

            /* update_comment_meta() also returns false when the value is already there,
               so the stored values are read back instead. */
            $metaSaved = true;
            foreach ($meta as $metaKey => $metaValue) {
                update_comment_meta($comment_id, $metaKey, $metaValue);
                if ((string) get_comment_meta($comment_id, $metaKey, true) !== (string) $metaValue) {
                    $metaSaved = false;
                }
            }

            /* A review left without its rating or external id would be skipped as a
               duplicate on the next run and never repaired, so it is removed and retried. */
            if (!$metaSaved) {
                wp_delete_comment($comment_id, true);
                $stats['errors']++;
                self::log('update_comment_meta failed', array(
                    'review_id'  => $reviewId,
                    'product_id' => $productId,
                    'email'      => $email,
                ));
                continue;
            }

            if (class_exists('\WC_Comments')) {
                \WC_Comments::clear_transients($productId);
            }

            $stats['inserted']++;
        }

        /* The next run starts one day before the cursor, so the cursor only moves
           when every review was handled; otherwise the same window is asked again.
           A manual start_date covers a window of its own choosing, which may skip
           part of the gap since the last automatic run, so it leaves the cursor alone. */
        if ($stats['errors'] === 0 && $startParam === null) {
            $data->last_review_sync = $requestedAt;
            $data->save();
        }

        self::log('Reviews sync finished', $stats);

        return $xml;
    }

    /* Reviews imported before mktr_review_id existed are matched by exact text,
       never by the partial `search` match, so a different review is not skipped. */
    private static function hasLegacyCopy($productId, $email, $text)
    {
        $comments = get_comments(array(
            'type__in'     => self::COMMENT_TYPES,
            'post_id'      => $productId,
            'author_email' => $email,
        ));

        foreach ($comments as $comment) {
            if (trim($comment->comment_content) === trim($text)
                && get_comment_meta($comment->comment_ID, 'mktr_review_id', true) === '') {
                return true;
            }
        }

        return false;
    }

    /* The "verified owner" badge is only shown for a customer who bought the product. */
    private static function boughtProduct($email, $userId, $productId)
    {
        return function_exists('wc_customer_bought_product')
            && wc_customer_bought_product($email, $userId, $productId);
    }

    /* Reviews belong to the product; a variation id or variation SKU resolves to its parent. */
    private static function reviewableProduct($id)
    {
        if ($id <= 0) {
            return 0;
        }

        $type = get_post_type($id);

        if ($type === 'product_variation') {
            $id = (int) wp_get_post_parent_id($id);
            $type = $id > 0 ? get_post_type($id) : false;
        }

        return $type === 'product' ? $id : 0;
    }

    private static function log($message, $context = array())
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Mktr Reviews] ' . $message . ' ' . wp_json_encode($context));
        }
    }
}