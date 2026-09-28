<?php

namespace DBTech\Security\Install;

use DBTech\Security\Repository\CountryRepository;
use DBTech\Security\Repository\TorRepository;
use GuzzleHttp\Exception\GuzzleException;
use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Column;
use XF\Db\Schema\Create;
use XF\Db\SchemaManager;
use XF\PrintableException;
use XF\Repository\IconRepository;

/**
 * @property AddOn addOn
 * @property App app
 *
 * @method AbstractAdapter db()
 * @method SchemaManager schemaManager()
 * @method Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait InstallDataTrait
{
	/**
	 * @return \Closure[]
	 */
	protected function getTables(): array
	{
		$tables = [];

		$tables['xf_dbtech_security_account_lock'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'is_staff', 'tinyint', 1)->setDefault(0);
			$this->addOrChangeColumn($table, 'action', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'hash', 'char', '32')->setDefault('');
			$table->addPrimaryKey('user_id');
			$table->addKey('hash');
		};

		$tables['xf_dbtech_security_admin_strike'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'admin_strike_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'ipaddress', 'varchar', 45)->setDefault('');
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'username', 'varchar', 50)->setDefault('');
			$table->addKey('user_id');
		};

		$tables['xf_dbtech_security_bad_behavior'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'ip', 'text');
			$this->addOrChangeColumn($table, 'date', 'datetime')->setDefault('0000-00-00 00:00:00');
			$this->addOrChangeColumn($table, 'request_method', 'text');
			$this->addOrChangeColumn($table, 'request_uri', 'text');
			$this->addOrChangeColumn($table, 'server_protocol', 'text');
			$this->addOrChangeColumn($table, 'http_headers', 'text');
			$this->addOrChangeColumn($table, 'user_agent', 'text');
			$this->addOrChangeColumn($table, 'request_entity', 'text');
			$this->addOrChangeColumn($table, 'key', 'text');
			$table->addKey([['ip', 15]], 'ip');
			$table->addKey([['user_agent', 10]], 'user_agent');
		};

		$tables['xf_dbtech_security_change_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'change_log_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'ipaddress', 'varchar', 45)->setDefault('');
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'script', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'action', 'varchar', 20)->setDefault('');
			$this->addOrChangeColumn($table, 'id', 'int')->unsigned(false)->setDefault('0');
			$this->addOrChangeColumn($table, 'title', 'varchar', 200)->setDefault('');
			$this->addOrChangeColumn($table, 'field', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'change', 'mediumblob')->nullable(true);
			$table->addKey('user_id');
		};

		$tables['xf_dbtech_security_compromised_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'compromised_log_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'ipaddress', 'varchar', 45)->setDefault('');
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'attempted_usernames', 'mediumblob')->nullable(true);
		};

		$tables['xf_dbtech_security_country'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'country_code', 'char', 2);
			$this->addOrChangeColumn($table, 'name', 'varchar', 255);
			$this->addOrChangeColumn($table, 'native_name', 'varchar', 255);
			$this->addOrChangeColumn($table, 'iso_code', 'char', 3);
			$this->addOrChangeColumn($table, 'blocked', 'tinyint')->setDefault(0);
			$table->addPrimaryKey('country_code');
		};

		$tables['xf_dbtech_security_criteria'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'criteria_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'title', 'varchar', 255)->setDefault('');
			$this->addOrChangeColumn($table, 'active', 'tinyint', 1)->setDefault(1);
			$this->addOrChangeColumn($table, 'weight', 'int')->unsigned(false)->setDefault('10');
			$this->addOrChangeColumn($table, 'fallback', 'enum')->values(['skip','fail'])->setDefault('skip');
			$this->addOrChangeColumn($table, 'day_limit', 'tinyint', 3)->setDefault(30);
			$this->addOrChangeColumn($table, 'amount_limit', 'tinyint', 3)->setDefault(5);
			$this->addOrChangeColumn($table, 'validate_input', 'tinyint', 1)->setDefault(1);
		};

		$tables['xf_dbtech_security_fingerprint_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'fingerprint_log_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'fingerprint', 'char', 32);
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'ipaddress', 'varchar', 45)->setDefault('');
			$this->addOrChangeColumn($table, 'components', 'mediumblob')->nullable(true);
			$table->addUniqueKey(['fingerprint', 'user_id'], 'fingerprint_user_id');
		};

		$tables['xf_dbtech_security_ip_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'user_id', 'int');
			$this->addOrChangeColumn($table, 'ipaddress', 'varchar', 45)->setDefault('');
			$this->addOrChangeColumn($table, 'first_visit', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'last_visit', 'int')->setDefault(0);
			$table->addPrimaryKey(['user_id', 'ipaddress']);
		};

		$tables['xf_dbtech_security_ip_verify'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'ip_verify_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'ipaddress', 'varchar', 45)->setDefault('');
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$table->addKey('user_id');
		};

		$tables['xf_dbtech_security_login_strike'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'login_strike_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'ipaddress', 'varchar', 45)->setDefault('');
			$this->addOrChangeColumn($table, 'username', 'blob')->nullable(true);
			$this->addOrChangeColumn($table, 'valid_user', 'tinyint', 1)->setDefault(1);
			$table->addKey('dateline');
			$table->addKey('ipaddress');
		};

		$tables['xf_dbtech_security_recovery_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'recovery_log_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'ipaddress', 'varchar', 45)->setDefault('');
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'username', 'varchar', 100)->setDefault('');
			$this->addOrChangeColumn($table, 'existing_email', 'varchar', 120)->setDefault('');
			$this->addOrChangeColumn($table, 'new_email', 'varchar', 120)->setDefault('');
			$this->addOrChangeColumn($table, 'pm_user', 'varchar', 100)->setDefault('');
			$this->addOrChangeColumn($table, 'score', 'int')->unsigned(false)->setDefault('0');
			$this->addOrChangeColumn($table, 'breakdown', 'mediumblob')->nullable(true);
			$table->addKey('user_id');
		};

		$tables['xf_dbtech_security_session'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'session_id', 'varbinary', 32);
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'start_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'last_activity_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'user_agent', 'blob')->nullable(true);
			$table->addPrimaryKey('session_id');
			$table->addKey(['user_id', 'start_date']);
			$table->addKey('start_date');
			$table->addKey('last_activity_date');
		};

		$tables['xf_dbtech_security_snapshot'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'snapshot_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'dateline', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'data', 'mediumblob')->nullable(true);
		};

		$tables['xf_dbtech_security_tor_node'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'ipaddress', 'varchar', 45)->setDefault('');
			$table->addPrimaryKey('ipaddress');
		};

		$tables['xf_dbtech_security_watcher'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'watcher_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'watcher_type', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'active', 'tinyint')->setDefault(1);
			$this->addOrChangeColumn($table, 'priority', 'int')->setDefault(1);
			$this->addOrChangeColumn($table, 'rule_data', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'actions', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'extra_data', 'mediumblob')->nullable(true);
		};

		$tables['xf_dbtech_security_watcher_log'] = function ($table)
		{
			/** @var Create|Alter $table */
			$this->addOrChangeColumn($table, 'watcher_log_id', 'int')->autoIncrement();
			$this->addOrChangeColumn($table, 'log_date', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'user_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'ip_address', 'varchar', 45)->setDefault('');
			$this->addOrChangeColumn($table, 'watcher_id', 'int')->setDefault(0);
			$this->addOrChangeColumn($table, 'action', 'varchar', 50)->setDefault('');
			$this->addOrChangeColumn($table, 'action_params', 'mediumblob')->nullable(true);
			$this->addOrChangeColumn($table, 'message', 'mediumblob')->nullable(true);
		};

		return $tables;
	}

	/**
	 * @return array
	 */
	protected function getAlterDefinitions(): array
	{
		$definitions = [];

		$definitions['xf_ip_match'] = [
			'columns' => [
				'dbtech_security_comment' => [
					'type'    => 'varchar',
					'length'  => 15,
					'default' => '',
				],
			],
		];

		$definitions['xf_user'] = [
			'columns' => [
				'dbtech_security_forcenewpass' => [
					'type'    => 'tinyint',
					'length'  => null,
					'default' => 0,
				],
				'dbtech_security_lastbreach' => [
					'type'    => 'int',
					'length'  => null,
					'default' => 0,
				],
				'dbtech_security_breached' => [
					'type'    => 'tinyint',
					'length'  => null,
					'default' => 0,
				],
			],
		];

		$definitions['xf_user_option'] = [
			'columns' => [
				'dbtech_security_is_user_locked' => [
					'type'    => 'tinyint',
					'length'  => null,
					'default' => 0,
				],
				'dbtech_security_is_admin_locked' => [
					'type'    => 'tinyint',
					'length'  => null,
					'default' => 0,
				],
			],
		];

		$definitions['xf_user_remember'] = [
			'columns' => [
				'dbtech_security_user_agent' => [
					'type'    => 'blob',
					'length'  => null,
					'nullable' => true,
				],
			],
		];

		$definitions['xf_user_tfa_trusted'] = [
			'columns' => [
				'dbtech_security_user_agent' => [
					'type'    => 'blob',
					'length'  => null,
					'nullable' => true,
				],
			],
		];

		return $definitions;
	}

	/**
	 *
	 */
	public function installStep4(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_ip_match', function (Alter $table)
		{
			$table->changeColumn('match_type')->addValues(['dbtech_security_tor', 'dbtech_security_country']);
		});
	}

	/**
	 * @return string[]
	 */
	protected function getInstallQueries(): array
	{
		return [
			"
				INSERT IGNORE INTO `xf_dbtech_security_criteria` (`criteria_id`, `title`, `active`, `weight`, `fallback`, `day_limit`, `amount_limit`, `validate_input`)
				VALUES
					(1, 'Incorrect user name', 1, -10, 'skip', 0, 0, 1),
					(2, 'Incorrect old email address (missing or mismatch to user name)', 1, -20, 'skip', 0, 0, 1),
					(3, 'Same IP range as registration IP', 1, 20, 'skip', 0, 0, 1),
					(4, 'Able to name last person private messaged', 1, 10, 'skip', 0, 0, 1),
					(5, 'New email and old email having the same before @', 1, 10, 'skip', 0, 0, 1),
					(6, 'Username or email shows up in database of breached accounts', 1, -10, 'skip', 0, 0, 1),
					(7, 'Someone has attempted to reset this email before', 1, -20, 'skip', 0, 0, 1),
					(8, 'Same IP logged into another account in the last {day_limit} days', 1, -10, 'fail', 30, 0, 1),
					(9, 'Same IP attempted to log into more than {amount_limit} accounts in the last {day_limit} days', 1, -10, 'skip', 30, 5, 1),
					(10, 'Same IP attempted to reset another email in the last {day_limit} days', 1, -10, 'skip', 30, 0, 1),
					(11, 'Produced a valid Paid Subscription transaction ID', 1, 99, 'skip', 0, 0, 1),
					(12, 'Same region as registration IP', 1, 10, 'skip', 0, 0, 1);
			",
		];
	}

	/**
	 * Returns true if permissions were modified, otherwise false.
	 *
	 * @return bool
	 */
	protected function applyPermissionsInstall(): bool
	{
		return false;
	}

	/**
	 * @return \Closure[]
	 */
	protected function getDefaultWidgetSetup(): array
	{
		return [];
	}

	/**
	 * @throws PrintableException
	 * @throws GuzzleException
	 */
	protected function runPostInstallActions(): void
	{
		$torRepo = \XF::repository(TorRepository::class);
		$torRepo->updateNodes();

		$countryRepo = \XF::repository(CountryRepository::class);
		$countryRepo->updateCountryList();

		$iconRepo = \XF::repository(IconRepository::class);
		$iconRepo->enqueueUsageAnalyzer('extra');
	}

	/**
	 * @return string[]
	 */
	protected function getAdminPermissions(): array
	{
		return [];
	}

	/**
	 * @return string[]
	 */
	protected function getPermissionGroups(): array
	{
		return [
			'dbtechSecurity',
			'dbtechSecurityAdmin',
		];
	}

	/**
	 * @return string[]
	 */
	protected function getContentTypes(): array
	{
		return [
			'dbtech_security',
		];
	}

	/**
	 * @return string[]
	 */
	protected function getRegistryEntries(): array
	{
		return [
			'dbtSecurityWatchers',
		];
	}
}