<?php

namespace XenSupport\Staff\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property int    $badge_id
 * @property string $title
 * @property string $description
 * @property string $icon
 * @property string $image_url
 * @property string $color
 * @property int    $assigned_count
 * @property int    $display_order
 * @property int    $created_date
 */
class Badge extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_xs_staff_badge';
		$structure->shortName = 'XenSupport\Staff:Badge';
		$structure->primaryKey = 'badge_id';
		$structure->columns = [
			'badge_id'       => ['type' => self::UINT, 'autoIncrement' => true],
			'title'          => ['type' => self::STR, 'maxLength' => 100, 'required' => true],
			'description'    => ['type' => self::STR, 'maxLength' => 500, 'default' => ''],
			'icon'           => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
			'image_url'      => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
			'color'          => ['type' => self::STR, 'maxLength' => 20, 'default' => '#fbbf24'],
			'assigned_count' => ['type' => self::UINT, 'default' => 0],
			'display_order'  => ['type' => self::UINT, 'default' => 0],
			'created_date'   => ['type' => self::UINT, 'default' => 0],
		];
		return $structure;
	}

	protected function _preSave(): void
	{
		if (!$this->created_date) { $this->created_date = \XF::$time; }
	}
}
