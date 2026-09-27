<?php

declare(strict_types=1);

namespace DC\LoginAsUser;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;
use XF\Session\StorageInterface;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;

	/**
	 * One row per impersonation. It is opened when a staff member switches in and closed when they
	 * come back, and it is the audit record either way - the same row answers "who is browsing as
	 * whom right now" and "who browsed as whom last March". Splitting live sessions from history
	 * would mean copying the row on close, which is two writes and one more way for the two halves
	 * to disagree about what happened.
	 *
	 * The usernames are stored alongside the ids on purpose. A log whose rows go blank when an
	 * account is deleted is at its least useful exactly when it is needed most, and the accounts
	 * most likely to be impersonated - spammers, members who asked to be erased - are also the
	 * ones most likely to be gone by the time anyone reads the log.
	 */
	public function installStep1(): void
	{
		$this->schemaManager()->createTable('xf_dcLoginAsUser_session', function (Create $table)
		{
			$table->checkExists(true);

			$table->addColumn('session_id', 'int')->autoIncrement();

			$table->addColumn('actor_user_id', 'int')->setDefault(0);
			$table->addColumn('actor_username', 'varchar', 50)->setDefault('');
			$table->addColumn('target_user_id', 'int')->setDefault(0);
			$table->addColumn('target_username', 'varchar', 50)->setDefault('');

			// Optional. An empty reason is a legitimate answer, not a validation failure, so this
			// carries no NOT NULL-with-no-default trap and the list template renders a hint for it.
			$table->addColumn('reason', 'varchar', 255)->setDefault('');

			// Named ip_address and typed varbinary(16) to match xf_moderator_log exactly, so the
			// |ip filter, XF\Util\Ip and every generic IP tool work here with no special casing.
			$table->addColumn('ip_address', 'varbinary', 16)->setDefault('');

			$table->addColumn('start_date', 'int')->setDefault(0);

			// 0 means open. A separate boolean plus a date can contradict each other; a date alone
			// cannot, and every query that cares reads it the same way.
			$table->addColumn('end_date', 'int')->setDefault(0);

			// Snapshotted from the option at open, not recomputed. Changing the maximum duration
			// must not silently extend or cut short a session that is already running, and the
			// per-request expiry check must not have to read options.
			$table->addColumn('expiry_date', 'int')->setDefault(0);

			// '' while open, then one of the END_* constants on the Session entity. varchar rather
			// than enum because this add-on ships to other boards: a further end type later is a
			// code change here and an ALTER TABLE on every installation there.
			$table->addColumn('end_type', 'varchar', 25)->setDefault('');
			$table->addColumn('ended_by_user_id', 'int')->setDefault(0);

			// The actor's live public session id, matching xf_session.session_id varbinary(32).
			// Wiped to '' by every close path - see SessionRepository::closeSession(). A durable
			// table full of live session ids is a hijack primitive; a table where only the handful
			// of currently open rows carry one, and where every use goes through
			// StorageInterface::deleteSession(), is not.
			$table->addColumn('actor_session_id', 'varbinary', 32)->setDefault('');

			// The xf_admin_log row this event also wrote, so the two trails can be reconciled.
			$table->addColumn('admin_log_id', 'int')->setDefault(0);

			// Which footprint suppressions were in force for this session. The option can be
			// changed; this row cannot, so this is the only way to answer "was this session
			// invisible?" after the fact.
			$table->addColumn('suppressed', 'blob');

			$table->addKey('start_date');
			$table->addKey(['actor_user_id', 'end_date'], 'actor_user_id_end_date');
			$table->addKey(['actor_user_id', 'start_date'], 'actor_user_id_start_date');
			$table->addKey(['target_user_id', 'start_date'], 'target_user_id_start_date');
			$table->addKey(['end_date', 'expiry_date'], 'end_date_expiry_date');
		});
	}

	/**
	 * Kill every live impersonation before the code that could end it goes away.
	 *
	 * Uninstalling removes the listener that swaps the visitor back, but it does not touch their
	 * session, which still names the target as the logged-in user. Without this step a staff member
	 * who happened to be mid-impersonation stays logged in as someone else permanently, with
	 * nothing left on the board that knows about it or can undo it.
	 *
	 * Deliberately before the drop: the ids live in the table being dropped.
	 */
	public function uninstallStep1(): void
	{
		if (!$this->schemaManager()->tableExists('xf_dcLoginAsUser_session'))
		{
			return;
		}

		/** @var StorageInterface $storage */
		$storage = $this->app()->container('session.public.storage');

		$sessionIds = $this->db()->fetchAllColumn(
			"SELECT actor_session_id
				FROM xf_dcLoginAsUser_session
				WHERE end_date = 0 AND actor_session_id <> ''"
		);

		foreach ($sessionIds AS $sessionId)
		{
			$storage->deleteSession($sessionId);
		}
	}

	public function uninstallStep2(): void
	{
		$this->schemaManager()->dropTable('xf_dcLoginAsUser_session');
	}
}
