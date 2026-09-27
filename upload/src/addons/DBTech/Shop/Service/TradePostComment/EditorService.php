<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePostComment;
use DBTech\Shop\Repository\TradePostRepository;
use XF\App;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class EditorService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected TradePostComment $comment;
	protected PreparerService $preparer;
	protected bool $alert = false;
	protected string $alertReason = '';


	/**
	 * @param App $app
	 * @param TradePostComment $comment
	 */
	public function __construct(App $app, TradePostComment $comment)
	{
		parent::__construct($app);
		$this->setComment($comment);
	}

	/**
	 * @param TradePostComment $comment
	 *
	 * @return $this
	 */
	protected function setComment(TradePostComment $comment): EditorService
	{
		$this->comment = $comment;
		$this->preparer = \XF::app()->service(PreparerService::class, $this->comment);

		return $this;
	}

	/**
	 * @return TradePostComment
	 */
	public function getComment(): TradePostComment
	{
		return $this->comment;
	}

	/**
	 * @return PreparerService
	 */
	public function getPreparer(): PreparerService
	{
		return $this->preparer;
	}

	/**
	 * @param string $message
	 * @param bool $format
	 *
	 * @return bool
	 */
	public function setMessage(string $message, bool $format = true): bool
	{
		return $this->preparer->setMessage($message, $format);
	}

	/**
	 * @param bool $alert
	 * @param string|null $reason
	 *
	 * @return $this
	 */
	public function setSendAlert(bool $alert, ?string $reason = null): EditorService
	{
		$this->alert = $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}

		return $this;
	}

	/**
	 *
	 */
	public function checkForSpam(): void
	{
		if ($this->comment->message_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
		{
			$this->preparer->checkForSpam();
		}
	}

	/**
	 *
	 */
	protected function finalSetup()
	{
	}

	/**
	 * @return array
	 */
	protected function _validate(): array
	{
		$this->finalSetup();

		$this->comment->preSave();
		return $this->comment->getErrors();
	}

	/**
	 * @return TradePostComment
	 * @throws PrintableException
	 */
	protected function _save(): TradePostComment
	{
		$db = $this->db();
		$db->beginTransaction();

		$comment = $this->comment;
		$visitor = \XF::visitor();

		$comment->save(true, false);

		$this->preparer->afterUpdate();

		if ($comment->message_state == 'visible' && $this->alert && $comment->user_id != $visitor->user_id)
		{
			$profilePostRepo = \XF::app()->repository(TradePostRepository::class);
			$profilePostRepo->sendCommentModeratorActionAlert($comment, 'edit', $this->alertReason);
		}

		$db->commit();

		return $comment;
	}
}