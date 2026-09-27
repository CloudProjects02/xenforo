<?php

namespace DBTech\Shop\Install;

use DBTech\Shop\Entity\Category;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\Lottery;
use DBTech\Shop\Entity\LotteryPrize;
use DBTech\Shop\Entity\Purchase;
use DBTech\Shop\Entity\TransactionLog;
use DBTech\Shop\Service\Item\IconService;
use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Exception;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Column;
use XF\Db\Schema\Create;
use XF\Db\SchemaManager;
use XF\PrintableException;
use XF\Service\RebuildNestedSetService;

/**
 * @property AddOn addOn
 * @property App app
 *
 * @method AbstractAdapter db()
 * @method SchemaManager schemaManager()
 * @method Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait Upgrade906019970Trait
{
	/**
	 *
	 */
	public function upgrade906010030Step1(): void
	{
		// Purge the cache
		\XF::registry()->delete([
			'dbt_shop_category',
			'dbt_shop_currency',
			'dbt_shop_item',
			'dbt_shop_itemtype',
			'dbt_shop_lottery',
			'dbt_shop_lotteryprize',
			'dbt_shop_shop',

			'dbtech_shop_category',
			'dbtech_shop_currency',
			'dbtech_shop_item',
			'dbtech_shop_itemtype',
			'dbtech_shop_lottery',
			'dbtech_shop_lotteryprize',
			'dbtech_shop_shop',
		]);
	}

	/**
	 *
	 */
	public function upgrade906010011Step1(): void
	{
		$sm = $this->schemaManager();

		if (!$sm->tableExists('xf_dbtech_shop_tradingcard'))
		{
			$sm->createTable('xf_dbtech_shop_tradingcard', function (Create $table)
			{
				$table->addColumn('tradingcardid', 'int')->autoIncrement();
				$table->addColumn('title', 'varchar', 50)->setDefault('');
				$table->addColumn('description', 'mediumblob')->nullable(true);
				$table->addColumn('active', 'tinyint', 1)->setDefault(1);
				$table->addColumn('rarity', 'tinyint', 1)->setDefault(1);
				$table->addColumn('filename', 'varchar', 255)->setDefault('');
			});
		}

		if (!$sm->tableExists('xf_dbtech_shop_tradingcard_collection'))
		{
			$sm->createTable('xf_dbtech_shop_tradingcard_collection', function (Create $table)
			{
				$table->addColumn('tradingcardid', 'int');
				$table->addColumn('userid', 'int');
				$table->addColumn('trade_locked', 'tinyint', 1)->setDefault(0);
				$table->addPrimaryKey(['tradingcardid', 'userid']);
			});
		}

		if ($sm->tableExists('xf_dbtech_shop_lotteryprize'))
		{
			$sm->renameTable('xf_dbtech_shop_lotteryprize', 'xf_dbtech_shop_lottery_prize');
		}

		if ($sm->tableExists('xf_dbtech_shop_lotteryticket'))
		{
			$sm->renameTable('xf_dbtech_shop_lotteryticket', 'xf_dbtech_shop_lottery_ticket');
		}

		if ($sm->tableExists('xf_dbtech_shop_shoppingcart'))
		{
			$sm->renameTable('xf_dbtech_shop_shoppingcart', 'xf_dbtech_shop_cart');
		}

		if ($sm->tableExists('xf_dbtech_shop_threadban'))
		{
			$sm->renameTable('xf_dbtech_shop_threadban', 'xf_dbtech_shop_thread_ban');
		}

		if ($sm->tableExists('xf_dbtech_shop_tradingcard'))
		{
			$sm->renameTable('xf_dbtech_shop_tradingcard', 'xf_dbtech_shop_trading_card');
		}

		if ($sm->tableExists('xf_dbtech_shop_tradingcard_collection'))
		{
			$sm->renameTable('xf_dbtech_shop_tradingcard_collection', 'xf_dbtech_shop_trading_card_collection');
		}

		if ($sm->tableExists('xf_dbtech_shop_transactionlog'))
		{
			$sm->renameTable('xf_dbtech_shop_transactionlog', 'xf_dbtech_shop_transaction_log');
		}
	}

	/**
	 *
	 */
	public function upgrade906010011Step2(): void
	{
		$sm = $this->schemaManager();

		$tables = $this->getTables();

		$key = 'xf_dbtech_shop_category_field';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_category_prefix';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_category_watch';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_item_field';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_item_field_value';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_item_filter_map';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_item_prefix';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_item_prefix_group';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_item_rating';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_item_watch';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_lottery_history';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_lottery_prize_map';
		$sm->createTable($key, $tables[$key]);

		$sm->dropTable('xf_dbtech_shop_itemtype');
	}

	/**
	 *
	 */
	public function upgrade906010011Step3(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$lotteries = $db->fetchAll('
			SELECT lotteryid, prizes
			FROM xf_dbtech_shop_lottery
		');
		foreach ($lotteries AS $lottery)
		{
			$prizes = @unserialize($lottery['prizes']);
			$prizes = is_array($prizes) ? $prizes : [];

			$newPrizes = [];
			foreach ($prizes AS $prize)
			{
				if (empty($prize['prizeid'])
					|| empty($prize['currencyid'])
					|| empty($prize['prize'])
				)
				{
					continue;
				}

				$prizeData = [
					'lottery_id' => $lottery['lotteryid'],
					'lottery_prize_id' => $prize['prizeid'],
					'currency_id' => $prize['currencyid'],
					'prize_amount' => $prize['prize'],
				];

				$newPrizes[] = $prizeData;

				$db->insert('xf_dbtech_shop_lottery_prize_map', $prizeData, false, false, 'IGNORE');
			}

			$db->update('xf_dbtech_shop_lottery', [
				'prizes' => json_encode($newPrizes),
			], null);
		}

		$db->commit();
	}

	/**
	 *
	 */
	public function upgrade906010011Step4(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_cart', function (Alter $table)
		{
			$table->addColumn('recipient_user_id', 'int')->after('itemid');
			$table->addColumn('recipient_username', 'varchar', 50)->after('recipient_user_id');
			$table->addColumn('message', 'mediumblob')->nullable(true)->after('quantity');
		});

		$sm->alterTable('xf_dbtech_shop_category', function (Alter $table)
		{
			$table->dropColumns([
				'permissions',
				'bitfield',
				'active',
				'customshops',
			]);
			$table->addColumn('parent_category_id', 'int')->setDefault(0)->after('description');
			$table->addColumn('lft', 'int')->setDefault(0);
			$table->addColumn('rgt', 'int')->setDefault(0);
			$table->addColumn('depth', 'smallint', 5)->setDefault(0);
			$table->addColumn('breadcrumb_data', 'blob');
			$table->addColumn('item_count', 'int')->setDefault(0);
			$table->addColumn('last_update', 'int')->setDefault(0);
			$table->addColumn('last_item_title', 'varchar', 100)->setDefault('');
			$table->addColumn('last_item_id', 'int')->setDefault(0);
			$table->addColumn('prefix_cache', 'mediumblob');
			$table->addColumn('field_cache', 'mediumblob');
			$table->addColumn('item_filters', 'blob');
			$table->addColumn('require_prefix', 'tinyint', 3)->setDefault(0);
			$table->addColumn('thread_node_id', 'int')->setDefault(0);
			$table->addColumn('thread_prefix_id', 'int')->setDefault(0);
			$table->addColumn('item_update_notify', 'enum')->values(['thread', 'reply'])->setDefault('thread');
			$table->addColumn('always_moderate_create', 'tinyint', 3)->setDefault(0);
			$table->addColumn('always_moderate_update', 'tinyint', 3)->setDefault(0);
			$table->addColumn('min_tags', 'smallint', 5)->setDefault(0);
			$table->addColumn('sales', 'int')->setDefault(0);
			$table->addColumn('sales_amounts', 'mediumblob')->nullable(true);
			$table->addColumn('latest_customer_id', 'int')->setDefault(0);
			$table->addColumn('latest_sale_id', 'int')->setDefault(0);
			$table->addColumn('beneficiary', 'int')->unsigned(false)->setDefault(-1);
			$table->addColumn('beneficiary_split', 'tinyint', 3)->setDefault(100);
			$table->addColumn('num_ratings', 'int')->setDefault(0);
			$table->addColumn('average_rating', 'double')->setDefault(0);
			$table->addColumn('positive_percent', 'double')->setDefault(0);
			$table->addColumn('negative_percent', 'double')->setDefault(0);
			$table->addColumn('neutral_percent', 'double')->setDefault(0);
			$table->addColumn('can_have_feedback', 'tinyint', 3)->setDefault(1);
			$table->addKey(['parent_category_id', 'lft']);
			$table->addKey(['lft', 'rgt']);
		});

		$sm->alterTable('xf_dbtech_shop_currency', function (Alter $table)
		{
			$table->dropColumns(['postbit']);
			$table->addColumn('postbit', 'tinyint', 3)->setDefault(1);
		});

		$sm->alterTable('xf_dbtech_shop_feedback', function (Alter $table)
		{
			$table->dropColumns(['shopid']);
		});

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->addColumn('category_id', 'int')->after('itemid');
			$table->addColumn('creation_date', 'int')->setDefault(0)->after('displayorder');
			$table->addColumn('last_update', 'int')->setDefault(0)->after('creation_date');
			$table->addColumn('_old_item_id', 'int')->setDefault(0);

			$table->dropColumns([
				'shop',
				'permissions',
				'prepurchase_callback_class',
				'prepurchase_callback_method',
				'postpurchase_callback_class',
				'postpurchase_callback_method',
				'sellback_callback_class',
				'sellback_callback_method',
				'configure_callback_class',
				'configure_callback_method',
				'gift_callback_class',
				'gift_callback_method',
				'discard_callback_class',
				'discard_callback_method',
			]);
			$table->addColumn('item_state', 'enum')->values(['visible', 'moderated', 'deleted'])->setDefault('visible')->after('active');
			$table->addColumn('length_amount', 'tinyint', 3)->after('duration');
			$table->addColumn('length_unit', 'enum')->values(['day', 'month', 'year', ''])->after('length_amount');
			$table->addColumn('currency_id', 'int')->setDefault(0);
			$table->addColumn('price', 'double')->setDefault(0);
			$table->addColumn('buyback_currency_id', 'int')->setDefault(0);
			$table->addColumn('buyback_price', 'double')->setDefault(0);
			$table->addColumn('buyback_time', 'int')->setDefault(0);
			$table->addColumn('buyback_replenish', 'tinyint', 3)->setDefault(1);
			$table->addColumn('notifications', 'mediumblob')->nullable(true);
			$table->addColumn('notifications_config', 'mediumblob')->nullable(true);
			$table->addColumn('stock', 'int')->unsigned(false)->setDefault('0');
			$table->addColumn('maxstock', 'int')->unsigned(false)->setDefault('0');
			$table->addColumn('refill_time', 'int')->setDefault(0);
			$table->addColumn('last_refill_date', 'int')->setDefault(0);
			$table->addColumn('username', 'varchar', 50)->after('ownerid');
			$table->addColumn('ip_id', 'int')->setDefault(0)->after('username');
			$table->addColumn('warning_id', 'int')->setDefault(0)->after('ip_id');
			$table->addColumn('warning_message', 'varchar', 255)->setDefault('')->after('warning_id');
			$table->addColumn('thread_node_id', 'int')->setDefault(0)->after('itemtypeid');
			$table->addColumn('thread_prefix_id', 'int')->setDefault(0)->after('thread_node_id');
			$table->addColumn('discussion_thread_id', 'int')->setDefault(0)->after('thread_prefix_id');
			$table->addColumn('field_cache', 'mediumblob')->after('discussion_thread_id');
			$table->addColumn('item_fields', 'mediumblob')->after('field_cache');
			$table->addColumn('item_filters', 'blob')->after('item_fields');
			$table->addColumn('rating_count', 'int')->setDefault(0);
			$table->addColumn('rating_sum', 'int')->setDefault(0);
			$table->addColumn('rating_avg', 'float', '')->setDefault(0);
			$table->addColumn('rating_weighted', 'float', '')->setDefault(0);
			$table->addColumn('review_count', 'int')->setDefault(0);
			$table->addColumn('icon_date', 'int')->setDefault(0);
			$table->addColumn('prefix_id', 'int')->setDefault(0);
			$table->addColumn('tags', 'mediumblob');
			$table->addKey(['category_id', 'last_update'], 'category_last_update');
			$table->addKey(['category_id', 'rating_weighted'], 'category_rating_weighted');
			$table->addKey('last_update');
			$table->addKey('rating_weighted');
			$table->addKey(['ownerid', 'last_update']);
			$table->addKey('discussion_thread_id');
			$table->addKey('prefix_id');
		});

		$sm->alterTable('xf_dbtech_shop_lottery', function (Alter $table)
		{
			$table->dropColumns([
				'permissions',
				'bitfield',
			]);
		});

		$sm->alterTable('xf_dbtech_shop_lottery_prize', function (Alter $table)
		{
			$table->dropColumns(['bitfield']);
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step5(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_purchase', function (Alter $table)
		{
			$table->addColumn('category_id', 'int')->after('dateline');
			$table->addColumn('buyer_username', 'varchar', 50)->setDefault('')->after('buyer');
			$table->addColumn('configured', 'tinyint', 3)->setDefault(0)->after('hidden');
			$table->addColumn('discussion_thread_id', 'int')->setDefault(0);
			$table->dropColumns(['feature']);
			$table->addKey('discussion_thread_id');
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step6(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_transaction_log', function (Alter $table)
		{
			$table->addColumn('content_type', 'varbinary', 25)->after('action');
			$table->addColumn('content_id', 'int')->setDefault(0)->after('content_type');
			$table->addColumn('ip_id', 'int', 10)->setDefault(0)->after('dateline');
			$table->dropColumns(['ipaddress']);
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step7(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_shopinventory', function (Alter $table)
		{
			$table->addColumn('category_id', 'int')->after('itemid');
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step8(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_bank', function (Alter $table)
		{
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('currencyid', 'currency_id');
			$table->renameColumn('lastinterest', 'last_interest_date');
		});

		$this->db()->emptyTable('xf_dbtech_shop_cart');

		$sm->alterTable('xf_dbtech_shop_cart', function (Alter $table)
		{
			$table->dropPrimaryKey();
			$table->dropColumns(['shopid']);
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('itemid', 'item_id');
			$table->addPrimaryKey(['user_id', 'item_id']);
		});

		$sm->alterTable('xf_dbtech_shop_category', function (Alter $table)
		{
			$table->renameColumn('categoryid', 'category_id');
			$table->renameColumn('displayorder', 'display_order');
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step9(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_currency', function (Alter $table)
		{
			$table->renameColumn('currencyid', 'currency_id');
			$table->renameColumn('displayorder', 'display_order');
			$table->renameColumn('useprefix', 'use_table_prefix');
			$table->renameColumn('userid', 'use_user_id');
			$table->renameColumn('usercol', 'user_id_column');
			$table->renameColumn('displaycurrency', 'is_display_currency');
			$table->renameColumn('canbank', 'can_bank');
			$table->renameColumn('cansteal', 'can_steal');
			$table->renameColumn('cantrade', 'can_trade');
			$table->renameColumn('stealprotect', 'steal_protect');
			$table->renameColumn('perreply', 'per_reply');
			$table->renameColumn('perthread', 'per_thread');
			$table->renameColumn('creditscurrencyid', 'credits_currency_id');
		});

		$sm->alterTable('xf_dbtech_shop_feedback', function (Alter $table)
		{
			$table->renameColumn('feedbackid', 'feedback_id');
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('purchaseid', 'purchase_id');
		});

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->renameColumn('itemid', 'item_id');
			$table->renameColumn('displayorder', 'display_order');
			$table->renameColumn('categoryid', 'category_id');
			$table->renameColumn('shopicon', 'shop_icon');
			$table->renameColumn('itemtypeid', 'item_type_id');
			$table->renameColumn('giftable', 'is_giftable');
			$table->renameColumn('giftpm', 'send_gift_pm');
			$table->renameColumn('exclusiveitem', 'is_exclusive');
			$table->renameColumn('autodiscard', 'auto_discard');
			$table->renameColumn('autodiscard_expiry', 'auto_discard_expiry');
			$table->renameColumn('ownerid', 'user_id');
			$table->renameColumn('uniqueitem', 'is_unique');
			$table->renameColumn('onlygiftable', 'is_only_giftable');
			$table->renameColumn('reconfigure', 'can_reconfigure');
			$table->renameColumn('regift', 'can_regift');
			$table->renameColumn('nodisplay', 'is_always_hidden');
			$table->renameColumn('threadcreation', 'purchasable_thread_creation');
			$table->renameColumn('threadpage', 'purchasable_thread_page');
			$table->renameColumn('customshops', 'enabled_custom_shops');
			$table->renameColumn('stealthed', 'is_stealth_item');
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step10(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_lottery', function (Alter $table)
		{
			$table->renameColumn('lotteryid', 'lottery_id');
			$table->renameColumn('ticketprice', 'ticket_price');
			$table->renameColumn('currencyid', 'currency_id');
			$table->renameColumn('drawnnumbers', 'drawn_numbers');
			$table->renameColumn('drawinterval', 'draw_interval_days');
			$table->renameColumn('nextdraw', 'next_draw_date');
			$table->renameColumn('prevdraw', 'previous_draw_date');
			$table->renameColumn('ticketssold', 'tickets_sold');
		});

		$sm->alterTable('xf_dbtech_shop_lottery_prize', function (Alter $table)
		{
			$table->renameColumn('lotteryprizeid', 'lottery_prize_id');
		});

		$sm->alterTable('xf_dbtech_shop_lottery_ticket', function (Alter $table)
		{
			$table->renameColumn('lotteryticketid', 'lottery_ticket_id');
			$table->renameColumn('lotteryid', 'lottery_id');
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('dateline', 'ticket_date');
			$table->renameColumn('lotterydraw', 'draw_date');
			$table->renameColumn('prizeid', 'lottery_prize_id');
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step11(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_purchase', function (Alter $table)
		{
			$table->renameColumn('purchaseid', 'purchase_id');
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('buyer', 'buyer_user_id');
			$table->renameColumn('featureid', 'item_id');
			$table->renameColumn('feedbackstatus', 'feedback_status');
			$table->renameColumn('expirydate', 'expiry_date');
		});

		$sm->alterTable('xf_dbtech_shop_thread_ban', function (Alter $table)
		{
			$table->renameColumn('threadid', 'thread_id');
			$table->renameColumn('userid', 'user_id');
		});

		$sm->alterTable('xf_dbtech_shop_trade', function (Alter $table)
		{
			$table->renameColumn('tradeid', 'trade_id');
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step12(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_trade_offer', function (Alter $table)
		{
			$table->renameColumn('tradeid', 'trade_id');
			$table->renameColumn('userid', 'user_id');
		});

		$sm->alterTable('xf_dbtech_shop_trading_card', function (Alter $table)
		{
			$table->renameColumn('tradingcardid', 'trading_card_id');
		});

		$sm->alterTable('xf_dbtech_shop_trading_card_collection', function (Alter $table)
		{
			$table->renameColumn('tradingcardid', 'trading_card_id');
			$table->renameColumn('userid', 'user_id');
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step13(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_transaction_log', function (Alter $table)
		{
			$table->renameColumn('transactionlogid', 'transaction_log_id');
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('recipient', 'recipient_user_id');
		});
	}

	/**
	 * @throws Exception
	 * @throws Exception
	 * @throws Exception
	 * @throws Exception
	 * @throws Exception
	 * @throws Exception
	 */
	public function upgrade906010011Step14(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$this->renamePermission('dbtech_shop', 'canview', 'dbtech_shop', 'view');
		$this->renamePermission('dbtech_shop', 'ismanager', 'dbtech_shop', 'moderateShop');
		$this->renamePermission('dbtech_shop', 'canbank', 'dbtech_shop', 'bank');
		$this->renamePermission('dbtech_shop', 'canlottery', 'dbtech_shop', 'viewLottery');
		$this->renamePermission('dbtech_shop', 'cantrade', 'dbtech_shop', 'trade');
		$this->renamePermission('dbtech_shop', 'defaults_canbuyitem', 'dbtech_shop', 'purchase');

		$db->commit();
	}

	/**
	 * @throws Exception
	 */
	public function upgrade906010011Step15(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$db->query('
			UPDATE xf_dbtech_shop_item
			SET creation_date = UNIX_TIMESTAMP(), last_update = UNIX_TIMESTAMP()
		');

		$db->query('
			UPDATE xf_dbtech_shop_item AS item
			LEFT JOIN xf_user AS user ON(user.user_id = item.user_id)
			SET item.username = user.username
			WHERE user.username IS NOT NULL
		');

		$db->query('
			UPDATE xf_dbtech_shop_item
			SET code = \'a:0:{}\'
			WHERE code IS NULL
		');

		$db->query('
			UPDATE xf_dbtech_shop_item
			SET item_state = \'visible\'
			WHERE active = 1
		');

		$db->query('
			UPDATE xf_dbtech_shop_item
			SET item_state = \'deleted\'
			WHERE active = 0
		');

		$db->query('
			UPDATE xf_dbtech_shop_purchase AS purchase
			LEFT JOIN xf_user AS user ON(user.user_id = purchase.buyer_user_id)
			SET purchase.buyer_username = user.username
			WHERE user.username IS NOT NULL
		');

		$db->query('
			UPDATE xf_dbtech_shop_purchase
			SET configuration = \'a:0:{}\'
			WHERE configuration IS NULL
		');

		$db->query('
			UPDATE xf_dbtech_shop_purchase
			SET configured = 1
			WHERE configuration != \'a:0:{}\'
		');

		$db->commit();
	}

	/**
	 *
	 */
	public function upgrade906010011Step16(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$purchases = $db->fetchAll('
			SELECT purchase.purchase_id, purchase.dateline, item.duration
			FROM xf_dbtech_shop_purchase AS purchase
			LEFT JOIN xf_dbtech_shop_item AS item USING(item_id)
			WHERE purchase.expiry_date = 1
		');
		foreach ($purchases AS $purchase)
		{
			$db->update(
				'xf_dbtech_shop_purchase',
				['expiry_date' => ($purchase['duration'] ? ($purchase['dateline'] + ($purchase['duration'] * 86400)) : 0)],
				'purchase_id = ?',
				$purchase['purchase_id']
			);
		}

		$db->commit();
	}

	/**
	 *
	 */
	public function upgrade906010011Step17(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$db->update(
			'xf_user',
			['dbtech_shop_purchase' => null],
			null
		);

		$db->commit();
	}

	/**
	 *
	 * @throws Exception
	 */
	public function upgrade906010011Step18(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$shops = $db->fetchAll('
			SELECT * FROM xf_dbtech_shop_shop
		');
		foreach ($shops AS $shop)
		{
			$salesAmounts = @unserialize($shop['salesamounts']);
			$salesAmounts = is_array($salesAmounts) ? $salesAmounts : [];

			$category = [
				'title' => $shop['title'],
				'description' => $shop['description'],
				'display_order' => $shop['displayorder'],
				'prefix_cache' => '',
				'field_cache' => '',
				'item_filters' => '',
				'breadcrumb_data' => serialize([]),
				'sales' => $shop['sales'],
				'sales_amounts' => json_encode($salesAmounts),
				'latest_customer_id' => $shop['latestcustomer'],
				'latest_sale_id' => $shop['latestsale'],
				'beneficiary' => ($shop['beneficiary'] == -1 ? 0 : $shop['beneficiary']),
				'beneficiary_split' => $shop['beneficiary_split'],
				'num_ratings' => $shop['numratings'],
				'average_rating' => $shop['averagerating'],
				'positive_percent' => $shop['positivepercent'],
				'negative_percent' => $shop['negativepercent'],
				'neutral_percent' => $shop['neutralpercent'],
				'can_have_feedback' => $shop['canhavefeedback'],
			];

			$db->insert('xf_dbtech_shop_category', $category);
			$categoryId = $db->lastInsertId();

			$db->update('xf_dbtech_shop_purchase', [
				'category_id' => $categoryId,
			], 'shopid = ?', $shop['shopid']);

			$db->update('xf_dbtech_shop_shopinventory', [
				'category_id' => $categoryId,
			], 'shopid = ?', $shop['shopid']);
		}

		$db->query('
			UPDATE xf_dbtech_shop_category
			SET sales_amounts = \'[]\'
			WHERE sales_amounts IS NULL
		');

		$db->commit();
	}

	/**
	 * @throws Exception
	 */
	public function upgrade906010011Step19(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$entries = $db->fetchAll('
			SELECT * FROM xf_dbtech_shop_shopinventory
			WHERE active = 1
			ORDER BY itemid, category_id
		');

		$seenItemIds = [];
		foreach ($entries AS $entry)
		{
			$data = [
				'category_id'  => $entry['category_id'],
				'currency_id' => $entry['currencyid'],
				'price' => $entry['price'],
				'buyback_currency_id' => $entry['buybackcurrencyid'],
				'buyback_price' => $entry['buybackprice'],
				'buyback_time' => $entry['buybacktime'],
				'buyback_replenish' => $entry['buybackreplenish'],
				'notifications' => $entry['notifications'],
				'notifications_config' => $entry['notifications_config'],
				'stock' => $entry['stock'],
				'maxstock' => $entry['maxstock'],
				'refill_time' => $entry['refilltime'],
				'last_refill_date' => $entry['lastrefill'],
			];

			if (!isset($seenItemIds[$entry['itemid']]))
			{
				$db->update('xf_dbtech_shop_item', $data, 'item_id = ?', $entry['itemid']);

				$seenItemIds[$entry['itemid']] = true;
			}
			else
			{
				// Get old item and add new data
				$item = $db->fetchRow('SELECT * FROM xf_dbtech_shop_item WHERE item_id = ?', $entry['itemid']);
				$item = array_merge($item, $data, [
					'_old_item_id' => $entry['itemid'],
				]);
				unset($item['item_id']);

				// Insert new item
				$db->insert('xf_dbtech_shop_item', $item);
				$itemId = $db->lastInsertId();

				// Update the record
				$db->update('xf_dbtech_shop_purchase', [
					'item_id' => $itemId,
				], 'item_id = ? AND category_id = ?', [
					$entry['itemid'],
					$entry['category_id'],
				]);

				$seenItemIds[$itemId] = true;
			}
		}

		$db->query("
			UPDATE xf_dbtech_shop_purchase AS purchase
			LEFT JOIN xf_dbtech_shop_item AS item USING(item_id)
			SET purchase.category_id = item.category_id
			WHERE purchase.category_id = 0
		");

		$db->commit();
	}

	/**
	 *
	 */
	public function upgrade906010011Step20(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_purchase', function (Alter $table)
		{
			$table->dropColumns(['shopid', 'category_id']);
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step21(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->dropColumns(['active']);
			$table->dropColumns(['_old_item_id']);
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step22(): void
	{
		$sm = $this->schemaManager();

		$sm->dropTable('xf_dbtech_shop_shopinventory');
		$sm->dropTable('xf_dbtech_shop_shop');
	}

	/**
	 *
	 */
	public function upgrade906010011Step23(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$items = $db->fetchAll('
			SELECT *
			FROM xf_dbtech_shop_item
			WHERE duration > 0
		');
		foreach ($items AS $item)
		{
			$amount = $item['duration'];
			$unit = 'day';

			if ($item['duration'] > 255)
			{
				if (($item['duration'] % 365) == 0)
				{
					// Divided cleanly by year
					$amount = $item['duration'] / 365;
					$unit = 'year';
				}
				else if (($item['duration'] % 30) == 0)
				{
					// Divided cleanly by month
					$amount = $item['duration'] / 30;
					$unit = 'month';
				}
				else
				{
					// Default values
					$amount = 1;
					$unit = 'year';
				}
			}

			$db->update(
				'xf_dbtech_shop_item',
				['length_amount' => $amount, 'length_unit' => $unit],
				'item_id = ?',
				$item['item_id']
			);
		}

		$db->commit();
	}

	/**
	 * @throws PrintableException
	 */
	public function upgrade906010011Step24(): void
	{
		$items = $this->db()->fetchAll('
			SELECT *
			FROM xf_dbtech_shop_item
			WHERE shop_icon <> \'\'
		');
		foreach ($items AS $item)
		{
			$itemEntity = \XF::app()->em()
				->instantiateEntity(Item::class, $item)
			;

			$iconService = \XF::app()->service(IconService::class, $itemEntity);
			$iconService->logIp(false);

			if (!$iconService->setImage($item['shop_icon']))
			{
				continue;
			}

			$iconService->updateIcon();
		}
	}

	/**
	 *
	 */
	public function upgrade906010011Step25(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->dropColumns([
				'duration',
				'shop_icon',
				'icon',
			]);
		});
	}

	/**
	 *
	 */
	public function upgrade906010011Step26(): void
	{
		$this->deleteWidget('dbtech_shop_wallet');
		$this->deleteWidget('dbtech_shop_cart');

		$this->insertNamedWidget('dbtech_shop_wallet');
		$this->insertNamedWidget('dbtech_shop_cart');
		$this->insertNamedWidget('dbtech_shop_list_top_items');
		$this->insertNamedWidget('dbtech_shop_overview_latest_reviews');
		$this->insertNamedWidget('dbtech_shop_overview_top_authors');
		$this->insertNamedWidget('dbtech_shop_whats_new_overview_new_items');
		$this->insertNamedWidget('dbtech_shop_forum_overview_new_items');
	}

	/**
	 *
	 */
	public function upgrade906010012Step1(): void
	{
		$sm = $this->schemaManager();

		$this->db()->update('xf_dbtech_shop_currency', [
			'steal_protect' => '100.00',
		], 'steal_protect = -1');

		$sm->alterTable('xf_dbtech_shop_currency', function (Alter $table)
		{
			$table->changeColumn('steal_protect')->resetDefinition()->type('double')->setDefault('100.00');
		});

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->addColumn('reaction_score', 'int')->setDefault(0)->after('ip_id');
			$table->addColumn('reactions', 'blob')->after('reaction_score');
			$table->addColumn('reaction_users', 'blob')->after('reactions');
		});
	}

	/**
	 *
	 */
	public function upgrade906010012Step2(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('dbtech_shop_item_count', 'int')->setDefault(0)->after('dbtech_shop_pendingtrades');
			$table->addKey('dbtech_shop_item_count', 'dbtech_shop_item_count');
		});
	}

	/**
	 *
	 */
	public function upgrade906010012Step3(): void
	{
		$this->deleteWidget('dbtech_shop_wallet');
		$this->insertNamedWidget('dbtech_shop_wallet');
	}

	/**
	 * @param array $stepParams
	 *
	 * @return array|bool
	 */
	public function upgrade906010014Step1(array $stepParams): bool|array
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];

		return $this->entityColumnsToJson(Item::class, ['code', 'item_fields', 'user_criteria'], $position, $stepParams);
	}

	/**
	 * @param array $stepParams
	 *
	 * @return array|bool
	 */
	public function upgrade906010014Step2(array $stepParams): bool|array
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];

		return $this->entityColumnsToJson(TransactionLog::class, ['info'], $position, $stepParams);
	}

	/**
	 * @param array $stepParams
	 *
	 * @return array|bool
	 */
	public function upgrade906010014Step3(array $stepParams): bool|array
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];

		return $this->entityColumnsToJson(Purchase::class, ['configuration'], $position, $stepParams);
	}

	/**
	 * @param array $stepParams
	 *
	 * @return array|bool
	 */
	public function upgrade906010014Step4(array $stepParams): bool|array
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];

		return $this->entityColumnsToJson(LotteryPrize::class, ['numbers'], $position, $stepParams);
	}

	/**
	 * @param array $stepParams
	 *
	 * @return array|bool
	 */
	public function upgrade906010014Step5(array $stepParams): bool|array
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];

		return $this->entityColumnsToJson(Category::class, ['prefix_cache', 'field_cache'], $position, $stepParams);
	}

	/**
	 * @param array $stepParams
	 *
	 * @return array|bool
	 */
	public function upgrade906010014Step6(array $stepParams): bool|array
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];

		return $this->entityColumnsToJson(Lottery::class, ['drawn_numbers'], $position, $stepParams);
	}

	/**
	 *
	 */
	public function upgrade906010014Step7(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$lotteries = $db->fetchAll('
			SELECT lottery_id, drawn_numbers
			FROM xf_dbtech_shop_lottery
		');
		foreach ($lotteries AS $lottery)
		{
			$drawnNumbers = json_decode($lottery['drawn_numbers'], true);

			foreach ($drawnNumbers AS $dateline => $numbers)
			{
				$db->insert('xf_dbtech_shop_lottery_history', [
					'lottery_id' => $lottery['lottery_id'],
					'drawn_numbers' => json_encode($numbers),
					'draw_date' => $dateline,
					'tickets_sold' => 0,
				]);
			}
		}

		$db->commit();
	}

	/**
	 *
	 */
	public function upgrade906010015Step1(): void
	{
		$sm = $this->schemaManager();

		$tables = $this->getTables();

		if (!$sm->tableExists('xf_dbtech_shop_trade'))
		{
			$key = 'xf_dbtech_shop_trade';
			$sm->createTable($key, $tables[$key]);
		}
		else
		{
			$sm->alterTable('xf_dbtech_shop_trade', function (Alter $table)
			{
				$table->renameColumn('user1', 'creator_user_id');
				$table->renameColumn('user2', 'recipient_user_id');
				$table->renameColumn('status', 'trade_state');
				$table->renameColumn('user1_accepted', 'creator_accepted');
				$table->renameColumn('user2_accepted', 'recipient_accepted');
				$table->addColumn('creator_username', 'varchar', 50)->after('creator_user_id');
				$table->addColumn('recipient_username', 'varchar', 50)->after('recipient_user_id');
			});
		}

		if (!$sm->tableExists('xf_dbtech_shop_trade_offer'))
		{
			$key = 'xf_dbtech_shop_trade_offer';
			$sm->createTable($key, $tables[$key]);
		}
		else
		{
			$sm->alterTable('xf_dbtech_shop_trade_offer', function (Alter $table)
			{
				$table->changeColumn('feature')->resetDefinition()->type('varchar', 25)->setDefault('dbtech_shop_purchase')->renameTo('content_type');
				$table->renameColumn('featureid', 'content_id');
				$table->addColumn('finalized', 'tinyint')->setDefault(0);
			});
		}
	}

	/**
	 * @throws Exception
	 */
	public function upgrade906010015Step2(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$db->query("
			UPDATE xf_dbtech_shop_trade AS trade
			SET creator_username = IFNULL((SELECT username FROM xf_user WHERE user_id = trade.creator_user_id), ''),
			    recipient_username = IFNULL((SELECT username FROM xf_user WHERE user_id = trade.recipient_user_id), '')
		");
		$db->commit();
	}

	/**
	 * @throws Exception
	 */
	public function upgrade906010015Step3(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$db->query("
			UPDATE xf_user AS user
			SET dbtech_shop_pendingtrades = (
				SELECT COUNT(*)
				FROM xf_dbtech_shop_trade
				WHERE trade_state IN('pending', 'open', 'awaiting_accept')
					AND (creator_user_id = user.user_id
			  			OR recipient_user_id = user.user_id)
			)
		");

		$db->commit();
	}

	/**
	 *
	 */
	public function upgrade906010015Step4(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$db->emptyTable('xf_dbtech_shop_trade');
		$db->emptyTable('xf_dbtech_shop_trade_offer');

		$db->commit();
	}

	/**
	 *
	 */
	public function upgrade906010015Step5(): void
	{
		$sm = $this->schemaManager();

		$tables = $this->getTables();

		$key = 'xf_dbtech_shop_trade_post';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_shop_trade_post_comment';
		$sm->createTable($key, $tables[$key]);
	}

	/**
	 * @throws Exception
	 * @throws Exception
	 */
	public function upgrade906010015Step6(): void
	{
		$this->executeUpgradeQuery("
			DELETE FROM xf_user_alert
			WHERE content_type = 'dbtech_shop_item'
				AND action IN('delete', 'undelete', 'edit', 'move', 'reassign_from', 'reassign_to')
		");

		$this->executeUpgradeQuery("
			UPDATE xf_user_alert
			SET content_type = 'user',
			    action = CONCAT_WS('_', 'dbt_shop_rating', action)
			WHERE content_type = 'dbtech_shop_rating'
				AND action IN('delete', 'edit')
		");
	}

	/**
	 *
	 */
	public function upgrade906010033Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_shop_item', function (Alter $table)
		{
			$table->addColumn('display_in_list', 'tinyint')->after('item_filters');
		});
	}

	/**
	 * @throws Exception
	 */
	public function upgrade906010033Step2(): void
	{
		$this->executeUpgradeQuery("
			UPDATE xf_dbtech_shop_item
			SET display_in_list = 1
		");
	}

	/**
	 * @throws Exception
	 * @throws Exception
	 */
	public function upgrade906010035Step1(): void
	{
		$defaultValue = [
			'enabled' => 1,
			'right_position' => false,
			'right_text' => true,
		];

		$this->query("
			UPDATE xf_option
			SET default_value = ?
			WHERE option_id = 'dbtech_shop_navbar'
		", json_encode($defaultValue));

		$navbarDefaults = json_decode($this->db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'dbtech_shop_navbar'
		"), true);

		$update = false;
		foreach (array_keys($defaultValue) AS $key)
		{
			if (!isset($navbarDefaults[$key]))
			{
				$update = true;
				$navbarDefaults[$key] = $defaultValue[$key];
			}
		}

		if ($update)
		{
			$this->query("
				UPDATE xf_option
				SET option_value = ?
				WHERE option_id = 'dbtech_shop_navbar'
			", json_encode($navbarDefaults));
		}
	}

	/**
	 * @throws Exception
	 */
	public function upgrade906010036Step1(): void
	{
		$defaultValue = [
			'width' => '',
			'height' => '',
			'disableScaling' => false,
		];

		$this->query("
			UPDATE xf_option
			SET default_value = ?
			WHERE option_id = 'dbtechShopItemIconMaxDimensions'
		", json_encode($defaultValue));

		$dimensionDefaults = json_decode($this->db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'dbtechShopItemIconMaxDimensions'
		"), true);

		$update = false;
		foreach (array_keys($defaultValue) AS $key)
		{
			if (!isset($dimensionDefaults[$key]))
			{
				$update = true;
				$dimensionDefaults[$key] = $defaultValue[$key];
			}
		}

		if ($update)
		{
			$this->query("
				UPDATE xf_option
				SET option_value = ?
				WHERE option_id = 'dbtechShopItemIconMaxDimensions'
			", json_encode($dimensionDefaults));
		}
	}

	/**
	 *
	 */
	public function upgrade906010037Step1(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$db->emptyTable('xf_dbtech_shop_lottery_history');

		$lotteries = $db->fetchAll('
			SELECT lottery_id, drawn_numbers
			FROM xf_dbtech_shop_lottery
		');
		foreach ($lotteries AS $lottery)
		{
			$drawnNumbers = json_decode($lottery['drawn_numbers'], true);

			foreach ($drawnNumbers AS $dateline => $numbers)
			{
				$db->insert('xf_dbtech_shop_lottery_history', [
					'lottery_id' => $lottery['lottery_id'],
					'drawn_numbers' => json_encode($numbers),
					'draw_date' => $dateline,
					'tickets_sold' => 0,
				]);
			}
		}

		$db->commit();
	}

	/**
	 *
	 */
	public function upgrade906010053Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function (Alter $table)
		{
			if ($table->getColumnDefinition('dbtech_shop_points'))
			{
				$table->changeColumn('dbtech_shop_points', 'double')
					->unsigned(false)
					->setDefault(0)
				;
			}
		});
	}

	/**
	 * @param bool $applied
	 * @param int|null $previousVersion
	 *
	 * @return bool
	 */
	protected function applyPermissionsUpgrade906019970(bool &$applied, ?int $previousVersion = null): bool
	{
		if (!$previousVersion || $previousVersion < 906010011)
		{
			// Regular perms
			$this->applyGlobalPermission('dbtech_shop', 'view', 'general', 'viewNode');
			$this->applyGlobalPermission('dbtech_shop', 'purchase', 'general', 'postThread');
			$this->applyGlobalPermission('dbtech_shop', 'react', 'forum', 'react');
			$this->applyGlobalPermission('dbtech_shop', 'rate', 'forum', 'react');
			$this->applyGlobalPermission('dbtech_shop', 'add', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('dbtech_shop', 'updateOwn', 'forum', 'editOwnPost');
			$this->applyGlobalPermission('dbtech_shop', 'tagOwnItem', 'forum', 'tagOwnThread');
			$this->applyGlobalPermission('dbtech_shop', 'tagAnyItem', 'forum', 'tagAnyThread');
			$this->applyGlobalPermission('dbtech_shop', 'manageOthersTagsOwnItem', 'forum', 'manageOthersTagsOwnThread');
			$this->applyGlobalPermission('dbtech_shop', 'deleteOwn', 'forum', 'deleteOwnPost');

			// Moderator perms
			$this->applyGlobalPermission('dbtech_shop', 'inlineMod', 'forum', 'inlineMod');
			$this->applyGlobalPermission('dbtech_shop', 'viewDeleted', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('dbtech_shop', 'deleteAny', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('dbtech_shop', 'undelete', 'forum', 'undelete');
			$this->applyGlobalPermission('dbtech_shop', 'hardDeleteAny', 'forum', 'hardDeleteAnyPost');
			$this->applyGlobalPermission('dbtech_shop', 'viewDeletedReviews', 'forum', 'viewDeleted');
			$this->applyGlobalPermission('dbtech_shop', 'deleteAnyReview', 'forum', 'deleteAnyPost');
			$this->applyGlobalPermission('dbtech_shop', 'editAny', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('dbtech_shop', 'updateAny', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('dbtech_shop', 'reassign', 'forum', 'editAnyPost');
			$this->applyGlobalPermission('dbtech_shop', 'manageAnyTag', 'forum', 'manageAnyTag');
			$this->applyGlobalPermission('dbtech_shop', 'viewModerated', 'forum', 'viewModerated');
			$this->applyGlobalPermission('dbtech_shop', 'approveUnapprove', 'forum', 'approveUnapprove');
			$this->applyGlobalPermission('dbtech_shop', 'warn', 'forum', 'warn');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 906010015)
		{
			// Regular perms
			$this->applyGlobalPermission('dbtechShopTradePost', 'view', 'profilePost', 'view');
			$this->applyGlobalPermission('dbtechShopTradePost', 'react', 'profilePost', 'react');
			$this->applyGlobalPermission('dbtechShopTradePost', 'manageOwn', 'profilePost', 'manageOwn');
			$this->applyGlobalPermission('dbtechShopTradePost', 'post', 'profilePost', 'post');
			$this->applyGlobalPermission('dbtechShopTradePost', 'comment', 'profilePost', 'comment');
			$this->applyGlobalPermission('dbtechShopTradePost', 'deleteOwn', 'profilePost', 'deleteOwn');
			$this->applyGlobalPermission('dbtechShopTradePost', 'editOwn', 'profilePost', 'editOwn');

			// Moderator perms
			$this->applyGlobalPermission('dbtechShopTradePost', 'inlineMod', 'profilePost', 'inlineMod');
			$this->applyGlobalPermission('dbtechShopTradePost', 'editAny', 'profilePost', 'editAny');
			$this->applyGlobalPermission('dbtechShopTradePost', 'deleteAny', 'profilePost', 'deleteAny');
			$this->applyGlobalPermission('dbtechShopTradePost', 'hardDeleteAny', 'profilePost', 'hardDeleteAny');
			$this->applyGlobalPermission('dbtechShopTradePost', 'warn', 'profilePost', 'warn');
			$this->applyGlobalPermission('dbtechShopTradePost', 'viewDeleted', 'profilePost', 'viewDeleted');
			$this->applyGlobalPermission('dbtechShopTradePost', 'viewModerated', 'profilePost', 'viewModerated');
			$this->applyGlobalPermission('dbtechShopTradePost', 'undelete', 'profilePost', 'undelete');
			$this->applyGlobalPermission('dbtechShopTradePost', 'approveUnapprove', 'profilePost', 'approveUnapprove');

			$applied = true;
		}

		return $applied;
	}

	/**
	 * @param $previousVersion
	 * @param array $stateChanges
	 */
	protected function postUpgrade906010011($previousVersion, array &$stateChanges): void
	{
		$service = \XF::service(RebuildNestedSetService::class, 'DBTech\Shop:Category', [
			'parentField' => 'parent_category_id',
		]);
		$service->rebuildNestedSetInfo();

		\XF::app()->jobManager()->enqueueUnique(
			'dbtechShopCategoryRebuild',
			'DBTech\Shop:Category',
			[],
			false
		);
	}
}