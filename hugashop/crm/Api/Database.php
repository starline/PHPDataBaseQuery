<?php

/**
 * HugaShop - Selling anything
 *
 * @author Andri Huga
 * @version 2.7
 *
 * Класс для доступа к базе данных
 *
 */

namespace HugaShop\Api;

use mysqli;

class Database
{
    private static $mysqli;
    private static $res;
    private static $query_count = 0;
    private $last_quert;

    public static $current_table;


    /**
     * At firts, connect to Database
     */
    public function __construct()
    {
        self::connect();
    }


    /**
     * At the end, disconnect database
     */
    public function __destruct()
    {
        self::disconnect();
    }


    /**
     * Подключение к базе данных
     */
    public static function connect()
    {

        // При повторном вызове возвращаем существующий линк
        if (!empty(self::$mysqli)) {
            return self::$mysqli;
        }

        // Иначе устанавливаем соединение
        else {
            self::$mysqli = new mysqli(Config::get('database')->server, Config::get('database')->user, Config::get('database')->password, Config::get('database')->name);
        }

        // Выводим сообщение, в случае ошибки
        if (self::$mysqli->connect_error) {
            trigger_error("Could not connect to the database: " . self::$mysqli->connect_error, E_USER_WARNING);
            return false;
        }

        // Или настраиваем соединение
        else {
            if (Config::get('database')->charset) {
                self::$mysqli->query('SET NAMES ' . Config::get('database')->charset);
            }
            if (Config::get('database')->sql_mode) {
                self::$mysqli->query('SET SESSION SQL_MODE = "' . Config::get('database')->sql_mode . '"');
            }
        }

        return self::$mysqli;
    }


    /**
     * Закрываем подключение к базе данных
     */
    public static function disconnect()
    {
        if (!@self::$mysqli->close()) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Request to Database
     * Обазятелен первый аргумент - текст запроса.
     * При указании других аргументов автоматически выполняется placehold() для запроса с подстановкой этих аргументов
     */
    public function query()
    {
        // Frees the memory associated with a result
        if (is_object(self::$res)) {
            self::$res->free();
        }

        // Count the number of requests per session
        self::$query_count += 1;

        $args = func_get_args();
        $query = call_user_func_array([$this, 'placehold'], $args);

        // Save last query
        $this->last_quert = $query;

        mysqli_report(MYSQLI_REPORT_ERROR); # disable exceptions
        self::$res = @self::$mysqli->query($query); # disable warning

        if (self::$res === false) {
            if (self::$mysqli->errno === 1146) { # table doesn't exist
                $this->createTable(); # create table
            }

            if (self::$mysqli->errno === 1054) { # Unknown column
                $this->createColumns(); # create column
            }
        }

        return self::$res;
    }


    /**
     * Кол-во запросов к БД
     */
    public static function getQueryCount()
    {
        return self::$query_count;
    }


    /**
     * Возвращает результаты запроса.
     * Необязательный второй аргумент указывает какую колонку возвращать вместо всего массива колонок
     * @param string $field
     */
    public function results(?string $field = null): array
    {
        $results = [];

        if (self::$res === false) {
            trigger_error(self::$mysqli->error, E_USER_WARNING);
            return $results;
        }

        if (self::$res->num_rows == 0) {
            return $results;
        }

        while ($row = self::$res->fetch_object()) {
            if (empty($field)) {
                $results[] = $row;
            } else {

                // If it has data
                if (isset($row->$field)) {
                    $results[] = $row->$field;
                }

                // If doesn't have Data or Column
                else {
                    $results[] = null;
                }
            }
        }
        return $results;
    }


    /**
     * Возвращает первый результат запроса.
     * Необязательный второй аргумент указывает какую колонку возвращать вместо всего массива колонок
     * @param string $field
     */
    public function result(?string $field = null) # all types
    {
        if (self::$res === false) {
            trigger_error(self::$mysqli->error, E_USER_WARNING);
            return false;
        }

        // Get 1-st row
        // If empty result, return NULL
        // If error, return FALSE
        $row = self::$res->fetch_object();

        // If $field = null, return all data Table
        if (empty($field)) {
            return $row;
        } else {

            // If it has data
            if (isset($row->$field)) {
                return $row->$field;
            }

            // If doesn't have Data or Column
            else {
                return null;
            }
        }
    }


    /**
     * Get last insert ID
     */
    public function getInsertId()
    {
        if (self::$res === false) {
            trigger_error(self::$mysqli->error, E_USER_WARNING);
            return false;
        }
        return (self::$mysqli->insert_id != 0) ? self::$mysqli->insert_id : false;
    }


    /**
     * Get Response
     */
    public function getResponse()
    {
        return self::$res;
    }


    /**
     * Возвращает количество выбранных строк
     */
    public function numRows()
    {
        return self::$res->num_rows;
    }


    /**
     * Возвращает количество затронутых строк
     */
    public function affectedRows()
    {
        return self::$mysqli->affected_rows;
    }


    /**
     * Make placeholder .
     * Example: $query = Database::placehold('SELECT name FROM product WHERE id=?', $id);
     */
    public static function placehold()
    {
        $args = func_get_args();
        $tmpl = array_shift($args);

        // Заменяем все __ на префикс, но только НЕобрамленные кавычками
        $tmpl = preg_replace('/([^"\'0-9a-z_])__([a-z_]+[^"\'])/i', "\$1" . Config::get('database')->prefix . "\$2", $tmpl);

        if (!empty($args)) {
            $result = self::sql_placeholder_ex($tmpl, $args, $error);
            if ($result === false) {
                $error = "Placeholder substitution error. Diagnostics: \"$error\"";
                trigger_error($error, E_USER_WARNING);
                return false;
            }
            return $result;
        } else {
            return $tmpl;
        }
    }


    /**
     * Компиляция плейсхолдера
     * @param string $tmpl
     */
    private static function sql_compile_placeholder(string $tmpl)
    {
        $compiled = [];
        $p = 0;     # текущая позиция в строке
        $i = 0;     # счетчик placeholder-ов
        $has_named = false;
        while (false !== ($start = $p = strpos($tmpl, '?', $p))) {

            // Определяем тип placeholder-а.
            switch ($c = substr($tmpl, ++$p, 1)) {
                case '%': # ?% Object
                case '@': # ?@ array
                case '#': # ?# Constanta
                    $type = $c;
                    ++$p;
                    break;
                default:
                    $type = '';
                    break;
            }

            // Проверяем, именованный ли это placeholder: "?keyname"
            if (preg_match('/^((?:[^\s[:punct:]]|_)+)/', substr($tmpl, $p), $pock)) {
                $key = $pock[1];
                if ($type != '#') {
                    $has_named = true;
                }
                $p += strlen($key);
            } else {
                $key = $i;
                if ($type != '#') {
                    $i++;
                }
            }

            // Сохранить запись о placeholder-е.
            $compiled[] = array($key, $type, $start, $p - $start);
        }
        return array($compiled, $tmpl, $has_named);
    }


    /**
     * Выполнение плейсхолдера
     * @param string|array $tmpl
     * @param $args
     * @param $errormsg
     */
    private static function sql_placeholder_ex(string|array $tmpl, $args, &$errormsg)
    {
        // Запрос уже разобран?.. Если нет, разбираем.
        if (is_array($tmpl)) {
            $compiled = $tmpl;
        } else {
            $compiled = self::sql_compile_placeholder($tmpl);
        }

        list($compiled, $tmpl, $has_named) = $compiled;

        // Если есть хотя бы один именованный placeholder, используем
        // первый аргумент в качестве ассоциативного массива.
        if ($has_named) {
            $args = @$args[0];
        }

        // Выполняем все замены в цикле.
        $p =     0;         # текущее положение в строке
        $out =   '';        # результирующая строка
        $error = false;     # были ошибки?

        foreach ($compiled as $num => $e) {
            list($key, $type, $start, $length) = $e;

            // Pre-string.
            $out .= substr($tmpl, $p, $start - $p);
            $p = $start + $length;

            $repl = '';     # текст для замены текущего placeholder-а
            $errmsg = '';   # сообщение об ошибке для этого placeholder-а

            do {

                // Это placeholder-константа
                if ($type === '#') {
                    $repl = @constant($key);
                    if (null === $repl) {
                        $error = $errmsg = "UNKNOWN_CONSTANT_$key";
                    }
                    break;
                }

                // Обрабатываем ошибку.
                if (!isset($args[$key]) and !is_null($args[$key])) {
                    $error = $errmsg = "UNKNOWN_PLACEHOLDER_$key";
                    break;
                }

                // Вставляем значение в соответствии с типом placeholder-а.
                $a = $args[$key];
                if ($type === '') {

                    // Скалярный placeholder.
                    if (is_array($a)) {
                        $error = $errmsg = "NOT_A_SCALAR_PLACEHOLDER_$key";
                        break;
                    }

                    $repl = is_numeric($a) ? str_replace(',', '.', $a) : "'" . self::escape($a) . "'";
                    break;
                }


                // Иначе это массив или список.
                if (is_object($a)) {
                    $a = get_object_vars($a);
                }

                if (!is_array($a)) {
                    $error = $errmsg = "NOT_AN_ARRAY_PLACEHOLDER_$key";
                    break;
                }

                // Это список. Array
                if ($type === '@') {
                    foreach ($a as $v) {
                        if (is_null($v)) {
                            $r = 'NULL';
                        } else {
                            if (is_numeric($v)) {
                                $r = @self::escape($v);
                            } else {
                                $r = "'" . @self::escape($v) . "'";
                            }
                        }

                        $repl .= ($repl === '' ? '' : ', ') . $r;
                    }
                }

                // Это набор пар ключ=>значение.
                elseif ($type === '%') {
                    $lerror = array();
                    foreach ($a as $k => $v) {
                        if (!is_string($k)) {
                            $lerror[$k] = "NOT_A_STRING_KEY_{$k}_FOR_PLACEHOLDER_$key";
                        } else {
                            $k = preg_replace('/[^a-zA-Z0-9_]/', '_', $k);
                        }

                        if (is_null($v)) {
                            $r = "=NULL";
                        } else {
                            if (is_int($v)) {
                                $r = "=" . @self::escape($v);
                            } else {
                                $r = "='" . @self::escape($v) . "'";
                            }
                        }

                        $repl .= ($repl === '' ? '' : ', ') . $k . $r;
                    }

                    // Если была ошибка, составляем сообщение.
                    if (count($lerror)) {
                        $repl = '';
                        foreach ($a as $k => $v) {
                            if (isset($lerror[$k])) {
                                $repl .= ($repl === '' ? "" : ", ") . $lerror[$k];
                            } else {
                                $k = preg_replace('/[^a-zA-Z0-9_-]/', '_', $k);
                                $repl .= ($repl === '' ? '' : ', ') . $k . '=?';
                            }
                        }
                        $error = $errmsg = $repl;
                    }
                }
            } while (false);

            if ($errmsg) {
                $compiled[$num]['error'] = $errmsg;
            }
            if (!$error) {
                $out .= $repl;
            }
        }
        $out .= substr($tmpl, $p);

        // Если возникла ошибка, переделываем результирующую строку
        // в сообщение об ошибке (расставляем диагностические строки
        // вместо ошибочных placeholder-ов).
        if ($error) {
            $out = '';
            $p = 0; # текущая позиция
            foreach ($compiled as $num => $e) {
                list($key, $type, $start, $length) = $e;
                $out .= substr($tmpl, $p, $start - $p);
                $p = $start + $length;
                if (isset($e['error'])) {
                    $out .= $e['error'];
                } else {
                    $out .= substr($tmpl, $start, $length);
                }
            }

            // Последняя часть строки.
            $out .= substr($tmpl, $p);
            $errormsg = $out;
            return false;
        } else {
            $errormsg = false;
            return $out;
        }
    }


    /**
     * Экранирование
     * @param $str
     */
    public static function escape($str)
    {
        self::connect();
        return self::$mysqli->real_escape_string($str);
    }


    /**
     * Создаем бэкап всех таблиц базы данных.
     * Сохраняем в вайл
     * @param $filename
     */
    public static function dump($filename)
    {
        $h = fopen($filename, 'w');

        // Выбираем все таблицы
        $q = self::placehold("SHOW FULL TABLES LIKE '__%';");
        $result = self::$mysqli->query($q);

        while ($row = $result->fetch_row()) {
            if ($row[1] == 'BASE TABLE') {
                self::dumpTable($row[0], $h);
            }
        }

        fclose($h);
    }


    /**
     * Восстанавливаем БД из файла
     * @param $filename
     */
    public static function restore($filename)
    {
        $templine = '';
        $h = fopen($filename, 'r');

        // Loop through each line
        if ($h) {
            while (!feof($h)) {
                $line = fgets($h);

                // Only continue if it's not a comment
                if (substr($line, 0, 2) != '--' && $line != '') {

                    // Add this line to the current segment
                    $templine .= $line;

                    // If it has a semicolon at the end, it's the end of the query
                    if (substr(trim($line), -1, 1) == ';') {

                        // Fix date '0000-00-00 00:00:00' to 1987-01-01 00:00:00
                        $templine = str_replace('0000-00-00 00:00:00', '1970-01-01 00:00:00', $templine);

                        // Perform the query
                        self::$mysqli->query($templine) or print('Error performing query \'<b>' . $templine . '</b>\': ' . self::$mysqli->error . '<br/><br/>');

                        // Reset temp variable to empty
                        $templine = '';
                    }
                }
            }
        }
        fclose($h);
    }


    /**
     * Dump of table
     */
    private static function dumpTable($table, $h)
    {
        $sql = "SELECT * FROM `$table`;";
        $result = self::$mysqli->query($sql);

        if ($result) {
            fwrite($h, "/* Data for table $table */\n");
            fwrite($h, "TRUNCATE TABLE `$table`;\n");

            $num_rows = $result->num_rows;
            $num_fields = self::$mysqli->field_count;

            if ($num_rows > 0) {
                $field_type = array();
                $field_name = array();
                $meta = $result->fetch_fields();

                foreach ($meta as $m) {
                    array_push($field_type, $m->type);
                    array_push($field_name, $m->name);
                }

                $fields = join('`, `', $field_name);
                fwrite($h, "INSERT INTO `$table` (`$fields`) VALUES\n");
                $index = 0;

                while ($row = $result->fetch_row()) {
                    fwrite($h, "(");
                    for ($i = 0; $i < $num_fields; $i++) {
                        if (is_null($row[$i])) {
                            fwrite($h, "null");
                        } else {
                            switch ($field_type[$i]) {
                                case 'int':
                                    fwrite($h, $row[$i]);
                                    break;
                                case 'string':
                                case 'blob':
                                default:
                                    fwrite($h, "'" . self::escape($row[$i]) . "'");
                            }
                        }
                        if ($i < $num_fields - 1) {
                            fwrite($h, ",");
                        }
                    }
                    fwrite($h, ")");

                    if ($index < $num_rows - 1) {
                        fwrite($h, ",");
                    } else {
                        fwrite($h, ";");
                    }
                    fwrite($h, "\n");

                    $index++;
                }
            }
        }

        $result->free();
        fwrite($h, "\n");
    }


    /**
     * Create Columnns
     */
    public function createColumns()
    {
        if (empty(self::$current_table['name']) || empty(self::$current_table['fields'])) {
            return false;
        }

        $name = self::$current_table['name'];

        $params_arr = [];
        foreach (self::$current_table['fields'] as $field_name => $params) {
            $params_arr[] = $this->makeColumnStr($field_name, $params);
        }

        $params_str = implode(', ', $params_arr);
        $query = self::placehold("ALTER TABLE __$name ADD IF NOT EXISTS ($params_str)");

        if (self::$mysqli->query($query) === false) {
            return false;
        }


        // Выполняем последний запрос
        return self::$res = self::$mysqli->query($this->last_quert);
    }


    /**
     * Create Table
     */
    public function createTable()
    {
        if (empty(self::$current_table['name']) || empty(self::$current_table['fields'])) {
            return false;
        }

        $name = self::$current_table['name'];

        $params_arr = [];
        foreach (self::$current_table['fields'] as $field_name => $params) {
            $params_arr[] = $this->makeColumnStr($field_name, $params);
        }

        $params_str = implode(', ', $params_arr);
        $query = self::placehold("CREATE TABLE IF NOT EXISTS __$name ($params_str)");

        if (self::$mysqli->query($query) === false) {
            return false;
        }

        // Выполняем последний запрос
        self::$res = self::$mysqli->query($this->last_quert);
        return self::$res;
    }


    /**
     * Make Column String
     * @param string $field_name
     * @param array $params
     * @return string Example: column_name varchar(255) null DEFAULT '' PRIMARY KEY
     */
    private function makeColumnStr(string $field_name, array $params)
    {

        // Example: id int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY
        $params_str = '`' . $field_name . '`';
        $params_str .= ' ' . $params['type'];

        $lenght = '';
        if (isset($params['lenght'])) {

            // Example: change 14.2 to 14,2
            $lenght = str_replace('.', ',', $params['lenght']);
        } else { # Default lenght
            switch ($params['type']) {
                case 'tinyint': {
                        $lenght = '1';
                        break;
                    }
                case 'int': {
                        $lenght = '11';
                        break;
                    }
                case 'varchar': {
                        $lenght = '255';
                        break;
                    }
            }
        }

        if (!empty($lenght)) {
            $params_str .= '(' . $lenght . ')';
        }


        if (isset($params['null'])) {
            $params_str .= ' ' . $params['null'];
        }


        // DEFAULT (def|default)
        if (isset($params['default'])) {
            $params_str .= ' DEFAULT ' . $params['default'];
        } elseif (isset($params['def'])) {
            $params_str .= ' DEFAULT ' . $params['def'];
        }


        if (isset($params['extra'])) {
            $params_str .= ' ' . $params['extra'];
            if ($params['extra'] == 'AUTO_INCREMENT') {
                $params_str .= ' PRIMARY KEY';
            }
        }

        return $params_str;
    }
}
