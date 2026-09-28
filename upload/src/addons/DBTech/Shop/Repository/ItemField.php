<?php

namespace DBTech\Shop\Repository;

use XF\Repository\AbstractField;

/**
 * Class ItemField
 * @package DBTech\Shop\Repository
 */
class ItemField extends AbstractField
{
	/**
	 * @return string
	 */
	protected function getRegistryKey(): string
	{
		return 'dbtShopItemFieldsInfo';
	}

	/**
	 * @return string
	 */
	protected function getClassIdentifier(): string
	{
		return 'DBTech\Shop:ItemField';
	}

	/**
	 * @return array
	 */
	public function getDisplayGroups(): array
	{
		return [
			'above_main' => \XF::phrase('dbtech_shop_above_main_info'),
			'above_info' => \XF::phrase('dbtech_shop_above_item_info'),
			'below_info' => \XF::phrase('dbtech_shop_below_item_info'),
			'new_tab' => \XF::phrase('dbtech_shop_own_tab')
		];
	}

	/**
	 * @param int $itemId
	 * @return array
	 */
	public function getItemFieldValues(int $itemId): array
	{
		$fields = $this->db()->fetchAll('
			SELECT field_value.*, field.field_type
			FROM xf_dbtech_shop_item_field_value AS field_value
			INNER JOIN xf_dbtech_shop_item_field AS field ON (field.field_id = field_value.field_id)
			WHERE field_value.item_id = ?
		', $itemId);

		$values = [];
		foreach ($fields AS $field)
		{
			if ($field['field_type'] == 'checkbox' || $field['field_type'] == 'multiselect')
			{
				$values[$field['field_id']] = \XF\Util\Php::safeUnserialize($field['field_value']);
			}
			else
			{
				$values[$field['field_id']] = $field['field_value'];
			}
		}
		return $values;
	}
	
	/**
	 * @param int $itemId
	 */
	public function rebuildItemFieldValuesCache(int $itemId)
	{
		$cache = $this->getItemFieldValues($itemId);
		
		$this->db()->update(
			'xf_dbtech_shop_item',
			['item_fields' => json_encode($cache)],
			'item_id = ?',
			$itemId
		);
	}
}