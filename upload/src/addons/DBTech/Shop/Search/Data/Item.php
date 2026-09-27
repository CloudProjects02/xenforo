<?php

namespace DBTech\Shop\Search\Data;

use DBTech\Shop\Entity\Category;
use DBTech\Shop\Repository\CategoryRepository;
use DBTech\Shop\Repository\ItemPrefixRepository;
use DBTech\Shop\XF\Entity\User;
use XF\Http\Request;
use XF\Mvc\Entity\Entity;
use XF\Search\Data\AbstractData;
use XF\Search\IndexRecord;
use XF\Search\MetadataStructure;
use XF\Search\Query\MetadataConstraint;
use XF\Search\Query\Query;
use XF\Tree;

class Item extends AbstractData
{
	/**
	 * @param bool $forView
	 *
	 * @return array
	 */
	public function getEntityWith($forView = false): array
	{
		$get = ['Category'];
		if ($forView)
		{
			$get[] = 'User';

			$visitor = \XF::visitor();
			$get[] = 'Permissions|' . $visitor->permission_combination_id;
			$get[] = 'Category.Permissions|' . $visitor->permission_combination_id;
		}

		return $get;
	}

	/**
	 * @param Entity $entity
	 * @return IndexRecord
	 */
	public function getIndexData(Entity $entity): IndexRecord
	{
		/** @var \DBTech\Shop\Entity\Item $entity */

		$index = IndexRecord::create('dbtech_shop_item', $entity->item_id, [
			'title' => $entity->title,
			'message' => $entity->description,
			'date' => $entity->creation_date,
			'user_id' => $entity->user_id,
			'discussion_id' => $entity->discussion_thread_id,
			'metadata' => $this->getMetaData($entity),
		]);

		if (!$entity->isVisible())
		{
			$index->setHidden();
		}

		if ($entity->tags)
		{
			$index->indexTags($entity->tags);
		}

		return $index;
	}

	/**
	 * @param \DBTech\Shop\Entity\Item $entity
	 *
	 * @return array
	 */
	protected function getMetaData(\DBTech\Shop\Entity\Item $entity): array
	{
		$metadata = [
			'itemcat' => $entity->category_id,
			'item' => $entity->item_id,
		];
		if ($entity->prefix_id)
		{
			$metadata['itemprefix'] = $entity->prefix_id;
		}

		return $metadata;
	}

	/**
	 * @param MetadataStructure $structure
	 */
	public function setupMetadataStructure(MetadataStructure $structure): void
	{
		$structure->addField('itemcat', MetadataStructure::INT);
		$structure->addField('item', MetadataStructure::INT);
		$structure->addField('itemprefix', MetadataStructure::INT);
	}

	/**
	 * @param Entity $entity
	 *
	 * @return int
	 */
	public function getResultDate(Entity $entity): int
	{
		/** @var \DBTech\Shop\Entity\Item $entity */
		return $entity->creation_date;
	}

	/**
	 * @param Entity $entity
	 * @param array $options
	 * @return array
	 */
	public function getTemplateData(Entity $entity, array $options = []): array
	{
		return [
			'item' => $entity,
			'options' => $options,
		];
	}

	/**
	 * @param Entity $entity
	 * @param null $error
	 *
	 * @return bool
	 */
	public function canUseInlineModeration(Entity $entity, &$error = null): bool
	{
		/** @var \DBTech\Shop\Entity\Item $entity */
		return $entity->canUseInlineModeration($error);
	}

	/**
	 * @return array
	 */
	public function getSearchableContentTypes(): array
	{
		return ['dbtech_shop_item'];
	}

	/**
	 * @return array|null
	 */
	public function getSearchFormTab(): ?array
	{
		/** @var User $visitor */
		$visitor = \XF::visitor();
		if (!method_exists($visitor, 'canViewDbtechShopItems') || !$visitor->canViewDbtechShopItems())
		{
			return null;
		}

		return [
			'title' => \XF::phrase('dbtech_shop_search_items'),
			'order' => 300,
		];
	}

	/**
	 * @return null|string
	 */
	public function getSectionContext(): ?string
	{
		return 'dbtech-shop';
	}

	/**
	 * @return array
	 */
	public function getSearchFormData(): array
	{
		$prefixListData = $this->getPrefixListData();

		return [
			'prefixGroups' => $prefixListData['prefixGroups'],
			'prefixesGrouped' => $prefixListData['prefixesGrouped'],

			'categoryTree' => $this->getSearchableCategoryTree(),
		];
	}

	/**
	 * @return Tree
	 */
	protected function getSearchableCategoryTree(): Tree
	{
		$categoryRepo = \XF::app()->repository(CategoryRepository::class);
		return $categoryRepo->createCategoryTree($categoryRepo->getViewableCategories());
	}

	/**
	 * @return array
	 */
	protected function getPrefixListData(): array
	{
		$prefixRepo = \XF::app()->repository(ItemPrefixRepository::class);
		return $prefixRepo->getPrefixListData();
	}

	/**
	 * @param Query $query
	 * @param Request $request
	 * @param array $urlConstraints
	 */
	public function applyTypeConstraintsFromInput(Query $query, Request $request, array &$urlConstraints): void
	{
		$prefixes = $request->filter('c.prefixes', 'array-uint');
		$prefixes = array_unique($prefixes);
		if ($prefixes && reset($prefixes))
		{
			$query->withMetadata('itemprefix', $prefixes);
		}
		else
		{
			unset($urlConstraints['prefixes']);
		}

		$categoryIds = $request->filter('c.categories', 'array-uint');
		$categoryIds = array_unique($categoryIds);
		if ($categoryIds && reset($categoryIds))
		{
			if ($request->filter('c.child_categories', 'bool'))
			{
				$categoryTree = $this->getSearchableCategoryTree();

				$searchCategoryIds = array_fill_keys($categoryIds, true);
				$categoryTree->traverse(function ($id, $category) use (&$searchCategoryIds)
				{
					if (isset($searchCategoryIds[$id]) || isset($searchCategoryIds[$category->parent_category_id]))
					{
						$searchCategoryIds[$id] = true;
					}
				});

				$categoryIds = array_unique(array_keys($searchCategoryIds));
			}
			else
			{
				unset($urlConstraints['child_categories']);
			}

			$query->withMetadata('itemcat', $categoryIds);
		}
		else
		{
			unset($urlConstraints['categories'], $urlConstraints['child_categories']);
		}
	}

	/**
	 * @param Query $query
	 * @param bool $isOnlyType
	 *
	 * @return array|MetadataConstraint[]
	 */
	public function getTypePermissionConstraints(Query $query, $isOnlyType): array
	{
		$categoryRepo = \XF::app()->repository(CategoryRepository::class);

		$with = ['Permissions|' . \XF::visitor()->permission_combination_id];
		$categories = $categoryRepo->findCategoryList(null, $with)->fetch();

		$skip = [];
		foreach ($categories AS $category)
		{
			/** @var Category $category */
			if (!$category->canView())
			{
				$skip[] = $category->category_id;
			}
		}

		if ($skip)
		{
			return [
				new MetadataConstraint('itemcat', $skip, MetadataConstraint::MATCH_NONE),
			];
		}

		return [];
	}

	/**
	 * @return null|string
	 */
	public function getGroupByType(): ?string
	{
		return 'dbtech_shop_item';
	}
}