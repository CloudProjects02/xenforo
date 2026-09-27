<?php

namespace XenGenTr\XGTStyleV13\XF\Admin\Controller;

class ForumController extends XFCP_ForumController
{
	protected function nodeSaveProcess(\XF\Entity\Node $node)
	{
		$input = $this->filter([
			'node' => [
				'xgt_forum_renk' => 'str',
			],
		]);

		$form = parent::nodeSaveProcess($node);

		$form->setup(function () use ($node, $input)
		{
			$node->xgt_forum_renk = $input['node']['xgt_forum_renk'] ?? '';
		});

		return $form;
	}
}
