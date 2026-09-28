<?php

namespace DBTech\Shop\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Class Item
 * @package DBTech\Shop\Admin\Controller
 */
class Item extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected $pendingUsergroupDiscounts = [];

	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertAdminPermission('dbtechShop');
	}

	/**
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionIndex(): \XF\Mvc\Reply\AbstractReply
	{
		$items = $this->getItemRepo()->findItemsForList()->fetch();
		
		/** @var \XF\Repository\PermissionEntry $permissionEntryRepo */
		$permissionEntryRepo = $this->repository('XF:PermissionEntry');
		$customPermissions = $permissionEntryRepo->getContentWithCustomPermissions('dbtech_shop_item');
		
		$viewParams = [
			'items' => $items,
			'customPermissions' => $customPermissions
		];
		return $this->view('DBTech\Shop:Item\Listing', 'dbtech_shop_item_list', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionEditIcon(ParameterBag $params)
	{
		$item = $this->assertItemExists($params->item_id);
		
		if ($this->isPost())
		{
			/** @var \DBTech\Shop\Service\Item\Icon $iconService */
			$iconService = $this->service('DBTech\Shop:Item\Icon', $item);
			
			$action = $this->filter('icon_action', 'str');
			
			if ($action == 'delete')
			{
				$iconService->deleteIcon();
			}
			elseif ($action == 'custom')
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
		return $this->view('DBTech\Shop:Item\EditIcon', 'dbtech_shop_item_edit_icon', $viewParams);
	}

	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @return \XF\Mvc\Reply\View
	 */
	protected function itemAddEdit(\DBTech\Shop\Entity\Item $item): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\Category $category */
		$category = $this->em()->find('DBTech\Shop:Category', $item->category_id);
		
		/** @var \XF\Repository\Node $nodeRepo */
		$nodeRepo = $this->repository('XF:Node');
		
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
			/** @var \XF\Service\Tag\Changer $tagger */
			$tagger = $this->service('XF:Tag\Changer', 'dbtech_shop_item', $item);
			
			$grouped = $tagger->getExistingTagsByEditability();
		}
		else
		{
			$grouped = [
				'editable' => null,
				'uneditable' => null,
			];
		}

		$existingDiscounts = [];
		if ($item->exists())
		{
			if (!$item->getRelation('UsergroupDiscounts'))
			{
				$item->hydrateRelation('UsergroupDiscounts', 
					$this->finder('DBTech\Shop:ItemUsergroupDiscount')
						->where('item_id', $item->item_id)
						->fetch()
				);
			}
			
			foreach ($item->UsergroupDiscounts as $discount)
			{
				$existingDiscounts[$discount->user_group_id] = [
					'type' => $discount->discount_type,
					'value' => $discount->discount_value
				];
			}
		}
		
		/** @var \XF\Repository\UserGroup $userGroupRepo */
		$userGroupRepo = $this->repository('XF:UserGroup');
		$userGroups = $userGroupRepo->findUserGroupsForList()->fetch();
		
		$viewParams = [
			'item' => $item,
			'category' => $category,
			'currencies' => $this->repository('DBTech\Shop:Currency')->getCurrencyTitlePairs(),
			
			'forumOptions' => $nodeRepo->getNodeOptionsData(false, 'Forum'),
			'threadPrefixes' => $threadPrefixes,
			
			'prefixes' => $category->getUsablePrefixes($item->prefix_id),
			
			'editableTags' => $grouped['editable'],
			'uneditableTags' => $grouped['uneditable'],
			
			'itemOwner' => $item->exists() ? $item->User : $this->em()->find('XF:User', $this->options()->dbtechShopDefaultItemOwner),
			'userGroups' => $userGroups,
        	'existingDiscounts' => $existingDiscounts
		];
		return $this->view('DBTech\Shop:Item\Edit', 'dbtech_shop_item_edit', $viewParams);
	}

	/**
	 * Setup usergroup discounts for item
	 * 
	 * @param \DBTech\Shop\Entity\Item $item
	 */
/**
     * Setup usergroup discounts for item
     * 
     * @param \DBTech\Shop\Entity\Item $item
     */
    protected function setupUsergroupDiscounts(\DBTech\Shop\Entity\Item $item)
    {
        $discountData = $this->pendingUsergroupDiscounts;
        
		// Delete existing discounts
		$this->app()->db()->delete('xf_dbtech_shop_item_usergroup_discount', 'item_id = ?', $item->item_id);
        
        // Add new discounts
        foreach ($discountData as $userGroupId => $discount)
        {
            $userGroupId = intval($userGroupId);
            $discountType = $discount['type'] ?? 'percentage';
            $discountValue = floatval($discount['value'] ?? 0);
            
            if ($userGroupId > 0 && $discountValue > 0)
            {
                /** @var \DBTech\Shop\Entity\ItemUsergroupDiscount $discountEntity */
                $discountEntity = $this->em()->create('DBTech\Shop:ItemUsergroupDiscount');
                $discountEntity->bulkSet([
                    'item_id' => $item->item_id,
                    'user_group_id' => $userGroupId,
                    'discount_type' => $discountType,
                    'discount_value' => $discountValue
                ]);
                $discountEntity->save();
            }
        }
        
        // Clear the pending discounts
        $this->pendingUsergroupDiscounts = [];
    }

	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		/** @var \DBTech\Shop\Entity\Item $item */
		$item = $this->assertItemExists($params->item_id);
		return $this->itemAddEdit($item);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Category $category
	 *
	 * @return \DBTech\Shop\Service\Item\Create
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	*/
	protected function setupItemCreate(\DBTech\Shop\Entity\Category $category): \DBTech\Shop\Service\Item\Create
	{
		/** @var \XF\ControllerPlugin\Editor $editorPlugin */
		$editorPlugin = $this->plugin('XF:Editor');
		
		/** @var \DBTech\Shop\Service\Item\Create $creator */
		$creator = $this->service('DBTech\Shop:Item\Create', $category, $this->filter('item_type_id', 'str'));
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
		// Remove this line that's causing the error:
		// $item->setOption('usergroup_discounts', $this->filter('usergroup_discounts', 'array'));
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

		$this->pendingUsergroupDiscounts = $this->filter('usergroup_discounts', 'array');

		return $creator;
	}
	
	/**
	 * @param \DBTech\Shop\Service\Item\Create $creator
	 */
	protected function finalizeItemCreate(\DBTech\Shop\Service\Item\Create $creator)
	{
		$creator->sendNotifications();
		
		/** @var \DBTech\Shop\Entity\Item $item */
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
	 * @return \XF\Mvc\Reply\View
	 * @throws \Exception
	 */
	public function actionAdd(): \XF\Mvc\Reply\AbstractReply
	{
		$categoryRepo = $this->getCategoryRepo();
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
						 'icon_date'
					 ] as $key)
			{
				unset($copyItem[$key]);
			}

			$copyItem['user_criteria'] = $copyItem['user_criteria'] ?? [];
			
			/** @var \DBTech\Shop\Entity\Item $item */
			$item = $this->em()->create('DBTech\Shop:Item');
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
					/** @var \DBTech\Shop\Entity\Item $item */
					$item = $category->getNewItem($itemType);
				}
				else
				{
					$viewParams = [
						'category'   => $category,
					];
					return $this->view('DBTech\Shop:Item\AddChooser\Type', 'dbtech_shop_item_add_chooser_type', $viewParams);
				}
			}
			else
			{
				$categoryTree = $categoryRepo->createCategoryTree($categoryList->fetch());
				$categoryExtras = $categoryRepo->getCategoryListExtras($categoryTree);
				
				$itemType = $this->filter('item_type', 'str');
				if ($itemType)
				{
					foreach ($categoryExtras as &$extra)
					{
						$extra['item_type'] = $itemType;
					}
				}
				
				$viewParams = [
					'categoryTree'   => $categoryTree,
					'categoryExtras' => $categoryExtras
				];
				return $this->view('DBTech\Shop:Item\AddChooser', 'dbtech_shop_item_add_chooser', $viewParams);
			}
		}
		
		return $this->itemAddEdit($item);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 *
	 * @return \DBTech\Shop\Service\Item\Edit
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 */
	protected function setupItemEdit(\DBTech\Shop\Entity\Item $item): \DBTech\Shop\Service\Item\Edit
	{
		/** @var \XF\ControllerPlugin\Editor $editorPlugin */
		$editorPlugin = $this->plugin('XF:Editor');
		
		/** @var \DBTech\Shop\Service\Item\Edit $editor */
		$editor = $this->service('DBTech\Shop:Item\Edit', $item);
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
		// Remove this line that's causing the error:
		// $editor->getItem()->setOption('usergroup_discounts', $this->filter('usergroup_discounts', 'array'));
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

		$this->pendingUsergroupDiscounts = $this->filter('usergroup_discounts', 'array');
		
		return $editor;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionSave(ParameterBag $params)
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
			
			// Handle usergroup discounts after the item is saved
			$this->setupUsergroupDiscounts($item);
			
			$this->finalizeItemEdit($editor);
			
			return $this->redirect($this->buildLink('dbtech-shop/items') . $this->buildLinkHash($item->item_id));
		}
		
		$categoryId = $this->filter('category_id', 'uint');
		$category = $this->assertCategoryExists($categoryId);
		
		$userName = $this->filter('username', 'str');
		
		/** @var \XF\Entity\User $user **/
		$user = $this->finder('XF:User')->where('username', $userName)->fetchOne();
		if (!$user)
		{
			throw $this->exception($this->error(\XF::phrase('requested_user_x_not_found', ['name' => $userName])));
		}
		
		$item = \XF::asVisitor($user, function () use ($category): \DBTech\Shop\Entity\Item
		{
			$creator = $this->setupItemCreate($category);
			
			if (!$creator->validate($errors))
			{
				throw $this->exception($this->error($errors));
			}
			
			/** @var \DBTech\Shop\Entity\Item $item */
			$item = $creator->save();
			
			// Handle usergroup discounts after the item is created
			$this->setupUsergroupDiscounts($item);
			
			$this->finalizeItemCreate($creator);
			
			return $item;
		});

		return $this->redirect($this->buildLink('dbtech-shop/items') . $this->buildLinkHash($item->item_id));
	}
	
	/**
	 * @param \DBTech\Shop\Service\Item\Edit $editor
	 */
	protected function finalizeItemEdit(\DBTech\Shop\Service\Item\Edit $editor)
	{
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 * @param \DBTech\Shop\Entity\Category $category
	 *
	 * @return \DBTech\Shop\Service\Item\Move
	 */
	protected function setupItemMove(\DBTech\Shop\Entity\Item $item, \DBTech\Shop\Entity\Category $category): \DBTech\Shop\Service\Item\Move
	{
		$options = $this->filter([
			'notify_watchers' => 'bool',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str',
			'prefix_id' => 'uint'
		]);
		
		/** @var \DBTech\Shop\Service\Item\Move $mover */
		$mover = $this->service('DBTech\Shop:Item\Move', $item);
		
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
		
		$mover->addExtraSetup(function (\DBTech\Shop\Entity\Item $item, \DBTech\Shop\Entity\Category $category)
		{
			$item->title = $this->filter('title', 'str');
		});
		
		return $mover;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionMove(ParameterBag $params)
	{
		$item = $this->assertItemExists($params->item_id);
		
		/** @var \DBTech\Shop\Entity\Category $category */
		$category = $item->Category;
		
		if ($this->isPost())
		{
			$targetCategoryId = $this->filter('target_category_id', 'uint');
			
			/** @var \DBTech\Shop\Entity\Category $targetCategory */
			$targetCategory = $this->app()->em()->find('DBTech\Shop:Category', $targetCategoryId);
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
			'categoryTree' => $this->getCategoryRepo()->createCategoryTree()
		];
		return $this->view('DBTech\Shop:Item\Move', 'dbtech_shop_item_move', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	public function actionReassign(ParameterBag $params)
	{
		$item = $this->assertItemExists($params->item_id);
		
		if ($this->isPost())
		{
			$userName = $this->filter('username', 'str');
			
			/** @var \XF\Entity\User $user */
			$user = $this->em()->findOne('XF:User', ['username' => $userName]);
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
			
			/** @var \DBTech\Shop\Service\Item\Reassign $reassigner */
			$reassigner = $this->service('DBTech\Shop:Item\Reassign', $item);
			
			if ($this->filter('alert', 'bool'))
			{
				$reassigner->setSendAlert(true, $this->filter('alert_reason', 'str'));
			}
			
			$reassigner->reassignTo($user);
			
			return $this->redirect($this->buildLink('dbtech-shop/items', $item) . $this->buildLinkHash($item->item_id));
		}
		
		$viewParams = [
			'item' => $item,
			'category' => $item->Category
		];
		return $this->view('DBTech\Shop:Item\Reassign', 'dbtech_shop_item_reassign', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionDelete(ParameterBag $params)
	{
		$item = $this->assertItemExists($params->item_id);
		
		/** @var \DBTech\Shop\ControllerPlugin\Delete $plugin */
		$plugin = $this->plugin('DBTech\Shop:Delete');
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
	 * @return \DBTech\Shop\ControllerPlugin\ItemPermission
	 */
	protected function getItemPermissionPlugin(): \DBTech\Shop\ControllerPlugin\ItemPermission
	{
		/** @var \DBTech\Shop\ControllerPlugin\ItemPermission $plugin */
		$plugin = $this->plugin('DBTech\Shop:ItemPermission');
		$plugin->setFormatters('DBTech\Shop:Item\Permission%s', 'dbtech_shop_item_permission_%s');
		$plugin->setRoutePrefix('dbtech-shop/items/permissions');
		
		return $plugin;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionPermissions(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		return $this->getItemPermissionPlugin()->actionList($params);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 */
	public function actionPermissionsEdit(ParameterBag $params)
	{
		return $this->getItemPermissionPlugin()->actionEdit($params);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect
	 */
	public function actionPermissionsSave(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		return $this->getItemPermissionPlugin()->actionSave($params);
	}

	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\Item|\XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertItemExists(?int $id, $with = null, ?string $phraseKey = null)
	{
		return $this->assertRecordExists('DBTech\Shop:Item', $id, $with, $phraseKey);
	}
	
	/**
	 * @param int|null $id
	 * @param array|string|null $with
	 * @param null|string $phraseKey
	 *
	 * @return \DBTech\Shop\Entity\Category|\XF\Mvc\Entity\Entity
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertCategoryExists(?int $id, $with = null, ?string $phraseKey = null)
	{
		return $this->assertRecordExists('DBTech\Shop:Category', $id, $with, $phraseKey);
	}

	/**
	 * @return \DBTech\Shop\Repository\Item|\XF\Mvc\Entity\Repository
	 */
	protected function getItemRepo()
	{
		return $this->repository('DBTech\Shop:Item');
	}
	
	/**
	 * @return \DBTech\Shop\Repository\Category|\XF\Mvc\Entity\Repository
	 */
	protected function getCategoryRepo()
	{
		return $this->repository('DBTech\Shop:Category');
	}
	
}