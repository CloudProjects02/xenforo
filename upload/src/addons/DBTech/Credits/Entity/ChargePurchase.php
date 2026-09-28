<?php

namespace DBTech\Credits\Entity;

use DBTech\Credits\EventTrigger\AbstractHandler;
use DBTech\Credits\Repository\CurrencyRepository;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\Post;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\PrintableException;

/**
 * COLUMNS
 * @property string $content_type
 * @property int $content_id
 * @property string $content_hash
 * @property int $user_id
 *
 * GETTERS
 * @property-read Currency $Currency
 *
 * RELATIONS
 * @property-read Charge|null $Charge
 * @property-read Post|null $Post
 * @property-read User|null $User
 */
class ChargePurchase extends Entity
{
	/**
	 * @return AbstractHandler|null
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function getHandler(): ?AbstractHandler
	{
		$currency = $this->Currency;
		$currency->verifyChargeEvent();

		return \XF::app()->repository(EventTriggerRepository::class)
			->getHandler('charge')
		;
	}

	/**
	 * @return Currency
	 */
	public function getCurrency(): Currency
	{
		return \XF::app()->repository(CurrencyRepository::class)
			->getChargeCurrency()
		;
	}

	/**
	 * @param Currency|null $currency
	 */
	public function setCurrency(?Currency $currency = null): void
	{
		$this->_getterCache['Currency'] = $currency;
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_charge_purchase';
		$structure->shortName = 'DBTech\Credits:ChargePurchase';
		$structure->primaryKey = ['content_type', 'content_id', 'content_hash', 'user_id'];
		$structure->columns = [
			'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'content_id'   => ['type' => self::UINT, 'required' => true],
			'content_hash' => ['type' => self::STR, 'required' => true],
			'user_id'      => ['type' => self::UINT, 'required' => true],
		];
		$structure->getters = [
			'Currency' => true,
		];
		$structure->relations = [
			'Charge' => [
				'entity'     => Charge::class,
				'type'       => self::TO_ONE,
				'conditions' => [
					['content_type', '=', '$content_type'],
					['content_id', '=', '$content_id'],
					['content_hash', '=', '$content_hash'],
				],
			],
			'Post'   => [
				'entity'     => Post::class,
				'type'       => self::TO_ONE,
				'primary'    => true,
				'conditions' => [
					['post_id', '=', '$content_id'],
				],
				'with'       => ['Thread'],
			],
			'User'   => [
				'entity'     => User::class,
				'type'       => self::TO_ONE,
				'conditions' => 'user_id',
				'primary'    => true,
			],
		];
		return $structure;
	}
}