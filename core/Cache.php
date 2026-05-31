<?php
/**
 * Shashka Game - Cache Class
 * Multi-level Caching System for 1M+ Users
 * Supports Redis, File, Memory, and Database caching
 */

class Cache {
    
    private $config;
    private $drivers = [];
    private $defaultDriver;
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    ];
    
    // Cache layers for different data types
    private $layers = [];
    
    /**
     * Constructor
     */
    public function __construct($config) {
        $this->config = $config;
        $this->defaultDriver = $config['default'];
        $this->layers = $config['layers'] ?? [];
        
        $this->initializeDrivers();
    }
    
    /**
     * Initialize cache drivers
     */
    private function initializeDrivers() {
        foreach ($this->config['stores'] as $name => $config) {
            $this->drivers[$name] = $this->createDriver($name, $config);
        }
    }
    
    /**
     * Create cache driver instance
     */
    private function createDriver($name, $config) {
        switch ($config['driver']) {
            case 'redis':
                require_once __DIR__ . '/cache/RedisCache.php';
                return new RedisCache($config);
            case 'file':
                require_once __DIR__ . '/cache/FileCache.php';
                return new FileCache($config);
            case 'array':
                require_once __DIR__ . '/cache/ArrayCache.php';
                return new ArrayCache($config);
            case 'database':
                require_once __DIR__ . '/cache/DatabaseCache.php';
                return new DatabaseCache($config);
            case 'null':
                require_once __DIR__ . '/cache/NullCache.php';
                return new NullCache($config);
            default:
                throw new Exception("Unsupported cache driver: {$config['driver']}");
        }
    }
    
    /**
     * Get cache value
     */
    public function get($key, $default = null) {
        $cacheKey = $this->normalizeKey($key);
        $layers = $this->getLayersForKey($key);
        
        // Try each layer in order
        foreach ($layers as $driverName) {
            if (!isset($this->drivers[$driverName])) continue;
            
            $driver = $this->drivers[$driverName];
            $value = $driver->get($cacheKey);
            
            if ($value !== null) {
                $this->stats['hits']++;
                
                // Populate higher-priority layers that missed
                $this->populateUpperLayers($layers, $driverName, $cacheKey, $value);
                
                return $this->unserializeValue($value);
            }
        }
        
        $this->stats['misses']++;
        return $default;
    }
    
    /**
     * Set cache value
     */
    public function set($key, $value, $ttl = null) {
        $cacheKey = $this->normalizeKey($key);
        $serializedValue = $this->serializeValue($value);
        $layers = $this->getLayersForKey($key);
        
        $success = true;
        
        // Set in all layers
        foreach ($layers as $driverName) {
            if (!isset($this->drivers[$driverName])) continue;
            
            $driver = $this->drivers[$driverName];
            $layerTtl = $this->getTtlForKey($key, $ttl);
            
            if (!$driver->set($cacheKey, $serializedValue, $layerTtl)) {
                $success = false;
            }
        }
        
        if ($success) {
            $this->stats['writes']++;
        }
        
        return $success;
    }
    
    /**
     * Delete cache value
     */
    public function delete($key) {
        $cacheKey = $this->normalizeKey($key);
        $layers = $this->getLayersForKey($key);
        
        $success = true;
        
        // Delete from all layers
        foreach ($layers as $driverName) {
            if (!isset($this->drivers[$driverName])) continue;
            
            $driver = $this->drivers[$driverName];
            if (!$driver->delete($cacheKey)) {
                $success = false;
            }
        }
        
        if ($success) {
            $this->stats['deletes']++;
        }
        
        return $success;
    }
    
    /**
     * Check if key exists
     */
    public function exists($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Get multiple values
     */
    public function getMultiple($keys, $default = null) {
        $results = [];
        foreach ($keys as $key) {
            $results[$key] = $this->get($key, $default);
        }
        return $results;
    }
    
    /**
     * Set multiple values
     */
    public function setMultiple($values, $ttl = null) {
        $success = true;
        foreach ($values as $key => $value) {
            if (!$this->set($key, $value, $ttl)) {
                $success = false;
            }
        }
        return $success;
    }
    
    /**
     * Delete multiple values
     */
    public function deleteMultiple($keys) {
        $success = true;
        foreach ($keys as $key) {
            if (!$this->delete($key)) {
                $success = false;
            }
        }
        return $success;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $success = true;
        foreach ($this->drivers as $driver) {
            if (!$driver->clear()) {
                $success = false;
            }
        }
        return $success;
    }
    
    /**
     * Get and set (cache with callback)
     */
    public function remember($key, $ttl, $callback) {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
    
    /**
     * Increment value
     */
    public function increment($key, $value = 1) {
        $layers = $this->getLayersForKey($key);
        $primaryDriver = $this->drivers[$layers[0]];
        
        if (method_exists($primaryDriver, 'increment')) {
            return $primaryDriver->increment($this->normalizeKey($key), $value);
        }
        
        // Fallback implementation
        $current = (int)$this->get($key, 0);
        $new = $current + $value;
        $this->set($key, $new);
        
        return $new;
    }
    
    /**
     * Decrement value
     */
    public function decrement($key, $value = 1) {
        return $this->increment($key, -$value);
    }
    
    /**
     * Tag-based cache invalidation
     */
    public function tags($tags) {
        return new TaggedCache($this, is_array($tags) ? $tags : [$tags]);
    }
    
    /**
     * Invalidate by tag
     */
    public function invalidateTag($tag) {
        $tagKey = "cache_tag:$tag";
        $keys = $this->get($tagKey, []);
        
        foreach ($keys as $key) {
            $this->delete($key);
        }
        
        $this->delete($tagKey);
    }
    
    /**
     * Get layers for specific key
     */
    private function getLayersForKey($key) {
        // Check if key matches any layer pattern
        foreach ($this->layers as $pattern => $layerDrivers) {
            if (strpos($key, $pattern) === 0) {
                return $layerDrivers;
            }
        }
        
        // Default to primary driver
        return [$this->defaultDriver];
    }
    
    /**
     * Populate upper layers that missed
     */
    private function populateUpperLayers($layers, $hitLayer, $key, $value) {
        $hitIndex = array_search($hitLayer, $layers);
        
        // Populate layers before the hit layer
        for ($i = 0; $i < $hitIndex; $i++) {
            $driverName = $layers[$i];
            if (isset($this->drivers[$driverName])) {
                $this->drivers[$driverName]->set($key, $value, $this->getTtlForKey($key));
            }
        }
    }
    
    /**
     * Get TTL for key
     */
    private function getTtlForKey($key, $defaultTtl = null) {
        if ($defaultTtl !== null) {
            return $defaultTtl;
        }
        
        // Check configured TTL for key patterns
        foreach ($this->config['ttl'] ?? [] as $pattern => $ttl) {
            if (strpos($key, $pattern) === 0) {
                return $ttl;
            }
        }
        
        return 3600; // Default 1 hour
    }
    
    /**
     * Normalize cache key
     */
    private function normalizeKey($key) {
        // Remove invalid characters
        $key = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $key);
        
        // Add prefix if configured
        $prefix = $this->config['prefix'] ?? 'shashka:';
        
        return $prefix . $key;
    }
    
    /**
     * Serialize value for storage
     */
    private function serializeValue($value) {
        if ($this->config['compression']['enabled'] ?? false) {
            $serialized = serialize($value);
            
            if (strlen($serialized) > ($this->config['compression']['min_size'] ?? 1024)) {
                $algorithm = $this->config['compression']['algorithm'] ?? 'gzip';
                
                switch ($algorithm) {
                    case 'gzip':
                        return gzcompress($serialized, $this->config['compression']['level'] ?? 6);
                    case 'lz4':
                        if (function_exists('lz4_compress')) {
                            return lz4_compress($serialized);
                        }
                        break;
                }
            }
            
            return $serialized;
        }
        
        return serialize($value);
    }
    
    /**
     * Unserialize value from storage
     */
    private function unserializeValue($value) {
        if ($this->config['compression']['enabled'] ?? false) {
            // Try to decompress
            $decompressed = @gzuncompress($value);
            if ($decompressed !== false) {
                return unserialize($decompressed);
            }
            
            // Try LZ4
            if (function_exists('lz4_uncompress')) {
                $decompressed = @lz4_uncompress($value);
                if ($decompressed !== false) {
                    return unserialize($decompressed);
                }
            }
        }
        
        return unserialize($value);
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $driverStats = [];
        foreach ($this->drivers as $name => $driver) {
            if (method_exists($driver, 'getStats')) {
                $driverStats[$name] = $driver->getStats();
            }
        }
        
        return [
            'overall' => $this->stats,
            'drivers' => $driverStats,
            'hit_rate' => $this->stats['hits'] + $this->stats['misses'] > 0 
                ? round($this->stats['hits'] / ($this->stats['hits'] + $this->stats['misses']) * 100, 2)
                : 0
        ];
    }
    
    /**
     * Warm up cache with critical data
     */
    public function warmUp() {
        $warmupConfig = $this->config['warmup'] ?? [];
        
        if (!($warmupConfig['enabled'] ?? false)) {
            return;
        }
        
        foreach ($warmupConfig['items'] ?? [] as $category => $settings) {
            foreach ($settings['keys'] ?? [] as $key) {
                // Implement specific warm-up logic based on category
                $this->warmUpItem($category, $key, $settings['priority'] ?? 'medium');
            }
        }
    }
    
    /**
     * Warm up specific item
     */
    private function warmUpItem($category, $key, $priority) {
        // This would be implemented based on specific application needs
        // For now, just ensure the key exists in cache layers
        if ($this->get($key) === null) {
            // Load data and cache it
            // Implementation depends on data source
        }
    }
    
    /**
     * Get cache driver
     */
    public function driver($name = null) {
        $name = $name ?? $this->defaultDriver;
        
        if (!isset($this->drivers[$name])) {
            throw new Exception("Cache driver '$name' not found");
        }
        
        return $this->drivers[$name];
    }
}

/**
 * Tagged Cache Implementation
 */
class TaggedCache {
    private $cache;
    private $tags;
    
    public function __construct($cache, $tags) {
        $this->cache = $cache;
        $this->tags = $tags;
    }
    
    public function get($key, $default = null) {
        return $this->cache->get($key, $default);
    }
    
    public function set($key, $value, $ttl = null) {
        // Store key in tag indexes
        foreach ($this->tags as $tag) {
            $tagKey = "cache_tag:$tag";
            $keys = $this->cache->get($tagKey, []);
            $keys[] = $key;
            $this->cache->set($tagKey, array_unique($keys));
        }
        
        return $this->cache->set($key, $value, $ttl);
    }
    
    public function delete($key) {
        return $this->cache->delete($key);
    }
    
    public function flush() {
        foreach ($this->tags as $tag) {
            $this->cache->invalidateTag($tag);
        }
    }
}