<?php
/**
 * Shashka Game - Base Model Class
 * High Performance Active Record Pattern for 1M+ Users
 */

abstract class Model {
    
    protected $table;
    protected $primaryKey = 'id';
    protected $fillable = [];
    protected $guarded = ['id'];
    protected $hidden = [];
    protected $casts = [];
    protected $dates = ['created_at', 'updated_at'];
    
    // Current model data
    protected $attributes = [];
    protected $original = [];
    protected $exists = false;
    
    // Database and cache instances
    protected static $db;
    protected static $cache;
    
    /**
     * Constructor
     */
    public function __construct($attributes = []) {
        $this->fill($attributes);
        
        // Get database and cache instances
        if (!self::$db) {
            $app = App::getInstance();
            self::$db = $app->db();
            self::$cache = $app->cache();
        }
    }
    
    /**
     * Fill model with attributes
     */
    public function fill($attributes) {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }
    
    /**
     * Check if attribute is fillable
     */
    protected function isFillable($key) {
        if (in_array($key, $this->guarded)) {
            return false;
        }
        
        if (empty($this->fillable)) {
            return true;
        }
        
        return in_array($key, $this->fillable);
    }
    
    /**
     * Set attribute value
     */
    public function setAttribute($key, $value) {
        // Apply mutator if exists
        $mutator = 'set' . ucfirst($key) . 'Attribute';
        if (method_exists($this, $mutator)) {
            $value = $this->$mutator($value);
        }
        
        $this->attributes[$key] = $value;
        return $this;
    }
    
    /**
     * Get attribute value
     */
    public function getAttribute($key) {
        $value = $this->attributes[$key] ?? null;
        
        // Apply accessor if exists
        $accessor = 'get' . ucfirst($key) . 'Attribute';
        if (method_exists($this, $accessor)) {
            return $this->$accessor($value);
        }
        
        // Apply casting
        if (isset($this->casts[$key])) {
            return $this->castAttribute($key, $value);
        }
        
        return $value;
    }
    
    /**
     * Cast attribute to specific type
     */
    protected function castAttribute($key, $value) {
        if ($value === null) {
            return null;
        }
        
        $cast = $this->casts[$key];
        
        switch ($cast) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return (float) $value;
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return (bool) $value;
            case 'array':
                return is_string($value) ? json_decode($value, true) : (array) $value;
            case 'json':
                return json_decode($value, true);
            case 'object':
                return json_decode($value);
            case 'datetime':
                return new DateTime($value);
            default:
                return $value;
        }
    }
    
    /**
     * Magic getter
     */
    public function __get($key) {
        return $this->getAttribute($key);
    }
    
    /**
     * Magic setter
     */
    public function __set($key, $value) {
        $this->setAttribute($key, $value);
    }
    
    /**
     * Check if attribute exists
     */
    public function __isset($key) {
        return isset($this->attributes[$key]);
    }
    
    /**
     * Find model by primary key
     */
    public static function find($id) {
        $instance = new static();
        $cacheKey = $instance->getCacheKey($id);
        
        // Try cache first
        $data = self::$cache->get($cacheKey);
        if ($data !== null) {
            $model = new static($data);
            $model->exists = true;
            $model->original = $data;
            return $model;
        }
        
        // Query database
        $sql = "SELECT * FROM `{$instance->table}` WHERE `{$instance->primaryKey}` = ? LIMIT 1";
        $data = self::$db->first($sql, [$id]);
        
        if (!$data) {
            return null;
        }
        
        // Cache the result
        self::$cache->set($cacheKey, $data, 300); // 5 minutes
        
        $model = new static($data);
        $model->exists = true;
        $model->original = $data;
        
        return $model;
    }
    
    /**
     * Find model by field
     */
    public static function findBy($field, $value) {
        $instance = new static();
        $cacheKey = $instance->getCacheKey($field . '_' . $value);
        
        // Try cache first
        $data = self::$cache->get($cacheKey);
        if ($data !== null) {
            $model = new static($data);
            $model->exists = true;
            $model->original = $data;
            return $model;
        }
        
        // Query database
        $sql = "SELECT * FROM `{$instance->table}` WHERE `{$field}` = ? LIMIT 1";
        $data = self::$db->first($sql, [$value]);
        
        if (!$data) {
            return null;
        }
        
        // Cache the result
        self::$cache->set($cacheKey, $data, 300);
        
        $model = new static($data);
        $model->exists = true;
        $model->original = $data;
        
        return $model;
    }
    
    /**
     * Get all records
     */
    public static function all($columns = ['*']) {
        $instance = new static();
        $columnStr = is_array($columns) ? implode(', ', $columns) : $columns;
        
        $sql = "SELECT {$columnStr} FROM `{$instance->table}`";
        $results = self::$db->get($sql);
        
        return array_map(function($row) {
            $model = new static($row);
            $model->exists = true;
            $model->original = $row;
            return $model;
        }, $results);
    }
    
    /**
     * Create new record
     */
    public static function create($attributes) {
        $model = new static($attributes);
        $model->save();
        return $model;
    }
    
    /**
     * Save model to database
     */
    public function save() {
        if ($this->exists) {
            return $this->performUpdate();
        } else {
            return $this->performInsert();
        }
    }
    
    /**
     * Perform insert
     */
    protected function performInsert() {
        // Add timestamps
        if (in_array('created_at', $this->dates)) {
            $this->attributes['created_at'] = date('Y-m-d H:i:s');
        }
        if (in_array('updated_at', $this->dates)) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $columns = array_keys($this->attributes);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO `{$this->table}` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        
        $id = self::$db->insert($sql, array_values($this->attributes));
        
        if ($id) {
            $this->attributes[$this->primaryKey] = $id;
            $this->exists = true;
            $this->original = $this->attributes;
            
            // Cache the new record
            $this->updateCache();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Perform update
     */
    protected function performUpdate() {
        if (empty($this->getDirty())) {
            return true; // No changes to save
        }
        
        // Add updated_at timestamp
        if (in_array('updated_at', $this->dates)) {
            $this->attributes['updated_at'] = date('Y-m-d H:i:s');
        }
        
        $dirty = $this->getDirty();
        $columns = array_keys($dirty);
        $setParts = array_map(function($col) { return "`{$col}` = ?"; }, $columns);
        
        $sql = "UPDATE `{$this->table}` SET " . implode(', ', $setParts) . " WHERE `{$this->primaryKey}` = ?";
        
        $values = array_values($dirty);
        $values[] = $this->attributes[$this->primaryKey];
        
        $affected = self::$db->update($sql, $values);
        
        if ($affected !== false) {
            $this->original = $this->attributes;
            
            // Update cache
            $this->updateCache();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete model from database
     */
    public function delete() {
        if (!$this->exists) {
            return false;
        }
        
        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?";
        $affected = self::$db->delete($sql, [$this->attributes[$this->primaryKey]]);
        
        if ($affected > 0) {
            $this->clearCache();
            $this->exists = false;
            return true;
        }
        
        return false;
    }
    
    /**
     * Get dirty attributes (changed since last save)
     */
    protected function getDirty() {
        $dirty = [];
        
        foreach ($this->attributes as $key => $value) {
            if (!isset($this->original[$key]) || $this->original[$key] !== $value) {
                $dirty[$key] = $value;
            }
        }
        
        return $dirty;
    }
    
    /**
     * Convert model to array
     */
    public function toArray() {
        $array = [];
        
        foreach ($this->attributes as $key => $value) {
            if (in_array($key, $this->hidden)) {
                continue;
            }
            
            $array[$key] = $this->getAttribute($key);
        }
        
        return $array;
    }
    
    /**
     * Convert model to JSON
     */
    public function toJson() {
        return json_encode($this->toArray());
    }
    
    /**
     * Get cache key for this model
     */
    protected function getCacheKey($identifier = null) {
        $identifier = $identifier ?: $this->attributes[$this->primaryKey] ?? 'new';
        return strtolower(get_class($this)) . ':' . $identifier;
    }
    
    /**
     * Update cache
     */
    protected function updateCache() {
        if (isset($this->attributes[$this->primaryKey])) {
            $cacheKey = $this->getCacheKey();
            self::$cache->set($cacheKey, $this->attributes, 300);
        }
    }
    
    /**
     * Clear cache
     */
    protected function clearCache() {
        if (isset($this->attributes[$this->primaryKey])) {
            $cacheKey = $this->getCacheKey();
            self::$cache->delete($cacheKey);
        }
    }
    
    /**
     * Begin database transaction
     */
    public static function transaction($callback) {
        return self::$db->transaction($callback);
    }
    
    /**
     * Get query builder instance
     */
    public static function query() {
        return new QueryBuilder(new static());
    }
    
    /**
     * Get table name
     */
    public function getTable() {
        return $this->table;
    }
    
    /**
     * Get primary key name
     */
    public function getKeyName() {
        return $this->primaryKey;
    }
    
    /**
     * Get primary key value
     */
    public function getKey() {
        return $this->getAttribute($this->primaryKey);
    }
}

/**
 * Simple Query Builder for Models
 */
class QueryBuilder {
    
    private $model;
    private $wheres = [];
    private $orders = [];
    private $limit;
    private $offset;
    
    public function __construct($model) {
        $this->model = $model;
    }
    
    /**
     * Add WHERE clause
     */
    public function where($column, $operator, $value = null) {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }
        
        $this->wheres[] = ['column' => $column, 'operator' => $operator, 'value' => $value];
        return $this;
    }
    
    /**
     * Add ORDER BY clause
     */
    public function orderBy($column, $direction = 'ASC') {
        $this->orders[] = ['column' => $column, 'direction' => strtoupper($direction)];
        return $this;
    }
    
    /**
     * Set LIMIT
     */
    public function limit($limit) {
        $this->limit = $limit;
        return $this;
    }
    
    /**
     * Set OFFSET
     */
    public function offset($offset) {
        $this->offset = $offset;
        return $this;
    }
    
    /**
     * Execute query and get results
     */
    public function get() {
        $sql = "SELECT * FROM `{$this->model->getTable()}`";
        $bindings = [];
        
        // Add WHERE clauses
        if (!empty($this->wheres)) {
            $whereParts = [];
            foreach ($this->wheres as $where) {
                $whereParts[] = "`{$where['column']}` {$where['operator']} ?";
                $bindings[] = $where['value'];
            }
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }
        
        // Add ORDER BY clauses
        if (!empty($this->orders)) {
            $orderParts = [];
            foreach ($this->orders as $order) {
                $orderParts[] = "`{$order['column']}` {$order['direction']}";
            }
            $sql .= " ORDER BY " . implode(', ', $orderParts);
        }
        
        // Add LIMIT and OFFSET
        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
            if ($this->offset) {
                $sql .= " OFFSET {$this->offset}";
            }
        }
        
        $results = Model::$db->get($sql, $bindings);
        
        return array_map(function($row) {
            $model = new get_class($this->model)($row);
            $model->exists = true;
            $model->original = $row;
            return $model;
        }, $results);
    }
    
    /**
     * Get first result
     */
    public function first() {
        $this->limit(1);
        $results = $this->get();
        return !empty($results) ? $results[0] : null;
    }
    
    /**
     * Get count of records
     */
    public function count() {
        $sql = "SELECT COUNT(*) as count FROM `{$this->model->getTable()}`";
        $bindings = [];
        
        // Add WHERE clauses
        if (!empty($this->wheres)) {
            $whereParts = [];
            foreach ($this->wheres as $where) {
                $whereParts[] = "`{$where['column']}` {$where['operator']} ?";
                $bindings[] = $where['value'];
            }
            $sql .= " WHERE " . implode(' AND ', $whereParts);
        }
        
        $result = Model::$db->first($sql, $bindings);
        return $result ? (int)$result['count'] : 0;
    }
}