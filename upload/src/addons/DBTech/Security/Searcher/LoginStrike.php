<?php

namespace DBTech\Security\Searcher;

use XF\Searcher\AbstractSearcher;

class LoginStrike extends AbstractSearcher
{
	/** @var array  */
	protected $allowedRelations = [];

	/** @var array  */
	protected $formats = [
		'username' => 'like',
		'title' => 'like',
	];

	/** @var array  */
	protected $order = [['dateline', 'desc']];


	/**
	 * @return string
	 */
	protected function getEntityType(): string
	{
		return 'DBTech\Security:LoginStrike';
	}

	/**
	 * @return array
	 */
	protected function getDefaultOrderOptions(): array
	{
		$orders = [
			'dateline' => \XF::phrase('date'),
			'username' => \XF::phrase('user_name'),
		];

		\XF::fire('dbtech_security_login_strike_searcher_orders', [$this, &$orders]);

		return $orders;
	}

	/**
	 * @return array
	 */
	public function getFormDefaults(): array
	{
		return [
			'valid_user' => [0, 1],
		];
	}
}