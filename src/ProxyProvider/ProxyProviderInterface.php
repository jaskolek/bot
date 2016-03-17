<?php
/**
 * Created by PhpStorm.
 * User: Jaskolek
 * Date: 2015-05-08
 * Time: 16:24
 */

namespace Jaskolek\ProxyProvider;


interface ProxyProviderInterface {

    public function getNextProxy($blockedProxyList = []);
}