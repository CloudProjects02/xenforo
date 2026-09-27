<?php

namespace XF\Option;

use XF\Entity\Option;
use XF\Repository\AdvertisingRepository;

class AdsDisallowedTemplates extends AbstractOption
{
	public static function verifyOption(&$value, Option $option)
	{
		if ($option->isInsert())
		{
			return true;
		}

		$repo = \XF::repository(AdvertisingRepository::class);
		$repo->writeAdsTemplate($value ?: false);

		return true;
	}
}
