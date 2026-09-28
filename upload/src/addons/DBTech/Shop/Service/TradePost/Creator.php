<?php

namespace DBTech\Shop\Service\TradePost;

use XF\Entity\User;
use DBTech\Shop\Entity\Trade;
use DBTech\Shop\Entity\TradePost;

/**
 * Class Creator
 *
 * @package DBTech\Shop\Service\TradePost
 */
class Creator extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/** @var Trade */
	protected $trade;

	/** @var TradePost */
	protected $tradePost;

	/** @var User */
	protected $user;

	/** @var \DBTech\Shop\Service\TradePost\Preparer */
	protected $preparer;
	
	
	/**
	 * Creator constructor.
	 *
	 * @param \XF\App $app
	 * @param Trade $trade
	 */
	public function __construct(\XF\App $app, Trade $trade)
	{
		parent::__construct($app);
		$this->setTrade($trade);
		$this->setUser(\XF::visitor());
		$this->setDefaults();
	}

	/**
	 * @param \DBTech\Shop\Entity\Trade $trade
	 *
	 * @return $this
	 */
	protected function setTrade(Trade $trade): Creator
	{
		$this->trade = $trade;
		$this->tradePost = $trade->getNewTradePost();
		$this->preparer = $this->service('DBTech\Shop:TradePost\Preparer', $this->tradePost);

		return $this;
	}

	/**
	 * @return \DBTech\Shop\Entity\Trade
	 */
	public function getTrade(): Trade
	{
		return $this->trade;
	}

	/**
	 * @return \DBTech\Shop\Entity\TradePost
	 */
	public function getTradePost(): TradePost
	{
		return $this->tradePost;
	}

	/**
	 * @return \DBTech\Shop\Service\TradePost\Preparer
	 */
	public function getTradePostPreparer(): Preparer
	{
		return $this->preparer;
	}

	/**
	 * @param bool $logIp
	 *
	 * @return $this
	 */
	public function logIp(bool $logIp): Creator
	{
		$this->preparer->logIp($logIp);

		return $this;
	}

	/**
	 * @param \XF\Entity\User $user
	 */
	protected function setUser(User $user)
	{
		$this->user = $user;
	}
	
	/**
	 *
	 */
	protected function setDefaults()
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
	public function checkForSpam()
	{
		if ($this->tradePost->message_state == 'visible' && $this->user->isSpamCheckRequired())
		{
			$this->preparer->checkForSpam();
		}
	}
	
	/**
	 *
	 */
	protected function finalSetup()
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
	 * @return \DBTech\Shop\Entity\TradePost
	 * @throws \XF\PrintableException
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
	public function sendNotifications()
	{
		if ($this->tradePost->isVisible())
		{
			/** @var \DBTech\Shop\Service\TradePost\Notifier $notifier */
			$notifier = $this->service('DBTech\Shop:TradePost\Notifier', $this->tradePost);
			$notifier->setNotifyMentioned($this->preparer->getMentionedUserIds());
			$notifier->notify();
		}
	}
}