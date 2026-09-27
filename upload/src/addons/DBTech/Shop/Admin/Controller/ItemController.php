<?php

namespace DBTech\Shop\Admin\Controller;

use DBTech\Shop\Admin\View;
use DBTech\Shop\ControllerPlugin\DeletePlugin;
use DBTech\Shop\ControllerPlugin\ItemPermissionPlugin;
use DBTech\Shop\Entity\Category;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Repository\CategoryRepository;
use DBTech\Shop\Repository\CurrencyRepository;
use DBTech\Shop\Repository\ItemRepository;
use DBTech\Shop\Service\Item\CreateService;
use DBTech\Shop\Service\Item\EditService;
use DBTech\Shop\Service\Item\IconService;
use DBTech\Shop\Service\Item\MoveService;
use DBTech\Shop\Service\Item\ReassignService;
use XF\Admin\Controller\AbstractController;
use XF\ControllerPlugin\EditorPlugin;
use XF\Entity\User;
use XF\Finder\UserFinder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\PrintableException;
use XF\Repository\NodeRepository;
use XF\Repository\PermissionEntryRepository;
use XF\Service\Tag\ChangerService;

class ItemController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertAdminPermission('dbtechShop');
	}

	/**
	 * @return AbstractReply
	 */
	public function actionIndex(): AbstractReply
	{
		$items = \XF::app()->repository(ItemRepository::class)
			->findItemsForList()
			->fetch()
		;

		$permissionEntryRepo = \XF::app()->repository(PermissionEntryRepository::class);
		$customPermissions = $permissionEntryRepo->getContentWithCustomPermissions('dbtech_shop_item');

		$viewParams = [
			'items' => $items,
			'customPermissions' => $customPermissions,
		];
		return $this->view(
			View\Item\ListingView::class,
			'dbtech_shop_item_list',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionEditIcon(ParameterBag $params): AbstractReply
	{
		$item = $this->assertItemExists($params->item_id);

		if ($this->isPost())
		{
			$iconService = \XF::app()->service(IconService::class, $item);

			$action = $this->filter('icon_action', 'str');

			if ($action == 'delete')
			{
				$iconService->deleteIcon();
			}
			else if ($action == 'custom')
			{
				$upload = $this->request->getFile('upload', false, false);
				if ($upload)
				{
					if (!$iconService->setImageFromUpload($upload))
					{
						return $this->error($iconService->getError());
					}

					if (!$iconService->updateIcon())
					{
						return $this->error(\XF::phrase('dbtech_shop_new_icon_could_not_be_applied_try_later'));
					}
				}
			}

			return $this->redirect($this->buildLink('dbtech-shop/items') . $this->buildLinkHash($item->item_id));
		}

		$viewParams = [
			'item' => $item,
		];
		return $this->view(
			View\Item\EditIconView::class,
			'dbtech_shop_item_edit_icon',
			$viewParams
		);
	}

	/**
	 * @param Item $item
	 * @return AbstractReply
	 */
	protected function itemAddEdit(Item $item): AbstractReply
	{
		$category = \XF::app()->em()->find(Category::class, $item->category_id);

		$nodeRepo = \XF::app()->repository(NodeRepository::class);

		if ($item->ThreadForum)
		{
			$threadPrefixes = $item->ThreadForum->getPrefixesGrouped();
		}
		else
		{
			$threadPrefixes = [];
		}

		if ($item->exists())
		{
			$tagger = \XF::app()->service(ChangerService::class, 'dbtech_shop_item', $item);

			$grouped = $tagger->getExistingTagsByEditability();
		}
		else
		{
			$grouped = [
				'editable' => null,
				'uneditable' => null,
			];
		}

		$viewParams = [
			'item' => $item,
			'category' => $category,
			'currencies' => \XF::app()->repository(CurrencyRepository::class)->getCurrencyTitlePairs(),

			'forumOptions' => $nodeRepo->getNodeOptionsData(false, 'Forum'),
			'threadPrefixes' => $threadPrefixes,

			'prefixes' => $category->getUsablePrefixes($item->prefix_id),

			'editableTags' => $grouped['editable'],
			'uneditableTags' => $grouped['uneditable'],

			'itemOwner' => $item->exists() ? $item->User : \XF::app()->em()->find(User::class, \XF::app()->options()->dbtechShopDefaultItemOwner),
		];
		return $this->view(
			View\Item\EditView::class,
			'dbtech_shop_item_edit',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params): AbstractReply
	{
		/** @var Item $item */
		$item = $this->assertItemExists($params->item_id);
		return $this->itemAddEdit($item);
	}

	/**
	 * @param Category $category
	 *
	 * @return CreateService
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	protected function setupItemCreate(Category $category): CreateService
	{
		$editorPlugin = $this->plugin(EditorPlugin::class);

		$creator = \XF::app()->service(CreateService::class, $category, $this->filter('item_type_id', 'str'));
		$creator->setPerformValidations(false);
		$creator->logIp(false);

		$item = $creator->getItem();

		$bulkInput = $this->filter([
			'title' => 'str',
			'display_order' => 'uint',

			'display_in_list' => 'bool',
			'is_stealth_item' => 'bool',
			'auto_discard_expiry' => 'bool',

			'item_flags' => 'array-bool',

			'price' => 'unum',
			'currency_id' => 'uint',
			'buyback_price' => 'unum',
			'buyback_currency_id' => 'uint',
			'buyback_time' => 'uint',
			'stock' => 'num',
			'maxstock' => 'num',
			'buyback_replenish' => 'bool',
			'refill_time' => 'uint',

			'thread_node_id' => 'uint',
			'thread_prefix_id' => 'uint',
		]);

		$bulkInput['notifications'] = explode(',', $this->filter('notifications', 'str'));
		$bulkInput['notifications_config'] = explode(',', $this->filter('notifications_config', 'str'));

		$item->setOption('admin_edit', true);
		$item->bulkSet($bulkInput);

		$creator->setTagLine($this->filter('tagline', 'str'));

		$creator->setDescription($editorPlugin->fromInput('description'));

		$itemFields = $this->filter('item_fields', 'array');
		$creator->setItemFields($itemFields);

		$prefixId = $this->filter('prefix_id', 'uint');
		if ($prefixId && $category->isPrefixUsable($prefixId))
		{
			$creator->setPrefix($prefixId);
		}

		$creator->setTags($this->filter('tags', 'str'));

		$filterIds = $this->filter('available_filters', 'array-str');
		$creator->setAvailableFilters($filterIds);

		$adminConfig = $this->filter('code', 'array');
		$creator->setAdminConfig($adminConfig);

		$dateInput = $this->filter([
			'length_type' => 'str',
			'length_amount' => 'uint',
			'length_unit' => 'str',
		]);
		$creator->setDuration($dateInput['length_type'], $dateInput['length_amount'], $dateInput['length_unit']);

		return $creator;
	}

	/**
	 * @param CreateService $creator
	 */
	protected function finalizeItemCreate(CreateService $creator): void
	{
		$creator->sendNotifications();

		/** @var Item $item */
		$item = $creator->getItem();

		if (\XF::visitor()->user_id)
		{
			if ($item->item_state == 'moderated')
			{
				$this->session()->setHasContentPendingApproval();
			}
		}
	}

	/**
	 * @return AbstractReply
	 * @throws \Exception
	 */
	public function actionAdd(): AbstractReply
	{
		$categoryRepo = \XF::app()->repository(CategoryRepository::class);
		$categoryList = $categoryRepo->findCategoryList();
		if (!$categoryList->total())
		{
			throw $this->exception($this->error(\XF::phrase('dbtech_shop_please_create_at_least_one_category_before_continuing')));
		}

		$copyItemId = $this->filter('source_item_id', 'uint');
		if ($copyItemId)
		{
			$item = $this->assertItemExists($copyItemId);

			$copyItem = $item->toArray(false);
			foreach ([
				'item_id',
				'icon_date',
			] AS $key)
			{
				unset($copyItem[$key]);
			}

			$copyItem['user_criteria'] = $copyItem['user_criteria'] ?? [];

			$item = \XF::app()->em()->create(Item::class);
			$item->bulkSet($copyItem);

			$item->hydrateRelation('Category', $item->Category);
		}
		else
		{
			$categoryId = $this->filter('category_id', 'uint');
			if ($categoryId)
			{
				$category = $this->assertCategoryExists($categoryId);

				$itemType = $this->filter('item_type', 'str');
				if ($itemType)
				{
					/** @var Item $item */
					$item = $category->getNewItem($itemType);
				}
				else
				{
					$viewParams = [
						'category'   => $category,
					];
					return $this->view(
						View\Item\AddChooser\TypeView::class,
						'dbtech_shop_item_add_chooser_type',
						$viewParams
					);
				}
			}
			else
			{
				$categoryTree = $categoryRepo->createCategoryTree($categoryList->fetch());
				$categoryExtras = $categoryRepo->getCategoryListExtras($categoryTree);

				$itemType = $this->filter('item_type', 'str');
				if ($itemType)
				{
					foreach ($categoryExtras AS &$extra)
					{
						$extra['item_type'] = $itemType;
					}
				}

				$viewParams = [
					'categoryTree'   => $categoryTree,
					'categoryExtras' => $categoryExtras,
				];
				return $this->view(
					View\Item\AddChooserView::class,
					'dbtech_shop_item_add_chooser',
					$viewParams
				);
			}
		}

		return $this->itemAddEdit($item);
	}

	/**
	 * @param Item $item
	 *
	 * @return EditService
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	protected function setupItemEdit(Item $item): EditService
	{
		$editorPlugin = $this->plugin(EditorPlugin::class);

		$editor = \XF::app()->service(EditService::class, $item);
		$editor->setPerformValidations(false);

		$bulkInput = $this->filter([
			'title' => 'str',
			'display_order' => 'uint',

			'display_in_list' => 'bool',
			'is_stealth_item' => 'bool',
			'auto_discard_expiry' => 'bool',

			'item_flags' => 'array-bool',

			'price' => 'unum',
			'currency_id' => 'uint',
			'buyback_price' => 'unum',
			'buyback_currency_id' => 'uint',
			'buyback_time' => 'uint',
			'stock' => 'num',
			'maxstock' => 'num',
			'buyback_replenish' => 'bool',
			'refill_time' => 'uint',

			'thread_node_id' => 'uint',
			'thread_prefix_id' => 'uint',
		]);

		$bulkInput['description'] = $editorPlugin->fromInput('description');
		$bulkInput['notifications'] = explode(',', $this->filter('notifications', 'str'));
		$bulkInput['notifications_config'] = explode(',', $this->filter('notifications_config', 'str'));

		$editor->getItem()->setOption('admin_edit', true);
		$editor->getItem()->bulkSet($bulkInput);

		$editor->setTagLine($this->filter('tagline', 'str'));

		$editor->setDescription($editorPlugin->fromInput('description'));

		$itemFields = $this->filter('item_fields', 'array');
		$editor->setItemFields($itemFields);

		$prefixId = $this->filter('prefix_id', 'uint');
		if ($prefixId != $item->prefix_id && !$item->Category->isPrefixUsable($prefixId))
		{
			$prefixId = 0; // not usable, just blank it out
		}
		$editor->setPrefix($prefixId);

		$editor->setTags($this->filter('tags', 'str'));

		$filterIds = $this->filter('available_filters', 'array-str');
		$editor->setAvailableFilters($filterIds);

		$adminConfig = $this->filter('code', 'array');
		$editor->setAdminConfig($adminConfig);

		$dateInput = $this->filter([
			'length_type' => 'str',
			'length_amount' => 'uint',
			'length_unit' => 'str',
		]);
		$editor->setDuration($dateInput['length_type'], $dateInput['length_amount'], $dateInput['length_unit']);

		if ($this->filter('author_alert', 'bool') && $item->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionSave(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		if ($params->item_id)
		{
			$item = $this->assertItemExists($params->item_id);

			$editor = $this->setupItemEdit($item);

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}

			$editor->save();
			$this->finalizeItemEdit($editor);

			return $this->redirect($this->buildLink('dbtech-shop/items') . $this->buildLinkHash($item->item_id));
		}

		$categoryId = $this->filter('category_id', 'uint');
		$category = $this->assertCategoryExists($categoryId);

		$userName = $this->filter('username', 'str');

		$user = \XF::app()->finder(UserFinder::class)->where('username', $userName)->fetchOne();
		if (!$user)
		{
			throw $this->exception($this->error(\XF::phrase('requested_user_x_not_found', ['name' => $userName])));
		}

		$item = \XF::asVisitor($user, function () use ($category): Item
		{
			$creator = $this->setupItemCreate($category);

			if (!$creator->validate($errors))
			{
				throw $this->exception($this->error($errors));
			}

			/** @var Item $item */
			$item = $creator->save();
			$this->finalizeItemCreate($creator);

			return $item;
		});

		return $this->redirect($this->buildLink('dbtech-shop/items') . $this->buildLinkHash($item->item_id));
	}

	/**
	 * @param EditService $editor
	 */
	protected function finalizeItemEdit(EditService $editor)
	{
	}

	/**
	 * @param Item $item
	 * @param Category $category
	 *
	 * @return MoveService
	 */
	protected function setupItemMove(Item $item, Category $category): MoveService
	{
		$options = $this->filter([
			'notify_watchers' => 'bool',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str',
			'prefix_id' => 'uint',
		]);

		$mover = \XF::app()->service(MoveService::class, $item);

		if ($options['author_alert'])
		{
			$mover->setSendAlert(true, $options['author_alert_reason']);
		}

		/*
		if ($options['notify_watchers'])
		{
			$mover->setNotifyWatchers();
		}
		*/

		if ($options['prefix_id'] !== null)
		{
			$mover->setPrefix($options['prefix_id']);
		}

		$mover->addExtraSetup(function (Item $item, Category $category)
		{
			$item->title = $this->filter('title', 'str');
		});

		return $mover;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 * @TODO: Add return value for the XF 2.3 version, remove below
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function actionMove(ParameterBag $params)/*: \XF\Mvc\Reply\AbstractReply*/
	{
		$item = $this->assertItemExists($params->item_id);

		/** @var Category $category */
		$category = $item->Category;

		if ($this->isPost())
		{
			$targetCategoryId = $this->filter('target_category_id', 'uint');

			$targetCategory = \XF::app()->em()->find(Category::class, $targetCategoryId);
			if (!$targetCategory)
			{
				return $this->error(\XF::phrase('requested_category_not_found'));
			}

			$this->setupItemMove($item, $targetCategory)->move($targetCategory);

			return $this->redirect($this->buildLink('dbtech-shop/items', $item) . $this->buildLinkHash($item->item_id));
		}

		$viewParams = [
			'item' => $item,
			'category' => $category,
			'prefixes' => $category->getUsablePrefixes(),
			'categoryTree' => \XF::app()->repository(CategoryRepository::class)->createCategoryTree(),
		];
		return $this->view(
			View\Item\MoveView::class,
			'dbtech_shop_item_move',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 * @throws PrintableException
	 */
	public function actionReassign(ParameterBag $params): AbstractReply
	{
		$item = $this->assertItemExists($params->item_id);

		if ($this->isPost())
		{
			$userName = $this->filter('username', 'str');

			$user = \XF::app()->em()->findOne(User::class, ['username' => $userName]);
			if (!$user)
			{
				return $this->error(\XF::phrase('requested_user_x_not_found', ['name' => $userName]));
			}

			$canTargetView = \XF::asVisitor($user, function () use ($item): bool
			{
				return $item->canView();
			});
			if (!$canTargetView)
			{
				return $this->error(\XF::phrase('dbtech_shop_new_owner_must_be_able_to_view_this_item'));
			}

			$reassigner = \XF::app()->service(ReassignService::class, $item);

			if ($this->filter('alert', 'bool'))
			{
				$reassigner->setSendAlert(true, $this->filter('alert_reason', 'str'));
			}

			$reassigner->reassignTo($user);

			return $this->redirect($this->buildLink('dbtech-shop/items', $item) . $this->buildLinkHash($item->item_id));
		}

		$viewParams = [
			'item' => $item,
			'category' => $item->Category,
		];
		return $this->view(
			View\Item\ReassignView::class,
			'dbtech_shop_item_reassign',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionDelete(ParameterBag $params): AbstractReply
	{
		$item = $this->assertItemExists($params->item_id);

		/** @var DeletePlugin $plugin */
		$plugin = $this->plugin(DeletePlugin::class);
		return $plugin->actionDeleteWithState(
			$item,
			'item_state',
			'DBTech\Shop:Item\Delete',
			'dbtech_shop_item',
			$this->buildLink('dbtech-shop/items/delete', $item),
			$this->buildLink('dbtech-shop/items/edit', $item),
			$this->buildLink('dbtech-shop/items'),
			$item->title,
			true
		);
	}

	/**
	 * @return ItemPermissionPlugin
	 */
	protected function getItemPermissionPlugin(): ItemPermissionPlugin
	{
		/** @var ItemPermissionPlugin $plugin */
		$plugin = $this->plugin(ItemPermissionPlugin::class);
		$plugin->setFormatters('DBTech\Shop:Item\Permission%s', 'dbtech_shop_item_permission_%s');
		$plugin->setRoutePrefix('dbtech-shop/items/permissions');

		return $plugin;
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionPermissions(ParameterBag $params): AbstractReply
	{
		return $this->getItemPermissionPlugin()->actionList($params);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionPermissionsEdit(ParameterBag $params): AbstractReply
	{
		return $this->getItemPermissionPlugin()->actionEdit($params);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionPermissionsSave(ParameterBag $params): AbstractReply
	{
		return $this->getItemPermissionPlugin()->actionSave($params);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Item
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertItemExists(?int $id, array $with = [], ?string $phraseKey = null): Item
	{
		return $this->assertRecordExists(Item::class, $id, $with, $phraseKey);
	}

	/**
	 * @param int|null $id
	 * @param array $with
	 * @param null|string $phraseKey
	 *
	 * @return Category
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertCategoryExists(?int $id, array $with = [], ?string $phraseKey = null): Category
	{
		return $this->assertRecordExists(Category::class, $id, $with, $phraseKey);
	}
}