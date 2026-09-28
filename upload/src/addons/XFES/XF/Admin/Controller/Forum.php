<?php

namespace XFES\XF\Admin\Controller;

use XF\Entity\AbstractNode;
use XF\Entity\Node;
use XF\Mvc\FormAction;

use function in_array;

class Forum extends XFCP_Forum
{
	protected function saveTypeData(
		FormAction $form,
		Node $node,
		AbstractNode $data
	)
	{
		parent::saveTypeData($form, $node, $data);

		$form->apply(function () use ($node)
		{
			$similarThreadsInclude = $this->filter(
				'similar_threads_include',
				'bool'
			);
			$similarThreadsExcludedForums = $this->options()->xfesSimilarThreadsExcludedForums;

			if (
				$similarThreadsInclude
				&& in_array($node->node_id, $similarThreadsExcludedForums)
			)
			{
				$similarThreadsExcludedForums = array_diff(
					$similarThreadsExcludedForums,
					[$node->node_id]
				);
			}
			else if (
				!$similarThreadsInclude
				&& !in_array($node->node_id, $similarThreadsExcludedForums)
			)
			{
				$similarThreadsExcludedForums[] = $node->node_id;
			}

			$optionRepo = $this->repository('XF:Option');
			$optionRepo->updateOption(
				'xfesSimilarThreadsExcludedForums',
				$similarThreadsExcludedForums
			);
		});
	}
}
