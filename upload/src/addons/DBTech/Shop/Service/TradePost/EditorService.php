<?php

namespace DBTech\Shop\Service\TradePost;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Repository\TradePostRepository;
use XF\App;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class EditorService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected TradePost $tradePost;
	protected PreparerService $preparer;
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
	}

	/**
	 * @param TradePost $tradePost
	 *
	 * @return $this
	 */
	protected function setTradePost(TradePost $tradePost): EditorService
	{
		$this->tradePost = $tradePost;
		$this->preparer = \XF::app()->service(PreparerService::class, $tradePost);

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
		if ($this->tradePost->message_state == 'visible' && \XF::visitor()->isSpamCheckRequired())
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

		$this->tradePost->preSave();
		return $this->tradePost->getErrors();
	}

	/**
	 * @return TradePost
	 * @throws PrintableException
	 */
	protected function _save(): TradePost
	{
		$db = $this->db();
		$db->beginTransaction();

		$tradePost = $this->tradePost;
		$visitor = \XF::visitor();

		$tradePost->save(true, false);

		$this->preparer->afterUpdate();

		if ($tradePost->message_state == 'visible' && $this->alert && $tradePost->user_id != $visitor->user_id)
		{
			$tradePostRepo = \XF::app()->repository(TradePostRepository::class);
			$tradePostRepo->sendModeratorActionAlert($tradePost, 'edit', $this->alertReason);
		}

		$db->commit();

		return $tradePost;
	}
}