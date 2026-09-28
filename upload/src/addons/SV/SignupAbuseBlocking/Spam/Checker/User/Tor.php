<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\User;

use SV\SignupAbuseBlocking\Repository\CloudFlare as CloudFlareRepo;
use SV\SignupAbuseBlocking\Spam\IScoringChecker;
use XF\Entity\User as UserAlias;
use XF\Spam\Checker\AbstractProvider;
use XF\Spam\Checker\UserCheckerInterface;

class Tor extends AbstractProvider implements UserCheckerInterface
{
    protected function getType(): string
    {
        return 'SignupAbuseTor';
    }

    public function check(UserAlias $user, array $extraParams = []): void
    {
        if (!($this->checker instanceof IScoringChecker))
        {
            return;
        }

        $torConfig = \XF::options()->svSignupTorBlockingConfig ?? [];
        if (!\is_array($torConfig))
        {
            return;
        }

        $isTor = $this->cloudFlareDetectsTor($torConfig);

        if ($isTor === null)
        {
            try
            {
                $isTor = $this->torProjectLookup($torConfig);
            }
            catch (\Throwable $e)
            {
                \XF::logException($e);
                if (\XF::$developmentMode)
                {
                    throw $e;
                }
                $isTor = null;
            }
        }

        if ($isTor)
        {
            /** @var IScoringChecker $checker */
            $checker = $this->checker;
            $checker->logScore('sv_reg_log.tor_fail', $torConfig['score'], []);
        }
    }

    /**
     * @param array $torConfig
     * @return bool|null
     */
    protected function cloudFlareDetectsTor(array $torConfig): ?bool
    {
        $cloudFlare = CloudFlareRepo::get();
        if (($torConfig['cloudflare'] ?? true) && $cloudFlare->canTrustCloudFlareHeaders())
        {
            return $cloudFlare->getCountry() === 'T1';
        }

        return null;
    }

    protected function torProjectLookup(array $torConfig): ?bool
    {
        $ip = \XF::app()->request()->getIp();
        if (!$ip)
        {
            return false;
        }

        // ipv4 only
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
        {
            return null;
        }

        // add the board canonical url, which may resolve to multiple IPs,
        // so push to the server_ip handling code
        $boardUrl = \XF::options()->boardUrl ?? '';
        if (\strlen($boardUrl) !== 0)
        {
            $parse = @parse_url($boardUrl);
            if (!empty($parse['host']))
            {
                if ($torConfig['serverIp'] ?? false)
                {
                    $torConfig['serverIp'] = '';
                }
                $torConfig['serverIp'] = $parse['host'] . ',' . $torConfig['serverIp'];
            }
        }

        $srvIp = [];
        // lookup process to handle when the server is behind a proxy
        if (\strlen($torConfig['serverIp'] ?? '') !== 0)
        {
            $list = \explode(',', $torConfig['serverIp']);
            foreach ($list as $entry)
            {
                $entry = $entry ? \trim($entry) : '';
                if (\strlen($entry) === 0)
                {
                    continue;
                }
                $hosts = @\gethostbynamel($entry);
                if (\is_array($hosts))
                {
                    foreach ($hosts as $hostIp)
                    {
                        if (\filter_var($hostIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4))
                        {
                            $srvIp[] = $hostIp;
                        }
                    }
                }
            }
        }

        $srvIp = \array_unique($srvIp);

        // check http/https with the tor exist list
        $user_ip = $this->reverseIpParts($ip);
        $portsToCheck = [80, 443];
        foreach ($srvIp as $hostIp)
        {
            foreach ($portsToCheck as $port)
            {
                $query = \sprintf('%s.%s.%s.ip-port.exitlist.torproject.org', $user_ip, $port, $this->reverseIpParts($hostIp));
                if (@\gethostbyname($query) === '127.0.0.2')
                {
                    return true;
                }
            }
        }

        return false;
    }

    protected function reverseIpParts($ip): ?string
    {
        $parts = \explode('.', \trim($ip));
        if (\count($parts) != 4)
        {
            return null;
        }

        $parts = \array_map('\intval', $parts);
        $parts = \array_reverse($parts);

        return \implode('.', $parts);
    }

    public function submit(UserAlias $user, array $extraParams = []): void
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
