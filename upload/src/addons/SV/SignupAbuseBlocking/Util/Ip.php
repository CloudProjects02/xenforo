<?php

namespace SV\SignupAbuseBlocking\Util;

use function inet_pton;
use function preg_match;
use function strlen, is_string, explode, intval, strcmp;

class Ip
{
    public static function isLocalIp(string $ip): bool
    {
        $ip = @inet_pton($ip);
        if (strlen($ip) === 4)
        {
            return self::isLocalIpv4($ip);
        }
        else if (strlen($ip) === 16)
        {
            return self::isLocalIpv6($ip);
        }

        return true;
    }

    public static function isLocalIpv4(string $ip): bool
    {
        $ip = (strlen($ip) === 4) ? $ip : @inet_pton($ip);

        $ranges = [
            '0.0.0.0' => 8,
            '10.0.0.0' => 8,
            '100.64.0.0' => 10,
            '127.0.0.0' => 8,
            '169.254.0.0' => 16,
            '172.16.0.0' => 12,
            '192.0.0.0' => 24,
            '192.0.2.0' => 24,
            '192.88.99.0' => 24,
            '192.168.0.0' => 16,
            '198.18.0.0' => 15,
            '198.51.100.0' => 24,
            '203.0.113.0' => 24,
            '244.0.0.0' => 4,
            '233.252.0.0' => 24,
            '240.0.0.0' => 4,
            '255.255.255.255' => 32
        ];

        foreach ($ranges AS $rangeIp => $cidr)
        {
            $rangeIp = inet_pton($rangeIp);
            if (self::ipMatchesCidrRange($ip, $rangeIp, $cidr))
            {
                return true;
            }
        }

        return false;
    }

    public static function isLocalIpv6(string $ip): bool
    {
        $ip = (strlen($ip) === 16 && preg_match('/[^0-9a-f.:]/i', $ip)) ? $ip : @inet_pton($ip);
        if (!self::ipMatchesCidrRange($ip, inet_pton('2000::'), 3))
        {
            return true;
        }

        $ranges = [
            '2001::' => 23,
            '2001:db8::' => 32,
            '2002::' => 16
        ];
        foreach ($ranges AS $rangeIp => $cidr)
        {
            $rangeIp = inet_pton($rangeIp);
            if (self::ipMatchesCidrRange($ip, $rangeIp, $cidr))
            {
                return true;
            }
        }

        return false;
    }

    public static function ipMatchesRanges(string $ip, array $ranges): bool
    {
        // copied from \XF\Http\Request::ipMatchesRanges since it is protected
        $ip = \XF\Util\Ip::convertIpStringToBinary($ip);
        if ($ip === false)
        {
            return false;
        }

        $type = strlen($ip) === 4 ? 'v4' : 'v6';

        if (empty($ranges[$type]))
        {
            return false;
        }

        foreach ($ranges[$type] AS $range)
        {
            if (is_string($range))
            {
                $range = explode('/', $range);
            }

            $rangeIp = \XF\Util\Ip::convertIpStringToBinary($range[0]);
            $cidr = intval($range[1]);

            if (self::ipMatchesCidrRange($ip, $rangeIp, $cidr))
            {
                return true;
            }
        }

        return false;
    }

    public static function ipMatchesCidrRange(string $testIp, string $rangeIp, string$cidr): bool
    {
        $range = \XF\Util\Ip::getIpCidrMatchRange($rangeIp, $cidr);
        if (is_string($range))
        {
            return ($testIp == $range);
        }
        else
        {
            return self::ipMatchesRange($testIp, $range[0], $range[1]);
        }
    }

    /**
     * Simplifies checking if an IP is within a range.
     * All IPs and ranges must be binary encoded.
     *
     * @param string $testIp
     * @param string $lowerBound
     * @param string $upperBound
     *
     * @return bool
     */
    public static function ipMatchesRange(string $testIp, string $lowerBound, string $upperBound): bool
    {
        // stock XF2.2.10 does integer comparisons and not string comparisons, which is not safe
        if (strlen($testIp) !== strlen($lowerBound))
        {
            return false;
        }

        return strcmp($testIp, $lowerBound) >= 0 && strcmp($testIp, $upperBound) <= 0;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
