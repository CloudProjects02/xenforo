<?php
/** 
* @package [AL] Filter Framework
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 1.4.3
This software is furnished under a license and may be used and copied
only  in  accordance  with  the  terms  of such  license and with the
inclusion of the above copyright notice.  This software  or any other
copies thereof may not be provided or otherwise made available to any
other person.  No title to and  ownership of the  software is  hereby
transferred.                                                         
                                                                     
You may not reverse  engineer, decompile, defeat  license  encryption
mechanisms, or  disassemble this software product or software product
license.  AddonsLab may terminate this license if you don't comply with
any of these terms and conditions.  In such event,  licensee  agrees 
to return licensor  or destroy  all copies of software  upon termination 
of the license.
*/


namespace AL\FilterFramework\Service;

use AL\Core\Compat;
use AL\FilterFramework\FilterApp;
use XF\CustomField\Definition;
use XF\Service\AbstractService;

/**
 * Class TypeProvider.
 */
class TypeProvider extends AbstractService
{
    public static $choiceSeparator = '__0__';

    /**
     * @param array|\ArrayAccess $fieldInfo
     *
     * @return bool|string
     *                     Returns the column holding the data for the normalized field in xf_alff_field_index table, false if no field matches
     */
    public function getIndexTableColumnName($fieldInfo)
    {
        if ($this->isInteger($fieldInfo))
        {
            return 'field_int';
        }

        if ($this->isFloat($fieldInfo))
        {
            return 'field_float';
        }

        // all other types do not have direct column name in xf_alff_field_index table and search index table should be used for filtering
        return false;
    }

    public function getColor($fieldInfo, array $filterData)
    {
        $fieldId = $fieldInfo['field_id'];
        if (!isset($filterData[$fieldId]))
        {
            return false;
        }

        $labColor = FilterApp::getColorConverter()->rgbToLabCie($filterData[$fieldId]);

        if (!$labColor)
        {
            return false;
        }

        return $labColor;
    }

    /**
     * @param Definition|\AL\LocationField\XF\CustomField\Definition $fieldInfo
     *
     * @return mixed|null|null
     */
    public function getIndexableLocationData(Definition $fieldInfo, array $filterData)
    {
        $fieldId = $fieldInfo['field_id'];
        if (empty($filterData[$fieldId]) || !is_array($filterData[$fieldId]))
        {
            return null;
        }
    }

    public function getIndexTableValue($fieldInfo, array &$filterData)
    {
        $isInteger = $this->isInteger($fieldInfo);
        $isFloat = $this->isFloat($fieldInfo);
        if ($isInteger || $isFloat)
        {
            $value = $operator = false;
            $fieldId = $fieldInfo['field_id'];

            $isArray = is_array($filterData[$fieldId]);

            $hasFrom = $isArray && isset($filterData[$fieldId]['from']);
            $hasTo = $isArray && isset($filterData[$fieldId]['to']);

            $columnName = $isInteger ? 'field_int' : 'field_float';
            if ($hasFrom && $hasTo)
            {
                $operator = 'BETWEEN';
                $value = [$filterData[$fieldId]['from'], $filterData[$fieldId]['to']];
            }
            elseif ($hasFrom)
            {
                $operator = '>=';
                $value = $filterData[$fieldId]['from'];
            }
            elseif ($hasTo)
            {
                $operator = '<=';
                $value = $filterData[$fieldId]['to'];
            }
            elseif (
                $isArray === false
            ) {
                $operator = '=';
                $value = $filterData[$fieldId];
            }

            if ($value !== false)
            {
                // convert the array from input form to the target format
                $function = $isInteger ? 'getInt' : 'getFloat';
                if (is_array($value))
                {
                    $value = array_map(function ($intervalValue) use ($function, $fieldInfo) {
                        return $this->{$function}($fieldInfo, $intervalValue);
                    }, $value);
                }
                else
                {
                    $value = $this->{$function}($fieldInfo, $value);
                }

                return [$operator, $value];
            }
            // remove the field which does not have any valid format
            unset($filterData[$fieldId]);
        }

        return false;
    }

    /**
     * @param array|\ArrayAccess $fieldInfo
     * @param mixed              $fieldValue
     *
     * @return false|float|int
     *                         Converts the value submitted in a form to value that can be used stored/used for filtering based on field type
     */
    public function getParsedValue($fieldInfo, $fieldValue)
    {
        if ($this->isInteger($fieldInfo))
        {
            return $this->getInt($fieldInfo, $fieldValue);
        }

        if ($this->isFloat($fieldInfo))
        {
            return $this->getFloat($fieldInfo, $fieldValue);
        }

        // all other types do not have a value stored in xf_alff_field_index table (color type should be handled separately)
        return false;
    }

    /**
     * @param array|\ArrayAccess $fieldInfo
     * @param mixed              $fieldValue
     *
     * @return string
     *                Gets the text associated with the field to store in the index. Empty text for all fields that represent advanced field types
     */
    public function getText($fieldInfo, $fieldValue)
    {
        if ($this->isFreeText($fieldInfo) === false || !is_scalar($fieldValue))
        {
            return '';
        }

        return $fieldValue;
    }

    public function isStarField($field)
    {
        return $field['field_type'] === 'stars';
    }

    public function isDateField($field)
    {
        return $field['match_type'] === 'date';
    }

    public function getInt($field, $fieldValue)
    {
        if ($field['field_type'] === 'stars')
        {
            return $fieldValue;
        }

        if ($field['match_type'] === 'date')
        {
            $timestamp = strtotime($fieldValue);

            if ($timestamp === false)
            {
                $timestamp = 0;
            }

            return $timestamp;
        }

        return (int) $fieldValue;
    }

    public function getFloat($field, $fieldValue)
    {
        return (float) $fieldValue;
    }

    /**
     * @param array|\ArrayAccess $fieldInfo
     *
     * @return bool True if field value should be an indexable text message
     */
    public function isFreeText($fieldInfo)
    {
        return
            !$this->isMultipleOption($fieldInfo)
            && !$this->isSingleOption($fieldInfo)
            && !$this->isFloat($fieldInfo)
            && !$this->isInteger($fieldInfo)
            && !$this->isLocationField($fieldInfo)
            && !$this->isColor($fieldInfo);
    }

    public function isMultipleOption($fieldInfo)
    {
        return in_array($fieldInfo['field_type'], ['checkbox', 'multiselect'], true);
    }

    public function isSingleOption($fieldInfo)
    {
        return in_array($fieldInfo['field_type'], ['radio', 'select'], true);
    }

    public function isInteger($fieldInfo)
    {
        if (
            $fieldInfo['field_type'] === 'stars'
            || (
                (
                    $fieldInfo['match_type'] === 'number' // numbers, dates and colors are handled specially
                    && !empty($fieldInfo['match_params']['number_integer'])
                )
                || $fieldInfo['match_type'] === 'date'
            )
        ) {
            return true;
        }

        return false;
    }

    public function isFloat($fieldInfo)
    {
        if (
            $fieldInfo['match_type'] === 'number' // numbers, dates and colors are handled specially
            && empty($fieldInfo['match_params']['number_integer'])
        ) {
            return true;
        }

        return false;
    }

    public function isColor($fieldInfo)
    {
        return $fieldInfo['match_type'] === 'color';
    }

    public function isLocationField($fieldInfo)
    {
        // Ignore the field if the add-on is disabled
        if (!Compat::isAddOnActive('AL/LocationField'))
        {
            return false;
        }

        return $fieldInfo['field_type'] === 'location';
    }

    public function getIndexSortingColumnName($fieldInfo)
    {
        if (
            $this->isInteger($fieldInfo)
            || $this->isDateField($fieldInfo)
            || $this->isStarField($fieldInfo)
        ) {
            return 'field_int';
        }

        if ($this->isFloat($fieldInfo))
        {
            return 'field_float';
        }

        return false;
    }
}
