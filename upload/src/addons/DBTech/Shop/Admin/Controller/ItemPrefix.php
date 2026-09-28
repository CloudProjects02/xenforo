<?php

namespace DBTech\Shop\Admin\Controller;

use XF\Admin\Controller\AbstractPrefix;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\FormAction;

/**
 * Class ItemPrefix
 *
 * @package DBTech\Shop\Admin\Controller
 */
class ItemPrefix extends AbstractPrefix
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
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
	 * @param \DBTech\Shop\Entity\ItemPrefix $prefix
	 *
	 * @return array
	 */
	protected function getCategoryParams(\DBTech\Shop\Entity\ItemPrefix $prefix): array
	{
		/** @var \DBTech\Shop\Repository\Category $categoryRepo */
		$categoryRepo = \XF::repository('DBTech\Shop:Category');
		$categoryTree = $categoryRepo->createCategoryTree($categoryRepo->findCategoryList()->fetch());

		return [
			'categoryTree' => $categoryTree,
		];
	}
	
	/**
	 * @param \DBTech\Shop\Entity\ItemPrefix|\XF\Entity\AbstractPrefix $prefix
	 *
	 * @return \XF\Mvc\Reply\View
	 */
	protected function prefixAddEditResponse(\XF\Entity\AbstractPrefix $prefix): \XF\Mvc\Reply\AbstractReply
	{
		$reply = parent::prefixAddEditResponse($prefix);

		if ($reply instanceof \XF\Mvc\Reply\View)
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
			'category_ids' => 'array-uint'
		]);

		if ($input['apply_category_ids'])
		{
			$form->complete(function () use ($prefixes, $input)
			{
				$mapRepo = $this->getCategoryPrefixRepo();

				foreach ($prefixes AS $prefix)
				{
					$mapRepo->updatePrefixAssociations($prefix, $input['category_ids']);
				}
			});
		}

		return $form;
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionQuickSet(): \XF\Mvc\Reply\AbstractReply
	{
		$reply = parent::actionQuickSet();

		if ($reply instanceof \XF\Mvc\Reply\View)
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
			$this->getCategoryPrefixRepo()->updatePrefixAssociations($prefix, $categoryIds);
		});

		return $form;
	}

	/**
	 * @return \DBTech\Shop\Repository\CategoryPrefix
	 */
	protected function getCategoryPrefixRepo(): \DBTech\Shop\Repository\CategoryPrefix
	{
		/** @var \DBTech\Shop\Repository\CategoryPrefix $categoryPrefixRepo */
		/** @noinspection OneTimeUseVariablesInspection */
		$categoryPrefixRepo = $this->repository('DBTech\Shop:CategoryPrefix');
		return $categoryPrefixRepo;
	}
}