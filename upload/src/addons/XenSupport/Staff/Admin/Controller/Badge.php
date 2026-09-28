<?php

namespace XenSupport\Staff\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Badge extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->setSectionContext('xenStaffBadges');
	}

	public function actionIndex()
	{
		$badges = $this->finder('XenSupport\Staff:Badge')
			->order([['display_order', 'ASC'], ['title', 'ASC']])
			->fetch();
		return $this->view(
			'XenSupport\Staff:Admin\BadgeList',
			'xs_staff_admin_badge_list',
			['badges' => $badges]
		);
	}

	/**
	 * Assign a badge to each staff member. GET renders the list of staff with a
	 * per-member badge dropdown; POST persists the whole map at once.
	 */
	public function actionAssign()
	{
		/** @var \XenSupport\Staff\Repository\Staff $staffRepo */
		$staffRepo = $this->repository('XenSupport\Staff:Staff');
		/** @var \XenSupport\Staff\Repository\Pro $proRepo */
		$proRepo = $this->repository('XenSupport\Staff:Pro');

		if ($this->isPost())
		{
			$map = $this->filter('badges', 'array');
			foreach ($map AS $userId => $badgeId)
			{
				$proRepo->assignBadge((int) $userId, (int) $badgeId);
			}
			return $this->redirect(
				$this->buildLink('staff-badges/assign'),
				\XF::phrase('xen_staff_admin_settings_saved')
			);
		}

		$badges = $this->finder('XenSupport\Staff:Badge')
			->order([['display_order', 'ASC'], ['title', 'ASC']])
			->fetch();

		$staff = [];
		foreach ($staffRepo->findStaffMembers()['groups'] AS $g)
		{
			foreach ($g['users'] AS $entry)
			{
				if (!empty($entry['user']))
				{
					$staff[] = $entry;
				}
			}
		}

		return $this->view(
			'XenSupport\Staff:Admin\BadgeAssign',
			'xs_staff_admin_badge_assign',
			['staff' => $staff, 'badges' => $badges]
		);
	}

	public function actionAdd()
	{
		$badge = $this->em()->create('XenSupport\Staff:Badge');
		return $this->badgeAddEdit($badge);
	}

	public function actionEdit(ParameterBag $params)
	{
		$badge = $this->assertBadgeExists($params->badge_id);
		return $this->badgeAddEdit($badge);
	}

	protected function badgeAddEdit(\XenSupport\Staff\Entity\Badge $badge)
	{
		return $this->view(
			'XenSupport\Staff:Admin\BadgeEdit',
			'xs_staff_admin_badge_edit',
			['badge' => $badge]
		);
	}

	public function actionSave(ParameterBag $params)
	{
		$this->assertPostOnly();
		$badge = $params->badge_id
			? $this->assertBadgeExists($params->badge_id)
			: $this->em()->create('XenSupport\Staff:Badge');

		$input = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'icon' => 'str',
			'image_url' => 'str',
			'color' => 'str',
			'display_order' => 'uint',
		]);
		$badge->bulkSet($input);
		$badge->save();

		return $this->redirect($this->buildLink('staff-badges'));
	}

	public function actionDelete(ParameterBag $params)
	{
		$badge = $this->assertBadgeExists($params->badge_id);
		if ($this->isPost())
		{
			// Detach from any assigned profile, then delete
			\XF::db()->update('xf_xs_staff_profile', ['badge_id' => 0], 'badge_id = ?', [$badge->badge_id]);
			$badge->delete();
			return $this->redirect($this->buildLink('staff-badges'));
		}
		return $this->view(
			'XenSupport\Staff:Admin\BadgeDelete',
			'xs_staff_admin_badge_delete',
			['badge' => $badge]
		);
	}

	protected function assertBadgeExists($id): \XenSupport\Staff\Entity\Badge
	{
		/** @var \XenSupport\Staff\Entity\Badge|null $b */
		$b = $this->em()->find('XenSupport\Staff:Badge', (int) $id);
		if (!$b) { throw $this->exception($this->notFound()); }
		return $b;
	}
}
