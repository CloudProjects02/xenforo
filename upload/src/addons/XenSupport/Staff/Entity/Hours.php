<?php

namespace XenSupport\Staff\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * @property int    $hour_id
 * @property int    $user_id
 * @property int    $day_of_week    0=Sun ... 6=Sat
 * @property int    $start_time     minutes from midnight
 * @property int    $end_time
 * @property string $note
 *
 * @property string $start_label
 * @property string $end_label
 * @property string $day_label
 */
class Hours extends Entity
{
	public static function getStructure(Structure $structure)
	{
		$structure->table = 'xf_xs_staff_hours';
		$structure->shortName = 'XenSupport\Staff:Hours';
		$structure->primaryKey = 'hour_id';
		$structure->columns = [
			'hour_id'     => ['type' => self::UINT, 'autoIncrement' => true],
			'user_id'     => ['type' => self::UINT, 'required' => true],
			'day_of_week' => ['type' => self::UINT, 'required' => true],
			'start_time'  => ['type' => self::UINT, 'required' => true],
			'end_time'    => ['type' => self::UINT, 'required' => true],
			'note'        => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
		];
		$structure->getters = [
			'start_label' => true,
			'end_label' => true,
			'day_label' => true,
		];
		return $structure;
	}

	public function getStartLabel(): string
	{
		return sprintf('%02d:%02d', intdiv((int) $this->start_time, 60), $this->start_time % 60);
	}

	public function getEndLabel(): string
	{
		return sprintf('%02d:%02d', intdiv((int) $this->end_time, 60), $this->end_time % 60);
	}

	public function getDayLabel(): string
	{
		$days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
		return $days[$this->day_of_week] ?? '';
	}
}
