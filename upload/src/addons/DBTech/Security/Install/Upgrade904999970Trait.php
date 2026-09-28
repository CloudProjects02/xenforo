<?php

namespace DBTech\Security\Install;

use DBTech\Security\Entity\FingerprintLog;
use DBTech\Security\Entity\RecoveryLog;
use DBTech\Security\Entity\Snapshot;
use DBTech\Security\Repository\CountryRepository;
use DBTech\Security\Repository\TorRepository;
use GuzzleHttp\Exception\GuzzleException;
use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Exception;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Column;
use XF\Db\Schema\Create;
use XF\Db\SchemaManager;
use XF\PrintableException;
use XF\Util\Ip;
use XF\Util\Php;

/**
 * @property AddOn addOn
 * @property App app
 *
 * @method AbstractAdapter db()
 * @method SchemaManager schemaManager()
 * @method Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait Upgrade904999970Trait
{
	/**
	 *
	 */
	public function upgrade904000032Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_security_loginstrikes', function (Alter $table)
		{
			$table->changeColumn('username', 'blob')->nullable(true)->setDefault(null);
		});
	}

	/**
	 *
	 */
	public function upgrade904010031Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->dropTable('xf_dbtech_security_ipbanlog');
		$sm->dropTable('xf_dbtech_security_tornode');

		$sm->renameTable('xf_dbtech_security_adminstrikes', 'xf_dbtech_security_admin_strike');
		$sm->renameTable('xf_dbtech_security_badbehavior', 'xf_dbtech_security_bad_behavior');
		$sm->renameTable('xf_dbtech_security_changelog', 'xf_dbtech_security_change_log');
		$sm->renameTable('xf_dbtech_security_compromisedlog', 'xf_dbtech_security_compromised_log');
		$sm->renameTable('xf_dbtech_security_fingerprintlog', 'xf_dbtech_security_fingerprint_log');
		$sm->renameTable('xf_dbtech_security_iplog', 'xf_dbtech_security_ip_log');
		$sm->renameTable('xf_dbtech_security_ipverify', 'xf_dbtech_security_ip_verify');
		$sm->renameTable('xf_dbtech_security_loginstrikes', 'xf_dbtech_security_login_strike');
		$sm->renameTable('xf_dbtech_security_recoverylog', 'xf_dbtech_security_recovery_log');
		$sm->renameTable('xf_dbtech_security_watcherlog', 'xf_dbtech_security_watcher_log');

		$tables = $this->getTables();

		$key = 'xf_dbtech_security_country';
		$sm->createTable($key, $tables[$key]);
	}

	/**
	 *
	 * @throws Exception
	 */
	public function upgrade904010031Step2(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_security_fingerprint_log', function (Alter $table)
		{
			$table->dropPrimaryKey();
		});

		$this->query('
			ALTER TABLE xf_dbtech_security_fingerprint_log
			ADD fingerprint_log_id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY
				FIRST
		');
	}

	/**
	 *
	 */
	public function upgrade904010031Step3(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_security_admin_strike', function (Alter $table)
		{
			$table->renameColumn('adminstrikesid', 'admin_strike_id');
			$table->renameColumn('userid', 'user_id');
		});

		$sm->alterTable('xf_dbtech_security_change_log', function (Alter $table)
		{
			$table->renameColumn('changelogid', 'change_log_id');
			$table->renameColumn('userid', 'user_id');
		});

		$sm->alterTable('xf_dbtech_security_compromised_log', function (Alter $table)
		{
			$table->renameColumn('compromisedlogid', 'compromised_log_id');
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('attemptedusernames', 'attempted_usernames');
		});
	}

	/**
	 *
	 */
	public function upgrade904010031Step4(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_security_criteria', function (Alter $table)
		{
			$table->renameColumn('criteriaid', 'criteria_id');
		});

		$sm->alterTable('xf_dbtech_security_fingerprint_log', function (Alter $table)
		{
			$table->renameColumn('userid', 'user_id');
			$table->addUniqueKey(['fingerprint', 'user_id'], 'fingerprint_user_id');
		});
	}

	/**
	 *
	 */
	public function upgrade904010031Step5(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_security_ip_log', function (Alter $table)
		{
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('firstvisit', 'first_visit');
			$table->renameColumn('lastvisit', 'last_visit');
		});

		$sm->alterTable('xf_dbtech_security_ip_verify', function (Alter $table)
		{
			$table->renameColumn('ipverifyid', 'ip_verify_id');
			$table->renameColumn('userid', 'user_id');
		});

		$sm->alterTable('xf_dbtech_security_login_strike', function (Alter $table)
		{
			$table->renameColumn('loginstrikesid', 'login_strike_id');
			$table->renameColumn('validuser', 'valid_user');
		});
	}

	/**
	 *
	 */
	public function upgrade904010031Step6(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_dbtech_security_recovery_log', function (Alter $table)
		{
			$table->renameColumn('recoverylogid', 'recovery_log_id');
			$table->renameColumn('userid', 'user_id');
			$table->renameColumn('existingemail', 'existing_email');
			$table->renameColumn('newemail', 'new_email');
			$table->renameColumn('pmuser', 'pm_user');
		});

		$sm->alterTable('xf_dbtech_security_snapshot', function (Alter $table)
		{
			$table->renameColumn('snapshotid', 'snapshot_id');
		});

		$sm->alterTable('xf_dbtech_security_watcher_log', function (Alter $table)
		{
			$table->renameColumn('watcherlogid', 'watcher_log_id');
			$table->renameColumn('dateline', 'log_date');
			$table->renameColumn('ipaddress', 'ip_address');
		});
	}

	/**
	 *
	 */
	public function upgrade904010031Step7(): void
	{
		$sm = $this->schemaManager();

		$this->db()->delete('xf_dbtech_security_watcher_log', null);

		$sm->alterTable('xf_dbtech_security_watcher_log', function (Alter $table)
		{
			$table->addColumn('log_date', 'int')->setDefault(0)->after('watcher_log_id');
			$table->addColumn('user_id', 'int')->setDefault(0)->after('log_date');
			$table->addColumn('watcher_id', 'int')->setDefault(0)->after('ip_address');
			$table->addColumn('action_params', 'mediumblob')->nullable(true)->after('action');
		});
	}

	public function upgrade904010031Step8(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user_remember', function (Alter $table)
		{
			$table->addColumn('dbtech_security_user_agent', 'text')->nullable(true);
		});
	}

	/**
	 *
	 */
	public function upgrade904010031Step9(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_ip_match', function (Alter $table)
		{
			$table->changeColumn('match_type')->addValues(['dbtech_security_tor', 'dbtech_security_country']);
			$table->changeColumn('dbtech_security_comment')->resetDefinition()->type('varchar', 15)->setDefault('');
		});
	}

	/**
	 *
	 */
	public function upgrade904010031Step10(): void
	{
		$sm = $this->schemaManager();

		$sm->renameTable('xf_dbtech_security_watcher', 'xf_dbtech_security_watcher_temp');

		$sm->createTable('xf_dbtech_security_watcher', function (Create $table)
		{
			$table->addColumn('watcher_id', 'int')->autoIncrement();
			$table->addColumn('watcher_type', 'varchar', 50)->setDefault('');
			$table->addColumn('active', 'tinyint')->setDefault(1);
			$table->addColumn('priority', 'int')->setDefault(1);
			$table->addColumn('rule_data', 'mediumblob')->nullable(true);
			$table->addColumn('actions', 'mediumblob')->nullable(true);
			$table->addColumn('extra_data', 'mediumblob')->nullable(true);
		});

		$oldWatchers = $this->db()->fetchAll("
			SELECT *
			FROM xf_dbtech_security_watcher_temp
		");

		$insert = [];
		foreach ($oldWatchers AS $oldWatcher)
		{
			$watcherType = match ($oldWatcher['watcher'])
			{
				'username', 'password', 'email', 'usergroupid', 'membergroupids' => 'user',
				'boardActive', 'boardInactiveMessage', 'dbtech_security_allowip', 'dbtech_security_allowip_exclude' => 'option',
				default => $oldWatcher['watcher'],
			};
			if (!$watcherType)
			{
				continue;
			}

			$oldWatcher['ruledata'] = Php::safeUnserialize($oldWatcher['ruledata']);

			$oldWatcher['extradata'] = match ($oldWatcher['watcher'])
			{
				'configtamper' => $oldWatcher['extradata'] ? ['differences' => $oldWatcher['extradata']] : [],
				default => Php::safeUnserialize($oldWatcher['extradata']),
			};

			$ruleData = is_array($oldWatcher['ruledata']) ? $oldWatcher['ruledata'] : [];

			if (isset($ruleData['ipaddresses']))
			{
				$ruleData['ipaddresses'] = $ruleData['ipaddresses'] ? 'same' : 'any';
			}

			switch ($watcherType)
			{
				case 'user':
					$ruleData['field'] = match ($oldWatcher['watcher'])
					{
						'usergroupid' => 'user_group_id',
						'membergroupids' => 'secondary_group_ids',
						default => $oldWatcher['watcher'],
					};
					break;

				case 'option':
					$ruleData['field'] = $oldWatcher['watcher'];
			}

			if (isset($ruleData['intrusions']) && !$ruleData['intrusions'])
			{
				continue;
			}

			if (isset($ruleData['hours']) && !$ruleData['hours'])
			{
				continue;
			}

			$insert[] = [
				'watcher_type' => $watcherType,
				'active' => 1,
				'priority' => ($oldWatcher['ruleid'] + 1),
				'rule_data' => json_encode($ruleData),
				'extra_data' => json_encode(is_array($oldWatcher['extradata']) ? $oldWatcher['extradata'] : []),
				'actions' => json_encode([
					'closeForum' => (bool) ((int) $oldWatcher['actions'] & 1),
					'emailWebmaster' => (bool) ((int) $oldWatcher['actions'] & 2),
					'banUser' => (bool) ((int) $oldWatcher['actions'] & 4),
					'banIp' => (bool) ((int) $oldWatcher['actions'] & 8),
					'emailUser' => (bool) ((int) $oldWatcher['actions'] & 16),
					'lockUser' => (bool) ((int) $oldWatcher['actions'] & 32),
					'adminLockUser' => (bool) ((int) $oldWatcher['actions'] & 64),
				]),
			];
		}

		if (count($insert))
		{
			$this->db()->insertBulk('xf_dbtech_security_watcher', $insert, true);
		}

		$sm->dropTable('xf_dbtech_security_watcher_temp');
	}

	/**
	 *
	 * @throws Exception
	 */
	public function upgrade904010031Step11(): void
	{
		$globalAllowIp = $this->db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'dbtech_security_globalallowip'
		");

		$newOptionValue = [];

		$ips = preg_split('#\s+#', $globalAllowIp, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($ips AS $ip)
		{
			$parsed = Ip::parseIpRangeString($ip);

			if (!$parsed)
			{
				continue;
			}

			$newOptionValue[] = [
				'ip' => $ip,
				'isRange' => $parsed['isRange'],
			];
		}

		$this->query("
			UPDATE xf_option
			SET option_value = ?
			WHERE option_id = 'dbtech_security_globalallowip'
		", json_encode($newOptionValue));
	}

	/**
	 *
	 * @throws Exception
	 */
	public function upgrade904010031Step12(): void
	{
		$allowIp = $this->db()->fetchOne("
			SELECT option_value
			FROM xf_option
			WHERE option_id = 'dbtech_security_allowip'
		");

		$newOptionValue = [];

		$ips = preg_split('#\s+#', $allowIp, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($ips AS $ip)
		{
			$parsed = Ip::parseIpRangeString($ip);

			if (!$parsed)
			{
				continue;
			}

			$newOptionValue[] = [
				'ip' => $ip,
				'isRange' => $parsed['isRange'],
			];
		}

		$this->query("
			UPDATE xf_option
			SET option_value = ?
			WHERE option_id = 'dbtech_security_allowip'
		", json_encode($newOptionValue));
	}

	public function upgrade904060970Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user_remember', function (Alter $table)
		{
			$table->changeColumn('dbtech_security_user_agent')
				->resetDefinition()
				->type('blob')
				->nullable(true)
			;
		});
	}

	/**
	 * @param array $stepParams
	 *
	 * @return array|bool
	 */
	public function upgrade904020070Step1(array $stepParams): bool|array
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];

		return $this->entityColumnsToJson(FingerprintLog::class, ['components'], $position, $stepParams);
	}

	/**
	 * @param array $stepParams
	 *
	 * @return array|bool
	 */
	public function upgrade904020070Step2(array $stepParams): bool|array
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];

		return $this->entityColumnsToJson(RecoveryLog::class, ['breakdown'], $position, $stepParams);
	}

	/**
	 * @param array $stepParams
	 *
	 * @return array|bool
	 */
	public function upgrade904020070Step3(array $stepParams): bool|array
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];

		return $this->entityColumnsToJson(Snapshot::class, ['data'], $position, $stepParams);
	}

	public function upgrade904030051Step1(): void
	{
		$sm = $this->schemaManager();

		$sm->alterTable('xf_user_remember', function (Alter $table)
		{
			$table->changeColumn('user_agent')
				->resetDefinition()
				->type('blob')
				->nullable(true)
				->renameTo('dbtech_security_user_agent')
			;
		});

		$sm->alterTable('xf_user_tfa_trusted', function (Alter $table)
		{
			$table->changeColumn('user_agent')
				->resetDefinition()
				->type('blob')
				->nullable(true)
				->renameTo('dbtech_security_user_agent')
			;
		});
	}

	/**
	 *
	 * @throws Exception
	 */
	public function upgrade904050031Step1(): void
	{
		$defaultValue = [
			'closeForum' => false,
			'emailWebmaster' => false,
			'banIp' => false,
			'banUser' => false,
			'emailUser' => false,
			'lockChange' => false,
			'lockReset' => false,
			'lockUser' => false,
			'adminLockUser' => false,
		];

		$items = $this->db()->fetchAll('SELECT * FROM xf_dbtech_security_watcher');
		foreach ($items AS $item)
		{
			try
			{
				$actions = \json_decode($item['actions'], true);
			}
			catch (\InvalidArgumentException $e)
			{
				$actions = [];
			}

			$update = false;
			foreach (array_keys($defaultValue) AS $key)
			{
				if (!isset($actions[$key]))
				{
					$update = true;
					$actions[$key] = $defaultValue[$key];
				}
			}

			if ($update)
			{
				$this->query("
					UPDATE xf_dbtech_security_watcher
					SET actions = ?
					WHERE watcher_id = ?
				", [
					\json_encode($actions),
					$item['watcher_id'],
				]);
			}
		}
	}

	/**
	 * @param $previousVersion
	 * @param array $stateChanges
	 *
	 * @throws PrintableException
	 * @throws GuzzleException
	 */
	protected function postUpgrade904010031($previousVersion, array &$stateChanges): void
	{
		$torRepo = \XF::repository(TorRepository::class);
		$torRepo->updateNodes();

		$countryRepo = \XF::repository(CountryRepository::class);
		$countryRepo->updateCountryList();

		\XF::app()->jobManager()->enqueueUnique(
			'dbtechSecurityCountryBlockRebuild',
			'DBTech\Security:CountryBlockRebuild',
			[],
			false
		);
	}
}