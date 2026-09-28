<?php

namespace DBTech\Credits\Entity;

use DBTech\Credits\EventTrigger\AbstractHandler;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\PrintableException;

/**
 * COLUMNS
 * @property string $content_type
 * @property int $content_id
 * @property int $user_id
 *
 * RELATIONS
 * @property-read User|null $User
 */
class ContentAccessPurchase extends Entity
{
	/**
	 * @return AbstractHandler|null
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function getHandler(): ?AbstractHandler
	{
		return \XF::app()->repository(EventTriggerRepository::class)
			->getHandler('content_access')
		;
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_credits_content_access_purchase';
		$structure->shortName = 'DBTech\Credits:ContentAccessPurchase';
		$structure->primaryKey = ['content_type', 'content_id', 'user_id'];
		$structure->columns = [
			'content_type' => ['type' => self::STR, 'maxLength' => 25, 'required' => true],
			'content_id'   => ['type' => self::UINT, 'required' => true],
			'user_id'      => ['type' => self::UINT, 'required' => true],
		];
		$structure->getters = [];
		$structure->relations = [
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