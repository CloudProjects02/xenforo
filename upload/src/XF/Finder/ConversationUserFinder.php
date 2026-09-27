<?php

namespace XF\Finder;

use XF\Entity\ConversationUser;
use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<ConversationUser> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<ConversationUser> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method ConversationUser|null fetchOne(?int $offset = null)
 * @extends Finder<ConversationUser>
 */
class ConversationUserFinder extends Finder
{
	public function forUser(User $user, $forList = true)
	{
		$this->where('owner_user_id', $user->user_id);

		if ($forList)
		{
			$this->forList($user);
		}

		return $this;
	}

	public function forList(User $user)
	{
		$this->with(['Master.Starter']);

		return $this;
	}

	public function orderForUser(User $user, $orderBy, $orderDir = 'desc')
	{
		if ($orderBy == 'last_message_date')
		{
			$this->order('Users|' . $user->user_id . '.last_message_date', $orderDir);
		}
		else
		{
			$this->order($orderBy, $orderDir);
		}

		return $this;
	}
}
