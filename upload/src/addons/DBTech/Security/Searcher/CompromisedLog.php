<?php

namespace DBTech\Security\Searcher;

use XF\Searcher\AbstractSearcher;

class CompromisedLog extends AbstractSearcher
{
	/** @var array  */
	protected $allowedRelations = ['User'];

	/** @var array  */
	protected $formats = [
		'username' => 'like',
		'attempted_usernames' => 'like',
		'ipaddress' => 'like',
	];

	/** @var array  */
	protected $order = [['dateline', 'desc']];


	/**
	 * @return string
	 */
	protected function getEntityType(): string
	{
		return 'DBTech\Security:CompromisedLog';
	}

	/**
	 * @return array
	 */
	protected function getDefaultOrderOptions(): array
	{
		$orders = [
			'dateline' => \XF::phrase('date'),
			'User.username' => \XF::phrase('user_name'),
		];

		\XF::fire('dbtech_security_compromised_log_searcher_orders', [$this, &$orders]);

		return $orders;
	}
}