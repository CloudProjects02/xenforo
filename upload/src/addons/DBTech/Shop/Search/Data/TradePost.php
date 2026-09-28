<?php

namespace DBTech\Shop\Search\Data;

use XF\Search\Data\AbstractData;
use XF\Mvc\Entity\Entity;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Util\Arr;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\Search\Data
 */
class TradePost extends AbstractData
{
	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith($forView = false): array
	{
		$get = ['Trade'];
		if ($forView)
		{
			$get[] = 'User';
		}

		return $get;
	}
	
	/**
	 * @param Entity $entity
	 *
	 * @return IndexRecord|null
	 */
	public function getIndexData(Entity $entity): ?IndexRecord
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */

		if (!$entity->Trade)
		{
			return null;
		}

		$index = IndexRecord::create('dbtech_shop_trade_post', $entity->trade_post_id, [
			'title' => '',
			'message' => $entity->message,
			'date' => $entity->post_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->trade_post_id,
			'metadata' => $this->getMetaData($entity)
		]);

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		return $index;
	}
	
	/**
	 * @param \DBTech\Shop\Entity\TradePost $entity
	 *
	 * @return array
	 */
	protected function getMetaData(\DBTech\Shop\Entity\TradePost $entity): array
	{
		$metadata = [];

		$metadata['trade_owner'] = $entity->Trade->creator_user_id;

		return $metadata;
	}
	
	/**
	 * @param MetadataStructure $structure
	 */
	public function setupMetadataStructure(MetadataStructure $structure)
	{
		$structure->addField('trade_owner', MetadataStructure::INT);
	}
	
	/**
	 * @param Entity $entity
	 *
	 * @return int
	 */
	public function getResultDate(Entity $entity): int
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		return $entity->post_date;
	}
	
	/**
	 * @return array
	 */
	public function getSearchableContentTypes(): array
	{
		return ['dbtech_shop_trade_post', 'dbtech_shop_trade_comment'];
	}
	
	/**
	 * @param Entity $entity
	 * @param array $options
	 *
	 * @return array
	 */
	public function getTemplateData(Entity $entity, array $options = []): array
	{
		return [
			'tradePost' => $entity,
			'options' => $options
		];
	}
	
	/**
	 * @return array|null
	 */
	public function getSearchFormTab(): ?array
	{
		/** @var \DBTech\Shop\XF\Entity\User $visitor */
		$visitor = \XF::visitor();
		if (!$visitor->canViewDbtechShopTradePosts())
		{
			return null;
		}

		return [
			'title' => \XF::phrase('dbtech_shop_search_trade_posts'),
			'order' => 1000
		];
	}
	
	/**
	 * @return string|null
	 */
	public function getSectionContext(): ?string
	{
		return 'dbtechShop';
	}
	
	/**
	 * @param \XF\Search\Query\Query $query
	 * @param \XF\Http\Request $request
	 * @param array $urlConstraints
	 */
	public function applyTypeConstraintsFromInput(\XF\Search\Query\Query $query, \XF\Http\Request $request, array &$urlConstraints)
	{
		$tradeOwner = $request->filter('c.trade_owners', 'str');
		if ($tradeOwner)
		{
			$users = Arr::stringToArray($tradeOwner, '/,\s*/');
			if ($users)
			{
				/** @var \XF\Repository\User $userRepo */
				$userRepo = \XF::repository('XF:User');
				$matchedUsers = $userRepo->getUsersByNames($users, $notFound);
				if ($notFound)
				{
					$query->error(
						'users',
						\XF::phrase('following_members_not_found_x', ['members' => implode(', ', $notFound)])
					);
				}
				else
				{
					$query->withMetadata('trade_owner', $matchedUsers->keys());
					$urlConstraints['trade_owners'] = implode(', ', $users);
				}
			}
		}
	}
	
	/**
	 * @param Entity $entity
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUseInlineModeration(Entity $entity, &$error = null): bool
	{
		/** @var \DBTech\Shop\Entity\TradePost $entity */
		return $entity->canUseInlineModeration($error);
	}
}