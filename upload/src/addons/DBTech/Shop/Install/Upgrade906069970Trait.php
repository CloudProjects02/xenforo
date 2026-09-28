<?php

namespace DBTech\Shop\Install;

use mikehaertl\wkhtmlto\Command;

/**
 * @property \XF\AddOn\AddOn addOn
 * @property \XF\App app
 *
 * @method \XF\Db\AbstractAdapter db()
 * @method \XF\Db\SchemaManager schemaManager()
 * @method \XF\Db\Schema\Column addOrChangeColumn($table, $name, $type = null, $length = null)
 */
trait Upgrade906069970Trait
{
	/**
	 *
	 */
	public function upgrade906060070Step1()
	{
		$this->applyTables();
	}

	/**
	 * @param array $stepParams
	 *
	 * @return array|bool
	 * @throws \XF\Db\Exception
	 */
	public function upgrade906060070Step2(array $stepParams)
	{
		$position = empty($stepParams[0]) ? 0 : $stepParams[0];
		$perPage = 250;

		$db = $this->db();

		if (!isset($stepParams['max']))
		{
			$stepParams['max'] = $db->fetchOne("
				SELECT MAX(item_id)
				FROM xf_dbtech_shop_item
			");
		}

		$itemIds = $db->fetchAllColumn($db->limit(
			"
				SELECT DISTINCT item_id
				FROM xf_dbtech_shop_item
				WHERE item_id > ?
				ORDER BY item_id
			",
			$perPage
		), $position);
		if (!$itemIds)
		{
			return true;
		}

		$db->beginTransaction();

		$queryResults = $db->query('
			SELECT *
			FROM xf_dbtech_shop_item
			WHERE item_id IN (' . $db->quote($itemIds) . ')
			ORDER BY item_id
		');
		while ($result = $queryResults->fetch())
		{
			$flags = [
				'is_giftable'          => $result['is_giftable'],
				'is_only_giftable'     => $result['is_only_giftable'],
				'send_gift_pm'         => $result['send_gift_pm'],
				'can_regift'           => $result['can_regift'],
				'can_discard'          => true,
				'is_unique'            => $result['is_unique'],
				'is_exclusive'         => $result['is_exclusive'],
				'is_always_hidden'     => $result['is_always_hidden'],
				'can_reconfigure'      => $result['can_reconfigure'],
				'auto_discard'         => $result['auto_discard'],
				'enabled_custom_shops' => $result['enabled_custom_shops']
			];

			$db->update('xf_dbtech_shop_item', [
				'item_flags' => \json_encode($flags)
			], 'item_id = ?', [$result['item_id']]);
		}

		$db->commit();

		$next = end($itemIds);

		return [
			$next,
			"{$next} / {$stepParams['max']}",
			$stepParams
		];
	}
}