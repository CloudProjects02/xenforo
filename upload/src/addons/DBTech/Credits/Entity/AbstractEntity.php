<?php

namespace DBTech\Credits\Entity;

use XF\Mvc\Entity\Entity;

abstract class AbstractEntity extends Entity
{
	public function getValue($key)
	{
		$value = parent::getValue($key);

		$columns = $this->_structure->columns;
		$column = $columns[$key];

		if (!empty($column['isDecimal']))
		{
			$value = $this->decodeDbtechValueFromSource(
				$column['type'],
				$value,
				$column
			);
		}

		return $value;
	}

	protected function decodeDbtechValueFromSource(int $type, mixed $value, array $columnOptions = [])
	{
		if ($value === null)
		{
			return null;
		}

		switch ($type)
		{
			case self::FLOAT:
				if (!empty($columnOptions['isDecimal']))
				{
					return floatval($value);
				}
				return $value;
			default:
				return $value;
		}
	}
}