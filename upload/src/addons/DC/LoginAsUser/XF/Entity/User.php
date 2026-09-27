<?php

declare(strict_types=1);

namespace DC\LoginAsUser\XF\Entity;

use DC\LoginAsUser\Impersonation;
use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
	/**
	 * Can the visitor take over THIS account? Visitor-implicit, in the house style of canBan() and
	 * canWarn().
	 *
	 * The checks are ordered by cost, which matters because this runs once per row on the ACP user
	 * list: the visitor's own permission set is already loaded, the nested-session check is a static
	 * read, is_admin is a plain column, and only the immunity check touches the target's permission
	 * set - and XF\PermissionCache memoises that per permission-combination, so a page of twenty
	 * members costs one lookup per distinct combination rather than twenty.
	 */
	public function canLoginAs(&$error = null): bool
	{
		if (!$this->user_id)
		{
			return false;
		}

		$visitor = \XF::visitor();

		if (!$visitor->user_id || $visitor->user_id === $this->user_id)
		{
			return false;
		}

		if (!$visitor->canLoginAsUsers())
		{
			return false;
		}

		// Nested impersonation is blocked: you must come back to yourself first. Without this an
		// impersonated member who happens to hold the permission could chain onward, and the return
		// stack has no way to unwind more than one level.
		if (Impersonation::isActive())
		{
			$error = \XF::phrase('dcLoginAsUser_you_are_already_logged_in_as_another_member');
			return false;
		}

		// Administrator immunity is hard-coded rather than being a permission, because immunity that
		// can be granted away is not immunity. getIsSuperAdmin() short-circuits on is_admin, so for
		// an ordinary member neither branch touches the Admin relation.
		if ($this->is_admin || $this->is_super_admin)
		{
			$error = \XF::phrase('dcLoginAsUser_you_may_not_log_in_as_an_administrator');
			return false;
		}

		if ($this->isImmuneFromLoginAs())
		{
			$error = \XF::phrase('dcLoginAsUser_this_member_cannot_be_logged_in_as');
			return false;
		}

		return true;
	}

	/**
	 * The grantable half of immunity, for protecting moderators, VIPs or one individual without
	 * touching code.
	 *
	 * CAVEAT worth knowing rather than working around: XF resolves a user whose user_state is not
	 * 'valid' against the GUEST permission combination (User::getPermissionCombinationId()), so a
	 * banned, disabled or unconfirmed member evaluates guest permissions and will not appear immune
	 * unless the permission is granted to unregistered users. Those are exactly the accounts staff
	 * most need to inspect, so the interaction is benign - but do not read this as "immunity always
	 * holds regardless of account state".
	 */
	public function isImmuneFromLoginAs(): bool
	{
		return $this->hasPermission('dcLoginAsUser', 'immuneFromLoginAs');
	}

	/**
	 * The capability gate with no target. Drives the staff-bar trigger and every "should this entry
	 * point render at all" question.
	 */
	public function canLoginAsUsers(): bool
	{
		if (!$this->user_id)
		{
			return false;
		}

		return $this->hasPermission('dcLoginAsUser', 'login');
	}

	public static function getStructure(Structure $structure)
	{
		$structure = parent::getStructure($structure);

		return $structure;
	}
}
