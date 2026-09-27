<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;
use XFRM\Entity\Category;
use XFRM\Finder\ResourceItem;
use XFRM\Service\ResourceItem\Create;

/**
 * @api-group Resources
 */
class ResourceItems extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource');
	}

	/**
	 * @api-desc Gets a list of resources
	 *
	 * @api-in int $page
	 * @api-see XFRM\Api\ControllerPlugin\ResourceItem::applyResourceListFilters
	 * @api-see XFRM\Api\ControllerPlugin\ResourceItem::applyResourceListSort
	 *
	 * @api-out XFRM_ResourceItem[] $resources
	 * @api-out pagination $pagination
	 */
	public function actionGet()
	{
		$page = $this->filterPage();
		$perPage = $this->options()->xfrmResourcesPerPage;

		$finder = $this->setupResourceFinder()->limitByPage($page, $perPage);
		$total = $finder->total();

		$this->assertValidApiPage($page, $perPage, $total);

		$resources = $finder->fetch();

		if (\XF::isApiCheckingPermissions())
		{
			$resources = $resources->filterViewable();
		}

		return $this->apiResult([
			'resources' => $resources->toApiResults(),
			'pagination' => $this->getPaginationData($resources, $page, $perPage, $total),
		]);
	}

	/**
	 * @param array $filters List of filters that have been applied from input
	 * @param array|null $sort If array, sort that has been applied from input
	 *
	 * @return ResourceItem
	 */
	protected function setupResourceFinder(&$filters = [], &$sort = null)
	{
		$repo = $this->repository('XFRM:ResourceItem');
		$finder = $repo->findResourcesForApi();

		/** @var \XFRM\Api\ControllerPlugin\ResourceItem $plugin */
		$plugin = $this->plugin('XFRM:Api:ResourceItem');

		$filters = $plugin->applyResourceListFilters($finder);
		$sort = $plugin->applyResourceListSort($finder);

		return $finder;
	}

	/**
	 * @api-desc Creates a resource
	 *
	 * @api-in <req> int $resource_category_id
	 * @api-in <req> str $title
	 * @api-in <req> str $tag_line
	 * @api-in <req> str $description
	 * @api-in <req> str $resource_type Type of resource: download_local, download_external, external_purchase, fileless
	 * @api-in int $prefix_id
	 * @api-in str $version_string
	 * @api-in string $custom_fields[<name>]
	 * @api-in str[] $tags
	 * @api-in str $external_url
	 * @api-in str $alt_support_url
	 * @api-in str $description_attachment_key API attachment key for description images
	 * @api-in str $version_attachment_key API attachment key for version file. Required for download_local type.
	 * @api-in str $external_download_url External download URL. Required for download_external type.
	 * @api-in num $price Price. Required for external_purchase type.
	 * @api-in str $currency Currency code. Required for external_purchase type.
	 * @api-in str $external_purchase_url Purchase URL. Required for external_purchase type.
	 *
	 * @api-out true $success
	 * @api-out XFRM_ResourceItem $resource
	 */
	public function actionPost()
	{
		$this->assertRequiredApiInput(['resource_category_id', 'title', 'tag_line', 'description', 'resource_type']);

		$categoryId = $this->filter('resource_category_id', 'uint');

		/** @var Category $category */
		$category = $this->assertViewableApiRecord('XFRM:Category', $categoryId);

		if (\XF::isApiCheckingPermissions() && !$category->canAddResource($error))
		{
			return $this->noPermission($error);
		}

		$creator = $this->setupResourceCreate($category);

		if (\XF::isApiCheckingPermissions())
		{
			$creator->checkForSpam();
		}

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		/** @var \XFRM\Entity\ResourceItem $resource */
		$resource = $creator->save();
		$this->finalizeResourceCreate($creator);

		return $this->apiSuccess([
			'resource' => $resource->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @param Category $category
	 *
	 * @return Create
	 *
	 * @throws Exception
	 */
	protected function setupResourceCreate(Category $category)
	{
		$input = $this->filter([
			'title' => 'str',
			'description' => 'str',
			'prefix_id' => 'uint',
			'version_string' => 'str',
			'resource_type' => 'str',
			'custom_fields' => 'array',
			'tags' => 'array-str',
			'description_attachment_key' => 'str',
			'version_attachment_key' => 'str',
		]);

		$bulkInput = $this->filter([
			'tag_line' => 'str',
			'external_url' => 'str',
			'alt_support_url' => 'str',
		]);

		$isBypassingPermissions = \XF::isApiBypassingPermissions();

		/** @var Create $creator */
		$creator = $this->service('XFRM:ResourceItem\Create', $category);

		$creator->setContent($input['title'], $input['description']);
		$creator->setVersionString($input['version_string'], true);
		$creator->setCustomFields($input['custom_fields']);
		$creator->getResource()->bulkSet($bulkInput);

		if ($input['prefix_id'] && ($isBypassingPermissions || $category->isPrefixUsable($input['prefix_id'])))
		{
			$creator->setPrefix($input['prefix_id']);
		}

		if ($category->canEditTags())
		{
			$creator->setTags($input['tags']);
		}

		if ($isBypassingPermissions || $category->canUploadAndManageUpdateImages())
		{
			$hash = $this->getAttachmentTempHashFromKey(
				$input['description_attachment_key'],
				'resource_update',
				['resource_category_id' => $category->resource_category_id]
			);

			$creator->setDescriptionAttachmentHash($hash);
		}

		switch ($input['resource_type'])
		{
			case 'download_local':
				if ($category->allow_local)
				{
					$this->assertRequiredApiInput('version_attachment_key');

					$hash = $this->getAttachmentTempHashFromKey(
						$input['version_attachment_key'],
						'resource_version',
						['resource_category_id' => $category->resource_category_id]
					);

					$creator->setLocalDownload($hash);
				}
				break;

			case 'download_external':
				if ($category->allow_external)
				{
					$this->assertRequiredApiInput('external_download_url');

					$creator->setExternalDownload($this->filter('external_download_url', 'str'));
				}
				break;

			case 'external_purchase':
				if ($category->allow_commercial_external)
				{
					$this->assertRequiredApiInput(['price', 'currency', 'external_purchase_url']);

					$purchaseInput = $this->filter([
						'price' => 'num',
						'currency' => 'str',
						'external_purchase_url' => 'str',
					]);

					$creator->setExternalPurchasable(
						$purchaseInput['price'],
						$purchaseInput['currency'],
						$purchaseInput['external_purchase_url']
					);
				}
				break;

			case 'fileless':
				if ($category->allow_fileless)
				{
					$creator->setFileless();
				}
				break;
		}

		return $creator;
	}

	protected function finalizeResourceCreate(Create $creator)
	{
		$creator->sendNotifications();
	}
}
