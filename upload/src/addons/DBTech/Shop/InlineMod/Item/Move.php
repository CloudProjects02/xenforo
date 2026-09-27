<?php

namespace DBTech\Shop\InlineMod\Item;

use DBTech\Shop\Entity\Category;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Finder\ItemPrefixFinder;
use DBTech\Shop\Repository\CategoryRepository;
use DBTech\Shop\Service\Item\MoveService;
use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;
use XF\PrintableException;

class Move extends AbstractAction
{
	protected ?Category $targetCategory = null;
	protected ?int $targetCategoryId = null;

	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::phrase('dbtech_shop_move_items...');
	}

	/**
	 * @param AbstractCollection $entities
	 * @param array $options
	 * @param $error
	 *
	 * @return bool
	 * @throws \InvalidArgumentException
	 */
	protected function canApplyInternal(AbstractCollection $entities, array $options, &$error): bool
	{
		$result = parent::canApplyInternal($entities, $options, $error);

		if ($result && $options['target_category_id'])
		{
			$category = $this->getTargetCategory($options['target_category_id']);
			if (!$category)
			{
				return false;
			}

			if ($options['check_category_viewable'] && !$category->canView($error))
			{
				return false;
			}

			if ($options['check_all_same_category'])
			{
				$allSame = true;
				foreach ($entities AS $entity)
				{
					/** @var Item $entity */
					if ($entity->category_id != $options['target_category_id'])
					{
						$allSame = false;
						break;
					}
				}

				if ($allSame)
				{
					$error = \XF::phrase('dbtech_shop_all_selected_items_already_in_destination_category_select_another');
					return false;
				}
			}
		}

		return $result;
	}

	/**
	 * @param Entity $entity
	 * @param array $options
	 * @param null $error
	 *
	 * @return bool
	 */
	protected function canApplyToEntity(Entity $entity, array $options, &$error = null): bool
	{
		/** @var Item $entity */
		return $entity->canMove($error);
	}

	/**
	 * @param Entity $entity
	 * @param array $options
	 *
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	protected function applyToEntity(Entity $entity, array $options): void
	{
		$category = $this->getTargetCategory($options['target_category_id']);
		if (!$category)
		{
			throw new \InvalidArgumentException('No target specified');
		}

		$mover = \XF::app()->service(MoveService::class, $entity);

		if ($options['alert'])
		{
			$mover->setSendAlert(true, $options['alert_reason']);
		}

		/*
		if ($options['notify_watchers'])
		{
			$mover->setNotifyWatchers();
		}
		*/

		if ($options['prefix_id'] !== null)
		{
			$mover->setPrefix($options['prefix_id']);
		}

		$mover->move($category);

		$this->returnUrl = \XF::app()->router()->buildLink('dbtech-shop/categories', $category);
	}

	/**
	 * @return array
	 */
	public function getBaseOptions(): array
	{
		return [
			'target_category_id' => 0,
			'check_category_viewable' => true,
			'check_all_same_category' => true,
			'prefix_id' => null,
			'notify_watchers' => false,
			'alert' => false,
			'alert_reason' => '',
		];
	}

	/**
	 * @param AbstractCollection $entities
	 * @param Controller $controller
	 *
	 * @return AbstractReply
	 */
	public function renderForm(AbstractCollection $entities, Controller $controller): AbstractReply
	{
		$prefixes = \XF::app()->finder(ItemPrefixFinder::class)
			->order('materialized_order')
			->fetch();

		$categoryRepo = \XF::app()->repository(CategoryRepository::class);
		$categories = $categoryRepo->getViewableCategories();

		$viewParams = [
			'items' => $entities,
			'prefixes' => $prefixes->groupBy('prefix_group_id'),
			'total' => count($entities),
			'categoryTree' => $categoryRepo->createCategoryTree($categories),
			'first' => $entities->first(),
		];
		return $controller->view('DBTech\Shop:Public:InlineMod\Item\Move', 'inline_mod_dbtech_shop_item_move', $viewParams);
	}

	/**
	 * @param AbstractCollection $entities
	 * @param Request $request
	 *
	 * @return array
	 */
	public function getFormOptions(AbstractCollection $entities, Request $request): array
	{
		$options = [
			'target_category_id' => $request->filter('target_category_id', 'uint'),
			'prefix_id' => $request->filter('prefix_id', 'uint'),
			'notify_watchers' => $request->filter('notify_watchers', 'bool'),
			'alert' => $request->filter('author_alert', 'bool'),
			'alert_reason' => $request->filter('author_alert_reason', 'str'),
		];
		if (!$request->filter('apply_prefix', 'bool'))
		{
			$options['prefix_id'] = null;
		}

		return $options;
	}

	/**
	 * @param int $categoryId
	 *
	 * @return null|Category
	 * @throws \InvalidArgumentException
	 */
	protected function getTargetCategory(int $categoryId): ?Category
	{
		if ($this->targetCategoryId && $this->targetCategoryId == $categoryId)
		{
			return $this->targetCategory;
		}
		if (!$categoryId)
		{
			return null;
		}

		$category = \XF::app()->em()->find(Category::class, $categoryId);
		if (!$category)
		{
			throw new \InvalidArgumentException("Invalid target category ($categoryId)");
		}

		$this->targetCategoryId = $categoryId;
		$this->targetCategory = $category;

		return $this->targetCategory;
	}
}