<?php
namespace Jaskolek\Bot\RecordCache;

interface RecordCacheInterface {

    public function has($request);
    public function get($request);
    public function set($request, $data);
    public function clear();
    public function setNamespace($namespace);
}