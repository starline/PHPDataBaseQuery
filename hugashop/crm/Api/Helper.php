<?php

/**
 * HugaShop - Sell anything
 *
 * @author Andri Huga
 * @version 3.0
 *
 */

namespace HugaShop\Api;

use ReCaptcha\ReCaptcha;
use HugaShop\Api\Database;
use Illuminate\Support\Str;
use Jenssegers\Agent\Agent;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class Helper
{

    private static $cache;

    /**
     * Cache
     * @param ?string $name = null default HugaShop
     * @param int $time Time in seconds. default 0
     */
    public static function cache(?string $name = null, int $time = 0)
    {
        if (is_null($name)) {
            return self::$cache['HugaShop'] ?? self::$cache['HugaShop'] = new FilesystemAdapter('HugaShop', $time, Config::get('api_cache_dir'));
        } else {
            $name = str_replace('\\', '', $name);
            return self::$cache[$name] ?? self::$cache[$name] = new FilesystemAdapter($name, $time, Config::get('api_cache_dir'));
        }
    }


    /**
     * Get the class "basename" of the given Object/Class.
     * Example: Folder\\Folder\\Class to Class
     *
     * @param  string|object  $class
     * @return string
     */
    public static function class_basename($class): string
    {
        $class = is_object($class) ? get_class($class) : $class;
        return basename(str_replace('\\', '/', $class));
    }


    /**
     * Camel to Snake Case
     * @param string $camel_string
     */
    public static function camelToSnakeCase(string $camel_string)
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter();
        return $nameConverter->normalize($camel_string);
    }


    /**
     * Snake to Camel Case
     * @param string $snake_string
     */
    public static function snakeToCamelCase(string $snake_string)
    {
        $nameConverter = new CamelCaseToSnakeCaseNameConverter();
        return $nameConverter->denormalize($snake_string);
    }


    /**
     * Number formmat
     * @param int|float $number
     * @param ?int $precision
     * @param ?string $decimal_point
     * @param ?string $thousands_separator
     */
    public static function numberFormat(int|float|null $number, ?int $precision = 0, ?string $decimal_point = null, ?string $thousands_separator = null)
    {
        $number = $number ?: 0;
        $decimal_point = $decimal_point ?: Settings::getParam('decimals_point');
        $thousands_separator = $thousands_separator ?: Settings::getParam('thousands_separator');

        return number_format($number, $precision, $decimal_point, $thousands_separator);
    }


    /**
     * Formatting date
     * @param $date
     * @param string $format
     *
     */
    public static function dateFormat($date = null, ?string $format = null)
    {
        if (empty($date)) {
            $date = date("Y-m-d");
        }
        $date = new \DateTime($date);
        $date->setTimeZone(new \DateTimeZone(Settings::getParam('timezone')));
        return $date->format(empty($format) ? Settings::getParam('date_format') : $format);
    }


    /**
     * Date to TIME format
     * @param $date
     * @param string $format
     */
    public static function timeFormat($date = null, ?string $format = null)
    {
        if (empty($date)) {
            $date = date("H:i");
        }
        $date = new \DateTime($date);
        $date->setTimeZone(new \DateTimeZone(Settings::getParam('timezone')));
        return $date->format(empty($format) ? 'H:i' : $format);
    }


    /**
     * Convert date
     */
    public static function dateConvert($date, $format, $from_timezone = null, $to_timezone = 'UTC')
    {
        if (is_null($from_timezone)) {
            $from_timezone = Settings::getParam('timezone');
        }
        $date = new \DateTime($date . ' ' . $from_timezone);
        $date->setTimeZone(new \DateTimeZone($to_timezone));
        return $date->format($format);
    }


    /**
     * Get User Agent Info: OS, Browser
     * @link https://github.com/jenssegers/agent/tree/1d91c71bc076a60061e0498216c0caf849eced94
     * @param string $userAgent
     */
    public static function getUserAgentInfo(?string $user_agent = null)
    {

        $Agent = new Agent();
        $ua_info = new \stdClass();

        if (is_null($user_agent)) {
            $Agent->setUserAgent($_SERVER['HTTP_USER_AGENT']);
        } else {
            $Agent->setUserAgent($user_agent);
        }

        $ua_info->device_type =     $Agent->deviceType(); # desktop|phone|tablet|robot|other
        $ua_info->device =          $Agent->device();
        $ua_info->os =              $Agent->platform();
        $ua_info->os_version =      $Agent->version($ua_info->os);
        $ua_info->browser =         $Agent->browser();

        return $ua_info;
    }


    /**
     * Clearing phone number. Clean  - ( ) space 
     * @param $phone
     */
    public static function clearPhoneNummber(String $phone): String
    {

        if (!empty($phone)) {

            // Убираем скобки, тире, пробелы
            $phone = str_replace([' ', '-', '(', ')'], '', $phone);

            // Добавляем +
            if (stripos($phone, "380") === 0) {
                $phone = "+" . $phone;
            }

            // Если вписали номер 0971234567 добавляем +38
            // если вначале 0 (0971234567) и 10 символов и нет +38
            if (stripos($phone, "+38") === false and stripos($phone, "0") === 0 and strlen($phone) == 10) {
                $phone = "+38" . $phone;
            }

            // Если вписали номер 97 123 45 67
            // Если 9 символов
            // Если начинается не на 0
            // если нет +38
            elseif (stripos($phone, "+38") === false and stripos($phone, "0") !== 0 and strlen($phone) == 9) {
                $phone = "+380" . $phone;
            }
        }

        return $phone;
    }


    /**
     * Очищаем цену от лишних знаков
     * @param $price
     */
    public static function clearPrice(String $price): Float
    {
        if (!empty($price)) {

            // Убираем пробелы
            // Убираем тире
            // заменяем , на .
            // Убираем пробел с Google Sheet
            $price = str_replace([' ', '-', ',', ' '], '', $price);
        }

        return floatval($price);
    }


    /**
     * Check messages in SESSION
     * @param $message_type
     */
    public static function getSessionMessage($message_type)
    {
        if (!empty($message_val = Request::getSession($message_type))) {
            Request::deleteSession($message_type);
            return $message_val;
        }
        return;
    }


    /**
     * Формируем название модуля из названия сущности
     * @param $entity_name
     */
    public static function getViewAdmin($entity_name)
    {
        switch ($entity_name) {
            case 'user':
                return 'user';
                break;
            case 'wh_movement':
                return 'warehouse/movement';
                break;
            case 'order':
                return 'order';
                break;
            default:
                return false;
                break;
        }
    }


    /**
     * Get position helper
     * @param string $order ASC|DESC
     */
    public static function getPositions(string $order = 'ASC')
    {
        $position_arr = [];
        if (!empty($positions = Request::post('positions'))) {
            $positions_ids = array_keys($positions);

            if ($order == 'DESC') {
                rsort($positions);
            } else {
                sort($positions);
            }

            $used_position = [];
            foreach ($positions as $i => $position) {
                while (in_array($position, $used_position)) {
                    $position++;
                }
                $used_position[] = $position;
                $position_arr[$positions_ids[$i]] = intval($position);
            }
        }

        return $position_arr;
    }


    /**
     * Get Modules
     *
     * @param string $module_dir
     * @return $modules['module']:{'settings', 'name'}
     */
    public static function getModules(string $module_dir)
    {

        $modules = [];
        $handler = opendir($module_dir);

        while ($dir = readdir($handler)) {
            $dir = preg_replace("/[^A-Za-z0-9]+/", "", $dir);
            if (!empty($dir) && $dir != "." && $dir != ".." && is_dir($module_dir . $dir)) {

                if (is_readable($module_dir . $dir . '/settings.yaml') && $yaml = Yaml::parseFile($module_dir . $dir . '/settings.yaml', Yaml::PARSE_OBJECT_FOR_MAP)) {
                    $yaml->module = $dir;
                    $modules[$dir] = $yaml;
                }
            }
        }

        closedir($handler);
        return $modules;
    }


    /**
     * Get One Module settings
     * @param string $module_name
     * @param string $module_dir
     */
    public static function getModule(string $module_name, string $module_dir)
    {
        if (is_readable($module_dir . "$module_name/settings.yaml") && $yaml = Yaml::parseFile($module_dir . "$module_name/settings.yaml", Yaml::PARSE_OBJECT_FOR_MAP)) {
            $yaml->module = $module_name;
            return $yaml;
        }
        return false;
    }


    /**
     * Прроверяем параметры фильта на пустые значения
     * @param array $filter
     * @param array $params
     */
    public static function checkFilterParams(array $filter, array $params): bool
    {
        foreach ($params as $param) {
            if (isset($filter[$param])) {
                if (empty($filter[$param])) {
                    return false;
                }
            }
        }
        return true;
    }


    /**
     * Преобразовываем массив в GET запрос
     * @param array $params
     */
    public static function paramsToURL(array $params): string
    {
        $query_string = array();
        foreach ($params as $k => $v) {
            $query_string[] = $k . '=' . urlencode($v);
        }
        return join('&', $query_string);
    }


    /**
     * Creat token with different length
     * 
     * @param string $text Max length is 256 characters.
     * @param int $length - max 32 characters
     * @return string Default: md5(uniqid()) - 32 characters.
     */
    public static function makeToken(?string $text = null, ?int $length = null): string
    {
        if (empty($text)) {
            $text = uniqid();
        }

        $hash = md5($text . Config::get('salt'));

        if (!empty($length)) {
            $hash = substr($hash, 0, $length);
        }

        // Сut hash
        return $hash;
    }


    /**
     * Check token for string
     * @param string $text
     * @param string $token
     */
    public static function checkToken(string $text, string $token, ?int $length = null)
    {
        return $token === self::makeToken($text, $length);
    }


    /**
     * Normalize object
     * @param array|object $objData
     * @param array $allowed_fields
     */
    public static function normalizeObjectData(array|object|null $objData, array $allowed_fields = [])
    {
        if (is_null($objData)) {
            return null;
        }

        if (is_array($objData)) {
            $objData_arr = $objData;
        } else {
            $objData_arr[] = $objData;
        }

        foreach ($objData_arr as &$item) {
            foreach ($item as $param_name => $param_val) {
                if (empty($allowed_fields) || in_array($param_name, $allowed_fields)) { # Преобразуем только разрешеные
                    $entity_param = explode("_", $param_name);
                    if (!empty($entity_param[0]) and !empty($entity_param[1])) {

                        $ob_name = $entity_param[0];
                        $ob_param = str_replace($ob_name . '_', '', $param_name);

                        if (empty($item->$ob_name)) {
                            $item->$ob_name = new \stdClass();
                        }

                        $item->$ob_name->$ob_param = $param_val;
                    }
                }
            }
        }

        if (is_array($objData)) {
            return $objData_arr;
        } else {
            return array_shift($objData_arr);
        }
    }


    /**
     * Отладочная информация
     * @param bool $debug
     */
    public static function getCoreStats(bool $debug = false)
    {

        if (!Config::get('php')->debug and !$debug) {
            return;
        }

        $stat =  "<!--\r\n";

        $time_end = hrtime(true);
        $exec_time = $time_end - Request::$time_start;

        if (function_exists('memory_get_peak_usage')) {
            $stat .= "Memory peak usage: " . self::convertBytes(memory_get_peak_usage()) . "\r\n";
        }

        $stat .= "Page generation time: " . round($exec_time / 1e+9, 4) . " seconds\r\n";
        $stat .= "DB queries count: " . Database::getQueryCount() . " pcs\r\n";
        $stat .= "-->";

        return $stat;
    }


    /**
     * Check Capcha
     * @link https://developers.google.com/recaptcha/docs/verify
     */
    public static function checkCaptcha()
    {
        if (empty($rec_resp = Request::post("g-recaptcha-response", 'string'))) {
            return false;
        }

        $recaptcha = new ReCaptcha(Config::get('recaptcha')->private_key);

        // Verify google recaptchia
        $resp = $recaptcha->setExpectedHostname(Config::get('domain'))
            ->verify($rec_resp, $_SERVER["REMOTE_ADDR"]);

        if ($resp->isSuccess()) {
            return true;
        }
        return false;
    }


    /**
     * Make Random String
     * @param int $lenhgt
     */
    public static function makeRandomStr(int $lenhgt = 4): string
    {
        $one = "qwrtpsdfghjklzxcvbnm";
        $two = "eyuioa";
        $string = "";
        for ($i = 0; $i < $lenhgt; $i++) {
            $string .= $one[mt_rand(0, strlen($one) - 1)];
            $string .= $two[mt_rand(0, strlen($two) - 1)];
        }

        return $string;
    }


    /**
     * Make uniq url
     * @param string $model
     * @param object|array $entity
     */
    public static function makeUniqSlug(string $model, object|array $entity)
    {
        $entity = is_array($entity) ? (object) $entity : $entity;
        if (!empty($entity->name) || !empty($entity->url)) {

            $uniqueUrl = $base_url = $entity->url ? Helper::slugEn($entity->url) : Helper::slugEn($entity->name);
            $id = $entity->id ?? null;
            $i = 1;

            while (
                $model::where('url', $uniqueUrl)
                ->where('id', '!=', $id) # исключаем текущий id
                ->exists()
            ) {
                $uniqueUrl = $base_url . '-' . $i++;
            }

            $entity->url = $uniqueUrl;
        }
        return $entity;
    }


    /**
     * Custom Str::slug. With Dot
     */
    public static function slugEn($title, $separator = '-', $dictionary = ['@' => 'at'])
    {
        $title = Str::ascii($title, 'en');

        // Convert all dashes/underscores into separator
        $flip = $separator === '-' ? '_' : '-';

        $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title);

        // Replace dictionary words
        foreach ($dictionary as $key => $value) {
            $dictionary[$key] = $separator . $value . $separator;
        }

        $title = str_replace(array_keys($dictionary), array_values($dictionary), $title);

        // Remove all characters that are not the separator, letters, numbers, or whitespace
        $title = preg_replace('![^' . preg_quote($separator) . '\.\pL\pN\s\.]+!u', '', Str::lower($title));

        // Replace all separator characters and whitespace by a single separator
        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title);

        return trim($title, $separator);
    }


    /**
     * Конвертация байтов в килобайты и мегабайты
     * 1 КБ = 1024 байта.
     * 1 МБ = 1024 килобайта.
     * 1 ГБ = 1024 мегабайта.
     * 1 ТБ = 1024 гигабайта.
     *
     * @param int $size
     * @param string $output
     */
    public static function convertBytes(int $size, ?string $output = null)
    {
        $i = 0;
        while (floor($size / 1024) > 0) {
            $i++;
            $size /= 1024;
        }

        $size = str_replace('.', '.', round($size, 1));
        switch ($i) {
            case 0:
                $unit = 'B';
                break;
            case 1:
                $unit = 'Kb';
                break;
            case 2:
                $unit = 'Mb';
                break;
            case 3:
                $unit = 'Gb';
        }

        switch ($output) {
            case null:
                return $size . ' ' . $unit;
            case 'unit':
                return $unit;
            case 'value':
                return $size;
        }
    }
}
