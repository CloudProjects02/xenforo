<?php

namespace XFRM\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XFRM\Entity\ResourceItem;

/**
 * @method AbstractCollection<\XFRM\Entity\ResourceUpdate> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFRM\Entity\ResourceUpdate> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFRM\Entity\ResourceUpdate|null fetchOne(?int $offset = null)
 * @extends Finder<\XFRM\Entity\ResourceUpdate>
 */
class ResourceUpdate extends Finder
{
	public function inResource(ResourceItem $resource, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
		], $limits);

		$this->where('resource_id', $resource->resource_id)
			->where('resource_update_id', '<>', $resource->description_update_id);

		if ($limits['visibility'])
		{
			$this->applyVisibilityChecksInResource($resource);
		}

		return $this;
	}

	public function applyVisibilityChecksInResource(ResourceItem $resource)
	{
		$conditions = [];
		$viewableStates = ['visible'];

		if ($resource->canViewDeletedContent())
		{
			$viewableStates[] = 'deleted';

			$this->with('DeletionLog');
		}

		if ($resource->canViewModeratedContent())
		{
			$viewableStates[] = 'moderated';
		}

		$conditions[] = ['message_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}
}
