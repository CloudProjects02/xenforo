<?php

namespace DBTech\Shop\ControllerPlugin;

use XF\ControllerPlugin\AbstractPermission;

/**
 * Class ItemPermission
 *
 * @package DBTech\Shop\ControllerPlugin
 */
class ItemPermission extends AbstractPermission
{
	/**
	 * @var string
	 */
	protected $viewFormatter = 'DBTech\Shop:Permission\Item%s';

	/**
	 * @var string
	 */
	protected $templateFormatter = 'dbtech_shop_permission_item_%s';

	/**
	 * @var string
	 */
	protected $routePrefix = 'permissions/dbtech-shop-items';

	/**
	 * @var string
	 */
	protected $contentType = 'dbtech_shop_item';

	/**
	 * @var string
	 */
	protected $entityIdentifier = 'DBTech\Shop:Item';

	/**
	 * @var string
	 */
	protected $primaryKey = 'item_id';

	/**
	 * @var string
	 */
	protected $privatePermissionGroupId = 'dbtech_shop';

	/**
	 * @var string
	 */
	protected $privatePermissionId = 'view';
}