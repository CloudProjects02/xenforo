<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XF\Service\Post;

use DBTech\Credits\Entity\Event;
use DBTech\Credits\EventTrigger\PostHandler;
use DBTech\Credits\EventTrigger\ReplyHandler;
use DBTech\Credits\EventTrigger\RevivalHandler;
use DBTech\Credits\EventTrigger\ThreadHandler;
use DBTech\Credits\Repository\EventTriggerRepository;
use XF\Entity\Thread;
use XF\PrintableException;

/**
 * @extends \XF\Service\Post\PreparerService
 */
class PreparerService extends XFCP_PreparerService
{
	protected bool $applyDragonByteCreditsEvents = true;


	/**
	 * @param $apply
	 */
	public function setApplyDragonByteCreditsEvents($apply)
	{
		$this->applyDragonByteCreditsEvents = (bool) $apply;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function validateDragonByteCreditsEventsBeforeInsert()
	{
		if (!$this->applyDragonByteCreditsEvents)
		{
			return [];
		}

		$post = $this->post;
		$thread = $post->Thread;

		if (!$post->user_id)
		{
			return [];
		}

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		$errors = [];

		try
		{
			if ($post->isFirstPost())
			{
				if ($thread->discussion_state == 'visible')
				{
					/** @var ThreadHandler $event */
					$event = $eventTriggerRepo->getHandler('thread');

					// Apply the new event
					$event->testApply([
						'multiplier' => $post->message,
						'node_id'    => $thread->node_id,
					], $post->User);
				}
			}
			else
			{
				if ($post->message_state == 'visible')
				{
					/** @var PostHandler $event */
					$event = $eventTriggerRepo->getHandler('post');

					// Apply the new event
					$event->testApply([
						'node_id'      => $thread->node_id,
						'thread_id'    => $post->thread_id,
						'multiplier'   => $post->message,
						'owner_id'     => $thread->user_id,
					], $post->User);
				}

				if ($post->user_id != $thread->user_id)
				{
					if ($post->message_state == 'visible')
					{
						/** @var ReplyHandler $event */
						$event = $eventTriggerRepo->getHandler('reply');

						// Apply the new event
						$event->testApply([
							'node_id'        => $thread->node_id,
							'thread_id'      => $post->thread_id,
							'source_user_id' => $post->user_id,
						], $thread->User);
					}
				}

				/** @var RevivalHandler $event */
				$event = $eventTriggerRepo->getHandler('revival');

				// Apply the new event
				$event->testApply([
					'last_post_date' => $thread->last_post_date,
				], $post->User);
			}
		}
		catch (PrintableException $e)
		{
			$errors = (array) $e->getMessages();
		}

		return $errors;
	}

	/**
	 * @return array
	 * @throws \Exception
	 */
	public function validateDragonByteCreditsEventsBeforeUpdate()
	{
		if (
			!$this->post->isChanged('thread_id')
			&& !$this->post->isChanged('user_id')
			&& !$this->post->isChanged('message')
		)
		{
			// Ensure we cancel any update events
			$this->setApplyDragonByteCreditsEvents(false);
			return [];
		}

		if (!$this->applyDragonByteCreditsEvents)
		{
			return [];
		}

		$post = $this->post;
		$thread = $post->Thread;
		$previousThread = $post->isChanged('thread_id')
			? \XF::app()->em()->find(Thread::class, $post->getPreviousValue('thread_id'))
			: $post->Thread;

		if (!$post->user_id)
		{
			return [];
		}

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		if ($post->isFirstPost())
		{
			/** @var ThreadHandler $event */
			$event = $eventTriggerRepo->getHandler('thread');
		}
		else
		{
			/** @var PostHandler $event */
			$event = $eventTriggerRepo->getHandler('post');
		}

		// Back up the current events
		$events = $event->getEvents();

		// Filter out events that don't use the "Amount per X" nor "Negation amount per X"
		$filteredEvents = $events
			->filter(function (Event $event)
			{
				if (!$event->mult_add && !$event->mult_sub)
				{
					return null;
				}

				return $event;
			})
		;

		// Set them back after filtering the useless ones
		$event->setEvents($filteredEvents);

		$errors = [];

		try
		{
			if ($post->isFirstPost())
			{
				if (
					$post->isChanged('thread_id')
					|| $post->isChanged('user_id')
					|| $post->isChanged('message')
				)
				{
					// Undo the previous event
					$event->testUndo([
						'multiplier' => $post->getPreviousValue('message'),
						'node_id'    => $previousThread->getPreviousValue('node_id'),
					], $post->User);

					// Apply the new event
					$event->testApply([
						'multiplier' => $post->message,
						'node_id'    => $thread->node_id,
					], $post->User);
				}
			}
			else
			{
				if (
					$post->isChanged('thread_id')
					|| $post->isChanged('user_id')
					|| $post->isChanged('message')
				)
				{
					// Undo the previous event
					$event->testUndo([
						'node_id'    => $previousThread->node_id,
						'thread_id'  => $previousThread->thread_id,
						'multiplier' => $post->getPreviousValue('message'),
						'owner_id'   => $previousThread->user_id,
					], $post->User);

					// Apply the new event
					$event->testApply([
						'node_id'    => $thread->node_id,
						'thread_id'  => $post->thread_id,
						'multiplier' => $post->message,
						'owner_id'   => $thread->user_id,
					], $post->User);
				}
				// END POST EVENT
			}
		}
		catch (PrintableException $e)
		{
			$errors = (array) $e->getMessages();
		}

		// Revert from backup
		$event->setEvents($events);

		return $errors;
	}

	/**
	 * @throws \Exception
	 */
	public function afterInsert()
	{
		parent::afterInsert();

		if (!$this->applyDragonByteCreditsEvents)
		{
			return;
		}

		$post = $this->post;
		$thread = $post->Thread;

		if (!$post->user_id)
		{
			return;
		}

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		if ($post->isFirstPost())
		{
			if ($thread->discussion_state == 'visible')
			{
				/** @var ThreadHandler $event */
				$event = $eventTriggerRepo->getHandler('thread');

				// Apply the new event
				$event->apply($post->thread_id, [
					'multiplier'   => $post->message,
					'node_id'      => $thread->node_id,
					'content_type' => 'thread',
					'content_id'   => $post->thread_id,
				], $post->User);
			}
		}
		else
		{
			if ($post->message_state == 'visible')
			{
				/** @var PostHandler $event */
				$event = $eventTriggerRepo->getHandler('post');

				// Apply the new event
				$event->apply($post->post_id, [
					'node_id'      => $thread->node_id,
					'thread_id'    => $post->thread_id,
					'multiplier'   => $post->message,
					'owner_id'     => $thread->user_id,
					'content_type' => 'post',
					'content_id'   => $post->post_id,
				], $post->User);
			}

			if ($post->user_id != $thread->user_id)
			{
				if ($post->message_state == 'visible')
				{
					/** @var ReplyHandler $event */
					$event = $eventTriggerRepo->getHandler('reply');

					// Apply the new event
					$event->apply($post->post_id, [
						'node_id'        => $thread->node_id,
						'thread_id'      => $post->thread_id,
						'source_user_id' => $post->user_id,
						'content_type'   => 'post',
						'content_id'     => $post->post_id,
					], $thread->User);
				}
			}

			/** @var RevivalHandler $event */
			$event = $eventTriggerRepo->getHandler('revival');

			// Apply the new event
			$event->apply($post->post_id, [
				'last_post_date' => $thread->getPreviousValue('last_post_date'),
				'content_type'   => 'post',
				'content_id'     => $post->post_id,
			], $post->User);
		}
	}

	/**
	 * @throws \Exception
	 */
	public function afterUpdate()
	{
		parent::afterUpdate();

		if (!$this->applyDragonByteCreditsEvents)
		{
			return;
		}

		$post = $this->post;
		$thread = $post->Thread;
		$previousThread = $post->isChanged('thread_id')
			? \XF::app()->em()->find(Thread::class, $post->getPreviousValue('thread_id'))
			: $post->Thread;

		if (!$post->user_id)
		{
			return;
		}

		$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

		if ($post->isFirstPost())
		{
			/** @var ThreadHandler $event */
			$event = $eventTriggerRepo->getHandler('thread');
		}
		else
		{
			/** @var PostHandler $event */
			$event = $eventTriggerRepo->getHandler('post');
		}

		// Back up the current events
		$events = $event->getEvents();

		// Filter out events that don't use the "Amount per X" nor "Negation amount per X"
		$filteredEvents = $events
			->filter(function (Event $event)
			{
				if (!$event->mult_add && !$event->mult_sub)
				{
					return null;
				}

				return $event;
			})
		;

		// Set them back after filtering the useless ones
		$event->setEvents($filteredEvents);

		if ($post->isFirstPost())
		{
			// Undo the previous event
			$event->undo($previousThread->thread_id, [
				'multiplier'   => $post->getPreviousValue('message'),
				'node_id'      => $previousThread->getPreviousValue('node_id'),
				'content_type' => 'thread',
				'content_id'   => $previousThread->thread_id,
			], $post->User);

			// Apply the new event
			$event->apply($post->thread_id, [
				'multiplier'   => $post->message,
				'node_id'      => $thread->node_id,
				'content_type' => 'thread',
				'content_id'   => $previousThread->thread_id,
			], $post->User);
		}
		else
		{
			// Undo the previous event
			$event->undo($post->getPreviousValue('post_id'), [
				'node_id'      => $previousThread->node_id,
				'thread_id'    => $previousThread->thread_id,
				'multiplier'   => $post->getPreviousValue('message'),
				'owner_id'     => $previousThread->user_id,
				'content_type' => 'post',
				'content_id'   => $post->getPreviousValue('post_id'),
			], $post->User);

			// Apply the new event
			$event->apply($post->post_id, [
				'node_id'      => $thread->node_id,
				'thread_id'    => $post->thread_id,
				'multiplier'   => $post->message,
				'owner_id'     => $thread->user_id,
				'content_type' => 'post',
				'content_id'   => $post->post_id,
			], $post->User);
		}

		// Revert from backup
		$event->setEvents($events);
	}
}