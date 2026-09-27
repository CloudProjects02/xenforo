<?php

namespace DBTech\Shop\Admin\Controller;

use DBTech\Shop\Admin\View;
use DBTech\Shop\ControllerPlugin\CategoryPermissionPlugin;
use DBTech\Shop\ControllerPlugin\ItemPermissionPlugin;
use DBTech\Shop\Repository\CategoryRepository;
use DBTech\Shop\Repository\ItemRepository;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Repository\PermissionEntryRepository;

class PermissionController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		switch ($action)
		{
			case 'Item':
			case 'Category':
				$this->assertAdminPermission('dbtechShop');
				break;
		}
	}

	/**
	 * @return CategoryPermissionPlugin
	 */
	protected function getCategoryPermissionPlugin(): CategoryPermissionPlugin
	{
		/** @var CategoryPermissionPlugin $plugin */
		$plugin = $this->plugin(CategoryPermissionPlugin::class);
		$plugin->setFormatters('DBTech\Shop:Permission\Category%s', 'dbtech_shop_permission_category_%s');
		$plugin->setRoutePrefix('permissions/dbtech-shop-categories');

		return $plugin;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionCategory(ParameterBag $params): AbstractReply
	{
		if ($params->category_id)
		{
			return $this->getCategoryPermissionPlugin()->actionList($params);
		}

		$categoryRepo = \XF::app()->repository(CategoryRepository::class);
		$categories = $categoryRepo->findCategoryList()->fetch();
		$categoryTree = $categoryRepo->createCategoryTree($categories);

		$permissionEntryRepo = \XF::app()->repository(PermissionEntryRepository::class);
		$customPermissions = $permissionEntryRepo->getContentWithCustomPermissions('dbtech_shop_category');

		$viewParams = [
			'categoryTree' => $categoryTree,
			'customPermissions' => $customPermissions,
		];
		return $this->view(
			View\Permission\CategoryOverviewView::class,
			'dbtech_shop_permission_category_overview',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionCategoryEdit(ParameterBag $params): AbstractReply
	{
		return $this->getCategoryPermissionPlugin()->actionEdit($params);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionCategorySave(ParameterBag $params): AbstractReply
	{
		return $this->getCategoryPermissionPlugin()->actionSave($params);
	}

	/**
	 * @return ItemPermissionPlugin
	 */
	protected function getItemPermissionPlugin(): ItemPermissionPlugin
	{
		/** @var ItemPermissionPlugin $plugin */
		$plugin = $this->plugin(ItemPermissionPlugin::class);
		$plugin->setFormatters('DBTech\Shop:Permission\Item%s', 'dbtech_shop_permission_item_%s');
		$plugin->setRoutePrefix('permissions/dbtech-shop-items');

		return $plugin;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionItem(ParameterBag $params): AbstractReply
	{
		if ($params->item_id)
		{
			return $this->getItemPermissionPlugin()->actionList($params);
		}

		$itemRepo = \XF::app()->repository(ItemRepository::class);
		$items = $itemRepo->findItemsForList()->fetch();

		$permissionEntryRepo = \XF::app()->repository(PermissionEntryRepository::class);
		$customPermissions = $permissionEntryRepo->getContentWithCustomPermissions('dbtech_shop_item');

		$viewParams = [
			'items' => $items,
			'customPermissions' => $customPermissions,
		];
		return $this->view(
			View\Permission\ItemOverviewView::class,
			'dbtech_shop_permission_item_overview',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionItemEdit(ParameterBag $params): AbstractReply
	{
		return $this->getItemPermissionPlugin()->actionEdit($params);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionItemSave(ParameterBag $params): AbstractReply
	{
		return $this->getItemPermissionPlugin()->actionSave($params);
	}
}