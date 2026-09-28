<?php

namespace XenSupport\Staff\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Pro extends AbstractController
{
	// ============== Contact form ==============

	/**
	 * GET  /staff/contact/{user_id}/  → form
	 * POST /staff/contact/{user_id}/  → submit
	 */
	public function actionContact(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		if (empty(\XF::options()->xenStaffContactEnabled))
		{
			return $this->error(\XF::phrase('xen_staff_contact_disabled'));
		}

		$toUser = $this->assertStaffExists((int) $params->user_id);
		$visitor = \XF::visitor();

		if ($this->isPost())
		{
			$isAnon = (bool) $this->filter('anonymous', 'bool');

			if ($visitor->user_id)
			{
				// Registered members need the send permission; sending anonymously
				// additionally requires the anonymous permission.
				if (!$visitor->hasPermission('xenStaff', 'contactSend'))
				{
					return $this->noPermission();
				}
				if ($isAnon && !$visitor->hasPermission('xenStaff', 'contactAnonymous'))
				{
					return $this->noPermission();
				}
			}
			else
			{
				// Guests can only ever send anonymously (gated by the target's
				// allow_anonymous_contact flag + the hourly rate limit).
				$isAnon = true;
			}

			$subject = (string) $this->filter('subject', 'str');
			$message = (string) $this->filter('message', 'str');
			$fromEmail = (string) $this->filter('from_email', 'str');

			/** @var \XenSupport\Staff\Repository\Pro $repo */
			$repo = $this->repository('XenSupport\Staff:Pro');
			$result = $repo->submitContact(
				$toUser,
				$subject,
				$message,
				$isAnon,
				$fromEmail,
				$visitor->user_id ? $visitor : null
			);
			if (empty($result['success']))
			{
				return $this->error($result['error']);
			}

			return $this->redirect(
				$this->buildLink('staff'),
				\XF::phrase('xen_staff_contact_success', ['username' => $toUser->username])
			);
		}

		return $this->view(
			'XenSupport\Staff:Pro\Contact',
			'xs_staff_contact',
			['toUser' => $toUser]
		);
	}

	// ============== Office hours ==============

	/**
	 * Edit current visitor's office hours.
	 */
	public function actionHours(): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertRegistrationRequired();
		$visitor = \XF::visitor();
		if (!$visitor->hasPermission('xenStaff', 'editProfile'))
		{
			return $this->noPermission();
		}

		/** @var \XenSupport\Staff\Repository\Pro $repo */
		$repo = $this->repository('XenSupport\Staff:Pro');

		if ($this->isPost())
		{
			$slots = (array) $this->filter('slots', 'array', []);
			$cleaned = [];
			foreach ($slots AS $s)
			{
				$cleaned[] = [
					'day_of_week' => (int) ($s['day_of_week'] ?? 0),
					'start_time'  => (int) ($s['start_time'] ?? 0),
					'end_time'    => (int) ($s['end_time'] ?? 0),
					'note'        => (string) ($s['note'] ?? ''),
				];
			}
			$repo->replaceHoursForUser($visitor->user_id, $cleaned);
			return $this->redirect($this->buildLink('staff/hours'), \XF::phrase('changes_saved'));
		}

		$hours = $repo->getHoursForUser($visitor->user_id);
		return $this->view('XenSupport\Staff:Pro\Hours', 'xs_staff_hours_edit', [
			'hours' => $hours,
		]);
	}

	// ============== Rich bio ==============

	public function actionBio(): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertRegistrationRequired();
		$visitor = \XF::visitor();
		if (!$visitor->hasPermission('xenStaff', 'editProfile'))
		{
			return $this->noPermission();
		}

		/** @var \XenSupport\Staff\Entity\Profile $profile */
		$profile = $this->em()->find('XenSupport\Staff:Profile', $visitor->user_id);
		if (!$profile)
		{
			$profile = $this->em()->create('XenSupport\Staff:Profile');
			$profile->user_id = $visitor->user_id;
		}

		if ($this->isPost())
		{
			$maxLen = (int) (\XF::options()->xenStaffRichBioMaxLength ?? 5000);
			$bio = (string) $this->filter('rich_bio', 'str');
			$profile->rich_bio = mb_substr($bio, 0, $maxLen);
			$profile->timezone = (string) $this->filter('timezone', 'str', 'UTC');
			$profile->allow_contact = (bool) $this->filter('allow_contact', 'bool');
			$profile->allow_anonymous_contact = (bool) $this->filter('allow_anonymous_contact', 'bool');
			$profile->online_override = (string) $this->filter('online_override', 'str', 'auto');
			$profile->save();
			return $this->redirect($this->buildLink('staff/bio'), \XF::phrase('changes_saved'));
		}

		return $this->view('XenSupport\Staff:Pro\Bio', 'xs_staff_bio_edit', [
			'profile' => $profile,
		]);
	}

	// ============== Stats ==============

	public function actionStats(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		if (empty(\XF::options()->xenStaffShowStats))
		{
			return $this->notFound();
		}
		$visitor = \XF::visitor();
		if (!$visitor->hasPermission('xenStaff', 'viewStats'))
		{
			return $this->noPermission();
		}

		$user = $this->assertStaffExists((int) $params->user_id);

		/** @var \XenSupport\Staff\Repository\Pro $repo */
		$repo = $this->repository('XenSupport\Staff:Pro');
		$daily = $repo->getDailyThanksFor($user->user_id, 30);

		/** @var \XenSupport\Staff\Entity\Profile|null $profile */
		$profile = $this->em()->find('XenSupport\Staff:Profile', $user->user_id);

		return $this->view('XenSupport\Staff:Pro\Stats', 'xs_staff_stats', [
			'user'    => $user,
			'profile' => $profile,
			'daily'   => $daily,
		]);
	}

	// ============== Inbox + message ==============

	public function actionInbox(): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertRegistrationRequired();
		$visitor = \XF::visitor();

		$messages = $this->finder('XenSupport\Staff:Contact')
			->with('FromUser')
			->where('to_user_id', $visitor->user_id)
			->order('created_date', 'DESC')
			->limit(100)
			->fetch();

		return $this->view('XenSupport\Staff:Pro\Inbox', 'xs_staff_inbox', [
			'messages' => $messages,
		]);
	}

	public function actionMessage(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertRegistrationRequired();
		$visitor = \XF::visitor();

		/** @var \XenSupport\Staff\Entity\Contact|null $msg */
		$msg = $this->em()->find('XenSupport\Staff:Contact', (int) $params->contact_id);
		if (!$msg || $msg->to_user_id != $visitor->user_id)
		{
			return $this->notFound();
		}

		if ($this->isPost())
		{
			$action = (string) $this->filter('action', 'str');
			if ($action === 'replied')
			{
				/** @var \XenSupport\Staff\Repository\Pro $repo */
				$repo = $this->repository('XenSupport\Staff:Pro');
				$repo->markReplied($msg, $visitor);
			}
			elseif ($action === 'close')
			{
				$msg->status = 'closed';
				$msg->save();
			}
			return $this->redirect($this->buildLink('staff/inbox'));
		}

		return $this->view('XenSupport\Staff:Pro\Message', 'xs_staff_message', [
			'msg' => $msg,
		]);
	}

	// ============== Helpers ==============

	protected function assertStaffExists(int $userId): \XF\Entity\User
	{
		/** @var \XF\Entity\User|null $user */
		$user = $this->em()->find('XF:User', $userId);
		if (!$user || !$user->user_id)
		{
			throw $this->exception($this->notFound());
		}
		return $user;
	}
}
