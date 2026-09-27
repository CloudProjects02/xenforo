<?php

namespace DBTech\Shop\Admin\Controller;

use DBTech\Shop\Entity\ItemPrefix;
use DBTech\Shop\Repository\CategoryPrefixRepository;
use DBTech\Shop\Repository\CategoryRepository;
use XF\Admin\Controller\AbstractPrefix;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\View;

class ItemPrefixController extends AbstractPrefix
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
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
		return 'DBTech\Shop:ItemPrefix';
	}

	/**
	 * @return string
	 */
	protected function getLinkPrefix(): string
	{
		return 'dbtech-shop/prefixes';
	}

	/**
	 * @return string
	 */
	protected function getTemplatePrefix(): string
	{
		return 'dbtech_shop_item_prefix';
	}

	/**
	 * @param ItemPrefix $prefix
	 *
	 * @return array
	 */
	protected function getCategoryParams(ItemPrefix $prefix): array
	{
		$categoryRepo = \XF::app()->repository(CategoryRepository::class);
		$categoryTree = $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());

		return [
			'categoryTree' => $categoryTree,
		];
	}

	/**
	 * @param ItemPrefix|\XF\Entity\AbstractPrefix $prefix
	 *
	 * @return AbstractReply
	 */
	protected function prefixAddEditResponse(
		\XF\Entity\AbstractPrefix|ItemPrefix $prefix
	): AbstractReply
	{
		$reply = parent::prefixAddEditResponse($prefix);

		if ($reply instanceof View)
		{
			$reply->setParams($this->getCategoryParams($prefix));
		}

		return $reply;
	}

	/**
	 * @param FormAction $form
	 * @param ArrayCollection $prefixes
	 *
	 * @return FormAction
	 */
	protected function quickSetAdditionalData(FormAction $form, ArrayCollection $prefixes): FormAction
	{
		$input = $this->filter([
			'apply_category_ids' => 'bool',
			'category_ids' => 'array-uint',
		]);

		if ($input['apply_category_ids'])
		{
			$form->complete(function () use ($prefixes, $input)
			{
				$mapRepo = \XF::app()->repository(CategoryPrefixRepository::class);

				foreach ($prefixes AS $prefix)
				{
					$mapRepo->updatePrefixAssociations($prefix, $input['category_ids']);
				}
			});
		}

		return $form;
	}

	/**
	 * @return AbstractReply
	 */
	public function actionQuickSet(): AbstractReply
	{
		$reply = parent::actionQuickSet();

		if ($reply instanceof View)
		{
			if ($reply->getTemplateName() == $this->getTemplatePrefix() . '_quickset_editor')
			{
				$reply->setParams($this->getCategoryParams($reply->getParam('prefix')));
			}
		}

		return $reply;
	}

	/**
	 * @param FormAction $form
	 * @param \XF\Entity\AbstractPrefix $prefix
	 *
	 * @return FormAction
	 */
	protected function saveAdditionalData(FormAction $form, \XF\Entity\AbstractPrefix $prefix): FormAction
	{
		$categoryIds = $this->filter('category_ids', 'array-uint');

		$form->complete(function () use ($prefix, $categoryIds)
		{
			\XF::app()->repository(CategoryPrefixRepository::class)
				->updatePrefixAssociations($prefix, $categoryIds)
			;
		});

		return $form;
	}
}