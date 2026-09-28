<?php

namespace XenSupport\Staff\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property int    $contact_id
 * @property int    $from_user_id    0 = anonymous
 * @property string $from_email
 * @property int    $to_user_id
 * @property string $subject
 * @property string $message
 * @property bool   $is_anonymous
 * @property string $status
 * @property int    $replied_date
 * @property int    $reply_seconds
 * @property string $ip_hash
 * @property int    $created_date
 *
 * @property \XF\Entity\User|null $FromUser
 * @property \XF\Entity\User|null $ToUser
 */
class Contact extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_xs_staff_contact';
		$structure->shortName = 'XenSupport\Staff:Contact';
		$structure->primaryKey = 'contact_id';
		$structure->columns = [
			'contact_id'    => ['type' => self::UINT, 'autoIncrement' => true],
			'from_user_id'  => ['type' => self::UINT, 'default' => 0],
			'from_email'    => ['type' => self::STR, 'maxLength' => 120, 'default' => ''],
			'to_user_id'    => ['type' => self::UINT, 'required' => true],
			'subject'       => ['type' => self::STR, 'maxLength' => 150, 'required' => true],
			'message'       => ['type' => self::STR, 'required' => true],
			'is_anonymous'  => ['type' => self::BOOL, 'default' => false],
			'status'        => ['type' => self::STR, 'default' => 'open', 'allowedValues' => ['open', 'replied', 'closed']],
			'replied_date'  => ['type' => self::UINT, 'default' => 0],
			'reply_seconds' => ['type' => self::UINT, 'default' => 0],
			'ip_hash'       => ['type' => self::STR, 'maxLength' => 32, 'default' => ''],
			'created_date'  => ['type' => self::UINT, 'default' => 0],
		];
		$structure->relations = [
			'FromUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$from_user_id']],
				'primary' => true,
			],
			'ToUser' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => [['user_id', '=', '$to_user_id']],
				'primary' => true,
			],
		];
		return $structure;
	}

	protected function _preSave(): void
	{
		if (!$this->created_date) { $this->created_date = \XF::$time; }
	}
}
