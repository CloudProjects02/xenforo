<?php

namespace DBTech\Credits\Install;

use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Exception as DbException;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Column;
use XF\Db\SchemaManager;
use XF\Repository\OptionRepository;

/**
 * @property AddOn addOn
 * @property App app
 *
 * @method AbstractAdapter db()
 * @method SchemaManager schemaManager()
 * @method Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait Upgrade905999970Trait
{
	/**
	 *
	 */
	public function upgrade905000031Step1(): void
	{
		$this->insertNamedWidget('dbtech_credits_wallet');
		$this->insertNamedWidget('dbtech_credits_richest');

		// Purge the cache of both possible copies of this
		\XF::registry()->delete([
			'dbt_credits_currency',
			'dbt_credits_event',
			'dbt_credits_eventtrigger',
			'dbt_credits_field',

			'dbtech_credits_currency',
			'dbtech_credits_event',
			'dbtech_credits_eventtrigger',
			'dbtech_credits_field',
		]);
	}

	/**
	 *
	 */
	public function upgrade905000032Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_eventtrigger', function (Alter $table)
		{
			$table->changeColumn('eventtriggerid', 'varbinary');
		});

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->addColumn('postbit', 'tinyint')->setDefault(1);
		});

		$sm->alterTable('xf_dbtech_credits_event', function (Alter $table)
		{
			$table->addColumn('title', 'varchar', 255)->setDefault('')->after('eventid');
			$table->addColumn('display', 'tinyint', 3)->setDefault(1)->after('alert');
		});

		// Purge the cache of both possible copies of this
		\XF::registry()->delete([
			'dbt_credits_event',
			'dbt_credits_eventtrigger',
		]);
	}

	/**
	 *
	 * @throws DbException
	 */
	public function upgrade905000033Step1(): void
	{
		$this->query("
			REPLACE INTO `xf_purchasable`
				(`purchasable_type_id`, `purchasable_class`, `addon_id`)
			VALUES
				('dbtech_credits_currency', 'DBTech\\\\Credits\\\\XF:Currency', X'4442546563682F43726564697473')
		");
	}

	/**
	 *
	 * @throws DbException
	 */
	public function upgrade905000039Step1(): void
	{
		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = REPLACE(`callback_class`, 'Event_XenGallery', 'Event_SonnbGallery_')
				WHERE `eventtriggerid` LIKE 'xengallery%'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = REPLACE(`callback_class`, 'Event_XenMedio', 'Event_JaxelMedio')
				WHERE `eventtriggerid` LIKE 'xenmedio%'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = REPLACE(`callback_class`, 'Event_PostRate', 'Event_PostRating_Rate')
				WHERE `eventtriggerid` LIKE 'postrate%'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Download'
				WHERE `eventtriggerid` = 'resourcedownload'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Downloaded'
				WHERE `eventtriggerid` = 'resourcedownloaded'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Rate'
				WHERE `eventtriggerid` = 'resourcerate'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Rated'
				WHERE `eventtriggerid` = 'resourcerated'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Update'
				WHERE `eventtriggerid` = 'resourceupdate'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Resource_Upload'
				WHERE `eventtriggerid` = 'resourceupload'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Comment'
				WHERE `eventtriggerid` = 'gallerycomment'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Commented'
				WHERE `eventtriggerid` = 'gallerycommented'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Download'
				WHERE `eventtriggerid` = 'gallerydownload'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Downloaded'
				WHERE `eventtriggerid` = 'gallerydownloaded'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Rate'
				WHERE `eventtriggerid` = 'galleryrate'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Rated'
				WHERE `eventtriggerid` = 'galleryrated'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = 'DBTech_Credits_Model_Event_Gallery_Upload'
				WHERE `eventtriggerid` = 'galleryupload'
		");

		$this->query("
			UPDATE `xf_dbtech_credits_eventtrigger`
				SET `callback_class` = REPLACE(`callback_class`, '_', '\\\')
				WHERE `callback_class` LIKE 'DBTech_Credits_%'
		");

		// Purge the cache
		\XF::registry()->delete([
			'dbt_credits_eventtrigger',
		]);
	}

	/**
	 *
	 * @throws DbException
	 */
	public function upgrade905000370Step1(): void
	{
		foreach ([
			'punish',
			'warning',
		] AS $eventTriggerId)
		{
			$this->query("
				UPDATE `xf_dbtech_credits_eventtrigger`
					SET `cancel` = 0
					WHERE `eventtriggerid` = '$eventTriggerId'
			");
		}

		// Purge the cache of both possible copies of this
		\XF::registry()->delete([
			'dbt_credits_eventtrigger',
		]);
	}

	/**
	 *
	 */
	public function upgrade905010031Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->renameTable('xf_dbtech_credits_eventtrigger', 'xf_dbtech_credits_event_trigger');
	}

	/**
	 *
	 */
	public function upgrade905010031Step2(): void
	{
		$sm = $this->schemaManager();

		$sm->dropTable('xf_dbtech_credits_field');
		$sm->dropTable('xf_dbtech_credits_purchase_log');
		$sm->dropTable('xf_dbtech_credits_transaction_pending');

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->addColumn('content_type', 'varbinary', 25)->after('referenceid');
			$table->addColumn('content_id', 'int')->after('content_type');
		});

		$sm->alterTable('xf_dbtech_credits_purchase_transaction', function (Alter $table)
		{
			$table->addColumn('ip_id', 'int', 10)->setDefault(0)->after('touserid');
			$table->dropColumns(['ipaddress']);
		});
	}

	/**
	 *
	 */
	public function upgrade905010031Step3(): void
	{
		$sm = $this->schemaManager();

		$columns = $sm->getTableColumnDefinitions('xf_user');

		if (array_key_exists('dbtech_credits_credits', $columns))
		{
			$sm->alterTable('xf_user', function (Alter $table)
			{
				// Column was changed but not blacklisted so rename the column
				$table->changeColumn('dbtech_credits_credits', 'double')->unsigned(false)->setDefault('0');
			});
		}
	}

	/**
	 *
	 */
	public function upgrade905010031Step4(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_charge', function (Alter $table)
		{
			$table->renameColumn('postid', 'post_id');
			$table->renameColumn('contenthash', 'content_hash');
		});

		$sm->alterTable('xf_dbtech_credits_charge_purchase', function (Alter $table)
		{
			$table->renameColumn('postid', 'post_id');
			$table->renameColumn('contenthash', 'content_hash');
			$table->renameColumn('userid', 'user_id');
		});

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->renameColumn('currencyid', 'currency_id');
			$table->renameColumn('displayorder', 'display_order');
			$table->renameColumn('useprefix', 'use_table_prefix');
			$table->renameColumn('userid', 'use_user_id');
			$table->renameColumn('usercol', 'user_id_column');
			$table->renameColumn('displaycurrency', 'is_display_currency');
		});
	}

	/**
	 *
	 */
	public function upgrade905010031Step5(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_event', function (Alter $table)
		{
			$table->renameColumn('eventid', 'event_id');
			$table->renameColumn('currencyid', 'currency_id');
			$table->renameColumn('eventtriggerid', 'event_trigger_id');
			$table->renameColumn('usergroups', 'user_group_ids');
			$table->renameColumn('forums', 'node_ids');
		});

		$sm->alterTable('xf_dbtech_credits_event_trigger', function (Alter $table)
		{
			$table->renameColumn('eventtriggerid', 'event_trigger_id');
		});

		$sm->alterTable('xf_dbtech_credits_purchase_transaction', function (Alter $table)
		{
			$table->renameColumn('eventid', 'event_id');
			$table->renameColumn('fromuserid', 'from_user_id');
			$table->renameColumn('touserid', 'to_user_id');
			$table->renameColumn('currencyid', 'currency_id');
		});
	}

	/**
	 *
	 */
	public function upgrade905010031Step6(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->renameColumn('transactionid', 'transaction_id');
			$table->renameColumn('eventid', 'event_id');
			$table->renameColumn('eventtriggerid', 'event_trigger_id');
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('sourceuserid', 'source_user_id');
			$table->renameColumn('referenceid', 'reference_id');
			$table->renameColumn('forumid', 'node_id');
			$table->renameColumn('ownerid', 'owner_id');
			$table->renameColumn('currencyid', 'currency_id');
			$table->renameColumn('isdisputed', 'is_disputed');
		});
	}

	/**
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 */
	public function upgrade905010031Step7(): void
	{
		$this->query("
			REPLACE INTO `xf_purchasable`
				(`purchasable_type_id`, `purchasable_class`, `addon_id`)
			VALUES
				('dbtech_credits_currency', 'DBTech\\\\Credits:Currency', X'4442546563682F43726564697473')
		");

		$this->query("
			UPDATE `xf_dbtech_credits_event`
			SET `user_group_ids` = '[-1]'
			WHERE `user_group_ids` = '[]'
				OR `user_group_ids` = ''
		");

		$this->query("
			UPDATE `xf_dbtech_credits_event`
			SET `node_ids` = '[-1]'
			WHERE `node_ids` = '[]'
				OR `node_ids` = ''
		");
	}

	/**
	 * @param $previousVersion
	 * @param array $stateChanges
	 */
	protected function postUpgrade905010031($previousVersion, array &$stateChanges): void
	{
		if ($previousVersion && $previousVersion < 905010031)
		{
			$options = $this->db()->fetchPairs("
				SELECT option_id, option_value
				FROM xf_option
				WHERE option_id LIKE 'dbtech_credits_eventtrigger_%'
			");
			if (!count($options))
			{
				$stateChanges['redirect'] = \XF::app()->router('admin')
					->buildLink('dbtech-credits/upgrade-error')
				;
			}
			else
			{
				$newSettings = [];

				$eventTriggers = $this->db()->fetchPairs("
					SELECT event_trigger_id, settings
					FROM xf_dbtech_credits_event_trigger
					WHERE event_trigger_id IN(
						'content', 'donate', 'interest', 'message',
						'paycheck', 'purchase', 'revival', 'taxation'
					)
				");
				foreach ($eventTriggers AS $eventTriggerId => $settings)
				{
					$settings = json_decode($settings, true);
					if (empty($settings))
					{
						continue;
					}

					foreach ($settings AS $key => $val)
					{
						if (!str_contains($eventTriggerId, $key))
						{
							// Work around an issue where every setting was saved for every event trigger
							// even if it didn't apply
							continue;
						}

						if (array_key_exists('dbtech_credits_eventtrigger_' . $key, $options))
						{
							$newSettings['dbtech_credits_eventtrigger_' . $key] = $val;
						}
					}
				}

				if (count($newSettings))
				{
					$optionRepo = \XF::repository(OptionRepository::class);
					$optionRepo->updateOptions($newSettings);
				}
			}
		}
	}

	/**
	 *
	 */
	public function upgrade905010033Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->addColumn('member_dropdown', 'tinyint')->setDefault(0);
		});

		\XF::registry()->delete([
			'dbtCreditsCurrencies',
		]);
	}

	/**
	 *
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 */
	public function upgrade905030031Step1(): void
	{
		$db = $this->db();

		$db->beginTransaction();

		$this->renamePermission('dbtech_credits', 'canview', 'dbtechCredits', 'view');
		$this->renamePermission('dbtech_credits', 'triggerEvents', 'dbtechCredits', 'triggerEvents');
		$this->renamePermission('dbtech_credits', 'charge', 'dbtechCredits', 'charge');

		$this->renamePermission('dbtech_credits', 'adjust', 'dbtechCredits', 'adjust');
		$this->renamePermission('dbtech_credits', 'viewlog', 'dbtechCredits', 'viewAnyLog');
		$this->renamePermission('dbtech_credits', 'special', 'dbtechCredits', 'bypassCurrencyPrivacy');
		$this->renamePermission('dbtech_credits', 'bypassChargeTag', 'dbtechCredits', 'bypassChargeTag');
	}

	/**
	 *
	 */
	public function upgrade905030031Step2(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_currency', function (Alter $table)
		{
			$table->addKey('active');
		});

		$sm->alterTable('xf_dbtech_credits_event', function (Alter $table)
		{
			$table->addKey(['active', 'display'], 'transaction_display');
		});

		$sm->alterTable('xf_dbtech_credits_purchase_transaction', function (Alter $table)
		{
			$table->renameColumn('to_user_id', 'user_id');
			$table->addColumn('transaction_date', 'int', 10)->setDefault(0)->after('user_id');
			$table->addKey(['transaction_date', 'user_id'], 'transaction_date');
		});

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->addKey(['dateline', 'transaction_id'], 'transaction_date');
		});
	}

	/**
	 *
	 */
	public function upgrade905030031Step3(): void
	{
		$sm = $this->schemaManager();

		$tables = $this->getTables();

		$key = 'xf_dbtech_credits_adjust_log';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_credits_donation_log';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_credits_redeem_log';
		$sm->createTable($key, $tables[$key]);

		$key = 'xf_dbtech_credits_transfer_log';
		$sm->createTable($key, $tables[$key]);
	}

	/**
	 *
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 */
	public function upgrade905030031Step4(): void
	{
		$this->executeUpgradeQuery("
			INSERT INTO xf_dbtech_credits_adjust_log
				(user_id, adjust_date, adjust_user_id, event_id, currency_id, amount, message)
			SELECT user_id, dateline, source_user_id, event_id, currency_id, amount, message
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'adjust'
				AND status = 1
		");

		$this->executeUpgradeQuery("
			INSERT INTO xf_dbtech_credits_donation_log
				(user_id, donation_date, donation_user_id, event_id, currency_id, amount, message)
			SELECT user_id, dateline, source_user_id, event_id, currency_id, amount, message
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'donate'
				AND status = 1
		");

		$this->executeUpgradeQuery("
			INSERT INTO xf_dbtech_credits_purchase_transaction
				(user_id, transaction_date, from_user_id, event_id, currency_id, amount, message)
			SELECT user_id, dateline, source_user_id, event_id, currency_id, amount, message
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'purchase'
				AND status = 1
		");

		$this->executeUpgradeQuery("
			INSERT INTO xf_dbtech_credits_redeem_log
				(user_id, redeem_date, redeem_code, event_id, currency_id, amount, message)
			SELECT user_id, dateline, reference_id, event_id, currency_id, amount, message
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'redeem'
				AND status = 1
		");

		$this->executeUpgradeQuery("
			INSERT INTO xf_dbtech_credits_transfer_log
				(user_id, transfer_date, event_id, currency_id, amount, message)
			SELECT user_id, dateline, event_id, currency_id, amount, message
			FROM xf_dbtech_credits_transaction
			WHERE event_trigger_id = 'transfer'
				AND status = 1
		");
	}

	/**
	 *
	 */
	public function upgrade905030032Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_purchase_transaction', function (Alter $table)
		{
			$table->renameColumn('to_user_id', 'user_id');
		});
	}

	/**
	 *
	 * @throws DbException
	 * @throws DbException
	 */
	public function upgrade905030033Step1(): void
	{
		$defaultValue = [
			'enabled' => 1,
			'right_position' => false,
			'right_text' => true,
		];

		$this->query("
			UPDATE xf_option
			SET default_value = ?
			WHERE option_id = 'dbtech_credits_navbar'
		", json_encode($defaultValue));

		$navbarDefaults = json_decode($this->db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'dbtech_credits_navbar'
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
				WHERE option_id = 'dbtech_credits_navbar'
			", json_encode($navbarDefaults));
		}
	}

	/**
	 *
	 */
	public function upgrade905030035Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->addColumn('transaction_state', 'enum')
				->values(['visible', 'moderated', 'skipped', 'skipped_maximum'])
				->setDefault('visible')
				->after('amount')
			;
		});
	}

	/**
	 *
	 * @throws DbException
	 */
	public function upgrade905030035Step2(): void
	{
		$this->executeUpgradeQuery("
			UPDATE `xf_dbtech_credits_transaction`
			SET `transaction_state` = CASE `status`
				WHEN 1 THEN 'visible'
				WHEN 2 THEN 'moderated'
				WHEN 3 THEN 'skipped'
				WHEN 4 THEN 'skipped_maximum'
				ELSE 'visible' END
		");
	}

	/**
	 *
	 */
	public function upgrade905030035Step3(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_transaction', function (Alter $table)
		{
			$table->dropIndexes([
				'dateline',
				'user_id_stats',
			]);
			$table->addKey(['dateline', 'user_id', 'transaction_state'], 'dateline');
			$table->addKey(['user_id', 'event_id', 'transaction_state', 'negate', 'dateline'], 'user_id_stats');

			$table->dropColumns([
				'status',
			]);
		});
	}

	/**
	 *
	 * @throws DbException
	 */
	public function upgrade905030035Step4(): void
	{
		$this->executeUpgradeQuery("
			INSERT INTO xf_approval_queue
				(content_type, content_id, content_date)
			SELECT 'dbtech_credits_txn', transaction_id, UNIX_TIMESTAMP()
			FROM xf_dbtech_credits_transaction
			WHERE transaction_state = 'moderated'
		");
	}

	/**
	 *
	 * @throws DbException
	 * @throws DbException
	 */
	public function upgrade905030035Step5(): void
	{
		$this->executeUpgradeQuery("
			UPDATE `xf_user_alert`
			SET `content_type` = 'dbtech_credits_txn'
			WHERE `content_type` = 'dbtech_credits'
		");

		$this->executeUpgradeQuery("
			UPDATE xf_user_alert_optout
			SET alert = REPLACE(`alert`, 'dbtech_credits_', 'dbtech_credits_txn_')
		");
	}

	/**
	 *
	 */
	public function upgrade905030370Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_charge', function (Alter $table)
		{
			$table->addColumn('content_type', 'varbinary', 25)->after('post_id');
			$table->addColumn('content_id', 'int')->after('content_type');
		});

		$sm->alterTable('xf_dbtech_credits_charge_purchase', function (Alter $table)
		{
			$table->addColumn('content_type', 'varbinary', 25)->after('post_id');
			$table->addColumn('content_id', 'int')->after('content_type');
		});
	}

	/**
	 *
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 * @throws DbException
	 */
	public function upgrade905030370Step2(): void
	{
		$this->executeUpgradeQuery("
			UPDATE `xf_dbtech_credits_charge`
			SET `content_type` = 'post'
		");

		$this->executeUpgradeQuery("
			UPDATE `xf_dbtech_credits_charge`
			SET `content_id` = `post_id`
		");

		$this->executeUpgradeQuery("
			UPDATE `xf_dbtech_credits_charge_purchase`
			SET `content_type` = 'post'
		");

		$this->executeUpgradeQuery("
			UPDATE `xf_dbtech_credits_charge_purchase`
			SET `content_id` = `post_id`
		");
	}

	/**
	 *
	 */
	public function upgrade905030370Step3(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_credits_charge', function (Alter $table)
		{
			$table->dropPrimaryKey();
			$table->dropColumns(['post_id']);
			$table->addPrimaryKey(['content_type', 'content_id', 'content_hash']);
		});

		$sm->alterTable('xf_dbtech_credits_charge_purchase', function (Alter $table)
		{
			$table->dropPrimaryKey();
			$table->dropColumns(['post_id']);
			$table->addPrimaryKey(['content_type', 'content_id', 'content_hash', 'user_id']);
		});
	}

	/**
	 *
	 * @throws DbException
	 */
	public function upgrade905050031Step1(): void
	{
		$this->query("
			REPLACE INTO `xf_payment_provider`
				(`provider_id`, `provider_class`, `addon_id`)
			VALUES
				('dbtech_credits', 'DBTech\\\\Credits:Credits', X'4442546563682F43726564697473')
		");
	}

	/**
	 *
	 */
	public function upgrade905060032Step1(): void
	{
		$this->applyTables();
	}

	/**
	 *
	 */
	public function upgrade905060033Step1(): void
	{
		$sm = $this->schemaManager();
		$db = $this->db();

		$currencies = $db->fetchAll('
			SELECT `column`
			FROM xf_dbtech_credits_currency
		');

		$sm->alterTable('xf_user', function (Alter $table) use ($currencies)
		{
			foreach ($currencies AS $currency)
			{
				if ($table->getColumnDefinition($currency['column']))
				{
					$table->changeColumn($currency['column'], 'decimal')
						->length('65,8')
						->unsigned(false)
						->setDefault(0)
					;
				}
			}
		});
	}

	/**
	 *
	 */
	public function upgrade905060033Step2(): void
	{
		$this->applyTables();
	}

	/**
	 *
	 */
	public function upgrade905060034Step1(): void
	{
		$this->db()->delete('xf_user_alert', 'content_type = ? AND action = ?', [
			'dbtech_credits_txn',
			'payment',
		]);
	}

	/**
	 *
	 */
	public function upgrade905080070Step1(): void
	{
		$this->applyTables();
	}

	/**
	 *
	 */
	public function upgrade905090031Step1(): void
	{
		$this->applyTables();
	}

	/**
	 * @return void
	 */
	public function upgrade905090031Step2(): void
	{
		$this->installStep2();
	}

	/**
	 * @param bool $applied
	 * @param int|null $previousVersion
	 *
	 * @return bool
	 */
	protected function applyPermissionsUpgrade905999970(bool &$applied, ?int $previousVersion = null): bool
	{
		if (!$previousVersion || $previousVersion < 905030031)
		{
			$this->applyGlobalPermission('dbtechCredits', 'viewModerated', 'forum', 'viewModerated');

			$applied = true;
		}

		if ($previousVersion && $previousVersion < 905030035)
		{
			$this->applyGlobalPermission('dbtechCredits', 'approveUnapprove', 'dbtechCredits', 'viewModerated');

			$applied = true;
		}
		else if (!$previousVersion)
		{
			$this->applyGlobalPermission('dbtechCredits', 'approveUnapprove', 'forum', 'viewModerated');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 905010031)
		{
			$this->applyGlobalPermission('dbtechCredits', 'bypassChargeTag', 'general', 'bypassUserPrivacy');

			$applied = true;
		}

		if (!$previousVersion || $previousVersion < 905010037)
		{
			$this->applyGlobalPermission('dbtechCredits', 'triggerEvents', 'general', 'viewNode');

			$applied = true;
		}

		return $applied;
	}
}