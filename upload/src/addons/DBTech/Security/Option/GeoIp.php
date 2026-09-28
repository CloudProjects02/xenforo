<?php

namespace DBTech\Security\Option;

use XF\Entity\Option;

class GeoIp
{
	/**
	 * @param $value
	 * @param Option $option
	 *
	 * @return bool
	 */
	public static function verifyOption(&$value, Option $option): bool
	{
		if (!empty($value) and (!file_exists($value) or !is_readable($value)))
		{
			$option->error(\XF::phrase('dbtech_security_geoip_file_unreadable'), $option->option_id);
			return false;
		}

		return true;
	}
}