<?php

namespace XFRM\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;
use XFRM\Entity\ResourceItem;

/**
 * @method AbstractCollection<\XFRM\Entity\ResourceRating> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFRM\Entity\ResourceRating> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFRM\Entity\ResourceRating|null fetchOne(?int $offset = null)
 * @extends Finder<\XFRM\Entity\ResourceRating>
 */
class ResourceRating extends Finder
{
	public function inResource(ResourceItem $resource, array $limits = [])
	{
		$limits = array_replace([
			'visibility' => true,
		], $limits);

		$this->where('resource_id', $resource->resource_id);

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

		$conditions[] = ['rating_state', $viewableStates];

		$this->whereOr($conditions);

		return $this;
	}

	/**
	 * @deprecated Use with('full') instead
	 *
	 * @return $this
	 */
	public function forFullView()
	{
		$this->with('full');

		return $this;
	}
}
