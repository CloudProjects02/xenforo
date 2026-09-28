<?php

namespace DBTech\Shop\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Class Permission
 *
 * @package DBTech\Shop\Admin\Controller
 */
class Permission extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
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
	 * @return \DBTech\Shop\ControllerPlugin\CategoryPermission
	 */
	protected function getCategoryPermissionPlugin(): \DBTech\Shop\ControllerPlugin\CategoryPermission
	{
		/** @var \DBTech\Shop\ControllerPlugin\CategoryPermission $plugin */
		$plugin = $this->plugin('DBTech\Shop:CategoryPermission');
		$plugin->setFormatters('DBTech\Shop\Permission\Category%s', 'dbtech_shop_permission_category_%s');
		$plugin->setRoutePrefix('permissions/dbtech-shop-categories');

		return $plugin;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionCategory(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		if ($params->category_id)
		{
			return $this->getCategoryPermissionPlugin()->actionList($params);
		}
		
		/** @var \DBTech\Shop\Repository\Category $categoryRepo */
		$categoryRepo = $this->repository('DBTech\Shop:Category');
		$categories = $categoryRepo->findCategoryList()->fetch();
		$categoryTree = $categoryRepo->createCategoryTree($categories);
		
		/** @var \XF\Repository\PermissionEntry $permissionEntryRepo */
		$permissionEntryRepo = $this->repository('XF:PermissionEntry');
		$customPermissions = $permissionEntryRepo->getContentWithCustomPermissions('dbtech_shop_category');
		
		$viewParams = [
			'categoryTree' => $categoryTree,
			'customPermissions' => $customPermissions
		];
		return $this->view('DBTech\Shop:Permission\CategoryOverview', 'dbtech_shop_permission_category_overview', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 */
	public function actionCategoryEdit(ParameterBag $params)
	{
		return $this->getCategoryPermissionPlugin()->actionEdit($params);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect
	 */
	public function actionCategorySave(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		return $this->getCategoryPermissionPlugin()->actionSave($params);
	}
	
	/**
	 * @return \DBTech\Shop\ControllerPlugin\ItemPermission
	 */
	protected function getItemPermissionPlugin(): \DBTech\Shop\ControllerPlugin\ItemPermission
	{
		/** @var \DBTech\Shop\ControllerPlugin\ItemPermission $plugin */
		$plugin = $this->plugin('DBTech\Shop:ItemPermission');
		$plugin->setFormatters('DBTech\Shop\Permission\Item%s', 'dbtech_shop_permission_item_%s');
		$plugin->setRoutePrefix('permissions/dbtech-shop-items');
		
		return $plugin;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionItem(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		if ($params->item_id)
		{
			return $this->getItemPermissionPlugin()->actionList($params);
		}
		
		/** @var \DBTech\Shop\Repository\Item $itemRepo */
		$itemRepo = $this->repository('DBTech\Shop:Item');
		$items = $itemRepo->findItemsForList()->fetch();
		
		/** @var \XF\Repository\PermissionEntry $permissionEntryRepo */
		$permissionEntryRepo = $this->repository('XF:PermissionEntry');
		$customPermissions = $permissionEntryRepo->getContentWithCustomPermissions('dbtech_shop_item');
		
		$viewParams = [
			'items' => $items,
			'customPermissions' => $customPermissions
		];
		return $this->view('DBTech\Shop:Permission\ItemOverview', 'dbtech_shop_permission_item_overview', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 */
	public function actionItemEdit(ParameterBag $params)
	{
		return $this->getItemPermissionPlugin()->actionEdit($params);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect
	 */
	public function actionItemSave(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		return $this->getItemPermissionPlugin()->actionSave($params);
	}
}