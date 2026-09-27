<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Pub\Controller;

use DBTech\Credits\Repository\EventTriggerRepository;
use DBTech\Credits\XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception as ReplyException;
use XF\Mvc\Reply\View;

/**
 * @extends \XF\Pub\Controller\MemberController
 */
class MemberController extends XFCP_MemberController
{
	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws ReplyException
	 * @throws \Exception
	 */
	public function actionView(ParameterBag $params)
	{
		$previous = parent::actionView($params);

		if ($previous instanceof View)
		{
			/** @var User $user */
			$user = $this->assertViewableUser($params->user_id);

			/** @var User $visitor */
			$visitor = \XF::visitor();

			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);
			$eventTriggerRepo->getHandler('profile')
				->apply($user->user_id, [
					'source_user_id' => $user->user_id,
					'content_type' => 'user',
					'content_id' => $user->user_id,
				], $visitor)
			;

			if ($visitor->user_id != $user->user_id)
			{
				$eventTriggerRepo->getHandler('visit')
					->apply($visitor->user_id, [
						'source_user_id' => $visitor->user_id,
						'content_type' => 'user',
						'content_id' => $user->user_id,
					], $user)
				;
			}
		}

		return $previous;
	}
}