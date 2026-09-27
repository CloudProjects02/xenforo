<?php

namespace DBTech\Shop\ControllerPlugin;

use XF\ControllerPlugin\AbstractCategoryTree;

class CategoryTreePlugin extends AbstractCategoryTree
{
	/**
	 * @var string
	 */
	protected $viewFormatter = 'DBTech\Shop:Category\%s';

	/**
	 * @var string
	 */
	protected $templateFormatter = 'dbtech_shop_category_%s';

	/**
	 * @var string
	 */
	protected $routePrefix = 'dbtech-shop/categories';

	/**
	 * @var string
	 */
	protected $entityIdentifier = 'DBTech\Shop:Category';

	/**
	 * @var string
	 */
	protected $primaryKey = 'category_id';
}