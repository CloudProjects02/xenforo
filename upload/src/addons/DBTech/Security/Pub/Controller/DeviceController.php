<?php

namespace DBTech\Security\Pub\Controller;

use DBTech\Security\XF\Entity\User;
use XF\Entity\UserRemember;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;

class DeviceController extends AbstractController
{
	/**
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionForget(): AbstractReply
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();

		if ($visitor->user_id)
		{
			$record = $this->assertUserRememberExists($this->filter('remember_id', 'uint'));
			if ($record->user_id == $visitor->user_id)
			{
				$record->delete();
			}
		}

		return $this->redirect($this->buildLink('account/security'));
	}

	/**
	 * @param null|int $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return UserRemember
	 * @throws Exception
	 */
	protected function assertUserRememberExists(?int $id, array $with = [], ?string $phraseKey = null): UserRemember
	{
		return $this->assertRecordExists(UserRemember::class, $id, $with, $phraseKey);
	}
}