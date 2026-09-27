<?php

namespace DBTech\Shop\Entity;

use DBTech\Shop\Repository\TradePostRepository;
use XF\Api\Result\EntityResult;
use XF\BbCode\RenderableContentInterface;
use XF\Entity\ApprovalQueue;
use XF\Entity\DeletionLog;
use XF\Entity\LinkableInterface;
use XF\Entity\ReactionTrait;
use XF\Entity\User;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\PrintableException;
use XF\Repository\UserAlertRepository;
use XF\Spam\ContentChecker;

/**
 * COLUMNS
 * @property int|null $trade_post_id
 * @property int $trade_id
 * @property int $user_id
 * @property string $username
 * @property int $post_date
 * @property string $message
 * @property int $ip_id
 * @property string $message_state
 * @property int $attach_count
 * @property int $warning_id
 * @property string $warning_message
 * @property int $comment_count
 * @property int $first_comment_date
 * @property int $last_comment_date
 * @property array $latest_comment_ids
 * @property array|null $embed_metadata
 * @property int $reaction_score
 * @property array $reactions_
 * @property array $reaction_users_
 *
 * GETTERS
 * @property-read array $comment_ids
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\TradePostComment>|null $LatestComments
 * @property-read array $Unfurls
 * @property mixed $reactions
 * @property mixed $reaction_users
 *
 * RELATIONS
 * @property-read Trade|null $Trade
 * @property-read User|null $User
 * @property-read DeletionLog|null $DeletionLog
 * @property-read ApprovalQueue|null $ApprovalQueue
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\TradePostComment> $Comments
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\ReactionContent> $Reactions
 */
class TradePost extends Entity implements LinkableInterface, RenderableContentInterface, ViewableInterface
{
	use ReactionTrait;

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canView(&$error = null): bool
	{
		$visitor = \XF::visitor();

		if (!$this->Trade)
		{
			return false;
		}

		if (!$this->Trade->canView())
		{
			return false;
		}

		if ($this->message_state == 'moderated')
		{
			if (
				!$visitor->hasPermission('dbtechShopTradePost', 'viewModerated')
				&& (!$visitor->user_id || $visitor->user_id != $this->user_id)
			)
			{
				$error = \XF::phraseDeferred('dbtech_shop_requested_trade_post_not_found');
				return false;
			}
		}
		else if ($this->message_state == 'deleted')
		{
			if (!$visitor->hasPermission('dbtechShopTradePost', 'viewDeleted'))
			{
				$error = \XF::phraseDeferred('dbtech_shop_requested_trade_post_not_found');
				return false;
			}
		}

		return true;
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUseInlineModeration(&$error = null): bool
	{
		$visitor = \XF::visitor();
		return ($visitor->user_id && $visitor->hasPermission('dbtechShopTradePost', 'inlineMod'));
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canEdit(&$error = null): bool
	{
		$visitor = \XF::visitor();

		if (!$visitor->user_id)
		{
			return false;
		}

		if ($visitor->user_id == $this->user_id)
		{
			return $visitor->hasPermission('dbtechShopTradePost', 'editOwn');
		}
		else
		{
			return $visitor->hasPermission('dbtechShopTradePost', 'editAny');
		}
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
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($type != 'soft' && !$visitor->hasPermission('dbtechShopTradePost', 'hardDeleteAny'))
		{
			return false;
		}

		if ($visitor->hasPermission('dbtechShopTradePost', 'deleteAny'))
		{
			return true;
		}

		return (
			(
				$this->Trade->isParticipant()
				&& $visitor->hasPermission('dbtechShopTradePost', 'manageOwn')
			)
			||
			(
				$visitor->user_id == $this->user_id
				&& $visitor->hasPermission('dbtechShopTradePost', 'deleteOwn')
			)
		);
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUndelete(&$error = null): bool
	{
		$visitor = \XF::visitor();
		return ($visitor->user_id && $visitor->hasPermission('dbtechShopTradePost', 'undelete'));
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canApproveUnapprove(&$error = null): bool
	{
		$visitor = \XF::visitor();
		return ($visitor->user_id && $visitor->hasPermission('dbtechShopTradePost', 'approveUnapprove'));
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canWarn(&$error = null): bool
	{
		$visitor = \XF::visitor();

		if ($this->warning_id
			|| !$this->user_id
			|| !$visitor->user_id
			|| $this->user_id == $visitor->user_id
			|| !$visitor->hasPermission('dbtechShopTradePost', 'warn')
		)
		{
			return false;
		}

		return ($this->User && $this->User->isWarnable());
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
	public function canReact(&$error = null): bool
	{
		$visitor = \XF::visitor();
		if (!$visitor->user_id)
		{
			return false;
		}

		if ($this->message_state != 'visible')
		{
			return false;
		}

		if ($this->user_id == $visitor->user_id)
		{
			$error = \XF::phraseDeferred('reacting_to_your_own_content_is_considered_cheating');
			return false;
		}

		return $visitor->hasPermission('dbtechShopTradePost', 'react');
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canComment(&$error = null): bool
	{
		$visitor = \XF::visitor();

		return (
			$this->message_state == 'visible'
			&& $visitor->user_id
			&& $visitor->hasPermission('dbtechShopTradePost', 'view')
			&& $visitor->hasPermission('dbtechShopTradePost', 'comment')
		);
	}

	/**
	 * @return bool
	 */
	public function canViewDeletedComments(): bool
	{
		return \XF::visitor()->hasPermission('dbtechShopTradePost', 'viewDeleted');
	}

	/**
	 * @return bool
	 */
	public function canViewModeratedComments(): bool
	{
		return \XF::visitor()->hasPermission('dbtechShopTradePost', 'viewModerated');
	}

	/**
	 * @return bool
	 */
	public function canSendModeratorActionAlert(): bool
	{
		$visitor = \XF::visitor();

		if (!$visitor->user_id || $visitor->user_id == $this->user_id)
		{
			return false;
		}

		if ($this->message_state != 'visible')
		{
			return false;
		}

		return (
			$visitor->hasPermission('dbtechShopTradePost', 'deleteAny')
			|| $visitor->hasPermission('dbtechShopTradePost', 'editAny')
		);
	}

	/**
	 * @return bool
	 */
	public function hasMoreComments(): bool
	{
		if ($this->comment_count > 3)
		{
			return true;
		}

		$visitor = \XF::visitor();

		$canViewDeleted = $visitor->hasPermission('dbtechShopTradePost', 'viewDeleted');
		$canViewModerated = $visitor->hasPermission('dbtechShopTradePost', 'viewModerated');

		if (!$canViewDeleted && !$canViewModerated)
		{
			return false;
		}

		$viewableCommentCount = 0;

		foreach ($this->latest_comment_ids AS $commentId => $state)
		{
			switch ($state[0])
			{
				case 'visible':
					$viewableCommentCount++;
					break;

				case 'moderated':
					if ($canViewModerated)
					{
						$viewableCommentCount++;
					}
					break;

				case 'deleted':
					if ($canViewDeleted)
					{
						$viewableCommentCount++;
					}
					break;
			}

			if ($viewableCommentCount > 3)
			{
				return true;
			}
		}

		return false;
	}

	/**
	 * @return bool
	 */
	public function isVisible(): bool
	{
		return ($this->message_state == 'visible');
	}

	/**
	 * @return bool
	 */
	public function isIgnored(): bool
	{
		return \XF::visitor()->isIgnoring($this->user_id);
	}

	/**
	 * @return bool
	 */
	public function canCleanSpam(): bool
	{
		return (\XF::visitor()->canCleanSpam() && $this->User && $this->User->isPossibleSpammer());
	}

	/**
	 * @return array
	 */
	public function getCommentIds(): array
	{
		return $this->db()->fetchAllColumn("
			SELECT trade_post_comment_id
			FROM xf_dbtech_shop_trade_post_comment
			WHERE trade_post_id = ?
			ORDER BY comment_date
		", $this->trade_post_id);
	}

	/**
	 * @return \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\TradePostComment>|null
	 */
	public function getLatestComments(): ?AbstractCollection
	{
		\XF::app()->repository(TradePostRepository::class)
			->addCommentsToTradePosts(
				\XF::app()->em()->getBasicCollection([$this->trade_post_id => $this])
			)
		;

		return $this->_getterCache['LatestComments'] ?? \XF::app()->em()->getBasicCollection([]);
	}

	/**
	 * @param array $latest
	 */
	public function setLatestComments(array $latest): void
	{
		$this->_getterCache['LatestComments'] = \XF::app()->em()->getBasicCollection($latest);
	}

	/**
	 * @param TradePostComment $comment
	 */
	public function commentAdded(TradePostComment $comment): void
	{
		$this->comment_count++;

		if (!$this->first_comment_date || $comment->comment_date < $this->first_comment_date)
		{
			$this->first_comment_date = $comment->comment_date;
		}

		if ($comment->comment_date > $this->last_comment_date)
		{
			$this->last_comment_date = $comment->comment_date;
		}

		$this->rebuildLatestCommentIds();

		unset($this->_getterCache['comment_ids']);
	}

	/**
	 * @param TradePostComment $comment
	 */
	public function commentRemoved(TradePostComment $comment): void
	{
		$this->comment_count--;

		if ($this->first_comment_date == $comment->comment_date)
		{
			if (!$this->comment_count)
			{
				$this->first_comment_date = 0;
			}
			else
			{
				$this->rebuildFirstCommentInfo();
			}
		}

		if ($this->last_comment_date == $comment->comment_date)
		{
			if (!$this->comment_count)
			{
				$this->last_comment_date = 0;
			}
			else
			{
				$this->rebuildLastCommentInfo();
			}
		}

		$this->rebuildLatestCommentIds();

		unset($this->_getterCache['comment_ids']);
	}

	/**
	 * @return bool
	 */
	public function rebuildCounters(): bool
	{
		if ($this->rebuildFirstCommentInfo())
		{
			$this->rebuildLastCommentInfo();
			$this->rebuildCommentCount();
		}

		// since this contains non-visible comments, we always have to rebuild
		$this->rebuildLatestCommentIds();

		return true;
	}

	/**
	 * @return bool
	 */
	public function rebuildFirstCommentInfo(): bool
	{
		$firstComment = $this->db()->fetchRow("
			SELECT trade_post_comment_id, comment_date, user_id, username
			FROM xf_dbtech_shop_trade_post_comment
			WHERE trade_post_id = ?
				AND message_state = 'visible'
			ORDER BY comment_date
			LIMIT 1
		", $this->trade_post_id);

		if (!$firstComment)
		{
			$this->comment_count = 0;
			$this->first_comment_date = 0;
			$this->last_comment_date = 0;
			return false;
		}
		else
		{
			$this->last_comment_date = $firstComment['comment_date'];
			return true;
		}
	}

	/**
	 * @return bool
	 */
	public function rebuildLastCommentInfo(): bool
	{
		$lastComment = $this->db()->fetchRow("
			SELECT trade_post_comment_id, comment_date, user_id, username
			FROM xf_dbtech_shop_trade_post_comment
			WHERE trade_post_id = ?
				AND message_state = 'visible'
			ORDER BY comment_date DESC
			LIMIT 1
		", $this->trade_post_id);

		if (!$lastComment)
		{
			$this->comment_count = 0;
			$this->first_comment_date = 0;
			$this->last_comment_date = 0;
			return false;
		}
		else
		{
			$this->last_comment_date = $lastComment['comment_date'];
			return true;
		}
	}

	/**
	 * @return bool|int|null
	 */
	public function rebuildCommentCount(): bool|int|null
	{
		$visibleComments = $this->db()->fetchOne("
			SELECT COUNT(*)
			FROM xf_dbtech_shop_trade_post_comment
			WHERE trade_post_id = ?
				AND message_state = 'visible'
		", $this->trade_post_id);

		$this->comment_count = $visibleComments;

		return $this->comment_count;
	}

	/**
	 *
	 */
	public function rebuildLatestCommentIds(): void
	{
		$this->latest_comment_ids = \XF::app()->repository(TradePostRepository::class)
			->getLatestCommentCache($this)
		;
	}

	/**
	 * @param $context
	 * @param $type
	 *
	 * @return array
	 */
	public function getBbCodeRenderOptions($context, $type): array
	{
		return [
			'entity' => $this,
			'user' => $this->User,
			'treatAsStructuredText' => true,
			'unfurls' => $this->Unfurls ?: [],
		];
	}

	/**
	 * @return array
	 */
	public function getUnfurls(): array
	{
		return $this->_getterCache['Unfurls'] ?? [];
	}

	/**
	 * @param array $unfurls
	 */
	public function setUnfurls(array $unfurls): void
	{
		$this->_getterCache['Unfurls'] = $unfurls;
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
		$route = $canonical ? 'canonical:dbtech-shop/trade-posts' : 'dbtech-shop/trade-posts';
		return \XF::app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	/**
	 * @return string|null
	 */
	public function getContentPublicRoute(): ?string
	{
		return 'dbtech-shop/trade-posts';
	}

	/**
	 * @param string $context
	 *
	 * @return Phrase
	 */
	public function getContentTitle(string $context = ''): Phrase
	{
		return \XF::phrase('dbtech_shop_trade_post_x', ['title' => $this->trade_post_id]);
	}

	/**
	 * @throws PrintableException
	 */
	protected function _postSave(): void
	{
		$visibilityChange = $this->isStateChanged('message_state', 'visible');
		$approvalChange = $this->isStateChanged('message_state', 'moderated');
		$deletionChange = $this->isStateChanged('message_state', 'deleted');

		if ($this->isUpdate())
		{
			if ($visibilityChange == 'enter')
			{
				$this->tradePostMadeVisible();

				if ($approvalChange)
				{
					$this->submitHamData();
				}
			}
			else if ($visibilityChange == 'leave')
			{
				$this->tradePostHidden();
			}

			if ($deletionChange == 'leave' && $this->DeletionLog)
			{
				$this->DeletionLog->delete();
			}

			if ($approvalChange == 'leave' && $this->ApprovalQueue)
			{
				$this->ApprovalQueue->delete();
			}
		}

		if ($approvalChange == 'enter')
		{
			$approvalQueue = $this->getRelationOrDefault('ApprovalQueue', false);
			$approvalQueue->content_date = $this->post_date;
			$approvalQueue->save();
		}
		else if ($deletionChange == 'enter' && !$this->DeletionLog)
		{
			$delLog = $this->getRelationOrDefault('DeletionLog', false);
			$delLog->setFromVisitor();
			$delLog->save();
		}

		if ($this->isUpdate() && $this->getOption('log_moderator'))
		{
			\XF::app()->logger()->logModeratorChanges('dbtech_shop_trade_post', $this);
		}
	}

	/**
	 *
	 */
	protected function tradePostMadeVisible()
	{
	}

	/**
	 * @param bool $hardDelete
	 */
	protected function tradePostHidden(bool $hardDelete = false): void
	{
		$alertRepo = \XF::app()->repository(UserAlertRepository::class);
		$alertRepo->fastDeleteAlertsForContent('dbtech_shop_trade_post', $this->trade_post_id);
		$alertRepo->fastDeleteAlertsForContent('dbtech_shop_trade_comment', $this->comment_ids);
	}

	/**
	 *
	 */
	protected function submitHamData(): void
	{
		/** @var ContentChecker $submitter */
		$submitter = \XF::app()->container('spam.contentHamSubmitter');
		$submitter->submitHam('dbtech_shop_trade_post', $this->trade_post_id);
	}

	/**
	 * @throws PrintableException
	 */
	protected function _postDelete(): void
	{
		if ($this->message_state == 'visible')
		{
			$this->tradePostHidden(true);
		}

		if ($this->message_state == 'deleted' && $this->DeletionLog)
		{
			$this->DeletionLog->delete();
		}

		if ($this->message_state == 'moderated' && $this->ApprovalQueue)
		{
			$this->ApprovalQueue->delete();
		}

		if ($this->getOption('log_moderator'))
		{
			\XF::app()->logger()->logModeratorAction('dbtech_shop_trade_post', $this, 'delete_hard');
		}

		$db = $this->db();
		$commentIds = $this->comment_ids;
		if ($commentIds)
		{
			$quotedIds = $db->quote($commentIds);

			$db->delete('xf_dbtech_shop_trade_post_comment', "trade_post_comment_id IN ($quotedIds)");
			$db->delete('xf_approval_queue', "content_id IN ($quotedIds) AND content_type = 'dbtech_shop_trade_comment'");
			$db->delete('xf_deletion_log', "content_id IN ($quotedIds) AND content_type = 'dbtech_shop_trade_comment'");
		}
	}

	/**
	 * @param string $reason
	 * @param User|null $byUser
	 *
	 * @return bool
	 * @throws PrintableException
	 */
	public function softDelete(string $reason = '', ?User $byUser = null): bool
	{
		$byUser = $byUser ?: \XF::visitor();

		if ($this->message_state == 'deleted')
		{
			return false;
		}

		$this->message_state = 'deleted';

		/** @var DeletionLog $deletionLog */
		$deletionLog = $this->getRelationOrDefault('DeletionLog');
		$deletionLog->setFromUser($byUser);
		$deletionLog->delete_reason = $reason;

		$this->save();

		return true;
	}

	/**
	 * @return TradePostComment
	 */
	public function getNewComment(): TradePostComment
	{
		$comment = \XF::app()->em()->create(TradePostComment::class);
		$comment->trade_post_id = $this->trade_post_id;

		return $comment;
	}

	/**
	 * @return string
	 */
	public function getNewContentState(): string
	{
		$visitor = \XF::visitor();

		if ($visitor->user_id && $visitor->hasPermission('dbtechShopTradePost', 'approveUnapprove'))
		{
			return 'visible';
		}

		if (!$visitor->hasPermission('general', 'submitWithoutApproval'))
		{
			return 'moderated';
		}

		return 'visible';
	}

	/**
	 * @param EntityResult $result
	 * @param int $verbosity
	 * @param array $options
	 *
	 * @api-out str $username
	 * @api-out bool $can_edit
	 * @api-out bool $can_soft_delete
	 * @api-out bool $can_hard_delete
	 * @api-out bool $can_react
	 * @api-out User $Trade <cond> If requested by context, the user this trade post was left for.
	 * @api-out TradePostComment[] $LatestComments <cond> If requested, the most recent comments on this trade post.
	 * @api-see XF\Entity\ReactionTrait::addReactionStateToApiResult
	 */
	protected function setupApiResultData(
		EntityResult $result,
		$verbosity = self::VERBOSITY_NORMAL,
		array $options = []
	): void
	{
		$result->username = $this->User ? $this->User->username : $this->username;

		if (!empty($options['with_trade']))
		{
			$result->includeRelation('Trade');
		}

		if (!empty($options['with_latest']))
		{
			$result->includeGetter('LatestComments');
		}

		$this->addReactionStateToApiResult($result);

		$result->can_edit = $this->canEdit();
		$result->can_soft_delete = $this->canDelete();
		$result->can_hard_delete = $this->canDelete('hard');
		$result->can_react = $this->canReact();
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_trade_post';
		$structure->shortName = 'DBTech\Shop:TradePost';
		$structure->contentType = 'dbtech_shop_trade_post';
		$structure->primaryKey = 'trade_post_id';
		$structure->columns = [
			'trade_post_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'trade_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'user_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'username' => ['type' => self::STR, 'maxLength' => 50,
				'required' => 'please_enter_valid_name',
			],
			'post_date' => ['type' => self::UINT, 'required' => true, 'default' => \XF::$time, 'api' => true],
			'message' => ['type' => self::STR,
				'required' => 'please_enter_valid_message', 'api' => true,
			],
			'ip_id' => ['type' => self::UINT, 'default' => 0],
			'message_state' => ['type' => self::STR, 'default' => 'visible',
				'allowedValues' => ['visible', 'moderated', 'deleted'], 'api' => true,
			],
			'attach_count' => ['type' => self::UINT, 'max' => 65535, 'forced' => true, 'default' => 0],
			'warning_id' => ['type' => self::UINT, 'default' => 0],
			'warning_message' => ['type' => self::STR, 'default' => '', 'api' => true],
			'comment_count' => ['type' => self::UINT, 'forced' => true, 'default' => 0, 'api' => true],
			'first_comment_date' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'last_comment_date' => ['type' => self::UINT, 'default' => 0, 'api' => true],
			'latest_comment_ids' => ['type' => self::JSON_ARRAY, 'default' => []],
			'embed_metadata' => ['type' => self::JSON_ARRAY, 'nullable' => true, 'default' => null],
		];
		$structure->behaviors = [
			'XF:Reactable' => ['stateField' => 'message_state'],
			'XF:ReactableContainer' => [
				'childContentType' => 'dbtech_shop_trade_comment',
				'childIds' => function ($tradePost) { return $tradePost->comment_ids; },
				'stateField' => 'message_state',
			],
			'XF:Indexable' => [
				'checkForUpdates' => ['message', 'trade_id', 'user_id', 'post_date', 'message_state'],
			],
			'XF:IndexableContainer' => [
				'childContentType' => 'dbtech_shop_trade_comment',
				'childIds' => function ($tradePost) { return $tradePost->comment_ids; },
				'checkForUpdates' => ['trade_id', 'message_state'],
			],
			'XF:NewsFeedPublishable' => [
				'usernameField' => 'username',
				'dateField' => 'post_date',
			],
		];
		$structure->getters = [
			'comment_ids' => true,
			'LatestComments' => true,
			'Unfurls' => true,
		];
		$structure->relations = [
			'Trade' => [
				'entity' => Trade::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['trade_id', '=', '$trade_id'],
				],
				'primary' => true,
			],
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
				'api' => true,
			],
			'DeletionLog' => [
				'entity' => DeletionLog::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'dbtech_shop_trade_post'],
					['content_id', '=', '$trade_post_id'],
				],
				'primary' => true,
			],
			'ApprovalQueue' => [
				'entity' => ApprovalQueue::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'dbtech_shop_trade_post'],
					['content_id', '=', '$trade_post_id'],
				],
				'primary' => true,
			],
			'Comments' => [
				'entity' => TradePostComment::class,
				'type' => self::TO_MANY,
				'conditions' => 'trade_post_id',
				'primary' => true,
			],
		];
		$structure->options = [
			'log_moderator' => true,
		];

		$structure->withAliases = [
			'full' => [
				'User',
				function (): ?string
				{
					$userId = \XF::visitor()->user_id;
					if ($userId)
					{
						return 'Reactions|' . $userId;
					}

					return null;
				},
			],
			'fullTrade' => ['full', 'Trade'],
			'api' => [
				'User',
				'User.api',
				function ($withParams): ?array
				{
					if (!empty($withParams['trade']))
					{
						return ['Trade.api'];
					}

					return null;
				},
			],
		];

		static::addReactableStructureElements($structure);

		return $structure;
	}
}