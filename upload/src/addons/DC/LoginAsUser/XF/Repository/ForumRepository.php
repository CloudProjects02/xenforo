<?php

declare(strict_types=1);

namespace DC\LoginAsUser\XF\Repository;

use DC\LoginAsUser\Impersonation;
use XF\Entity\AbstractNode;
use XF\Entity\Forum;

/**
 * TWO overrides, not one. markForumTreeReadByVisitor() bypasses markForumReadByUser() entirely and
 * runs its own bulk insert into xf_forum_read - that is the "Mark forums read" button, and guarding
 * only the per-forum method would leave it wide open.
 */
class ForumRepository extends XFCP_ForumRepository
{
	public function markForumReadByUser(Forum $forum, $userId, $newRead = null)
	{
		if (Impersonation::suppressesFor(Impersonation::SUPPRESS_READ_MARKING, (int) $userId))
		{
			return false;
		}

		return parent::markForumReadByUser($forum, $userId, $newRead);
	}

	public function markForumTreeReadByVisitor(?AbstractNode $baseNode = null, $newRead = null)
	{
		if (Impersonation::suppressesForVisitor(Impersonation::SUPPRESS_READ_MARKING))
		{
			return [];
		}

		return parent::markForumTreeReadByVisitor($baseNode, $newRead);
	}
}
