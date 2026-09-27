<?php

namespace DBTech\Shop\Service\TradePostComment;

use DBTech\Shop\Entity\TradePostComment;
use DBTech\Shop\Repository\TradePostRepository;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

class DeleterService extends AbstractService
{
	protected TradePostComment $comment;
	protected User $user;
	protected bool $alert = false;
	protected string $alertReason = '';


	/**
	 * @param App $app
	 * @param TradePostComment $content
	 */
	public function __construct(App $app, TradePostComment $content)
	{
		parent::__construct($app);
		$this->setComment($content);
		$this->setUser(\XF::visitor());
	}

	/**
	 * @param TradePostComment $content
	 *
	 * @return $this
	 */
	protected function setComment(TradePostComment $content): DeleterService
	{
		$this->comment = $content;

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
	 * @param User $user
	 *
	 * @return $this
	 */
	protected function setUser(User $user): DeleterService
	{
		$this->user = $user;

		return $this;
	}

	/**
	 * @return User
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
	public function setSendAlert(bool $alert, ?string $reason = null): DeleterService
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
	 * @throws PrintableException
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
			$profilePostRepo = \XF::app()->repository(TradePostRepository::class);
			$profilePostRepo->sendCommentModeratorActionAlert($comment, 'delete', $this->alertReason);
		}

		return $result;
	}
}