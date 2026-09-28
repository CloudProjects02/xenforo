<?php

namespace SV\SignupAbuseBlocking\Repository;

use SV\SignupAbuseBlocking\Entity\SignupThrottlingLog as SignupThrottlingLogEntity;
use SV\SignupAbuseBlocking\Entity\UserLoginLog;
use SV\SignupAbuseBlocking\Finder\UserRegistrationLog as UserRegistrationLogFinder;
use SV\SignupAbuseBlocking\Globals;
use SV\SignupAbuseBlocking\Repository\UserAgent as UserAgentRepo;
use SV\SignupAbuseBlocking\Spam\AsnProvider;
use SV\SignupAbuseBlocking\Spam\GeoIpProvider;
use SV\SignupAbuseBlocking\Util\Ip as SvIp;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XenCentral\MultiSite\Repository\Domain as MultiSiteDomainRepo;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Repository;
use XF\Util\Ip;
use function ceil;
use function count;
use function in_array;
use function is_string;
use function max;
use function min;
use function str_replace;
use function stripos;
use function strlen;
use function strpos;
use function urlencode;

/**
 * Class UserRegistrationLog
 *
 * @package SV\SignupAbuseBlocking\Repository
 */
class UserRegistrationLog extends Repository
{
    protected $ipAsn = [];
    protected $ipCountry = [];

    public static function get(): self
    {
        return Helper::repository(self::class);
    }

    public function findUserRegistrationLogsForList(): UserRegistrationLogFinder
    {
        $finder = UserRegistrationLogFinder::finder();
        $finder->with('User')
               ->setDefaultOrder('log_date', 'DESC');

        return $finder;
    }

    public function resolveCountryCode(string $ip, bool $interactive, bool $clampIp): ?string
    {
        if ($ip === '')
        {
            return null;
        }

        if (SvIp::isLocalIp($ip))
        {
            return null;
        }

        if ($clampIp)
        {
            $result = $this->clampStringIpToMinimumCIDR($ip);
            $ip = $result[0] ?? null;
            if ($ip === null)
            {
                return null;
            }
        }

        $spamContainer = $this->app()->spam();
        $spamContainer->get('contentProviders');
        if (!$spamContainer->offsetExists('geoIpProviders'))
        {
            return null;
        }
        /** @var GeoIpProvider[] $providers */
        $providers = $spamContainer->get('geoIpProviders');
        if (!$providers)
        {
            return null;
        }

        $countryCode = $this->ipCountry[$ip] ?? null;
        if ($countryCode !== null)
        {
            return $countryCode;
        }

        foreach ($providers as $provider)
        {
            if ($interactive)
            {
                if (!$provider->isInteractive())
                {
                    continue;
                }
            }
            else
            {
                if (!$provider->isNonInteractive())
                {
                    continue;
                }
            }

            try
            {
                $country = $provider->resolveGeoIp($ip);
            }
            catch (\Throwable $e)
            {
                if (\XF::$developmentMode)
                {
                    \XF::logException($e);
                }
                $country = null;
            }
            if ($country !== null)
            {
                $countryCode = $country;
                break;
            }
        }

        $this->ipCountry[$ip] = $countryCode;

        return $countryCode;
    }

    public function resolveAsn(string $ip, bool $interactive, bool $clampIp): ?array
    {
        if ($ip === '')
        {
            return null;
        }

        if (SvIp::isLocalIp($ip))
        {
            return null;
        }

        if ($clampIp)
        {
            $result = $this->clampStringIpToMinimumCIDR($ip);
            $ip = $result[0] ?? null;
            if ($ip === null)
            {
                return null;
            }
        }

        $spamContainer = $this->app()->spam();
        $spamContainer->get('contentProviders');
        if (!$spamContainer->offsetExists('asnProviders'))
        {
            return null;
        }
        /** @var AsnProvider[] $providers */
        $providers = $spamContainer->get('asnProviders');
        if (!$providers)
        {
            return null;
        }

        $asn = $this->ipAsn[$ip] ?? null;
        if ($asn !== null)
        {
            return $asn;
        }

        $asn = $asName = $fallbackCountry = null;
        foreach ($providers as $provider)
        {
            if ($interactive)
            {
                if (!$provider->isInteractive())
                {
                    continue;
                }
            }
            else
            {
                if (!$provider->isNonInteractive())
                {
                    continue;
                }
            }

            try
            {
                $entry = $provider->resolveIpToAsn($ip);
            }
            catch (\Throwable $e)
            {
                if (\XF::$developmentMode)
                {
                    \XF::logException($e);
                }
                $entry = null;
            }
            if ($entry !== null)
            {
                $asn = $entry[0] ?? null;
                $asName = $entry[1] ?? null;
                $fallbackCountry = $entry[2] ?? $fallbackCountry;
                if ($asn !== null)
                {
                    break;
                }
            }
        }

        if ($fallbackCountry === 'XX' || $fallbackCountry === '')
        {
            $fallbackCountry = null;
        }

        $asName = $asName ?? '';
        if ($asn === null)
        {
            Globals::$asnCountryLookupFallback = null;
            // no providers configured, skip
            return null;
        }

        Globals::$asnCountryLookupFallback = $fallbackCountry;
        $this->ipAsn[$ip] = [$asn, $asName];

        return [$asn, $asName];
    }

    public function getThrottlingTypes(): array
    {
        return ['ip_address', 'country', 'asn'];
    }

    public function getThrottlingTypePhrases(): array
    {
        $types = [];
        foreach ($this->getThrottlingTypes() as $type)
        {
            $types[$type] = \XF::phrase('svSignupThrottling_type.' . $type);
        }

        return $types;
    }

    protected function getSiteDomain(): ?string
    {
        if (\XF::isAddOnActive('XenCentral/MultiSite'))
        {
            $repo = Helper::repository(MultiSiteDomainRepo::class);
            $domain = (string)$repo->getCurrentDomain();

            if ($domain === '')
            {
                return null;
            }

            return $domain;
        }

        return null;
    }

    public function logSignupRecordForRateLimit(UserEntity $user, string $ip, array $fields)
    {
        $ipAddress = $ip ? (Ip::convertIpStringToBinary($ip) ?: '') : '';

        // Throttling is implemented via xf_sv_signup_log
        // This has additional fields over xf_spam_trigger_log, but is durable compared to xf_sv_user_registration_log
        $values = [
            'log_date' => \XF::$time,
            'ip_address' => $ipAddress,
            'asn' => $fields['asn'] ?? null,
            'country' => $fields['country'] ?? null,
            'user_agent_id' => $fields['user_agent_id'] ?? null,
        ];

        $domain = $this->getSiteDomain();
        if ($domain !== null)
        {
            $values['domain'] = $domain;
        }

        \XF::db()->insert('xf_sv_signup_log', $values);

        $this->logSignRecord($user, $ip, 'register', $fields);
    }

    public function logSignRecord(UserEntity $user, string $ip, string $action, array $fields): UserLoginLog
    {
        $ipAddress = $ip ? (Ip::convertIpStringToBinary($ip) ?: '') : '';
        $domain = $this->getSiteDomain();
        if ($domain !== null)
        {
            $fields['domain'] = $domain;
        }

        $userLoginLog = UserLoginLog::create();
        $userLoginLog->user_id = $user->user_id;
        $userLoginLog->log_date = \XF::$time;
        $userLoginLog->ip_address = $ipAddress;
        $userLoginLog->action = $action;
        $userLoginLog->bulkSet($fields);
        $userLoginLog->save();

        return $userLoginLog;
    }


    public function isSignupsThrottled(string $ip): bool
    {
        $options = \XF::options();
        if (!($options->svSignupThrottling ?? false))
        {
            return false;
        }

        $throttleTypes = $options->svSignupThrottleTypes ?? null;
        if ($throttleTypes === null)
        {
            return false;
        }

        /** @var array<string> $throttlingWindow */
        $throttlingWindow = $options->svSignupThrottlingWindow ?? [];
        if (count($throttlingWindow) === 0)
        {
            return false;
        }
        $throttlingWindowInSeconds = $throttlingWindow['value'] * $throttlingWindow['unit'];
        if ($throttlingWindowInSeconds === 0)
        {
            return false;
        }

        $domain = $this->getSiteDomain();
        if ($domain !== null && $this->isSignupsThrottledInternal($ip, $throttleTypes, $throttlingWindowInSeconds, $domain))
        {
            return true;
        }

        return $this->isSignupsThrottledInternal($ip, $throttleTypes, $throttlingWindowInSeconds, null);
    }

    public function isSignupsThrottledInternal(string $ip, array $throttleTypes, int $throttlingWindow, ?string $currentDomain): bool
    {
        $checks = [];
        if (in_array('ip_address', $throttleTypes, true))
        {
            $ipAddress = $ip ? (Ip::convertIpStringToBinary($ip) ?: null) : null;
            if ($ipAddress !== null)
            {
                $checks['ip_address'] = function() use ($ipAddress) { return $ipAddress; };
            }
        }

        $country = in_array('country', $throttleTypes, true);
        $asn = in_array('asn', $throttleTypes, true);
        if ($country || $asn)
        {
            $clampedIp = (string)($this->clampStringIpToMinimumCIDR($ip)[0] ?? '');
        }

        if ($country)
        {
            $checks['country'] = function() use ($clampedIp)  { return $this->resolveCountryCode($clampedIp, true, false); };
        }

        if ($asn)
        {
            $checks['asn'] = function() use ($clampedIp) { return $this->resolveAsn($clampedIp, true, false)[0] ?? null; };
        }

        $timeCutOff = \XF::$time - $throttlingWindow;
        $args = [];
        $db = $this->db();
        if ($currentDomain !== null)
        {
            $domainSql = ' domain = ? AND ';
            $args[] = $currentDomain;
        }
        else
        {
            $domainSql = '';
        }

        $countCutOffValue = null;
        foreach ($checks as $key => $func)
        {
            $value = $func();
            if ($value === null)
            {
                continue;
            }

            $args2 = $args;
            $args2[] = \XF::$time;
            $args2[] = $value;

            $isThrottled = (int)$db->fetchOne("
                SELECT 1
                FROM xf_sv_signup_throttle_log
                WHERE {$domainSql} expiry_date >= ? AND {$key} = ?
                LIMIT 1
            ", $args2);
            if ($isThrottled)
            {
                return true;
            }

            $args2 = $args;
            $args2[] = $timeCutOff;
            $args2[] = $value;

            $signupAttempts = (int)$db->fetchOne("
                SELECT COUNT(*)
                FROM xf_sv_signup_log
                WHERE {$domainSql} log_date >= ? AND {$key} = ?
            ", $args2);

            if ($signupAttempts === 0)
            {
                continue;
            }
            if ($countCutOffValue === null)
            {
                $countCutOffValue = $this->getThrottlingCutOff($throttlingWindow, $currentDomain);
            }
            if ($countCutOffValue === 0 || $signupAttempts < $countCutOffValue)
            {
                continue;
            }

            $entity = SignupThrottlingLogEntity::create();
            $entity->log_date = \XF::$time;
            $entity->expiry_date = \XF::$time + $throttlingWindow;
            $entity->signup_attempts = $signupAttempts;
            $entity->set($key, $value);
            $entity->save(false);

            return true;
        }

        return false;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function getThrottlingCutOff(int $throttlingWindow, ?string $currentDomain): int
    {
        $cutOffDefinition = \XF::options()->svSignupThrottlingCutOff ?? [];
        $type = (string)($cutOffDefinition['type'] ?? 'fixed');
        if ($type === 'fixed')
        {
            return $cutOffDefinition['fixedValue'] ?? 10;
        }
        // dynamically determine signup attempts
        $min = (int)($cutOffDefinition['dynamicMin'] ?? 10);
        $max = (int)($cutOffDefinition['dynamicMax'] ?? 50);
        if ($max <= 0)
        {
            return 0;
        }

        // get user registrations predating today, scaled by the throttling window
        $today = \XF::$time - (\XF::$time % 86400);
        $window = $today - min(86400, $throttlingWindow - ($throttlingWindow % 86400));
        $regCount = (int)$this->db()->fetchOne('
            SELECT sum(counter)
            FROM xf_stats_daily
            WHERE stats_type = ? AND stats_date >= ?
            ORDER BY stats_date DESC
        ', ['user_registration', $window]);
        if ($regCount === 0)
        {
            return $min;
        }
        // average out the day stats to the throttling window period
        $dynamicValue = (int)ceil($regCount * ($throttlingWindow / $window));
        // clamp to something sane-ish
        return max($min, min($max, $dynamicValue));
    }

    public function cleanUpSignupLog(?int $cutOff = null)
    {
        if ($cutOff === null)
        {
            /** @var array<string> $throttlingWindow */
            $throttlingWindow = \XF::options()->svSignupThrottlingWindow ?? [];
            if (count($throttlingWindow) === 0)
            {
                return;
            }
            $throttlingWindowInSeconds = min(12 * 3600, $throttlingWindow['value'] * $throttlingWindow['unit']);
            $cutOff = \XF::$time - $throttlingWindowInSeconds;
        }

        $this->db()->query('
            DELETE
            FROM xf_sv_signup_log
            WHERE log_date < ?
        ', $cutOff);
    }

    public function cleanUpSignupThrottlingLog(?int $cutOff = null)
    {
        if ($cutOff === null)
        {
            /** @var array<string> $throttlingWindow */
            $throttlingWindow = \XF::options()->svSignupThrottlingWindow ?? [];
            if (count($throttlingWindow) === 0)
            {
                return;
            }
            $throttlingWindowInSeconds = min(24 * 3600, $throttlingWindow['value'] * $throttlingWindow['unit']);
            $cutOff = \XF::$time - $throttlingWindowInSeconds;
        }

        $this->db()->query('
            DELETE
            FROM xf_sv_signup_throttle_log
            WHERE expiry_date < ?
        ', $cutOff);
    }


    public function getAsnLink(string $asn): ?string
    {
        $url = $this->options()->svAsnInfoUrl ?? '';
        if (strlen($url) === 0)
        {
            return null;
        }

        if (strpos($url, '{asn}') === false)
        {
            $url = 'https://www.peeringdb.com/advanced_search?reftag=org&asn={asn}';
        }

        return str_replace('{asn}', urlencode($asn), $url);
    }

    /**
     * @param string $ipAddress
     * @return array{string,int,int}|null
     */
    public function clampStringIpToMinimumCIDR(string $ipAddress): ?array
    {
        // Practical minimum routing CIDR is a /24 for ipv4, or a /48 for ipv6
        if (stripos($ipAddress, '.') !== false)
        {
            $range = Ip::parseIpRangeString($ipAddress.'/24');
        }
        else
        {
            $range = Ip::parseIpRangeString($ipAddress.'/48');
        }
        if (!$range)
        {
            return null;
        }
        $minIp = $range['startRange'] ?? 0;
        $maxIp = $range['endRange'] ?? 0;
        if ($minIp === 0 || $maxIp === 0)
        {
            return null;
        }
        $ipAddress = Ip::convertIpBinaryToString($minIp);
        if (!is_string($ipAddress))
        {
            return null;
        }

        return [$ipAddress, $minIp, $maxIp];
    }

    /** @noinspection SqlIdentifier */
    public function resolveIpToAsnAndCountry(string $ipAddress, ?int $dateCutOff = null, bool $interactive = true, bool $resolveGeoIpWithProviders = true): ?array
    {
        if (SvIp::isLocalIp($ipAddress))
        {
            return [0, 'XX'];
        }

        $dateCutOff = $dateCutOff ?? 86400;

        $result = $this->clampStringIpToMinimumCIDR($ipAddress);
        if ($result === null)
        {
            return null;
        }
        [$ipAddress, $minIp, $maxIp] = $result;

        $db = \XF::db();
        $dateCutOffSql = $dateCutOff > 0 ? 'AND (log_date > '.$db->quote(\XF::$time - $dateCutOff). ')' : '';


        $asn = (string)$db->fetchOne('
            SELECT asn
            FROM xf_sv_login_log force index (ip_address_log_date)
            WHERE ip_address >= ? AND ip_address <= ? AND asn IS NOT NULL '.$dateCutOffSql.'
            ORDER BY log_date DESC
            LIMIT 1
        ', [$minIp, $maxIp]);
        if ($asn === '')
        {
            $asn = (string)$db->fetchOne('
                SELECT asn
                FROM xf_sv_user_registration_log force index (ip_address_log_date)
                WHERE ip_address >= ? AND ip_address <= ? AND asn IS NOT NULL '.$dateCutOffSql.'
                ORDER BY log_date DESC
                LIMIT 1
            ', [$minIp, $maxIp]);
        }
        if ($asn === '')
        {
            $asn = (string)$db->fetchOne('
                SELECT asn
                FROM xf_sv_signup_log force index (ip_address_log_date)
                WHERE ip_address >= ? AND ip_address <= ? AND asn IS NOT NULL '.$dateCutOffSql.'
                ORDER BY log_date DESC
                LIMIT 1
            ', [$minIp, $maxIp]);
        }
        if ($asn === '')
        {
            $asnData = $this->resolveAsn($ipAddress, $interactive, false);
            if ($asnData !== null)
            {
                $asn = $asnData[0] ?? '';
            }
        }

        $country = (string)$db->fetchOne('
            SELECT country
            FROM xf_sv_login_log force index (ip_address_log_date)
            WHERE ip_address >= ? AND ip_address <= ? AND country IS NOT NULL '.$dateCutOffSql.'
            ORDER BY log_date DESC
            LIMIT 1
        ', [$minIp, $maxIp]);
        if ($country === '')
        {
            $country = (string)$db->fetchOne('
                SELECT country
                FROM xf_sv_user_registration_log force index (ip_address_log_date)
                WHERE ip_address >= ? AND ip_address <= ? AND country IS NOT NULL '.$dateCutOffSql.'
                ORDER BY log_date DESC
                LIMIT 1
            ', [$minIp, $maxIp]);
        }
        if ($country === '')
        {
            $country = (string)$db->fetchOne('
                SELECT asn
                FROM xf_sv_signup_log force index (ip_address_log_date)
                WHERE ip_address >= ? AND ip_address <= ? AND country IS NOT NULL '.$dateCutOffSql.'
                ORDER BY log_date DESC
                LIMIT 1
            ', [$minIp, $maxIp]);
        }
        if ($resolveGeoIpWithProviders && $country === '')
        {
            $country = $this->resolveCountryCode($ipAddress, $interactive, false);
            if ($country === 'XX')
            {
                $country = '';
            }
        }
        if ($country === '')
        {
            $country = Globals::$asnCountryLookupFallback ?? '';
        }

        if ($asn === '')
        {
            $asn = null;
        }
        if ($country === '')
        {
            $country = 'XX';
        }

        return [$asn, $country];
    }

    public function logLogin(UserEntity $user, string $ip, string $userAgent, string $action): void
    {
        $userAgentId = UserAgentRepo::get()->logUserAgent($userAgent);
        [$asn, $country] = $this->resolveIpToAsnAndCountry($ip);

        $lastLogin = $this->logSignRecord($user, $ip, $action, [
            'user_agent_id' => $userAgentId,
            'asn'           => $asn,
            'country'       => $country,
        ]);
        /** @var ExtendedUserEntity $user */
        $user->setSvLastLogin($lastLogin);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
