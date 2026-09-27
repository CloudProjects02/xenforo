<?php

namespace DBTech\Shop\Install;

use DBTech\Shop\Repository\ItemFieldRepository;
use DBTech\Shop\Repository\ItemPrefixRepository;
use XF\AddOn\AddOn;
use XF\App;
use XF\Db\AbstractAdapter;
use XF\Db\Schema\Column;
use XF\Db\SchemaManager;

/**
 * @property AddOn addOn
 * @property App app
 *
 * @method AbstractAdapter db()
 * @method SchemaManager schemaManager()
 * @method Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait UpgradeDataTrait
{
	/**
	 * @param int|null $previousVersion
	 * @param array $stateChanges
	 */
	protected function runPostUpgradeActions(?int $previousVersion, array &$stateChanges): void
	{
		$itemPrefixRepo = \XF::repository(ItemPrefixRepository::class);
		$itemPrefixRepo->rebuildPrefixCache();

		$itemFieldRepo = \XF::repository(ItemFieldRepository::class);
		$itemFieldRepo->rebuildFieldCache();


		$reflection = new \ReflectionObject($this);
		foreach ($reflection->getMethods() AS $method)
		{
			if (preg_match('/^postUpgrade(\d+)$/', $method->name, $match))
			{
				$versionId = intval($match[1]);

				if (!$previousVersion || $previousVersion >= $versionId)
				{
					continue;
				}

				$fnPattern = 'postUpgrade%d';
				$func = sprintf($fnPattern, $versionId);

				$this->$func($previousVersion, $stateChanges);
			}
		}
	}
}