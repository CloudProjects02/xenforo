<?php

namespace XF\Webhook\Event;

use XF\Webhook\Criteria\UserUpgrade;

class UserUpgradeHandler extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return UserUpgrade::class;
	}

	public function getEvents(): array
	{
		return array_merge(parent::getEvents(), [
			'purchase_complete', 'purchase_reinstate', 'purchase_reverse',
		]);
	}
}
