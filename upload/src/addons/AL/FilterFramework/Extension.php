<?php
/** 
* @package [AL] Filter Framework
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 1.4.3
This software is furnished under a license and may be used and copied
only  in  accordance  with  the  terms  of such  license and with the
inclusion of the above copyright notice.  This software  or any other
copies thereof may not be provided or otherwise made available to any
other person.  No title to and  ownership of the  software is  hereby
transferred.                                                         
                                                                     
You may not reverse  engineer, decompile, defeat  license  encryption
mechanisms, or  disassemble this software product or software product
license.  AddonsLab may terminate this license if you don't comply with
any of these terms and conditions.  In such event,  licensee  agrees 
to return licensor  or destroy  all copies of software  upon termination 
of the license.
*/


namespace AL\FilterFramework;

/**
 * Class Extension tweak to support XenForo 2.1.x and 2.2.x in the same pacakge.
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

            return str_replace('/', '\\', $class);

            // In this case we already know the exact name of the class, no need to call the parent at all
        }

        // This is the fix required on XenForo 2.2.x
        if (property_exists($this->original_extension, 'inverseExtensionMap'))
        {
            $this->original_extension->inverseExtensionMap += $this->inverseExtensionMap;
        }

        return $this->original_extension->resolveExtendedClassToRoot($class);
    }

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
