<?php

namespace XFRM\Service\ResourceVersion;

use XF\App;
use XF\Service\AbstractService;
use XFRM\Entity\ResourceVersion;

class Preparer extends AbstractService
{
	/**
	 * @var ResourceVersion
	 */
	protected $version;

	protected $attachmentHash;

	public function __construct(App $app, ResourceVersion $version)
	{
		parent::__construct($app);

		$this->version = $version;
	}

	public function getVersion()
	{
		return $this->version;
	}

	public function setVersionString($versionString, $useDefault = false)
	{
		if ($versionString === '' && $useDefault)
		{
			$this->setDefaultVersionString();
		}
		else
		{
			$this->version->version_string = $versionString;
		}
	}

	public function setDefaultVersionString()
	{
		$this->version->version_string = date('Y-m-d', \XF::$time);
	}

	public function setAttachmentHash($hash)
	{
		$this->attachmentHash = $hash;
		$this->version->download_url = '';
	}

	public function setDownloadUrl($url)
	{
		$this->version->download_url = $url;
		if ($url)
		{
			$this->attachmentHash = null;
		}
	}

	public function validateFiles(&$error = null)
	{
		if ($this->attachmentHash)
		{
			$totalAttachments = $this->finder('XF:Attachment')->where('temp_hash', $this->attachmentHash)->total();
		}
		else
		{
			$totalAttachments = 0;
		}

		$resource = $this->version->Resource;
		if (!$resource)
		{
			throw new \LogicException("Could not find resource for version");
		}

		if ($resource->isDownloadable())
		{
			if (!$totalAttachments && !$this->version->download_url)
			{
				$error = \XF::phrase('xfrm_you_must_upload_file_or_provide_external_download_url');
				return false;
			}
		}

		return true;
	}

	public function afterInsert()
	{
		$this->associateAttachments();
	}

	protected function associateAttachments()
	{
		if (!$this->attachmentHash)
		{
			return 0;
		}

		$version = $this->version;

		/** @var \XF\Service\Attachment\Preparer $inserter */
		$inserter = $this->service('XF:Attachment\Preparer');

		$total = $inserter->associateAttachmentsWithContent(
			$this->attachmentHash,
			'resource_version',
			$version->resource_version_id
		);

		$version->fastUpdate('file_count', $total);

		return $total;
	}
}
