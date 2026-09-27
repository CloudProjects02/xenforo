<?php

namespace DBTech\Credits\Entity;

use XF\Entity\User;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int|null $donation_log_id
 * @property int $user_id
 * @property int $donation_date
 * @property int $donation_user_id
 * @property int $event_id
 * @property int $currency_id
 * @property float $amount
 * @property string $message
 *
 * RELATIONS
 * @property-read User|null $User
 * @property-read User|null $DonatedBy
 * @property-read Event|null $Event
 * @property-read Currency|null $Currency
 */
class DonationLog extends AbstractEntity
{
	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_donation_log';
		$structure->shortName = 'DBTech\Credits:DonationLog';
		$structure->primaryKey = 'donation_log_id';
		$structure->columns = [
			'donation_log_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'donation_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'donation_user_id' => ['type' => self::UINT, 'required' => true],
			'event_id' => ['type' => self::UINT, 'required' => true],
			'currency_id' => ['type' => self::UINT, 'required' => true],
			'amount' => ['type' => self::FLOAT, 'required' => true, 'max' => PHP_INT_MAX, 'isDecimal' => true],
			'message' => ['type' => self::STR, 'default' => ''],
		];
		$structure->getters = [];
		$structure->relations = [
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
			'DonatedBy' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$donation_user_id']],
				'primary' => true,
			],
			'Event' => [
				'entity' => Event::class,
				'type' => self::TO_ONE,
				'conditions' => 'event_id',
				'primary' => true,
			],
			'Currency' => [
				'entity' => Currency::class,
				'type' => self::TO_ONE,
				'conditions' => 'currency_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}