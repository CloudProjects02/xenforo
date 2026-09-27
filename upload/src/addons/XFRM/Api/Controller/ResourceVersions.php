<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Util\Arr;
use XFRM\Entity\ResourceItem;
use XFRM\Service\ResourceItem\CreateVersionUpdate;

/**
 * @api-group Resource versions
 */
class ResourceVersions extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource');
	}

	/**
	 * @api-desc Creates a new version of a resource
	 *
	 * @api-in <req> int $resource_id
	 * @api-in str $version_string
	 * @api-in str $version_type Type of version download: local or external. Defaults based on current resource type.
	 * @api-in str $version_attachment_key API attachment key for local download files. Required if version_type is local.
	 * @api-in str $external_download_url Required if version_type is external
	 * @api-in num $price For externally purchasable resources
	 * @api-in str $currency For externally purchasable resources
	 * @api-in str $external_purchase_url For externally purchasable resources
	 *
	 * @api-out true $success
	 * @api-out XFRM_ResourceVersion $version
	 */
	public function actionPost()
	{
		$this->assertRequiredApiInput(['resource_id']);

		$resourceId = $this->filter('resource_id', 'uint');

		/** @var ResourceItem $resource */
		$resource = $this->assertViewableApiRecord('XFRM:ResourceItem', $resourceId);

		if (\XF::isApiCheckingPermissions() && !$resource->canReleaseUpdate($error))
		{
			return $this->noPermission($error);
		}

		if (!$resource->hasUpdatableVersionData())
		{
			return $this->noPermission();
		}

		$creator = $this->setupResourceVersion($resource);

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}

		$creator->save();
		$version = $creator->getVersionCreator()->getVersion();

		return $this->apiSuccess([
			'version' => $version->toApiResult(Entity::VERBOSITY_VERBOSE),
		]);
	}

	/**
	 * @param ResourceItem $resource
	 *
	 * @return CreateVersionUpdate
	 */
	protected function setupResourceVersion(ResourceItem $resource)
	{
		/** @var CreateVersionUpdate $creator */
		$creator = $this->service('XFRM:ResourceItem\CreateVersionUpdate', $resource);
		$versionCreator = $creator->getVersionCreator();

		$input = $this->filter([
			'version_type' => 'str',
			'version_string' => 'str',
			'version_attachment_key' => 'str',
		]);

		$versionCreator->setVersionString($input['version_string'], true);

		if ($resource->isDownloadable())
		{
			$category = $resource->Category;

			switch ($input['version_type'])
			{
				case 'local':
				case 'external':
					break;

				default:
					$input['version_type'] = $resource->getResourceTypeDetailed() == 'download_local'
						? 'local'
						: 'external';
			}

			if ($input['version_type'] == 'local')
			{
				$this->assertRequiredApiInput('version_attachment_key');

				if ($category->allow_local || $resource->getResourceTypeDetailed() == 'download_local')
				{
					$hash = $this->getAttachmentTempHashFromKey(
						$input['version_attachment_key'],
						'resource_version',
						['resource_id' => $resource->resource_id]
					);
					$versionCreator->setAttachmentHash($hash);
				}
			}
			else if ($input['version_type'] == 'external')
			{
				$this->assertRequiredApiInput('external_download_url');

				if ($category->allow_external || $resource->getResourceTypeDetailed() == 'download_external')
				{
					$versionCreator->setDownloadUrl($this->filter('external_download_url', 'str'));
				}
			}
		}

		if ($resource->isExternalPurchasable())
		{
			$purchaseFields = $this->filter([
				'price' => '?num',
				'currency' => '?str',
				'external_purchase_url' => '?str',
			]);
			$purchaseFields = Arr::filterNull($purchaseFields);
			$creator->addResourceChanges($purchaseFields);
		}

		return $creator;
	}
}
