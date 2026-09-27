<?php

namespace DBTech\Shop\AdminSearch;

use DBTech\Shop\Finder\ItemFinder;
use XF\AdminSearch\AbstractHandler;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;

class Item extends AbstractHandler
{
	/**
	 * @return int
	 */
	public function getDisplayOrder(): int
	{
		return 51;
	}

	/**
	 * @param string $text
	 * @param int $limit
	 * @param array $previousMatchIds
	 *
	 * @return AbstractCollection
	 */
	public function search($text, $limit, array $previousMatchIds = []): AbstractCollection
	{
		$finder = \XF::app()->finder(ItemFinder::class);

		$conditions = [
			['title', 'like', $finder->escapeLike($text, '%?%')],
			['description', 'like', $finder->escapeLike($text, '%?%')],
		];
		if ($previousMatchIds)
		{
			$conditions[] = ['item_id', $previousMatchIds];
		}

		$finder
			->whereOr($conditions)
			->orderForList()
			->limit($limit);

		return $finder->fetch();
	}

	/**
	 * @param Entity $record
	 *
	 * @return array
	 */
	public function getTemplateData(Entity $record): array
	{
		/** @var Router $router */
		$router = \XF::app()->container('router.admin');

		return [
			'link' => $router->buildLink('dbtech-shop/items/edit', $record),
			'title' => $record->title,
		];
	}

	/**
	 * @return bool
	 */
	public function isSearchable(): bool
	{
		return \XF::visitor()->hasAdminPermission('dbtechShop');
	}
}