<?php

namespace XFRM;

use XF\App;
use XF\Container;
use XF\Entity\User;
use XF\Import\Manager;
use XF\Service\User\ContentChange;
use XF\Service\User\DeleteCleanUp;
use XF\Service\User\Merge;
use XF\SubContainer\Import;
use XF\Template\Templater;
use XFRM\Install\Data\MySql;
use XFRM\Template\TemplaterSetup;

use function in_array, is_array;

class Listener
{
	public static function appSetup(App $app)
	{
		$container = $app->container();

		$container['prefixes.resource'] = $app->fromRegistry(
			'xfrmPrefixes',
			function (Container $c) { return $c['em']->getRepository('XFRM:ResourcePrefix')->rebuildPrefixCache(); }
		);

		$container['customFields.resources'] = $app->fromRegistry(
			'xfrmResourceFields',
			function (Container $c) { return $c['em']->getRepository('XFRM:ResourceField')->rebuildFieldCache(); },
			function (array $fields) use ($app)
			{
				$class = 'XF\CustomField\DefinitionSet';
				$class = $app->extendClass($class);

				$definitionSet = new $class($fields);
				$definitionSet->addFilter('resource', function (array $field)
				{
					return !empty($field['viewable_resource']);
				});

				return $definitionSet;
			}
		);

		$container['customFields.resourceReviews'] = $app->fromRegistry(
			'xfrmResourceReviewFields',
			function (Container $c) { return $c['em']->getRepository('XFRM:ResourceReviewField')->rebuildFieldCache(); },
			function (array $fields) use ($app)
			{
				$class = 'XF\CustomField\DefinitionSet';
				$class = $app->extendClass($class);

				return new $class($fields);
			}
		);

		$container['xfrmIconSizeMap'] = function (Container $c)
		{
			return $c['avatarSizeMap'];
		};
	}

	public static function criteriaUser($rule, array $data, User $user, &$returnValue)
	{
		switch ($rule)
		{
			case 'resource_count':
				if (isset($user->xfrm_resource_count) && $user->xfrm_resource_count >= $data['resources'])
				{
					$returnValue = true;
				}
				break;
		}
	}

	public static function criteriaPage($rule, array $data, User $user, array $params, &$returnValue)
	{
		if ($rule === 'xfrm_categories')
		{
			$returnValue = false;

			if (!empty($data['resource_category_ids']))
			{
				$selectedCategoryIds = $data['resource_category_ids'];

				if (isset($params['breadcrumbs']) && is_array($params['breadcrumbs']) && empty($data['category_only']))
				{
					foreach ($params['breadcrumbs'] AS $i => $navItem)
					{
						if (
							isset($navItem['attributes']['resource_category_id'])
							&& in_array($navItem['attributes']['resource_category_id'], $selectedCategoryIds)
						)
						{
							$returnValue = true;
						}
					}
				}

				if (!empty($params['containerKey']))
				{
					[$type, $id] = explode('-', $params['containerKey'], 2);

					if ($type == 'xfrmCategory' && $id && in_array($id, $selectedCategoryIds))
					{
						$returnValue = true;
					}
				}
			}
		}
	}

	public static function criteriaTemplateData(array &$templateData)
	{
		$categoryRepo = \XF::repository('XFRM:Category');
		$templateData['xfrmCategories'] = $categoryRepo->getCategoryOptionsData(false);
	}

	public static function templaterSetup(Container $container, Templater &$templater)
	{
		/** @var TemplaterSetup $templaterSetup */
		$class = \XF::extendClass('XFRM\Template\TemplaterSetup');
		$templaterSetup = new $class();

		$templater->addFunction('resource_icon', [$templaterSetup, 'fnResourceIcon']);
	}

	public static function userContentChangeInit(ContentChange $changeService, array &$updates)
	{
		$updates['xf_rm_category_watch'] = ['user_id', 'emptyable' => false];
		$updates['xf_rm_resource'] = ['user_id', 'username'];
		$updates['xf_rm_resource_download'] = ['user_id', 'emptyable' => false];
		$updates['xf_rm_resource_rating'] = [
			['user_id', 'emptyable' => false],
			['author_response_team_user_id', 'author_response_team_username'],
		];
		$updates['xf_rm_resource_team_member'] = ['user_id', 'emptyable' => false];
		$updates['xf_rm_resource_update'] = ['team_user_id', 'team_username'];
		$updates['xf_rm_resource_version'] = ['team_user_id', 'team_username'];
		$updates['xf_rm_resource_watch'] = ['user_id', 'emptyable' => false];
	}

	public static function userDeleteCleanInit(DeleteCleanUp $deleteService, array &$deletes)
	{
		$deletes['xf_rm_category_watch'] = 'user_id = ?';
		$deletes['xf_rm_resource_download'] = 'user_id = ?';
		$deletes['xf_rm_resource_team_member'] = 'user_id = ?';
		$deletes['xf_rm_resource_watch'] = 'user_id = ?';
	}

	public static function userMergeCombine(
		User $target,
		User $source,
		Merge $mergeService
	)
	{
		$target->xfrm_resource_count += $source->xfrm_resource_count;
	}

	public static function userSearcherOrders(\XF\Searcher\User $userSearcher, array &$sortOrders)
	{
		$sortOrders['xfrm_resource_count'] = \XF::phrase('xfrm_resource_count');
	}

	public static function memberStatResultPrepare($order, array &$cacheResults)
	{
		if ($order == 'xfrm_resource_count')
		{
			$cacheResults = array_map(function ($value)
			{
				return \XF::language()->numberFormat($value);
			}, $cacheResults);
		}
	}

	public static function importImporterClasses(Import $container, Container $parentContainer, array &$importers)
	{
		$importers = array_merge(
			$importers,
			Manager::getImporterShortNamesForType('XFRM')
		);
	}

	public static function getInstallData(array &$tableData)
	{
		$tableData['XFRM'] = new MySql();
	}
}
