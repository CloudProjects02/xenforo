<?php

declare(strict_types=1);

namespace DC\LoginAsUser\XF\Repository;

use DC\LoginAsUser\Impersonation;
use XF\Entity\ConversationUser;

/**
 * It is Conversation, not DirectMessage: XF 2.3 renamed only the user-facing phrasing, and the sole
 * DirectMessage identifier in the core tree is an ACP action name.
 *
 * One consequence worth knowing rather than fixing: ReplierService marks the replier's own copy
 * read after they post, so with this suppressed a direct message the staff member answered while
 * impersonating stays bold-unread for the member. That is the honest outcome - the alternative lets
 * an administrator silently clear somebody's unread state.
 */
class ConversationRepository extends XFCP_ConversationRepository
{
	public function markUserConversationRead(ConversationUser $userConv, $newRead = null)
	{
		if (Impersonation::suppressesFor(Impersonation::SUPPRESS_CONVERSATIONS, (int) $userConv->owner_user_id))
		{
			return;
		}

		parent::markUserConversationRead($userConv, $newRead);
	}
}
