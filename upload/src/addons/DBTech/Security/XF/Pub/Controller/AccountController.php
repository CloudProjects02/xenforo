<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Security\XF\Pub\Controller;

use XF\Db\Exception;
use XF\Mvc\Reply\AbstractReply;
use XF\Repository\UserTfaTrustedRepository;

/**
 * @extends \XF\Pub\Controller\AccountController
 */
class AccountController extends XFCP_AccountController
{
	/**
	 * @return AbstractReply
	 * @throws Exception
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionTwoStepTrustedDisableById()
	{
		$this->assertPostOnly();
		$this->assertTwoStepPasswordVerified();

		/** @var \DBTech\Security\XF\Repository\UserTfaTrustedRepository $tfaTrustRepo */
		$tfaTrustRepo = \XF::app()->repository(UserTfaTrustedRepository::class);

		$userId = \XF::visitor()->user_id;

		$tfaTrustRepo->untrustDeviceById($userId, $this->filter('trusted_id', 'uint'));

		return $this->redirect($this->buildLink('account/two-step'));
	}
}