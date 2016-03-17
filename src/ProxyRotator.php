<?php
/**
 * Created by PhpStorm.
 * User: Jaskolek
 * Date: 2015-06-05
 * Time: 23:05
 */

namespace Jaskolek;


use Jaskolek\ProxyProvider\ProxyProviderInterface;

/**
 * Class ProxyRotator
 * @package Application\Application
 */
class ProxyRotator {

    /**
     * @var ProxyProviderInterface
     */
    protected $_proxyProvider;

    /**
     * @var
     */
    protected $_cycleSize;

    /**
     * @var int
     */
    protected $_cycleIndex = 0;

    /**
     * @var
     */
    protected $_proxy;

    /**
     * @var
     */
    protected $_blockedProxyList = [];

    protected $_blockedCycle;
    /**
     * @param ProxyProviderInterface $proxyProvider
     * @param int $cycleSize
     */
    function __construct(ProxyProviderInterface $proxyProvider, $cycleSize = 15, $blockedCycle = 5)
    {
        $this->_proxyProvider = $proxyProvider;
        $this->_cycleSize = $cycleSize;
        $this->_proxy = $this->_proxyProvider->getNextProxy();
        $this->_blockedCycle = $blockedCycle;
    }


    /**
     * @return mixed
     */
    public function getProxy()
    {
        if($this->_cycleIndex >= $this->_cycleSize){
            $this->reset();
        };
        $this->_cycleIndex++;

        return $this->_proxy;
    }

    public function reset(){
        try {
            $this->_proxy = $this->_proxyProvider->getNextProxy($this->_blockedProxyList);
        }catch(\Exception $e){
            if($this->_blockedCycle > 0){
                $this->_blockedCycle--;
                $this->_blockedProxyList = [];
            }else{
                throw $e;
            }
        }
        $this->_cycleIndex = 0;
    }

    public function blockProxy($proxy)
    {
        $this->_blockedProxyList[] = $proxy;
        $this->reset();
    }
}