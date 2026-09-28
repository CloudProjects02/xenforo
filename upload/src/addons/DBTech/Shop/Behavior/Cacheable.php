<?php

namespace DBTech\Shop\Behavior;

use XF\Mvc\Entity\Behavior;

class Cacheable extends Behavior
{
	public function postSave()
	{
		$this->rebuildCache();
	}

	public function postDelete()
	{
		$this->rebuildCache();
	}
	
	public function rebuildCache()
	{
		$this->repository($this->entity->structure()->shortName)->rebuildCache();
	}
}