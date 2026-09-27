<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\ItemRating;
use DBTech\Shop\Repository\ItemRatingRepository;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class RateService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Item $item;
	protected ItemRating $rating;
	protected bool $reviewRequired = false;
	protected int $reviewMinLength = 0;
	protected bool $sendAlert = true;

	/**
	 * @param App $app
	 * @param Item $item
	 */
	public function __construct(App $app, Item $item)
	{
		parent::__construct($app);

		$this->item = $item;
		$this->rating = $this->setupRating();

		$this->reviewRequired = \XF::app()->options()->dbtechShopReviewRequired;
		$this->reviewMinLength = \XF::app()->options()->dbtechShopMinimumReviewLength;
	}

	/**
	 * @return ItemRating
	 */
	protected function setupRating(): ItemRating
	{
		$item = $this->item;

		$rating = \XF::app()->em()->create(ItemRating::class);
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
	 * @return ItemRating
	 */
	public function getRating(): ItemRating
	{
		return $this->rating;
	}

	/**
	 * @param int $rating
	 * @param string $message
	 *
	 * @return $this
	 */
	public function setRating(int $rating, string $message = ''): RateService
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
	public function setIsAnonymous(bool $value = true): RateService
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
	public function setReviewRequirements(?bool $reviewRequired = null, ?bool $minLength = null): RateService
	{
		if ($reviewRequired !== null)
		{
			$this->reviewRequired = $reviewRequired;
		}
		if ($minLength !== null)
		{
			$minLength = max(0, (int) $minLength);
			$this->reviewMinLength = $minLength;
		}

		return $this;
	}

	/**
	 *
	 */
	public function checkForSpam(): void
	{
		$rating = $this->rating;

		if (
			$this->rating->message === ''
			|| $this->rating->getErrors()
			|| !\XF::visitor()->isSpamCheckRequired()
		)
		{
			return;
		}

		/** @var User $user */
		$user = $rating->User;

		$message = $rating->message;

		$checker = \XF::app()->spam()->contentChecker();
		$checker->check($user, $message, [
			'permalink' => \XF::app()->router('public')->buildLink('canonical:dbtech-shop', $rating->Item),
			'content_type' => 'dbtech_shop_rating',
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

		if ($rating->is_review && \mb_strlen($rating->message) < $this->reviewMinLength)
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
	 * @return ItemRating
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	protected function _save(): ItemRating
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
			$itemRatingRepo = \XF::app()->repository(ItemRatingRepository::class);
			$itemRatingRepo->sendReviewAlertToItemAuthor($rating);
		}

		return $rating;
	}
}