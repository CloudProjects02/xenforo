<?php

namespace DBTech\Shop\Finder;

use XF\Mvc\Entity\Finder;

/**
 * Class Lottery
 * @package DBTech\Shop\Finder
 */
class Lottery extends Finder
{
	/**
	 * @return $this
	 */
	public function isRecurring(): Lottery
	{
		$this->where($this->expression(
			'%s <> %s',
			'next_draw_date',
			'previous_draw_date'
		));
		
		return $this;
	}
}