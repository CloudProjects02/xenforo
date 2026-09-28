<?php

namespace XFES\XF\Entity;

use function in_array;

class Forum extends XFCP_Forum
{
	public function isSimilarThreadSuggestionsEnabled(): bool
	{
		$options = $this->app()->options();
		if (!$options->xfesEnabled)
		{
			return false;
		}

		if (!$options->xfesSimilarThreads['suggestionsEnabled'])
		{
			return false;
		}

		return !in_array(
			$this->node_id,
			$options->xfesSimilarThreadsExcludedForums
		);
	}
}
