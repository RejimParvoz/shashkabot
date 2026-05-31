<?php
/**
 * Database Cache Driver
 * Fallback cache stored in the `cache` table (MySQL)
 */

class DatabaseCache {

    private $config;
    private $table;
    private $db;
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    ];

    public function __construct($config = []) {
        $this->config = $config;
        $this->table = $config['table'] ?? 'cache';
    }

    /**
     * Lazily get the database instance
     */
    private function db() {
        if (!$this->db) {
            $app = App::getInstance();
            $this->db = $app->db();
        }
        return $this->db;
    }

    /**
     * Get value from cache
     */
    public function get($key) {
        try {
            $row = $this->db()->first(
                "SELECT `value`, `expiration` FROM `{$this->table}` WHERE `key` = ? LIMIT 1",
                [$key]
            );

            if (!$row) {
                $this->stats['misses']++;
                return null;
            }

            // Check expiration
            if ((int) $row['expiration'] > 0 && (int) $row['expiration'] < time()) {
                $this->delete($key);
                $this->stats['misses']++;
                return null;
            }

            $this->stats['hits']++;
            return $row['value'];
        } catch (Exception $e) {
            error_log('DatabaseCache get error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Set value in cache
     */
    public function set($key, $value, $ttl = null) {
        try {
            $expiration = $ttl !== null ? (time() + $ttl) : 0;

            $this->db()->query(
                "INSERT INTO `{$this->table}` (`key`, `value`, `expiration`) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `expiration` = VALUES(`expiration`)",
                [$key, $value, $expiration],
                'write'
            );

            $this->stats['writes']++;
            return true;
        } catch (Exception $e) {
            error_log('DatabaseCache set error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete value from cache
     */
    public function delete($key) {
        try {
            $this->db()->delete("DELETE FROM `{$this->table}` WHERE `key` = ?", [$key]);
            $this->stats['deletes']++;
            return true;
        } catch (Exception $e) {
            error_log('DatabaseCache delete error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if key exists
     */
    public function exists($key) {
        return $this->get($key) !== null;
    }

    /**
     * Clear all cache entries
     */
    public function clear() {
        try {
            $this->db()->query("TRUNCATE TABLE `{$this->table}`", [], 'write');
            return true;
        } catch (Exception $e) {
            error_log('DatabaseCache clear error: ' . $e->getMessage());
            return false;
        }
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
     * Clean expired entries
     */
    public function cleanExpired() {
        try {
            return $this->db()->delete(
                "DELETE FROM `{$this->table}` WHERE `expiration` > 0 AND `expiration` < ?",
                [time()]
            );
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats() {
        return array_merge($this->stats, ['type' => 'database', 'table' => $this->table]);
    }
}
