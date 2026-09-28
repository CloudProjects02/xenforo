<?php

namespace DBTech\Security\Util;

class Ip
{
	/**
	 * @return array
	 */
	public static function getClientIps(): array
	{
		$ips = preg_split('/,\s*/', \XF::app()->request()->getIp(true));
		$ips[] = \XF::app()->request()->getIp();

		return array_unique($ips);
	}
}