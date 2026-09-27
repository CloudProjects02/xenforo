<?php

declare(strict_types=1);

namespace DC\LoginAsUser\XF\Repository;

use DC\LoginAsUser\Impersonation;
use XF\Entity\User;

/**
 * The repository's seven public entry points all bottom out in these two protected funnels, so
 * guarding the pair covers all seven with no ambiguity about which one a given caller took.
 *
 * The signatures are typed and return void; they must match core exactly or PHP fatals at class
 * load. Both funnels already early-return on isPrefetch(), so a bare return is idiomatic here.
 */
class UserAlertRepository extends XFCP_UserAlertRepository
{
	protected function markAllUserAlertsViewedOrRead(
		User $user,
		?int $viewDate = null,
		bool $markRead = false
	): void
	{
		if (Impersonation::suppressesFor(Impersonation::SUPPRESS_ALERTS, (int) $user->user_id))
		{
			return;
		}

		parent::markAllUserAlertsViewedOrRead($user, $viewDate, $markRead);
	}

	protected function markUserAlertsViewedOrRead(
		User $user,
		array $alertIds,
		?int $viewDate = null,
		bool $markRead = false,
		bool $updateAlertEntities = false
	): void
	{
		if (Impersonation::suppressesFor(Impersonation::SUPPRESS_ALERTS, (int) $user->user_id))
		{
			return;
		}

		parent::markUserAlertsViewedOrRead($user, $alertIds, $viewDate, $markRead, $updateAlertEntities);
	}
}
