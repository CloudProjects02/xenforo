<?php

namespace XenSupport\Staff\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XenSupport\Staff\Repository\Staff as StaffRepo;

class Account extends AbstractController
{
	public function actionIndex()
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return $this->redirect($this->buildLink('login'));
		}

		/** @var StaffRepo $repo */
		$repo = $this->repository('XenSupport\Staff:Staff');

		if (!$repo->isStaff($visitor) || !$visitor->hasPermission('xenStaff', 'editProfile'))
		{
			return $this->error(\XF::phrase('xen_staff_no_permission_profile'));
		}

		$profile = $repo->getOrCreateProfile($visitor->user_id);

		if ($this->isPost())
		{
			$input = $this->filter([
				'quote'             => 'str',
				'social_discord'    => 'str',
				'social_twitter'    => 'str',
				'social_twitch'     => 'str',
				'social_youtube'    => 'str',
				'social_instagram'  => 'str',
				'social_github'     => 'str',
				'social_website'    => 'str',
				'accent_color'      => 'str',
			]);

			foreach ($input AS $key => $val)
			{
				$profile->{$key} = trim((string) $val);
			}

			$profile->save();

			return $this->redirect($this->buildLink('account/xs-staff-profile'), \XF::phrase('xen_staff_profile_saved'));
		}

		$viewParams = [
			'profile' => $profile,
		];
		return $this->view('XenSupport\Staff:Account\Profile', 'account_xs_staff_profile', $viewParams);
	}
}
