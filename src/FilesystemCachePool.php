<?php

namespace Phpfox\CacheManager;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Simple filesystem cache on local.
 * implement CacheItemPoolInterface
 *
 * @package Phpfox\CacheManager
 */
class FilesystemCachePool implements CacheItemPoolInterface
{

    /**
     * @var CacheItemInterface[]
     */
    protected $waitItems = [];

    /**
     * @var bool
     */
    protected $debug = false;

    /**
     * @var string
     */
    protected $directory;

    /**
     * FilesystemCachePool constructor.
     *
     * @param      $directory
     * @param bool $debug
     */
    public function __construct($directory, $debug = false)
    {
        $this->directory = $directory;
        $this->debug = $debug;
    }

    /**
     * @param string $name
     *
     * @return string
     */
    protected function getFilename($name)
    {
        $path = md5($name);
        $path = substr($path, 0, 3) . PATH_SEPARATOR . $path;
        return $this->directory . PATH_SEPARATOR . $path;
    }

    /**
     * @param string $filename
     *
     * @return bool
     */
    protected function ensureFilename($filename)
    {
        $dir = dirname($filename);
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
            return false;
        }
        return true;
    }

    public function getItem($key)
    {
        $filename = $this->getFilename($key);

        if (!file_exists($filename)) {
            return null;
        }

        $data = unserialize(file_get_contents($filename));

        if (!$data instanceof CacheItem || $data->expiration < time()) {
            return null;
        }

        return $data;
    }


    public function getItems(array $keys = [])
    {
        return array_map(function ($v) {
            return $this->getItem($v);
        }, $keys);
    }

    public function hasItem($key)
    {
        return file_exists($this->getFilename($key));
    }

    public function clear()
    {
        $files
            = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory,
            RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $splInfo) {
            if ($splInfo->isDir()) {
                rmdir($splInfo->getRealpath());
            }

            if ($splInfo->isDot()) {

            }
            unlink($splInfo->getRealpath());
        }
    }

    public function deleteItem($key)
    {
        if (file_exists($filename = $this->getFilename($key))) {
            if (@unlink($filename)) {
                return true;
            }
            if (!$this->debug) {
                return false;
            } else {
                throw new CacheException("Can not delete cache item " . $key);
            }
        }

        return true;
    }

    public function deleteItems(array $keys)
    {
        array_walk($keys, function ($v) {
            $this->deleteItem($v);
        });

        return true;
    }

    public function save(CacheItemInterface $item)
    {
        $filename = $this->getFilename($item->getKey());
        if (!file_put_contents($filename, serialize($item))) {
            return false;
        }
        return true;
    }

    public function saveDeferred(CacheItemInterface $item)
    {
        $this->waitItems[] = $item;
        return true;
    }

    public function commit()
    {
        array_walk($this->waitItems, function ($v) {
            $this->save($v);
        });
    }
}