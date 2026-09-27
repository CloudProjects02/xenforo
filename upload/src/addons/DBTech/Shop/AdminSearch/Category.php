<?php

namespace DBTech\Shop\AdminSearch;

use DBTech\Shop\Finder\CategoryFinder;
use XF\AdminSearch\AbstractHandler;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Router;

class Category extends AbstractHandler
{
	/**
	 * @return int
	 */
	public function getDisplayOrder(): int
	{
		return 55;
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
		$finder = \XF::app()->finder(CategoryFinder::class);

		$conditions = [
			['title', 'like', $finder->escapeLike($text, '%?%')],
			['description', 'like', $finder->escapeLike($text, '%?%')],
		];
		if ($previousMatchIds)
		{
			$conditions[] = ['category_id', $previousMatchIds];
		}

		$finder
			->whereOr($conditions)
			->order('title')
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
			'link' => $router->buildLink('dbtech-shop/categories/edit', $record),
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