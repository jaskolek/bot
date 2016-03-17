<?php
/**
 * Created by PhpStorm.
 * User: jaskolek
 * Date: 2015-02-24
 * Time: 13:16
 */

namespace Jaskolek\Bot\RecordCache;


use GuzzleHttp\Message\RequestInterface;
use Stash\Driver\FileSystem;
use Stash\Driver\Sqlite;
use Stash\Interfaces\DriverInterface;
use Stash\Pool;

/**
 * Class StashFilesystemRecordCache
 * @package Jaskolek\Utils\Bot\RecordCache
 */
class StashRecordCache implements RecordCacheInterface
{

    /**
     * @var Pool
     */
    protected $_pool;

    /**
     * @param DriverInterface $driver
     */
    function __construct(DriverInterface $driver = null)
    {
        if ($driver == null) {
            $driver = $this->getDefaultDriver();
        }
        $this->_pool = new Pool($driver);
    }

    /**
     * @return FileSystem
     */
    protected function getDefaultDriver()
    {
        $driver = new Sqlite();
        $driver->setOptions([
            "path" => "cache",
            "nesting" => 2
        ]);
        return $driver;
    }

    /**
     * @param RequestInterface $request
     * @param $cachePrefix
     * @return bool
     */
    public function has(RequestInterface $request, $cachePrefix)
    {
        $key = $this->getRequestKey($request, $cachePrefix);
        $item = $this->_pool->getItem($key);
        $isMiss = $item->isMiss();

        return !$isMiss;
    }

    /**
     * @param RequestInterface $request
     * @param $cachePrefix
     * @return mixed|null
     */
    public function get(RequestInterface $request, $cachePrefix)
    {
        $key = $this->getRequestKey($request, $cachePrefix);
        $item = $this->_pool->getItem($key);
        return $item->get();
    }

    /**
     * @param RequestInterface $request
     * @param $data
     * @param $cachePrefix
     */
    public function set(RequestInterface $request, $data, $cachePrefix)
    {
        $start = microtime(true);
        $key = $this->getRequestKey($request, $cachePrefix);
        $item = $this->_pool->getItem($key);
        $item->set($data);

        echo microtime(true) - $start . "\n";
    }


    /**
     * @param RequestInterface $request
     * @return string
     */
    protected function getRequestKey(RequestInterface $request, $cachePrefix)
    {
        $key = "/" . $cachePrefix . "/" . md5($request->getBody()) . "/" . md5($request->getUrl());

        return $key;
    }

    public function clear($cachePrefix)
    {
        $item = $this->_pool->getItem("/" . $cachePrefix)->clear();
    }
}