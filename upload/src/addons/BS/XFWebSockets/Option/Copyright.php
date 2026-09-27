<?php

namespace BS\XFWebSockets\Option;

class Copyright
{
    private const RESOURCE_ID = 'BS\XFWebSocketsCopyright';

    public static function verifyOption(&$value, \XF\Entity\Option $option)
    {
        if ($value) {
            return true;
        }

        /** @var \BS\XFWebSockets\Service\LicenseChecker $licenseChecker */
        $licenseChecker = \XF::service('BS\XFWebSockets:LicenseChecker');
        if (! $licenseChecker->isAllowedAction(self::RESOURCE_ID, 'install')) {
            $option->error(
                'The right to remove copyright is paid. Please contact the developer to purchase this right.'
            );
            $value = false;
            return false;
        }

        return true;
    }
}