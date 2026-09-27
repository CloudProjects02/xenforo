<?php

namespace DBTech\Shop\Entity;

use DBTech\Shop\Repository\TransactionRepository;
use XF\Entity\Ip;
use XF\Entity\LinkableInterface;
use XF\Entity\User;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int $transaction_log_id
 * @property int $user_id
 * @property int $recipient_user_id
 * @property int $dateline
 * @property int $ip_id
 * @property string $action
 * @property string $content_type
 * @property int $content_id
 * @property array $info
 *
 * GETTERS
 * @property-read Phrase $title
 * @property-read ArrayCollection|Entity|null $Content
 * @property-read ArrayCollection|Entity|null $Item
 *
 * RELATIONS
 * @property-read User|null $User
 * @property-read User|null $Recipient
 * @property-read Ip|null $Ip
 */
class TransactionLog extends Entity implements LinkableInterface, ViewableInterface
{
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canView(&$error = null): bool
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		return ($visitor->user_id == $this->user_id
			|| $visitor->user_id == $this->recipient_user_id
		);
	}

	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::app()->repository(TransactionRepository::class)
			->getActionTitle($this->action)
		;
	}

	/**
	 * @return ArrayCollection|Entity|null
	 */
	public function getItem(): Entity|ArrayCollection|null
	{
		return match ($this->content_type)
		{
			'dbtech_shop_item' => $this->Content,
			'dbtech_shop_purchase' => \XF::app()->em()->find(Item::class, $this->info['featureid']),
			default => null,
		};

	}

	/**
	 * @return ArrayCollection|Entity|null
	 */
	public function getContent(): Entity|ArrayCollection|null
	{
		return \XF::app()->findByContentType($this->content_type, $this->content_id);
	}

	/**
	 * @param bool $canonical
	 * @param array $extraParams
	 * @param null $hash
	 *
	 * @return string
	 */
	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null): string
	{
		$route = $canonical ? 'canonical:dbtech-shop/transactions' : 'dbtech-shop/transactions';
		return \XF::app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	/**
	 * @return string|null
	 */
	public function getContentPublicRoute(): ?string
	{
		return 'dbtech-shop/transactions';
	}

	/**
	 * @param string $context
	 *
	 * @return Phrase
	 */
	public function getContentTitle(string $context = ''): Phrase
	{
		return \XF::phrase('dbtech_shop_transaction_x', ['title' => $this->transaction_log_id]);
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_transaction_log';
		$structure->shortName = 'DBTech\Shop:TransactionLog';
		$structure->primaryKey = 'transaction_log_id';
		$structure->columns = [
			'transaction_log_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id'            => ['type' => self::UINT, 'required' => true],
			'recipient_user_id'  => ['type' => self::UINT, 'required' => true],
			'dateline'           => ['type' => self::UINT, 'default' => \XF::$time],
			'ip_id'              => ['type' => self::UINT, 'default' => 0],
			'action'             => ['type' => self::STR, 'required' => true],
			'content_type'       => ['type' => self::STR, 'maxLength' => 25, 'default' => ''],
			'content_id'         => ['type' => self::UINT, 'default' => 0],
			'info'               => ['type' => self::JSON_ARRAY, 'default' => []],
		];
		$structure->getters = [
			'title' => true,
			'Content' => true,
			'Item' => true,
		];
		$structure->relations = [
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
			'Recipient' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$recipient_user_id'],
				],
				'primary' => true,
			],
			'Ip' => [
				'entity' => Ip::class,
				'type' => self::TO_ONE,
				'conditions' => 'ip_id',
				'primary' => true,
			],
		];

		$structure->withAliases = [
			'full' => [
				'User',
				'Recipient',
			],
		];

		return $structure;
	}
}