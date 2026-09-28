<?php
/*************************************************************************
 * Profile Views - XenConcept (c) 2017
 * All Rights Reserved.
 **************************************************************************
 * This file is subject to the terms and conditions defined in the Licence
 * Agreement available at Try it like it buy it :)
 *************************************************************************/

namespace XenConcept\ProfileViews\XF\Pub\Controller;

class Account extends XFCP_Account
{
    protected function savePrivacyProcess(\XF\Entity\User $visitor)
    {
        $form = parent::savePrivacyProcess($visitor);

        $input = $this->filter('privacy', 'array');

        $userPrivacy = $visitor->getRelationOrDefault('Privacy');
        $form->setupEntityInput($userPrivacy, $input);

        return $form;
    }
}