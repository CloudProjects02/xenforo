<?php

namespace DBTech\Security\Option;

use DBTech\Security\Repository\BanningRepository;
use XF\Entity\Option;

class Tor
{
	protected static bool $triggered = false;

	/**
	 * This can be used as a verification callback to force a tor node rebuild
	 *
	 * @param mixed $value
	 * @param Option $option
	 * @return bool
	 */
	public static function triggerTorNodeBanning(mixed &$value, Option $option): bool
	{
		if ($option->isInsert())
		{
			return true;
		}

		if (!self::$triggered)
		{
			$banningRepo = \XF::app()->repository(BanningRepository::class);

			if ($value)
			{
				$banningRepo->rebuildBannedTorIps();
			}
			else
			{
				$banningRepo->removeBannedTorIps();
			}
			self::$triggered = true;
		}

		return true;
	}
}