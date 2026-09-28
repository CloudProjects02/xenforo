<?php
namespace AddonsLab\Core\Service;
class ArrayHelper
{
    /**
     * @param array $data
     * @param array $defaultData
     * @return array
     */
	public function deepMerge(array $data, array $defaultData)
	{
		foreach ($defaultData AS $key => $value) {
			if (is_array($value)) {
				$subData = isset($data[$key]) ? $data[$key] : array();
				$data[$key] = $this->deepMerge($subData, $value);
			} else if (!isset($data[$key])) {
				$data[$key] = $value;
			}
		}

		return $data;
	}

    public function arrayColumn($array, $column, $index = null)
    {
        if (function_exists('array_column')) {
            return array_column($array, $column, $index);
        }

        $output = array();
        foreach ($array AS $row) {
            if ($column === null) {
                $value = $row;
            } else if (array_key_exists($column, $row)) {
                $value = $row[$column];
            } else {
                continue;
            }

            if ($index === null || !array_key_exists($index, $row)) {
                $output[] = $value;
            } else {
                $output[$row[$index]] = $value;
            }
        }

        return $output;
    }
}