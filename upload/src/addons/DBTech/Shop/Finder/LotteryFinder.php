<?php

/** @noinspection PhpFullyQualifiedNameUsageInspection */

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\DBTech\Shop\Entity\Lottery> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\DBTech\Shop\Entity\Lottery> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \DBTech\Shop\Entity\Lottery|null fetchOne(?int $offset = null)
 * @extends Finder<\DBTech\Shop\Entity\Lottery>
 */
class LotteryFinder extends Finder
{
	/**
	 * @return $this
	 */
	public function isRecurring(): LotteryFinder
	{
		$this->where($this->expression(
			'%s <> %s',
			'next_draw_date',
			'previous_draw_date'
		));

		return $this;
	}
}