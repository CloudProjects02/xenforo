<?php

namespace XF\Repository;

use XF\Finder\ClassExtensionFinder;
use XF\Mvc\Entity\Repository;

class ClassExtensionRepository extends Repository
{
	/**
	 * @return ClassExtensionFinder
	 */
	public function findExtensionsForList()
	{
		$listeners = $this->finder(ClassExtensionFinder::class)
			->order(['from_class', 'to_class', 'execute_order']);

		return $listeners;
	}

	public function getExtensionCacheData()
	{
		$extensions = $this->finder(ClassExtensionFinder::class)
			->whereAddOnActive(['disableProcessing' => true])
			->where('active', 1)
			->order(['execute_order', 'to_class'])
			->fetch();

		return $this->buildExtensionCacheData($extensions);
	}

	protected function buildExtensionCacheData($extensions): array
	{
		$cache = [];

		foreach ($extensions AS $extension)
		{
			// Keep stripped and suffixed class spellings in one cache bucket.
			$fromClass = \XF::getClassForAlias($extension->from_class);
			$cache[$fromClass][] = $extension->to_class;
		}

		// Avoid building the same XFCP proxy twice after spellings are merged.
		foreach ($cache AS $fromClass => $toClasses)
		{
			$cache[$fromClass] = array_values(array_unique($toClasses));
		}

		return $cache;
	}

	public function rebuildExtensionCache()
	{
		$cache = $this->getExtensionCacheData();
		\XF::registry()->set('classExtensions', $cache);
		return $cache;
	}
}
