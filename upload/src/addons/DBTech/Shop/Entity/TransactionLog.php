<?php

namespace DBTech\Shop\Entity;

use XF\Entity\LinkableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

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
 * @property \XF\Phrase $title
 * @property \XF\Mvc\Entity\ArrayCollection|Entity|null $Content
 * @property \XF\Mvc\Entity\ArrayCollection|Entity|null $Item
 *
 * RELATIONS
 * @property \XF\Entity\User $User
 * @property \XF\Entity\User $Recipient
 * @property \XF\Entity\Ip $Ip
 */
class TransactionLog extends Entity implements LinkableInterface
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
	 * @return \XF\Phrase
	 */
	public function getTitle(): \XF\Phrase
	{
		return $this->getTransactionRepo()->getActionTitle($this->action);
	}

	/**
	 * @return \XF\Mvc\Entity\ArrayCollection|Entity|null
	 */
	public function getItem()
	{
		switch ($this->content_type)
		{
			case 'dbtech_shop_item':
				return $this->Content;

			case 'dbtech_shop_purchase':
				return $this->_em->find('DBTech\Shop:Item', $this->info['featureid']);
		}

		return null;
	}

	/**
	 * @return \XF\Mvc\Entity\ArrayCollection|Entity|null
	 */
	public function getContent()
	{
		return \XF::app()->findByContentType($this->content_type, $this->content_id);
	}

	/**
	 * @param bool $canonical
	 * @param array $extraParams
	 * @param null $hash
	 *
	 * @return mixed|string
	 */
	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null): string
	{
		$route = $canonical ? 'canonical:dbtech-shop/transactions' : 'dbtech-shop/transactions';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
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
	 * @return string|\XF\Phrase
	 */
	public function getContentTitle(string $context = '')
	{
		return \XF::phrase('dbtech_shop_transaction_x', ['title' => $this->transaction_log_id]);
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
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
			'Item' => true
		];
		$structure->relations = [
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
			],
			'Ip' => [
				'entity' => 'XF:Ip',
				'type' => self::TO_ONE,
				'conditions' => 'ip_id',
				'primary' => true
			]
		];

		$structure->withAliases = [
			'full' => [
				'User',
				'Recipient',
			],
		];

		return $structure;
	}

	/**
	 * @return \DBTech\Shop\Repository\Transaction|\XF\Mvc\Entity\Repository
	 */
	protected function getTransactionRepo()
	{
		return $this->repository('DBTech\Shop:Transaction');
	}
}