<?php

namespace XFRM\Api\ControllerPlugin;

use XF\Api\ControllerPlugin\AbstractPlugin;
use XF\Util\Arr;

class Category extends AbstractPlugin
{
	/**
	 * @api-in str $title
	 * @api-in str $description
	 * @api-in int $parent_category_id
	 * @api-in int $display_order
	 * @api-in bool $allow_local
	 * @api-in bool $allow_external
	 * @api-in bool $allow_commercial_external
	 * @api-in bool $allow_fileless
	 * @api-in bool $enable_versioning
	 * @api-in bool $enable_support_url
	 * @api-in bool $always_moderate_create
	 * @api-in bool $always_moderate_update
	 * @api-in int $min_tags
	 * @api-in int $thread_node_id
	 * @api-in int $thread_prefix_id
	 * @api-in bool $require_prefix
	 * @api-in bool $auto_feature
	 */
	public function setupCategorySave(\XFRM\Entity\Category $category)
	{
		$entityInput = $this->filter([
			'title' => '?str',
			'description' => '?str',
			'parent_category_id' => '?uint',
			'display_order' => '?uint',
			'allow_local' => '?bool',
			'allow_external' => '?bool',
			'allow_commercial_external' => '?bool',
			'allow_fileless' => '?bool',
			'enable_versioning' => '?bool',
			'enable_support_url' => '?bool',
			'always_moderate_create' => '?bool',
			'always_moderate_update' => '?bool',
			'min_tags' => '?uint',
			'thread_node_id' => '?uint',
			'thread_prefix_id' => '?uint',
			'require_prefix' => '?bool',
			'auto_feature' => '?bool',
		]);
		$entityInput = Arr::filterNull($entityInput);

		$form = $this->formAction();
		$form->basicEntitySave($category, $entityInput);

		return $form;
	}
}
