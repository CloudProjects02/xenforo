<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int $user_id
 * @property int $item_id
 * @property int $recipient_user_id
 * @property string $recipient_username
 * @property int $quantity
 * @property string $message
 *
 * GETTERS
 * @property float $price
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Item $Item
 * @property \XF\Entity\User $User
 * @property \XF\Entity\User $Recipient
 */
class Cart extends Entity
{
	/**
	 * @return float
	 */
	public function getPrice()
	{
		$finalPrice = $this->Item->getFinalPriceForUser($this->User);
		return $finalPrice * $this->quantity;
	}

	public function getOriginalPrice()
	{
		return $this->Item->price * $this->quantity;
	}

	public function getDiscount()
	{
		return $this->getOriginalPrice() - $this->getPrice();
	}
	
	/**
	 * @return Currency
	 */
	public function getCurrency(): Currency
	{
		return $this->Item->PurchaseCurrency;
	}
	
	/**
	 * @return Purchase|Entity
	 */
	public function getNewPurchase()
	{
		$purchase = $this->_em->create('DBTech\Shop:Purchase');
		$purchase->user_id = $this->recipient_user_id ?: $this->user_id; // we're assuming this only gets called after validation
		$purchase->buyer_user_id = $this->user_id;
		$purchase->buyer_username = $this->User->username;
		$purchase->item_id = $this->item_id;
		$purchase->dateline = \XF::$time;
		$purchase->message = $this->message;
		$purchase->active = !$this->Item->isUserConfigurable();
		$purchase->hidden = $this->Item->getFlag('is_always_hidden');
		$purchase->gifted = (bool)$this->recipient_user_id;
		$purchase->expiry_date = $this->Item->getExpiryDate();
		
		$purchase->hydrateRelation('Item', $this->Item);
		$purchase->hydrateRelation('User', $this->recipient_user_id ? $this->Recipient : $this->User);
		$purchase->hydrateRelation('Buyer', $this->User);
		
		return $purchase;
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_cart';
		$structure->shortName = 'DBTech\Shop:Cart';
		$structure->primaryKey = ['user_id', 'item_id', 'recipient_user_id'];
		$structure->columns = [
			'user_id'            => ['type' => self::UINT, 'required' => true],
			'item_id'            => ['type' => self::UINT, 'required' => true],
			'recipient_user_id'  => ['type' => self::UINT, 'default' => 0],
			'recipient_username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
			'quantity'           => ['type' => self::UINT, 'default' => 1],
			'message'            => ['type' => self::STR, 'default' => ''],
		];
		$structure->getters = [
			'price' => true
		];
		$structure->relations = [
			'Item' => [
				'entity' => 'DBTech\Shop:Item',
				'type' => self::TO_ONE,
				'conditions' => 'item_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'Recipient' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$recipient_user_id']
				],
				'primary' => true
			]
		];

		return $structure;
	}
}