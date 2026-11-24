<?php
namespace Mktr\Tracker\Helpers;

class FileHelper
{
    private static $fileSystem = null;

    public static function init()
    {
        if (self::$fileSystem === null) {
            global $wp_filesystem;
            if (empty($wp_filesystem)) {
                require_once(ABSPATH . '/wp-admin/includes/file.php');
                WP_Filesystem();
            }
            self::$fileSystem = $wp_filesystem;
        }
        return self::$fileSystem;
    }

    public static function getContents($file)
    {
        $fileSystem = self::init();
        return $fileSystem->get_contents($file);
    }

    public static function putContents($file, $contents, $mode = false)
    {
        $fileSystem = self::init();
        return $fileSystem->put_contents($file, $contents, $mode);
    }

    public static function delete($file)
    {
        $fileSystem = self::init();
        return $fileSystem->delete($file);
    }

    public static function removeDirectory($dir)
    {
        $fileSystem = self::init();
        $dir = rtrim($dir, '/\\');

        if (is_link($dir) || !is_dir($dir)) {
            return false;
        }

        $names = scandir($dir);
        foreach ($names === false ? array() : $names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $path = $dir . '/' . $name;
            if (!is_link($path) && is_dir($path)) {
                self::removeDirectory($path);
            } else {
                wp_delete_file($path);
            }
        }

        return $fileSystem->rmdir($dir);
    }
}