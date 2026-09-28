<?php

namespace XFRM\Job;

use XF\Job\AbstractEmbedMetadataJob;
use XF\Mvc\Entity\Entity;
use XF\Service\Message\PreparerService;

class ResourceUpdateEmbedMetadata extends AbstractEmbedMetadataJob
{
	protected function getIdsToRebuild(array $types)
	{
		$db = $this->app->db();

		return $db->fetchAllColumn(
			$db->limit(
				'SELECT resource_update_id
					FROM xf_rm_resource_update
					WHERE resource_update_id > ?
					ORDER BY resource_update_id',
				$this->data['batch']
			),
			$this->data['start']
		);
	}

	protected function getRecordToRebuild($id)
	{
		return $this->app->em()->find('XFRM:ResourceUpdate', $id);
	}

	protected function getPreparerContext()
	{
		return 'resource_update';
	}

	protected function getMessageContent(Entity $record)
	{
		return $record->message;
	}

	protected function rebuildQuotes(Entity $record, PreparerService $preparer, array &$embedMetadata): void
	{
		$embedMetadata['quotes'] = $preparer->getEmbeddedQuotes();
	}

	protected function rebuildAttachments(Entity $record, PreparerService $preparer, array &$embedMetadata)
	{
		$embedMetadata['attachments'] = $preparer->getEmbeddedAttachments();
	}

	protected function rebuildEmbeds(Entity $record, PreparerService $preparer, array &$embedMetadata): void
	{
		$embedMetadata['embeds'] = $preparer->getEmbeds();
	}

	protected function rebuildImages(Entity $record, PreparerService $preparer, array &$embedMetadata): void
	{
		$embedMetadata['images'] = $preparer->getEmbeddedImages();
	}

	protected function getActionDescription()
	{
		$rebuildPhrase = \XF::phrase('rebuilding');
		$type = \XF::phrase('xfrm_resources');
		return sprintf('%s... %s', $rebuildPhrase, $type);
	}
}
