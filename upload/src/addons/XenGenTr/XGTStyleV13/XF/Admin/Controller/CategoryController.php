<?php

namespace XenGenTr\XGTStyleV13\XF\Admin\Controller;

class CategoryController extends XFCP_CategoryController
{
	protected function nodeSaveProcess(\XF\Entity\Node $node)
	{
		$input = $this->filter([
			'node' => [
				'xgt_grid_etkin' => 'bool',
			],
		]);

		$form = parent::nodeSaveProcess($node);

		$form->setup(function () use ($node, $input)
		{
			$node->xgt_grid_etkin = $input['node']['xgt_grid_etkin'] ?? 0;
		});

		return $form;
	}
}
