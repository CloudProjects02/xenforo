<?php

namespace XFRM\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\ControllerPlugin\AttachmentPlugin;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Exception;

/**
 * @api-group Resource versions
 */
class ResourceVersion extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('resource');
	}

	/**
	 * @api-desc Gets information about the specified resource version
	 *
	 * @api-out XFRM_ResourceVersion $version
	 */
	public function actionGet(ParameterBag $params)
	{
		$version = $this->assertViewableVersion($params->resource_version_id);

		$result = [
			'version' => $version->toApiResult(Entity::VERBOSITY_VERBOSE, ['with_resource' => true]),
		];
		return $this->apiResult($result);
	}

	/**
	 * @api-desc Downloads a file from the specified resource version. For external downloads, redirects to the external URL.
	 *
	 * @api-in int $file Attachment ID of the file to download. Required for local downloads.
	 */
	public function actionGetDownload(ParameterBag $params)
	{
		$version = $this->assertViewableVersion($params->resource_version_id);

		if (\XF::isApiCheckingPermissions() && !$version->canDownload($error))
		{
			return $this->error($error);
		}

		if ($version->download_url)
		{
			return $this->redirect($version->download_url);
		}

		$this->assertRequiredApiInput('file');

		$fileId = $this->filter('file', 'uint');
		if (!$fileId || !isset($version->Attachments[$fileId]))
		{
			return $this->notFound();
		}

		$file = $version->Attachments[$fileId];

		/** @var AttachmentPlugin $attachPlugin */
		$attachPlugin = $this->plugin('XF:Attachment');

		return $attachPlugin->displayAttachment($file);
	}

	/**
	 * @api-desc Deletes the specified resource version. Defaults to soft deletion.
	 *
	 * @api-in bool $hard_delete
	 * @api-in str $reason
	 *
	 * @api-out true $success
	 */
	public function actionDelete(ParameterBag $params)
	{
		$version = $this->assertViewableVersion($params->resource_version_id);

		if (\XF::isApiCheckingPermissions() && !$version->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('resource:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$version->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$version->delete();
		}
		else
		{
			$reason = $this->filter('reason', 'str');
			$version->softDelete($reason);
		}

		return $this->apiSuccess();
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return \XFRM\Entity\ResourceVersion
	 *
	 * @throws Exception
	 */
	protected function assertViewableVersion($id, $with = 'api')
	{
		return $this->assertViewableApiRecord('XFRM:ResourceVersion', $id, $with);
	}
}
