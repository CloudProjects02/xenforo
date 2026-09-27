<?php

namespace DBTech\Shop\Behavior;

use XF\Mvc\Entity\Behavior;

class Cacheable extends Behavior
{
	public function postSave(): void
	{
		$this->rebuildCache();
	}

	public function postDelete(): void
	{
		$this->rebuildCache();
	}

	public function rebuildCache(): void
	{
		\XF::app()->repository($this->entity->structure()->shortName)->rebuildCache();
	}
}