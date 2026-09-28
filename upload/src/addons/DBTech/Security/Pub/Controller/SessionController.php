<?php

namespace DBTech\Security\Pub\Controller;

use DBTech\Security\Entity\Session;
use DBTech\Security\XF\Entity\User;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;

class SessionController extends AbstractController
{
	/**
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionDelete(): AbstractReply
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();

		if ($visitor->user_id)
		{
			$record = $this->assertSessionExists($this->filter('session_id', 'str'));
			if ($record->user_id == $visitor->user_id)
			{
				$record->delete();
			}
		}

		return $this->redirect($this->buildLink('account/security'));
	}

	/**
	 * @param string|null $id
	 * @param array $with
	 * @param string|null $phraseKey
	 *
	 * @return Session
	 * @throws Exception
	 */
	protected function assertSessionExists(?string $id, array $with = [], ?string $phraseKey = null): Session
	{
		return $this->assertRecordExists(Session::class, $id, $with, $phraseKey);
	}
}