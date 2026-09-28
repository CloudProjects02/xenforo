<?php

namespace AL\Core;

/**
 * Class Extension tweak to support XenForo 2.1.x and 2.2.x in the same pacakge
 *
 * The class wraps the default extension object available in the container
 * as it was the only way to manipulate the method @resolveExtendedClassToRoot
 */
class Extension extends \XF\Extension
{
    /**
     * @var \XF\Extension
     */
    protected $original_extension;

    public function resolveExtendedClassToRoot($class)
    {
        if (is_object($class))
        {
            $class = get_class($class);
        }

        // Class extensions are rare, so do the check first
        if (substr($class, 0, 3) === 'AL\\' && preg_match('#XF\d+#', $class))
        {
            // Just to simplify the regexp below
            $class = str_replace('\\', '/', $class);
            $class = preg_replace('#/XF\d+/(\w+)$#', '/$1', $class);
            $class = preg_replace('#^AL.*?/XF/#', 'XF/', $class);
            $class = str_replace('/', '\\', $class);

            // In this case we already know the exact name of the class, no need to call the parent at all
            return $class;
        }

        // This is the fix required on XenForo 2.2.x
        if (property_exists($this->original_extension, 'inverseExtensionMap'))
        {
            $this->original_extension->inverseExtensionMap += $this->inverseExtensionMap;
        }

        return $this->original_extension->resolveExtendedClassToRoot($class);
    }

    /**
     * @param \XF\Extension $original_extension
     */
    public function setOriginalExtension(\XF\Extension $original_extension)
    {
        $this->original_extension = $original_extension;
        $this->listeners = $original_extension->listeners;
        $this->classExtensions = $original_extension->classExtensions;
        $this->extensionMap = $original_extension->extensionMap;
        if (property_exists($original_extension, 'inverseExtensionMap'))
        {
            $this->inverseExtensionMap = $original_extension->inverseExtensionMap;
        }
    }
}