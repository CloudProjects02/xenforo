<?php

namespace DBTech\Shop\Entity;

use XF\Api\Result\EntityResult;
use XF\BbCode\RenderableContentInterface;
use XF\Entity\ApprovalQueue;
use XF\Entity\DeletionLog;
use XF\Entity\LinkableInterface;
use XF\Entity\ReactionTrait;
use XF\Entity\User;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;
use XF\PrintableException;
use XF\Repository\UserAlertRepository;
use XF\Spam\ContentChecker;

/**
 * COLUMNS
 * @property int|null $trade_post_comment_id
 * @property int $trade_post_id
 * @property int $user_id
 * @property string $username
 * @property int $comment_date
 * @property string $message
 * @property int $ip_id
 * @property string $message_state
 * @property int $warning_id
 * @property string $warning_message
 * @property array|null $embed_metadata
 * @property int $reaction_score
 * @property array $reactions_
 * @property array $reaction_users_
 *
 * GETTERS
 * @property-read array $Unfurls
 * @property mixed $reactions
 * @property mixed $reaction_users
 *
 * RELATIONS
 * @property-read User|null $User
 * @property-read TradePost|null $TradePost
 * @property-read DeletionLog|null $DeletionLog
 * @property-read ApprovalQueue|null $ApprovalQueue
 * @property-read \XF\Mvc\Entity\AbstractCollection<\XF\Entity\ReactionContent> $Reactions
 */
class TradePostComment extends Entity implements LinkableInterface, RenderableContentInterface, ViewableInterface
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

		/** @var TradePost $tradePost */
		$tradePost = $this->TradePost;
		if (!$tradePost)
		{
			return false;
		}

		if ($this->message_state == 'moderated')
		{
			if (
				!$tradePost->canViewModeratedComments()
				&& (!$visitor->user_id || $visitor->user_id != $this->user_id)
			)
			{
				$error = \XF::phraseDeferred('requested_comment_not_found');
				return false;
			}
		}
		else if ($this->message_state == 'deleted')
		{
			if (!$tradePost->canViewDeletedComments())
			{
				$error = \XF::phraseDeferred('requested_comment_not_found');
				return false;
			}
		}

		return $tradePost->canView($error);
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
				$this->TradePost
				&& $this->TradePost->Trade->isParticipant()
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
			$this->TradePost->canSendModeratorActionAlert()
			&& $this->message_state == 'visible'
		);
	}

	/**
	 * @return bool
	 */
	public function isVisible(): bool
	{
		return (
			$this->message_state == 'visible'
			&& $this->TradePost
			&& $this->TradePost->message_state == 'visible'
		);
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
	public function isLastComment(): bool
	{
		return (
			$this->TradePost
			&& $this->TradePost->last_comment_date == $this->comment_date
		);
	}

	/**
	 * @return bool
	 */
	public function canCleanSpam(): bool
	{
		return (\XF::visitor()->canCleanSpam() && $this->User && $this->User->isPossibleSpammer());
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
		$route = $canonical ? 'canonical:dbtech-shop/trade-posts/comments' : 'dbtech-shop/trade-posts/comments';
		return \XF::app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	/**
	 * @return string|null
	 */
	public function getContentPublicRoute(): ?string
	{
		return 'dbtech-shop/trade-posts/comments';
	}

	/**
	 * @param string $context
	 *
	 * @return Phrase
	 */
	public function getContentTitle(string $context = ''): Phrase
	{
		return \XF::phrase('dbtech_shop_trade_post_comment_x', ['title' => $this->trade_post_comment_id]);
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
				$this->commentMadeVisible();

				if ($approvalChange)
				{
					$this->submitHamData();
				}
			}
			else if ($visibilityChange == 'leave')
			{
				$this->commentHidden();
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
			$approvalQueue->content_date = $this->comment_date;
			$approvalQueue->save();
		}
		else if ($deletionChange == 'enter' && !$this->DeletionLog)
		{
			$delLog = $this->getRelationOrDefault('DeletionLog', false);
			$delLog->setFromVisitor();
			$delLog->save();
		}

		$this->updateTradePostRecord();

		if ($this->isUpdate() && $this->getOption('log_moderator'))
		{
			\XF::app()->logger()->logModeratorChanges('dbtech_shop_trade_comment', $this);
		}
	}

	/**
	 *
	 */
	protected function commentMadeVisible()
	{
	}

	/**
	 * @param bool $hardDelete
	 */
	protected function commentHidden(bool $hardDelete = false): void
	{
		$alertRepo = \XF::app()->repository(UserAlertRepository::class);
		$alertRepo->fastDeleteAlertsForContent('dbtech_shop_trade_comment', $this->trade_post_comment_id);
	}

	/**
	 * @throws PrintableException
	 */
	protected function updateTradePostRecord(): void
	{
		if (!$this->TradePost || !$this->TradePost->exists())
		{
			return;
		}

		$visibilityChange = $this->isStateChanged('message_state', 'visible');
		if ($visibilityChange == 'enter')
		{
			$this->TradePost->commentAdded($this);
			$this->TradePost->save();
		}
		else if ($visibilityChange == 'leave')
		{
			$this->TradePost->commentRemoved($this);
			$this->TradePost->save();
		}
	}

	/**
	 *
	 */
	protected function submitHamData(): void
	{
		/** @var ContentChecker $submitter */
		$submitter = \XF::app()->container('spam.contentHamSubmitter');
		$submitter->submitHam('dbtech_shop_trade_comment', $this->trade_post_comment_id);
	}

	/**
	 * @throws PrintableException
	 */
	protected function _postDelete(): void
	{
		if ($this->message_state == 'visible')
		{
			$this->commentHidden(true);
		}

		if ($this->TradePost && $this->message_state == 'visible')
		{
			$this->TradePost->commentRemoved($this);
			$this->TradePost->save();
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
			\XF::app()->logger()->logModeratorAction('dbtech_shop_trade_comment', $this, 'delete_hard');
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
	 * @param EntityResult $result
	 * @param int $verbosity
	 * @param array $options
	 *
	 * @api-out str $username
	 * @api-out bool $can_edit
	 * @api-out bool $can_soft_delete
	 * @api-out bool $can_hard_delete
	 * @api-out bool $can_react
	 * @api-out TradePost $TradePost <cond> If requested by context, the trade post this comment relates to.
	 *
	 * @api-see XF\Entity\ReactionTrait::addReactionStateToApiResult
	 */
	protected function setupApiResultData(
		EntityResult $result,
		$verbosity = self::VERBOSITY_NORMAL,
		array $options = []
	): void
	{
		$result->username = $this->User ? $this->User->username : $this->username;

		if (!empty($options['with_post']))
		{
			$result->includeRelation('TradePost', self::VERBOSITY_NORMAL, [
				'with_trade' => true,
			]);
		}

		$this->addReactionStateToApiResult($result);

		$result->can_edit = $this->canEdit();
		$result->can_soft_delete = $this->canDelete();
		$result->can_hard_delete = $this->canDelete('hard');
		$result->can_react = $this->canReact();
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_trade_post_comment';
		$structure->shortName = 'DBTech\Shop:TradePostComment';
		$structure->contentType = 'dbtech_shop_trade_comment';
		$structure->primaryKey = 'trade_post_comment_id';
		$structure->columns = [
			'trade_post_comment_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
			'trade_post_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'user_id' => ['type' => self::UINT, 'required' => true, 'api' => true],
			'username' => ['type' => self::STR, 'maxLength' => 50,
				'required' => 'please_enter_valid_name',
			],
			'comment_date' => ['type' => self::UINT, 'required' => true, 'default' => \XF::$time, 'api' => true],
			'message' => ['type' => self::STR,
				'required' => 'please_enter_valid_message', 'api' => true,
			],
			'ip_id' => ['type' => self::UINT, 'default' => 0],
			'message_state' => ['type' => self::STR, 'default' => 'visible',
				'allowedValues' => ['visible', 'moderated', 'deleted'], 'api' => true,
			],
			'warning_id' => ['type' => self::UINT, 'default' => 0],
			'warning_message' => ['type' => self::STR, 'default' => '', 'api' => true],
			'embed_metadata' => ['type' => self::JSON_ARRAY, 'nullable' => true, 'default' => null],
		];
		$structure->behaviors = [
			'XF:Reactable' => ['stateField' => 'message_state'],
			'XF:Indexable' => [
				'checkForUpdates' => ['message', 'user_id', 'comment_date', 'message_state'],
			],
			'XF:NewsFeedPublishable' => [
				'usernameField' => 'username',
				'dateField' => 'comment_date',
			],
		];
		$structure->getters = [
			'Unfurls' => true,
		];
		$structure->relations = [
			'User' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => 'user_id',
				'primary' => true,
				'api' => true,
			],
			'TradePost' => [
				'entity' => TradePost::class,
				'type' => self::TO_ONE,
				'conditions' => 'trade_post_id',
				'primary' => true,
			],
			'DeletionLog' => [
				'entity' => DeletionLog::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'dbtech_shop_trade_comment'],
					['content_id', '=', '$trade_post_comment_id'],
				],
				'primary' => true,
			],
			'ApprovalQueue' => [
				'entity' => ApprovalQueue::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['content_type', '=', 'dbtech_shop_trade_comment'],
					['content_id', '=', '$trade_post_comment_id'],
				],
				'primary' => true,
			],
		];
		$structure->options = [
			'log_moderator' => true,
		];
		$structure->defaultWith = ['TradePost'];

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
			'api' => [
				'User',
				'User.api',
				function ($withParams): ?array
				{
					if (!empty($withParams['post']))
					{
						return ['TradePost.api|trade'];
					}

					return null;
				},
			],
		];

		static::addReactableStructureElements($structure);

		return $structure;
	}
}