<?php

namespace XenSupport\Staff\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;
use XenSupport\Staff\Repository\Staff as StaffRepo;

class Staff extends AbstractController
{
	public function actionIndex()
	{
		/** @var StaffRepo $repo */
		$repo = $this->repository('XenSupport\Staff:Staff');
		$data = $repo->findStaffMembers();

		// Collect candidate user_ids for live stats + top staff (Pro features
		// — silently no-op on Free where the Pro columns may not exist yet).
		$staffIds = [];
		foreach ($data['groups'] AS $g)
		{
			foreach ($g['users'] AS $entry)
			{
				$staffIds[] = $entry['user']->user_id;
			}
		}

		$liveStats = null;
		$topStaff  = null;
		try
		{
			$liveStats = $repo->getLiveStats($staffIds);
			$topStaff  = $repo->getTopStaff($staffIds);
		}
		catch (\Throwable $e) { /* Free install: Pro columns missing */ }

		$viewParams = [
			'groups'    => $data['groups'],
			'total'     => $data['total'],
			'liveStats' => $liveStats,
			'topStaff'  => $topStaff,
		];
		return $this->view('XenSupport\Staff:Staff\Index', 'xs_staff_index', $viewParams);
	}

	public function actionThanks(ParameterBag $params)
	{
		$this->assertPostOnly();

		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return $this->error(\XF::phrase('xen_staff_thanks_guest'));
		}
		if (!$visitor->hasPermission('xenStaff', 'thank'))
		{
			return $this->noPermission();
		}

		$targetUserId = (int) $params->user_id;
		if (!$targetUserId)
		{
			return $this->notFound();
		}
		if ($targetUserId === $visitor->user_id)
		{
			return $this->error(\XF::phrase('xen_staff_thanks_self'));
		}

		/** @var StaffRepo $repo */
		$repo = $this->repository('XenSupport\Staff:Staff');

		$targetUser = $this->em()->find('XF:User', $targetUserId);
		if (!$targetUser || !$repo->isStaff($targetUser))
		{
			return $this->notFound();
		}

		if ($repo->hasThanked($visitor->user_id, $targetUserId))
		{
			return $this->error(\XF::phrase('xen_staff_thanks_already'));
		}

		$repo->recordThank($visitor->user_id, $targetUserId);

		$newCount = $repo->getThanksCount($targetUserId);

		$reply = $this->redirect($this->buildLink('staff'), \XF::phrase('xen_staff_thanks_given'));
		$reply->setJsonParam('thanks_count', $newCount);
		$reply->setJsonParam('user_id', $targetUserId);
		return $reply;
	}
}
