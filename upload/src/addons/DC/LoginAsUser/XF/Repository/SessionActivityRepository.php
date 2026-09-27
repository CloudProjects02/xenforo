<?php

declare(strict_types=1);

namespace DC\LoginAsUser\XF\Repository;

use DC\LoginAsUser\Impersonation;

/**
 * This single row IS both the "currently online" list and last_activity - XF\Entity\User::
 * getLastActivity() returns $this->Activity->view_date whenever the row exists. Freezing the write
 * therefore freezes both, which is why the two ACP sub-options are documented as related rather
 * than independent.
 */
class SessionActivityRepository extends XFCP_SessionActivityRepository
{
	public function updateSessionActivity($userId, $ip, $controller, $action, array $params, $viewState, $robotKey)
	{
		if (Impersonation::suppressesFor(Impersonation::SUPPRESS_ONLINE, (int) $userId))
		{
			return;
		}

		parent::updateSessionActivity($userId, $ip, $controller, $action, $params, $viewState, $robotKey);
	}
}
