<?php

namespace DBTech\Security\Install;

use XF\AddOn\AddOn;
use XF\Admin\App;
use XF\Behavior\DevOutputWritable as DevOutputWritableBehavior;
use XF\Db\AbstractAdapter;
use XF\Db\Schema\AbstractDdl;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Column;
use XF\Db\Schema\Create;
use XF\Db\SchemaManager;
use XF\Entity\Option;
use XF\Entity\Phrase;
use XF\Entity\StyleProperty;
use XF\Finder\PhraseFinder;
use XF\PreEscaped;
use XF\PrintableException;
use XF\Repository\NodeTypeRepository;
use XFES\Elasticsearch\Exception;
use XFES\Listener;
use XFES\Service\Configurer;
use XFES\Service\Optimizer;

/**
 * @property AddOn addOn
 *
 * @method AbstractAdapter db()
 * @method SchemaManager schemaManager()
 */
trait InstallerTrait
{
	/**
	 * Default checkRequirements which triggers various actions
	 *
	 * @param string[] $errors
	 * @param string[] $warnings
	 *
	 * @noinspection PhpMissingReturnTypeInspection
	 * @noinspection PhpMissingParamTypeInspection
	 */
	public function checkRequirements(&$errors = [], &$warnings = [])
	{
		$this->checkComposer($errors);
		$this->checkSoftRequires($errors, $warnings);
		$this->isCliRecommended($warnings);
	}

	/**
	 * @param string $title
	 * @param string $value
	 * @param bool $deOwn
	 *
	 * @throws PrintableException
	 */
	protected function addDefaultPhrase(string $title, string $value, bool $deOwn = true): void
	{
		$phrase = \XF::em()->findOne(
			Phrase::class,
			[
				['title', $title],
				['language_id', 0],
			]
		);
		if (!$phrase)
		{
			$phrase = \XF::em()->create(Phrase::class);
			$phrase->language_id = 0;
			$phrase->title = $title;
			$phrase->phrase_text = $value;
			$phrase->global_cache = false;
			$phrase->addon_id = '';
			$phrase->save(false);
		}
		else if ($deOwn && $phrase->addon_id === $this->addOn->getAddOnId())
		{
			$phrase->addon_id = '';
			$phrase->save(false);
		}
	}

	/**
	 * @param string $groupId
	 * @param string $permissionId
	 * @param int[] $userGroups
	 *
	 * @throws \XF\Db\Exception
	 */
	protected function applyGlobalPermissionByGroup(string $groupId, string $permissionId, array $userGroups): void
	{
		foreach ($userGroups AS $userGroupId)
		{
			$this->applyGlobalPermissionForGroup(
				$groupId,
				$permissionId,
				$userGroupId
			);
		}
	}

	/**
	 * @param string $applyGroupId
	 * @param string $applyPermissionId
	 * @param int $userGroupId
	 *
	 * @throws \XF\Db\Exception
	 */
	public function applyGlobalPermissionForGroup(string $applyGroupId, string $applyPermissionId, int $userGroupId): void
	{
		$this->db()->query(
			"INSERT IGNORE INTO xf_permission_entry 
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int)
				VALUES
					(?, 0, ?, ?, 'allow', '0')
			",
			[$userGroupId, $applyGroupId, $applyPermissionId]
		);
	}

	/**
	 * @param string $applyGroupId
	 * @param string $applyPermissionId
	 * @param int $applyValue
	 * @param int $userGroupId
	 *
	 * @throws \XF\Db\Exception
	 */
	public function applyGlobalPermissionIntForGroup(
		string $applyGroupId,
		string $applyPermissionId,
		int $applyValue,
		int $userGroupId
	): void
	{
		$this->db()->query(
			"INSERT IGNORE INTO xf_permission_entry 
					(user_group_id, user_id, permission_group_id, permission_id, permission_value, permission_value_int) 
			VALUES
				(?, 0, ?, ?, 'use_int', ?)
			",
			[$userGroupId, $applyGroupId, $applyPermissionId, $applyValue]
		);
	}

	/**
	 * @param array $newRegistrationDefaults
	 */
	protected function applyRegistrationDefaults(array $newRegistrationDefaults): void
	{
		$option = \XF::em()->find(Option::class, 'registrationDefaults');
		if (!$option)
		{
			throw new \LogicException(
				"XenForo installation is damaged. Expected option 'registrationDefaults' to exist."
			);
		}

		$registrationDefaults = $option->option_value;
		foreach ($newRegistrationDefaults AS $optionName => $optionDefault)
		{
			if (!isset($registrationDefaults[$optionName]))
			{
				$registrationDefaults[$optionName] = $optionDefault;
			}
		}

		$option->option_value = $registrationDefaults;
		$option->saveIfChanged();
	}

	/**
	 * @param string $oldGroupId
	 * @param string $oldPermissionId
	 * @param string $newGroupId
	 * @param string $newPermissionId
	 *
	 * @throws \XF\Db\Exception
	 */
	protected function renamePermission(
		string $oldGroupId,
		string $oldPermissionId,
		string $newGroupId,
		string $newPermissionId
	): void
	{
		$this->db()->query('
			UPDATE IGNORE xf_permission_entry
			SET permission_group_id = ?, permission_id = ?
			WHERE permission_group_id = ? AND permission_id = ?
		', [$newGroupId, $newPermissionId, $oldGroupId, $oldPermissionId]);

		$this->db()->query('
			UPDATE IGNORE xf_permission_entry_content
			SET permission_group_id = ?, permission_id = ?
			WHERE permission_group_id = ? AND permission_id = ?
		', [$newGroupId, $newPermissionId, $oldGroupId, $oldPermissionId]);

		$this->db()->query('
			DELETE FROM xf_permission_entry
			WHERE permission_group_id = ? AND permission_id = ?
		', [$oldGroupId, $oldPermissionId]);

		$this->db()->query('
			DELETE FROM xf_permission_entry_content
			WHERE permission_group_id = ? AND permission_id = ?
		', [$oldGroupId, $oldPermissionId]);
	}

	/**
	 * @param string $old
	 * @param string $new
	 * @param bool $takeOwnership
	 *
	 * @throws PrintableException
	 */
	protected function renameOption(string $old, string $new, bool $takeOwnership = false): void
	{
		$optionOld = \XF::em()->find(Option::class, $old);
		$optionNew = \XF::em()->find(Option::class, $new);

		if ($optionOld && !$optionNew)
		{
			$optionOld->option_id = $new;
			if ($takeOwnership)
			{
				$optionOld->addon_id = $this->addOn->getAddOnId();
			}
			if ($optionOld->hasBehavior(DevOutputWritableBehavior::class))
			{
				$optionOld->getBehavior(DevOutputWritableBehavior::class)
					->setOption('write_dev_output', false)
				;
			}
			$optionOld->saveIfChanged();
		}
		else if ($takeOwnership && $optionOld && $optionNew)
		{
			$optionNew->option_value = $optionOld->option_value;
			$optionNew->addon_id = $this->addOn->getAddOnId();
			if ($optionNew->hasBehavior(DevOutputWritableBehavior::class))
			{
				$optionNew->getBehavior(DevOutputWritableBehavior::class)
					->setOption('write_dev_output', false)
				;
			}
			$optionNew->save();
			$optionOld->delete();
		}
	}

	/**
	 * @param array $map
	 * @param bool $deOwn
	 * @param bool $replace
	 *
	 * @throws PrintableException
	 */
	protected function renamePhrases(array $map, bool $deOwn = false, bool $replace = true): void
	{
		$db = $this->db();

		foreach ($map AS $from => $to)
		{
			$mySqlRegex = '^' . \str_replace('*', '[a-zA-Z0-9_]+', $from) . '$';
			$phpRegex = '/^' . \str_replace('*', '([a-zA-Z0-9_]+)', $from) . '$/';
			$replacePhrase = \str_replace('*', '$1', $to);

			$results = $db->fetchPairs("
				SELECT phrase_id, title
				FROM xf_phrase
				WHERE CONVERT(title USING utf8mb4) RLIKE ?
					AND addon_id = ''
			", $mySqlRegex);

			if ($results)
			{
				$em = \XF::em();
				$phrases = \XF::em()->findByIds(Phrase::class, \array_keys($results));
				foreach ($results AS $phraseId => $oldTitle)
				{
					if (isset($phrases[$phraseId]))
					{
						$newTitle = \preg_replace($phpRegex, $replacePhrase, $oldTitle);
						$phrase = $phrases[$phraseId];

						$db->beginTransaction();

						$newPhrase = $replace
							? $em->getFinder(PhraseFinder::class, false)
								 ->where('title', '=', $newTitle)
								 ->fetchOne()
							: null;

						if ($newPhrase)
						{
							// already exists, replace the value and delete
							$newPhrase->set('title', $phrase->phrase_text, ['forceSet' => true]);
							$newPhrase->set('global_cache', false, ['forceSet' => true]);
							if ($deOwn)
							{
								$newPhrase->addon_id = '';
							}
							if ($newPhrase->hasBehavior(DevOutputWritableBehavior::class))
							{
								$newPhrase->getBehavior(DevOutputWritableBehavior::class)
									->setOption('write_dev_output', false)
								;
							}
							$newPhrase->save(false);
							$phrase->delete(false);
						}
						else
						{
							$phrase->set('title', $newTitle, ['forceSet' => true]);
							$phrase->set('global_cache', false, ['forceSet' => true]);
							if ($deOwn)
							{
								$phrase->addon_id = '';
							}
							if ($phrase->hasBehavior(DevOutputWritableBehavior::class))
							{
								$phrase->getBehavior(DevOutputWritableBehavior::class)
									->setOption('write_dev_output', false)
								;
							}
							$phrase->save(false);
						}

						$db->commit();
					}
				}
			}
		}
	}

	/**
	 * @param string[] $map
	 *
	 * @throws PrintableException
	 */
	protected function deletePhrases(array $map): void
	{
		$titles = [];
		foreach ($map AS $titlePattern)
		{
			$titles[] = ['title', 'LIKE', $titlePattern];
		}

		$phrases = \XF::finder(PhraseFinder::class)
			->where('language_id', 0)
			->whereOr($titles)
			->fetch()
		;
		foreach ($phrases AS $phrase)
		{
			$phrase->delete();
		}
	}

	/**
	 * @param string $old
	 * @param string $new
	 */
	protected function renameStyleProperty(string $old, string $new): void
	{
		$optionOld = \XF::em()->findOne(
			StyleProperty::class,
			['property_name', $old]
		);
		$optionNew = \XF::em()->findOne(
			StyleProperty::class,
			['property_name', $new]
		);
		if ($optionOld && !$optionNew)
		{
			if ($optionOld->hasBehavior(DevOutputWritableBehavior::class))
			{
				$optionOld->getBehavior(DevOutputWritableBehavior::class)
					->setOption('write_dev_output', false)
				;
			}
			$optionOld->property_name = $new;
			$optionOld->saveIfChanged();
		}
	}

	/**
	 * @param string $old
	 * @param string $new
	 * @param bool $dropOldIfNewExists
	 */
	protected function migrateTable(string $old, string $new, bool $dropOldIfNewExists = false): void
	{
		$sm = $this->schemaManager();
		if ($sm->tableExists($old))
		{
			if (!$sm->tableExists($new))
			{
				$sm->renameTable($old, $new);
			}
			else if ($dropOldIfNewExists)
			{
				$sm->dropTable($old);
			}
		}
	}

	/**
	 * @param AbstractDdl $table
	 * @param string $name
	 * @param string|null $type
	 * @param int|string|string[]|null $length
	 *
	 * @return Column
	 */
	protected function addOrChangeColumn(
		AbstractDdl $table,
		string $name,
		?string $type = null,
		array|int|string|null $length = null
	): Column
	{
		if ($table instanceof Create)
		{
			$table->checkExists(true);

			return $table->addColumn($name, $type, $length);
		}
		else if ($table instanceof Alter)
		{
			if ($table->getColumnDefinition($name))
			{
				return $table->changeColumn($name, $type, $length);
			}

			return $table->addColumn($name, $type, $length);
		}
		else
		{
			throw new \LogicException('Unknown schema DDL type ' . \get_class($table));
		}
	}

	/**
	 * @param string[] $errors
	 *
	 * @return void
	 */
	protected function checkComposer(array &$errors): void
	{
		$json = $this->addOn->getJson();
		$composerPath = $json['composer_autoload'] ?? '';
		if (\strlen($composerPath))
		{
			$vendorDirectory = $this->addOn->getAddOnDirectory() . \XF::$DS . $composerPath;
			if (!\file_exists($vendorDirectory))
			{
				$errors[] = 'Please install the neccessary Composer dependencies before installing this add-on.';
			}
		}
	}

	/**
	 * @param int $minAddonVersion
	 * @param int $maxThreads
	 * @param int $maxPosts
	 * @param int $maxUsers
	 *
	 * @return bool
	 */
	protected function isCliRecommendedCheck(int $minAddonVersion, int $maxThreads, int $maxPosts, int $maxUsers): bool
	{
		$totals = \XF::app()->db()->fetchOne("
			SELECT data_value
			FROM xf_data_registry
			WHERE data_key IN ('boardTotals', 'forumStatistics')
			LIMIT 1
		");
		if (!$totals)
		{
			return false;
		}

		$totals = @\unserialize($totals);
		if (!$totals)
		{
			return false;
		}

		if ($maxPosts && !empty($totals['messages']) && $totals['messages'] >= $maxPosts)
		{
			return true;
		}

		if ($maxUsers && !empty($totals['users']) && $totals['users'] >= $maxUsers)
		{
			return true;
		}

		if ($maxThreads && !empty($totals['threads']) && $totals['threads'] >= $maxThreads)
		{
			return true;
		}

		if ($minAddonVersion)
		{
			$existing = $this->addOn->getInstalledAddOn();
			if ($existing === null || $existing->version_id < $minAddonVersion)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string[] $warnings
	 * @param int $minAddonVersion
	 * @param int $maxThreads
	 * @param int $maxPosts
	 * @param int $maxUsers
	 *
	 * @return bool
	 */
	public function isCliRecommended(
		array &$warnings,
		int $minAddonVersion = 0,
		int $maxThreads = 0,
		int $maxPosts = 500000,
		int $maxUsers = 50000
	): bool
	{
		if (\XF::app() instanceof App
			&& $this->isCliRecommendedCheck(
				$minAddonVersion,
				$maxThreads,
				$maxPosts,
				$maxUsers
			)
		)
		{
			$existing = $this->addOn->getInstalledAddOn();
			if ($existing)
			{
				$html = 'Your XenForo installation is large. You may wish to upgrade via the command line.<br/>
						Simply run this command from within the root XenForo directory and follow the on-screen instructions:<br/>
						<pre style="margin: 1em 2em">php cmd.php xf-addon:upgrade ' . \XF::escapeString($this->addOn->getAddOnId()) . '</pre>
						You can continue with the browser-based upgrade, but large queries may cause browser timeouts<br/>
						that will force you to reload the page.';
			}
			else
			{
				$html = 'Your XenForo installation is large. You may wish to install via the command line.<br/>
						Simply run this command from within the root XenForo directory and follow the on-screen instructions:<br/>
						<pre style="margin: 1em 2em">php cmd.php xf-addon:install ' . \XF::escapeString($this->addOn->getAddOnId()) . '</pre>
						You can continue with the browser-based install, but large queries may cause browser timeouts<br/>
						that will force you to reload the page.';
			}

			$warnings[] = new PreEscaped($html);

			return true;
		}

		return false;
	}

	/**
	 * @param string $type
	 * @param string $entityIdentifier
	 * @param string $permissionGroupId
	 * @param string $adminRoute
	 * @param string $publicRoute
	 * @param string $handlerClass
	 * @param bool $rebuildCache
	 *
	 * @return void
	 */
	public function insertNodeType(
		string $type,
		string $entityIdentifier,
		string $permissionGroupId,
		string $adminRoute,
		string $publicRoute,
		string $handlerClass,
		bool $rebuildCache = true
	): void
	{
		$this->db()->insert('xf_node_type', [
			'node_type_id' => $type,
			'entity_identifier' => $entityIdentifier,
			'permission_group_id' => $permissionGroupId,
			'admin_route' => $adminRoute,
			'public_route' => $publicRoute,
			'handler_class' => $handlerClass,
		], false, '
			entity_identifier = VALUES(entity_identifier),
			handler_class = VALUES(handler_class),
			permission_group_id = VALUES(permission_group_id),
			admin_route = VALUES(admin_route),
			handler_class = VALUES(handler_class)
		');

		if ($rebuildCache)
		{
			\XF::runOnce('rebuildNodeTypeCache', function ()
			{
				\XF::repository(NodeTypeRepository::class)
					->rebuildNodeTypeCache()
				;
			});
		}
	}

	/**
	 * Supports a 'require-soft' section with near identical structure to 'require'
	 *
	 * An example;
		"require-soft" :{
			"MyVendorPrefix/MyAddOn": [
				2000370,
				"My Add-on v2.0.3+",
				false,
				"Please provide feedback if you are unable to upgrade."
			]
		},
	 * The 3rd array argument has 3 supported values, null/true/false
	 *   null/no exists - this entry will not be checked
	 *   false - if the item exists and is below the minimum version, log as a warning
	 *   true - if the item exists and is below the minimum version, log as an error
	 *
	 * The 4th array argument is extra help text intended to offer a more detailed explanation why
	 * this version is required. For instance, if you're checking for PHP 7.2.0+, you can explain
	 * that you plan to bump the minimum version going forward.
	 *
	 * @param string[] $errors
	 * @param string[] $warnings
	 */
	protected function checkSoftRequires(array &$errors, array &$warnings): void
	{
		$json = $this->addOn->getJson();
		if (empty($json['require-soft']))
		{
			return;
		}

		$addOns = \XF::app()->container('addon.cache');
		foreach ((array) $json['require-soft'] AS $productKey => $requirement)
		{
			if (!\is_array($requirement))
			{
				continue;
			}
			[$version, $product] = $requirement;
			$errorType = count($requirement) >= 3 ? $requirement[2] : null;

			// advisory
			if ($errorType === null)
			{
				continue;
			}

			$enabled = false;
			$versionValid = false;

			if (str_starts_with($productKey, 'php-ext'))
			{
				$parts = \explode('/', $productKey, 2);
				if (isset($parts[1]))
				{
					$enabled = \phpversion($parts[1]) !== false;
					$versionValid = ($version === '*') || (\version_compare(\phpversion($parts[1]), $version, 'ge'));
				}
			}
			else if (str_starts_with($productKey, 'php'))
			{
				$enabled = true;
				$versionValid = \version_compare(\phpversion(), $version, 'ge');
			}
			else if (str_starts_with($productKey, 'mysql'))
			{
				$mySqlVersion = \XF::db()->getServerVersion();
				if ($mySqlVersion)
				{
					$enabled = true;
					$versionValid = \version_compare(\strtolower($mySqlVersion), $version, 'ge');
				}
			}
			else
			{
				$enabled = isset($addOns[$productKey]);
				$versionValid = ($version === '*' || ($enabled && $addOns[$productKey] >= $version));
			}

			if (!$enabled)
			{
				continue;
			}

			if (!$versionValid)
			{
				$reason = \count($requirement) >= 4 ? (' ' . $requirement[3]) : '';

				if ($errorType)
				{
					$errors[] = new PreEscaped(\sprintf(
						'%s requires %s.%s',
						$json['title'],
						$product,
						$reason
					));
				}
				else
				{
					$warnings[] = new PreEscaped(\sprintf(
						'%s recommends %s.%s',
						$json['title'],
						$product,
						$reason
					));
				}
			}
		}
	}

	/**
	 * Determine if elasticsearch type mapping require updating, which can require re-indexing the entire site.
	 */
	protected function checkElasticSearchOptimizableState(): void
	{
		$es = Listener::getElasticsearchApi();

		$configurer = \XF::service(Configurer::class, $es);
		$testError = null;
		$isOptimizable = false;

		if ($configurer->hasActiveConfig())
		{
			try
			{
				$version = $es->version();

				if ($version && $es->test($testError))
				{
					if ($es->indexExists())
					{
						$optimizer = \XF::service(Optimizer::class, $es);
						$isOptimizable = $optimizer->isOptimizable();
					}
					else
					{
						$isOptimizable = true;
					}
				}
			}
			catch (Exception $e)
			{
			}
		}

		if ($isOptimizable)
		{
			\XF::logError('Elasticsearch index must be rebuilt to include custom mappings.', true);
		}
	}

	/**
	 * Determine whether a permission is currently in use by another add-on.  Installers can use this to determine
	 * whether a permission that was formerly associated with a different add-on should receive default settings or
	 * should be left alone.
	 *
	 * For example, if MyVendorPrefix/FooBar is split into two add-ons, MyVendorPrefix/Foo and MyVendorPrefix/Bar,
	 * the two new add-ons will need to avoid overwriting permissions that
	 * have already been configured as part of MyVendorPrefix/FooBar.
	 *
	 * @param string $permissionGroupId
	 * @param string $permissionId
	 * @return bool
	 */
	public function isPermissionInUse(string $permissionGroupId, string $permissionId): bool
	{
		return (bool) \XF::db()->fetchOne(
			"
				SELECT
					EXISTS(SELECT * FROM xf_permission WHERE permission_group_id = ? AND permission_id = ?)
					OR EXISTS(SELECT * FROM xf_permission_entry WHERE permission_group_id = ? AND permission_id = ?)
					OR EXISTS(SELECT * FROM xf_permission_entry_content WHERE permission_group_id = ? AND permission_id = ?)
			",
			[
				$permissionGroupId,
				$permissionId,
				$permissionGroupId,
				$permissionId,
				$permissionGroupId,
				$permissionId,
			]
		);
	}
}