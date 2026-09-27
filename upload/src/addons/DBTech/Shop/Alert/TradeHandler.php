<?php

namespace DBTech\Shop\Alert;

use XF\Alert\AbstractHandler;

class TradeHandler extends AbstractHandler
{
	/**
	 * @return array
	 */
	public function getEntityWith(): array
	{
		return ['Creator', 'Recipient'];
	}

	/**
	 * @return array
	 */
	public function getOptOutActions(): array
	{
		return [
			'invite',
			'invite_decline',
			'invite_accept',
			'cancel',
			'modify',
			'accept',
			'finalize',
		];
	}

	/**
	 * @return int
	 */
	public function getOptOutDisplayOrder(): int
	{
		return 89999;
	}
}