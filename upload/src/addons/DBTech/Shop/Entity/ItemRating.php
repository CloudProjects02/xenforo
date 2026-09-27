<?php

namespace DBTech\Shop\Entity;

use DBTech\Shop\Repository\ItemRatingRepository;
use XF\Entity\DeletionLog;
use XF\Entity\LinkableInterface;
use XF\Entity\User;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\PrintableException;
use XF\Repository\UserAlertRepository;

/**
 * COLUMNS
 * @property int|null $item_rating_id
 * @property int $item_id
 * @property int $user_id
 * @property int $rating
 * @property int $rating_date
 * @property string $message
 * @property string $author_response
 * @property bool $is_review
 * @property bool $count_rating
 * @property string $rating_state
 * @property int $warning_id
 * @property bool $is_anonymous
 *
 * GETTERS
 * @property-read string $item_title
 *
 * RELATIONS
 * @property-read Item|null $Item
 * @property-read User|null $User
 * @property-read DeletionLog|null $DeletionLog
 */
class ItemRating extends Entity implements LinkableInterface, ViewableInterface
{
	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canView(&$error = null): bool
	{
		$item = $this->Item;

		if (!$item || !$item->canView($error))
		{
			return false;
		}

		if ($this->rating_state == 'deleted')
		{
			if (!$item->hasPermission('viewDeletedReviews'))
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $type
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canDelete(string $type = 'soft', &$error = null): bool
	{
		$visitor = \XF::visitor();
		$item = $this->Item;

		if (!$visitor->user_id || !$item)
		{
			return false;
		}

		if ($type != 'soft')
		{
			return (
				$item->hasPermission('hardDeleteAny')
				&& $item->hasPermission('deleteAnyReview')
			);
		}

		if ($this->user_id == $visitor->user_id && !$this->author_response)
		{
			return true;
		}

		return $item->hasPermission('deleteAnyReview');
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUpdate(&$error = null): bool
	{
		$visitor = \XF::visitor();
		$item = $this->Item;

		if (!$visitor->user_id
			|| $visitor->user_id != $this->user_id
			|| !$item
			|| !$item->hasPermission('rate')
		)
		{
			return false;
		}

		if ($this->rating_state != 'visible' || !$this->is_review)
		{
			return true;
		}

		if ($this->author_response)
		{
			$error = \XF::phraseDeferred('dbtech_shop_cannot_update_rating_once_author_response');
			return false;
		}

		return true;
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUndelete(&$error = null): bool
	{
		$visitor = \XF::visitor();
		$item = $this->Item;

		if (!$visitor->user_id || !$item)
		{
			return false;
		}

		return $item->hasPermission('undelete');
	}

	/**
	 * @param null $error
	 * @param User|null $asUser
	 *
	 * @return bool
	 */
	public function canReport(&$error = null, ?User $asUser = null): bool
	{
		$asUser = $asUser ?: \XF::visitor();
		return $asUser->canReport($error);
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canWarn(&$error = null): bool
	{
		$visitor = \XF::visitor();
		$item = $this->Item;

		if ($this->warning_id
			|| !$item
			|| !$visitor->user_id
			|| $this->user_id == $visitor->user_id
			|| !$item->hasPermission('warn')
		)
		{
			return false;
		}

		$user = $this->User;
		return ($user && $user->isWarnable());
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canReply(&$error = null): bool
	{
		$visitor = \XF::visitor();
		$item = $this->Item;

		return (
			$visitor->user_id
			&& $item
			&& $item->user_id == $visitor->user_id
			&& $this->is_review
			&& !$this->author_response
			&& $this->rating_state == 'visible'
			&& $item->hasPermission('reviewReply')
		);
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canDeleteAuthorResponse(&$error = null): bool
	{
		$visitor = \XF::visitor();
		$item = $this->Item;

		if (!$visitor->user_id || !$this->is_review || !$this->author_response || !$item)
		{
			return false;
		}

		return (
			$visitor->user_id == $this->Item->user_id
			|| $item->hasPermission('deleteAnyReview')
		);
	}

	/**
	 * @return bool
	 */
	public function canViewAnonymousAuthor(): bool
	{
		$visitor = \XF::visitor();

		return (
			$visitor->user_id
			&& (
				$visitor->user_id == $this->user_id
				|| $visitor->canBypassUserPrivacy()
			)
		);
	}

	/**
	 * @return bool
	 */
	public function canSendModeratorActionAlert(): bool
	{
		$item = $this->Item;

		return (
			$item
			&& $item->canSendModeratorActionAlert()
			&& $this->rating_state == 'visible'
		);
	}

	/**
	 * @return bool
	 */
	public function isVisible(): bool
	{
		return (
			$this->rating_state == 'visible'
			&& $this->Item
			&& $this->Item->isVisible()
		);
	}

	/**
	 * @return bool
	 */
	public function isIgnored(): bool
	{
		if ($this->is_anonymous)
		{
			return false;
		}

		return \XF::visitor()->isIgnoring($this->user_id);
	}

	/**
	 * @return string
	 */
	public function getItemTitle(): string
	{
		return $this->Item ? $this->Item->title : '';
	}

	/**
	 * @param bool $canonical
	 * @param array $extraParams
	 * @param null $hash
	 *
	 * @return string
	 */
	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null): string
	{
		$route = $canonical ? 'canonical:dbtech-shop/review' : 'dbtech-shop/review';
		return \XF::app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	/**
	 * @return string|null
	 */
	public function getContentPublicRoute(): ?string
	{
		return 'dbtech-shop/review';
	}

	/**
	 * @param string $context
	 *
	 * @return Phrase
	 */
	public function getContentTitle(string $context = ''): Phrase
	{
		if ($this->Item)
		{
			return \XF::phrase('dbtech_shop_review_for_x', ['title' => $this->Item->title]);
		}

		return \XF::phrase('dbtech_shop_review_for_x', ['title' => 'N/A']);
	}

	/**
	 *
	 * @throws \LogicException
	 */
	protected function _preSave(): void
	{
		if ($this->isUpdate() && $this->isChanged(['message', 'rating', 'user_id']))
		{
			throw new \LogicException('Cannot change rating message, value or user');
		}

		if ($this->isChanged('message'))
		{
			$this->is_review = (bool) strlen($this->message);
		}

		if (!$this->user_id)
		{
			throw new \LogicException('Need user ID');
		}
	}

	/**
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws PrintableException
	 */
	protected function _postSave(): void
	{
		$visibilityChange = $this->isStateChanged('rating_state', 'visible');
		$deletionChange = $this->isStateChanged('rating_state', 'deleted');

		if ($this->isUpdate())
		{
			if ($visibilityChange == 'enter')
			{
				$this->ratingMadeVisible();
			}
			else if ($visibilityChange == 'leave')
			{
				$this->ratingHidden();
			}

			if ($deletionChange == 'leave' && $this->DeletionLog)
			{
				$this->DeletionLog->delete();
			}
		}
		else
		{
			// insert
			if ($this->rating_state == 'visible')
			{
				$this->ratingMadeVisible();
			}
		}

		if ($deletionChange == 'enter' && !$this->DeletionLog)
		{
			$delLog = $this->getRelationOrDefault('DeletionLog', false);
			$delLog->setFromVisitor();
			$delLog->save();
		}

		if ($this->isUpdate() && $this->getOption('log_moderator'))
		{
			\XF::app()->logger()->logModeratorChanges('dbtech_shop_rating', $this);
		}
	}

	/**
	 *
	 * @throws \LogicException
	 */
	protected function ratingMadeVisible(): void
	{
		$item = $this->Item;

		if ($item)
		{
			if ($this->is_review)
			{
				$item->review_count++;
			}

			if ($this->rebuildRatingCounted())
			{
				$item->rebuildRating();
			}

			$item->saveIfChanged();
		}
	}

	/**
	 * @param bool $hardDelete
	 *
	 * @throws \LogicException
	 */
	protected function ratingHidden(bool $hardDelete = false): void
	{
		$item = $this->Item;

		if ($item)
		{
			if ($this->is_review)
			{
				$item->review_count--;
			}

			if ($this->count_rating)
			{
				$item->rebuildRating();
			}

			$item->saveIfChanged();
		}

		$alertRepo = \XF::app()->repository(UserAlertRepository::class);
		$alertRepo->fastDeleteAlertsForContent('dbtech_shop_rating', $this->item_rating_id);
	}

	/**
	 * @return bool
	 * @throws \LogicException
	 */
	protected function rebuildRatingCounted(): bool
	{
		$ratingRepo = \XF::app()->repository(ItemRatingRepository::class);

		$countable = $ratingRepo->getCountableRating($this->item_id, $this->user_id);
		if ($countable && $countable->count_rating)
		{
			// already counted, no action needed
			return false;
		}

		$rebuildRequired = false;

		$counted = $ratingRepo->getCountedRatings($this->item_id, $this->user_id);

		if ($countable)
		{
			$countable->fastUpdate('count_rating', true);
			$rebuildRequired = true;
		}

		foreach ($counted AS $count)
		{
			if ($countable && $count->item_rating_id == $countable->item_rating_id)
			{
				// we've just set this to be counted, ignore it
				continue;
			}

			$count->fastUpdate('count_rating', false);
			$rebuildRequired = true;
		}

		return $rebuildRequired;
	}

	/**
	 * @throws \LogicException
	 * @throws PrintableException
	 */
	protected function _postDelete(): void
	{
		if ($this->rating_state == 'visible')
		{
			$this->ratingHidden(true);
		}

		if ($this->rating_state == 'deleted' && $this->DeletionLog)
		{
			$this->DeletionLog->delete();
		}

		if ($this->getOption('log_moderator'))
		{
			\XF::app()->logger()->logModeratorAction('dbtech_shop_rating', $this, 'delete_hard');
		}
	}

	/**
	 * @param string $reason
	 * @param User|null $byUser
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function softDelete(string $reason = '', ?User $byUser = null): bool
	{
		$byUser = $byUser ?: \XF::visitor();

		if ($this->rating_state == 'deleted')
		{
			return false;
		}

		$this->rating_state = 'deleted';

		/** @var DeletionLog $deletionLog */
		$deletionLog = $this->getRelationOrDefault('DeletionLog');
		$deletionLog->setFromUser($byUser);
		$deletionLog->delete_reason = $reason;

		$this->save();

		return true;
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_item_rating';
		$structure->shortName = 'DBTech\Shop:ItemRating';
		$structure->primaryKey = 'item_rating_id';
		$structure->contentType = 'dbtech_shop_rating';
		$structure->columns = [
			'item_rating_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'item_id' => ['type' => self::UINT, 'required' => true],
			'user_id' => ['type' => self::UINT, 'required' => true],
			'rating' => ['type' => self::UINT, 'required' => true, 'min' => 1, 'max' => 5],
			'rating_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'message' => ['type' => self::STR, 'default' => ''],
			'author_response' => ['type' => self::STR, 'default' => ''],
			'is_review' => ['type' => self::BOOL, 'default' => false],
			'count_rating' => ['type' => self::BOOL, 'default' => false],
			'rating_state' => ['type' => self::STR, 'default' => 'visible',
				'allowedValues' => ['visible', 'deleted'],
			],
			'warning_id' => ['type' => self::UINT, 'default' => 0],
			'is_anonymous' => ['type' => self::BOOL, 'default' => false],
		];
		$structure->getters = [
			'item_title' => true,
		];
		$structure->behaviors = [
			'XF:NewsFeedPublishable' => [
				'userIdField' => function ($rating): int
				{
					return $rating->is_anonymous ? 0 : $rating->user_id;
				},
				'usernameField' => function ($rating): string
				{
					return $rating->is_anonymous ? '' : $rating->User->username;
				},
				'dateField' => 'rating_date',
			],
		];
		$structure->relations = [
			'Item' => [
				'entity' => Item::class,
				'type' => self::TO_ONE,
				'conditions' => 'item_id',
				'primary' => true,
			],
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
			],
			'DeletionLog' => [
				'entity' => DeletionLog::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'dbtech_shop_rating'],
					['content_id', '=', '$item_rating_id'],
				],
				'primary' => true,
			],
		];

		$structure->withAliases = [
			'full' => [
				'User',
			],
		];

		$structure->options = [
			'log_moderator' => true,
		];
		$structure->defaultWith = ['Item'];

		return $structure;
	}
}