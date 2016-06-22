<?php
/**
 * Created by PhpStorm.
 * User: Jaskolek
 * Date: 2016-03-21
 * Time: 20:17
 */

namespace Jaskolek\Bot\RecordCache;


use GuzzleHttp\Psr7\Request;

class SQLiteRecordCache implements RecordCacheInterface
{
    protected $_namespace = 'default';
    protected $_tablePrefix;

    protected $_pdo;

    public function __construct($path, $tablePrefix = 'cache')
    {
        $this->_pdo = new \PDO("sqlite:" . $path);
        $this->_tablePrefix = $tablePrefix;
        $this->setNamespace($this->_namespace);
    }



    public function has($request)
    {
        $key = $this->getRequestKey($request);
        $stmt = $this->_pdo->prepare("SELECT 1 FROM " . $this->getTableName() . " WHERE key = :key LIMIT 1");
        $stmt->bindValue("key", $key);
        $stmt->execute();
        $found = $stmt->fetch(\PDO::FETCH_ASSOC) !== false;

        return $found;
    }

    public function get($request)
    {
        $key = $this->getRequestKey($request);
        $stmt = $this->_pdo->prepare("SELECT data FROM " . $this->getTableName() . " WHERE key = :key LIMIT 1");
        $stmt->bindValue("key", $key);
        $stmt->execute();

        $serializedData = $stmt->fetchColumn();
        return unserialize($serializedData);
    }

    public function set($request, $data)
    {
        $key = $this->getRequestKey($request);
        $serializedData = serialize($data);

        $stmt = $this->_pdo->prepare("INSERT INTO " . $this->getTableName() . "(key, data) VALUES(:key, :data)");
        $stmt->bindValue("key", $key);
        $stmt->bindValue("data", $serializedData);
        $stmt->execute();
    }

    public function clear()
    {
        $this->_pdo->exec("DELETE FROM " . $this->getTableName());
    }

    public function setNamespace($namespace)
    {
        $this->_namespace = $namespace;
        $this->_pdo->query("CREATE TABLE IF NOT EXISTS " . $this->getTableName() . "(key CHAR(50) PRIMARY KEY NOT NULL, data TEXT NOT NULL)");
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

    protected function getTableName(){
        return $this->_tablePrefix . "_" . $this->_namespace;
    }
}