<?php
/**
 * Array Cache Driver
 * In-Memory Array-based Caching (for single request lifecycle)
 */

class ArrayCache {
    
    private $config;
    private $cache = [];
    private $maxSize;
    private $currentSize = 0;
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0,
        'evictions' => 0
    ];
    
    public function __construct($config = []) {
        $this->config = array_merge([
            'max_size' => 50 * 1024 * 1024, // 50MB
            'ttl' => 300, // 5 minutes default
            'eviction_policy' => 'lru' // lru, fifo, random
        ], $config);
        
        $this->maxSize = $this->config['max_size'];
    }
    
    /**
     * Get value from cache
     */
    public function get($key) {
        if (!isset($this->cache[$key])) {
            $this->stats['misses']++;
            return null;
        }
        
        $entry = $this->cache[$key];
        
        // Check expiration
        if (isset($entry['expires']) && $entry['expires'] < time()) {
            unset($this->cache[$key]);
            $this->updateSize($key, 0, $entry['size']);
            $this->stats['misses']++;
            return null;
        }
        
        // Update access time for LRU
        if ($this->config['eviction_policy'] === 'lru') {
            $entry['accessed'] = time();
            $this->cache[$key] = $entry;
        }
        
        $this->stats['hits']++;
        return $entry['value'];
    }
    
    /**
     * Set value in cache
     */
    public function set($key, $value, $ttl = null) {
        $serialized = serialize($value);
        $size = strlen($serialized);
        
        // Check if we need to make space
        $oldSize = isset($this->cache[$key]) ? $this->cache[$key]['size'] : 0;
        $sizeIncrease = $size - $oldSize;
        
        if ($this->currentSize + $sizeIncrease > $this->maxSize) {
            if (!$this->makeSpace($sizeIncrease)) {
                return false; // Couldn't make enough space
            }
        }
        
        $entry = [
            'value' => $value,
            'size' => $size,
            'created' => time(),
            'accessed' => time()
        ];
        
        if ($ttl !== null) {
            $entry['expires'] = time() + $ttl;
        } elseif (isset($this->config['ttl'])) {
            $entry['expires'] = time() + $this->config['ttl'];
        }
        
        $this->cache[$key] = $entry;
        $this->updateSize($key, $size, $oldSize);
        
        $this->stats['writes']++;
        return true;
    }
    
    /**
     * Delete value from cache
     */
    public function delete($key) {
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        $oldSize = $this->cache[$key]['size'];
        unset($this->cache[$key]);
        $this->updateSize($key, 0, $oldSize);
        
        $this->stats['deletes']++;
        return true;
    }
    
    /**
     * Check if key exists
     */
    public function exists($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Clear all cache
     */
    public function clear() {
        $this->cache = [];
        $this->currentSize = 0;
        return true;
    }
    
    /**
     * Get multiple values
     */
    public function getMultiple($keys) {
        $result = [];
        
        foreach ($keys as $key) {
            $value = $this->get($key);
            if ($value !== null) {
                $result[$key] = $value;
            }
        }
        
        return $result;
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
     * Delete multiple keys
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
     * Make space in cache
     */
    private function makeSpace($neededSpace) {
        $freedSpace = 0;
        $policy = $this->config['eviction_policy'];
        
        // Clean expired entries first
        $freedSpace += $this->cleanExpired();
        
        if ($freedSpace >= $neededSpace) {
            return true;
        }
        
        // Apply eviction policy
        switch ($policy) {
            case 'lru':
                $freedSpace += $this->evictLRU($neededSpace - $freedSpace);
                break;
            case 'fifo':
                $freedSpace += $this->evictFIFO($neededSpace - $freedSpace);
                break;
            case 'random':
                $freedSpace += $this->evictRandom($neededSpace - $freedSpace);
                break;
        }
        
        return $freedSpace >= $neededSpace;
    }
    
    /**
     * Clean expired entries
     */
    private function cleanExpired() {
        $freedSpace = 0;
        $currentTime = time();
        
        foreach ($this->cache as $key => $entry) {
            if (isset($entry['expires']) && $entry['expires'] < $currentTime) {
                $freedSpace += $entry['size'];
                unset($this->cache[$key]);
            }
        }
        
        $this->currentSize -= $freedSpace;
        return $freedSpace;
    }
    
    /**
     * Evict using LRU (Least Recently Used)
     */
    private function evictLRU($neededSpace) {
        // Sort by access time
        uasort($this->cache, function($a, $b) {
            return $a['accessed'] - $b['accessed'];
        });
        
        return $this->evictEntries($neededSpace);
    }
    
    /**
     * Evict using FIFO (First In, First Out)
     */
    private function evictFIFO($neededSpace) {
        // Sort by creation time
        uasort($this->cache, function($a, $b) {
            return $a['created'] - $b['created'];
        });
        
        return $this->evictEntries($neededSpace);
    }
    
    /**
     * Evict randomly
     */
    private function evictRandom($neededSpace) {
        $freedSpace = 0;
        $keys = array_keys($this->cache);
        
        while ($freedSpace < $neededSpace && !empty($keys)) {
            $randomKey = $keys[array_rand($keys)];
            $freedSpace += $this->cache[$randomKey]['size'];
            
            unset($this->cache[$randomKey]);
            $keys = array_diff($keys, [$randomKey]);
            
            $this->stats['evictions']++;
        }
        
        $this->currentSize -= $freedSpace;
        return $freedSpace;
    }
    
    /**
     * Evict entries (helper for LRU and FIFO)
     */
    private function evictEntries($neededSpace) {
        $freedSpace = 0;
        
        foreach ($this->cache as $key => $entry) {
            if ($freedSpace >= $neededSpace) {
                break;
            }
            
            $freedSpace += $entry['size'];
            unset($this->cache[$key]);
            $this->stats['evictions']++;
        }
        
        $this->currentSize -= $freedSpace;
        return $freedSpace;
    }
    
    /**
     * Update size tracking
     */
    private function updateSize($key, $newSize, $oldSize) {
        $this->currentSize += ($newSize - $oldSize);
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        $entryCount = count($this->cache);
        $avgSize = $entryCount > 0 ? $this->currentSize / $entryCount : 0;
        
        return array_merge($this->stats, [
            'entry_count' => $entryCount,
            'current_size' => $this->currentSize,
            'max_size' => $this->maxSize,
            'size_usage_percent' => ($this->currentSize / $this->maxSize) * 100,
            'average_entry_size' => $avgSize,
            'eviction_policy' => $this->config['eviction_policy']
        ]);
    }
    
    /**
     * Get all keys
     */
    public function getKeys() {
        return array_keys($this->cache);
    }
    
    /**
     * Get cache info for debugging
     */
    public function getInfo() {
        $info = [];
        
        foreach ($this->cache as $key => $entry) {
            $info[$key] = [
                'size' => $entry['size'],
                'created' => date('Y-m-d H:i:s', $entry['created']),
                'accessed' => date('Y-m-d H:i:s', $entry['accessed']),
                'expires' => isset($entry['expires']) ? date('Y-m-d H:i:s', $entry['expires']) : 'never',
                'ttl' => isset($entry['expires']) ? max(0, $entry['expires'] - time()) : null
            ];
        }
        
        return $info;
    }
    
    /**
     * Optimize cache (clean expired entries)
     */
    public function optimize() {
        $before = $this->currentSize;
        $cleaned = $this->cleanExpired();
        
        return [
            'size_before' => $before,
            'size_after' => $this->currentSize,
            'freed_space' => $cleaned,
            'entries_cleaned' => count($this->cache)
        ];
    }
}