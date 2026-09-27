<?php

namespace XFRM\EmbedResolver;

use XF\EmbedResolver\AbstractHandler;

class ResourceItem extends AbstractHandler
{
	public function getEntityWith(): array
	{
		$visitor = \XF::visitor();

		return ['Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}
}
