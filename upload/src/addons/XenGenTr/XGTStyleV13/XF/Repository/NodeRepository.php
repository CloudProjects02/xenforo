<?php

namespace XenGenTr\XGTStyleV13\XF\Repository;

class NodeRepository extends XFCP_NodeRepository
{
	public function getNodeListExtras(\XF\Tree $nodeTree)
	{
		$extras = parent::getNodeListExtras($nodeTree);

		foreach ($nodeTree->getFlattened() as $nodeId => $entry)
		{
			$node = $entry['record'];
			$extras[$nodeId]['xgt_grid_etkin'] = $node->xgt_mega_grid_etkin;

			if ($node->node_type_id === 'Forum')
			{
				$extras[$nodeId]['recent_threads'] = $this->getRecentRepliedThreadsForNode($nodeTree, $node);
			}
			else
			{
				$extras[$nodeId]['recent_threads'] = [];
			}
		}

		return $extras;
	}

	public function getRecentRepliedThreadsForNode(\XF\Tree $nodeTree, \XF\Entity\Node $node)
	{
		$nodeIds = [$node->node_id];
		$children = $nodeTree->getDescendants($node->node_id);

		foreach ($children as $child)
		{
			$nodeIds[] = $child->node_id;
		}

		return $this->finder('XF:Thread')
			->where('node_id', $nodeIds)
			->where('discussion_state', 'visible')
			->order('last_post_date', 'DESC')
			->with(['LastPoster'])
			->limit(5)
			->fetch()
			->toArray();
	}
}
