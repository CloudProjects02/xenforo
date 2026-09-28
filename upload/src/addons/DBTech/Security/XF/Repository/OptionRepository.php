<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Repository;

use DBTech\Security\Repository\WatcherRepository;
use XF\Entity\Option;
use XF\Mvc\Entity\AbstractCollection;
use XF\PrintableException;

/**
 * @extends \XF\Repository\OptionRepository
 */
class OptionRepository extends XFCP_OptionRepository
{
	/**
	 * @param array $values
	 *
	 * @return AbstractCollection
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function updateOptions(array $values)
	{
		$watcherRepo = \XF::app()->repository(WatcherRepository::class);
		$handler = $watcherRepo->getHandler('option', false);

		if (!$handler)
		{
			return parent::updateOptions($values);
		}

		$oldValues = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\XF\Entity\Option> $options */
		$options = \XF::app()->em()->findByIds(Option::class, array_keys($values));

		foreach ($options AS $option)
		{
			$oldValue = $option->option_value;
			if (is_array($oldValue))
			{
				$oldValue = json_encode($oldValue);
			}

			$oldValues[$option->option_id] = $oldValue;
		}

		$retval = parent::updateOptions($values);
		\XF::app()->em()->clearEntityCache(Option::class);

		/** @var \XF\Mvc\Entity\AbstractCollection<\XF\Entity\Option> $options */
		$options = \XF::app()->em()->findByIds(Option::class, array_keys($values));

		foreach ($options AS $option)
		{
			$oldValue = $oldValues[$option->option_id];
			$newValue = $option->option_value;

			if (is_array($newValue))
			{
				$newValue = json_encode($newValue);
			}

			if (strval($oldValue) === strval($newValue))
			{
				continue;
			}

			$handler->trigger([
				'ipaddress' 	=> \XF::app()->request()->getIp(),
				'script' 		=> 'options',
				'action' 		=> 'edited',
				'id' 			=> 0,
				'title' 		=> $option->title,
				'field' 		=> $option->option_id,
				'old' 			=> strval($oldValue),
				'new' 			=> strval($newValue),
				'differences'  	=> $oldValue . ' -> ' . $newValue,
			], \XF::visitor());
		}

		return $retval;
	}
}