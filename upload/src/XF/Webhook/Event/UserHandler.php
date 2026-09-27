<?php

namespace XF\Webhook\Event;

use XF\Webhook\Criteria\User;

class UserHandler extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return User::class;
	}
}
