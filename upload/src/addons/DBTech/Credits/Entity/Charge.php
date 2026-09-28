<?php

namespace DBTech\Credits\Entity;

use DBTech\Credits\EventTrigger\AbstractHandler;
use DBTech\Credits\Repository\CurrencyRepository;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\PrintableException;

/**
 * COLUMNS
 * @property string $content_type
 * @property int $content_id
 * @property string $content_hash
 * @property float $cost
 *
 * GETTERS
 * @property-read Currency $Currency
 * @property-read null|Entity $Content
 *
 * RELATIONS
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Credits\Entity\ChargePurchase> $Purchases
 */
class Charge extends Entity
{
	/**
	 * @return null|Entity
	 */
	public function getContent(): ?Entity
	{
		return \XF::app()->findByContentType($this->content_type, $this->content_id);
	}

	/**
	 * @param Entity|null $content
	 */
	public function setContent(?Entity $content = null): void
	{
		$this->_getterCache['Content'] = $content;
	}

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
			->getHandler('content')
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
		$structure->table = 'xf_dbtech_credits_charge';
		$structure->shortName = 'DBTech\Credits:Charge';
		$structure->primaryKey = ['content_type', 'content_id', 'content_hash'];
		$structure->columns = [
			'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'content_id'   => ['type' => self::UINT, 'required' => true],
			'content_hash' => ['type' => self::STR, 'required' => true],
			'cost'         => ['type' => self::FLOAT, 'required' => true, 'default' => 0.0],
		];
		$structure->getters = [
			'Currency' => true,
			'Content' => true,
		];
		$structure->relations = [
			'Purchases' => [
				'entity'     => ChargePurchase::class,
				'type'       => self::TO_MANY,
				'conditions' => [
					['content_type', '=', '$content_type'],
					['content_id', '=', '$content_id'],
					['content_hash', '=', '$content_hash'],
				],
				'with'       => ['User'],
				'key'        => 'user_id',
			],
		];

		return $structure;
	}
}