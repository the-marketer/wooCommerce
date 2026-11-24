<?php
/** @noinspection SpellCheckingInspection */
/**
 * @copyright   Copyright (c) 2023 TheMarketer.com
 * @project     TheMarketer.com
 * @website     https://themarketer.com/
 * @author      Alexandru Buzica (EAX LEX S.R.L.) <b.alex@eax.ro>
 * @license     https://opensource.org/licenses/osl-3.0.php - Open Software License (OSL 3.0)
 * @docs        https://themarketer.com/resources/api
 */

namespace Mktr\Tracker;

use Mktr\Tracker\Helpers\FileHelper;

class FileSystem
{
    private static $path = null;
    private static $lastPath = null;
    private static $status = array();
    private static $useRoot = false;
    private static $storagePath = null;

    const STORAGE = 'Storage/';
    const STORAGE_HTACCESS = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";

    private static $init = null;

    public static function init() {
        if (self::$init == null) {
            self::$init = new self();
        }
        return self::$init;
    }

    /** @noinspection PhpUnused */
    public static function setWorkDirectory($name = self::STORAGE)
    {
        if ($name === self::STORAGE && !self::$useRoot)
        {
            self::$path = self::storagePath();
        } elseif ($name != 'base' && !self::$useRoot)
        {
            self::$path = Config::getDir() . $name;
        } else {
            self::$path = ABSPATH;
        }

        return self::init();
    }

    /** @noinspection PhpUnused */
    public static function writeFile($fName, $content)
    {
        if (!is_dir(self::getPath())) {
            wp_mkdir_p(self::getPath());
        }

        if (!self::isInsideWorkDirectory($fName)) {
            self::$status[] = [
                'path' => self::getPath(),
                'fileName' => $fName,
                'fullPath' => null,
                'status' => false
            ];
            return self::init();
        }

        self::$lastPath = self::getPath() . $fName;

        if (!is_dir(dirname(self::$lastPath))) {
            wp_mkdir_p(dirname(self::$lastPath));
        }

        if (self::$storagePath !== null && self::getPath() === self::$storagePath) {
            self::protectStorage();
        }

        $result = FileHelper::putContents(self::$lastPath, $content);

        self::$status[] = [
            'path' => self::getPath(),
            'fileName' => $fName,
            'fullPath' => self::getPath() . $fName,
            'status' => (bool) $result
        ];

        return self::init();
    }

    public static function storagePath()
    {
        if (self::$storagePath === null) {
            $uploads = wp_upload_dir(null, false);
            self::$storagePath = trailingslashit(wp_normalize_path($uploads['basedir']))
                . 'mktr-' . substr(wp_hash('mktr_storage'), 0, 32) . '/';
        }

        return self::$storagePath;
    }

    private static function protectStorage()
    {
        $dir = self::storagePath();

        if (is_dir($dir) && !file_exists($dir . 'index.php')) {
            FileHelper::putContents($dir . 'index.php', "<?php\n");
        }

        if (is_dir($dir) && !file_exists($dir . '.htaccess')) {
            FileHelper::putContents($dir . '.htaccess', self::STORAGE_HTACCESS);
        }
    }

    public static function migrateLegacyStorage()
    {
        $legacy = rtrim(Config::getDir() . self::STORAGE, '/');

        if (is_link($legacy)) {
            wp_delete_file($legacy);
            return;
        }

        if (!is_dir($legacy)) {
            return;
        }

        $data = $legacy . '/data.json';
        $target = self::storagePath();

        if (!is_link($data) && is_file($data) && !file_exists($target . 'data.json')) {
            $content = FileHelper::getContents($data);
            if (is_string($content) && $content !== '') {
                if (!is_dir($target)) {
                    wp_mkdir_p($target);
                }
                self::protectStorage();
                FileHelper::putContents($target . 'data.json', $content);
            }
        }

        FileHelper::removeDirectory($legacy);
    }

    /** @noinspection PhpUnused */
    public static function rFile($fName, $mode = "rb")
    {
        self::$lastPath = self::getPath() . $fName;

        if (self::fileExists($fName) && filesize(self::$lastPath) > 0) {
            $contents = FileHelper::getContents(self::$lastPath);
            return $contents !== false ? $contents : '';
        } else {
            return '';
        }
    }

    /** @noinspection PhpUnused */
    public static function readFile($fName, $mode = "rb")
    {
        return self::rFile($fName, $mode);
    }

    /** @noinspection PhpUnused */
    public static function fileExists($fName)
    {
        return self::isInsideWorkDirectory($fName) && file_exists(self::getPath() . $fName);
    }

    private static function isInsideWorkDirectory($fName)
    {
        if (!is_string($fName) || $fName === '' || strpos($fName, "\0") !== false) {
            return false;
        }

        $name = wp_normalize_path($fName);

        if (preg_match('#(^|/)\.\.(/|$)#', $name) || $name[0] === '/' || preg_match('#^[A-Za-z]:#', $name)) {
            return false;
        }

        $base = realpath(self::getPath());
        if ($base === false) {
            return false;
        }
        $base = trailingslashit(wp_normalize_path($base));

        $target = self::getPath() . $fName;
        while (!file_exists($target) && dirname($target) !== $target) {
            $target = dirname($target);
        }
        $real = realpath($target);
        if ($real === false) {
            return false;
        }
        $real = wp_normalize_path($real);

        return $real . '/' === $base || strpos($real, $base) === 0;
    }

    /** @noinspection PhpUnused */
    public static function deleteFile($fName)
    {
        self::$lastPath = self::getPath() . $fName;

        if(self::fileExists($fName))
        {
            wp_delete_file(self::$lastPath);
        }
        return true;
    }

    public static function getPath()
    {
        if (self::$path == null)
        {
            self::setWorkDirectory();
        }
        return self::$path;
    }

    /** @noinspection PhpUnused */
    public static function getLastPath()
    {
        return self::$lastPath;
    }

    public static function getStatus()
    {
        return self::$status;
    }
}
