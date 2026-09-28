<?php

namespace DBTech\Shop\Option;

use XF\Option\AbstractOption;

/**
 * Class Postbit
 *
 * @package DBTech\Shop\Option
 */
class Postbit extends AbstractOption
{
	/**
	 * @param \XF\Entity\Option $option
	 * @param array $htmlParams
	 *
	 * @return string
	 */
	public static function renderCheckbox(\XF\Entity\Option $option, array $htmlParams): string
	{
		$bitfielddefs = [
			'dbtech_shop_below_postcount' => 2
		];

		$value = [];
		$choices = [];
		foreach ($bitfielddefs AS $phrase => $bitfield)
		{
			if (($option->option_value[0] & $bitfield))
			{
				$value[] = $bitfield;
			}
			$choices[$bitfield] = \XF::phrase($phrase);
		}

		return self::getCheckboxRow($option, $htmlParams, $choices, $value);
	}
	
	/**
	 * @param array $choices
	 * @param \XF\Entity\Option $option
	 *
	 * @return bool
	 */
	public static function verifyOption(array &$choices, \XF\Entity\Option $option): bool
	{
		if ($option->isInsert())
		{
			// insert - just trust the default value
			return true;
		}

		// Sort out bitfield sum
		$choices = [array_sum($choices)];

		return true;
	}
}