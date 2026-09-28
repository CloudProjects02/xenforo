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

use AddonsLab\Core\LazyLoadList;
use AL\FilterFramework\ActiveFilterInfo;
use AL\FilterFramework\ContentTypeProviderInterface;
use AL\FilterFramework\FilterApp;
use XF\App;
use XF\CustomField\Definition;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Str\Formatter;

class DisplayFormatter extends AbstractService
{
    protected $contentTypeProvider;

    public function __construct(
        App $app,
        ContentTypeProviderInterface $contentTypeProvider
    ) {
        parent::__construct($app);
        $this->contentTypeProvider = $contentTypeProvider;
    }

    /**
     * @param array $filterData
     *                          Based on input data provided prepares data required for rending active filters
     *
     * @throws PrintableException
     */
    public function prepareActiveFilters(array $filterData)
    {
        if (empty($filterData))
        {
            return [];
        }

        $activeFilterData = FilterApp::getExtendedAttributeHandler($this->contentTypeProvider)->getActiveFilters($filterData);

        $fieldDefinitions = $this->contentTypeProvider->getFieldDefinitions();

        /** @var TypeProvider $typeProvider */
        $typeProvider = FilterApp::getTypeProvider();
        $inputTransformer = FilterApp::getInputTransformer($this->contentTypeProvider);
        $contentTypeProvider = $this->contentTypeProvider;

        /** @var Definition $fieldDefinition */
        foreach ($fieldDefinitions as $fieldId => $fieldDefinition)
        {
            // will handle the case of numeric fields, including dates and ratings
            if ($inputTransformer->normalizeFieldInputValue($filterData, $fieldId) === false)
            {
                continue;
            }

            $template = '';
            $phrase = '';
            $phraseParams = [];
            $templateParams = [];
            $showLabel = true; // if false the name of the field itself will not be shown

            if ($typeProvider->isColor($fieldDefinition) && ($labColor = $typeProvider->getColor($fieldDefinition, $filterData)))
            {
                $showLabel = false;
                $template = 'color';

                /** @var ColorConverter $colorConverter */
                $colorConverter = \XF::service('AL\FilterFramework:ColorConverter');
                $templateParams = [
                    'backgroundColor' => $filterData[$fieldId],
                    'color' => $colorConverter->getContrastColor($filterData[$fieldId]),
                ];
            }
            elseif ($typeProvider->isInteger($fieldDefinition) || $typeProvider->isFloat($fieldDefinition))
            {
                $filterValue = $typeProvider->getIndexTableValue($fieldDefinition, $filterData);
                if ($filterValue === false)
                {
                    // the data was not set in filters at all
                    continue;
                }

                [$operator, $value] = $filterValue;

                if ($typeProvider->isStarField($fieldDefinition))
                {
                    $template = 'stars'; // no need for any phrase, will use a template to render
                    $templateParams = ['stars' => $value];
                }
                else
                {
                    $postfix = $typeProvider->isDateField($fieldDefinition) ? '_date' : '';

                    switch ($operator)
                    {
                        case 'BETWEEN':
                            $phrase = $contentTypeProvider->getOptionPrefix() . '_value_between_x_y';
                            $phraseParams =
                                [
                                    'x' => $this->formatFieldForDisplay($fieldDefinition, $value[0]),
                                    'y' => $this->formatFieldForDisplay($fieldDefinition, $value[1]),
                                ];

                            break;

                        case '<=':
                            $phrase = $contentTypeProvider->getOptionPrefix() . '_less_than_x';
                            $phraseParams = ['x' => $this->formatFieldForDisplay($fieldDefinition, $value)];

                            break;

                        case '>=':
                            $phrase = $contentTypeProvider->getOptionPrefix() . '_more_than_x';
                            $phraseParams = ['x' => $this->formatFieldForDisplay($fieldDefinition, $value)];

                            break;

                        case '=':
                            $phrase = $contentTypeProvider->getOptionPrefix() . '_equal_to_x';
                            $phraseParams = ['x' => $this->formatFieldForDisplay($fieldDefinition, $value)];

                            break;

                        default:
                            throw new PrintableException(\XF::phrase($contentTypeProvider->getOptionPrefix() . '_field_operator_is_undefined'));
                    }
                    $phrase .= $postfix;
                }
            }
            elseif ($typeProvider->isFreeText($fieldDefinition))
            {
                if (empty($filterData[$fieldId]))
                {
                    continue;
                }

                $string = $this->formatFieldForDisplay($fieldDefinition, $filterData[$fieldId]);

                $phrase = $contentTypeProvider->getOptionPrefix() . '_match_text';
                $phraseParams = [
                    'string' => htmlspecialchars($string),
                ];
            }
            elseif ($typeProvider->isSingleOption($fieldDefinition) || $typeProvider->isMultipleOption($fieldDefinition))
            {
                if (empty($filterData[$fieldId]))
                {
                    continue;
                }

                $formattedValue = $this->formatFieldForDisplay($fieldDefinition, $filterData[$fieldId]);

                if (!$formattedValue)
                {
                    continue;
                }

                $phrase = $contentTypeProvider->getOptionPrefix() . '_match_text';
                $phraseParams = [
                    'string' => $formattedValue,
                ];

                // remove the lines below if we don't need them anymore
                /*$selectedValues = $this->formatFieldForDisplay($fieldDefinition, $filterData[$fieldId]);

                if (empty($selectedValues))
                {
                    continue;
                }

                $template = 'selection';
                $templateParams['options'] = $selectedValues;*/
            }
            else
            {
                // Fire an event to allow other add-ons to handle the custom types
                $template = '';
                $phrase = '';
                $phraseParams = [];
                $templateParams = [];
                $showLabel = true; // if false the name of the field itself will not be shown

                \XF::app()->fire('filter_framework_display_formatter_active_filters', [$contentTypeProvider, $fieldDefinition, $filterData, &$template, &$phrase, &$phraseParams, &$templateParams, &$showLabel]);
            }

            if ($template || $phrase)
            {
                if ($phrase)
                {
                    $phrase = \XF::phrase($phrase, $phraseParams);
                }

                $activeFilterData[$fieldId] = new ActiveFilterInfo([
                    'showLabel' => $showLabel,
                    'title' => $fieldDefinition['title'],
                    'template' => $template,
                    'templateParams' => $templateParams,
                    'phrase' => $phrase,
                    'match_type' => $fieldDefinition['match_type'],
                    'field_type' => $fieldDefinition['field_type'],
                ]);
            }
        }

        return $activeFilterData;
    }

    /**
     * @param Definition|\AL\FilterFramework\XF\CustomField\Definition $field
     * @param mixed                                                    $value
     *
     * @return array|mixed|string|string[]|null
     */
    public function formatFieldForDisplay($field, $value)
    {
        $typeProvider = FilterApp::getTypeProvider();

        if ($field instanceof \AL\FilterFramework\XF\CustomField\Definition && $field->offsetGet('display_template'))
        {
            $field->trimHtmlFromDisplayTemplate();
        }

        $formattedValue = null;
        \XF::app()->fire('filter_framework_format_field_for_display', [$this->contentTypeProvider, $field, $value, &$formattedValue]);

        if ($formattedValue !== null)
        {
            return $formattedValue;
        }

        if ($typeProvider->isDateField($field))
        {
            $value = \XF::language()->date($value);
        }
        elseif ($typeProvider->isInteger($field) || $typeProvider->isFloat($field))
        {
            $exclusionList = $this->contentTypeProvider->getNumberFormattingExclusionListSetting();
            $exclusionList = $exclusionList ? array_map('trim', explode(',', $exclusionList)) : [];
            if (!in_array($field->field_id, $exclusionList, true))
            {
                $value = \XF::language()->numberFormat($value, $typeProvider->isInteger($field) ? 0 : 1);
            }

            // format according to display template setting
            $value = $field->getFormattedValue($value);
        }
        elseif ($typeProvider->isFreeText($field))
        {
            $formatter = new Formatter();
            $value = $formatter->wholeWordTrim($value, 20);
            $value = $field->getFormattedValue($value);
        }
        elseif (
            $typeProvider->isSingleOption($field)
            || $typeProvider->isMultipleOption($field)
        ) {
            $value = (array) $value;
            $list = new LazyLoadList(', ');

            if ($typeProvider->isSingleOption($field))
            {
                $value = array_map(function ($v) use ($field, $list) {
                    $list->addItem($field->getFormattedValue($v));
                }, $value);
            }
            elseif ($typeProvider->isMultipleOption($field))
            {
                $value = array_map(function ($v) use ($field, $list) {
                    $list->addItem($field->getFormattedValue(array_flip([$v])));
                }, $value);
            }

            $value = (string) $list;

            /*$selectedValues = [];
            foreach ($value AS $selectedOption)
            {
                $phrase = $this->contentTypeProvider->getPhraseForOption($field, $selectedOption);
                $selectedValues[] = \XF::phrase($phrase);
            }
            $value = $selectedValues;*/
        }

        return $value;
    }
}
