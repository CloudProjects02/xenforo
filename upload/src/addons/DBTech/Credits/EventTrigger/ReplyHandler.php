<?php

namespace DBTech\Credits\EventTrigger;

use DBTech\Credits\Entity\Event as EventEntity;
use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\XF\Entity\Post;
use XF\Entity\User;
use XF\InputFilterer;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;

class ReplyHandler extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'canRevert' => true,
			'canRebuild' => true,
		]);
	}

	/**
	 * @param User $user
	 * @param mixed $refId
	 * @param bool $negate
	 * @param array $extraParams
	 *
	 * @return TransactionEntity[]
	 * @throws PrintableException
	 */
	protected function trigger(
		User $user,
		mixed           $refId,
		bool            $negate = false,
		array           $extraParams = []
	): array
	{
		$extraParams = array_replace([
			'thread_id' => 0,
		], $extraParams);

		return parent::trigger($user, $refId, $negate, $extraParams);
	}

	/**
	 * @param EventEntity $event
	 * @param User $user
	 * @param \ArrayObject $extraParams
	 *
	 * @return bool
	 */
	protected function assertEvent(EventEntity $event, User $user, \ArrayObject $extraParams): bool
	{
		if (
			$event->getSetting('threadid')
			&& $event->getSetting('threadid') != $extraParams->thread_id
		)
		{
			// Skip this
			return false;
		}

		return parent::assertEvent($event, $user, $extraParams);
	}

	/**
	 * @param TransactionEntity $transaction
	 *
	 * @return mixed
	 */
	public function alertTemplate(TransactionEntity $transaction): string
	{
		// For the benefit of the template
		$which = $transaction->amount < 0.00 ? 'spent' : 'earned';

		if ($transaction->negate)
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_reply_negate', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_reply_negate', $transaction);
			}
		}
		else
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_reply', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_reply', $transaction);
			}
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getFilterOptions(): array
	{
		$filterOptions = parent::getFilterOptions();

		return \array_merge($filterOptions, [
			'threadid' => InputFilterer::UNSIGNED,
		]);
	}

	/**
	 * @param Entity $entity
	 *
	 * @throws PrintableException
	 */
	public function rebuild(Entity $entity): void
	{
		/** @var Post $entity */

		if (!$entity->isFirstPost() && $entity->isVisible())
		{
			$this->apply($entity->post_id, [
				'node_id' => $entity->Thread->node_id,
				'thread_id' => $entity->thread_id,
				'source_user_id'  => $entity->user_id,

				'content_type' => 'post',
				'content_id'   => $entity->post_id,

				'timestamp'   => $entity->post_date,
				'enableAlert' => false,
				'runPostSave' => false,
			], $entity->Thread->User);
		}
	}

	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith(bool $forView = false): array
	{
		return ['Thread', 'Thread.User'];
	}
}