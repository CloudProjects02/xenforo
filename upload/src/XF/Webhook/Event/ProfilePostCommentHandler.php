<?php

namespace XF\Webhook\Event;

use XF\Webhook\Criteria\ProfilePostComment;

class ProfilePostCommentHandler extends AbstractHandler
{
	public function getCriteriaClass(): string
	{
		return ProfilePostComment::class;
	}
}
