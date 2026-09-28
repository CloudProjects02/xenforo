<?php

namespace DBTech\Shop\ControllerPlugin;

use XF\ControllerPlugin\AbstractPermission;

/**
 * Class CategoryPermission
 *
 * @package DBTech\Shop\ControllerPlugin
 */
class CategoryPermission extends AbstractPermission
{
	/**
	 * @var string
	 */
	protected $viewFormatter = 'DBTech\Shop:Permission\Category%s';
	
	/**
	 * @var string
	 */
	protected $templateFormatter = 'dbtech_shop_permission_category_%s';
	
	/**
	 * @var string
	 */
	protected $routePrefix = 'permissions/dbtech-shop-categories';
	
	/**
	 * @var string
	 */
	protected $contentType = 'dbtech_shop_category';
	
	/**
	 * @var string
	 */
	protected $entityIdentifier = 'DBTech\Shop:Category';
	
	/**
	 * @var string
	 */
	protected $primaryKey = 'category_id';
	
	/**
	 * @var string
	 */
	protected $privatePermissionGroupId = 'dbtech_shop';
	
	/**
	 * @var string
	 */
	protected $privatePermissionId = 'view';
}