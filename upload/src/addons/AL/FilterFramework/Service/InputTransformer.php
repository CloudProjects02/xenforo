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

use AL\FilterFramework\ContentTypeProviderInterface;
use AL\FilterFramework\Field\AbstractField;
use AL\FilterFramework\Field\ColorField;
use AL\FilterFramework\Field\FloatField;
use AL\FilterFramework\Field\FreeTextField;
use AL\FilterFramework\Field\IntField;
use AL\FilterFramework\Field\LocationField;
use AL\FilterFramework\Field\MultipleChoiceField;
use AL\FilterFramework\Field\SingleChoiceField;
use AL\FilterFramework\FilterApp;
use AL\FilterFramework\Flags;
use XF\App;
use XF\CustomField\Definition;
use XF\Service\AbstractService;

/**
 * Class InputTransformer.
 */
class InputTransformer extends AbstractService
{
    protected $contentTypeProvider;

    public function __construct(App $app, ?ContentTypeProviderInterface $contentTypeProvider = null)
    {
        parent::__construct($app);
        $this->contentTypeProvider = $contentTypeProvider;
    }

    /**
     * @param array $metadata Addition information provided for search configuration
     *
     * @return AbstractField[]
     *                         Converts user input to built-in field in types and set their values. Returns an array of fields
     */
    public function convertInputToFieldArray(ContentTypeProviderInterface $contentTypeProvider, array $filterData, array $metadata)
    {
        $fieldDefinitions = $contentTypeProvider->getFieldDefinitions();
        $typeProvider = FilterApp::getTypeProvider();

        /** @var AbstractField[] $fieldArray */
        $fieldArray = [];

        $fieldConfigArray = $filterData['__config'] ?? [];

        foreach ($fieldDefinitions as $fieldId => $fieldDefinition)
        {
            if (!isset($filterData[$fieldId]))
            {
                continue;
            }

            $fieldInputArray = $filterData[$fieldId];

            if (
                $typeProvider->isColor($fieldDefinition)
                && ($labColor = $typeProvider->getColor($fieldDefinition, $filterData))
            ) {
                $fieldArray[$fieldId] = new ColorField($labColor);

                continue;
            }

            if ($typeProvider->isInteger($fieldDefinition))
            {
                $filterValue = $typeProvider->getIndexTableValue($fieldDefinition, $filterData);
                if ($filterValue !== false)
                {
                    $fieldArray[$fieldId] = new IntField($filterValue[0], $filterValue[1]);
                }

                continue;
            }

            if ($typeProvider->isFloat($fieldDefinition))
            {
                $filterValue = $typeProvider->getIndexTableValue($fieldDefinition, $filterData);
                if ($filterValue !== false)
                {
                    $fieldArray[$fieldId] = new FloatField($filterValue[0], $filterValue[1]);
                }

                continue;
            }

            if ($isSingleOption = $typeProvider->isSingleOption($fieldDefinition))
            {
                if (empty($fieldInputArray))
                {
                    continue;
                }

                $field = new SingleChoiceField((array) $fieldInputArray);

                if (
                    !empty($metadata[Flags::SINGLE_CHOICE_PARTIAL_MATCH])
                    || $this->_forcePartialMatch($fieldDefinition)
                ) {
                    $field->setExactMatch(false);
                }

                $fieldArray[$fieldId] = $field;

                continue;
            }

            if ($isMultipleOption = $typeProvider->isMultipleOption($fieldDefinition))
            {
                if (empty($fieldInputArray))
                {
                    continue;
                }

                $fieldArray[$fieldId] = new MultipleChoiceField((array) $fieldInputArray);

                continue;
            }

            if ($isFreeText = $typeProvider->isFreeText($fieldDefinition))
            {
                $fieldArray[$fieldId] = new FreeTextField($fieldInputArray);

                continue;
            }

            if (
                $typeProvider->isLocationField($fieldDefinition)
                && $fieldDefinition instanceof \AL\LocationField\XF\CustomField\Definition
            ) {
                $configuration = $fieldDefinition->getLocationConfiguration();
                // Location fields will be filtered only if there is an API Key
                // and range mode is enabled
                if (
                    $configuration->getSearchFormatOption() !== 'range_search' || $contentTypeProvider->getGoogleApiKeySetting()
                ) {
                    // Removed default country selection
                    $fieldArray[$fieldId] = new LocationField($fieldInputArray);
                    $fieldArray[$fieldId]->setSearchFormat($configuration->getSearchFormatOption());
                }

                continue;
            }

            throw new \RuntimeException('Unhandled field type - ' . $fieldId);
        }

        foreach ($fieldArray as $fieldId => $field)
        {
            if (
                isset($fieldConfigArray[$fieldId], $fieldConfigArray[$fieldId]['match_type'])

                && $fieldConfigArray[$fieldId]['match_type'] === 'AND'
            ) {
                $field->setMatchType('AND');
            }
        }

        return $fieldArray;
    }

    public function normalizeInput(array &$filterData)
    {
        $config = $filterData['__config'] ?? [];
        unset($filterData['__config']);

        /** @var TypeProvider $typeProvider */
        $typeProvider = FilterApp::getTypeProvider();
        foreach ($filterData as $fieldId => $fieldValue)
        {
            $this->normalizeFieldInputValue($filterData, $fieldId);
        }

        if (!empty($filterData[Flags::MULTI_PREFIX_KEY]))
        {
            $filterData[Flags::MULTI_PREFIX_KEY] = array_filter($filterData[Flags::MULTI_PREFIX_KEY]);

            /** @noinspection NotOptimalIfConditionsInspection */
            if (empty($filterData[Flags::MULTI_PREFIX_KEY]))
            {
                unset($filterData[Flags::MULTI_PREFIX_KEY]);
            }
        }

        $allowedKeys = array_keys($filterData);

        // only configuration of non-empty fields needs to stay in the URL
        $config = array_filter($config, function ($key) use ($allowedKeys) {
            return in_array($key, $allowedKeys, true);
        }, ARRAY_FILTER_USE_KEY);

        if (!empty($config))
        {
            $filterData['__config'] = $config;
        }
    }

    /**
     * @param       $fieldId
     *                         Checks the values submitted have any non-empty data in them, and removes the unneeded keys
     * @param mixed $filterData
     *
     * @return bool If false, no further processing of the field is required as it is empty
     */
    public function normalizeFieldInputValue(&$filterData, $fieldId)
    {
        if (!isset($filterData[$fieldId]))
        {
            return false;
        }

        if (!is_array($filterData[$fieldId]))
        {
            // for scalar values we just check if there is any non-empty information in them
            if (strlen($filterData[$fieldId]) === 0)
            {
                // no info filled-in
                unset($filterData[$fieldId]);

                return false;
            }

            return true;
        }

        // cleanup the location field
        if ($this->contentTypeProvider)
        {
            $typeProvider = FilterApp::getTypeProvider();
            $field = FilterApp::getContextProvider($this->contentTypeProvider)->getFieldDefinitionById($fieldId);
            if ($field && $typeProvider->isLocationField($field))
            {
                $filterData[$fieldId] = $this->getFilterLocationData($field, $filterData);
                if (empty($filterData[$fieldId]))
                {
                    unset($filterData[$fieldId]);
                }

                return true;
            }
        }

        // now we know the keys are there, we should check if they are filled
        if (isset($filterData[$fieldId]['from']) && $this->isNonEmptyValue($filterData[$fieldId]['from']) === false)
        {
            unset($filterData[$fieldId]['from']);
        }
        if (isset($filterData[$fieldId]['to']) && $this->isNonEmptyValue($filterData[$fieldId]['to']) === false)
        {
            unset($filterData[$fieldId]['to']);
        }

        if (is_array($filterData[$fieldId]))
        {
            $filterData[$fieldId] = array_diff($filterData[$fieldId], ['']);

            if (empty($filterData[$fieldId]))
            {
                // a completely empty array remained, we can remove it from filters
                unset($filterData[$fieldId]);
            }
        }

        // we have got some none-empty data
        return isset($filterData[$fieldId]);
    }

    public function isNonEmptyValue($value)
    {
        if (is_array($value) && empty($value))
        {
            return false;
        }

        if (is_scalar($value) && strlen($value) === 0)
        {
            return false;
        }

        return true;
    }

    /**
     * @param string $fieldValue
     *
     * @return string|array
     *                      Gets the value of a custom field stored in the database and converts it an array if it is a multiple-choice field
     */
    public function getNormalizedFieldValue($fieldValue)
    {
        if ($this->is_serialized_array($fieldValue))
        {
            $fieldValue = unserialize($fieldValue);
        }

        return $fieldValue;
    }

    /**
     * @param Definition|\AL\LocationField\XF\CustomField\Definition $fieldInfo
     *
     * @return mixed|null
     */
    public function getFilterLocationData(Definition $fieldInfo, array $filterData)
    {
        $fieldId = $fieldInfo['field_id'];

        if (empty($filterData[$fieldId]) || !is_array($filterData[$fieldId]))
        {
            return null;
        }

        $locationData = $filterData[$fieldId];

        $configuration = $fieldInfo->getLocationConfiguration();

        // Validate the country code provided
        $countryProvider = \XF::service('AL\LocationField:CountryListProvider');
        if (
            !empty($locationData['country_code'])
            && !$countryProvider->isValidCountryCode($locationData['country_code'])
        ) {
            $locationData['country_code'] = '';
        }

        $searchFormatOption = method_exists($configuration, 'getSearchFormatOption') ? $configuration->getSearchFormatOption() : 'range_search';

        if ($searchFormatOption === 'range_search')
        {
            // in range search mode we should have the address and range specified
            if (empty($locationData['address'])
                || trim($locationData['address']) === ''
                || empty($locationData['range'])
                || (float) $locationData['range'] < 0.001)
            {
                return null;
            }

            // validate the unit
            if (empty($locationData['unit']) || !in_array($locationData['unit'], ['km', 'mile']))
            {
                $optionName = $this->contentTypeProvider
                    ? $this->contentTypeProvider->getOptionPrefix() . '_default_distance_unit'
                    : '';
                $locationData['unit'] = $optionName ? \XF::options()->{$optionName} : 'km';
            }
        }

        if ($searchFormatOption === 'address_search')
        {
            if (!$configuration->isCountrySelectionEnabled())
            {
                // Country field is not shown to users, only default country is available anyway
                $locationData['country_code'] = $configuration->getDefaultCountry();
            }

            if (empty($locationData['country_code']))
            {
                // We need the country code to validate the data in it
                unset($locationData['country_code'], $locationData['state_id'], $locationData['city_id']);
            }
            if (empty($locationData['state_id']))
            {
                // No city without state
                unset($locationData['state_id'], $locationData['city_id']);
            }
            if (empty($locationData['city_id']))
            {
                // Do not have city in the URL if it is empty
                unset($locationData['city_id']);
            }

            if (
                empty($locationData['country_code'])
                && empty($locationData['state_id'])
                && empty($locationData['city_id'])
            ) {
                // no data provided
                return null;
            }
        }

        return $locationData;
    }

    /**
     * @param bool  $strict
     * @param mixed $data
     *
     * @return bool
     *              WordPress implementation of checking if a value is serialized
     */
    protected function is_serialized_array($data, $strict = true)
    {
        // if it isn't a string, it isn't serialized.
        if (!is_string($data))
        {
            return false;
        }

        $data = trim($data);

        if (substr($data, 0, 2) !== 'a:')
        {
            // not a serialized array
            return false;
        }

        if ('N;' === $data)
        {
            return true;
        }
        if (strlen($data) < 4)
        {
            return false;
        }
        if (':' !== $data[1])
        {
            return false;
        }
        if ($strict)
        {
            $lastc = substr($data, -1);
            if (';' !== $lastc && '}' !== $lastc)
            {
                return false;
            }
        }
        else
        {
            $semicolon = strpos($data, ';');
            $brace = strpos($data, '}');
            // Either ; or } must exist.
            if (false === $semicolon && false === $brace)
            {
                return false;
            }
            // But neither must be in the first X characters.
            if (false !== $semicolon && $semicolon < 3)
            {
                return false;
            }
            if (false !== $brace && $brace < 4)
            {
                return false;
            }
        }
        $token = $data[0];

        switch ($token)
        {
            case 's':
                if ($strict)
                {
                    if ('"' !== substr($data, -2, 1))
                    {
                        return false;
                    }
                }
                elseif (false === strpos($data, '"'))
                {
                    return false;
                }

                // or else fall through
                // no break
            case 'a':
            case 'O':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);

            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';

                return (bool) preg_match("/^{$token}:[0-9.E-]+;{$end}/", $data);
        }

        return false;
    }

    /**
     * Extension point for other add-ons to force partial match mode on a field.
     *
     * @return bool
     */
    protected function _forcePartialMatch(Definition $field)
    {
        return false;
    }
}
