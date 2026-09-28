<?php

namespace SV\SignupAbuseBlocking\XF\Admin\Controller;

use SV\SignupAbuseBlocking\Spam\AsnProvider;
use SV\SignupAbuseBlocking\Spam\Checker\GeoIp\AsnLookupFallback;
use SV\SignupAbuseBlocking\Spam\GeoIpProvider;
use SV\SignupAbuseBlocking\Util\Ip as SvIp;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;
use function array_filter;
use function array_values;
use function count;
use function explode;
use function get_class;
use function inet_pton;

/**
 * @extends \XF\Admin\Controller\Tools
 */
class Tools extends XFCP_Tools
{
    protected function svGetHeaderFound(string $headerName): Phrase
    {
        return $this->request()->getServer($headerName) !== false
            ? \XF::phrase('svSignupAbuseBlocking_site_header_found')
            : \XF::phrase('svSignupAbuseBlocking_site_header_not_found');
    }

    public function actionTestAsn(): AbstractReply
    {
        $this->assertAdminPermission('svAntiSpam');
        $this->setSectionContext('svTestAsn');

        $errors = [];
        $results = [];
        $checks = [
            'server' => [
                'REMOTE_ADDR' => $this->svGetHeaderFound('REMOTE_ADDR'),
            ],
        ];

        $ip = '';
        $ipRaw = $this->filter('ip', 'str', '');
        if ($ipRaw !== '')
        {
            $output = @inet_pton($ipRaw);
            if ($output === false)
            {
                $errors[] = \XF::phrase('svSignupAbuseBlocking_invalid_ip');
            }
            else
            {
                $ip = $ipRaw;
            }
        }
        if ($ip !== '' && SvIp::isLocalIp($ip))
        {
            $errors[] = \XF::phrase('svSignupAbuseBlocking_local_ip');
        }

        $providers = [];
        $spamContainer = \XF::app()->spam();
        $spamContainer->get('contentProviders');
        if ($spamContainer->offsetExists('asnProviders'))
        {
            $providers = $spamContainer->get('asnProviders');
        }
        if (count($providers) === 0)
        {
            $errors[] = \XF::phrase('svSignupAbuseBlocking_no_asn_providers');
        }

        if (count($errors) === 0 && $ip !== '')
        {
            $extension = \XF::extension();
            /** @var AsnProvider[] $providers */
            foreach ($providers as $provider)
            {
                $class = $extension->resolveExtendedClassToRoot(get_class($provider));
                $parts = explode('\\', $class);
                $class = $parts[count($parts) - 1];

                $asnResult = null;
                try
                {
                    $asnResult = $provider->resolveIpToAsn($ip);
                }
                catch (\Throwable $e)
                {
                    $errors[] = $class .': '. $e->getMessage();
                }
                if ($asnResult === null)
                {
                    $results['asn'][$class] = \XF::phrase('svSignupAbuseBlocking_resolve_failed');
                }
                else
                {
                    $results['asn'][$class] = [
                        'asn' => $asnResult[0] ?? null,
                        'org' => $asnResult[1] ?? null,
                        'country' => $asnResult[2] ?? null,
                    ];
                }
            }
        }

        $viewParams = [
            'ip'     => $ipRaw,
            'errors' => $errors,
            'checks' => $checks,
            'results' => $results,
        ];

        return $this->view('XF\Tools:Test\GeoIp', 'svSignupAbuseBlocking_test_asn', $viewParams);
    }

    public function actionTestGeoIP(): AbstractReply
    {
        $this->assertAdminPermission('svAntiSpam');
        $this->setSectionContext('svTestGeoIp');

        $errors = [];
        $results = [];
        $checks = [
            'server'     => [
                'REMOTE_ADDR' => $this->svGetHeaderFound('REMOTE_ADDR'),
            ],
            'cloudflare' => [
                'CF_CONNECTING_IP' => $this->svGetHeaderFound('HTTP_CF_CONNECTING_IP'),
                'CF_RAY'           => $this->svGetHeaderFound('HTTP_CF_RAY'),
                'CF_VISITOR'       => $this->svGetHeaderFound('HTTP_CF_VISITOR'),
                'CF_IPCOUNTRY'     => $this->svGetHeaderFound('HTTP_CF_IPCOUNTRY'),
            ],
        ];

        $ip = '';
        $ipRaw = $this->filter('ip', 'str', '');
        if (!$this->request()->exists('ip'))
        {
            $ipRaw = $this->request()->getIp();
        }
        if ($ipRaw !== '')
        {
            $output = @inet_pton($ipRaw);
            if ($output === false)
            {
                $errors[] = \XF::phrase('svSignupAbuseBlocking_invalid_ip');
            }
            else
            {
                $ip = $ipRaw;
            }
        }
        if ($ip !== '' && SvIp::isLocalIp($ip))
        {
            $errors[] = \XF::phrase('svSignupAbuseBlocking_local_ip');
        }

        $providers = [];
        $spamContainer = \XF::app()->spam();
        $spamContainer->get('contentProviders');
        if ($spamContainer->offsetExists('geoIpProviders'))
        {
            $providers = array_values($spamContainer->get('geoIpProviders'));

            $providers = array_filter($providers, function (GeoIpProvider $provider) {
                return !($provider instanceof AsnLookupFallback);
            });
        }
        if (count($providers) === 0)
        {
            $errors[] = \XF::phrase('svSignupAbuseBlocking_no_geoip_providers');
        }

        if (count($errors) === 0 && $ip !== '')
        {
            $extension = \XF::extension();
            /** @var GeoIpProvider[] $providers */
            foreach ($providers as $provider)
            {
                $class = $extension->resolveExtendedClassToRoot(get_class($provider));
                $parts = explode('\\', $class);
                $class = $parts[count($parts) - 1];

                $country = null;
                try
                {
                    $country = $provider->resolveGeoIp($ip);
                }
                catch (\Throwable $e)
                {
                    $errors[] = $class .': '. $e->getMessage();
                }
                if ($country === null)
                {
                    $country = \XF::phrase('svSignupAbuseBlocking_resolve_failed');
                }
                else if ($country === 'XX')
                {
                    $country = \XF::phrase('svSignupAbuseBlocking_resolve_unknown');
                }
                else if ($country === 'T1')
                {
                    $country = \XF::phrase('svSignupAbuseBlocking_resolve_tor');
                }
                $results['geoip'][$class] = $country;
            }
        }

        $viewParams = [
            'ip'     => $ipRaw,
            'errors' => $errors,
            'checks' => $checks,
            'results' => $results,
        ];

        return $this->view('XF\Tools:Test\GeoIp', 'svSignupAbuseBlocking_test_geoip', $viewParams);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
