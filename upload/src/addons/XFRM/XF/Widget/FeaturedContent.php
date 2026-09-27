<?php

namespace XFRM\XF\Widget;

use XFRM\Entity\Category;

class FeaturedContent extends XFCP_FeaturedContent
{
	protected function getContextualOptions(): array
	{
		$options = parent::getContextualOptions();

		$category = $this->contextParams['category'] ?? null;
		if ($category && $category instanceof Category)
		{
			return [
				'content_type' => 'resource',
				'content_container_id' => $category->resource_category_id,
			];
		}

		return $options;
	}
}
