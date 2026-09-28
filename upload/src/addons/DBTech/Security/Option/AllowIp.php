<?php

namespace DBTech\Security\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;
use XF\Util\Ip;

class AllowIp extends AbstractOption
{
	/**
	 * @param Option $option
	 * @param array $htmlParams
	 *
	 * @return string
	 */
	public static function renderOption(Option $option, array $htmlParams): string
	{
		$choices = [];
		foreach ($option->option_value AS $ip)
		{
			$choices[] = [
				'ip' => $ip['ip'],
			];
		}

		return self::getTemplate('admin:option_template_dbtechSecurityAllowIp', $option, $htmlParams, [
			'choices' => $choices,
			'nextCounter' => count($choices),
		]);
	}

	/**
	 * @param array $value
	 *
	 * @return bool
	 */
	public static function verifyOption(array &$value): bool
	{
		$output = [];
		foreach ($value AS $ip)
		{
			if (!isset($ip['ip']))
			{
				continue;
			}

			$cache = self::buildIpCacheValue($ip['ip']);
			if ($cache)
			{
				$output[] = $cache;
			}
		}

		$value = $output;

		return true;
	}

	/**
	 * Builds the IP data array
	 *
	 * @param string $ip
	 *
	 * @return array|bool
	 */
	public static function buildIpCacheValue(string $ip): bool|array
	{
		$ip = trim($ip);
		if ($ip === '')
		{
			return false;
		}

		$parsed = Ip::parseIpRangeString($ip);

		if (!$parsed)
		{
			return false;
		}

		return [
			'ip' => $ip,
			'isRange' => $parsed['isRange'],
		];
	}
}