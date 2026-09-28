<?php

namespace MMO\CoreLib\Option\CustomFields;

use XF\Option\AbstractOption;

class Contact extends AbstractOption
{
    public static function renderCheckbox(\XF\Entity\Option $option, array $htmlParams)
    {
        $contactFields = \XF::app()
            ->getCustomFields('users', 'contact')
            ->getFieldDefinitions();

        $fields = [];

        foreach ($contactFields as $contact)
        {
            $fields[$contact->field_id] = $contact->title;
        }

        return self::getCheckboxRow($option, $htmlParams, $fields);
    }
}