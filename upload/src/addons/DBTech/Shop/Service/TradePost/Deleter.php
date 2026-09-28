<?php

namespace DBTech\Shop\Service\TradePost;

use DBTech\Shop\Entity\TradePost;
use XF\Entity\User;

/**
 * Class Deleter
 *
 * @package DBTech\Shop\Service\TradePost
 */
class Deleter extends \XF\Service\AbstractService
{
	/** @var TradePost */
	protected $tradePost;

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
	 * @param TradePost $tradePost
	 */
	public function __construct(\XF\App $app, TradePost $tradePost)
	{
		parent::__construct($app);
		$this->setTradePost($tradePost);
		$this->setUser(\XF::visitor());
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return $this
	 */
	protected function setTradePost(TradePost $tradePost): Deleter
	{
		$this->tradePost = $tradePost;

		return $this;
	}

	/**
	 * @return \DBTech\Shop\Entity\TradePost
	 */
	public function getTradePost(): TradePost
	{
		return $this->tradePost;
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
			/** @var \DBTech\Shop\Repository\TradePost $tradePostRepo */
			$tradePostRepo = $this->repository('DBTech\Shop:TradePost');
			$tradePostRepo->sendModeratorActionAlert($tradePost, 'delete', $this->alertReason);
		}

		return $result;
	}
}