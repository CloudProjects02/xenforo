<?php

namespace DBTech\Shop;

use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Entity\Item;
use DBTech\Shop\Repository\CurrencyRepository;
use DBTech\Shop\Repository\ItemFieldRepository;
use DBTech\Shop\Repository\ItemPrefixRepository;
use DBTech\Shop\Repository\PurchaseRepository;
use XF\Container;
use XF\CustomField\DefinitionSet;
use XF\Db\Exception;
use XF\Entity\Post;
use XF\Entity\Thread;
use XF\Entity\User;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Renderer\AbstractRenderer;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\App;
use XF\Service\User\ContentChangeService;
use XF\Service\User\DeleteCleanUpService;
use XF\Service\User\MergeService;
use XF\Template\Templater;

class Listener
{
	/**
	 * The product ID (in the DBTech store)
	 */
	protected static int $_productId = 336;


	/**
	 * @param App $app
	 */
	public static function appPubSetup(App $app): void
	{
		/*DBTECH_BRANDING_START*/
		// Make sure we fetch the branding array from the application
		$branding = $app->offsetExists('dbtech_branding') ? $app->dbtech_branding : [];

		// Add productid to the array
		$branding[] = self::$_productId;

		// Store the branding
		$app->dbtech_branding = $branding;
		/*DBTECH_BRANDING_END*/
	}

	/**
	 * Called during the global \XF\App object setup. This will fire regardless of the application type.
	 *
	 * @param \XF\App $app The global app object.
	 * @param array $keys An array of keys to preload from the registry.
	 *
	 * @noinspection PhpUnusedParameterInspection
	 */
	public static function appRegistryPreload(\XF\App $app, array &$keys): void
	{
		$keys[] = 'dbtShopPrefixes';
		$keys[] = 'dbtShopItemFieldsInfo';
		$keys[] = 'dbtShopCurrencies';
		$keys[] = 'dbtShopUserNameStyle';
		$keys[] = 'dbtShopUserTitleStyle';
		$keys[] = 'dbtShopAvatarStyle';
	}

	/**
	 * @param \XF\App $app
	 *
	 * @throws Exception
	 */
	public static function appSetup(\XF\App $app): void
	{
		$container = $app->container();

		$container['prefixes.dbtechShopItem'] = $app->fromRegistry(
			'dbtShopPrefixes',
			function (Container $c) { return $c['em']->getRepository(ItemPrefixRepository::class)->rebuildPrefixCache(); }
		);

		$container['customFields.dbtechShopItems'] = $app->fromRegistry(
			'dbtShopItemFieldsInfo',
			function (Container $c) { return $c['em']->getRepository(ItemFieldRepository::class)->rebuildFieldCache(); },
			function (array $fields) use ($app): DefinitionSet
			{
				$class = \XF::extendClass(DefinitionSet::class);
				return new $class($fields);
			}
		);

		$container['dbtechShop.currencies'] = $app->fromRegistry(
			'dbtShopCurrencies',
			function (Container $c) { return $c['em']->getRepository(CurrencyRepository::class)->rebuildCache(); },
			function (array $currencies): AbstractCollection
			{
				$em = \XF::em();

				$entities = [];
				foreach ($currencies AS $currencyId => $currency)
				{
					$entities[$currencyId] = \XF::app()->em()->instantiateEntity(Currency::class, $currency);
				}

				return $em->getBasicCollection($entities);
			}
		);

		$container['dbtechShop.usernameStyles'] = $app->fromRegistry(
			'dbtShopUserNameStyle',
			function (Container $c) { return $c['em']->getRepository(PurchaseRepository::class)->rebuildUserNameStyleCache(); }
		);

		$container['dbtechShop.usertitleStyles'] = $app->fromRegistry(
			'dbtShopUserTitleStyle',
			function (Container $c) { return $c['em']->getRepository(PurchaseRepository::class)->rebuildUserTitleStyleCache(); }
		);

		$container['dbtechShop.avatarStyles'] = $app->fromRegistry(
			'dbtShopAvatarStyle',
			function (Container $c) { return $c['em']->getRepository(PurchaseRepository::class)->rebuildAvatarStyleCache(); }
		);

		
	}

	/**
	 * @param Templater $templater
	 * @param string $type
	 * @param string $template
	 * @param string $name
	 * @param array $arguments
	 * @param array $globalVars
	 */
	public static function templaterMacroPreRender(
		Templater $templater,
		string &$type,
		string &$template,
		string &$name,
		array &$arguments,
		array &$globalVars
	): void
	{
		if (!empty($arguments['group']) && $arguments['group']->group_id == 'dbtech_shop')
		{
			// Override template name
			$template = 'dbtech_shop_option_macros';
		}
	}

	/**
	 * @param DeleteCleanUpService $deleteService
	 * @param array $deletes
	 */
	public static function userDeleteCleanInit(DeleteCleanUpService $deleteService, array &$deletes): void
	{
		$deletes['xf_dbtech_shop_category_watch'] = 'user_id = ?';
		$deletes['xf_dbtech_shop_item_rating'] = 'user_id = ?';
		$deletes['xf_dbtech_shop_item_watch'] = 'user_id = ?';
		$deletes['xf_dbtech_shop_purchase'] = 'user_id = ?';
		$deletes['xf_dbtech_shop_transaction_log'] = 'user_id = ?';
	}

	/**
	 * @param User $target
	 * @param User $source
	 * @param MergeService $mergeService
	 */
	public static function userMergeCombine(
		User $target,
		User $source,
		MergeService $mergeService
	): void
	{
		$target->dbtech_shop_item_count += $source->dbtech_shop_item_count;
		$target->dbtech_shop_purchases += $source->dbtech_shop_purchases;
	}

	/**
	 * @param \XF\Searcher\User $userSearcher
	 * @param array $sortOrders
	 */
	public static function userSearcherOrders(\XF\Searcher\User $userSearcher, array &$sortOrders): void
	{
		$sortOrders['dbtech_shop_item_count'] = \XF::phrase('dbtech_shop_item_count');
		$sortOrders['dbtech_shop_purchases'] = \XF::phrase('dbtech_shop_purchases');
	}

	/**
	 * @param App $app
	 * @param array $params
	 * @param AbstractReply $reply
	 * @param AbstractRenderer $renderer
	 */
	public static function appPubRenderPage(
		App $app,
		array &$params,
		AbstractReply $reply,
		AbstractRenderer $renderer
	): void
	{
		foreach ($params['selectedNavChildren'] AS $key => &$child)
		{
			if (str_contains($key, 'dbtechCreditsCurrency'))
			{
				$child['attributes']['class'] = '';
			}
		}
		unset($child);
	}

	/**
	 * @param $rule
	 * @param array $data
	 * @param User $user
	 * @param $returnValue
	 */
	public static function criteriaUser($rule, array $data, User $user, &$returnValue): void
	{
		/** @var XF\Entity\User $user */

		switch ($rule)
		{
			case 'dbtech_shop_purchases':
				if ($user->dbtech_shop_purchases >= $data['purchases'])
				{
					$returnValue = true;
				}
				break;

			case 'dbtech_shop_item':
				$data['itemIds'] = $data['itemIds'] ?? (!empty($data['itemid']) ? [$data['itemid']] : []);

				$purchasedGrouped = $user->dbtech_shop_purchase
					->filter(function (Entity\Purchase $purchase) use ($data): ?Entity\Purchase
					{
						if (!\in_array($purchase->item_id, $data['itemIds']))
						{
							return null;
						}

						if (!$purchase->isActive() && !empty($data['activeonly_user']))
						{
							return null;
						}

						if (!$purchase->Item->isVisible() && !empty($data['activeonly_global']))
						{
							return null;
						}

						return $purchase;
					})
					->groupBy('item_id', 'purchase_id')
				;

				$returnValue = !empty($data['all'])
					? (\count($purchasedGrouped) === \count($data['itemIds']))
					: (\count($purchasedGrouped) > 0)
				;
				break;

			case 'dbtech_shop_itemtype':
				$purchases = $user->dbtech_shop_purchase
					->filter(function (Entity\Purchase $purchase) use ($data): ?Entity\Purchase
					{
						if ($purchase->Item->item_type_id != $data['itemtypeid'])
						{
							return null;
						}

						return $purchase;
					})
				;

				$returnValue = $purchases->count() > 0;
				break;
		}
	}

	/**
	 * @param App $app
	 * @param array $navigationFlat
	 * @param array $navigationTree
	 */
	public static function navigationSetup(App $app, array &$navigationFlat, array &$navigationTree): void
	{
		if (!isset($navigationFlat['dbtechShop']) || !isset($navigationTree['dbtechShop']))
		{
			return;
		}

		/** @var XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		/** @var Currency $currency */
		if ($visitor->user_id && $currency = \XF::app()->repository(CurrencyRepository::class)->getDisplayCurrencyFromCache())
		{
			// Update the navbar title
			$navigationFlat['dbtechShop']['title'] = \XF::phrase('dbtech_shop_display_currency_phrase', [
				'currency' => $currency->title,
				'prefix' => $currency->prefix,
				'amount' => $currency->getValueFromUser(),
				'suffix' => $currency->suffix,
			]);
		}

		// Set the counter
		$navigationFlat['dbtechShop']['counter'] = $visitor->dbtech_shop_pendingtrades;

		if (isset($navigationFlat['dbtechShop']['children']['dbtechShopTrade']))
		{
			// Add the counter to the child element
			$navigationFlat['dbtechShop']['children']['dbtechShopTrade']['counter'] = $visitor->dbtech_shop_pendingtrades;
		}
	}

	/**
	 * @param ContentChangeService $changeService
	 * @param array $updates
	 */
	public static function userContentChangeInit(ContentChangeService $changeService, array &$updates): void
	{
		$updates['xf_dbtech_shop_category_watch'] = ['user_id', 'emptyable' => false];
		$updates['xf_dbtech_shop_item'] = ['user_id', 'username'];
		$updates['xf_dbtech_shop_item_rating'] = ['user_id', 'emptyable' => false];
		$updates['xf_dbtech_shop_item_watch'] = ['user_id', 'emptyable' => false];
		$updates['xf_dbtech_shop_purchase'] = [
			['user_id', 'emptyable' => false],
			['buyer_user_id', 'buyer_username'],
		];
	}

	/**
	 * @param Container $container
	 * @param Templater $templater
	 */
	public static function templaterSetup(Container $container, Templater &$templater): void
	{
		$templater->addFunctions([
			'dbtech_shop_item_icon'          => [__CLASS__, 'templaterFnItemIcon'],
			'dbtech_shop_thread_background'  => [__CLASS__, 'templaterFnRichThreadbit'],
			'dbtech_shop_post_background'    => [__CLASS__, 'templaterFnRichPostBackground'],
			'dbtech_shop_postbit_background' => [__CLASS__, 'templaterFnRichPostbit'],
			'dbtech_shop_post_style'         => [__CLASS__, 'templaterFnRichPostMessage'],
		]);

		$templater->addFilters([
			'dbtech_shop_thread_style' => [__CLASS__, 'templaterFilterRichThreadTitle'],
		]);
	}

	/**
	 * @param Templater $templater
	 * @param bool $escape
	 * @param Item $item
	 * @param string $size
	 * @param string $href
	 * @param string $xfClick
	 *
	 * @return string
	 */
	public static function templaterFnItemIcon(
		Templater $templater,
		bool &$escape,
		Item $item,
		string $size = 'm',
		string $href = '',
		string $xfClick = ''
	): string
	{
		$escape = false;

		if ($href)
		{
			$tag = 'a';
			$hrefAttr = 'href="' . \XF::escapeString($href) . '" data-xf-click="' . $xfClick . '"';
		}
		else
		{
			$tag = 'span';
			$hrefAttr = '';
		}

		if (!$item->icon_date)
		{
			return "<$tag $hrefAttr class=\"avatar avatar--$size avatar--itemIconDefault\"><span></span></$tag>";
		}

		$src = $item->getIconUrl($size);

		return "<$tag $hrefAttr class=\"avatar avatar--$size avatar--itemIcon\">"
			. '<img src="' . \XF::escapeString($src) . '" alt="' . \XF::escapeString($item->title) . '" loading="lazy" />'
			. "</$tag>";
	}

	/**
	 * @param Templater $templater
	 * @param bool $escape
	 * @param Thread $thread
	 * @param bool $transparent
	 *
	 * @return string
	 */
	public static function templaterFnRichThreadbit(
		Templater $templater,
		bool &$escape,
		Thread $thread,
		bool $transparent = false
	): string
	{
		if (!$thread->thread_id
			|| !$thread->User
		)
		{
			return '';
		}

		/** @var XF\Entity\User $user */
		$user = $thread->User;

		$styleProps = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)->filterActivePurchasesForUser($user);
		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('thread_bit_markup', [$thread, &$styleProps, $transparent], $thread->thread_id);
		}

		if ($styleProps)
		{
			// Ensure we only add the span if needed
			return implode('; ', $styleProps);
		}

		return '';
	}

	/**
	 * @param Templater $templater
	 * @param bool $escape
	 * @param Post $post
	 *
	 * @return string
	 */
	public static function templaterFnRichPostMessage(
		Templater $templater,
		bool &$escape,
		Post $post
	): string
	{
		if (!$post->post_id
			|| !$post->User
		)
		{
			return '';
		}

		/** @var XF\Entity\User $user */
		$user = $post->User;

		$styleProps = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)->filterActivePurchasesForUser($user);
		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('post_message_markup', [$post, &$styleProps], $post->post_id);
		}

		if ($styleProps)
		{
			// Ensure we only add the span if needed
			return implode('; ', $styleProps);
		}

		return '';
	}

	/**
	 * @param Templater $templater
	 * @param bool $escape
	 * @param Post $post
	 * @param bool $transparent
	 *
	 * @return string
	 */
	public static function templaterFnRichPostBackground(
		Templater $templater,
		bool &$escape,
		Post $post,
		bool $transparent = false
	): string
	{
		if (!$post->post_id
			|| !$post->User
		)
		{
			return '';
		}

		/** @var XF\Entity\User $user */
		$user = $post->User;

		$styleProps = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)->filterActivePurchasesForUser($user);
		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('post_background_markup', [$post, &$styleProps, $transparent], $post->post_id);
		}

		if ($styleProps)
		{
			// Ensure we only add the span if needed
			return implode('; ', $styleProps);
		}

		return '';
	}

	/**
	 * @param Templater $templater
	 * @param bool $escape
	 * @param Post $post
	 * @param bool $transparent
	 *
	 * @return string
	 */
	public static function templaterFnRichPostbit(
		Templater $templater,
		bool &$escape,
		Post $post,
		bool $transparent = false
	): string
	{
		if (!$post->post_id
			|| !$post->User
		)
		{
			return '';
		}

		/** @var XF\Entity\User $user */
		$user = $post->User;

		$styleProps = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)->filterActivePurchasesForUser($user);
		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('post_bit_markup', [$post, &$styleProps, $transparent], $post->post_id);
		}

		if ($styleProps)
		{
			// Ensure we only add the span if needed
			return implode('; ', $styleProps);
		}

		return '';
	}

	/**
	 * @param Templater $templater
	 * @param string $value
	 * @param bool $escape
	 * @param Thread $thread
	 *
	 * @return string
	 */
	public static function templaterFilterRichThreadTitle(
		Templater $templater,
		string $value,
		bool &$escape,
		Thread $thread
	): string
	{
		if (!$thread->thread_id
			|| !$thread->User
		)
		{
			return $value;
		}

		/** @var XF\Entity\User $user */
		$user = $thread->User;

		$styleProps = [];

		/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Purchase> $purchases */
		$purchases = \XF::app()->repository(PurchaseRepository::class)->filterActivePurchasesForUser($user);
		foreach ($purchases AS $purchase)
		{
			$handler = $purchase->handler;
			$handler->fire('thread_title_markup', [$thread, &$styleProps], $thread->thread_id);
		}

		if ($styleProps)
		{
			$escape = false;

			// Ensure we only add the span if needed
			return '<span style="' . implode('; ', $styleProps) . '">' . \XF::escapeString($value) . '</span>';
		}

		return $value;
	}
}