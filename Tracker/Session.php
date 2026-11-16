<?php
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker;

class Session
{
    private static $init = null;
    private static $uid = null;

    private $data = [];
    private $org = [];
    private $insert = true;
    public static $saveCookie = false;

    private $isDirty = false;

    public static function init() {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    public static function data() {
        return self::init()->data;
    }

    public function remove($key) {
        if (isset($this->data[$key])) { unset($this->data[$key]); }
        return $this;
    }

    /* Cache compatible mode: the visitor id is created in the browser, the server only
       writes the cookie itself when it has to store something and the browser had none. */
    private static $cookiePending = false;
    private static $hintSent = false;

    /** Off while syncEvents runs, so the endpoint does not tell the browser to call it again. */
    public static $syncHint = true;

    const UID_COOKIE = '__sm__uid';
    const SYNC_COOKIE = '__mktr_sync';

    public static function cacheMode() {
        return Config::getCacheMode() === 1;
    }

    /** Only ids we could have issued: no long or odd values used as a table key. */
    public static function isValidUid($uid) {
        return is_string($uid) && preg_match('/^[A-Za-z0-9-]{8,50}$/', $uid) === 1;
    }

    public static function getUid() {
        if (self::$uid === null) {
            $cookie = isset($_COOKIE[self::UID_COOKIE]) ? sanitize_text_field(wp_unslash($_COOKIE[self::UID_COOKIE])) : '';

            if (self::isValidUid($cookie)) {
                self::$uid = $cookie;
            } else {
                /* uniqid() is a timestamp, so ids were guessable; the session holds order and email events. */
                self::$uid = wp_generate_uuid4();

                if (self::cacheMode()) {
                    /* No Set-Cookie on page views: full-page caches skip any response that has one. */
                    self::$cookiePending = true;
                } else if (!headers_sent()) {
                    setcookie(self::UID_COOKIE, self::$uid, strtotime('+365 days'), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
                } else {
                    self::$saveCookie = true;
                }
            }
        }
        return self::$uid;
    }

    /** Cache mode: the browser had no id yet, but this request stores events for it. */
    private static function persistUid() {
        if (self::$cookiePending && !headers_sent()) {
            self::$cookiePending = false;
            setcookie(self::UID_COOKIE, self::getUid(), strtotime('+365 days'), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        }
    }

    /**
     * Cache mode: a short cookie the page script can read, telling it that events are
     * waiting, so it calls syncEvents only then instead of on every page view.
     */
    private static function hintSync($key, $value) {
        if (!self::$syncHint || self::$hintSent || empty($value) || !isset(Events::observerGetEvents[$key])) {
            return;
        }
        if (!headers_sent()) {
            self::$hintSent = true;
            setcookie(self::SYNC_COOKIE, '1', 0, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false);
        }
    }

    public static function clearSyncHint() {
        if (isset($_COOKIE[self::SYNC_COOKIE]) && !headers_sent()) {
            setcookie(self::SYNC_COOKIE, '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false);
        }
    }

    public function __construct() {
       
        $uid = self::getUid();
        $table_name = Config::tableName();

        $prep = Config::db()->prepare( "SELECT `data` FROM `".$table_name."` WHERE `uid` = %s", $uid );
        $data = Config::db()->get_var( $prep );
        $err = Config::db()->last_error;

        if (!empty($err) && strpos($err, $table_name) !== false && strpos($err, "doesn't exist") !== false ) {
            self::up();
        }
        $this->insert = $data === null;
        $this->org = $data ? unserialize($data) : [];
        $this->data = $this->org;
    }

    public static function set($key, $value = null) {
        if (self::cacheMode()) {
            self::persistUid();
            self::hintSync($key, $value);
        }
        if ($value === null) {
            self::init()->remove($key);
        } else {
            self::init()->data[$key] = $value;
        }
        self::init()->isDirty = true;
    }

    public static function get($key, $default = null) {
        if (isset(self::init()->data[$key])) {
            return self::init()->data[$key];
        } else {
            return $default;
        }
    }

    public static function save() {
        if (self::init()->isDirty) {
            $uid = self::getUid();
            $table_name = Config::tableName();
            if (!empty(self::init()->data)) {
                $data = [ 'data' => serialize(self::init()->data), 'expire' => gmdate('Y-m-d H:i:s', strtotime('+2 day')) ];
                if (self::init()->insert) {
                    $data['uid'] = $uid;
                    $del = Config::db()->prepare("DELETE FROM `" . $table_name . "` WHERE `uid` = %s", $uid);
                    Config::db()->query($del);
                    Config::db()->insert($table_name, $data);
                } else {
                    Config::db()->update($table_name, $data, array('uid' => $uid));
                }
                self::init()->org = self::init()->data;
            } else {
                $prep = Config::db()->prepare("DELETE FROM `" . $table_name . "` WHERE `uid` = %s", $uid);
                Config::db()->query($prep);
                self::init()->org = [];
                self::init()->data = [];
            }

            self::clearIfExipire();
            self::init()->isDirty = false;
            return true;
        }

        return false;
    }

    public static function clearIfExipire()
    {
        $table_name = Config::tableName();
        $expire_at = gmdate('Y-m-d H:i:s', time());
        $prep = Config::db()->prepare("DELETE FROM `". $table_name ."` WHERE `expire` < %s", $expire_at);
        Config::db()->query($prep);
    }

    public static function clear() {
        self::init()->data = [];
        self::init()->isDirty = true;
    }

    public function __destruct() {
        if ($this->isDirty) {
            $this->save();
        }
    }

    public static function sessionSet($name, $data, $key = null)
    {
        $add = self::get($name);
        if ($key === null) { $n = '';
            for ($i = 0; $i < 5; ++$i) { $n .= wp_rand(0, 9); }
            $add[time() . $n] = $data;
        } else { $add[$key] = $data; }
        self::set($name, $add);
    }
    
    public static function up()
    {
        $table_name = Config::tableName();
        if (Config::db()->get_var("SHOW TABLES LIKE '".$table_name."'") != $table_name) {
            $charset_collate = Config::db()->get_charset_collate();

            $sql = "CREATE TABLE `$table_name` (
                `uid` varchar(50) NOT NULL,
                `data` longtext, 
                `expire` datetime,
                PRIMARY KEY  (uid)
              ) $charset_collate;";

            require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
            dbDelta( $sql );
        }
    }

    public static function down() {
        $table_name = Config::tableName();
        Config::db()->query("DROP TABLE IF EXISTS $table_name;");
    }
}
