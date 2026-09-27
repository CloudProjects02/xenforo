<?php

namespace DBTech\Shop\Admin\Controller;

use DBTech\Shop\Admin\View;
use DBTech\Shop\ControllerPlugin\CategoryPermissionPlugin;
use DBTech\Shop\ControllerPlugin\CategoryTreePlugin;
use DBTech\Shop\Entity\Category;
use DBTech\Shop\Repository\CategoryFieldRepository;
use DBTech\Shop\Repository\CategoryPrefixRepository;
use DBTech\Shop\Repository\CategoryRepository;
use DBTech\Shop\Repository\ItemFieldRepository;
use DBTech\Shop\Repository\ItemPrefixRepository;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\AbstractPlugin;
use XF\Finder\UserFinder;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;
use XF\Repository\NodeRepository;

class CategoryController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechShop');
	}

	/**
	 * @return AbstractPlugin|CategoryTreePlugin
	 */
	protected function getCategoryTreePlugin(): AbstractPlugin|CategoryTreePlugin
	{
		return $this->plugin(CategoryTreePlugin::class);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		return $this->getCategoryTreePlugin()->actionList([
			'permissionContentType' => 'dbtech_shop_category',
		]);
	}

	/**
	 * @param Category $category
	 * @return AbstractReply
	 */
	protected function categoryAddEdit(Category $category): AbstractReply
	{
		$categoryRepo = \XF::app()->repository(CategoryRepository::class);
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

		$prefixRepo = \XF::app()->repository(ItemPrefixRepository::class);
		$availablePrefixes = $prefixRepo->findPrefixesForList()->fetch();
		$availablePrefixes = $availablePrefixes->pluckNamed('title', 'prefix_id');

		$fieldRepo = \XF::app()->repository(ItemFieldRepository::class);
		$availableFields = $fieldRepo->findFieldsForList()->fetch();

		$nodeRepo = \XF::app()->repository(NodeRepository::class);

		$viewParams = [
			'forumOptions' => $nodeRepo->getNodeOptionsData(false, 'Forum'),
			'threadPrefixes' => $threadPrefixes,
			'category' => $category,
			'categoryTree' => $categoryTree,

			'availablePrefixes' => $availablePrefixes,
			'availableFields' => $availableFields,
		];
		return $this->view(
			View\Category\EditView::class,
			'dbtech_shop_category_edit',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionEdit(ParameterBag $params): AbstractReply
	{
		/** @var Category $category */
		$category = $this->assertCategoryExists($params['category_id']);
		return $this->categoryAddEdit($category);
	}

	/**
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionAdd(): AbstractReply
	{
		$copyCategoryId = $this->filter('source_category_id', 'uint');
		if ($copyCategoryId)
		{
			$copyCategory = $this->assertCategoryExists($copyCategoryId)->toArray(false);
			unset($copyCategory['category_id']);

			$category = \XF::app()->em()->create(Category::class);
			$category->bulkSet($copyCategory);
		}
		else
		{
			$category = \XF::app()->em()->create(Category::class);
			$category->parent_category_id = $this->filter('parent_category_id', 'uint');
		}

		return $this->categoryAddEdit($category);
	}

	/**
	 * @param Category $category
	 *
	 * @return FormAction
	 * @throws Exception
	 */
	protected function categorySaveProcess(Category $category): FormAction
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
			'beneficiary_split' => 'uint',
		]);

		$userName = $this->filter('beneficiary', 'str');
		if ($userName)
		{
			$user = \XF::app()->finder(UserFinder::class)
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
			$repo = \XF::app()->repository(CategoryPrefixRepository::class);
			$repo->updateContentAssociations($category->category_id, $prefixIds);
		});

		$fieldIds = $this->filter('available_fields', 'array-str');
		$form->complete(function () use ($category, $fieldIds)
		{
			$repo = \XF::app()->repository(CategoryFieldRepository::class);
			$repo->updateContentAssociations($category->category_id, $fieldIds);
		});

		return $form;
	}

	/**
	 * @param ParameterBag $params
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionSave(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if ($params['category_id'])
		{
			/** @var Category $category */
			$category = $this->assertCategoryExists($params['category_id']);
		}
		else
		{
			$category = \XF::app()->em()->create(Category::class);
		}

		$this->categorySaveProcess($category)->run();

		return $this->redirect($this->buildLink('dbtech-shop/categories') . $this->buildLinkHash($category->category_id));
	}

	/**
	 * @param ParameterBag $params
	 * @return AbstractReply
	 */
	public function actionDelete(ParameterBag $params): AbstractReply
	{
		return $this->getCategoryTreePlugin()->actionDelete($params);
	}

	/**
	 * @return mixed
	 */
	public function actionSort(): mixed
	{
		return $this->getCategoryTreePlugin()->actionSort();
	}

	/**
	 * @return CategoryPermissionPlugin
	 */
	protected function getCategoryPermissionPlugin(): CategoryPermissionPlugin
	{
		/** @var CategoryPermissionPlugin $plugin */
		$plugin = $this->plugin(CategoryPermissionPlugin::class);
		$plugin->setFormatters('DBTech\Shop:Category\Permission%s', 'dbtech_shop_category_permission_%s');
		$plugin->setRoutePrefix('dbtech-shop/categories/permissions');

		return $plugin;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionPermissions(ParameterBag $params): AbstractReply
	{
		return $this->getCategoryPermissionPlugin()->actionList($params);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionPermissionsEdit(ParameterBag $params): AbstractReply
	{
		return $this->getCategoryPermissionPlugin()->actionEdit($params);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionPermissionsSave(ParameterBag $params): AbstractReply
	{
		return $this->getCategoryPermissionPlugin()->actionSave($params);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param string|null $phraseKey
	 *
	 * @return Category
	 * @throws Exception
	 */
	protected function assertCategoryExists(?int $id, array $with = [], ?string $phraseKey = null): Category
	{
		return $this->assertRecordExists(Category::class, $id, $with, $phraseKey);
	}
}