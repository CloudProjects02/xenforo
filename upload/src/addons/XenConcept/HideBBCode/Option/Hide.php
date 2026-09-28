<?php
/*************************************************************************
 * Hide BBCode - XenConcept (c) 2021
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\HideBBCode\Option;

use XF\Option\AbstractOption;

class Hide extends AbstractOption
{
    public static function renderHideMappedTo(\XF\Entity\Option $option, array $htmlParams)
    {
        $data = self::getSelectData($option, $htmlParams);

        unset($data['choices']['xcHideDefault']);

        return self::getTemplater()->formSelectRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );
    }

    public static function renderHideReactMappedTo(\XF\Entity\Option $option, array $htmlParams)
    {
        $data = self::getSelectData($option, $htmlParams, true);
        
        $data['choices'] = array_filter($data['choices'], function ($hide)
        {
            if ($hide['value'] == 'default' || $hide['value'] == 'xcHideReplyReact' || $hide['value'] == 'xcHideReplyOrReact')
            {
                return true;
            }
            return false;
        });

        return self::getTemplater()->formSelectRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );
    }

    public static function renderSelect(\XF\Entity\Option $option, array $htmlParams)
    {
        $data = self::getSelectData($option, $htmlParams);

        return self::getTemplater()->formCheckBoxRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );
    }

    public static function renderSelectMultiple(\XF\Entity\Option $option, array $htmlParams)
    {
        $data = self::getSelectData($option, $htmlParams);
        $data['controlOptions']['multiple'] = true;
        $data['controlOptions']['size'] = 8;
        $data['controlOptions']['listclass'] = 'listColumns';

        return self::getTemplater()->formCheckBoxRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );
    }

    protected static function getSelectData(\XF\Entity\Option $option, array $htmlParams, $includeDefault = false)
    {
        /** @var \XenConcept\HideBBCode\Repository\HideBbCode $hideRepo */
        $hideRepo = \XF::repository('XenConcept\HideBBCode:HideBbCode');

        $choices = $hideRepo->getHideOptionsData($includeDefault);
        $choices = array_map(function($v) {
            $v['label'] = \XF::escapeString($v['label']);
            return $v;
        }, $choices);

        return [
            'choices' => $choices,
            'controlOptions' => self::getControlOptions($option, $htmlParams),
            'rowOptions' => self::getRowOptions($option, $htmlParams)
        ];
    }
}