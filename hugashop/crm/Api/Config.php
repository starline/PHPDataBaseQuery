<?php

/**
 * HugaShop - Sell anything
 *
 * @author Andri Huga
 * @version 2.8
 *
 * Класс-обертка для конфигурационного файла с настройками магазина
 * В отличие от класса Settings, Config оперирует низкоуровневыми настройками, например найстройками базы данных.
 *
 */

namespace HugaShop\Api;

use Symfony\Component\Yaml\Yaml;

class Config
{
    // Config file path
    private static string $config_file = 'config.yaml';

    // Defin Project root Path
    private static string $root_dir;

    // Defin CRM dir Path
    private static string $crm_dir;

    // Config params container
    private static array $vars = [];


    /**
     * Записываем настройки файла в $vars
     */
    private static function getInstance()
    {

        if (!empty(self::$vars)) {
            return;
        }


        self::$crm_dir = dirname(__DIR__) . '/';
        self::$root_dir = dirname(dirname(self::$crm_dir)) . '/';

        // Read configs from file
        $config = Yaml::parseFile(self::$crm_dir . self::$config_file, Yaml::PARSE_OBJECT_FOR_MAP);

        // Записываем настройку как переменную класса
        foreach ($config as $var => $value) {
            self::$vars[$var] = $value;
        }

        // Протокол
        $protocol = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';

        self::$vars['protocol'] =                   $protocol;
        self::$vars['host'] =                       rtrim($_SERVER['HTTP_HOST']);
        self::$vars['root_url'] =                   $protocol . '://' . self::$vars['host'];

        self::$vars['root_dir'] =                   self::$root_dir;                                  # Root directory
        self::$vars['templates_dir'] =              self::$root_dir . 'templates/';                   # Directory for Templates

        self::$vars['payment_dir'] =                self::$crm_dir . 'Modules/Payment/';    # Directory for payment modules
        self::$vars['delivery_dir'] =               self::$crm_dir . 'Modules/Delivery/';   # Directory for delivery modules
        self::$vars['notifier_dir'] =               self::$crm_dir . 'Modules/Notifier/';   # Directory for notifier modules
        self::$vars['extension_dir'] =              self::$crm_dir . 'Extensions/';         # Directory for Extensions

        self::$vars['import_files_dir'] =           self::$root_dir . 'public/files/imports/';        # Directory for import files
        self::$vars['export_files_dir'] =           self::$root_dir . 'public/files/exports/';        # Directory for export files

        self::$vars['log_dir'] =                    self::$root_dir . 'var/log/';                     # Directory for Logs
        self::$vars['cache_dir'] =                  self::$root_dir . 'var/cache/';                   # Directory for Cache
        self::$vars['api_cache_dir'] =              self::$root_dir . 'var/cache/hugashop/';          # Directory for API Cache
        self::$vars['compiled_dir'] =               self::$root_dir . 'var/compiled/';                # Directory for Compiled templates

        self::$vars['max_upload_filesize'] =        self::getMaxUploadFilesize();

        self::$vars['images_resized_url'] =         'files/resize/';
        self::$vars['images_originals_dir'] =       self::$root_dir . 'public/files/originals/';
        self::$vars['images_resized_dir'] =         self::$root_dir . 'public/files/resize/';
        self::$vars['images_brands_dir'] =          self::$root_dir . 'public/files/brands/';
        self::$vars['images_watermark_file'] =      self::$root_dir . 'public/files/watermark/watermark.png';

        self::$vars['now'] = time(); # Now time from 1970
    }


    /**
     * Magic method get current var
     * Example: Config::get()->root_url;
     * Example: Config::get('database')->user;
     * Example: Config::get()->database->user;
     * @param string $name
     */
    public static function get(?string $param_name = null)
    {
        self::getInstance();

        if (is_null($param_name)) {
            return (object) self::$vars;
        }

        if (isset(self::$vars[$param_name])) {
            return self::$vars[$param_name];
        } else {
            return null;
        }
    }


    /**
     * Magic method
     * Write in to config.yaml
     * @param string $param_name
     * @param string $value
     */
    public static function set(string $param_name, string $value): void
    {
        self::getInstance();

        # Запишем конфиги
        if (isset(self::$vars[$param_name])) {
            $config = Yaml::parseFile(self::$crm_dir . self::$config_file, Yaml::PARSE_OBJECT_FOR_MAP);
            $config->$param_name = $value;

            // Write .yaml
            file_put_contents(self::$crm_dir . self::$config_file, $config);

            self::$vars[$param_name] = $value;
        }
    }


    /**
     * Max upload filesize
     * @return int bytes
     */
    private static function getMaxUploadFilesize(): int
    {
        $max_upload = (int)(ini_get('upload_max_filesize'));                # mb
        $max_post = (int)(ini_get('post_max_size'));                        # mb
        $memory_limit = (int)(ini_get('memory_limit'));                     # mb
        return min($max_upload, $max_post, $memory_limit) * 1024 * 1024;    # bytes
    }
}
