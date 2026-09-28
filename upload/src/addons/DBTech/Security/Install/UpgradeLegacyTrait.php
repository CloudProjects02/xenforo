<?php

namespace DBTech\Security\Install;

use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Exception;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Column;
use XF\Db\Schema\Create;
use XF\Db\SchemaManager;

/**
 * @property AddOn addOn
 * @property App app
 *
 * @method AbstractAdapter db()
 * @method SchemaManager schemaManager()
 * @method Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait UpgradeLegacyTrait
{
	/**
	 *
	 */
	public function upgrade20160531Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_dbtech_security_tornode', function (Create $table)
		{
			$table->addColumn('ipaddress', 'varchar', 45)->setDefault('');
			$table->addPrimaryKey('ipaddress');
		});
	}

	/**
	 *
	 */
	public function upgrade20160601Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user_tfa_trusted', function (Alter $table)
		{
			$table->addColumn('user_agent', 'varchar', 255)->setDefault('');
		});
	}

	/**
	 *
	 */
	public function upgrade20160602Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_dbtech_security_session', function (Create $table)
		{
			$table->addColumn('session_id', 'varbinary', 32);
			$table->addColumn('user_id', 'int')->setDefault(0);
			$table->addColumn('start_date', 'int')->setDefault(0);
			$table->addColumn('last_activity_date', 'int')->setDefault(0);
			$table->addColumn('user_agent', 'varchar', 255)->setDefault('');
			$table->addPrimaryKey('session_id');
			$table->addKey('start_date');
			$table->addKey('last_activity_date');
		});
	}

	/**
	 *
	 */
	public function upgrade20160611Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_dbtech_security_account_lock', function (Create $table)
		{
			$table->addColumn('user_id', 'int');
			$table->addColumn('is_staff', 'tinyint', 1)->setDefault(0);
			$table->addColumn('action', 'varchar', 50)->setDefault('');
			$table->addColumn('hash', 'char', '32')->setDefault('');
			$table->addPrimaryKey('user_id');
			$table->addKey('hash');
		});
	}

	/**
	 *
	 */
	public function upgrade20160611Step2(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user_option', function (Alter $table)
		{
			$table->addColumn('dbtech_security_is_user_locked', 'tinyint', 1)->setDefault(0);
			$table->addColumn('dbtech_security_is_admin_locked', 'tinyint', 1)->setDefault(0);
		});
	}

	/**
	 *
	 */
	public function upgrade20160614Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_ip_match', function (Alter $table)
		{
			$table->addColumn('dbtech_security_comment', 'char', 2)->setDefault('');
		});
	}

	/**
	 *
	 */
	public function upgrade20160616Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_dbtech_security_fingerprintlog', function (Create $table)
		{
			$table->addColumn('fingerprint', 'char', 32);
			$table->addColumn('userid', 'int');
			$table->addColumn('dateline', 'int')->setDefault(0);
			$table->addColumn('ipaddress', 'varchar', 45)->setDefault('');
			$table->addColumn('components', 'mediumblob')->nullable(true);
			$table->addPrimaryKey(['fingerprint', 'userid']);
		});
	}

	/**
	 *
	 */
	public function upgrade20160621Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user', function (Alter $table)
		{
			$table->addColumn('dbtech_security_lastbreach', 'int')->setDefault(0);
			$table->addColumn('dbtech_security_breached', 'tinyint', 1)->setDefault(0);
		});
	}

	/**
	 *
	 */
	public function upgrade20160630Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_dbtech_security_criteria', function (Create $table)
		{
			$table->addColumn('criteriaid', 'int')->autoIncrement();
			$table->addColumn('title', 'varchar', 255)->setDefault('');
			$table->addColumn('active', 'tinyint', 1)->setDefault(1);
			$table->addColumn('weight', 'int')->unsigned(false)->setDefault('10');
			$table->addColumn('fallback', 'enum')->values(['skip','fail'])->setDefault('skip');
			$table->addColumn('day_limit', 'tinyint', 3)->setDefault(30);
			$table->addColumn('amount_limit', 'tinyint', 3)->setDefault(5);
		});

		$sm->createTable('xf_dbtech_security_recoverylog', function (Create $table)
		{
			$table->addColumn('recoverylogid', 'int')->autoIncrement();
			$table->addColumn('userid', 'int')->setDefault(0);
			$table->addColumn('ipaddress', 'varchar', 45)->setDefault('');
			$table->addColumn('dateline', 'int')->setDefault(0);
			$table->addColumn('username', 'varchar', 100)->setDefault('');
			$table->addColumn('existingemail', 'varchar', 120)->setDefault('');
			$table->addColumn('newemail', 'varchar', 120)->setDefault('');
			$table->addColumn('pmuser', 'varchar', 100)->setDefault('');
			$table->addColumn('score', 'int')->unsigned(false)->setDefault('0');
			$table->addColumn('breakdown', 'mediumblob')->nullable(true);
			$table->addKey('userid');
		});
	}

	/**
	 *
	 * @throws Exception
	 */
	public function upgrade20160630Step2(): void
	{
		foreach ([
			[
				'criteriaid' => 1,
				'title' => 'Incorrect user name',
				'weight' => -10,
				'fallback' => 'skip',
				'day_limit' => 0,
				'amount_limit' => 0,
			],
			[
				'criteriaid' => 2,
				'title' => 'Incorrect old email address (missing or mismatch to user name)',
				'weight' => -20,
				'fallback' => 'skip',
				'day_limit' => 0,
				'amount_limit' => 0,
			],
			[
				'criteriaid' => 3,
				'title' => 'Same IP range as registration IP',
				'weight' => 20,
				'fallback' => 'skip',
				'day_limit' => 0,
				'amount_limit' => 0,
			],
			[
				'criteriaid' => 4,
				'title' => 'Able to name last person private messaged',
				'weight' => 10,
				'fallback' => 'skip',
				'day_limit' => 0,
				'amount_limit' => 0,
			],
			[
				'criteriaid' => 5,
				'title' => 'New email and old email having the same before @',
				'weight' => 10,
				'fallback' => 'skip',
				'day_limit' => 0,
				'amount_limit' => 0,
			],
			[
				'criteriaid' => 6,
				'title' => 'Username or email shows up in database of breached accounts',
				'weight' => -10,
				'fallback' => 'skip',
				'day_limit' => 0,
				'amount_limit' => 0,
			],
			[
				'criteriaid' => 7,
				'title' => 'Someone has attempted to reset this email before',
				'weight' => -20,
				'fallback' => 'skip',
				'day_limit' => 0,
				'amount_limit' => 0,
			],
			[
				'criteriaid' => 8,
				'title' => 'Same IP logged into another account in the last {day_limit} days',
				'weight' => -10,
				'fallback' => 'fail',
				'day_limit' => 30,
				'amount_limit' => 0,
			],
			[
				'criteriaid' => 9,
				'title' => 'Same IP attempted to log into more than {amount_limit} accounts in the last {day_limit} days',
				'weight' => -10,
				'fallback' => 'skip',
				'day_limit' => 30,
				'amount_limit' => 5,
			],
			[
				'criteriaid' => 10,
				'title' => 'Same IP attempted to reset another email in the last {day_limit} days',
				'weight' => -10,
				'fallback' => 'skip',
				'day_limit' => 30,
				'amount_limit' => 0,
			],
		] AS $data)
		{
			$this->query("
				INSERT IGNORE INTO `xf_dbtech_security_criteria`
					(`criteriaid`, `title`, `weight`, `fallback`, `day_limit`, `amount_limit`)
				VALUES (
					'" . intval($data['criteriaid']) . "',
					" . $this->db()->quote($data['title']) . ",
					'" . intval($data['weight']) . "',
					" . $this->db()->quote($data['fallback']) . ",
					'" . intval($data['day_limit']) . "',
					'" . intval($data['amount_limit']) . "'
				)
			");
		}
	}

	/**
	 *
	 * @throws Exception
	 */
	public function upgrade20160712Step1(): void
	{
		foreach ([
			[
				'criteriaid' => 11,
				'title' => 'Produced a valid Paid Subscription transaction ID',
				'weight' => 99,
				'fallback' => 'skip',
				'day_limit' => 0,
				'amount_limit' => 0,
			],
			[
				'criteriaid' => 12,
				'title' => 'Same region as registration IP',
				'weight' => 10,
				'fallback' => 'skip',
				'day_limit' => 0,
				'amount_limit' => 0,
			],
		] AS $data)
		{
			$this->query("
				INSERT IGNORE INTO `xf_dbtech_security_criteria`
					(`criteriaid`, `title`, `weight`, `fallback`, `day_limit`, `amount_limit`)
				VALUES (
					'" . intval($data['criteriaid']) . "',
					" . $this->db()->quote($data['title']) . ",
					'" . intval($data['weight']) . "',
					" . $this->db()->quote($data['fallback']) . ",
					'" . intval($data['day_limit']) . "',
					'" . intval($data['amount_limit']) . "'
				)
			");
		}
	}

	/**
	 *
	 */
	public function upgrade20160717Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user_tfa_trusted', function (Alter $table)
		{
			$table->changeColumn('user_agent', 'blob')->nullable(true);
		});
	}

	/**
	 *
	 */
	public function upgrade20160721Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_security_criteria', function (Alter $table)
		{
			$table->addColumn('validate_input', 'tinyint', 1)->setDefault(1);
		});
	}

	/**
	 *
	 */
	public function upgrade20161207Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->createTable('xf_dbtech_security_badbehavior', function (Create $table)
		{
			$table->addColumn('id', 'int')->autoIncrement();
			$table->addColumn('ip', 'text');
			$table->addColumn('date', 'datetime')->setDefault('0000-00-00 00:00:00');
			$table->addColumn('request_method', 'text');
			$table->addColumn('request_uri', 'text');
			$table->addColumn('server_protocol', 'text');
			$table->addColumn('http_headers', 'text');
			$table->addColumn('user_agent', 'text');
			$table->addColumn('request_entity', 'text');
			$table->addColumn('key', 'text');
			$table->addKey([['ip', 15]], 'ip');
			$table->addKey([['user_agent', 10]], 'user_agent');
		});
	}
}