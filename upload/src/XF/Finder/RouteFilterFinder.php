<?php

namespace XF\Finder;

use XF\Entity\RouteFilter;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<RouteFilter> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<RouteFilter> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method RouteFilter|null fetchOne(?int $offset = null)
 * @extends Finder<RouteFilter>
 */
class RouteFilterFinder extends Finder
{
	public function orderLength($field, $direction = 'DESC')
	{
		$expression = $this->expression('LENGTH(%s)', $field);
		$this->order($expression, $direction);

		return $this;
	}
}
