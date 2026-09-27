<?php

namespace DBTech\Shop\Entity;

use XF\Db\Exception;
use XF\Entity\ConversationMaster;
use XF\Entity\LinkableInterface;
use XF\Entity\User;
use XF\Entity\ViewableInterface;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Phrase;

/**
 * COLUMNS
 * @property int $trade_id
 * @property int $creator_user_id
 * @property string $creator_username
 * @property int $recipient_user_id
 * @property string $recipient_username
 * @property int $created_date
 * @property int $updated_date
 * @property string $trade_state
 * @property bool $creator_accepted
 * @property bool $recipient_accepted
 * @property int $conversation_id
 *
 * GETTERS
 * @property-read Phrase $title
 * @property-read string $other_username
 *
 * RELATIONS
 * @property-read \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\TradeOffer> $Offers
 * @property-read User|null $Creator
 * @property-read User|null $Recipient
 * @property-read ConversationMaster|null $Conversation
 */
class Trade extends Entity implements LinkableInterface, ViewableInterface
{
	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::phrase('dbtech_shop_trade_x_y_with_z', [
			'tradeId' => $this->trade_id,
			'creator' => $this->Creator->username,
			'recipient' => $this->Recipient->username,
		]);
	}

	/**
	 * @return string
	 */
	public function getOtherUsername(): string
	{
		return ($this->creator_user_id == \XF::visitor()->user_id
			? $this->recipient_username
			: $this->creator_username
		);
	}

	/**
	 * @return bool
	 */
	public function canView(): bool
	{
		return $this->isParticipant();
	}

	/**
	 * @return bool
	 */
	public function canEdit(): bool
	{
		return (
			($this->trade_state != 'pending' || $this->creator_user_id == \XF::visitor()->user_id)
			&& $this->trade_state != 'accepted'
		);
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canAcceptInvite(&$error = null): bool
	{
		if ($this->trade_state != 'pending')
		{
			$error = \XF::phraseDeferred('dbtech_shop_trade_not_pending');
			return false;
		}

		if ($this->recipient_user_id != \XF::visitor()->user_id)
		{
			$error = \XF::phraseDeferred('dbtech_shop_not_your_trade_to_accept_invite');
			return false;
		}

		return true;
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canAccept(&$error = null): bool
	{
		if (!in_array($this->trade_state, ['open', 'awaiting_accept']))
		{
			$error = \XF::phraseDeferred('dbtech_shop_trade_not_awaiting_accept');
			return false;
		}

		if (!$this->isParticipant())
		{
			$error = \XF::phraseDeferred('dbtech_shop_not_your_trade_to_accept');
			return false;
		}

		return true;
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canCancel(&$error = null): bool
	{
		if (!$this->isParticipant())
		{
			$error = \XF::phraseDeferred('dbtech_shop_not_your_trade_to_cancel');
			return false;
		}

		if (in_array($this->trade_state, ['accepted', 'cancelled']))
		{
			$error = \XF::phraseDeferred('dbtech_shop_trade_finalised');
			return false;
		}

		return true;
	}

	/**
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canViewPostsInTrade(&$error = null): bool
	{
		return $this->isParticipant() && \XF::visitor()->hasPermission('dbtechShopTradePost', 'view');
	}

	/**
	 * @return bool
	 */
	public function canViewDeletedPostsInTrade(): bool
	{
		return \XF::visitor()->hasPermission('dbtechShopTradePost', 'viewDeleted');
	}

	/**
	 * @return bool
	 */
	public function canViewModeratedPostsInTrade(): bool
	{
		return \XF::visitor()->hasPermission('dbtechShopTradePost', 'viewModerated');
	}

	/**
	 * @return bool
	 */
	public function canPostInTrade(): bool
	{
		$visitor = \XF::visitor();

		return ($visitor->user_id
			&& $visitor->hasPermission('dbtechShopTradePost', 'view')
			&& $visitor->hasPermission('dbtechShopTradePost', 'post')
			&& $this->isParticipant()
		);
	}

	/**
	 * @return bool
	 */
	public function isParticipant(): bool
	{
		$visitor = \XF::visitor();

		return ($this->creator_user_id == $visitor->user_id
			|| $this->recipient_user_id == $visitor->user_id
		);
	}

	/**
	 * @return bool
	 */
	public function hasAccepted(): bool
	{
		return ($this->creator_user_id == \XF::visitor()->user_id
			? $this->creator_accepted
			: $this->recipient_accepted
		);
	}

	/**
	 * @return bool
	 */
	public function hasBothUsersAccepted(): bool
	{
		return ($this->creator_accepted
			&& $this->recipient_accepted
		);
	}

	/**
	 * @param string $contentType
	 * @param int $contentId
	 * @param int $quantity
	 *
	 * @return TradeOffer
	 * @throws \InvalidArgumentException
	 */
	public function getNewTradeOffer(string $contentType, int $contentId, int $quantity = 1): TradeOffer
	{
		$tradeOffer = \XF::app()->em()->create(TradeOffer::class);

		$tradeOffer->trade_id = $this->trade_id;
		$tradeOffer->hydrateRelation('Trade', $this);

		$tradeOffer->user_id = \XF::visitor()->user_id;
		$tradeOffer->content_type = $contentType;
		$tradeOffer->content_id = $contentId;
		$tradeOffer->quantity = $quantity;

		return $tradeOffer;
	}

	/**
	 * @return TradePost|Entity
	 */
	public function getNewTradePost(): Entity|TradePost
	{
		$tradePost = \XF::app()->em()->create(TradePost::class);
		$tradePost->trade_id = $this->trade_id;

		return $tradePost;
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
		$route = $canonical ? 'canonical:dbtech-shop/trades' : 'dbtech-shop/trades';
		return \XF::app()->router('public')->buildLink($route, $this, $extraParams, $hash);
	}

	/**
	 * @return string|null
	 */
	public function getContentPublicRoute(): ?string
	{
		return 'dbtech-shop/trades';
	}

	/**
	 * @param string $context
	 *
	 * @return Phrase
	 */
	public function getContentTitle(string $context = ''): Phrase
	{
		return \XF::phrase('dbtech_shop_trade_x', ['title' => $this->trade_id]);
	}

	/**
	 * @param int $amount
	 * @param int|null $userId
	 *
	 * @throws Exception
	 */
	protected function adjustUserPendingTradeCountIfNeeded(int $amount, ?int $userId = null): void
	{
		if ($userId === null)
		{
			$userId = $this->creator_user_id;
		}

		if ($userId)
		{
			$this->db()->query('
				UPDATE xf_user
				SET dbtech_shop_pendingtrades = GREATEST(0, CAST(dbtech_shop_pendingtrades AS SIGNED) + ?)
				WHERE user_id = ?
			', [$amount, $userId]);
		}
	}

	/**
	 *
	 */
	protected function _preSave(): void
	{
		if ($this->isUpdate()
			&& !$this->isChanged('updated_date')
		)
		{
			$this->updated_date = \XF::$time;
		}
	}

	/**
	 * @throws Exception
	 */
	protected function _postSave(): void
	{
		if ($this->isInsert())
		{
			$this->adjustUserPendingTradeCountIfNeeded(1, $this->creator_user_id);
			$this->adjustUserPendingTradeCountIfNeeded(1, $this->recipient_user_id);
		}
		else
		{
			// check for entering accepted/cancelled
			$acceptedChange = $this->isStateChanged('trade_state', 'accepted');
			$cancelledChange = $this->isStateChanged('trade_state', 'cancelled');

			if ($acceptedChange == 'enter')
			{
				$this->adjustUserPendingTradeCountIfNeeded(-1, $this->creator_user_id);
				$this->adjustUserPendingTradeCountIfNeeded(-1, $this->recipient_user_id);
			}
			else if ($cancelledChange == 'enter')
			{
				$this->adjustUserPendingTradeCountIfNeeded(-1, $this->creator_user_id);
				$this->adjustUserPendingTradeCountIfNeeded(-1, $this->recipient_user_id);
			}
		}
	}

	/**
	 * @param Structure $structure
	 *
	 * @return Structure
	 */
	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_dbtech_shop_trade';
		$structure->shortName = 'DBTech\Shop:Trade';
		$structure->primaryKey = 'trade_id';
		$structure->columns = [
			'trade_id'           => ['type' => self::UINT, 'autoIncrement' => true],
			'creator_user_id'    => ['type' => self::UINT, 'required' => true],
			'creator_username'   => ['type' => self::STR, 'maxLength' => 50],
			'recipient_user_id'  => ['type' => self::UINT, 'required' => true],
			'recipient_username' => ['type' => self::STR, 'maxLength' => 50],
			'created_date'       => ['type' => self::UINT, 'default' => \XF::$time],
			'updated_date'       => ['type' => self::UINT, 'default' => \XF::$time],
			'trade_state'             => [
				'type'          => self::STR,
				'default'       => 'pending',
				'allowedValues' => ['pending', 'open', 'awaiting_accept', 'accepted', 'cancelled'],
			],
			'creator_accepted'   => ['type' => self::BOOL, 'default' => false],
			'recipient_accepted' => ['type' => self::BOOL, 'default' => false],
			'conversation_id'    => ['type' => self::UINT, 'default' => 0],
		];
		$structure->getters = [
			'title' => true,
			'other_username' => true,
		];
		$structure->relations = [
			'Offers' => [
				'entity' => TradeOffer::class,
				'type' => self::TO_MANY,
				'conditions' => 'trade_id',
				'with' => ['Trade', 'User'],
			],
			'Creator' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$creator_user_id'],
				],
				'primary' => true,
			],
			'Recipient' => [
				'entity' => User::class,
				'type' => self::TO_ONE,
				'conditions' => [
					['user_id', '=', '$recipient_user_id'],
				],
				'primary' => true,
			],
			'Conversation' => [
				'entity' => ConversationMaster::class,
				'type' => self::TO_ONE,
				'conditions' => 'conversation_id',
				'primary' => true,
			],
		];

		return $structure;
	}
}