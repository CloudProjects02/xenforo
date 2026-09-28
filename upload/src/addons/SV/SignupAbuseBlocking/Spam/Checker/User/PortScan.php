<?php
/**
 * @noinspection RedundantSuppression
 * @noinspection DuplicatedCode
 */

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Repository\ScoreMatch as ScoreMatchRepo;
use SV\SignupAbuseBlocking\Repository\ScoreMatchableInterface;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserEntity;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;
use XF\Util\Arr;

class PortScan extends AbstractProvider implements UserCheckerInterface, ScoreMatchableInterface
{
    protected function getType(): string
    {
        return 'SignupAbusePortScan';
    }

    public function check(UserEntity $user, array $extraParams = []): void
    {
        // check various socket functions aren't disabled as some environments are really badly broken
        if (!function_exists('socket_create') ||
            !function_exists('socket_clear_error') ||
            !function_exists('socket_connect') ||
            !function_exists('socket_set_nonblock') ||
            !function_exists('socket_close') ||
            !function_exists('socket_last_error'))
        {
            return;
        }

        if (!($this->checker instanceof IScoringChecker))
        {
            return;
        }

        if (!(\XF::options()->svSignupOpenPortScanEnable ?? false))
        {
            return;
        }

        $portScanConfig = \XF::options()->svSignupOpenPortBlockingRule ?? '';
        if (\strlen($portScanConfig) === 0)
        {
            return;
        }

        $ip = \XF::app()->request()->getIp();
        if (!$ip)
        {
            return;
        }

        $portsToScan = $this->buildPortScanList($portScanConfig);
        if (!$portsToScan)
        {
            return;
        }

        $timeout = 1.0;
        $portScanProxy = @\trim(\XF::options()->svSignupOpenPortScanProxy ?? '');
        if ($portScanProxy)
        {
            $ports = implode(',', $portsToScan);
            $url = "{$portScanProxy}?ip={$ip}&ports={$ports}&timeout={$timeout}";

            $openPorts = $this->httpApiQuery($url, [], false, true, $timeout + 2);
        }
        else
        {
            $openPorts = $this->scanPorts($ip, $portsToScan, $timeout);
        }
        if (!$openPorts || !\is_array($openPorts))
        {
            return;
        }

        ScoreMatchRepo::get()->evaluateRules($openPorts, $portScanConfig, $this, true, false);
    }

    public function onRuleMatch($input, $score, string $matchRule, string $matchInput): bool
    {
        /** @var IScoringChecker $checker */
        $checker = $this->checker;
        switch (\strval($score))
        {
            case 'reject':
                $checker->logScoreReject('sv_reg_log.port_scan_fail', ['port' => $matchInput]);
                break;
            case 'moderate':
                $checker->logScoreModerate('sv_reg_log.port_scan_fail', ['port' => $matchInput]);
                break;
            case '0':
                $checker->logScoreAccept('sv_reg_log.port_scan_fail', ['port' => $matchInput]);
                break;
            default:
                $checker->logScore('sv_reg_log.port_scan_fail', $score, ['port' => $matchInput]);
                break;
        }

        // keep going
        return true;
    }

    /**
     * @param string   $url
     * @param string[] $headers
     * @param bool     $cache
     * @param bool     $json
     * @param float    $timeLimit
     * @param int      $bytesLimit
     * @return array|null|string
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function httpApiQuery(string $url, array $headers = [], bool $cache = true, bool $json = true, float $timeLimit = 10, int $bytesLimit = 1048576)
    {
        try
        {
            $response = $this->app->http()->reader()->get($url,
                [
                    'time'  => $timeLimit,
                    'bytes' => $bytesLimit
                ], null, [
                    'headers' => $headers,
                ]);

            if (!$response || $response->getStatusCode() != 200)
            {
                return null;
            }

            $body = (string)$response->getBody();
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
     * @param string $portScanConfig
     * @return string[]
     */
    protected function buildPortScanList(string $portScanConfig): array
    {
        $ports = [];
        // build the list of ports to work with
        $rules = \mb_strtolower($portScanConfig);
        $splitRules = Arr::stringToArray($rules, '/\r?\n/');
        foreach ($splitRules as $splitRule)
        {
            $entry = \explode('|', $splitRule, 2);
            if (\count($entry) !== 2)
            {
                continue;
            }
            $entry = \array_map('\trim', $entry);
            /** @noinspection PhpUnusedLocalVariableInspection */
            [$score, $port] = $entry;

            if (!\is_numeric($port))
            {
                $port = \getservbyname($port, 'tcp');
            }

            $port = (int)$port;

            if ($port <= 0)
            {
                continue;
            }

            $ports[$port] = false;
        }

        return \array_keys($ports);
    }

    /**
     * Should be kept in sync with scan_proxy.php, after stripping of XF specific bits
     * Note; timeout should be a rough multiple of 0.25s chunks
     *
     * @param string $ip
     * @param int[]  $portsToScan
     * @param float  $timeout
     * @return array
     */
    protected function scanPorts(string $ip, array $portsToScan, float $timeout): array
    {
        $isIpV6 = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

        $ports = [];
        try
        {
            // open ports in async mode to start scanning for ports
            $sockets = [];
            foreach ($portsToScan as $port)
            {
                if (isset($sockets[$port]))
                {
                    continue;
                }
                $ports[$port] = false;

                @socket_clear_error();

                $sock = @socket_create($isIpV6 ? AF_INET6 : AF_INET, SOCK_STREAM, SOL_TCP);
                if (!$sock)
                {
                    continue;
                }
                @socket_set_nonblock($sock);
                @socket_connect($sock, $ip, $port);

                $errno = @socket_last_error();
                if (($errno === SOCKET_EALREADY) || ($errno === SOCKET_EINPROGRESS) || !$errno)
                {
                    $sockets[$port] = $sock;
                }
                else
                {
                    @socket_close($sock);
                }
            }

            // wait for timeout or the socket opens
            $start = microtime(true);
            while (($sockets) && (microtime(true) - $start < $timeout))
            {
                $null = null;
                $write = $sockets;
                // socket_select preserve's key/value associations, so e can directly update the port list
                socket_select($null, $write, $null, 0, 250000);
                foreach ($write as $k => $sock)
                {
                    $errno = socket_get_option($sock, SOL_SOCKET, SO_ERROR);

                    if ($errno == 0)
                    {
                        $ports[$k] = true;
                    }
                    else
                    {
                        unset($ports[$k]);
                    }

                    unset($sockets[$k]);
                    @socket_close($sock);
                }
            }
        }
        finally
        {

            foreach ($sockets as $sock)
            {
                @socket_close($sock);
            }
        }

        $filteredPorts = [];
        foreach ($ports as $port => $isOpen)
        {
            if ($isOpen)
            {
                $filteredPorts[] = $port;
            }
        }

        return $filteredPorts;
    }

    public function submit(UserEntity $user, array $extraParams = [])
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
