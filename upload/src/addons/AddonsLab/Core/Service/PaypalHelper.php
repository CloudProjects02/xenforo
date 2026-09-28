<?php

namespace AddonsLab\Core\Service;

class PaypalHelper
{
    public static function sanitizeItemTitle($inputString, $maxLength = 127)
    {
        // Remove any special characters that might cause issues
        $safeString = preg_replace('/[^():a-zA-Z0-9 _-]/', '', $inputString);

        // Trim the string to the specified max length
        $safeString = substr($safeString, 0, $maxLength);

        return $safeString;
    }
}