<?php

/**
 * HugaShop - Selling anything
 *
 * @author Andri Huga
 * @version 2.4
 *
 * Create query string for Database
 *
 */

namespace HugaShop\Api;

class DatabaseQuery
{

    private static Database $DB;
    private array $query = [];

    public static $table;
    public static $table_prefix = '';

    /**
     * Initialise Database
     */
    private static function initDB()
    {
        if (empty(self::$DB)) {
            self::$DB = new Database(); # DB connect
        }

        self::getName();
        self::getAlias();

        return new static();
    }


    /**
     * Get table alias
     */
    public static function getAlias()
    {
        if (empty(static::$table['alias'])) {
            static::$table['alias'] = Helper::makeToken(self::getName(), 6);
        }

        return static::$table['alias'];
    }


    /**
     * Get table name
     */
    public static function getName()
    {
        return static::$table['name'] ?? static::$table['name'] = Helper::camelToSnakeCase(Helper::class_basename(static::class));
    }


    /**
     * Get table property
     */
    public static function getTable()
    {
        return static::$table;
    }


    /**
     * Raw query
     */
    public static function query(...$query)
    {
        self::initDB();
        self::$DB->query(...$query);
        return self::$DB;
    }


    /**
     * SELECT
     * @param array|string $select
     */
    public static function select(array|string|null $fields = null)
    {
        $apiObject = self::initDB();
        $apiObject->query['type'] = 'select';
        $apiObject->query['select'] = $fields;
        return $apiObject;
    }


    /**
     * SELECT COUNT
     * @param string $count
     */
    public static function count(string $param = '*')
    {
        return self::select("COUNT($param) as count");
    }


    /**
     * SELECT SUM
     * @param string $count
     */
    public static function sum(string $param = '*')
    {
        return self::select("SUM($param) as sum");
    }


    /**
     * INSERT
     * @param array|object $entity
     */
    public static function insert(array|object $entity)
    {
        $apiObject = self::initDB();
        $apiObject->query['type'] = 'insert';
        $apiObject->query['entity'] = $entity;
        return $apiObject;
    }


    /**
     * UPDATE
     * @param  array|object $entity
     */
    public static function update(array|object|string $entity)
    {
        $apiObject = self::initDB();
        $apiObject->query['type'] = 'update';

        if (is_string($entity)) {
            $apiObject->query['set'] = $entity;
        } else {
            $apiObject->query['entity'] = $entity;
        }

        return $apiObject;
    }


    /**
     * DELETE
     */
    public static function delete()
    {
        $apiObject = self::initDB();
        $apiObject->query['type'] = 'delete';
        return $apiObject;
    }


    /**
     * WHERE 
     * Example: ->where('article.published_at > ?', $date)
     * Example: ->where('id in (1, 2, 3)')
     * Example: ->where('id=5')
     * @param string $var
     * @param ?mixed $value
     */
    public function where(string $var, mixed $value = null)
    {

        $isOR = false;
        if (mb_substr($var, 0, 2) == 'OR') {
            $var = substr($var, 3);
            $isOR = true;
        }

        if (!is_null($value)) {
            $value_str = Database::placehold($var, $value);

            if (!empty(static::$table['alias'] and $this->query['type'] === 'select')) {
                $value_str = '`' . static::$table['alias'] . '`.' . $value_str;
            }
        } else {
            $value_str = $var;
        }

        if (empty($this->query['where'])) {
            $this->query['where'] = 'WHERE';
        } else {

            // Check OR
            if ($isOR === true) {
                $value_str = 'OR ' . $value_str;
            } else {
                $value_str = 'AND ' . $value_str;
            }
        }

        $this->query['where'] .= ' ' . $value_str;
        return $this;
    }


    /**
     * Make and escape WHERE string
     * @param int|array $where
     */
    public function makeWhere(array $where)
    {
        foreach ($where as $key => $value) {
            if (is_array($value)) {
                $this->where("$key in (?@)", $value);
            } else {
                $this->where("$key=?", $value);
            }
        }
        return $this;
    }


    /**
     * Make where id
     * @param int $id
     */
    public function whereId(int|array $id)
    {
        if (is_array($id)) {
            $this->where('id in (?@)', $id);
        } else {
            $this->where('id=?', $id);
        }

        return $this;
    }


    /**
     * WHERE OR
     * @param string $var
     * @param ?string $value
     */
    public function whereOr(string $var, ?string $value = null)
    {
        $var = 'OR ' . $var;
        return $this->where($var, $value);
    }


    /**
     * LEFT JOIN;
     * Example: ->leftJoin(User::class)
     * @param $api_class
     */
    public function leftJoin(string|array $api_class)
    {
        if (is_string($api_class)) {
            $api_class = [$api_class];
        }

        if (is_array($api_class)) {
            foreach ($api_class as $cur_api_class) {
                $this->query['left_join'][$cur_api_class] = $cur_api_class::getTable();
                $this->query['left_join'][$cur_api_class]['name'] =  $cur_api_class::getName();
                $this->query['left_join'][$cur_api_class]['alias'] = $cur_api_class::getAlias();
            }
        }

        return $this;
    }


    /**
     * ORDER
     * @param string|array $param
     * @param ?string $order
     */
    public function order(string|array|null $param = null, ?string $order = 'ASC')
    {
        if (!empty($param)) {
            if (is_array($param)) {
                $join_api = array_key_first($param);
                $join_param = $param[$join_api];
                if (!empty($this->query['left_join'][$join_api]['alias'])) {
                    $param = $this->query['left_join'][$join_api]['alias'] . '.' . $join_param;
                }
            }

            if (is_string($param)) {
                $this->query['order'][$param] = $order;
            }
        }
        return $this;
    }


    /**
     * LIMIT
     * @param int $page
     * @param int $limit
     */
    public function limit(int $page, ?int $limit = null)
    {
        if (!empty($limit)) {
            $page = $page <= 0 ? 1 : $page;
            $limit = $limit <= 0 ? 1 : $limit;

            if ($page === 1) {
                $this->query['limit'] = Database::placehold('LIMIT ?', $limit);
            } else {
                $this->query['limit'] = Database::placehold('LIMIT ?, ?', ($page - 1) * $limit, $limit);
            }
        }

        return $this;
    }


    /**
     * from
     */
    public function from(string $from)
    {
        $this->query['main_table_name'] = $from;
        return $this;
    }


    /**
     * Execute DB query
     */
    public function execute()
    {

        if (empty($this->query['main_table_name'])) {
            if (!empty(static::$table)) {
                $this->query['main_table'] = static::$table;
            } else {
                return false;
            }
        }

        $WHERE = $this->query['where'] ?? '';
        $LIMIT = $this->query['limit'] ?? '';

        // Get API Class
        // Example: Cart|Order|OrderPurchase
        if (!empty($this->query['main_table'])) {
            $FROM = '__' . $this->query['main_table']['name'];
            Database::$current_table = $this->query['main_table'];
        } else {
            $FROM = '__' . $this->query['main_table_name'];
        }


        // UPDATE
        if ($this->query['type'] === 'update') {

            if (!empty($this->query['set'])) { # string
                $SET = $this->query['set'];
            } else { # object|array
                $entity = $this->validateEntity($this->query['entity']);
                $SET = Database::placehold("?%", $entity);
            }

            $query = "UPDATE $FROM SET $SET $WHERE";
        }


        // INSERT
        if ($this->query['type'] === 'insert') {

            $entity = $this->validateEntity($this->query['entity']);
            $SET = Database::placehold("?%", $entity);

            $query = "INSERT INTO $FROM SET $SET";
        }


        // DELETE
        if ($this->query['type'] === 'delete') {
            $query = "DELETE FROM $FROM $WHERE $LIMIT";
        }


        // SELECT
        if ($this->query['type'] === 'select') {

            // Make select fields
            $SELECT = '';
            if (is_array($this->query['select'])) {
                foreach ($this->query['select'] as $param) {
                    if (!empty($SELECT)) {
                        $SELECT .= ', ';
                    }

                    // Make join fields

                    $SELECT .= '`' . $this->query['main_table']['alias'] . '`.' . $param;
                }
            }

            if (is_string($this->query['select'])) {
                $SELECT = $this->query['select'] ?: '*';

                if (!empty($this->query['main_table']['alias']) and $SELECT === '*') {
                    $SELECT = '`' . $this->query['main_table']['alias'] . '`.' . $SELECT;
                }
            }

            if (is_null($this->query['select'])) {
                foreach ($this->query['main_table']['fields'] as $field_name => $field_params) {
                    if (!empty($SELECT)) {
                        $SELECT .= ', ';
                    }
                    $SELECT .= '`' . $this->query['main_table']['alias'] . '`.' . $field_name;
                }
            }

            // Make from string
            if (!empty($this->query['main_table']['alias'])) {
                $FROM = $FROM . ' as `' . $this->query['main_table']['alias'] . '`';
            }

            // Make Left join
            $LEFT_JOIN = '';
            if (!empty($this->query['left_join']) and !empty($this->query['main_table'])) {
                foreach ($this->query['left_join'] as $api_class => $db_table) {

                    if (!empty($LEFT_JOIN)) {
                        $LEFT_JOIN .= ' ';
                    }

                    $join_table = $db_table['name'];
                    $join_alias = $db_table['alias'];

                    $join_id = array_key_first($this->query['main_table']['join'][$api_class]);
                    $main_id = $this->query['main_table']['join'][$api_class][$join_id];
                    $main_alias = $this->query['main_table']['alias'];
                    $join_as_name = str_replace($this->query['main_table']['name'] . '_', '', $join_table);

                    foreach ($db_table['fields'] as $field_name => $field_params) {
                        $this->query['join_fields'][] = $join_as_name . '_' . $field_name;
                        if ($field_name !== $join_id) { # not use 
                            $SELECT .= ', `' . $join_alias . '`.`' . $field_name . '` as `' . $join_as_name . '_' . $field_name . '`';
                        }
                    }

                    $LEFT_JOIN .= "LEFT JOIN __$join_table `$join_alias` ON `$join_alias`.`$join_id`=`$main_alias`.`$main_id`";

                    // Additional join params
                    $join_params = array_keys($this->query['main_table']['join'][$api_class]);
                    array_shift($join_params);

                    if (!empty($join_params)) {
                        foreach ($join_params as $join_param) {
                            if (!empty($this->query['main_table']['join'][$api_class][$join_param])) {
                                $join_value = $this->query['main_table']['join'][$api_class][$join_param];
                                $LEFT_JOIN .= " AND `$join_alias`.`$join_param`='$join_value'";
                            }
                        }
                    }
                }
            }

            // Make order string
            $ORDER = '';
            if (!empty($this->query['order'])) {
                foreach ($this->query['order'] as $param => $order) {
                    if (empty($ORDER)) {
                        $ORDER = 'ORDER BY ';
                    } else {
                        $ORDER .= ', ';
                    }

                    if (!empty($this->query['main_table']['alias']) and !str_contains($param, '.')) {
                        $ORDER .= '`' . $this->query['main_table']['alias'] . "`.$param $order";
                    } else {
                        $ORDER .= "$param $order";
                    }
                }
            }

            $query = "SELECT $SELECT FROM $FROM $LEFT_JOIN $WHERE $ORDER $LIMIT";
        }

        return self::$DB->query($query);
    }


    /**
     * Get result
     * @param ?string $field
     */
    public function getResult(?string $field = null)
    {
        $this->execute();
        $result = self::$DB->result($field);

        if (!empty($this->query['join_fields'])) {
            $result = Helper::normalizeObjectData($result, $this->query['join_fields']);
        }

        // Преобразуем json в object
        // Если преобразовывать пустую переменную, в обьект добавляется "scalar"
        if (!empty($result->settings)) {
            $result->settings = (object) unserialize($result->settings);
        }

        return $result;
    }


    /**
     * Get results
     * @param ?string $field
     */
    public function getResults(?string $field = null): array
    {
        $this->execute();
        $results =  self::$DB->results($field);

        if (!empty($this->query['join_fields'])) {
            $results = Helper::normalizeObjectData($results, $this->query['join_fields']);
        }

        return $results;
    }


    /**
     * Get last insert ID
     */
    public function getInsertId()
    {
        $this->execute();
        return self::$DB->getInsertId();
    }


    /**
     * Get Response
     */
    public function get()
    {
        $this->execute();
        return self::$DB->getResponse();
    }


    /**
     * Prepare Entity
     * @param object|array $entity
     */
    public function validateEntity(object|array $entity)
    {

        $db_table = $this->query['main_table'] ?? [];

        $entity = (object)$entity;
        $clear_entity = new \stdClass();

        // check allowed params
        if (!empty($db_table['fields'])) {
            foreach ($db_table['fields'] as $param_name => $param_params) {
                if (property_exists($entity, $param_name)) {
                    $clear_entity->$param_name = $entity->$param_name;
                }
            }
        } else {
            $clear_entity = clone $entity; # Клонируем Object, отвязываем от основного
        }

        if (property_exists($clear_entity, 'id')) {
            unset($clear_entity->id);
        }

        // Convert Settings
        // Array to json
        if (isset($entity->settings)) {
            $entity->settings = empty($entity->settings) ? [] : (array) $entity->settings;
            $entity->settings = serialize($entity->settings);
        }

        return $entity;
    }


    /**
     * Make table columns name string
     */
    public static function makeSelect()
    {
        $select_string = '';
        if (!empty(static::$table['fields'])) {
            foreach (static::$table['fields'] as $column_name => $column_type) {
                if (!empty($select_string)) {
                    $select_string .= ', ';
                }

                // Set Alias
                if (!empty(static::$table['alias'])) {
                    $select_string .= '`' . static::$table['alias'] . '`.';
                }

                $select_string .= '`' . $column_name . '`';
            }
        }
        return $select_string;
    }


    /**
     * Make ORDER for Mysql query
     * Example: 'position' | ['position' => 'DESC'] | ['User.position' => 'DESC']
     * @param array|string|null $order
     */
    public static function makeOrder(array|string|null $order)
    {
        $order_string = '';

        if (!empty($order)) {

            $cur_alias = static::$table['alias'];

            if (is_array($order)) { # Array
                foreach ($order as $sort_field => $direction) {
                    if (empty($direction)) {
                        $direction = 'ASC';
                    }

                    // If sortfield is Api Class
                    // Example: Product.id
                    if (str_contains($sort_field, '.')) {
                        $sort_field_arr = explode('.', $sort_field);
                        $join_table = $sort_field_arr[0]::getTable();
                        $cur_alias = $join_table['alias'];
                        $sort_field = $sort_field_arr[1];
                    }

                    if (empty($order_string)) {
                        $order_string .= " ORDER BY `$cur_alias`.`$sort_field` $direction";
                    } else {
                        $order_string .= ", `$cur_alias`.`$sort_field` $direction";
                    }
                }
            } else { # String
                $order_string = " ORDER BY `$cur_alias`.`$order`";
            }
        }
        return $order_string;
    }


    /**
     * Get current Table fields
     */
    public static function getFields()
    {
        return static::$table['fields'] ?: null;
    }


    /**
     * Get one
     * @param int|array|null $id id | ['id'' => 1, 'name' => 'name']
     * @param array|string $join User::class | [User::class, Group::class]
     */
    public static function getOne(int|array|null $id, array|string $join = [])
    {
        if (empty($id)) {
            return null;
        }

        if (is_string($join)) {
            $join = [$join];
        }

        if (is_array($id)) {
            return self::select()->makeWhere($id)->leftJoin($join)->getResult();
        }

        return self::select()->whereId($id)->leftJoin($join)->getResult();
    }


    /**
     * Get list
     * @param array $filter
     * @param array|string $order ['id', 'DESC]
     * @param array $join [Order:class, User::class]
     * @param string|array|null $select
     */
    public static function getList(array $filter = [], array|string $order = [], array $join = [], string|array|null $select = null)
    {
        if (is_string($order)) {
            $order = [$order];
        }

        $page = $filter['page'] ?? 1;
        $limit = $filter['limit'] ?? null;

        unset($filter['page']);
        unset($filter['limit']);

        return static::select($select)->makeWhere($filter)->leftJoin($join)->order(...$order)->limit($page, $limit)->getResults($select);
    }


    /**
     * Get count
     * @param array $filter
     */
    public static function getCount(array $filter = [])
    {
        unset($filter['page']);
        unset($filter['limit']);

        return self::count()->makeWhere($filter)->getResult('count');
    }


    /**
     * Get count
     * @param array $filter
     */
    public static function getSum(array $filter = [], ?string $param = null)
    {
        unset($filter['page']);
        unset($filter['limit']);

        return self::sum($param)->makeWhere($filter)->getResult('sum');
    }

    /**
     * Insert by Id
     */
    public static function add($entity)
    {
        $new_id = self::insert($entity)->getInsertId();

        if (!empty($new_id) and isset(static::$table['fields']['position'])) {
            self::update('position=id')->whereId($new_id)->get();
        }

        return $new_id;
    }


    /**
     * Update  by Id
     * @param int|array $ids
     * @param array|object $entity
     */
    public static function updateOne(int|array $ids, $entity)
    {
        return self::update($entity)->whereId($ids)->get();
    }


    /**
     * Delete by Id
     * @param $id
     */
    public static function deleteOne($id)
    {
        return self::delete()->whereId($id)->get();
    }
}
