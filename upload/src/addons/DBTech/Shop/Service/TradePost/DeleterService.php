<?php

namespace DBTech\Shop\Service\TradePost;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Repository\TradePostRepository;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;

class DeleterService extends AbstractService
{
	protected TradePost $tradePost;
	protected User $user;
	protected bool $alert = false;
	protected string $alertReason = '';


	/**
	 * @param App $app
	 * @param TradePost $tradePost
	 */
	public function __construct(App $app, TradePost $tradePost)
	{
		parent::__construct($app);
		$this->setTradePost($tradePost);
		$this->setUser(\XF::visitor());
	}

	/**
	 * @param TradePost $tradePost
	 *
	 * @return $this
	 */
	protected function setTradePost(TradePost $tradePost): DeleterService
	{
		$this->tradePost = $tradePost;

		return $this;
	}

	/**
	 * @return TradePost
	 */
	public function getTradePost(): TradePost
	{
		return $this->tradePost;
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

		$tradePost = $this->tradePost;
		$wasVisible = ($tradePost->message_state == 'visible');

		if ($type == 'soft')
		{
			$result = $tradePost->softDelete($reason, $user);
		}
		else
		{
			$result = $tradePost->delete();
		}

		if ($result && $wasVisible && $this->alert && $tradePost->user_id != $user->user_id)
		{
			$tradePostRepo = \XF::app()->repository(TradePostRepository::class);
			$tradePostRepo->sendModeratorActionAlert($tradePost, 'delete', $this->alertReason);
		}

		return $result;
	}
}