<?php

namespace DBTech\Security\Searcher;

use DBTech\Security\Repository\WatcherRepository;
use XF\Searcher\AbstractSearcher;

class WatcherLog extends AbstractSearcher
{
	/** @var array  */
	protected $allowedRelations = ['Watcher', 'User'];

	/** @var array  */
	protected $formats = [
		'username' => 'like',
		'message' => 'like',
		'ip_address' => 'like',
	];

	/** @var array  */
	protected $order = [['log_date', 'desc']];


	/**
	 * @return string
	 */
	protected function getEntityType(): string
	{
		return 'DBTech\Security:WatcherLog';
	}

	/**
	 * @return array
	 */
	protected function getDefaultOrderOptions(): array
	{
		$orders = [
			'log_date' => \XF::phrase('date'),
		];

		\XF::fire('dbtech_security_watcher_log_searcher_orders', [$this, &$orders]);

		return $orders;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function getFormData(): array
	{
		return [
			'watchers' => \XF::app()->repository(WatcherRepository::class)
				->getWatcherTitlePairs(),
		];
	}
}