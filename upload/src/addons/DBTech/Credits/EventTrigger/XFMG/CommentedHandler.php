<?php

namespace DBTech\Credits\EventTrigger\XFMG;

use DBTech\Credits\Entity\Transaction as TransactionEntity;
use DBTech\Credits\EventTrigger\AbstractHandler;
use XF\Mvc\Entity\Entity;
use XF\PrintableException;
use XFMG\Entity\Comment;

class CommentedHandler extends AbstractHandler
{
	/**
	 *
	 */
	protected function setupOptions(): void
	{
		$this->options = array_replace($this->options, [
			'isGlobal' => true,
			'canRevert' => true,
			'canRebuild' => true,

			'multiplier' => self::MULTIPLIER_SIZE,
		]);
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
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_gallerycommented_negate', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_gallerycommented_negate', $transaction);
			}
		}
		else
		{
			if ($which == 'spent')
			{
				return $this->getAlertPhrase('dbtech_credits_lost_x_y_via_gallerycommented', $transaction);
			}
			else
			{
				return $this->getAlertPhrase('dbtech_credits_gained_x_y_via_gallerycommented', $transaction);
			}
		}
	}

	/**
	 * @return string|null
	 */
	public function getOptionsTemplate(): ?string
	{
		return null;
	}

	/**
	 * @param Entity $entity
	 *
	 * @throws PrintableException
	 */
	public function rebuild(Entity $entity): void
	{
		/** @var Comment $entity */

		if ($entity->user_id != $entity->Content->user_id)
		{
			$this->apply($entity->comment_id, [
				'multiplier'     => $entity->message,
				'source_user_id' => $entity->user_id,

				'content_type' => 'xfmg_comment',
				'content_id'   => $entity->comment_id,

				'timestamp'   => $entity->comment_date,
				'enableAlert' => false,
				'runPostSave' => false,
			], $entity->Content->User);
		}
	}
}