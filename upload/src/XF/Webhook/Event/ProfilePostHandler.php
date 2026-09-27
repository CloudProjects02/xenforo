<?php

namespace XF\Webhook\Event;

use XF\Webhook\Criteria\ProfilePost;

class ProfilePostHandler extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return ProfilePost::class;
	}

	public function getEntityWith(): array
	{
		return ['ProfileUser', 'ProfileUser.Privacy'];
	}
}
