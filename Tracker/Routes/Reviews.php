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

    /* Seconds allowed for the product_reviews call; the default 3 is too short for a long window. */
    const API_TIMEOUT = 30;

    /* A review that keeps failing stops holding the cursor after this many attempts. */
    const MAX_ATTEMPTS = 3;

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
            if ($t === false) {
                return array('status' => 'Incorrect Start Date start_date - ' . $startParam);
            }
        } elseif ($lastSync > 0) {
            $t = $lastSync - DAY_IN_SECONDS;
        } else {
            /* First run after the update: older reviews are imported with a manual start_date.
               The start is saved before the request and reused until the cursor moves, so a
               review that fails on the first run is still inside the window on the next one. */
            $t = (int) $data->review_first_from;
            if ($t <= 0) {
                $t = strtotime('-1 day');
                $data->review_first_from = $t;
                $data->save();
            }
        }

        $failures = is_array($data->review_failures) ? $data->review_failures : array();
        $failedNow = array();

        /* Reviews whose comment was inserted but could neither get its meta nor be deleted. */
        $incomplete = is_array($data->review_incomplete) ? $data->review_incomplete : array();

        $requestedAt = time();
        $o = Api::send("product_reviews", array(
            't' => $t
        ), false, self::API_TIMEOUT);

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
            'repaired'         => 0,
            'errors'           => 0,
            'given_up'         => 0,
        );

        if ((int) $stats['http'] !== 200) {
            $stats['errors']++;
            self::log('Unexpected HTTP status', $stats);
            self::saveStats($data, $stats);
            return false;
        }

        $body = $o ? $o->getContent() : '';
        $xml = @simplexml_load_string($body, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xml === false) {
            $stats['errors']++;
            self::log('XML parse failed', $stats);
            self::saveStats($data, $stats);
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

            $failKey = $reviewId !== '' ? $reviewId : md5($productId . '|' . $email . '|' . $text);

            /* theMarketer rates out of 10. A missing rating stays 0, which WooCommerce
               leaves out of the product average, instead of counting as one star. */
            $rawRating = (int) $value->rating;
            $rating = $rawRating > 0 ? max(1, min(5, (int) round($rawRating / 2))) : 0;

            $user = get_user_by('email', $email);

            /* A comment left without its meta on an earlier run is repaired before the
               duplicate check, which would otherwise take it for an old import. Only the
               meta is written, so a review the merchant moved to trash or spam stays there. */
            if (isset($incomplete[$failKey])) {
                $leftover = get_comment((int) $incomplete[$failKey]);

                if ($leftover) {
                    if (self::saveMeta($leftover->comment_ID, self::buildMeta($rating, $reviewId, $email, $user, $productId))) {
                        unset($incomplete[$failKey]);
                        self::clearProductCache($productId);
                        $stats['repaired']++;
                    } else {
                        self::failed($stats, $failures, $failedNow, $failKey);
                        self::log('update_comment_meta failed on repair', array(
                            'review_id'  => $reviewId,
                            'comment_id' => $leftover->comment_ID,
                        ));
                    }
                    continue;
                }

                /* The comment is gone, so the review is imported again. */
                unset($incomplete[$failKey]);
            }

            $dup = 0;
            if ($reviewId !== '') {
                /* WooCommerce leaves product reviews out of get_comments() unless the type is asked for. */
                $dup = (int) get_comments(array(
                    'type__in'   => self::COMMENT_TYPES,
                    'meta_key'   => 'mktr_review_id',
                    'meta_value' => $reviewId,
                    'status'     => 'any',
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

            if ($user) {
                $add['user_id'] = $user->ID;
            }

            $comment_id = wp_insert_comment($add);

            if (!$comment_id) {
                self::failed($stats, $failures, $failedNow, $failKey);
                self::log('wp_insert_comment failed', array(
                    'review_id'  => $reviewId,
                    'product_id' => $productId,
                    'email'      => $email,
                ));
                continue;
            }

            /* A review left without its rating or external id would be skipped as a
               duplicate on the next run, so it is removed and retried. When the delete
               fails too, the comment id is kept and the meta is repaired on the next run. */
            if (!self::saveMeta($comment_id, self::buildMeta($rating, $reviewId, $email, $user, $productId))) {
                if (!wp_delete_comment($comment_id, true)) {
                    $incomplete[$failKey] = (int) $comment_id;
                }
                self::failed($stats, $failures, $failedNow, $failKey);
                self::log('update_comment_meta failed', array(
                    'review_id'  => $reviewId,
                    'product_id' => $productId,
                    'email'      => $email,
                    'kept'       => isset($incomplete[$failKey]),
                ));
                continue;
            }

            self::clearProductCache($productId);

            $stats['inserted']++;
        }

        /* Only reviews that failed in this window are kept, so the list does not grow.
           A manual run adds its failures without dropping the automatic run's counts. */
        $data->review_failures = $startParam === null ? $failedNow : array_replace($failures, $failedNow);
        $data->review_incomplete = $incomplete;

        /* The next run starts one day before the cursor, so the cursor only moves
           when every review was handled; otherwise the same window is asked again.
           A manual start_date covers a window of its own choosing, which may skip
           part of the gap since the last automatic run, so it leaves the cursor alone. */
        if ($stats['errors'] === 0 && $startParam === null) {
            $data->last_review_sync = $requestedAt;
            Data::del('review_first_from');
        }

        self::log('Reviews sync finished', $stats);
        self::saveStats($data, $stats);

        return $xml;
    }

    /* Counts a failed review. After MAX_ATTEMPTS it no longer holds the cursor, so one
       review the shop cannot store (for example an emoji on a utf8 table) does not stop
       the sync; it is still tried again while it stays inside the requested window. */
    private static function failed(&$stats, $failures, &$failedNow, $key)
    {
        $attempts = (isset($failures[$key]) ? (int) $failures[$key] : 0) + 1;
        $failedNow[$key] = $attempts;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $stats['given_up']++;
        } else {
            $stats['errors']++;
        }
    }

    private static function buildMeta($rating, $reviewId, $email, $user, $productId)
    {
        $meta = array(
            'rating'   => $rating,
            'verified' => self::boughtProduct($email, $user ? $user->ID : 0, $productId) ? 1 : 0,
        );
        if ($reviewId !== '') {
            $meta['mktr_review_id'] = $reviewId;
        }
        return $meta;
    }

    /* update_comment_meta() also returns false when the value is already there,
       so the stored values are read back instead. */
    private static function saveMeta($commentId, $meta)
    {
        $saved = true;
        foreach ($meta as $metaKey => $metaValue) {
            update_comment_meta($commentId, $metaKey, $metaValue);
            if ((string) get_comment_meta($commentId, $metaKey, true) !== (string) $metaValue) {
                $saved = false;
            }
        }
        return $saved;
    }

    private static function clearProductCache($productId)
    {
        if (class_exists('\WC_Comments')) {
            \WC_Comments::clear_transients($productId);
        }
    }

    /* The last run's result is kept in Data, so it shows in the Cron route response
       without WP_DEBUG. */
    private static function saveStats($data, $stats)
    {
        $stats['finished_at'] = gmdate('Y-m-d H:i:s');
        $data->last_review_stats = $stats;
        $data->save();
    }

    /* Reviews imported before mktr_review_id existed are matched by exact text,
       never by the partial `search` match, so a different review is not skipped. */
    private static function hasLegacyCopy($productId, $email, $text)
    {
        $comments = get_comments(array(
            'type__in'     => self::COMMENT_TYPES,
            'post_id'      => $productId,
            'author_email' => $email,
            'status'       => 'any',
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
        // Goes to WooCommerce > Status > Logs (source "themarketer-reviews") instead of the PHP error log.
        if (defined('WP_DEBUG') && WP_DEBUG && function_exists('wc_get_logger')) {
            wc_get_logger()->debug($message . ' ' . wp_json_encode($context), array('source' => 'themarketer-reviews'));
        }
    }
}