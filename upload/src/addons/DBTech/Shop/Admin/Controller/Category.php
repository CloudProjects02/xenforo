<?php

namespace DBTech\Shop\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

/**
 * Class Category
 * @package DBTech\Shop\Admin\Controller
 */
class Category extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('dbtechShop');
	}
	
	/**
	 * @return \XF\ControllerPlugin\AbstractPlugin|\DBTech\Shop\ControllerPlugin\CategoryTree
	 */
	protected function getCategoryTreePlugin()
	{
		return $this->plugin('DBTech\Shop:CategoryTree');
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionIndex(): \XF\Mvc\Reply\AbstractReply
	{
		return $this->getCategoryTreePlugin()->actionList([
			'permissionContentType' => 'dbtech_shop_category'
		]);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Category $category
	 * @return \XF\Mvc\Reply\View
	 */
	protected function categoryAddEdit(\DBTech\Shop\Entity\Category $category): \XF\Mvc\Reply\AbstractReply
	{
		$categoryRepo = $this->getCategoryRepo();
		$categories = $categoryRepo->findCategoryList()->fetch();
		$categoryTree = $categoryRepo->createCategoryTree($categories);
		
		if ($category->ThreadForum)
		{
			$threadPrefixes = $category->ThreadForum->getPrefixesGrouped();
		}
		else
		{
			$threadPrefixes = [];
		}
		
		/** @var \DBTech\Shop\Repository\ItemPrefix $prefixRepo */
		$prefixRepo = $this->repository('DBTech\Shop:ItemPrefix');
		$availablePrefixes = $prefixRepo->findPrefixesForList()->fetch();
		$availablePrefixes = $availablePrefixes->pluckNamed('title', 'prefix_id');
		
		/** @var \DBTech\Shop\Repository\ItemField $fieldRepo */
		$fieldRepo = $this->repository('DBTech\Shop:ItemField');
		$availableFields = $fieldRepo->findFieldsForList()->fetch();
		
		/** @var \XF\Repository\Node $nodeRepo */
		$nodeRepo = $this->repository('XF:Node');
		
		$viewParams = [
			'forumOptions' => $nodeRepo->getNodeOptionsData(false, 'Forum'),
			'threadPrefixes' => $threadPrefixes,
			'category' => $category,
			'categoryTree' => $categoryTree,
			
			'availablePrefixes' => $availablePrefixes,
			'availableFields' => $availableFields
		];
		return $this->view('DBTech\Shop:Category\Edit', 'dbtech_shop_category_edit', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\Category $category */
		$category = $this->assertCategoryExists($params['category_id']);
		return $this->categoryAddEdit($category);
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAdd(): \XF\Mvc\Reply\AbstractReply
	{
		$copyCategoryId = $this->filter('source_category_id', 'uint');
		if ($copyCategoryId)
		{
			$copyCategory = $this->assertCategoryExists($copyCategoryId)->toArray(false);
			foreach ([
						 'category_id',
					 ] as $key)
			{
				unset($copyCategory[$key]);
			}
			
			/** @var \DBTech\Shop\Entity\Category $category */
			$category = $this->em()->create('DBTech\Shop:Category');
			$category->bulkSet($copyCategory);
		}
		else
		{
			/** @var \DBTech\Shop\Entity\Category $category */
			$category = $this->em()->create('DBTech\Shop:Category');
			$category->parent_category_id = $this->filter('parent_category_id', 'uint');
		}
		
		return $this->categoryAddEdit($category);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Category $category
	 *
	 * @return FormAction
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function categorySaveProcess(\DBTech\Shop\Entity\Category $category): FormAction
	{
		$form = $this->formAction();
		
		$input = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'display_order' => 'uint',
			'parent_category_id' => 'uint',
			'always_moderate_create' => 'bool',
			'always_moderate_update' => 'bool',
			'thread_node_id' => 'uint',
			'thread_prefix_id' => 'uint',
			'require_prefix' => 'bool',
//			'item_update_notify' => 'str',
			'beneficiary_split' => 'uint'
		]);
		
		$userName = $this->filter('beneficiary', 'str');
		if ($userName)
		{
			/** @var \XF\Entity\User $user * */
			$user = $this->finder('XF:User')
				->where('username', $userName)
				->fetchOne()
			;
			if (!$user)
			{
				throw $this->exception($this->error(\XF::phrase('requested_user_x_not_found', ['name' => $userName])));
			}
			
			$input['beneficiary'] = $user->user_id;
		}
		else
		{
			$input['beneficiary'] = 0;
		}
		
		$form->basicEntitySave($category, $input);
		
		$prefixIds = $this->filter('available_prefixes', 'array-uint');
		$form->complete(function () use ($category, $prefixIds)
		{
			/** @var \DBTech\Shop\Repository\CategoryPrefix $repo */
			$repo = $this->repository('DBTech\Shop:CategoryPrefix');
			$repo->updateContentAssociations($category->category_id, $prefixIds);
		});
		
		$fieldIds = $this->filter('available_fields', 'array-str');
		$form->complete(function () use ($category, $fieldIds)
		{
			/** @var \DBTech\Shop\Repository\CategoryField $repo */
			$repo = $this->repository('DBTech\Shop:CategoryField');
			$repo->updateContentAssociations($category->category_id, $fieldIds);
		});

		return $form;
	}
	
	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\Redirect
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionSave(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		$this->assertPostOnly();
		
		if ($params['category_id'])
		{
			/** @var \DBTech\Shop\Entity\Category $category */
			$category = $this->assertCategoryExists($params['category_id']);
		}
		else
		{
			/** @var \DBTech\Shop\Entity\Category $category */
			$category = $this->em()->create('DBTech\Shop:Category');
		}
		
		$this->categorySaveProcess($category)->run();
		
		return $this->redirect($this->buildLink('dbtech-shop/categories') . $this->buildLinkHash($category->category_id));
	}
	
	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 */
	public function actionDelete(ParameterBag $params)
	{
		return $this->getCategoryTreePlugin()->actionDelete($params);
	}
	
	/**
	 * @return mixed
	 */
	public function actionSort()
	{
		return $this->getCategoryTreePlugin()->actionSort();
	}
	
	/**
	 * @return \DBTech\Shop\ControllerPlugin\CategoryPermission
	 */
	protected function getCategoryPermissionPlugin(): \DBTech\Shop\ControllerPlugin\CategoryPermission
	{
		/** @var \DBTech\Shop\ControllerPlugin\CategoryPermission $plugin */
		$plugin = $this->plugin('DBTech\Shop:CategoryPermission');
		$plugin->setFormatters('DBTech\Shop:Category\Permission%s', 'dbtech_shop_category_permission_%s');
		$plugin->setRoutePrefix('dbtech-shop/categories/permissions');
		
		return $plugin;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionPermissions(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		return $this->getCategoryPermissionPlugin()->actionList($params);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 */
	public function actionPermissionsEdit(ParameterBag $params)
	{
		return $this->getCategoryPermissionPlugin()->actionEdit($params);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect
	 */
	public function actionPermissionsSave(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		return $this->getCategoryPermissionPlugin()->actionSave($params);
	}
	
	/**
	 * @param int|null $id
	 * @param null $with
	 * @param string|null $phraseKey
	 * @return \XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertCategoryExists(?int $id, $with = null, ?string $phraseKey = null): \XF\Mvc\Entity\Entity
	{
		return $this->assertRecordExists('DBTech\Shop:Category', $id, $with, $phraseKey);
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Category|\XF\Mvc\Entity\Repository
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('DBTech\Shop:Category');
	}
}