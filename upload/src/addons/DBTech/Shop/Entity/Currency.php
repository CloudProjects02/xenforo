<?php

namespace DBTech\Shop\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Db\Schema\Alter;

/**
 * COLUMNS
 * @property int $currency_id
 * @property string $title
 * @property string $description
 * @property bool $active
 * @property int $display_order
 * @property string $table
 * @property bool $use_table_prefix
 * @property string $column
 * @property bool $use_user_id
 * @property string $user_id_column
 * @property int $decimals
 * @property int $privacy
 * @property bool $blacklist
 * @property string $prefix
 * @property string $suffix
 * @property bool $is_display_currency
 * @property bool $sidebar
 * @property bool $postbit
 * @property bool $can_bank
 * @property bool $can_steal
 * @property bool $can_trade
 * @property float $steal_protect
 * @property float $interest
 * @property bool $customshops
 * @property int $per_reply
 * @property int $per_thread
 * @property int $credits_currency_id
 *
 * GETTERS
 * @property \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\ArrayCollection $Events
 *
 * RELATIONS
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\Bank[] $Banked
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\Lottery[] $Lotteries
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\Item[] $Items
 * @property \XF\Mvc\Entity\AbstractCollection|\DBTech\Shop\Entity\Item[] $BuyBackItems
 */
class Currency extends Entity
{
	/**
	 * @return bool
	 */
	public function isActive(): bool
	{
		return $this->active;
	}
	
	/**
	 * @param \XF\Entity\User|null $user
	 *
	 * @return bool
	 */
	public function canView(?\XF\Entity\User $user = null): bool
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		
		$user = $user ?: $visitor;

		switch ($this->privacy)
		{
			case 0:
				if (!$visitor->hasPermission('dbtech_shop', 'special'))
				{
					return false;
				}
				break;

			case 1:
				if (!$visitor->hasPermission('dbtech_shop', 'special')
					&& $user->user_id != $visitor->user_id
				) {
					return false;
				}
				break;
		}

		return $this->isActive();
	}
	
	/**
	 * @return bool
	 */
	public function canBank(): bool
	{
		return $this->can_bank;
	}
	
	/**
	 * @return bool
	 */
	public function canSteal(): bool
	{
		return ($this->can_steal
			&& (
				\XF::options()->dbtech_shop_steal_lose == 0
				|| $this->getValueFromUser(null, false) > 0.00
			)
		);
	}
	
	/**
	 * @return bool
	 */
	public function canTrade(): bool
	{
		return $this->can_trade;
	}
	
	/**
	 * @param \XF\Entity\User|null $userInfo
	 * @param bool $format
	 *
	 * @return mixed
	 */
	public function getValueFromUser(?\XF\Entity\User $userInfo = null, bool $format = true)
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		
		$userInfo = $userInfo ?: $visitor;
		
		$value = $userInfo->{$this->column};
		
		if ($format)
		{
			// We need to format the value
			$value = $this->getFormattedValue($value);
		}
		else
		{
			$value = sprintf("%.{$this->decimals}f", $value);
		}
		
		return $value;
	}
	
	/**
	 * @param float $value
	 *
	 * @return mixed
	 */
	public function getFormattedValue(float $value = 0.00)
	{
		/*
		if ($value < 0 && $this->negative == 1)
		{
			// Make sure this is displaying correctly
			$value = 0;
		}
		*/
		
		return \XF::language()->numberFormat($value, $this->decimals);
	}
	
	/**
	 * @param int $limit
	 *
	 * @return \DBTech\Credits\XF\Entity\User[]|\XF\Mvc\Entity\AbstractCollection
	 */
	public function getRichestUsers(int $limit = 5)
	{
		return $this->getCurrencyRepo()
			->getRichestUsers($this, $limit)
			->fetch()
			;
	}
	
	/**
	 * @return bool
	 */
	public function isIntegrated(): bool
	{
		return $this->credits_currency_id ? true : false;
	}
	
	/**
	 * @return \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\ArrayCollection
	 */
	public function getEvents()
	{
		if (!$this->isIntegrated())
		{
			return $this->_em->getEmptyCollection();
		}
		
		$addOns = \XF::app()->container('addon.cache');
		if (array_key_exists('DBTech/Credits', $addOns) && $addOns['DBTech/Credits'] >= 905010031)
		{
			/** @var \DBTech\Credits\Entity\Event[] $events */
			$events = $this->finder('DBTech\Credits:Event')
				->where('currency_id', $this->credits_currency_id)
				->fetch()
				->filter(function (\DBTech\Credits\Entity\Event $event): ?\DBTech\Credits\Entity\Event
				{
					if (strpos('dbtech_shop_', $event->event_trigger_id) === 0)
					{
						return $event;
					}
					
					return null;
				})
			;
			
			return $events;
		}
		
		return $this->_em->getEmptyCollection();
	}
	
	/**
	 * @param \DBTech\Credits\Entity\Event[]|\XF\Mvc\Entity\ArrayCollection|null $events
	 */
	public function setEvents(?\XF\Mvc\Entity\ArrayCollection $events = null)
	{
		$this->_getterCache['Events'] = $events;
	}
	
	/**
	 * @param string $column
	 *
	 * @return bool
	 */
	protected function verifySqlSafe(string &$column): bool
	{
		$column = strval($column);
		if ($column === '')
		{
			// Invalid
			return false;
		}
		
		// Ensure this is valid
		$column = preg_replace('/\W/i', '_', preg_quote($column));
		
		return true;
	}
	
	/**
	 * @return bool
	 */
	protected function _preSave(): bool
	{
		if ($this->credits_currency_id)
		{
			$addOns = \XF::app()->container('addon.cache');
			if (array_key_exists('DBTech/Credits', $addOns) && $addOns['DBTech/Credits'] >= 905010031)
			{
				/** @var \DBTech\Credits\Entity\Currency $creditsCurrency */
				$creditsCurrency = $this->_em->find('DBTech\Credits:Currency', $this->credits_currency_id);
				if (!$creditsCurrency)
				{
					$this->error(\XF::phrase('please_enter_valid_value'), 'credits_currency_id');
					return false;
				}
				
				// Very common columns
				$this->title = $creditsCurrency->title;
				$this->description = $creditsCurrency->description;
				$this->display_order = $creditsCurrency->display_order;
				$this->active = $creditsCurrency->active;
				
				// Shared columns
				$this->column = $creditsCurrency->column;
				$this->decimals = $creditsCurrency->decimals;
				$this->privacy = $creditsCurrency->privacy;
				$this->prefix = $creditsCurrency->prefix;
				$this->suffix = $creditsCurrency->suffix;
				$this->is_display_currency = $creditsCurrency->is_display_currency;
				
				// These are handled by the Credits mod
				$this->sidebar = false;
				$this->postbit = false;
				$this->per_thread = 0;
				$this->per_reply = 0;
			}
			else
			{
				$this->error(\XF::phrase('please_enter_valid_value'), 'credits_currency_id');
				return false;
			}
		}
		
		if (!$this->credits_currency_id)
		{
			$db = $this->db();
			$sm = $db->getSchemaManager();
			
			$tableName = ($this->use_table_prefix ? 'xf_' : '') . $this->table;
			
			if (!$sm->tableExists($tableName))
			{
				// Invalid
				$this->error(\XF::phrase('dbtech_currency_missing_table'), 'table');
				return false;
			}
			
			$columns = $sm->getTableColumnDefinitions($tableName);
			
			if (!array_key_exists($this->user_id_column, $columns))
			{
				// Invalid
				$this->error(\XF::phrase('dbtech_currency_missing_user_column'), 'table');
				return false;
			}
			
			if (
				$this->isInsert()
				|| $this->isChanged('column')
			) {
				// deal with desired table column
				$this->blacklist = isset($columns[$this->column]);
				
				if (!$this->blacklist)
				{
					// create or switch custom columns
					if (
						$this->isUpdate()
						&& $this->isChanged('column')
						&& !$this->getPreviousValue('blacklist')
					) {
						// Invalid
						$sm->alterTable($tableName, function (Alter $table) use ($columns)
						{
							if (!array_key_exists($this->getPreviousValue('column'), $columns))
							{

								// Column was either not changed
								$table->addColumn($this->column, 'decimal')
									->length('65,8')
									->unsigned(false)
									->setDefault('0')
								;
							}
							else
							{
								// Column was changed but not blacklisted so rename the column
								$table->changeColumn($this->getPreviousValue('column'), 'decimal')
									->length('65,8')
									->unsigned(false)
									->setDefault('0')
									->renameTo($this->column)
								;
							}
						});
					}
					else
					{
						$sm->alterTable($tableName, function (Alter $table)
						{
							// Column was either not changed
							$table->addColumn($this->column, 'decimal')
								->length('65,8')
								->unsigned(false)
								->setDefault('0')
							;
						});
					}
				}
				elseif (
					$this->isChanged('column')
					&& $this->isUpdate()
					&& !$this->getExistingValue('blacklist')
				) {
					$sm->alterTable($tableName, function (Alter $table)
					{
						$table->dropColumns([$this->getPreviousValue('column')]);
					});
				}
			}
		}
		
		/** @var \DBTech\Shop\Entity\Currency $existing */
		if (
			$this->is_display_currency
			&& $existing = $this->finder('DBTech\Shop:Currency')
				->where('is_display_currency', 1)
				->where('currency_id', '!=', $this->currency_id)
				->fetchOne()
		) {
			// We're changing display currency
			$existing->fastUpdate('is_display_currency', 0);
		}
		
		return true;
	}
	
	/**
	 * @throws \XF\Db\Exception
	 */
	protected function _postSave()
	{
		if ($this->isInsert())
		{
			// New currency
			$this->db()->query('
				INSERT INTO `xf_dbtech_shop_bank`
					(`user_id`, `currency_id`)
				SELECT `user_id`, ' . $this->currency_id . '
				FROM `xf_user` AS `user`
				ORDER BY `user_id`
			');
		}
	}
	
	/**
	 * @throws \XF\PrintableException
	 */
	protected function _postDelete()
	{
		$sm = $this->db()->getSchemaManager();
		
		$tableName = ($this->use_table_prefix ? 'xf_' : '') . $this->table;
		
		if (!$this->blacklist)
		{
			$sm->alterTable($tableName, function (Alter $table)
			{
				$table->dropColumns([$this->column]);
			});
		}

		foreach ($this->Events as $event)
		{
			$event->delete();
		}
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_currency';
		$structure->shortName = 'DBTech\Shop:Currency';
		$structure->primaryKey = 'currency_id';
		$structure->columns = [
			'currency_id'         => ['type' => self::UINT, 'autoIncrement' => true],
			'title'               => ['type' => self::STR, 'required' => true],
			'description'         => ['type' => self::STR, 'default' => ''],
			'active'              => ['type' => self::BOOL, 'default' => true],
			'display_order'       => ['type' => self::UINT, 'default' => 10],
			'table'               => ['type' => self::STR, 'required' => true, 'verify' => 'verifySqlSafe'],
			'use_table_prefix'    => ['type' => self::BOOL, 'default' => true],
			'column'              => ['type' => self::STR, 'required' => true],
			'use_user_id'         => ['type' => self::BOOL, 'default' => true],
			'user_id_column'      => ['type' => self::STR, 'required' => true, 'verify' => 'verifySqlSafe'],
			'decimals'            => ['type' => self::UINT, 'default' => 0, 'max' => 8],
			'privacy'             => ['type' => self::UINT, 'default' => 2, 'max' => 2],
			'blacklist'           => ['type' => self::BOOL, 'default' => false],
			'prefix'              => ['type' => self::STR, 'default' => ''],
			'suffix'              => ['type' => self::STR, 'default' => ''],
			'is_display_currency' => ['type' => self::BOOL, 'default' => false],
			'sidebar'             => ['type' => self::BOOL, 'default' => true],
			'postbit'             => ['type' => self::BOOL, 'default' => true],
			'can_bank'            => ['type' => self::BOOL, 'default' => true],
			'can_steal'           => ['type' => self::BOOL, 'default' => true],
			'can_trade'           => ['type' => self::BOOL, 'default' => true],
			'steal_protect'       => ['type' => self::FLOAT, 'default' => 100.00, 'min' => 0.00, 'max' => 100.00],
			'interest'            => ['type' => self::FLOAT, 'default' => 0.00, 'min' => -100.00, 'max' => 100.00],
			'customshops'         => ['type' => self::BOOL, 'default' => true],
			'per_reply'           => ['type' => self::UINT, 'default' => 1, 'min' => 0],
			'per_thread'          => ['type' => self::UINT, 'default' => 5, 'min' => 0],
			'credits_currency_id' => ['type' => self::UINT, 'default' => 0]
		];
		$structure->behaviors = [
			'DBTech\Shop:Cacheable' => []
		];
		$structure->getters = [
			'Events' => true
		];
		$structure->relations = [
			'Banked' => [
				'entity' => 'DBTech\Shop:Bank',
				'type' => self::TO_MANY,
				'conditions' => 'currency_id',
				'cascadeDelete' => true
			],
			'Lotteries' => [
				'entity' => 'DBTech\Shop:Lottery',
				'type' => self::TO_MANY,
				'conditions' => 'currency_id'
			],
			'Items' => [
				'entity' => 'DBTech\Shop:Item',
				'type' => self::TO_MANY,
				'conditions' => 'currency_id'
			],
			'BuyBackItems' => [
				'entity' => 'DBTech\Shop:Item',
				'type' => self::TO_MANY,
				'conditions' => [
					['buyback_currency_id', '=', '$currency_id']
				]
			],
		];

		return $structure;
	}
	
	/**
	 *
	 */
	protected function _setupDefaults()
	{
		$this->active = true;
		$this->display_order = 10;
		$this->table = 'user';
		$this->use_table_prefix = true;
		$this->use_user_id = true;
		$this->user_id_column = 'user_id';
		$this->decimals = 0;
		$this->privacy = 2;
		$this->is_display_currency = false;
		$this->sidebar = true;
		$this->postbit = true;
		$this->blacklist = false;
		$this->can_bank = true;
		$this->can_steal = true;
		$this->can_trade = true;
		$this->steal_protect = 100;
		$this->interest = 0.00;
		$this->customshops = true;
		$this->per_reply = 1;
		$this->per_thread = 5;
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Currency|\XF\Mvc\Entity\Repository
	 */
	protected function getCurrencyRepo()
	{
		return $this->repository('DBTech\Shop:Currency');
	}
}