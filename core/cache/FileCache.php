<?php
/**
 * File Cache Driver
 * High Performance File-based Caching
 */

class FileCache {
    
    private $config;
    private $cachePath;
    private $stats = [
        'hits' => 0,
        'misses' => 0,
        'writes' => 0,
        'deletes' => 0
    ];
    
    public function __construct($config) {
        $this->config = array_merge([
            'path' => '/tmp/cache',
            'hash_function' => 'md5',
            'directory_levels' => 2,
            'file_locking' => true,
            'compress' => false,
            'compression_level' => 6
        ], $config);
        
        $this->cachePath = rtrim($this->config['path'], '/');
        $this->ensureCacheDirectory();
    }
    
    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory() {
        if (!is_dir($this->cachePath)) {
            mkdir($this->cachePath, 0755, true);
        }
    }
    
    /**
     * Get cache file path for key
     */
    private function getFilePath($key) {
        $hash = hash($this->config['hash_function'], $key);
        
        $path = $this->cachePath;
        
        // Create directory structure
        for ($i = 0; $i < $this->config['directory_levels']; $i++) {
            $path .= '/' . substr($hash, $i * 2, 2);
        }
        
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        
        return $path . '/' . $hash . '.cache';
    }
    
    /**
     * Get value from cache
     */
    public function get($key) {
        $filePath = $this->getFilePath($key);
        
        if (!file_exists($filePath)) {
            $this->stats['misses']++;
            return null;
        }
        
        try {
            // Check if file is locked
            $handle = fopen($filePath, 'r');
            if (!$handle) {
                $this->stats['misses']++;
                return null;
            }
            
            if ($this->config['file_locking']) {
                if (!flock($handle, LOCK_SH)) {
                    fclose($handle);
                    $this->stats['misses']++;
                    return null;
                }
            }
            
            // Read cache data
            $data = stream_get_contents($handle);
            
            if ($this->config['file_locking']) {
                flock($handle, LOCK_UN);
            }
            
            fclose($handle);
            
            if ($data === false) {
                $this->stats['misses']++;
                return null;
            }
            
            // Decompress if needed
            if ($this->config['compress']) {
                $data = gzuncompress($data);
                if ($data === false) {
                    $this->stats['misses']++;
                    return null;
                }
            }
            
            // Unserialize cache entry
            $cacheEntry = unserialize($data);
            
            if ($cacheEntry === false) {
                $this->stats['misses']++;
                return null;
            }
            
            // Check expiration
            if (isset($cacheEntry['expires']) && $cacheEntry['expires'] < time()) {
                $this->delete($key);
                $this->stats['misses']++;
                return null;
            }
            
            $this->stats['hits']++;
            return $cacheEntry['value'];
            
        } catch (Exception $e) {
            error_log("FileCache get error: " . $e->getMessage());
            $this->stats['misses']++;
            return null;
        }
    }
    
    /**
     * Set value in cache
     */
    public function set($key, $value, $ttl = null) {
        $filePath = $this->getFilePath($key);
        
        try {
            // Create cache entry
            $cacheEntry = [
                'value' => $value,
                'created' => time()
            ];
            
            if ($ttl !== null) {
                $cacheEntry['expires'] = time() + $ttl;
            }
            
            // Serialize data
            $data = serialize($cacheEntry);
            
            // Compress if needed
            if ($this->config['compress']) {
                $data = gzcompress($data, $this->config['compression_level']);
            }
            
            // Write to temporary file first (atomic write)
            $tempFile = $filePath . '.tmp.' . uniqid();
            
            $handle = fopen($tempFile, 'w');
            if (!$handle) {
                return false;
            }
            
            if ($this->config['file_locking']) {
                if (!flock($handle, LOCK_EX)) {
                    fclose($handle);
                    unlink($tempFile);
                    return false;
                }
            }
            
            $written = fwrite($handle, $data);
            
            if ($this->config['file_locking']) {
                flock($handle, LOCK_UN);
            }
            
            fclose($handle);
            
            if ($written === false) {
                unlink($tempFile);
                return false;
            }
            
            // Atomic rename
            if (!rename($tempFile, $filePath)) {
                unlink($tempFile);
                return false;
            }
            
            $this->stats['writes']++;
            return true;
            
        } catch (Exception $e) {
            error_log("FileCache set error: " . $e->getMessage());
            
            // Clean up temp file
            if (isset($tempFile) && file_exists($tempFile)) {
                unlink($tempFile);
            }
            
            return false;
        }
    }
    
    /**
     * Delete value from cache
     */
    public function delete($key) {
        $filePath = $this->getFilePath($key);
        
        if (!file_exists($filePath)) {
            return false;
        }
        
        try {
            if (unlink($filePath)) {
                $this->stats['deletes']++;
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log("FileCache delete error: " . $e->getMessage());
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
     * Clear all cache
     */
    public function clear() {
        try {
            return $this->deleteDirectory($this->cachePath, true);
        } catch (Exception $e) {
            error_log("FileCache clear error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete directory recursively
     */
    private function deleteDirectory($dir, $preserveRoot = false) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        if (!$preserveRoot) {
            rmdir($dir);
        }
        
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
     * Clean expired entries
     */
    public function cleanExpired() {
        $cleaned = 0;
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cachePath),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'cache') {
                    $filePath = $file->getRealPath();
                    
                    // Read and check expiration without going through get() method
                    $data = file_get_contents($filePath);
                    
                    if ($data !== false) {
                        if ($this->config['compress']) {
                            $data = gzuncompress($data);
                        }
                        
                        if ($data !== false) {
                            $cacheEntry = unserialize($data);
                            
                            if ($cacheEntry && isset($cacheEntry['expires']) && $cacheEntry['expires'] < time()) {
                                if (unlink($filePath)) {
                                    $cleaned++;
                                }
                            }
                        }
                    }
                }
            }
            
        } catch (Exception $e) {
            error_log("FileCache cleanExpired error: " . $e->getMessage());
        }
        
        return $cleaned;
    }
    
    /**
     * Get cache size
     */
    public function getSize() {
        $size = 0;
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cachePath),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
            
        } catch (Exception $e) {
            error_log("FileCache getSize error: " . $e->getMessage());
        }
        
        return $size;
    }
    
    /**
     * Get cache entry count
     */
    public function getCount() {
        $count = 0;
        
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($this->cachePath),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'cache') {
                    $count++;
                }
            }
            
        } catch (Exception $e) {
            error_log("FileCache getCount error: " . $e->getMessage());
        }
        
        return $count;
    }
    
    /**
     * Get cache statistics
     */
    public function getStats() {
        return array_merge($this->stats, [
            'cache_path' => $this->cachePath,
            'total_size' => $this->getSize(),
            'entry_count' => $this->getCount(),
            'directory_levels' => $this->config['directory_levels'],
            'compression' => $this->config['compress']
        ]);
    }
    
    /**
     * Optimize cache (clean expired and defragment)
     */
    public function optimize() {
        $cleaned = $this->cleanExpired();
        
        // Additional optimization: remove empty directories
        $this->removeEmptyDirectories($this->cachePath);
        
        return [
            'expired_cleaned' => $cleaned,
            'optimization_complete' => true
        ];
    }
    
    /**
     * Remove empty directories
     */
    private function removeEmptyDirectories($dir) {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->removeEmptyDirectories($path);
                
                // Check if directory is empty after recursive cleanup
                if (count(array_diff(scandir($path), ['.', '..'])) === 0 && $path !== $this->cachePath) {
                    rmdir($path);
                }
            }
        }
    }
}