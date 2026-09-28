<?php

namespace DBTech\Shop\Service\TradePost;

use DBTech\Shop\Entity\TradePost;

/**
 * Class Editor
 *
 * @package DBTech\Shop\Service\TradePost
 */
class Editor extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/** @var TradePost */
	protected $tradePost;

	/** @var \DBTech\Shop\Service\TradePost\Preparer */
	protected $preparer;

	/** @var bool */
	protected $alert = false;

	/** @var string */
	protected $alertReason = '';
	
	
	/**
	 * Editor constructor.
	 *
	 * @param \XF\App $app
	 * @param TradePost $tradePost
	 */
	public function __construct(\XF\App $app, TradePost $tradePost)
	{
		parent::__construct($app);
		$this->setTradePost($tradePost);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return $this
	 */
	protected function setTradePost(TradePost $tradePost): Editor
	{
		$this->tradePost = $tradePost;
		$this->preparer = $this->service('DBTech\Shop:TradePost\Preparer', $tradePost);

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
	 * @return \DBTech\Shop\Service\TradePost\Preparer
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
	 * @throws \XF\PrintableException
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
			/** @var \DBTech\Shop\Repository\TradePost $tradePostRepo */
			$tradePostRepo = $this->repository('DBTech\Shop:TradePost');
			$tradePostRepo->sendModeratorActionAlert($tradePost, 'edit', $this->alertReason);
		}

		$db->commit();

		return $tradePost;
	}
}