<?php

namespace DBTech\Shop\Entity;

use XF\Entity\LinkableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

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
 * @property string $item_title
 *
 * RELATIONS
 * @property \DBTech\Shop\Entity\Item $Item
 * @property \XF\Entity\User $User
 * @property \XF\Entity\DeletionLog $DeletionLog
 */
class ItemRating extends Entity implements LinkableInterface
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
	 * @return bool|mixed
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
		) {
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
	 * @return bool|mixed
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
	 * @param \XF\Entity\User|null $asUser
	 *
	 * @return bool
	 */
	public function canReport(&$error = null, ?\XF\Entity\User $asUser = null): bool
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
		) {
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
	 * @return mixed|string
	 */
	public function getContentUrl(bool $canonical = false, array $extraParams = [], $hash = null): string
	{
		$route = $canonical ? 'canonical:dbtech-shop/review' : 'dbtech-shop/review';
		return $this->app()->router('public')->buildLink($route, $this, $extraParams, $hash);
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
	 * @return string|\XF\Phrase
	 */
	public function getContentTitle(string $context = '')
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
	protected function _preSave()
	{
		if ($this->isUpdate() && $this->isChanged(['message', 'rating', 'user_id']))
		{
			throw new \LogicException('Cannot change rating message, value or user');
		}

		if ($this->isChanged('message'))
		{
			$this->is_review = strlen($this->message) ? true : false;
		}

		if (!$this->user_id)
		{
			throw new \LogicException('Need user ID');
		}
	}
	
	/**
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \XF\PrintableException
	 */
	protected function _postSave()
	{
		$visibilityChange = $this->isStateChanged('rating_state', 'visible');
		$deletionChange = $this->isStateChanged('rating_state', 'deleted');

		if ($this->isUpdate())
		{
			if ($visibilityChange == 'enter')
			{
				$this->ratingMadeVisible();
			}
			elseif ($visibilityChange == 'leave')
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
			$this->app()->logger()->logModeratorChanges('dbtech_shop_rating', $this);
		}
	}
	
	/**
	 *
	 * @throws \LogicException
	 */
	protected function ratingMadeVisible()
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
	protected function ratingHidden(bool $hardDelete = false)
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

		/** @var \XF\Repository\UserAlert $alertRepo */
		$alertRepo = $this->repository('XF:UserAlert');
		$alertRepo->fastDeleteAlertsForContent('dbtech_shop_rating', $this->item_rating_id);
	}
	
	/**
	 * @return bool
	 * @throws \LogicException
	 */
	protected function rebuildRatingCounted(): bool
	{
		/** @var \DBTech\Shop\Repository\ItemRating $ratingRepo */
		$ratingRepo = $this->repository('DBTech\Shop:ItemRating');

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
	 * @throws \XF\PrintableException
	 */
	protected function _postDelete()
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
			$this->app()->logger()->logModeratorAction('dbtech_shop_rating', $this, 'delete_hard');
		}
	}
	
	/**
	 * @param string $reason
	 * @param \XF\Entity\User|null $byUser
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function softDelete(string $reason = '', ?\XF\Entity\User $byUser = null): bool
	{
		$byUser = $byUser ?: \XF::visitor();

		if ($this->rating_state == 'deleted')
		{
			return false;
		}

		$this->rating_state = 'deleted';

		/** @var \XF\Entity\DeletionLog $deletionLog */
		$deletionLog = $this->getRelationOrDefault('DeletionLog');
		$deletionLog->setFromUser($byUser);
		$deletionLog->delete_reason = $reason;

		$this->save();

		return true;
	}

	/**
	 * @param \XF\Mvc\Entity\Structure $structure
	 *
	 * @return \XF\Mvc\Entity\Structure
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
				'allowedValues' => ['visible', 'deleted']
			],
			'warning_id' => ['type' => self::UINT, 'default' => 0],
			'is_anonymous' => ['type' => self::BOOL, 'default' => false]
		];
		$structure->getters = [
			'item_title' => true
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
				'dateField' => 'rating_date'
			]
		];
		$structure->relations = [
			'Item' => [
				'entity' => 'DBTech\Shop:Item',
				'type' => self::TO_ONE,
				'conditions' => 'item_id',
				'primary' => true
			],
			'User' => [
				'entity' => 'XF:User',
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true
			],
			'DeletionLog' => [
				'entity' => 'XF:DeletionLog',
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'dbtech_shop_rating'],
					['content_id', '=', '$item_rating_id']
				],
				'primary' => true
			]
		];

		$structure->withAliases = [
			'full' => [
				'User',
			]
		];

		$structure->options = [
			'log_moderator' => true
		];
		$structure->defaultWith = ['Item'];

		return $structure;
	}
}