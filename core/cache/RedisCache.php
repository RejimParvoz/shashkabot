<?php
/**
 * Redis Cache Driver
 * High Performance Redis Implementation
 */

class RedisCache {
    
    private $redis;
    private $config;
    private $connected = false;
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    ];
    
    public function __construct($config) {
        $this->config = $config;
        $this->connect();
    }
    
    /**
     * Connect to Redis
     */
    private function connect() {
        try {
            $this->redis = new Redis();
            
            // Connect with timeout
            $connected = $this->redis->connect(
                $this->config['host'],
                $this->config['port'],
                $this->config['timeout'] ?? 2.0
            );
            
            if (!$connected) {
                throw new Exception("Could not connect to Redis server");
            }
            
            // Authenticate if password is set
            if (!empty($this->config['password'])) {
                $this->redis->auth($this->config['password']);
            }
            
            // Select database
            $this->redis->select($this->config['database'] ?? 0);
            
            // Set read timeout
            if (isset($this->config['read_timeout'])) {
                $this->redis->setOption(Redis::OPT_READ_TIMEOUT, $this->config['read_timeout']);
            }
            
            // Set serialization
            $serializer = $this->config['options']['serializer'] ?? 'php';
            switch ($serializer) {
                case 'php':
                    $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_PHP);
                    break;
                case 'json':
                    $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_JSON);
                    break;
                case 'igbinary':
                    if (defined('Redis::SERIALIZER_IGBINARY')) {
                        $this->redis->setOption(Redis::OPT_SERIALIZER, Redis::SERIALIZER_IGBINARY);
                    }
                    break;
            }
            
            // Set compression
            $compression = $this->config['options']['compression'] ?? false;
            if ($compression && defined('Redis::COMPRESSION_LZ4')) {
                switch ($compression) {
                    case 'lz4':
                        $this->redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_LZ4);
                        break;
                    case 'zstd':
                        if (defined('Redis::COMPRESSION_ZSTD')) {
                            $this->redis->setOption(Redis::OPT_COMPRESSION, Redis::COMPRESSION_ZSTD);
                        }
                        break;
                }
            }
            
            $this->connected = true;
            
        } catch (Exception $e) {
            error_log("Redis connection failed: " . $e->getMessage());
            $this->connected = false;
        }
    }
    
    /**
     * Get value from cache
     */
    public function get($key) {
        if (!$this->connected) {
            return null;
        }
        
        try {
            $value = $this->redis->get($key);
            
            if ($value === false) {
                $this->stats['misses']++;
                return null;
            }
            
            $this->stats['hits']++;
            return $value;
            
        } catch (Exception $e) {
            error_log("Redis get error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Set value in cache
     */
    public function set($key, $value, $ttl = null) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            if ($ttl !== null) {
                $result = $this->redis->setex($key, $ttl, $value);
            } else {
                $result = $this->redis->set($key, $value);
            }
            
            if ($result) {
                $this->stats['writes']++;
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Redis set error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete value from cache
     */
    public function delete($key) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            $result = $this->redis->del($key);
            
            if ($result > 0) {
                $this->stats['deletes']++;
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("Redis delete error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if key exists
     */
    public function exists($key) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            return $this->redis->exists($key) > 0;
        } catch (Exception $e) {
            error_log("Redis exists error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Increment value
     */
    public function increment($key, $value = 1) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            if ($value === 1) {
                return $this->redis->incr($key);
            } else {
                return $this->redis->incrBy($key, $value);
            }
        } catch (Exception $e) {
            error_log("Redis increment error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Decrement value
     */
    public function decrement($key, $value = 1) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            if ($value === 1) {
                return $this->redis->decr($key);
            } else {
                return $this->redis->decrBy($key, $value);
            }
        } catch (Exception $e) {
            error_log("Redis decrement error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get multiple values
     */
    public function getMultiple($keys) {
        if (!$this->connected || empty($keys)) {
            return [];
        }
        
        try {
            $values = $this->redis->mGet($keys);
            $result = [];
            
            foreach ($keys as $index => $key) {
                if ($values[$index] !== false) {
                    $result[$key] = $values[$index];
                    $this->stats['hits']++;
                } else {
                    $this->stats['misses']++;
                }
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Redis getMultiple error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Set multiple values
     */
    public function setMultiple($values, $ttl = null) {
        if (!$this->connected || empty($values)) {
            return false;
        }
        
        try {
            if ($ttl === null) {
                $result = $this->redis->mSet($values);
            } else {
                // Set with TTL using pipeline
                $pipe = $this->redis->multi(Redis::PIPELINE);
                foreach ($values as $key => $value) {
                    $pipe->setex($key, $ttl, $value);
                }
                $results = $pipe->exec();
                $result = !in_array(false, $results, true);
            }
            
            if ($result) {
                $this->stats['writes'] += count($values);
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Redis setMultiple error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete multiple keys
     */
    public function deleteMultiple($keys) {
        if (!$this->connected || empty($keys)) {
            return false;
        }
        
        try {
            $result = $this->redis->del($keys);
            $this->stats['deletes'] += $result;
            return $result > 0;
            
        } catch (Exception $e) {
            error_log("Redis deleteMultiple error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        if (!$this->connected) {
            return false;
        }
        
        try {
            return $this->redis->flushDB();
        } catch (Exception $e) {
            error_log("Redis clear error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $redisInfo = [];
        
        if ($this->connected) {
            try {
                $info = $this->redis->info();
                $redisInfo = [
                    'version' => $info['redis_version'] ?? 'unknown',
                    'used_memory' => $info['used_memory_human'] ?? 'unknown',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                ];
            } catch (Exception $e) {
                // Ignore errors getting info
            }
        }
        
        return array_merge($this->stats, [
            'connected' => $this->connected,
            'redis_info' => $redisInfo
        ]);
    }
    
    /**
     * Get TTL of key
     */
    public function getTtl($key) {
        if (!$this->connected) {
            return -1;
        }
        
        try {
            return $this->redis->ttl($key);
        } catch (Exception $e) {
            error_log("Redis getTtl error: " . $e->getMessage());
            return -1;
        }
    }
    
    /**
     * Set expiration time
     */
    public function expire($key, $ttl) {
        if (!$this->connected) {
            return false;
        }
        
        try {
            return $this->redis->expire($key, $ttl);
        } catch (Exception $e) {
            error_log("Redis expire error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get keys by pattern
     */
    public function keys($pattern) {
        if (!$this->connected) {
            return [];
        }
        
        try {
            return $this->redis->keys($pattern);
        } catch (Exception $e) {
            error_log("Redis keys error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Pipeline operations
     */
    public function pipeline($operations) {
        if (!$this->connected) {
            return [];
        }
        
        try {
            $pipe = $this->redis->multi(Redis::PIPELINE);
            
            foreach ($operations as $operation) {
                $method = $operation['method'];
                $args = $operation['args'] ?? [];
                call_user_func_array([$pipe, $method], $args);
            }
            
            return $pipe->exec();
            
        } catch (Exception $e) {
            error_log("Redis pipeline error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Close connection
     */
    public function close() {
        if ($this->connected && $this->redis) {
            try {
                $this->redis->close();
            } catch (Exception $e) {
                // Ignore close errors
            }
            $this->connected = false;
        }
    }
    
    /**
     * Destructor
     */
    public function __destruct() {
        $this->close();
    }
}