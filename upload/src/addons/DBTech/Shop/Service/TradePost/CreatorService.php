<?php

namespace DBTech\Shop\Service\TradePost;

use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradePost;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class CreatorService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Trade $trade;
	protected TradePost $tradePost;
	protected User $user;
	protected PreparerService $preparer;


	/**
	 * @param App $app
	 * @param Trade $trade
	 */
	public function __construct(App $app, Trade $trade)
	{
		parent::__construct($app);
		$this->setTrade($trade);
		$this->setUser(\XF::visitor());
		$this->setDefaults();
	}

	/**
	 * @param Trade $trade
	 *
	 * @return $this
	 */
	protected function setTrade(Trade $trade): CreatorService
	{
		$this->trade = $trade;
		$this->tradePost = $trade->getNewTradePost();
		$this->preparer = \XF::app()->service(PreparerService::class, $this->tradePost);

		return $this;
	}

	/**
	 * @return Trade
	 */
	public function getTrade(): Trade
	{
		return $this->trade;
	}

	/**
	 * @return TradePost
	 */
	public function getTradePost(): TradePost
	{
		return $this->tradePost;
	}

	/**
	 * @return PreparerService
	 */
	public function getTradePostPreparer(): PreparerService
	{
		return $this->preparer;
	}

	/**
	 * @param bool $logIp
	 *
	 * @return $this
	 */
	public function logIp(bool $logIp): CreatorService
	{
		$this->preparer->logIp($logIp);

		return $this;
	}

	/**
	 * @param User $user
	 */
	protected function setUser(User $user): void
	{
		$this->user = $user;
	}

	/**
	 *
	 */
	protected function setDefaults(): void
	{
		$this->tradePost->message_state = $this->tradePost->getNewContentState();
		$this->tradePost->user_id = $this->user->user_id;
		$this->tradePost->username = $this->user->username;
	}

	/**
	 * @param string $message
	 * @param bool $format
	 *
	 * @return bool
	 */
	public function setContent(string $message, bool $format = true): bool
	{
		return $this->preparer->setMessage($message, $format);
	}

	/**
	 *
	 */
	public function checkForSpam(): void
	{
		if ($this->tradePost->message_state == 'visible' && $this->user->isSpamCheckRequired())
		{
			$this->preparer->checkForSpam();
		}
	}

	/**
	 *
	 */
	protected function finalSetup(): void
	{
		$this->tradePost->post_date = time();
	}

	/**
	 * @return array
	 */
	protected function _validate(): array
	{
		$this->finalSetup();

		$this->tradePost->preSave();
		return $this->tradePost->getErrors();
	}

	/**
	 * @return TradePost
	 * @throws PrintableException
	 */
	protected function _save(): TradePost
	{
		$tradePost = $this->tradePost;
		$tradePost->save();

		$this->preparer->afterInsert();

		return $tradePost;
	}

	/**
	 * @throws \Exception
	 */
	public function sendNotifications(): void
	{
		if ($this->tradePost->isVisible())
		{
			$notifier = \XF::app()->service(NotifierService::class, $this->tradePost);
			$notifier->setNotifyMentioned($this->preparer->getMentionedUserIds());
			$notifier->notify();
		}
	}
}