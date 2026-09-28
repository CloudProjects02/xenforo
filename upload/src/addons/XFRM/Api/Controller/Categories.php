<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Api\ControllerPlugin\CategoryTreePlugin;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XFRM\Entity\Category;

class Categories extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource_category', ['delete' => 'delete']);
	}

	public function actionGet()
	{
		$repo = $this->getCategoryRepo();
		return $this->getCategoryTreePlugin()->actionGet($repo);
	}

	public function actionGetFlattened()
	{
		$repo = $this->getCategoryRepo();
		return $this->getCategoryTreePlugin()->actionGetFlattened($repo);
	}

	public function actionPost(ParameterBag $params)
	{
		$this->assertAdminPermission('resourceManager');
		$this->assertRequiredApiInput(['title', 'parent_category_id']);

		/** @var Category $category */
		$category = $this->em()->create('XFRM:Category');

		/** @var \XFRM\Api\ControllerPlugin\Category $plugin */
		$plugin = $this->plugin('XFRM:Api:Category');

		$form = $plugin->setupCategorySave($category);
		$form->run();

		return $this->apiSuccess([
			'category' => $category->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @return \XFRM\Repository\Category
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('XFRM:Category');
	}

	/**
	 * @return CategoryTreePlugin
	 */
	protected function getCategoryTreePlugin()
	{
		return $this->plugin('XF:Api:CategoryTree');
	}
}
