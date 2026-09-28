<?php

namespace SynapseThemes\YearsOfService\Helper;

use XF\Entity\User;

class ServiceHelper
{
	/**
	 * Calculate service badge data for a user
	 *
	 * @param User $user
	 * @return array
	 */
	public static function getServiceBadgeData(User $user)
	{
		$joinDate = $user->register_date;
		$now = \XF::$time;
		
		$diff = $now - $joinDate;
		$days = floor($diff / 86400); // 86400 seconds in a day
		
		// Calculate years and remaining months
		$years = floor($days / 365);
		$remainingDays = $days - ($years * 365);
		$months = floor($remainingDays / 30.44); // Average days per month
		

		$data = [
			'years' => $years,
			'months' => $months,
			'totalMonths' => $months,
			'display_years' => $years >= 1,
			'display_months' => $years < 1 && $months >= 1,
			'yearsPhrase' => self::getYearsPhrase($years),
			'monthsPhrase' => self::getMonthsPhrase($months)
		];
		
		return $data;
	}
	
	/**
	 * Get the appropriate phrase for years
	 *
	 * @param int $years
	 * @return string
	 */
	public static function getYearsPhrase($years)
	{
		$phraseKey = $years === 1 ? 'synapseYearsOfService_year_singular' : 'synapseYearsOfService_years_plural';
		return \XF::phrase($phraseKey)->render();
	}
	
	/**
	 * Get the appropriate phrase for months
	 *
	 * @param int $months
	 * @return string
	 */
	public static function getMonthsPhrase($months)
	{
		$phraseKey = $months === 1 ? 'synapseYearsOfService_month_singular' : 'synapseYearsOfService_months_plural';
		return \XF::phrase($phraseKey)->render();
	}
}