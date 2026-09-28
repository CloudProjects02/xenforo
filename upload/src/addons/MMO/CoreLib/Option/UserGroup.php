<?php

namespace MMO\CoreLib\Option;

use XF\Option\AbstractOption;

class UserGroup extends AbstractOption
{
    /**
     * @param \XF\Entity\Option $option
     * @param array $htmlParams
     * @return string
     */
    public static function renderSelectMultiple(\XF\Entity\Option $option, array $htmlParams)
    {
        $data = self::getSelectData($option, $htmlParams);
        $data['controlOptions']['multiple'] = true;
        $data['controlOptions']['size'] = 8;

        return self::getTemplater()->formCheckBoxRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );
    }

    /**
     * @param \XF\Entity\Option $option
     * @param array $htmlParams
     * @return array
     */
    protected static function getSelectData(\XF\Entity\Option $option, array $htmlParams)
    {
        /** @var \XF\Repository\UserGroup $userGroupRepo */
        $userGroupRepo = \XF::repository('XF:UserGroup');

        $choices = $userGroupRepo->getUserGroupOptionsData(false, 'option');
        $choices = \array_map(function($v) {
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