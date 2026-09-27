<?php

declare(strict_types=1);

namespace DC\LoginAsUser\XF\Repository;

use DC\LoginAsUser\Impersonation;
use XF\Entity\Thread;
use XF\Entity\User;

/**
 * markThreadReadByVisitor() is a two-line delegate to this, and every read-marking call site in
 * core reaches one or the other. This method also cascades into marking the containing forum read,
 * so guarding here kills that too.
 *
 * false is core's own "nothing was marked" return.
 */
class ThreadRepository extends XFCP_ThreadRepository
{
	public function markThreadReadByUser(Thread $thread, User $user, $newRead = null)
	{
		if (Impersonation::suppressesFor(Impersonation::SUPPRESS_READ_MARKING, (int) $user->user_id))
		{
			return false;
		}

		return parent::markThreadReadByUser($thread, $user, $newRead);
	}
}
