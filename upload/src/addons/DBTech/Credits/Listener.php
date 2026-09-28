<?php

namespace DBTech\Credits;

use DBTech\Credits\Entity\Currency;
use XF\Container;
use XF\Db\Exception;
use XF\Entity\User;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Renderer\AbstractRenderer;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\App;
use XF\Template\Templater;

class Listener
{
	/**
	 * The product ID (in the DBTech store)
	 */
	protected static int $_productId = 339;


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
		$keys[] = 'dbtCreditsCurrencies';
	}

	/**
	 * @param \XF\App $app
	 *
	 * @throws Exception
	 */
	public static function appSetup(\XF\App $app): void
	{
		$container = $app->container();

		$container['dbtechCredits.currencies'] = $app->fromRegistry(
			'dbtCreditsCurrencies',
			function (Container $c) { return $c['em']->getRepository(Repository\CurrencyRepository::class)->rebuildCache(); },
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
		if (!empty($arguments['group']) && $arguments['group']->group_id == 'dbtech_credits')
		{
			// Override template name
			$template = 'dbtech_credits_option_macros';

			// Or use 'option_form_block_tabs' for tabs
			$name = 'option_form_block';

			// Your block header configurations
			$arguments['headers'] = [
				'generalOptions'      => [
					'label'           => \XF::phrase('general_options'),
					'minDisplayOrder' => 0,
					'maxDisplayOrder' => 2000,
					'active'          => true,
				],
				'eventOptions'        => [
					'label'           => \XF::phrase('dbtech_credits_event_options'),
					'minDisplayOrder' => 2000,
					'maxDisplayOrder' => 3000,
				],
				'eventTriggerOptions' => [
					'label'           => \XF::phrase('dbtech_credits_event_trigger_options'),
					'minDisplayOrder' => 3000,
					'maxDisplayOrder' => -1,
				],
			];
		}
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
		if (
			\XF::options()->dbtech_credits_navbar['enabled'] == 3
			&& isset($params['pageSection'])
			&& $params['pageSection'] == 'dbtechCredits'
		)
		{
			// Override this and reset the nav
			$params['pageSection'] = 'dbtechShop';

			// note that this intentionally only selects a top level entry
			$selectedNavEntry = $params['navTree'][$params['pageSection']] ?? null
			;

			$params['selectedNavEntry'] = $selectedNavEntry;
			$params['selectedNavChildren'] = !empty($selectedNavEntry['children'])
				? $selectedNavEntry['children']
				: []
			;
		}
	}

	/**
	 * @param string $rule
	 * @param array $data
	 * @param User $user
	 * @param bool $returnValue
	 */
	public static function criteriaUser(string $rule, array $data, User $user, bool &$returnValue): void
	{
		$container = \XF::app()->container();
		if (isset($container['dbtechCredits.currencies']) && $currencies = $container['dbtechCredits.currencies'])
		{
			$currencies = $currencies->filter(function (Currency $currency): ?Currency
			{
				if (!$currency->isActive())
				{
					return null;
				}

				return $currency;
			});

			foreach ($currencies AS $currencyId => $currency)
			{
				if ($rule == 'dbtech_credits_currency_' . $currencyId . '_less')
				{
					if ($user->{$currency['column']} < $data['amount'])
					{
						$returnValue = true;
						break;
					}
				}
				else if ($rule == 'dbtech_credits_currency_' . $currencyId . '_more')
				{
					if ($user->{$currency['column']} > $data['amount'])
					{
						$returnValue = true;
						break;
					}
				}
			}
		}
	}

	/**
	 * @param App $app
	 * @param array $navigationFlat
	 * @param array $navigationTree
	 */
	public static function navigationSetup(App $app, array &$navigationFlat, array &$navigationTree): void
	{
		

		if (!isset($navigationFlat['dbtechCredits']) or !isset($navigationTree['dbtechCredits']))
		{
			return;
		}

		/** @var XF\Entity\User $visitor */
		$visitor = \XF::visitor();

		$container = $app->container();
		if (isset($container['dbtechCredits.currencies']) && $currencies = $container['dbtechCredits.currencies'])
		{
			/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Credits\Entity\Currency> $currencies */
			$currencies = $currencies->filterViewable();

			$children = [];

			foreach ($currencies AS $currency)
			{
				// Set the child element
				$children['dbtechCreditsCurrency' . $currency->currency_id] = [
					'title' => \XF::phrase('dbtech_credits_footer_currency_phrase', [
						'currency' => $currency->title,
						'prefix' => $currency->prefix,
						'amount' => $currency->getValueFromUser(),
						'suffix' => $currency->suffix,
					]),
					'href' => $app->router('public')->buildLink('dbtech-credits/currency', $currency),
					'attributes' => [
						'rel' => 'nofollow',
						'data-xf-click' => 'overlay',
						'class' => 'menu-footer',
					],
				];

				if ($visitor->user_id && $currency->is_display_currency)
				{
					$navigationFlat['dbtechCredits']['title'] = \XF::phrase('dbtech_credits_display_currency_phrase', [
						'currency' => $currency->title,
						'prefix' => $currency->prefix,
						'amount' => $currency->getValueFromUser(),
						'suffix' => $currency->suffix,
					]);
				}
			}

			// Add the child elements
			$navigationFlat['dbtechCredits']['children'] = array_merge($navigationFlat['dbtechCredits']['children'], $children);

			$addOns = $container['addon.cache'];

			if (
				array_key_exists('DBTech/Shop', $addOns)
				&& \XF::options()->dbtech_credits_navbar['enabled'] == 3
				&& $visitor->hasPermission('dbtech_shop', 'view')
				&& !empty($navigationFlat['dbtechShop'])
				&& !empty($navigationFlat['dbtechShop']['children'])
			)
			{
				// Add the child elements
				$navigationFlat['dbtechShop']['children'] = array_merge($navigationFlat['dbtechShop']['children'], $navigationFlat['dbtechCredits']['children']);
			}
		}
	}

	/**
	 * @param array $data
	 * @param Controller $controller
	 */
	public static function editorDialog(array &$data, Controller $controller): void
	{
		$data['template'] = 'dbtech_credits_editor_dialog_charge';
		$data['params']['currency'] = \XF::app()->repository(Repository\CurrencyRepository::class)->getChargeCurrency();
	}
}