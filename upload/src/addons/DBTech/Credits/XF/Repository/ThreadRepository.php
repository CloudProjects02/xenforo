<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Repository;

use DBTech\Credits\Repository\EventTriggerRepository;
use DBTech\Credits\XF\Entity\User;
use XF\Entity\Thread;

/**
 * @extends \XF\Repository\ThreadRepository
 */
class ThreadRepository extends XFCP_ThreadRepository
{
	/**
	 * @param Thread $thread
	 *
	 * @throws \Exception
	 */
	public function logThreadView(Thread $thread)
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		$eventTriggerRepo->getHandler('read')
			->apply($thread->thread_id, [
				'node_id' => $thread->node_id,
				'owner_id' => $thread->user_id,
				'content_type' => 'thread',
				'content_id' => $thread->thread_id,
			], $visitor)
		;

		if ($visitor->user_id != $thread->user_id)
		{
			$eventTriggerRepo->getHandler('view')
				->apply($thread->thread_id, [
					'node_id' => $thread->node_id,
					'source_user_id' => $visitor->user_id,
					'content_type' => 'thread',
					'content_id' => $thread->thread_id,
				], $thread->User)
			;
		}

		parent::logThreadView($thread);
	}
}