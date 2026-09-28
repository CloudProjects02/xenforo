<?php

namespace SV\SignupAbuseBlocking\Util;

use DateTime;
use XF\Data\TimeZone;
use function strtoupper, timezone_identifiers_list, array_unique, implode;

abstract class TimezoneList
{
    // the XF registration system detects timezone based on offset, and isn't the full list
    public static $registrationTimezoneList = [
        '660,660'   => 'Pacific/Midway',
        '600,600'   => 'Pacific/Honolulu',
        '570,570'   => 'Pacific/Marquesas',
        '540,480'   => 'America/Anchorage',
        '480,420'   => 'America/Los_Angeles',
        '420,360'   => 'America/Denver',
        '420,420'   => 'America/Phoenix',
        '360,300'   => 'America/Chicago',
        '360,360'   => 'America/Belize',
        '300,240'   => 'America/New_York',
        '300,300'   => 'America/Bogota',
        '240,240'   => 'America/Caracas',
        '240,180'   => 'America/Halifax',
        '180,240'   => 'America/Cuiaba',
        '210,150'   => 'America/St_Johns',
        '180,180'   => 'America/Argentina/Buenos_Aires',
        '120,180'   => 'America/Sao_Paulo',
        '180,120'   => 'America/Miquelon',
        '120,120'   => 'America/Noronha',
        '60,60'     => 'Atlantic/Cape_Verde',
        '60,0'      => 'Atlantic/Azores',
        '0,-60'     => 'Europe/London',
        '0,0'       => 'Atlantic/Reykjavik',
        '-60,-120'  => 'Europe/Amsterdam',
        '-60,-60'   => 'Africa/Algiers',
        '-120,-60'  => 'Africa/Windhoek',
        '-120,-180' => 'Europe/Athens',
        '-120,-120' => 'Africa/Johannesburg',
        '-180,-240' => 'Africa/Nairobi',
        '-180,-180' => 'Europe/Moscow',
        '-210,-270' => 'Asia/Tehran',
        '-240,-300' => 'Asia/Yerevan',
        '-270,-270' => 'Asia/Kabul',
        '-300,-360' => 'Asia/Yekaterinburg',
        '-300,-300' => 'Asia/Tashkent',
        '-330,-330' => 'Asia/Kolkata',
        '-345,-345' => 'Asia/Kathmandu',
        '-360,-360' => 'Asia/Dhaka',
        '-390,-390' => 'Asia/Yangon',
        '-420,-420' => 'Asia/Bangkok',
        '-420,-480' => 'Asia/Krasnoyarsk',
        '-480,-480' => 'Asia/Hong_Kong',
        '-480,-540' => 'Asia/Irkutsk',
        '-540,-540' => 'Asia/Tokyo',
        '-540,-600' => 'Asia/Yakutsk',
        '-630,-570' => 'Australia/Adelaide',
        '-570,-570' => 'Australia/Darwin',
        '-660,-600' => 'Australia/Sydney',
        '-600,-660' => 'Asia/Vladivostok',
        '-660,-720' => 'Asia/Magadan',
        '-690,-690' => 'Pacific/Norfolk',
        '-780,-720' => 'Pacific/Auckland',
        '-825,-765' => 'Pacific/Chatham',
        '-780,-780' => 'Pacific/Tongatapu',
        '-840,-840' => 'Pacific/Kiritimati'

    ];

    public static function buildList(): string
    {
        //$tzData = \XF::app()->data('XF:TimeZone');
        //$tzs = $tzData->getTimeZoneData();
        $tzs = static::$registrationTimezoneList;
        $utcTz = new \DateTimeZone('UTC');
        $now = new DateTime('now', $utcTz);

        $list = [];

        foreach (CountryList::$countryList as $code => $name)
        {
            $countryCode = strtoupper($code);
            $timezones = timezone_identifiers_list(\DateTimeZone::PER_COUNTRY, $code);
            if ($timezones)
            {
                foreach ($timezones as $timezone)
                {
                    try
                    {
                        // probe based on the jan1/jun1 offset like the registration process does
                        $tzObj = new \DateTimeZone($timezone);
                        $jan1 = DateTime::createFromFormat('Y-m-d h:i:s', $now->format('Y') . '-01-01 00:00:00', $tzObj);
                        $jun1 = DateTime::createFromFormat('Y-m-d h:i:s', $now->format('Y') . '-06-01 00:00:00', $tzObj);

                        // offset is inverted compared to the javascript version
                        $jan1offset = -(int)round($tzObj->getOffset($jan1) / 60);
                        $jun1offset = -(int)round($tzObj->getOffset($jun1) / 60);

                        $tz = $tzs[$jan1offset . ',' . $jun1offset] ?? null;
                        if ($tz !== null)
                        {
                            $list[] = '0|' . $countryCode . '-' . $tz;
                        }
                    }
                    catch (\Exception $e)
                    {

                    }
                }
            }
        }
        sort($list);
        $list = array_unique($list);

        return implode("\n", $list);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
