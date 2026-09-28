<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_id
 * @property int $currency_id
 * @property float $points
 * @property int $last_interest_date
 *
 * GETTERS
 * @property float $protected_points
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Currency $Currency
 * @property \XF\Entity\User $User
 */
class Bank extends Entity
{
	/**
	 * @return float
	 */
	public function getProtectedPoints()
	{
		return $this->points * ($this->Currency->steal_protect / 100);
	}
	
	/**
	 * @return bool
	 */
	public function hasManualInterest(): bool
	{
		return \XF::options()->dbtech_shop_manualinterest;
	}
	
	/**
	 * @return int
	 */
	public function getNextInterestDate(): int
	{
		if ($this->last_interest_date == 1)
		{
			return \XF::$time;
		}
		
		$nextInterest = $this->last_interest_date + 86400;
		return ($nextInterest > \XF::$time) ? $nextInterest : \XF::$time;
	}
	
	/**
	 * @return bool
	 */
	public function canManuallyCollectInterest(): bool
	{
		if (!$this->hasManualInterest())
		{
			return false;
		}
		
		return $this->canCollectInterest();
	}
	
	/**
	 * @return bool
	 */
	public function canCollectInterest(): bool
	{
		if (!$this->Currency->interest)
		{
			return false;
		}
		
		return ($this->last_interest_date == 1 || ($this->last_interest_date + 86400) <= \XF::$time);
	}
	
	/**
	 * @param bool $automatic
	 *
	 * @return bool
	 */
	public function collectInterest(bool $automatic = false): bool
	{
		if (!$automatic && !$this->canCollectInterest())
		{
			return false;
		}
		
		$this->points *= (1 + ($this->Currency->interest) / 100);
		$this->last_interest_date = \XF::$time;
		
		return true;
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_bank';
		$structure->shortName = 'DBTech\Shop:Bank';
		$structure->primaryKey = ['user_id', 'currency_id'];
		$structure->columns = [
			'user_id'				=> ['type' => self::UINT, 'required' => true],
			'currency_id'			=> ['type' => self::UINT, 'required' => true],
			'points' 				=> ['type' => self::FLOAT, 'required' => true],
			'last_interest_date' 	=> ['type' => self::UINT, 'default' => 1],
		];
		$structure->getters = [
			'protected_points' => true
		];
		$structure->relations = [
			'Currency' => [
				'entity' => 'DBTech\Shop:Currency',
				'type' => self::TO_ONE,
				'conditions' => 'currency_id'
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
		];

		return $structure;
	}
}