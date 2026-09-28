<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePostComment;
use XF\Entity\User;

/**
 * Class Deleter
 *
 * @package DBTech\Shop\Service\TradePostComment
 */
class Deleter extends \XF\Service\AbstractService
{
	/** @var TradePostComment */
	protected $comment;

	/** @var User */
	protected $user;

	/** @var bool */
	protected $alert = false;

	/** @var string */
	protected $alertReason = '';
	
	
	/**
	 * Deleter constructor.
	 *
	 * @param \XF\App $app
	 * @param TradePostComment $content
	 */
	public function __construct(\XF\App $app, TradePostComment $content)
	{
		parent::__construct($app);
		$this->setComment($content);
		$this->setUser(\XF::visitor());
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePostComment $content
	 *
	 * @return $this
	 */
	protected function setComment(TradePostComment $content): Deleter
	{
		$this->comment = $content;

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
	 * @param \XF\Entity\User $user
	 *
	 * @return $this
	 */
	protected function setUser(User $user): Deleter
	{
		$this->user = $user;

		return $this;
	}

	/**
	 * @return \XF\Entity\User
	 */
	public function getUser(): User
	{
		return $this->user;
	}

	/**
	 * @param bool $alert
	 * @param string|null $reason
	 *
	 * @return $this
	 */
	public function setSendAlert(bool $alert, ?string $reason = null): Deleter
	{
		$this->alert = $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}

		return $this;
	}
	
	/**
	 * @param string $type
	 * @param string $reason
	 *
	 * @return bool
	 * @throws \XF\PrintableException
	 */
	public function delete(string $type, string $reason = ''): bool
	{
		$user = $this->user;

		$comment = $this->comment;
		$wasVisible = ($comment->message_state == 'visible');

		if ($type == 'soft')
		{
			$result = $comment->softDelete($reason, $user);
		}
		else
		{
			$result = $comment->delete();
		}

		if ($result && $wasVisible && $this->alert && $comment->user_id != $user->user_id)
		{
			/** @var \DBTech\Shop\Repository\TradePost $profilePostRepo */
			$profilePostRepo = $this->repository('DBTech\Shop:TradePost');
			$profilePostRepo->sendCommentModeratorActionAlert($comment, 'delete', $this->alertReason);
		}

		return $result;
	}
}