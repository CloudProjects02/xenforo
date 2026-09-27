<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\User;

use DBTech\Credits\Repository\EventTriggerRepository;
use XF\PrintableException;

/**
 * @extends \XF\Service\User\RegistrationCompleteService
 */
class RegistrationCompleteService extends XFCP_RegistrationCompleteService
{
	/**
	 * @throws PrintableException
	 * @throws \Exception
	 */
	public function triggerCompletionActions()
	{
		parent::triggerCompletionActions();

		$user = $this->user;

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		$eventTriggerRepo->getHandler('registration')
			->apply($user->user_id, [
				'source_user_id' => $user->user_id,
				'content_type'   => 'user',
				'content_id'     => $user->user_id,
			], $user)
		;
	}
}