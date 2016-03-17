<?php
/**
 * Created by PhpStorm.
 * User: Jaskolek
 * Date: 2015-06-09
 * Time: 18:03
 */

namespace Jaskolek\ProxyProvider;


class FileProxyProvider implements ProxyProviderInterface{

    protected $_proxies = [];
    protected $_currentIndex = 0;

    public function __construct($filePath)
    {
        $this->_proxies = file($filePath, FILE_IGNORE_NEW_LINES);
        shuffle($this->_proxies);
    }

    public function getNextProxy($blockedProxyList = [])
    {
        $proxies = array_values(array_diff($this->_proxies, $blockedProxyList));
        if(count($proxies) == 0) throw new \Exception("Ran out of proxies!");

        $proxy = $proxies[$this->_currentIndex % count($proxies)];
        $this->_currentIndex++;
        return $proxy;
    }
}