<?php

namespace DBTech\Security\Searcher;

use XF\Searcher\AbstractSearcher;

class FingerprintLog extends AbstractSearcher
{
	/** @var array  */
	protected $allowedRelations = ['User'];

	/** @var array  */
	protected $formats = [
		'username' => 'like',
		'fingerprint' => 'like',
		'ipaddress' => 'like',
	];

	/** @var array  */
	protected $order = [['dateline', 'desc']];


	/**
	 * @return string
	 */
	protected function getEntityType(): string
	{
		return 'DBTech\Security:FingerprintLog';
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

		\XF::fire('dbtech_security_fingerprint_log_searcher_orders', [$this, &$orders]);

		return $orders;
	}
}