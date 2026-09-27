<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Credits\XFMG\Service\Comment;

use DBTech\Credits\Repository\EventTriggerRepository;
use XF\PrintableException;
use XFMG\Entity\Comment;

/**
 * @extends \XFMG\Service\Comment\Creator
 */
class Creator extends XFCP_Creator
{
	/**
	 * @return array
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _validate()
	{
		$previous = parent::_validate();

		if (empty($previous) && $this->comment->user_id && !$this->comment->rating_id)
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

			$eventTriggerRepo->getHandler('gallerycomment')
				->testApply([
					'multiplier' => $this->comment->message,
					'owner_id' => $this->content->user_id,
				], $this->comment->User)
			;

			if ($this->comment->user_id != $this->comment->Content->user_id)
			{
				$eventTriggerRepo->getHandler('gallerycommented')
					->testApply([
						'multiplier'     => $this->comment->message,
						'source_user_id' => $this->comment->user_id,
					], $this->content->User)
				;
			}
		}

		return $previous;
	}

	/**
	 * @return Comment
	 * @throws PrintableException
	 * @throws \Exception
	 */
	protected function _save()
	{
		$comment = parent::_save();

		if ($comment && $comment->user_id && !$comment->rating_id && $comment->isVisible())
		{
			$eventTriggerRepo = \XF::app()->repository(EventTriggerRepository::class);

			$eventTriggerRepo->getHandler('gallerycomment')
				->apply($comment->comment_id, [
					'multiplier'   => $comment->message,
					'owner_id'     => $comment->Content->user_id,
					'content_type' => 'xfmg_comment',
					'content_id'   => $comment->comment_id,
				], $comment->User)
			;

			if ($comment->user_id != $comment->Content->user_id)
			{
				$eventTriggerRepo->getHandler('gallerycommented')
					->apply($comment->comment_id, [
						'multiplier'     => $comment->message,
						'source_user_id' => $comment->user_id,
						'content_type'   => 'xfmg_comment',
						'content_id'     => $comment->comment_id,
					], $comment->Content->User)
				;
			}
		}

		return $comment;
	}
}