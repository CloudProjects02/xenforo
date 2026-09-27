<?php

declare(strict_types=1);

namespace DC\LoginAsUser\Cron;

use DC\LoginAsUser\Repository\SessionRepository;

class CleanUp
{
	/**
	 * Every fifteen minutes. The impersonated session's own per-request check ends a session the
	 * moment its owner touches the board again, so this only ever catches the sessions nobody came
	 * back to - a closed laptop, a killed tab. Those are exactly the rows that would otherwise sit
	 * on the ACP active list claiming someone is browsing as a member when they are not, which is
	 * the failure that makes an active-sessions page worse than no page at all.
	 */
	public static function closeStaleSessions(): void
	{
		\XF::repository(SessionRepository::class)->closeStaleSessions();
	}

	/**
	 * Daily.
	 */
	public static function pruneLogs(): void
	{
		\XF::repository(SessionRepository::class)->pruneLogs();
	}
}
