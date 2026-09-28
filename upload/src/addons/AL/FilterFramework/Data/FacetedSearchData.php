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


namespace AL\FilterFramework\Data;

use AL\FilterFramework\ContentTypeProviderInterface;
use XF\CustomField\Definition;

class FacetedSearchData
{
    protected $has_data = false;

    /**
     * bool If set to true the methods that return CSS class names
     * will return an empty string instead
     * use if you want some templates not to show any visual data related to
     * facet search.
     */
    protected $disable_css = false;

    protected $search_data;

    /**
     * @var ContentTypeProviderInterface|null
     */
    protected $contentTypeProvider;

    /**
     * @param mixed $facetedSearchData
     */
    public function setFacetedSearchData($facetedSearchData)
    {
        $this->has_data = true;
        $this->search_data = $facetedSearchData;
    }

    /**
     * @param bool $has_data
     */
    public function setHasData($has_data)
    {
        $this->has_data = (bool) $has_data;
    }

    /**
     * @param mixed $disable_css
     */
    public function setDisableCss($disable_css)
    {
        $this->disable_css = (bool) $disable_css;
    }

    public function setContentTypeProvider(ContentTypeProviderInterface $contentTypeProvider)
    {
        $this->contentTypeProvider = $contentTypeProvider;
    }

    public function getContainerClass(Definition $field)
    {
        if (
            !$this->contentTypeProvider
            || !$this->contentTypeProvider->getFacetedSearchSetting()
            || $this->contentTypeProvider->getAutoHideIfEmptySetting() !== 'fade'
            || $this->disable_css
        ) {
            return '';
        }

        $field_id = $field->offsetGet('field_id');

        if (
            isset($this->search_data[$field_id])
            && empty($this->search_data[$field_id]['count']))
        {
            return 'customFieldContainer--faded';
        }

        return '';
    }

    public function getChoiceClass(Definition $field, $choice)
    {
        if (
            !$this->contentTypeProvider
            || !$this->contentTypeProvider->getFacetedSearchSetting()
            || $this->contentTypeProvider->getAutoHideIfEmptySetting() !== 'fade'
            || $this->disable_css
        ) {
            return '';
        }

        $field_id = $field->offsetGet('field_id');

        if (
            isset($this->search_data[$field_id])
            && empty($this->search_data[$field_id]['options'][$choice]))
        {
            return 'customFieldOption--faded';
        }

        return '';
    }

    public function getChoices(Definition $field, $filterData, $useHtmlLabel = true)
    {
        $choices = $field->offsetGet('field_choices');

        $enabled = false;
        \XF::app()->fire('filter_framework_filter_active_choices', [$field, &$enabled]);

        if ($enabled)
        {
            // Filter $choices only to ones that are selected in $filterData
            $filterValue = $filterData[$field->offsetGet('field_id')] ?? [];
            if (!is_array($filterValue))
            {
                $filterValue = [$filterValue];
            }

            // Filter by key
            $choices = array_filter($choices, static function ($key) use ($filterValue) {
                return in_array($key, $filterValue, true);
            }, ARRAY_FILTER_USE_KEY);
        }

        if (
            $this->contentTypeProvider === null
            || !$this->contentTypeProvider->getFacetedSearchSetting()
            || $this->disable_css
        ) {
            return $choices;
        }

        $field_id = $field->offsetGet('field_id');

        if (!$this->contentTypeProvider->getFacetedSearchSetting())
        {
            return $choices;
        }

        if (!isset($this->search_data[$field_id]))
        {
            // No data available at all, don't modify the options in any way
            return $choices;
        }

        $autoHide = $this->contentTypeProvider->getAutoHideIfEmptySetting();
        $fieldFacetChoices = $this->search_data[$field_id]['options'];

        $secondList = [];

        foreach ($choices as $choice => $value)
        {
            $choiceCount = !empty($fieldFacetChoices[$choice]) ? $fieldFacetChoices[$choice] : 0;
            if (
                $choiceCount === 0
                && $autoHide === 'hide'
                && (
                    !isset($filterData[$field_id])
                    || (
                        (
                            is_array($filterData[$field_id])
                            && !in_array($choice, $filterData[$field_id], false)
                        )
                        || $filterData[$field_id] !== $choice
                    )
                )
            ) {
                // Remove the option only if there are no items available
                // and the option is not currently active
                continue;
            }

            if ($useHtmlLabel)
            {
                $value = \XF::phrase($this->contentTypeProvider->getOptionPrefix() . '_choice_label', ['choice' => $value, 'count' => $choiceCount]);
            }
            else
            {
                $value = \XF::phrase($this->contentTypeProvider->getOptionPrefix() . '_choice_label_select', ['choice' => $value, 'count' => $choiceCount]);
            }

            $choices[$choice] = $value;

            if (!$choiceCount)
            {
                // show the empty options at the end of the list
                $secondList[$choice] = $value;
                unset($choices[$choice]);
            }
        }

        return $choices + $secondList;
    }

    public function getSearchData()
    {
        return $this->search_data;
    }

    public function getFieldData($fieldId)
    {
        if (isset($this->search_data[$fieldId]))
        {
            return $this->search_data[$fieldId];
        }

        return null;
    }

    public function fieldHasResults($fieldId)
    {
        return true;
    }
}
