<?php

namespace MMO\CoreLib\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;
use XF\Repository\UserUpgradeRepository;

class UserUpgrade extends AbstractOption
{
    /**
     * @param Option $option
     * @param array $htmlParams
     * @return string
     */
    public static function renderSelect(Option $option, array $htmlParams)
    {
        $data = self::getSelectData($option, $htmlParams);

        return self::getTemplater()->formSelectRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );
    }

    /**
     * @param Option $option
     * @param array $htmlParams
     * @return string
     */
    public static function renderSelectMultiple(Option $option, array $htmlParams)
    {
        $data = self::getSelectData($option, $htmlParams);
        $data['controlOptions']['multiple'] = true;
        $data['controlOptions']['size'] = 8;

        return self::getTemplater()->formSelectRow(
            $data['controlOptions'], $data['choices'], $data['rowOptions']
        );
    }

    /**
     * @param Option $option
     * @param array $htmlParams
     * @return array
     */
    protected static function getSelectData(Option $option, array $htmlParams)
    {
        /** @var UserUpgradeRepository $userUpgradeRepo */
        $userUpgradeRepo = \XF::repository(UserUpgradeRepository::class);
        $userUpgrades = $userUpgradeRepo->findUserUpgradesForList();

        $choices = [
            0 => ['_type' => 'option', 'value' => '', 'label' => \XF::phrase('(none)')]
        ];

        foreach ($userUpgrades as $userUpgradeId => $label)
        {
            $choices[$userUpgradeId] = [
                'value' => $userUpgradeId,
                'label' => $label['title']
            ];
        }

        $choices = array_map(function ($v) {
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