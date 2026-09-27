<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\ControllerPlugin\OverviewPlugin;
use DBTech\Shop\Entity\Category;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Entity\ItemRating;
use DBTech\Shop\Finder\ItemFinder;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\CategoryRepository;
use DBTech\Shop\Repository\CurrencyRepository;
use DBTech\Shop\Repository\ItemRatingRepository;
use DBTech\Shop\Repository\ItemWatchRepository;
use DBTech\Shop\Service\Cart\CreatorService;
use DBTech\Shop\Service\Item\ApproveService;
use DBTech\Shop\Service\Item\CreateService;
use DBTech\Shop\Service\Item\DeleteService;
use DBTech\Shop\Service\Item\EditService;
use DBTech\Shop\Service\Item\IconService;
use DBTech\Shop\Service\Item\MoveService;
use DBTech\Shop\Service\Item\RateService;
use DBTech\Shop\Service\Item\ReassignService;
use XF\ControllerPlugin\BookmarkPlugin;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\InlineModPlugin;
use XF\ControllerPlugin\IpPlugin;
use XF\ControllerPlugin\ReactionPlugin;
use XF\ControllerPlugin\ReportPlugin;
use XF\ControllerPlugin\UndeletePlugin;
use XF\ControllerPlugin\WarnPlugin;
use XF\CustomField\Set;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;
use XF\Repository\NodeRepository;
use XF\Repository\UserRepository;
use XF\Service\Tag\ChangerService;

class ItemController extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params): void
	{
		switch ($action)
		{
			case 'LatestReviews':
			case 'Reviews':
			case 'Rate':
				if (!\XF::app()->options()->dbtechShopEnableRate)
				{
					throw $this->exception($this->noPermission());
				}
				break;
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		if ($params->item_id)
		{
			return $this->rerouteController(__CLASS__, 'view', $params);
		}

		/** @var OverviewPlugin $overviewPlugin */
		$overviewPlugin = $this->plugin(OverviewPlugin::class);

		$categoryParams = $overviewPlugin->getCategoryListData();
		$viewableCategoryIds = $categoryParams['categories']->keys();

		$listParams = $overviewPlugin->getCoreListData($viewableCategoryIds);

		$this->assertValidPage($listParams['page'], $listParams['perPage'], $listParams['total'], 'dbtech-shop');
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop', null, ['page' => $listParams['page']]));

		$viewParams = $categoryParams + $listParams;
		return $this->view(
			View\OverviewView::class,
			'dbtech_shop_overview',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionView(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id, $this->getItemViewExtraWith());

		$this->assertCanonicalUrl($this->buildLink('dbtech-shop', $item));

		$viewParams = [
			'item' => $item,
			'category' => $item->Category,
		];
		return $this->view(
			View\Item\ViewView::class,
			'dbtech_shop_item_view',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionField(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);

		$fieldId = $this->filter('field', 'str');
		$tabFields = $item->getExtraFieldTabs();

		if (!isset($tabFields[$fieldId]))
		{
			return $this->redirect($this->buildLink('dbtech-shop', $item));
		}

		/** @var Set $fieldSet */
		$fieldSet = $item->item_fields;
		$definition = $fieldSet->getDefinition($fieldId);
		$fieldValue = $fieldSet->getFieldValue($fieldId);

		$viewParams = [
			'item' => $item,
			'category' => $item->Category,

			'fieldId' => $fieldId,
			'fieldDefinition' => $definition,
			'fieldValue' => $fieldValue,
		];
		return $this->view(
			View\Item\FieldView::class,
			'dbtech_shop_item_field',
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
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canEditIcon($error))
		{
			return $this->noPermission($error);
		}

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

			return $this->redirect($this->buildLink('dbtech-shop', $item));
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
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionPurchase(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);

		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/purchase', $item));

		if (!$item->canView($error))
		{
			return $this->noPermission($error);
		}

		if (!$item->canPurchase())
		{
			return $this->noPermission();
		}

		$redirect = $this->getDynamicRedirect(null, false);

		if ($this->isPost())
		{
			$toUser = null;
			if ($item->isGiftable()
				&& ($item->isOnlyGiftable() || $this->request->exists('is_gift'))
			)
			{
				$toUser = \XF::app()->repository(UserRepository::class)
					->getUserByNameOrEmail($this->filter('recipient', 'str'))
				;
				if (!$toUser)
				{
					return $this->error(\XF::phrase('requested_user_not_found'));
				}

				if ($toUser->user_id == \XF::visitor()->user_id)
				{
					return $this->error(\XF::phrase('dbtech_shop_cannot_gift_item_to_self'));
				}
			}

			if ($toUser)
			{
				if (!$item->canPurchaseForUser($toUser, true, $error))
				{
					return $this->noPermission($error);
				}
			}
			else
			{
				if (!$item->canPurchaseForSelf(true, $error))
				{
					return $this->noPermission($error);
				}
			}

			$creator = \XF::app()->service(CreatorService::class);

			$creator->addItem(
				$item,
				$this->filter('quantity', 'uint', 1),
				$toUser,
				$this->filter('message', 'str')
			);

			if (!$creator->validate($errors))
			{
				return $this->error($errors);
			}

			$creator->save();

			return $this->redirect($redirect, \XF::phrase('dbtech_shop_items_added_to_cart'));
		}

		$viewParams = [
			'item' => $item,
			'redirect' => $redirect,
		];
		return $this->view(
			View\Item\PurchaseView::class,
			'dbtech_shop_item_purchase',
			$viewParams
		);
	}

	/**
	 * @return AbstractReply
	 */
	public function actionFilters(): AbstractReply
	{
		/** @var OverviewPlugin $overviewPlugin */
		$overviewPlugin = $this->plugin(OverviewPlugin::class);

		return $overviewPlugin->actionFilters();
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionPrefixes(ParameterBag $params): AbstractReply
	{
		$this->assertPostOnly();

		$categoryId = $this->filter('val', 'uint');

		$category = \XF::app()->em()->find(
			Category::class,
			$categoryId,
			[
				'Permissions|' . \XF::visitor()->permission_combination_id,
			]
		);
		if (!$category)
		{
			return $this->notFound(\XF::phrase('requested_category_not_found'));
		}

		if (!$category->canView($error))
		{
			return $this->noPermission($error);
		}

		$viewParams = [
			'category' => $category,
			'prefixes' => $category->getUsablePrefixes(),
		];
		return $this->view(
			View\Category\PrefixesView::class,
			'dbtech_shop_category_prefixes',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionWatch(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canWatch($error))
		{
			return $this->noPermission($error);
		}

		$visitor = \XF::visitor();

		if ($this->isPost())
		{
			if ($this->filter('stop', 'bool'))
			{
				$action = 'delete';
				$config = [];
			}
			else
			{
				$action = 'watch';
				$config = [
					'email_subscribe' => $this->filter('email_subscribe', 'bool'),
				];
			}

			$watchRepo = \XF::app()->repository(ItemWatchRepository::class);
			$watchRepo->setWatchState($item, $visitor, $action, $config);

			$redirect = $this->redirect($this->buildLink('dbtech-shop', $item));
			$redirect->setJsonParam('switchKey', $action == 'delete' ? 'watch' : 'unwatch');
			return $redirect;
		}

		$viewParams = [
			'item' => $item,
			'isWatched' => !empty($item->Watch[$visitor->user_id]),
			'category' => $item->Category,
		];
		return $this->view(
			View\Item\WatchView::class,
			'dbtech_shop_item_watch',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionRate(ParameterBag $params): AbstractReply
	{
		$visitorUserId = \XF::visitor()->user_id;

		$extraWith = [
			'Purchases|' . $visitorUserId,
			'Ratings|' . $visitorUserId,
		];
		$item = $this->assertViewableItem($params->item_id, $extraWith);
		if (!$item->canRate(true, $error))
		{
			return $this->noPermission($error);
		}

		/** @var ItemRating|null $existingRating */
		$existingRating = $item->Ratings[$visitorUserId];
		if ($existingRating && !$existingRating->canUpdate($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$rater = $this->setupItemRate($item);
			$rater->checkForSpam();

			if (!$rater->validate($errors))
			{
				return $this->error($errors);
			}

			$rating = $rater->save();

			return $this->redirect($this->buildLink(
				$rating->is_review ? 'dbtech-shop/reviews' : 'dbtech-shop',
				$item
			));
		}

		$viewParams = [
			'item' => $item,
			'category' => $item->Category,
			'existingRating' => $existingRating,
		];
		return $this->view(
			View\Item\RateView::class,
			'dbtech_shop_item_rate',
			$viewParams
		);
	}

	/**
	 * @param Item $item
	 *
	 * @return RateService
	 */
	protected function setupItemRate(Item $item): RateService
	{
		$rater = \XF::app()->service(RateService::class, $item);

		$input = $this->filter([
			'rating' => 'uint',
			'message' => 'str',
			'is_anonymous' => 'bool',
		]);

		$rater->setRating($input['rating'], $input['message']);

		return $rater;
	}

	/**
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionLatestReviews(): AbstractReply
	{
		$viewableCategoryIds = \XF::app()->repository(CategoryRepository::class)
			->getViewableCategoryIds()
		;

		$ratingRepo = \XF::app()->repository(ItemRatingRepository::class);
		$finder = $ratingRepo->findLatestReviews($viewableCategoryIds);

		$total = $finder->total();
		$page = $this->filterPage();
		$perPage = \XF::app()->options()->dbtechShopReviewsPerPage;

		$this->assertValidPage($page, $perPage, $total, 'dbtech-shop/latest-reviews');
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/latest-reviews', null, ['page' => $page]));

		$reviews = $finder->limitByPage($page, $perPage)->fetch();
		$reviews = $reviews->filterViewable();

		$viewParams = [
			'reviews' => $reviews,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
		];
		return $this->view(
			View\LatestReviewsView::class,
			'dbtech_shop_latest_reviews',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReviews(ParameterBag $params): AbstractReply
	{
		if (!$params->item_id)
		{
			return $this->redirectPermanently($this->buildLink('dbtech-shop/latest-reviews'));
		}

		$item = $this->assertViewableItem($params->item_id);

		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/reviews', $item));

		$reviewId = $this->filter('item_rating_id', 'uint');
		if ($reviewId)
		{
			$review = \XF::app()->em()->find(ItemRating::class, $reviewId);
			if (!$review || $review->item_id != $item->item_id || !$review->is_review)
			{
				return $this->noPermission();
			}
			if (!$review->canView($error))
			{
				return $this->noPermission($error);
			}

			return $this->redirectPermanently($this->buildLink('dbtech-shop/review', $review));
		}

		$page = $this->filterPage();
		$perPage = \XF::app()->options()->dbtechShopReviewsPerPage;

		$ratingRepo = \XF::app()->repository(ItemRatingRepository::class);
		$reviewFinder = $ratingRepo->findReviewsInItem($item);

		$total = $item->real_review_count;
		if (!$total)
		{
			return $this->redirect($this->buildLink('dbtech-shop', $item));
		}

		$this->assertValidPage($page, $perPage, $total, 'dbtech-shop/reviews', $item);
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/reviews', $item, ['page' => $page]));

		$reviewFinder->with('full')->limitByPage($page, $perPage);
		$reviews = $reviewFinder->fetch();

		$viewParams = [
			'item' => $item,
			'reviews' => $reviews,

			'page' => $page,
			'perPage' => $perPage,
			'total' => $total,
		];
		return $this->view(
			View\Item\ReviewsView::class,
			'dbtech_shop_item_reviews',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionTags(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canEditTags($error))
		{
			return $this->noPermission($error);
		}

		$tagger = \XF::app()->service(ChangerService::class, 'dbtech_shop_item', $item);

		if ($this->isPost())
		{
			$tagger->setEditableTags($this->filter('tags', 'str'));
			if ($tagger->hasErrors())
			{
				return $this->error($tagger->getErrors());
			}

			$tagger->save();

			return $this->redirect($this->buildLink('dbtech-shop', $item));
		}

		$grouped = $tagger->getExistingTagsByEditability();

		$viewParams = [
			'item' => $item,
			'category' => $item->Category,
			'editableTags' => $grouped['editable'],
			'uneditableTags' => $grouped['uneditable'],
		];
		return $this->view(
			View\Item\TagsView::class,
			'dbtech_shop_item_tags',
			$viewParams
		);
	}

	/**
	 * @param Item $item
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
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

		if ($item->exists() && $item->canEditTags())
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
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canEdit($error))
		{
			return $this->noPermission($error);
		}

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
		$creator->setPerformValidations(true);

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
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAdd(): AbstractReply
	{
		$categoryId = $this->filter('category_id', 'uint');
		if ($categoryId)
		{
			$category = $this->assertViewableCategory($categoryId);
			if (!$category->canAddItem($error))
			{
				return $this->noPermission($error);
			}

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
			$this->assertCanonicalUrl($this->buildLink('dbtech-shop/add'));

			$categoryRepo = \XF::app()->repository(CategoryRepository::class);
			$categories = $categoryRepo->getViewableCategories();
			$canAdd = false;

			foreach ($categories AS $category)
			{
				/** @var Category $category */
				if ($category->canAddItem())
				{
					$canAdd = true;
					break;
				}
			}

			if (!$canAdd)
			{
				return $this->noPermission();
			}

			$categoryTree = $categoryRepo->createCategoryTree($categories);
			$categoryTree = $categoryTree->filter(null, function ($id, Category $category, $depth, $children): bool
			{
				if ($children)
				{
					return true;
				}
				if ($category->canAddItem())
				{
					return true;
				}

				return false;
			});

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
				'categoryTree' => $categoryTree,
				'categoryExtras' => $categoryExtras,
			];

			return $this->view(
				View\Item\AddChooserView::class,
				'dbtech_shop_item_add_chooser',
				$viewParams
			);
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
		$editor->setPerformValidations(true);

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
			$item = $this->assertViewableItem($params->item_id);
			if (!$item->canEdit($error))
			{
				return $this->noPermission($error);
			}

			$editor = $this->setupItemEdit($item);
			$editor->checkForSpam();

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}

			$editor->save();
			$this->finalizeItemEdit($editor);

			return $this->redirect($this->buildLink('dbtech-shop', $item));
		}

		$categoryId = $this->filter('category_id', 'uint');
		$category = $this->assertViewableCategory($categoryId);
		if (!$category->canAddItem($error))
		{
			return $this->noPermission($error);
		}

		$creator = $this->setupItemCreate($category);
		$creator->checkForSpam();

		if (!$creator->validate($errors))
		{
			throw $this->exception($this->error($errors));
		}
		$this->assertNotFlooding('post');

		/** @var Item $item */
		$item = $creator->save();
		$this->finalizeItemCreate($creator);

		if (!$item->canView())
		{
			return $this->redirect($this->buildLink('dbtech-shop/categories', $category, ['pending_approval' => 1]));
		}

		return $this->redirect($this->buildLink('dbtech-shop', $item));
	}

	/**
	 * @param EditService $editor
	 */
	protected function finalizeItemEdit(EditService $editor)
	{
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionBookmark(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);

		$bookmarkPlugin = $this->plugin(BookmarkPlugin::class);

		return $bookmarkPlugin->actionBookmark(
			$item,
			$this->buildLink('dbtech-shop/bookmark', $item)
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
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			if ($item->item_state == 'deleted')
			{
				$type = $this->filter('hard_delete', 'uint');
				switch ($type)
				{
					case 0:
						return $this->redirect($this->buildLink('dbtech-shop/categories', $item->Category));

					case 1:
						$reason = $this->filter('reason', 'str');
						if (!$item->canDelete('hard', $error))
						{
							return $this->noPermission($error);
						}

						$deleter = \XF::app()->service(DeleteService::class, $item);

						if ($this->filter('author_alert', 'bool'))
						{
							$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
						}

						$deleter->delete('hard', $reason);

						$inlineModPlugin = $this->plugin(InlineModPlugin::class);
						$inlineModPlugin->clearIdFromCookie('dbtech_shop_item', $item->item_id);

						return $this->redirect($this->buildLink('dbtech-shop/categories', $item->Category));

					case 2:
						if (!$item->canUndelete($error))
						{
							return $this->noPermission($error);
						}

						$deleter = \XF::app()->service(DeleteService::class, $item);

						if ($this->filter('author_alert', 'bool'))
						{
							$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
						}

						$deleter->unDelete();

						return $this->redirect($this->buildLink('dbtech-shop', $item));
				}
			}
			else
			{
				$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
				$reason = $this->filter('reason', 'str');
				if (!$item->canDelete($type, $error))
				{
					return $this->noPermission($error);
				}

				$deleter = \XF::app()->service(DeleteService::class, $item);

				if ($this->filter('author_alert', 'bool'))
				{
					$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
				}

				$deleter->delete($type, $reason);

				$inlineModPlugin = $this->plugin(InlineModPlugin::class);
				$inlineModPlugin->clearIdFromCookie('dbtech_shop_item', $item->item_id);

				return $this->redirect($this->buildLink('dbtech-shop'));
			}
		}

		$viewParams = [
			'item' => $item,
		];
		return $this->view(
			View\Item\DeleteView::class,
			'dbtech_shop_item_delete',
			$viewParams
		);
	}



	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionUndelete(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);

		$plugin = $this->plugin(UndeletePlugin::class);
		return $plugin->actionUndelete(
			$item,
			$this->buildLink('dbtech-shop/undelete', $item),
			$this->buildLink('dbtech-shop', $item),
			$item->title,
			'item_state'
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionReassign(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canReassign($error))
		{
			return $this->noPermission($error);
		}

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

			return $this->redirect($this->buildLink('dbtech-shop', $item));
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
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 * @TODO: Add return value for the XF 2.3 version, remove below
	 * @noinspection PhpMissingReturnTypeInspection
	 */
	public function actionMove(ParameterBag $params)/*: \XF\Mvc\Reply\AbstractReply*/
	{
		$item = $this->assertViewableItem($params->item_id);

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

			return $this->redirect($this->buildLink('dbtech-shop', $item));
		}

		$categoryRepo = \XF::app()->repository(CategoryRepository::class);
		$categories = $categoryRepo->getViewableCategories();

		$viewParams = [
			'item' => $item,
			'category' => $category,
			'prefixes' => $category->getUsablePrefixes(),
			'categoryTree' => $categoryRepo->createCategoryTree($categories),
		];
		return $this->view(
			View\Item\MoveView::class,
			'dbtech_shop_item_move',
			$viewParams
		);
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
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReport(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canReport($error))
		{
			return $this->noPermission($error);
		}

		$reportPlugin = $this->plugin(ReportPlugin::class);
		return $reportPlugin->actionReport(
			'dbtech_shop_item',
			$item,
			$this->buildLink('dbtech-shop/report', $item),
			$this->buildLink('dbtech-shop', $item)
		);
	}



	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReact(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canReact($error))
		{
			return $this->noPermission($error);
		}

		$reactionPlugin = $this->plugin(ReactionPlugin::class);
		return $reactionPlugin->actionReactSimple($item, 'dbtech-shop');
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReactions(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);

		$breadcrumbs = $item->Category->getBreadcrumbs();

		$reactionPlugin = $this->plugin(ReactionPlugin::class);
		return $reactionPlugin->actionReactions(
			$item,
			'dbtech-shop/reactions',
			null,
			$breadcrumbs
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIp(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		$breadcrumbs = $item->getBreadcrumbs();

		$ipPlugin = $this->plugin(IpPlugin::class);
		return $ipPlugin->actionIp($item, $breadcrumbs);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionWarn(ParameterBag $params): AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);

		if (!$item->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $item->getBreadcrumbs();

		$warnPlugin = $this->plugin(WarnPlugin::class);
		return $warnPlugin->actionWarn(
			'dbtech_shop_item',
			$item,
			$this->buildLink('dbtech-shop/warn', $item),
			$breadcrumbs
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionApprove(ParameterBag $params): AbstractReply
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}

		$approver = \XF::app()->service(ApproveService::class, $item);
		$approver->approve();

		return $this->redirect($this->buildLink('dbtech-shop', $item));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionUnapprove(ParameterBag $params): AbstractReply
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}

		$item->item_state = 'moderated';
		$item->save();

		return $this->redirect($this->buildLink('dbtech-shop', $item));
	}

	/**
	 * @return AbstractReply
	 */
	public function actionAutoComplete(): AbstractReply
	{
		$q = ltrim($this->filter('q', 'str', ['no-trim']));

		if ($q !== '' && \mb_strlen($q) >= 2)
		{
			/** @var ItemFinder $itemFinder */
			$itemFinder = \XF::app()->finder(ItemFinder::class);
			$itemFinder = $itemFinder->searchText($q);

			$itemType = $this->filter('item_type', 'str');
			if ($itemType)
			{
				$itemFinder->where('item_type', $itemType);
			}

			/** @var \DBTech\Shop\XF\Entity\User $visitor */
			$visitor = \XF::visitor();

			$itemFinder->with([
				'User',
				'Category',
				'Category.Permissions|' . $visitor->permission_combination_id,
			]);

			$items = $itemFinder->fetch(10);
			$items = $items->filterViewable();
		}
		else
		{
			$items = [];
			$q = '';
		}

		$viewParams = [
			'q' => $q,
			'items' => $items,
		];
		return $this->view(
			View\Item\FindView::class,
			'',
			$viewParams
		);
	}

	/**
	 * @return array
	 */
	protected function getItemViewExtraWith(): array
	{
		$extraWith = [];
		$userId = \XF::visitor()->user_id;
		if ($userId)
		{
			$extraWith[] = 'Watch|' . $userId;
			//			$extraWith[] = 'Likes|' . $userId;
		}

		return $extraWith;
	}

	/**
	 * @param int|null $itemId
	 * @param array $extraWith
	 *
	 * @return Item
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableItem(?int $itemId, array $extraWith = []): Item
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Permissions|' . $visitor->permission_combination_id;
		$extraWith[] = 'User';
		$extraWith[] = 'Category';
		$extraWith[] = 'Category.Permissions|' . $visitor->permission_combination_id;
		$extraWith[] = 'Discussion';
		$extraWith[] = 'Discussion.Forum';
		$extraWith[] = 'Discussion.Forum.Node';
		$extraWith[] = 'Discussion.Forum.Node.Permissions|' . $visitor->permission_combination_id;

		if ($visitor->user_id)
		{
			$extraWith[] = 'Watch|' . $visitor->user_id;
		}

		$item = \XF::app()->em()->find(Item::class, $itemId, $extraWith);
		if (!$item)
		{
			throw $this->exception($this->notFound(\XF::phrase('dbtech_shop_requested_item_not_found')));
		}

		if (!$item->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $item;
	}

	/**
	 * @param int|null $categoryId
	 * @param array $extraWith
	 *
	 * @return Category
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableCategory(?int $categoryId, array $extraWith = []): Category
	{
		$visitor = \XF::visitor();

		$extraWith[] = 'Permissions|' . $visitor->permission_combination_id;

		$category = \XF::app()->em()->find(Category::class, $categoryId, $extraWith);
		if (!$category)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_category_not_found')));
		}

		if (!$category->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $category;
	}

	/**
	 * @param array $activities
	 *
	 * @return array
	 */
	public static function getActivityDetails(array $activities): array
	{
		return self::getActivityDetailsForContent(
			$activities,
			\XF::phrase('dbtech_shop_viewing_item'),
			'item_id',
			function (array $ids): array
			{
				$items = \XF::app()->em()->findByIds(
					Item::class,
					$ids,
					['Category', 'Category.Permissions|' . \XF::visitor()->permission_combination_id]
				);

				$router = \XF::app()->router('public');
				$data = [];

				foreach ($items->filterViewable() AS $id => $item)
				{
					$data[$id] = [
						'title' => $item->title,
						'url' => $router->buildLink('dbtech-shop', $item),
					];
				}

				return $data;
			},
			\XF::phrase('dbtech_shop_viewing_items')
		);
	}
}