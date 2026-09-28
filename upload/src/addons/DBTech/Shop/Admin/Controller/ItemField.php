<?php

namespace DBTech\Shop\Admin\Controller;

use XF\Admin\Controller\AbstractField;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

/**
 * Class ItemField
 * @package DBTech\Shop\Admin\Controller
 */
class ItemField extends AbstractField
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
	 * @return string
	 */
	protected function getClassIdentifier(): string
	{
		return 'DBTech\Shop:ItemField';
	}

	/**
	 * @return string
	 */
	protected function getLinkPrefix(): string
	{
		return 'dbtech-shop/items/fields';
	}

	/**
	 * @return string
	 */
	protected function getTemplatePrefix(): string
	{
		return 'dbtech_shop_item_field';
	}
	
	/**
	 * @param \XF\Entity\AbstractField $field
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
	 */
	protected function fieldAddEditResponse(\XF\Entity\AbstractField $field): \XF\Mvc\Reply\AbstractReply
	{
		$reply = parent::fieldAddEditResponse($field);
		
		if ($reply instanceof \XF\Mvc\Reply\View)
		{
			/** @var \DBTech\Shop\Repository\Category $categoryRepo */
			$categoryRepo = $this->repository('DBTech\Shop:Category');
			
			$categories = $categoryRepo->findCategoryList()->fetch();
			$categoryTree = $categoryRepo->createCategoryTree($categories);
			
			/** @var \XF\Mvc\Entity\ArrayCollection $fieldAssociations */
			$fieldAssociations = $field->getRelationOrDefault('CategoryFields', false);
			
			$reply->setParams([
				'categoryTree' => $categoryTree,
				'categoryIds' => $fieldAssociations->pluckNamed('category_id')
			]);
		}
		
		return $reply;
	}
	
	/**
	 * @param FormAction $form
	 * @param \XF\Entity\AbstractField $field
	 *
	 * @return void|FormAction
	 */
	/**
	 * @param FormAction $form
	 * @param \XF\Entity\AbstractField $field
	 *
	 * @return FormAction
	 */
	protected function saveAdditionalData(FormAction $form, \XF\Entity\AbstractField $field): FormAction
	{
		$categoryIds = $this->filter('category_ids', 'array-uint');
		
		/** @var \DBTech\Shop\Entity\ItemField $field */
		$form->complete(function () use ($field, $categoryIds)
		{
			/** @var \DBTech\Shop\Repository\CategoryField $repo */
			$repo = $this->repository('DBTech\Shop:CategoryField');
			$repo->updateFieldAssociations($field, $categoryIds);
		});
		
		return $form;
	}
}