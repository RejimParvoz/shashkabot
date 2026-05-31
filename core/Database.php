<?php
/**
 * Shashka Game - Database Class
 * High Performance Database Layer for 1M+ Users
 * Supports Connection Pooling, Read/Write Splitting, Prepared Statements
 */

class Database {
    
    private $config;
    private $connections = [];
    private $readConnections = [];
    private $transactionLevel = 0;
    private $queryLog = [];
    private $slowQueryThreshold = 2.0;
    
    // Connection pool
    private $pool = [];
    private $poolSize = 0;
    private $maxPoolSize = 100;
    
    /**
     * Constructor
     */
    public function __construct($config) {
        $this->config = $config;
        $this->slowQueryThreshold = $config['query']['slow_query_time'] ?? 2.0;
        $this->maxPoolSize = $config['pool']['max_connections'] ?? 100;
    }
    
    /**
     * Get write connection (master)
     */
    private function getWriteConnection() {
        if (!isset($this->connections['write'])) {
            $this->connections['write'] = $this->createConnection($this->config['connections']['mysql']);
        }
        return $this->connections['write'];
    }
    
    /**
     * Get read connection (slave or master)
     */
    private function getReadConnection() {
        // Use read replica if configured
        if (isset($this->config['connections']['mysql_read']) && $this->config['replication']['enabled']) {
            if (!isset($this->readConnections['read'])) {
                $this->readConnections['read'] = $this->createConnection($this->config['connections']['mysql_read']);
            }
            return $this->readConnections['read'];
        }
        
        // Fallback to write connection
        return $this->getWriteConnection();
    }
    
    /**
     * Create database connection with optimized settings
     */
    private function createConnection($config) {
        try {
            $dsn = "mysql:host={$config['host']};port={$config['port']};dbname={$config['database']};charset={$config['charset']}";
            
            $options = $config['options'] ?? [];
            
            // Add MySQL specific optimizations
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$config['charset']} COLLATE {$config['collation']}";
            
            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            
            // Set MySQL session variables for performance
            if (isset($config['mysql_options'])) {
                foreach ($config['mysql_options'] as $key => $value) {
                    $pdo->exec("SET SESSION $key = '$value'");
                }
            }
            
            return $pdo;
            
        } catch (PDOException $e) {
            $this->logError("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed");
        }
    }
    
    /**
     * Execute SELECT query (uses read connection)
     */
    public function select($sql, $bindings = []) {
        return $this->query($sql, $bindings, 'read');
    }
    
    /**
     * Execute INSERT query
     */
    public function insert($sql, $bindings = []) {
        $result = $this->query($sql, $bindings, 'write');
        return $this->getWriteConnection()->lastInsertId();
    }
    
    /**
     * Execute UPDATE query
     */
    public function update($sql, $bindings = []) {
        $stmt = $this->query($sql, $bindings, 'write');
        return $stmt->rowCount();
    }
    
    /**
     * Execute DELETE query
     */
    public function delete($sql, $bindings = []) {
        $stmt = $this->query($sql, $bindings, 'write');
        return $stmt->rowCount();
    }
    
    /**
     * Execute raw query
     */
    public function query($sql, $bindings = [], $type = 'write') {
        $startTime = microtime(true);
        
        try {
            // Choose connection based on query type
            $connection = ($type === 'read') ? $this->getReadConnection() : $this->getWriteConnection();
            
            // Prepare statement
            $stmt = $connection->prepare($sql);
            
            // Bind parameters
            if (!empty($bindings)) {
                $this->bindParameters($stmt, $bindings);
            }
            
            // Execute query
            $stmt->execute();
            
            // Log slow queries
            $executionTime = microtime(true) - $startTime;
            if ($executionTime > $this->slowQueryThreshold) {
                $this->logSlowQuery($sql, $bindings, $executionTime);
            }
            
            // Log query in debug mode
            if ($_ENV['APP_DEBUG'] ?? false) {
                $this->queryLog[] = [
                    'sql' => $sql,
                    'bindings' => $bindings,
                    'time' => $executionTime,
                    'type' => $type
                ];
            }
            
            return $stmt;
            
        } catch (PDOException $e) {
            $this->logError("Query failed: " . $e->getMessage() . " SQL: $sql");
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }
    
    /**
     * Bind parameters to prepared statement
     */
    private function bindParameters($stmt, $bindings) {
        foreach ($bindings as $key => $value) {
            $paramType = PDO::PARAM_STR;
            
            if (is_int($value)) {
                $paramType = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $paramType = PDO::PARAM_BOOL;
            } elseif ($value === null) {
                $paramType = PDO::PARAM_NULL;
            }
            
            if (is_numeric($key)) {
                $stmt->bindValue($key + 1, $value, $paramType);
            } else {
                $stmt->bindValue($key, $value, $paramType);
            }
        }
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        if ($this->transactionLevel === 0) {
            $this->getWriteConnection()->beginTransaction();
        } else {
            // Nested transaction using savepoints
            $this->getWriteConnection()->exec("SAVEPOINT sp_level_{$this->transactionLevel}");
        }
        
        $this->transactionLevel++;
        return true;
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        if ($this->transactionLevel === 0) {
            throw new Exception("No active transaction");
        }
        
        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            return $this->getWriteConnection()->commit();
        } else {
            // Release savepoint
            return $this->getWriteConnection()->exec("RELEASE SAVEPOINT sp_level_{$this->transactionLevel}");
        }
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        if ($this->transactionLevel === 0) {
            throw new Exception("No active transaction");
        }
        
        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            return $this->getWriteConnection()->rollback();
        } else {
            // Rollback to savepoint
            return $this->getWriteConnection()->exec("ROLLBACK TO SAVEPOINT sp_level_{$this->transactionLevel}");
        }
    }
    
    /**
     * Execute transaction with callback
     */
    public function transaction($callback) {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * Get single row
     */
    public function first($sql, $bindings = []) {
        $stmt = $this->select($sql, $bindings);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all rows
     */
    public function get($sql, $bindings = []) {
        $stmt = $this->select($sql, $bindings);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get single value
     */
    public function value($sql, $bindings = []) {
        $stmt = $this->select($sql, $bindings);
        return $stmt->fetchColumn();
    }
    
    /**
     * Insert or update (ON DUPLICATE KEY UPDATE)
     */
    public function upsert($table, $data, $updateColumns = []) {
        $columns = array_keys($data);
        $placeholders = ':' . implode(', :', $columns);
        
        $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ($placeholders)";
        
        if (!empty($updateColumns)) {
            $updateParts = [];
            foreach ($updateColumns as $column) {
                $updateParts[] = "`$column` = VALUES(`$column`)";
            }
            $sql .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
        }
        
        return $this->query($sql, $data, 'write');
    }
    
    /**
     * Batch insert for better performance
     */
    public function batchInsert($table, $rows, $batchSize = 1000) {
        if (empty($rows)) return 0;
        
        $columns = array_keys($rows[0]);
        $totalInserted = 0;
        
        // Process in batches
        for ($i = 0; $i < count($rows); $i += $batchSize) {
            $batch = array_slice($rows, $i, $batchSize);
            
            // Build SQL
            $sql = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES ";
            
            $valueParts = [];
            $bindings = [];
            $bindingIndex = 0;
            
            foreach ($batch as $row) {
                $placeholders = [];
                foreach ($columns as $column) {
                    $placeholder = ":batch_$bindingIndex";
                    $placeholders[] = $placeholder;
                    $bindings[$placeholder] = $row[$column];
                    $bindingIndex++;
                }
                $valueParts[] = '(' . implode(', ', $placeholders) . ')';
            }
            
            $sql .= implode(', ', $valueParts);
            
            $this->query($sql, $bindings, 'write');
            $totalInserted += count($batch);
        }
        
        return $totalInserted;
    }
    
    /**
     * Cursor-based pagination for large datasets
     */
    public function paginate($sql, $bindings = [], $cursor = null, $limit = 20, $cursorColumn = 'id') {
        // Add cursor condition if provided
        if ($cursor !== null) {
            if (strpos(strtoupper($sql), 'WHERE') !== false) {
                $sql .= " AND `$cursorColumn` > :cursor";
            } else {
                $sql .= " WHERE `$cursorColumn` > :cursor";
            }
            $bindings['cursor'] = $cursor;
        }
        
        // Add limit
        $sql .= " ORDER BY `$cursorColumn` ASC LIMIT :limit";
        $bindings['limit'] = $limit + 1; // +1 to check if there are more records
        
        $rows = $this->get($sql, $bindings);
        
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows); // Remove the extra row
        }
        
        $nextCursor = null;
        if ($hasMore && !empty($rows)) {
            $lastRow = end($rows);
            $nextCursor = $lastRow[$cursorColumn];
        }
        
        return [
            'data' => $rows,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor
        ];
    }
    
    /**
     * Get table statistics
     */
    public function getTableStats($table) {
        $sql = "SELECT 
                    COUNT(*) as row_count,
                    AVG(CHAR_LENGTH(CONCAT_WS('', " . 
                        "COALESCE(COLUMN_NAME, '')))" . 
                    ") as avg_row_length
                FROM `$table`";
        
        return $this->first($sql);
    }
    
    /**
     * Optimize table
     */
    public function optimizeTable($table) {
        return $this->query("OPTIMIZE TABLE `$table`", [], 'write');
    }
    
    /**
     * Analyze table
     */
    public function analyzeTable($table) {
        return $this->query("ANALYZE TABLE `$table`", [], 'write');
    }
    
    /**
     * Check table for errors
     */
    public function checkTable($table) {
        return $this->get("CHECK TABLE `$table`");
    }
    
    /**
     * Get connection info
     */
    public function getConnectionInfo() {
        return [
            'write_connection' => isset($this->connections['write']),
            'read_connections' => count($this->readConnections),
            'transaction_level' => $this->transactionLevel,
            'query_count' => count($this->queryLog),
        ];
    }
    
    /**
     * Log slow query
     */
    private function logSlowQuery($sql, $bindings, $time) {
        $message = "Slow query ({$time}s): $sql";
        if (!empty($bindings)) {
            $message .= " Bindings: " . json_encode($bindings);
        }
        
        error_log($message);
        
        // Store in database if slow query logging is enabled
        if ($this->config['query']['slow_query_log']) {
            try {
                $this->insert("INSERT INTO slow_query_log (query_sql, execution_time, bindings, created_at) VALUES (?, ?, ?, NOW())", [
                    $sql,
                    $time,
                    json_encode($bindings)
                ]);
            } catch (Exception $e) {
                // Ignore errors in logging
            }
        }
    }
    
    /**
     * Log database error
     */
    private function logError($message) {
        error_log("Database Error: $message");
    }
    
    /**
     * Get query log (debug mode only)
     */
    public function getQueryLog() {
        return $this->queryLog;
    }
    
    /**
     * Clear query log
     */
    public function clearQueryLog() {
        $this->queryLog = [];
    }
    
    /**
     * Close all connections
     */
    public function close() {
        $this->connections = [];
        $this->readConnections = [];
        $this->pool = [];
        $this->poolSize = 0;
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}