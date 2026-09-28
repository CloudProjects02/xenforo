<?php
/**
 * @noinspection PhpMultipleClassDeclarationsInspection
 */

namespace SV\SignupAbuseBlocking\Spam;

use ArrayObject;
use XF\App;
use XF\Options as XFOptions;

abstract class ApiProvider
{
    /**
     * @var App
     */
    protected $app;
    /**
     * @deprecated
     * @var XFOptions
     */
    protected $xfOptions;

    /** @noinspection PhpDeprecationInspection */
    public function __construct(App $app, ArrayObject $xfOptions)
    {
        $this->app = $app;
        $this->xfOptions = $xfOptions;
    }

    abstract public function isEnabled(): bool;

    /**
     * @param string   $url
     * @param string[] $headers
     * @param bool     $cache
     * @param bool     $json
     * @param float    $timeLimit
     * @param int      $bytesLimit
     * @return array|null|string
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function httpApiQuery(string $url, array $headers = [], bool $cache = true, bool $json = true, float $timeLimit = 10, int $bytesLimit = 1048576)
    {
        $checker = $this->app->spam()->userChecker();
        // in minutes, default 12 hours
        $cacheTime = (int)(\XF::options()->svSignupAbuseBlockingQueryCacheTime ?? 12 * 60) * 60;
        $key = 'svSignupAbuseBlocking_' . \md5($url . $cacheTime . \json_encode($headers));
        $cacheObj = $cache ? $this->app->cache('spamCheckCache') : null;
        if ($cacheObj)
        {
            $body = $cacheObj->fetch($key);
            if ($body !== false)
            {
                return $json ? @\json_decode($body, true) : $body;
            }
        }
        else
        {
            $result = $checker->getRegistrationResultFromCache($key);
            if ($result !== false)
            {
                if ($json)
                {
                    $result = @\json_decode($result, true);
                }

                return $result;
            }
        }

        try
        {
            $response = $this->app->http()->reader()->getUntrusted($url,
                [
                    'time'  => $timeLimit,
                    'bytes' => $bytesLimit
                ], null, [
                    'headers' => $headers,
                ]);

            if (!$response)
            {
                return null;
            }

            if ($response->getStatusCode() >= 300)
            {
                \XF::logError('Unexpected API response, HTTP status:' .$response->getStatusCode() . ', Response:' . $response->getBody());

                return null;
            }

            $body = (string)$response->getBody();

            if ($cacheObj)
            {
                $cacheObj->save($key, $body, \max(0, $cacheTime));
            }
            else
            {
                $checker->cacheRegistrationResponse($key, $body, 'allowed');
            }

            if ($json)
            {
                $body = @\json_decode($body, true);
            }

            return $body;
        }
        catch (\Throwable $e)
        {
            \XF::logException($e);
            if (\XF::$developmentMode)
            {
                throw $e;
            }

            return null;
        }
    }

    /**
     * @param string $zone
     * @param int    $recordType
     * @param string $splitter
     * @param bool   $singleResult
     * @param bool   $cache
     * @return string|array|null
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function dnsApiQuery(string $zone, int $recordType, string $splitter = '', bool $singleResult = true, bool $cache = true)
    {
        switch ($recordType)
        {
            case \DNS_TXT:
                $resultField = 'txt';
                break;
            case \DNS_A:
                $resultField = 'ip';
                break;
            case \DNS_AAAA:
                $resultField = 'ipv6';
                break;
            case \DNS_CNAME:
            case \DNS_PTR:
            case \DNS_NS:
            case \DNS_MX:
                $resultField = 'target';
                break;
            case \DNS_CAA:
            case \DNS_A6:
            case \DNS_HINFO:
            case \DNS_SOA:
            case \DNS_SRV:
            case \DNS_NAPTR:
            case \DNS_ALL:
            case \DNS_ANY:
            default:
                $resultField = null;
                break;
        }
        if ($resultField === null)
        {
            throw new \LogicException('Not supported');
        }
        $cacheTime = (\XF::options()->svSignupAbuseBlockingDnsQueryCacheTime ?? 15) * 60;
        $key = 'svSignupAbuseBlocking_' . \md5($zone . $recordType . $splitter . $singleResult . $cacheTime);
        $cacheObj = $cache ? $this->app->cache('spamCheckCache') : null;
        if ($cacheObj)
        {
            $body = $cacheObj->fetch($key);
            if ($body !== false)
            {
                return $singleResult ? \reset($body) : $body;
            }
        }

        try
        {
            $results = @\dns_get_record($zone, $recordType);
        }
        catch (\Throwable $e)
        {
            \XF::logException($e);
            if (\XF::$developmentMode)
            {
                throw $e;
            }
            $results = false;
        }

        $parsedBody = [];
        if (\is_array($results))
        {
            foreach($results as $result)
            {
                $body = $result[$resultField] ?? '';

                if (\is_string($body) && \strlen($body) !== 0 && \strlen($splitter) !== 0)
                {
                    $parsedBody[] = \preg_split($splitter, $body);
                }
                else
                {
                    $parsedBody[] = $body;
                }
            }
        }

        if ($cacheObj)
        {
            $cacheObj->save($key, $parsedBody, \max(0, $cacheTime));
        }

        return $singleResult ? \reset($parsedBody) : $parsedBody;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
