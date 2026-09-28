<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePostComment;

/**
 * Class Editor
 *
 * @package DBTech\Shop\Service\TradePostComment
 */
class Editor extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/** @var TradePostComment */
	protected $comment;

	/** @var \DBTech\Shop\Service\TradePostComment\Preparer */
	protected $preparer;

	/** @var bool */
	protected $alert = false;

	/** @var string */
	protected $alertReason = '';
	
	
	/**
	 * Editor constructor.
	 *
	 * @param \XF\App $app
	 * @param TradePostComment $comment
	 */
	public function __construct(\XF\App $app, TradePostComment $comment)
	{
		parent::__construct($app);
		$this->setComment($comment);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePostComment $comment
	 *
	 * @return $this
	 */
	protected function setComment(TradePostComment $comment): Editor
	{
		$this->comment = $comment;
		$this->preparer = $this->service('DBTech\Shop:TradePostComment\Preparer', $this->comment);

		return $this;
	}

	/**
	 * @return \DBTech\Shop\Entity\TradePostComment
	 */
	public function getComment(): TradePostComment
	{
		return $this->comment;
	}

	/**
	 * @return \DBTech\Shop\Service\TradePostComment\Preparer
	 */
	public function getPreparer(): Preparer
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
	public function setSendAlert(bool $alert, ?string $reason = null): Editor
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
	public function checkForSpam()
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
	 * @throws \XF\PrintableException
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
			/** @var \DBTech\Shop\Repository\TradePost $profilePostRepo */
			$profilePostRepo = $this->repository('DBTech\Shop:TradePost');
			$profilePostRepo->sendCommentModeratorActionAlert($comment, 'edit', $this->alertReason);
		}

		$db->commit();

		return $comment;
	}
}