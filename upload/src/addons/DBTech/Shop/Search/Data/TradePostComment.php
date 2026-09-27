<?php

namespace DBTech\Shop\Search\Data;

use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;

class TradePostComment extends AbstractData
{
	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith($forView = false): array
	{
		$get = ['TradePost'];
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
		/** @var \DBTech\Shop\Entity\TradePostComment $entity */

		if (!$entity->TradePost || !$entity->TradePost->Trade)
		{
			return null;
		}

		$index = IndexRecord::create('dbtech_shop_trade_comment', $entity->trade_post_comment_id, [
			'title' => '',
			'message' => $entity->message,
			'date' => $entity->comment_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->trade_post_id,
			'metadata' => $this->getMetaData($entity),
		]);

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		return $index;
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePostComment $entity
	 *
	 * @return array
	 */
	protected function getMetaData(\DBTech\Shop\Entity\TradePostComment $entity): array
	{
		$metadata = [];

		$metadata['trade_owner'] = $entity->TradePost->Trade->creator_user_id;

		return $metadata;
	}

	/**
	 * @param MetadataStructure $structure
	 */
	public function setupMetadataStructure(MetadataStructure $structure): void
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
		/** @var \DBTech\Shop\Entity\TradePostComment $entity */

		return $entity->comment_date;
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
			'comment' => $entity,
			'options' => $options,
		];
	}
}