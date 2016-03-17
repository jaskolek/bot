<?php
/**
 * Created by PhpStorm.
 * User: jaskolek
 * Date: 2015-02-23
 * Time: 17:05
 */

namespace Jaskolek\Bot\RecordCache;



class DummyRecordCache implements RecordCacheInterface {

    public function has($request)
    {
        return false;
    }

    public function get($request)
    {
        // TODO: Implement get() method.
    }

    public function set($request, $data)
    {
        // TODO: Implement set() method.
    }

    public function clear()
    {
        // TODO: Implement clear() method.
    }

    public function setNamespace($namespace)
    {
        // TODO: Implement setNamespace() method.
    }
}