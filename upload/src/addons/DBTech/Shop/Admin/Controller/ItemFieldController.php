<?php

namespace DBTech\Shop\Admin\Controller;

use DBTech\Shop\Entity\ItemField;
use DBTech\Shop\Repository\CategoryFieldRepository;
use DBTech\Shop\Repository\CategoryRepository;
use XF\Admin\Controller\AbstractField;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\View;

class ItemFieldController extends AbstractField
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
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 */
	protected function fieldAddEditResponse(\XF\Entity\AbstractField $field): AbstractReply
	{
		$reply = parent::fieldAddEditResponse($field);

		if ($reply instanceof View)
		{
			$categoryRepo = \XF::app()->repository(CategoryRepository::class);

			$categories = $categoryRepo->findCategoryList()->fetch();
			$categoryTree = $categoryRepo->createCategoryTree($categories);

			/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\CategoryField> $fieldAssociations */
			$fieldAssociations = $field->getRelationOrDefault('CategoryFields', false);

			$reply->setParams([
				'categoryTree' => $categoryTree,
				'categoryIds' => $fieldAssociations->pluckNamed('category_id'),
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

		/** @var ItemField $field */
		$form->complete(function () use ($field, $categoryIds)
		{
			$repo = \XF::app()->repository(CategoryFieldRepository::class);
			$repo->updateFieldAssociations($field, $categoryIds);
		});

		return $form;
	}
}