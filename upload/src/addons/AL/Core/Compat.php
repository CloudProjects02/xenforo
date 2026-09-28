<?php

namespace AL\Core;
/**
 * Compatibility functions for older XenForo versions
 */
class Compat
{
    /**
     * @deprecated the method is not used anymore, as class extension hack is moved to filter framework
     * @return bool
     */
    public static function isLegacyExtensionEnabled()
    {
        if (
            (
                !\XF::options()->offsetExists('al_core_legacy_extension')
                ||
                \XF::options()->al_core_legacy_extension
            )
            && !defined('AL_CORE_DISABLE_LEGACY_EXTENSION'))
        {
            return true;
        }

        return false;
    }

    public static function isAddOnActive($addOnId, $versionId = null, $operator = '>=')
    {
        if (is_callable('\XF::isAddOnActive'))
        {
            return call_user_func('\XF::isAddOnActive', $addOnId, $versionId, $operator);
        }

        $addOns = \XF::app()->container('addon.cache');
        if (!isset($addOns[$addOnId]))
        {
            return false;
        }

        $activeVersionId = $addOns[$addOnId];
        if ($versionId === null)
        {
            return $activeVersionId;
        }

        switch ($operator)
        {
            case '>':
                return ($activeVersionId > $versionId);

            case '>=':
                return ($activeVersionId >= $versionId);

            case '<':
                return ($activeVersionId < $versionId);

            case '<=':
                return ($activeVersionId <= $versionId);
        }

        return $activeVersionId;
    }
}