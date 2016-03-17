<?php
/**
 * Created by PhpStorm.
 * User: jaskolek
 * Date: 2015-02-24
 * Time: 16:35
 */

namespace Jaskolek\Bot\RecordCache;


use GuzzleHttp\Psr7\Request;

/**
 * Class FileRecordCache
 * @package Jaskolek\Utils\Bot\RecordCache
 */
class FileRecordCache implements RecordCacheInterface{

    /**
     * @var array
     */
    protected $_loadedData = [];
    /**
     * @var
     */
    protected $_namespace;

    protected $_path;

    protected $_newRecordCount = 1;

    protected $_chunkSize;
    /**
     * @param $path
     * @param int $chunkSize
     */
    public function __construct($path, $chunkSize = 10)
    {
        if(!file_exists($path)){
            file_put_contents($path, '');
        }

        $this->_path = $path;
        $this->_chunkSize = $chunkSize;
        $this->loadData();
    }

    /**
     *
     */
    protected function loadData()
    {
        $serializedData = file_get_contents($this->_path);
        $this->_loadedData = unserialize($serializedData);
        if(!is_array($this->_loadedData)) $this->_loadedData = [];
    }

    /**
     *
     */
    protected function saveData()
    {
        $serializedData = serialize($this->_loadedData);
        file_put_contents($this->_path, $serializedData);
    }


    /**
     * @param $request
     * @return bool
     */
    public function has($request)
    {
        $key = $this->getRequestKey($request);
        if(!isset($this->_loadedData[$this->_namespace][$key])) return false;

        return true;
    }

    /**
     * @param $request
     * @return mixed
     */
    public function get($request)
    {
        $key = $this->getRequestKey($request);
        return $this->_loadedData[$this->_namespace][$key];
    }

    /**
     * @param $request
     * @param $data
     */
    public function set($request, $data)
    {
        $key = $this->getRequestKey($request);
        $this->_loadedData[$this->_namespace][$key] = $data;

        $this->_newRecordCount++;
        if($this->_chunkSize != 0 && $this->_newRecordCount > $this->_chunkSize){
            $this->_newRecordCount -= $this->_chunkSize;
            $this->saveData();
        }
    }

    /**
     *
     */
    public function clear()
    {
        $this->_loadedData[$this->_namespace] = [];
        $this->saveData();
    }

    /**
     * @param $requestData
     * @return string
     */
    protected function getRequestKey($requestData){

        /** @var Request $request */
        $request = $requestData["request"];

        $body = $request->getBody();
        $body->rewind();
        $contents = $body->getContents();
        $key = md5($request->getUri()->__toString() . $contents);
        $body->rewind();
        return $key;
    }

    /**
     * @param $namespace
     */
    public function setNamespace($namespace)
    {
        if(!isset($this->_loadedData[$namespace])) $this->_loadedData[$namespace] = [];

        $this->_namespace = $namespace;
    }

    public function __destruct()
    {
        $this->saveData();
    }
}