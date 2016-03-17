<?php
/**
 * Created by PhpStorm.
 * User: jaskolek
 * Date: 2015-02-20
 * Time: 13:54
 */

namespace Jaskolek\Bot;


use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Jaskolek\Bot\RecordCache\FileRecordCache;
use Jaskolek\Bot\RecordCache\RecordCacheInterface;
use Jaskolek\ProxyRotator;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\MemoryPeakUsageProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Bot
 * @package Jaskolek\Utils\Bot
 */
class Bot
{


    /**
     * @var int
     */
    protected $_poolSize;

    /**
     * @var RecordCacheInterface
     */
    protected $_recordCache;

    /**
     * @var Client
     */
    protected $_client;

    /**
     * @var Logger
     */
    protected $_logger;

    /**
     * @var ProxyRotator
     */
    protected $_proxyRotator;

    protected $_cookieJars = [];

    /**
     * @param Client $client
     * @param RecordCacheInterface $recordCache
     * @param Logger $logger
     * @param ProxyRotator $proxyRotator
     * @param int $poolSize
     */
    function __construct(Client $client = null, RecordCacheInterface $recordCache = null, Logger $logger = null, ProxyRotator $proxyRotator = null, $poolSize = 25)
    {
        $this->_poolSize = $poolSize;
        $this->_recordCache = $recordCache;
        $this->_client = $client;
        $this->_logger = $logger;
        $this->_proxyRotator = $proxyRotator;

        if ($this->_recordCache == null) $this->_recordCache = $this->getDefaultRecordCache();
        if ($this->_client == null) $this->_client = $this->getDefaultClient();
        if ($this->_logger == null) $this->_logger = $this->getDefaultLogger();
    }

    /**
     * @return Logger
     */
    public function getDefaultLogger()
    {
        $logger = new Logger("log");

        $stream = new StreamHandler("php://stdout");
        $stream->pushProcessor(new MemoryPeakUsageProcessor());
        $stream->pushProcessor(new MemoryUsageProcessor());

        $logger->pushHandler($stream, Logger::INFO);

        return $logger;
    }

    /**
     * @param $namespace
     */
    public function clearCache($namespace)
    {
        $this->_recordCache->setNamespace($namespace);
        $this->_recordCache->clear();
    }

    /**
     * @return RecordCacheInterface
     */
    protected function getDefaultRecordCache()
    {
        return new FileRecordCache("cache.txt");
    }

    /**
     * @return mixed
     */
    protected function getDefaultClient()
    {
        $client = new Client([
            "headers" => [
                "Accept" => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
                "Accept-Encoding" => "gzip, deflate, sdch",
                "Accept-Language" => "pl-PL,pl;q=0.8,en-US;q=0.6,en;q=0.4",
                "Cache-Control" => "no-cache",
                "Connection" => "keep-alive",
                "Pragma" => "no-cache",
                "User-Agent" => "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.111 Safari/537.36"
            ],
            'debug' => false,
            "verify" => false,
            "cookies" => true
        ]);

        return $client;
    }

    /**
     * @return int
     */
    public function getPoolSize()
    {
        return $this->_poolSize;
    }

    /**
     * @return RecordCacheInterface
     */
    public function getRecordCache()
    {
        return $this->_recordCache;
    }

    /**
     * @return Client
     */
    public function getClient()
    {
        return $this->_client;
    }


    public function send($requestList, callable $callback, $namespace = "default")
    {
        $recordList = [];
        $requestListChunks = array_chunk($requestList, $this->_poolSize);
        $chunkTimeListSize = 10;
        $chunkTimeList = [];
        foreach ($requestListChunks as $key => $requestListChunk) {
            $start = microtime(true);
            $chunkRecordList = $this->sendChunk($requestListChunk, $callback, $namespace);
            foreach ($chunkRecordList as $record) {
                $recordList[] = $record;
            }
            $chunkTime = microtime(true) - $start;
            $chunkTimeList[] = $chunkTime;
            if (count($chunkTimeList) > $chunkTimeListSize) array_shift($chunkTimeList);
            $avg = round(array_sum($chunkTimeList) / count($chunkTimeList));

            $est = ceil((count($requestListChunks) - $key) * $avg);
            $this->_logger->info("Scraped " . ($key + 1) . "/" . count($requestListChunks) . ' chunks. AVG chunk time: ' . $avg . '/s. Est. timeleft:' . $est . ' - ' . date('Y-m-d H:i:s', time() + $est));
        }
        return $recordList;
    }

    /**
     * @param $requestList
     * @param callable $callback
     * @param string $namespace
     * @return array
     */
    public function sendChunk($requestList, callable $callback, $namespace = "default")
    {

        $preparedRequestDataList = $this->prepareRequestList($requestList);

        $requestList = [];
        $recordList = [];

        $this->_recordCache->setNamespace($namespace);
        /** @var Request $request */
        foreach ($preparedRequestDataList as $requestData) {
            if ($this->_recordCache->has($requestData)) {
                $cachedData = $this->_recordCache->get($requestData);
                $this->_logger->addInfo("Fetched " . count($cachedData) . " from CACHE. Url: " . $requestData["request"]->getUri());
                foreach ($cachedData as $record) {
                    $recordList[] = $record;
                }
                $recordList += $cachedData;
            } else {
//                $requestList[] = $requestData;
            }
        }


        //now we have request list ready to send
        $promiseList = [];
        foreach ($requestList as $requestData) {
            $request = $requestData["request"];

            try {
                $promiseList[] = $this->_client->sendAsync($request, [
                    "proxy" => $requestData["proxy"],
                    "cookies" => $requestData["cookies"]
                ])->then(
                    function (ResponseInterface $response) use ($requestData, $callback, &$recordList) {
                        try {
                            $newRecordList = $callback($requestData["request"], $response, $requestData);
                            $this->_recordCache->set($requestData, $newRecordList);
                            $this->_logger->addInfo("Proxy: " . $requestData["proxy"] . ". Fetched " . count($newRecordList) . " from SITE. Url: " . $requestData["request"]->getUri());
                            $recordList = array_merge($recordList, $newRecordList);
                        } catch (\Exception $e) {
                            $this->_logger->addWarning("Proxy: " . $requestData["proxy"] . ". " . $requestData["request"]->getUri() . ". Error - can not parse!! " . $e->getMessage());
                        }
                    },
                    function (\Exception $exception) use ($requestData) {
                        $this->_proxyRotator->blockProxy($requestData["proxy"]);
                        $this->_logger->addWarning("Proxy: " . $requestData["proxy"] . ". " . $requestData["request"]->getUri() . ". Error! " . $exception->getMessage());
                    });

            } catch (\Exception $e) {
                $this->_proxyRotator->blockProxy($requestData["proxy"]);
                $this->_logger->addWarning("Proxy: " . $requestData["proxy"] . ". " . $requestData["request"]->getUri() . ". Error! " . $e->getMessage());

            }
            if (count($promiseList) > $this->_poolSize) {
                try {
                    \GuzzleHttp\Promise\unwrap($promiseList);
                    $promiseList = [];
                } catch (\Exception $e) {
                    $this->_logger->addWarning("Error during requests! " . $e->getMessage() . "\n" . $e->getTraceAsString());
                }
            }
        }

        \GuzzleHttp\Promise\unwrap($promiseList);
        return $recordList;
    }

    /**
     * @param $requestList
     * @return Request[]
     */
    protected function prepareRequestList($requestList)
    {
        $requestList = array_map(function ($request) {
            if ($request instanceof Request) {
                $requestData = [];
                $requestData['request'] = $request;
            } else if (is_string($request)) {
                $requestData = [];
                $requestData["request"] = new Request("GET", $request);
            } else {
                throw new \Exception("Unknown type of request!");
            }

            if ($this->_proxyRotator !== null) {
                $proxy = $this->_proxyRotator->getProxy();
            } else {
                $proxy = null;
            }
            if (!isset($this->_cookieJars[$proxy])) {
                $this->_cookieJars[$proxy] = new CookieJar();
            }

            $requestData["proxy"] = $proxy;
            $requestData["cookies"] = $this->_cookieJars[$proxy];

            return $requestData;
        }, $requestList);
        return $requestList;
    }

    /**
     * @return Logger
     */
    public function getLogger()
    {
        return $this->_logger;
    }


}