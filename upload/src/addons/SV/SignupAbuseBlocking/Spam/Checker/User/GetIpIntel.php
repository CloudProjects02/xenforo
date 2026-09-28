<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use SV\StandardLib\Helper;
use XF\Entity\Option as OptionEntity;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;

class GetIpIntel extends AbstractProvider implements UserCheckerInterface
{
    protected $timeout = 5;

    protected function getType(): string
    {
        return 'SignupAbuseGetIpIntel';
    }

    public function check(UserEntity $user, array $extraParams = []): void
    {
        if (!($this->checker instanceof IScoringChecker))
        {
            return;
        }

        $config = \XF::options()->svSignupGetIpIntelRule ?? [];
        if (!\is_array($config) || !$this->validateConfig($config))
        {
            return;
        }

        /** @var IScoringChecker $checker */
        $checker = $this->checker;

        $ip = \XF::app()->request()->getIp();
        if (!$ip)
        {
            return;
        }

        $asJson = (bool)(\XF::options()->svSignupGetIpIntelAsJson ?? false);
        $api = "{$config['url']}?ip={$ip}&contact={$config['email']}";
        if ($asJson)
        {
            $api .= '&format=json';
        }
        $flags = $config['flags'];
        if ($flags)
        {
            if (\stripos($flags, '&flags=') === false)
            {
                $flags = '&flags=' . $flags;
            }
            $api .= $flags;
        }

        $statusCode = 200;
        $queryResults = $this->httpApiQuery($api, $statusCode, [], true, $asJson, $this->timeout);
        if ($queryResults === null)
        {
            // error, should be pushed to logs already.
            return;
        }
        // rate limiting has been applied
        if ($statusCode === 429)
        {
            if ($config['url'] === 'https://check.getipintel.net/check.php')
            {
                \XF::logError('[SignupAbuseBlocking] Hitting rate-limiting from https://getipintel.net/ consider upgrading to a paid plan, disabling getipintel option', true);
                $this->disableService($config);
            }
            else
            {
                \XF::logError('[SignupAbuseBlocking] Hitting rate-limiting from https://getipintel.net/', true);
            }
            return;
        }

        if (!\is_numeric($queryResults) && !\is_array($queryResults))
        {
            \XF::logError("[SignupAbuseBlocking] Unexpected result for {$api} status:{$statusCode}, " . \var_export($queryResults, true));

            return;
        }

        if (\is_array($queryResults))
        {
            $threadLevel = $queryResults['result'] ?? 0;
        }
        else
        {
            $threadLevel = (float)$queryResults;
        }
        if ($threadLevel < 0)
        {
            $errorCode = (int)$threadLevel;
            // error
            switch ($errorCode)
            {
                // http://getipintel.net/free-proxy-vpn-tor-detection-api/#expected_output
                case -3: // Unroutable address / private address
                    // server miss-configuration! or bgp hijacking
                    \XF::logError('[SignupAbuseBlocking] User registered from an unroutable address/private address. This may be a BGP hijacking, or server configuration is not passing a real IP to XenForo', true);
                    $checker->logScoreModerate('sv_reg_log.getipintel_private');

                    return;
                case -4: // Unable to reach database, most likely the database is being updated. Keep an eye on twitter for more information.
                    \XF::logError('[SignupAbuseBlocking] https://getipintel.net/ is not currently accessible', true);

                    return;
                case -5: // Your connecting IP has been banned from the system or you do not have permission to access a particular service. Did you exceed your query limits? Did you use an invalid email address? If you want more information, please use the contact links below.
                    \XF::logError('[SignupAbuseBlocking] Server appears banned from https://getipintel.net/, disabling getipintel option', true);
                    $this->disableService($config);

                    return;
                case -6: // You did not provide any contact information with your query or the contact information is invalid.
                    \XF::logError('[SignupAbuseBlocking] Invalid contact information setup for querying https://getipintel.net/, disabling getipintel option', true);
                    $this->disableService($config);

                    return;
                case -1: // Invalid no input
                case -2: // Invalid IP address
                default:
                    // should never happen
                    \XF::logError("[SignupAbuseBlocking] Unexpected result for {$api} status:{$statusCode}, disabling getipintel option" . var_export($queryResults, true));
                    $this->disableService($config);
                    return;
            }
        }
        // error returned by API. nfi
        if ($statusCode >= 400)
        {
            \XF::logError("[SignupAbuseBlocking] Unexpected result for {$api} status:{$statusCode}," . var_export($queryResults, true));
        }

        $threadLevelRounded = \round($threadLevel, 4);
        if ($config['highScoreThreshold'] && ($threadLevel >= 1.0 || $threadLevel >= $config['highScoreThreshold']))
        {
            $checker->logScore('sv_reg_log.getipintel_fail', $config['highScore'], ['threatLevel' => $threadLevelRounded]);
        }
        else if ($config['lowScoreThreshold'] && $threadLevel >= $config['lowScoreThreshold'])
        {
            $checker->logScore('sv_reg_log.getipintel_fail', $config['lowScore'], ['threatLevel' => $threadLevelRounded]);
        }
        else
        {
            $checker->logScoreAccept('sv_reg_log.getipintel_fail', ['threatLevel' => $threadLevelRounded]);
        }
    }

    /**
     * @param string   $url
     * @param int      $statusCode
     * @param string[] $headers
     * @param bool     $cache
     * @param bool     $json
     * @param float    $timeLimit
     * @param int      $bytesLimit
     * @return array|null|string
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function httpApiQuery(string $url, int &$statusCode, array $headers = [], bool $cache = true, bool $json = true, float $timeLimit = 10, int $bytesLimit = 1048576)
    {
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
            $statusCode = $response->getStatusCode();

            $body = (string)$response->getBody();

            return $json ? @\json_decode($body, true) : $body;
        }
        catch (\Throwable $e)
        {
            $statusCode = 500;
            \XF::logException($e);
            if (\XF::$developmentMode)
            {
                throw $e;
            }

            return null;
        }
    }

    public function submit(UserEntity $user, array $extraParams = [])
    {
    }

    protected function disableService(array $portScanConfig)
    {
        $portScanConfig['enable'] = false;
        $option = Helper::find(OptionEntity::class, 'svSignupGetIpIntelRule');
        if ($option !== null)
        {
            $option->option_value = $portScanConfig;
            $option->saveIfChanged();
        }
    }

    /**
     * @param array $config
     * @return bool
     */
    protected function validateConfig(array $config): bool
    {

        foreach ([
                     'enable',
                     'url',
                     'email',
                 ] as $key)
        {
            if (empty($config[$key]))
            {
                return false;
            }
        }

        foreach ([
                     'flags',
                     'lowScore',
                     'lowScoreThreshold',
                     'highScore',
                     'highScoreThreshold',
                 ] as $key)
        {
            if (!isset($config[$key]))
            {
                return false;
            }
        }

        return true;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
