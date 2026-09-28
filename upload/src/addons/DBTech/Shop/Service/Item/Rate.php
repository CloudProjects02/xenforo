<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;

/**
 * Class Rate
 *
 * @package DBTech\Shop\Service\Item
 */
class Rate extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;

	/** @var \DBTech\Shop\Entity\Item */
	protected $item;

	/** @var \DBTech\Shop\Entity\ItemRating */
	protected $rating;
	
	/** @var bool */
	protected $reviewRequired = false;

	/** @var int */
	protected $reviewMinLength = 0;
	
	/** @var bool */
	protected $sendAlert = true;
	
	/**
	 * Rate constructor.
	 *
	 * @param \XF\App $app
	 * @param Item $item
	 */
	public function __construct(\XF\App $app, Item $item)
	{
		parent::__construct($app);

		$this->item = $item;
		$this->rating = $this->setupRating();

		$this->reviewRequired = $this->app->options()->dbtechShopReviewRequired;
		$this->reviewMinLength = $this->app->options()->dbtechShopMinimumReviewLength;
	}
	
	/**
	 * @return \XF\Mvc\Entity\Entity
	 */
	protected function setupRating()
	{
		$item = $this->item;

		/** @var \DBTech\Shop\Entity\ItemRating $rating */
		$rating = $this->em()->create('DBTech\Shop:ItemRating');
		$rating->item_id = $item->item_id;
		$rating->user_id = \XF::visitor()->user_id;

		return $rating;
	}
	
	/**
	 * @return Item
	 */
	public function getItem(): Item
	{
		return $this->item;
	}
	
	/**
	 * @return \DBTech\Shop\Entity\ItemRating|\XF\Mvc\Entity\Entity
	 */
	public function getRating()
	{
		return $this->rating;
	}

	/**
	 * @param int $rating
	 * @param string $message
	 *
	 * @return $this
	 */
	public function setRating(int $rating, string $message = ''): Rate
	{
		$this->rating->rating = $rating;
		$this->rating->message = $message;

		return $this;
	}

	/**
	 * @param bool $value
	 *
	 * @return $this
	 */
	public function setIsAnonymous(bool $value = true): Rate
	{
		$this->rating->is_anonymous = $value;

		return $this;
	}

	/**
	 * @param bool|null $reviewRequired
	 * @param bool|null $minLength
	 *
	 * @return $this
	 */
	public function setReviewRequirements(?bool $reviewRequired = null, ?bool $minLength = null): Rate
	{
		if ($reviewRequired !== null)
		{
			$this->reviewRequired = (bool)$reviewRequired;
		}
		if ($minLength !== null)
		{
			$minLength = max(0, (int)$minLength);
			$this->reviewMinLength = $minLength;
		}

		return $this;
	}
	
	/**
	 *
	 */
	public function checkForSpam()
	{
		$rating = $this->rating;

		if (
			$this->rating->message === ''
			|| $this->rating->getErrors()
			|| !\XF::visitor()->isSpamCheckRequired()
		) {
			return;
		}

		/** @var \XF\Entity\User $user */
		$user = $rating->User;

		$message = $rating->message;

		$checker = $this->app->spam()->contentChecker();
		$checker->check($user, $message, [
			'permalink' => $this->app->router('public')->buildLink('canonical:dbtech-shop', $rating->Item),
			'content_type' => 'dbtech_shop_rating'
		]);

		$decision = $checker->getFinalDecision();
		switch ($decision)
		{
			case 'moderated':
			case 'denied':
				$checker->logSpamTrigger('dbtech_shop_rating', null);
				$rating->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
				break;
		}
	}
	
	/**
	 * @return array
	 */
	protected function _validate(): array
	{
		$rating = $this->rating;

		$rating->preSave();
		$errors = $rating->getErrors();

		if ($this->reviewRequired && !$rating->is_review)
		{
			$errors['message'] = \XF::phrase('dbtech_shop_please_provide_review_with_your_rating');
		}

		if ($rating->is_review && utf8_strlen($rating->message) < $this->reviewMinLength)
		{
			$errors['message'] = \XF::phrase(
				'dbtech_shop_your_review_must_be_at_least_x_characters',
				['min' => $this->reviewMinLength]
			);
		}

		if (!$rating->rating)
		{
			$errors['rating'] = \XF::phrase('dbtech_shop_please_select_star_rating');
		}

		return $errors;
	}
	
	/**
	 * @return \DBTech\Shop\Entity\ItemRating|\XF\Mvc\Entity\Entity
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	protected function _save()
	{
		$rating = $this->rating;

		$existing = $this->item->Ratings[$rating->user_id];
		if ($existing)
		{
			$existing->delete();
		}

		$rating->save(true, false);

		if ($this->sendAlert)
		{
			/** @var \DBTech\Shop\Repository\ItemRating $itemRatingRepo */
			$itemRatingRepo = $this->repository('DBTech\Shop:ItemRating');
			$itemRatingRepo->sendReviewAlertToItemAuthor($rating);
		}

		return $rating;
	}
}