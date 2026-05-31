<?php
/**
 * Null Cache Driver
 * No-op cache implementation for debugging/testing
 */

class NullCache {
    
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    ];
    
    public function __construct($config = []) {
        // No-op constructor
    }
    
    /**
     * Get value from cache (always returns null)
     */
    public function get($key) {
        $this->stats['misses']++;
        return null;
    }
    
    /**
     * Set value in cache (always returns true but doesn't store)
     */
    public function set($key, $value, $ttl = null) {
        $this->stats['writes']++;
        return true;
    }
    
    /**
     * Delete value from cache (always returns false)
     */
    public function delete($key) {
        $this->stats['deletes']++;
        return false;
    }
    
    /**
     * Check if key exists (always returns false)
     */
    public function exists($key) {
        return false;
    }
    
    /**
     * Clear all cache (always returns true)
     */
    public function clear() {
        return true;
    }
    
    /**
     * Get multiple values (always returns empty array)
     */
    public function getMultiple($keys) {
        $this->stats['misses'] += count($keys);
        return [];
    }
    
    /**
     * Set multiple values (always returns true)
     */
    public function setMultiple($values, $ttl = null) {
        $this->stats['writes'] += count($values);
        return true;
    }
    
    /**
     * Delete multiple keys (always returns false)
     */
    public function deleteMultiple($keys) {
        $this->stats['deletes'] += count($keys);
        return false;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        return array_merge($this->stats, [
            'type' => 'null',
            'description' => 'Null cache driver - all operations are no-ops'
        ]);
    }
}