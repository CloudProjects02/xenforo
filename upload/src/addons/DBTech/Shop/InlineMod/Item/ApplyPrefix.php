<?php

namespace DBTech\Shop\InlineMod\Item;

use DBTech\Shop\Entity\Item;
use DBTech\Shop\Finder\ItemPrefixFinder;
use DBTech\Shop\Service\Item\EditService;
use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;

class ApplyPrefix extends AbstractAction
{
	/**
	 * @return Phrase
	 */
	public function getTitle(): Phrase
	{
		return \XF::phrase('apply_prefix...');
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
		return $entity->canEdit($error);
	}

	/**
	 * @param Entity $entity
	 * @param array $options
	 *
	 * @throws \LogicException
	 */
	protected function applyToEntity(Entity $entity, array $options): void
	{
		/** @var Item $entity */
		if (!$entity->Category->isPrefixValid($options['prefix_id']))
		{
			return;
		}

		$editor = \XF::app()->service(EditService::class, $entity);
		$editor->setPerformValidations(false);
		$editor->setPrefix($options['prefix_id']);
		if ($editor->validate($errors))
		{
			$editor->save();
		}
	}

	/**
	 * @return array
	 */
	public function getBaseOptions(): array
	{
		return [
			'prefix_id' => null,
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
		$categories = $entities->pluckNamed('Category', 'category_id');
		$prefixIds = [];

		foreach ($categories AS $category)
		{
			$prefixIds = array_merge($prefixIds, array_keys($category->prefix_cache));
		}

		$prefixes = \XF::app()->finder(ItemPrefixFinder::class)
			->where('prefix_id', array_unique($prefixIds))
			->order('materialized_order')
			->fetch();

		if (!$prefixes->count())
		{
			return $controller->error(\XF::phrase('dbtech_shop_no_prefixes_available_for_selected_categories'));
		}

		$selectedPrefix = 0;
		$prefixCounts = [0 => 0];
		foreach ($entities AS $item)
		{
			/** @var Item $item */
			$prefixId = $item->prefix_id;

			if (!isset($prefixCounts[$prefixId]))
			{
				$prefixCounts[$prefixId] = 1;
			}
			else
			{
				$prefixCounts[$prefixId]++;
			}

			if ($prefixCounts[$prefixId] > $prefixCounts[$selectedPrefix])
			{
				$selectedPrefix = $prefixId;
			}
		}

		$viewParams = [
			'items' => $entities,
			'prefixes' => $prefixes->groupBy('prefix_group_id'),
			'categoryCount' => count($categories->keys()),
			'selectedPrefix' => $selectedPrefix,
			'total' => count($entities),
		];
		return $controller->view('DBTech\Shop:Public:InlineMod\Item\ApplyPrefix', 'inline_mod_dbtech_shop_item_apply_prefix', $viewParams);
	}

	/**
	 * @param AbstractCollection $entities
	 * @param Request $request
	 *
	 * @return array
	 */
	public function getFormOptions(AbstractCollection $entities, Request $request): array
	{
		return [
			'prefix_id' => $request->filter('prefix_id', 'uint'),
		];
	}
}