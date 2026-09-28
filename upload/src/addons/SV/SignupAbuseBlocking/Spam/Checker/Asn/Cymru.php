<?php

namespace SV\SignupAbuseBlocking\Spam\Checker\Asn;

use SV\SignupAbuseBlocking\Spam\AsnProvider;
use SV\StandardLib\Helper;

class Cymru extends AsnProvider
{
    public function isEnabled(): bool
    {
        return !empty($this->asnConfig['cymru']);
    }

    public function resolveIpToAsn(string $ip): ?array
    {
        if (\filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6))
        {
            $addr = \inet_pton($ip);
            $unpack = \unpack('H*hex', $addr);
            $hex = $unpack['hex'];
            $parts = \array_reverse(\str_split($hex));
            $host = '%s.origin6.asn.cymru.com';
        }
        else
        {
            $parts = \explode('.', trim($ip));
            if (\count($parts) !== 4)
            {
                return null;
            }

            $parts = \array_map('\intval', $parts);
            $parts = \array_reverse($parts);
            $host = '%s.origin.asn.cymru.com';
        }

        $query = \sprintf($host, \implode('.', $parts));

        $parts = $this->queryCymruDnsTxt($query);

        //  $ dig +short 31.108.90.216.origin.asn.cymru.com TXT
        // "23028 | 216.90.108.0/24 | US | arin | 1998-09-25"
        $asNumber = (int)($parts[0] ?? 0);
        if (!$asNumber)
        {
            return null;
        }

        $asCountry = $parts[2] ?? null;

        // $ dig +short AS23028.asn.cymru.com TXT
        // "23028 | US | arin | 2002-01-04 | TEAM-CYMRU - Team Cymru Inc., US"
        $query = 'AS' . $asNumber . '.asn.cymru.com';
        $parts = $this->queryCymruDnsTxt($query);
        $asName = $parts[4] ?? 'UNKNOWN';

        return [$asNumber, $asName, $asCountry];
    }

    /**
     * https://www.team-cymru.com/ip-asn-mapping
     * uses text records that are | delimited
     *
     * @param $query
     * @return array
     * @noinspection PhpUnnecessaryLocalVariableInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    protected function queryCymruDnsTxt($query): array
    {
        $body = $this->dnsApiQuery($query, DNS_TXT, '/\|/');

        if (!\is_array($body))
        {
            return [];
        }

        $parts = \array_map('\trim', $body);

        return $parts;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public function isNonInteractive(): bool
    {
        // if max-mind is configured, avoid slow DNS queries for bulk-updates
        $maxMind = Helper::newExtendedClass(MaxMind::class, $this->app, \XF::options(), $this->asnConfig);

        if ($maxMind->isEnabled() && $maxMind->isNonInteractive())
        {
            return false;
        }

        return true;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
