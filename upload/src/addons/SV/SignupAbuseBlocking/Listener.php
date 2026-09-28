<?php

namespace SV\SignupAbuseBlocking;

use SV\SignupAbuseBlocking\Spam\ApiProvider;
use SV\SignupAbuseBlocking\Spam\Checker\GeoIp\AsnLookupFallback;
use SV\StandardLib\Helper;
use XF\App;
use XF\Container;
use XF\Entity\OptionGroup as OptionGroupEntity;
use XF\Phrase;
use XF\SubContainer\Spam;
use XF\Template\Templater;

abstract class Listener
{
    /** @noinspection PhpUnusedParameterInspection */
    /** @noinspection PhpParameterByRefIsNotUsedAsReferenceInspection */
    public static function preOptionsRender(Templater $templater, string &$type, string &$template, string &$name, array &$arguments, array &$globalVars)
    {
        /** @var OptionGroupEntity $group */
        $group = $arguments['group'] ?? null;
        if ($group && $group->group_id === 'svSignupAbuseBlocking')
        {
            $template = 'svSignupAbuseBlocking_option_macros';
        }
    }

    /** @noinspection PhpUnusedParameterInspection */
    public static function errorTemplatePreRender(Templater $templater, string &$type, string &$template, array &$params)
    {
        $error = $params['error'] ?? null;
        if ($error instanceof Phrase)
        {
            $name = $error->getName();
            switch($name)
            {
                case 'your_account_has_been_rejected':
                case 'your_account_has_been_rejected_for_following_reason_x':
                    $params['svShowLogoutLink'] = \XF::options()->svAllowRejectedLogout ?? false;
                    break;
                case 'you_have_been_banned':
                case 'you_have_been_banned_for_following_reason_x':
                    $params['svShowLogoutLink'] = \XF::options()->svAllowBannedLogout ?? false;
                    break;
            }
        }
    }

    public static function initProviders(string $event, string $configKey, string $formatter, Spam $container, Container $parentContainer, ?callable $lastResortProvider): array
    {
        $providerClassNames = [];
        $app = \XF::app();
        $app->fire($event, [$container, $parentContainer, &$providerClassNames]);
        if ($lastResortProvider !== null)
        {
            $lastResortProvider($container, $parentContainer, $providerClassNames);
        }
        // expand and make unique
        $classnames = [];
        foreach ($providerClassNames AS $className)
        {
            $expanded = \XF::stringToClass($className, $formatter);
            $classnames[\strtolower($expanded)] = $expanded;
        }
        $providerClassNames = \array_values($classnames);

        /** @var array<ApiProvider> $providers */
        $providers = [];
        $options = $app->options();
        $config = $options->offsetGet($configKey);
        foreach ($providerClassNames AS $className)
        {
            /** @var ApiProvider $provider */
            $provider = Helper::newExtendedClass($className, $app, $options, $config);
            if ($provider->isEnabled())
            {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    /**
     * @param Spam      $container
     * @param Container $parentContainer
     * @param array<class-string> $providers
     * @return void
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public static function spamContentProviders(Spam $container, Container $parentContainer, array &$providers)
    {
        static::initAdditionalProviders($container, $parentContainer);

        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Content\LinkChecker::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Content\RegSpamPhrases::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Content\AsnCheck::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Content\GeoIpCheck::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Content\LanguageCheck::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Content\TimezoneCheck::class;
    }

    /**
     * @param Spam      $container
     * @param Container $parentContainer
     * @param array<class-string> $providers
     * @return void
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public static function spamUserProviders(Spam $container, Container $parentContainer, array &$providers)
    {
        static::initAdditionalProviders($container, $parentContainer);

        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\MultipleAccount::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\Asn::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\GeoIp::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\Tor::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\Username::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\Email::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\Hostname::class;
        if (\XF::options()->svSignupOpenPortScanEnable ?? false)
        {
            $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\PortScan::class;
        }
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\Js::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\GetIpIntel::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\UserField::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\EmailDomain::class;
        // must occur after geoip!
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\Language::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\User\Timezone::class;
    }

    public static function initAdditionalProviders(Spam $container, Container $parentContainer)
    {
        $container->container()->set('geoIpProviders', function (/** @noinspection PhpUnusedParameterInspection */ Container $c) use ($container, $parentContainer) {
            return static::initProviders('spam_geo_ip_providers', 'svSignupGeoIPConfig', '%s\Spam\Checker\GeoIp\%s', $container, $parentContainer,
                function (Spam $container, Container $parentContainer, array &$providers) {
                    $providers[] = AsnLookupFallback::class;
                });
        });

        $container->container()->set('asnProviders', function (/** @noinspection PhpUnusedParameterInspection */ Container $c) use ($container, $parentContainer) {
            return static::initProviders('spam_asn_providers', 'svSignupAsnBlockingConfig', '%s\Spam\Checker\Asn\%s', $container, $parentContainer, null);
        });
    }

    /**
     * @param Spam      $container
     * @param Container $parentContainer
     * @param array<class-string> $providers
     * @return void
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public static function geoIpProviders(Spam $container, Container $parentContainer, array &$providers)
    {
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\GeoIp\CloudFlare::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\GeoIp\ipStack::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\GeoIp\ipApi::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\GeoIp\ipregistry::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\GeoIp\MaxMind::class;
    }

    /**
     * @param Spam      $container
     * @param Container $parentContainer
     * @param array<class-string> $providers
     * @return void
     * @noinspection PhpUnusedParameterInspection
     * @noinspection PhpFullyQualifiedNameUsageInspection
     */
    public static function asnProviders(Spam $container, Container $parentContainer, array &$providers)
    {
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Asn\ipStack::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Asn\ipApi::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Asn\ipregistry::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Asn\Cymru::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Asn\MaxMind::class;
        $providers[] = \SV\SignupAbuseBlocking\Spam\Checker\Asn\Ripe::class;
    }

    /**
     * @deprecated
     */
    public static function appSetup(App $app)
    {
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
