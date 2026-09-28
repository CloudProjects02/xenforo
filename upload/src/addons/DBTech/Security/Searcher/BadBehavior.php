<?php

namespace DBTech\Security\Searcher;

use XF\Searcher\AbstractSearcher;

class BadBehavior extends AbstractSearcher
{
	/** @var array  */
	protected $allowedRelations = [];

	/** @var array  */
	protected $formats = [
		'request_uri' => 'like',
		'ip' => 'like',
	];

	/** @var array  */
	protected $order = [['date', 'desc']];


	/**
	 * @return string
	 */
	protected function getEntityType(): string
	{
		return 'DBTech\Security:BadBehavior';
	}

	/**
	 * @return array
	 */
	protected function getDefaultOrderOptions(): array
	{
		$orders = [
			'date' => \XF::phrase('date'),
		];

		\XF::fire('dbtech_security_bad_behavior_log_searcher_orders', [$this, &$orders]);

		return $orders;
	}

	/**
	 * @return array
	 */
	public function getFormDefaults(): array
	{
		return [
			'request_method' => [
				'GET',
				'POST',
				'HEAD',
				'PUT',
				'DELETE',
				'CONNECT',
				'OPTIONS',
				'TRACE',
				'PATCH',
			],
		];
	}
}