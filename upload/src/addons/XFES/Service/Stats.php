<?php

namespace XFES\Service;

use XF\App;
use XF\Service\AbstractService;
use XFES\Elasticsearch\Api;

class Stats extends AbstractService
{
	protected $es;

	public function __construct(App $app, Api $es)
	{
		parent::__construct($app);

		$this->es = $es;
	}

	public function getStats()
	{
		if ($this->es->indexExists())
		{
			return $this->es->getIndexStats();
		}
		else
		{
			return [];
		}
	}
}
