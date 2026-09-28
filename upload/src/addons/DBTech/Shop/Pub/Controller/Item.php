<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Class Item
 *
 * @package DBTech\Shop\Pub\Controller
 */
class Item extends AbstractController
{
	/**
	 * @param $action
	 * @param ParameterBag $params
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function preDispatchController($action, ParameterBag $params)
	{
		switch ($action)
		{
			case 'LatestReviews':
			case 'Reviews':
			case 'Rate':
				if (!$this->options()->dbtechShopEnableRate)
				{
					throw $this->exception($this->noPermission());
				}
				break;
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Reroute|\XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionIndex(ParameterBag $params)
	{
		if ($params->item_id)
		{
			return $this->rerouteController(__CLASS__, 'view', $params);
		}
		
		$currency = $this->assertCurrencyExists(1);

		$eventFinder = $this->finder('DBTech\Credits:Event')
        ->where('currency_id', $currency->currency_id)
        ->where('event_trigger_id', [
            'donate', 'adjust', 'purchase', 'redeem', 'transfer'
        ]);

		$events = $eventFinder->fetch();
		
		/** @var \DBTech\Shop\ControllerPlugin\Overview $overviewPlugin */
		$overviewPlugin = $this->plugin('DBTech\Shop:Overview');
		
		$categoryParams = $overviewPlugin->getCategoryListData();
		$viewableCategoryIds = $categoryParams['categories']->keys();
		
		$listParams = $overviewPlugin->getCoreListData($viewableCategoryIds);
		
		$this->assertValidPage($listParams['page'], $listParams['perPage'], $listParams['total'], 'dbtech-shop');
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop', null, ['page' => $listParams['page']]));
		
		$viewParams = $categoryParams + $listParams + ['events' => $events] + ['categoriesItem' => $categoryParams];
		return $this->view('DBTech\Shop:Overview', 'dbtech_shop_overview', $viewParams);
	}

	public function assertCurrencyExists($currencyId)
	{
		// Fetch the currency entity from the database
		$currencyFinder = $this->finder('DBTech\Credits:Currency');
		$currency = $currencyFinder->where('currency_id', $currencyId)->fetchOne();

		// Check if the currency exists
		if (!$currency) {
			throw new \XF\Mvc\Reply\Exception(\XF::phrase('requested_currency_not_found'), 404);
		}

		return $currency;
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Reroute|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionView(ParameterBag $params)
	{
		$item = $this->assertViewableItem($params->item_id, $this->getItemViewExtraWith());
		
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop', $item));
		
		$viewParams = [
			'item' => $item,
			'category' => $item->Category,
		];
		return $this->view('DBTech\Shop:Item\View', 'dbtech_shop_item_view', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionField(ParameterBag $params)
	{
		$item = $this->assertViewableItem($params->item_id);
		
		$fieldId = $this->filter('field', 'str');
		$tabFields = $item->getExtraFieldTabs();
		
		if (!isset($tabFields[$fieldId]))
		{
			return $this->redirect($this->buildLink('dbtech-shop', $item));
		}
		
		/** @var \XF\CustomField\Set $fieldSet */
		$fieldSet = $item->item_fields;
		$definition = $fieldSet->getDefinition($fieldId);
		$fieldValue = $fieldSet->getFieldValue($fieldId);
		
		$viewParams = [
			'item' => $item,
			'category' => $item->Category,
			
			'fieldId' => $fieldId,
			'fieldDefinition' => $definition,
			'fieldValue' => $fieldValue
		];
		return $this->view('DBTech\Shop:Item\Field', 'dbtech_shop_item_field', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \RuntimeException
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionEditIcon(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canEditIcon($error))
		{
			return $this->noPermission($error);
		}
		
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
			
			return $this->redirect($this->buildLink('dbtech-shop', $item));
		}
		
		$viewParams = [
			'item' => $item,
		];
		return $this->view('DBTech\Shop:Item\EditIcon', 'dbtech_shop_item_edit_icon', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionPurchase(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
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
			) {
				/** @var \XF\Entity\User $toUser */
				$toUser = $this->repository('XF:User')
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
			
			/** @var \DBTech\Shop\Service\Cart\Creator $creator */
			$creator = $this->service('DBTech\Shop:Cart\Creator');
			
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
		
		// Calculate discount information for the template
		$finalPrice = $item->getFinalPriceForUser();
		$hasDiscount = $item->hasDiscountForUser();
		$discount = $hasDiscount ? $item->getDiscountForUser() : null;
		
		$viewParams = [
			'item' => $item,
			'redirect' => $redirect,
			'finalPrice' => $finalPrice,
			'hasDiscount' => $hasDiscount,
			'discount' => $discount,
			'originalPrice' => $item->price
		];
		return $this->view('DBTech\Shop:Item\Purchase', 'dbtech_shop_item_purchase', $viewParams);
	}
	
	/**
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 */
	public function actionFilters()
	{
		/** @var \DBTech\Shop\ControllerPlugin\Overview $overviewPlugin */
		$overviewPlugin = $this->plugin('DBTech\Shop:Overview');
		
		return $overviewPlugin->actionFilters();
	}

	/**
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionPrefixes(ParameterBag $params)
	{
		$this->assertPostOnly();

		$categoryId = $this->filter('val', 'uint');

		/** @var \DBTech\Shop\Entity\Category $category */
		$category = $this->em()->find(
			'DBTech\Shop:Category',
			$categoryId,
			'Permissions|' . \XF::visitor()->permission_combination_id
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
			'prefixes' => $category->getUsablePrefixes()
		];
		return $this->view('DBTech\Shop:Category\Prefixes', 'dbtech_shop_category_prefixes', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionWatch(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
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
					'email_subscribe' => $this->filter('email_subscribe', 'bool')
				];
			}
			
			/** @var \DBTech\Shop\Repository\ItemWatch $watchRepo */
			$watchRepo = $this->repository('DBTech\Shop:ItemWatch');
			$watchRepo->setWatchState($item, $visitor, $action, $config);
			
			$redirect = $this->redirect($this->buildLink('dbtech-shop', $item));
			$redirect->setJsonParam('switchKey', $action == 'delete' ? 'watch' : 'unwatch');
			return $redirect;
		}
		
		$viewParams = [
			'item' => $item,
			'isWatched' => !empty($item->Watch[$visitor->user_id]),
			'category' => $item->Category
		];
		return $this->view('DBTech\Shop:Item\Watch', 'dbtech_shop_item_watch', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionRate(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
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
		
		/** @var \DBTech\Shop\Entity\ItemRating|null $existingRating */
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
			'existingRating' => $existingRating
		];
		return $this->view('DBTech\Shop:Item\Rate', 'dbtech_shop_item_rate', $viewParams);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 *
	 * @return \DBTech\Shop\Service\Item\Rate
	 */
	protected function setupItemRate(\DBTech\Shop\Entity\Item $item): \DBTech\Shop\Service\Item\Rate
	{
		/** @var \DBTech\Shop\Service\Item\Rate $rater */
		$rater = $this->service('DBTech\Shop:Item\Rate', $item);
		
		$input = $this->filter([
			'rating' => 'uint',
			'message' => 'str',
			'is_anonymous' => 'bool'
		]);
		
		$rater->setRating($input['rating'], $input['message']);
		
		return $rater;
	}
	
	/**
	 * @return \XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionLatestReviews(): \XF\Mvc\Reply\AbstractReply
	{
		$viewableCategoryIds = $this->getCategoryRepo()->getViewableCategoryIds();
		
		/** @var \DBTech\Shop\Repository\ItemRating $ratingRepo */
		$ratingRepo = $this->repository('DBTech\Shop:ItemRating');
		$finder = $ratingRepo->findLatestReviews($viewableCategoryIds);
		
		$total = $finder->total();
		$page = $this->filterPage();
		$perPage = $this->options()->dbtechShopReviewsPerPage;
		
		$this->assertValidPage($page, $perPage, $total, 'dbtech-shop/latest-reviews');
		$this->assertCanonicalUrl($this->buildLink('dbtech-shop/latest-reviews', null, ['page' => $page]));
		
		$reviews = $finder->limitByPage($page, $perPage)->fetch();
		$reviews = $reviews->filterViewable();
		
		$viewParams = [
			'reviews' => $reviews,
			'page' => $page,
			'perPage' => $perPage,
			'total' => $total
		];
		return $this->view('DBTech\Shop:LatestReviews', 'dbtech_shop_latest_reviews', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReviews(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
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
			/** @var \DBTech\Shop\Entity\ItemRating|null $review */
			$review = $this->em()->find('DBTech\Shop:ItemRating', $reviewId);
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
		$perPage = $this->options()->dbtechShopReviewsPerPage;
		
		/** @var \DBTech\Shop\Repository\ItemRating $ratingRepo */
		$ratingRepo = $this->repository('DBTech\Shop:ItemRating');
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
			'total' => $total
		];
		return $this->view('DBTech\Shop:Item\Reviews', 'dbtech_shop_item_reviews', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionTags(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canEditTags($error))
		{
			return $this->noPermission($error);
		}
		
		/** @var \XF\Service\Tag\Changer $tagger */
		$tagger = $this->service('XF:Tag\Changer', 'dbtech_shop_item', $item);
		
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
			'uneditableTags' => $grouped['uneditable']
		];
		return $this->view('DBTech\Shop:Item\Tags', 'dbtech_shop_item_tags', $viewParams);
	}
	
	/**
	 * @param \DBTech\Shop\Entity\Item $item
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
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
		
		if ($item->exists() && $item->canEditTags())
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
		];
		return $this->view('DBTech\Shop:Item\Edit', 'dbtech_shop_item_edit', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 * @return \XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canEdit($error))
		{
			return $this->noPermission($error);
		}
		
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
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionAdd(): \XF\Mvc\Reply\AbstractReply
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
			$this->assertCanonicalUrl($this->buildLink('dbtech-shop/add'));
			
			$categoryRepo = $this->getCategoryRepo();
			
			$categories = $categoryRepo->getViewableCategories();
			$canAdd = false;
			
			foreach ($categories AS $category)
			{
				/** @var \DBTech\Shop\Entity\Category $category */
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
			$categoryTree = $categoryTree->filter(null, function ($id, \DBTech\Shop\Entity\Category $category, $depth, $children): bool
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
				foreach ($categoryExtras as &$extra)
				{
					$extra['item_type'] = $itemType;
				}
			}
			
			$viewParams = [
				'categoryTree' => $categoryTree,
				'categoryExtras' => $categoryExtras
			];
			
			return $this->view('DBTech\Shop:Item\AddChooser', 'dbtech_shop_item_add_chooser', $viewParams);
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
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionSave(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
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
		
		/** @var \DBTech\Shop\Entity\Item $item */
		$item = $creator->save();
		$this->finalizeItemCreate($creator);
		
		if (!$item->canView())
		{
			return $this->redirect($this->buildLink('dbtech-shop/categories', $category, ['pending_approval' => 1]));
		}
		
		return $this->redirect($this->buildLink('dbtech-shop', $item));
	}
	
	/**
	 * @param \DBTech\Shop\Service\Item\Edit $editor
	 */
	protected function finalizeItemEdit(\DBTech\Shop\Service\Item\Edit $editor)
	{
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionBookmark(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		
		/** @var \XF\ControllerPlugin\Bookmark $bookmarkPlugin */
		$bookmarkPlugin = $this->plugin('XF:Bookmark');
		
		return $bookmarkPlugin->actionBookmark(
			$item,
			$this->buildLink('dbtech-shop/bookmark', $item)
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \LogicException
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionDelete(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
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
						
						/** @var \DBTech\Shop\Service\Item\Delete $deleter */
						$deleter = $this->service('DBTech\Shop:Item\Delete', $item);
						
						if ($this->filter('author_alert', 'bool'))
						{
							$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
						}
						
						$deleter->delete('hard', $reason);
						
						/** @var \XF\ControllerPlugin\InlineMod $inlineModPlugin */
						$inlineModPlugin = $this->plugin('XF:InlineMod');
						$inlineModPlugin->clearIdFromCookie('dbtech_shop_item', $item->item_id);
						
						return $this->redirect($this->buildLink('dbtech-shop/categories', $item->Category));

					case 2:
						if (!$item->canUndelete($error))
						{
							return $this->noPermission($error);
						}
						
						/** @var \DBTech\Shop\Service\Item\Delete $deleter */
						$deleter = $this->service('DBTech\Shop:Item\Delete', $item);
						
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
				
				/** @var \DBTech\Shop\Service\Item\Delete $deleter */
				$deleter = $this->service('DBTech\Shop:Item\Delete', $item);
				
				if ($this->filter('author_alert', 'bool'))
				{
					$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
				}
				
				$deleter->delete($type, $reason);
				
				/** @var \XF\ControllerPlugin\InlineMod $inlineModPlugin */
				$inlineModPlugin = $this->plugin('XF:InlineMod');
				$inlineModPlugin->clearIdFromCookie('dbtech_shop_item', $item->item_id);
				
				return $this->redirect($this->buildLink('dbtech-shop'));
			}
		}

		$viewParams = [
			'item' => $item
		];
		return $this->view('DBTech\Shop:Item\Delete', 'dbtech_shop_item_delete', $viewParams);
	}



	/**
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionUndelete(ParameterBag $params)
	{
		$item = $this->assertViewableItem($params->item_id);

		/** @var \XF\ControllerPlugin\Undelete $plugin */
		$plugin = $this->plugin('XF:Undelete');
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
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionReassign(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canReassign($error))
		{
			return $this->noPermission($error);
		}
		
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
			
			return $this->redirect($this->buildLink('dbtech-shop', $item));
		}
		
		$viewParams = [
			'item' => $item,
			'category' => $item->Category
		];
		return $this->view('XF:Item\Reassign', 'dbtech_shop_item_reassign', $viewParams);
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
		$item = $this->assertViewableItem($params->item_id);
		
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
			
			return $this->redirect($this->buildLink('dbtech-shop', $item));
		}
		
		$categoryRepo = $this->getCategoryRepo();
		$categories = $categoryRepo->getViewableCategories();
		
		$viewParams = [
			'item' => $item,
			'category' => $category,
			'prefixes' => $category->getUsablePrefixes(),
			'categoryTree' => $categoryRepo->createCategoryTree($categories)
		];
		return $this->view('DBTech\Shop:Item\Move', 'dbtech_shop_item_move', $viewParams);
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
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReport(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canReport($error))
		{
			return $this->noPermission($error);
		}
		
		/** @var \XF\ControllerPlugin\Report $reportPlugin */
		$reportPlugin = $this->plugin('XF:Report');
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
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReact(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canReact($error))
		{
			return $this->noPermission($error);
		}
		
		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactSimple($item, 'dbtech-shop');
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Message|\XF\Mvc\Reply\View
	 * @throws \InvalidArgumentException
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReactions(ParameterBag $params)
	{
		$item = $this->assertViewableItem($params->item_id);
		
		$breadcrumbs = $item->Category->getBreadcrumbs();
		
		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
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
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIp(ParameterBag $params)
	{
		$item = $this->assertViewableItem($params->item_id);
		$breadcrumbs = $item->getBreadcrumbs();
		
		/** @var \XF\ControllerPlugin\Ip $ipPlugin */
		$ipPlugin = $this->plugin('XF:Ip');
		return $ipPlugin->actionIp($item, $breadcrumbs);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionWarn(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$item = $this->assertViewableItem($params->item_id);
		
		if (!$item->canWarn($error))
		{
			return $this->noPermission($error);
		}
		
		$breadcrumbs = $item->getBreadcrumbs();
		
		/** @var \XF\ControllerPlugin\Warn $warnPlugin */
		$warnPlugin = $this->plugin('XF:Warn');
		return $warnPlugin->actionWarn(
			'dbtech_shop_item',
			$item,
			$this->buildLink('dbtech-shop/warn', $item),
			$breadcrumbs
		);
	}

	/**
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionApprove(ParameterBag $params)
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));
		
		$item = $this->assertViewableItem($params->item_id);
		if (!$item->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}
		
		/** @var \DBTech\Shop\Service\Item\Approve $approver */
		$approver = \XF::service('DBTech\Shop:Item\Approve', $item);
		$approver->approve();
		
		return $this->redirect($this->buildLink('dbtech-shop', $item));
	}

	/**
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionUnapprove(ParameterBag $params)
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
	 * @return \XF\Mvc\Reply\View
	 */
	public function actionAutoComplete(): \XF\Mvc\Reply\AbstractReply
	{
		$q = ltrim($this->filter('q', 'str', ['no-trim']));
		
		if ($q !== '' && utf8_strlen($q) >= 2)
		{
			/** @var \DBTech\Shop\Finder\Item $itemFinder */
			$itemFinder = $this->finder('DBTech\Shop:Item');
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
				'Category.Permissions|' . $visitor->permission_combination_id
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
			'items' => $items
		];
		return $this->view('DBTech\Shop:Item\Find', '', $viewParams);
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
	 * @return \DBTech\Shop\Entity\Item
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableItem(?int $itemId, array $extraWith = []): \DBTech\Shop\Entity\Item
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
		
		/** @var \DBTech\Shop\Entity\Item $item */
		$item = $this->em()->find('DBTech\Shop:Item', $itemId, $extraWith);
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
	 * @return \DBTech\Shop\Entity\Category
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableCategory(?int $categoryId, array $extraWith = []): \DBTech\Shop\Entity\Category
	{
		$visitor = \XF::visitor();
		
		$extraWith[] = 'Permissions|' . $visitor->permission_combination_id;
		
		/** @var \DBTech\Shop\Entity\Category $category */
		$category = $this->em()->find('DBTech\Shop:Category', $categoryId, $extraWith);
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
	
	/**
	 * @param array $activities
	 *
	 * @return array|bool
	 */
	public static function getActivityDetails(array $activities)
	{
		return self::getActivityDetailsForContent(
			$activities,
			\XF::phrase('dbtech_shop_viewing_item'),
			'item_id',
			function (array $ids): array
			{
				$items = \XF::em()->findByIds(
					'DBTech\Shop:Item',
					$ids,
					['Category', 'Category.Permissions|' . \XF::visitor()->permission_combination_id]
				);
				
				$router = \XF::app()->router('public');
				$data = [];
				
				foreach ($items->filterViewable() AS $id => $item)
				{
					$data[$id] = [
						'title' => $item->title,
						'url' => $router->buildLink('dbtech-shop', $item)
					];
				}
				
				return $data;
			},
			\XF::phrase('dbtech_shop_viewing_items')
		);
	}
}