<?php

namespace SV\SignupAbuseBlocking\Entity;

use SV\StandardLib\Helper;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\PreEscaped;
use XF\Util\Ip;

/**
 * COLUMNS
 *
 * @property int|null $signup_throttle_log_id
 * @property int $log_date
 * @property int $expiry_date
 * @property int $signup_attempts
 * @property string|null $ip_address
 * @property int|null $asn
 * @property string|null $country
 * GETTERS
 * @property-read string $throttling_type
 * @property-read string $throttling_value
 */
class SignupThrottlingLog extends Entity
{
    public static function create(): self
    {
        return Helper::createEntity(self::class);
    }

    protected function getThrottlingType(): string
    {
        foreach ($this->structure()->columns as $key => $def)
        {
            if (($def['throttlingType'] ?? false) && $this->get($key) !== null)
            {
                return $key;
            }
        }

        throw new \LogicException('Unknown throttling type');
    }

    /**
     * @return PreEscaped|string|null
     */
    protected function getThrottlingValue()
    {
        $type = $this->throttling_type;
        $value = $this->{$type} ?? '';

        return $this->formatThrottlingValue($type, $value);
    }

    /**
     * @return PreEscaped|string
     */
    protected function formatThrottlingValue(string $type, $value)
    {
        if ($value === '' || $value === null)
        {
            return '';
        }

        $publicRouter = \XF::app()->router('public');

        switch ($type)
        {
            case 'ip_address':
                $ip = Ip::convertIpBinaryToString($value);
                if (!$ip)
                {
                    return '';
                }
                $link = $publicRouter->buildLink('canonical:misc/ip-info', null, ['ip' => $ip]);

                return new PreEscaped('<a href="' . $link . '">' . $ip . '</a>');

            case 'asn':
                $link = $publicRouter->buildLink('canonical:misc/asn-info', null, ['asn' => $value]);

                return new PreEscaped('<a href="' . $link . '">AS' . $value . '</a>');
            default:
                return (string)$value;
        }
    }

    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_sv_signup_throttle_log';
        $structure->shortName = 'SV\SignupAbuseBlocking:SignupThrottlingLog';
        $structure->primaryKey = 'signup_throttle_log_id';
        $structure->columns = [
            // XF Bug: UINT assumes 32bit value, so explicitly set 'max'
            'signup_throttle_log_id' => ['type' => self::UINT, 'max' => \PHP_INT_MAX, 'autoIncrement' => true, 'nullable' => true],
            'log_date'                 => ['type' => self::UINT, 'default' => \XF::$time],
            'expiry_date'              => ['type' => self::UINT, 'default' => \XF::$time],
            'signup_attempts'          => ['type' => self::UINT, 'default' => 0],
            'ip_address'               => ['type' => self::BINARY, 'nullable' => true, 'default' => null, 'maxLength' => 16, 'throttlingType' => true],
            'asn'                      => ['type' => self::UINT, 'nullable' => true, 'default' => null, 'throttlingType' => true],
            'country'                  => ['type' => self::STR, 'maxLength' => 3, 'nullable' => true, 'default' => null, 'throttlingType' => true],
        ];
        $structure->getters = [
            'throttling_type'  => ['getter' => 'getThrottlingType', 'cache' => true],
            'throttling_value' => ['getter' => 'getThrottlingValue', 'cache' => true],
        ];

        return $structure;
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
