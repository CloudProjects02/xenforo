<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Pub\View\Account;

use XF\Repository\UserTfaTrustedRepository;

class TwoStep extends XFCP_TwoStep
{
	public function renderHtml()
	{
		if (is_callable(parent::class . '::renderHtml'))
		{
			/** @noinspection PhpUndefinedMethodInspection */
			parent::renderHtml();
		}

		$tfaTrustRepo = \XF::app()->repository(UserTfaTrustedRepository::class);

		$this->params['otherDevices'] = $tfaTrustRepo->getUserTrustedRecords(
			\XF::visitor()->user_id,
			(is_null($this->params['currentTrustRecord']) ? null : $this->params['currentTrustRecord']['trusted_key'])
		);
	}
}