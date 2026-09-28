<?php

namespace XenSupport\Staff\Admin\Controller;

use XF\Admin\Controller\AbstractController;

class Staff extends AbstractController
{
	public function actionIndex()
	{
		/** @var \XF\Entity\OptionGroup|null $group */
		$group = $this->em()->find('XF:OptionGroup', 'xenStaff');

		if ($group)
		{
			return $this->redirectPermanently($this->buildLink('options/groups', $group));
		}

		// Defensive fallback: option group missing (rebuild required) — go to options home
		return $this->redirectPermanently($this->buildLink('options'));
	}
}
