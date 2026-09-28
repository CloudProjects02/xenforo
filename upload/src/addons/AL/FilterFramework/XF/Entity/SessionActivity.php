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


namespace AL\FilterFramework\XF\Entity;

use XF\Mvc\Entity\Structure;

class SessionActivity extends XFCP_SessionActivity
{
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['controller_name'] = true;

        return $structure;
    }

    /**
     * @return $this hack is most likely not needed anymore, as the controller name
     *               is correctly resolved before storing it in the database
     */
    public function getControllerName()
    {
        $name = $this->controller_name_;

        if (preg_match('#^AL\\\.*?\\\XF\d+\\\#', $name))
        {
            try
            {
                $name = \XF::app()->extension()->resolveExtendedClassToRoot($name);
            }
            catch (\Exception $ex)
            {
                return $name;
            }
        }

        return $name;
    }
}
