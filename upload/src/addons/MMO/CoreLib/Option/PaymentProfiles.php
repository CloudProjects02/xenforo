<?php

namespace MMO\CoreLib\Option;

use XF\Option\AbstractOption;

class PaymentProfiles extends AbstractOption
{
    /**
     * @param \XF\Entity\Option $option
     * @param array $htmlParams
     *
     * @return string
     */
    public static function renderSelect(\XF\Entity\Option $option, array $htmlParams)
    {
        $paymentProfiles = \XF::repository('XF:Payment')
            ->findPaymentProfilesForList()
            ->fetch();

        $profiles = [];

        foreach ($paymentProfiles as $profile)
        {
            $profiles[$profile->payment_profile_id] = $profile->title;
        }

        return self::getSelectRow($option, $htmlParams, $profiles);
    }

    /**
     * @param \XF\Entity\Option $option
     * @param array $htmlParams
     *
     * @return string
     */
    public static function renderCheckbox(\XF\Entity\Option $option, array $htmlParams)
    {
        $paymentProfiles = \XF::repository('XF:Payment')
            ->findPaymentProfilesForList()
            ->fetch();

        $profiles = [];

        foreach ($paymentProfiles as $profile)
        {
            $profiles[$profile->payment_profile_id] = $profile->title;
        }

        return self::getCheckboxRow($option, $htmlParams, $profiles);
    }
}