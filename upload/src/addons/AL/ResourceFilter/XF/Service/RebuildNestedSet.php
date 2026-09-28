<?php
/** 
* @package [AddonsLab] Resource Filter
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 3.6.1
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


namespace AL\ResourceFilter\XF\Service;

use AL\FilterFramework\RebuildNestedSetTrait;
use AL\ResourceFilter\App;
use XF\Mvc\Entity\Entity;

class  RebuildNestedSet extends XFCP_RebuildNestedSet
{
    protected function _isValidResourceCategoryEntity($entity)
    {
        if (is_string($entity))
        {
            return $entity === App::getContentTypeProvider()->getCategoryEntityName();
        }
        
        if (is_object($entity))
        {
            $className = \XF::em()->getEntityClassName(App::getContentTypeProvider()->getCategoryEntityName());
            return ($entity instanceof $className);
        }

        return false;
    }

    protected function getBasePassableData()
    {
        $data = parent::getBasePassableData();

        if (!$this->_isValidResourceCategoryEntity($this->entityType))
        {
            return $data;
        }

        $data['effective_filter_location'] = '';

        return $data;
    }

    protected function getSelfData(array $passData, Entity $entity, $depth, $left)
    {
        $passData = parent::getSelfData($passData, $entity, $depth, $left);

        if (!$this->_isValidResourceCategoryEntity($entity))
        {
            return $passData;
        }

        try
        {
            if ($entity->filter_location)
            {
                $passData['effective_filter_location'] = $entity->filter_location;
            }
        } catch (\ErrorException $exception)
        {
            // for types not supported for now
            $passData['effective_filter_location'] = '';
        }

        return $passData;
    }

    protected function getChildPassableData(array $passData, Entity $entity, $depth, $left)
    {
        $passData = parent::getChildPassableData($passData, $entity, $depth, $left);

        if (!$this->_isValidResourceCategoryEntity($entity))
        {
            return $passData;
        }

        try
        {
            if ($entity->filter_location)
            {
                $passData['effective_filter_location'] = $entity->filter_location;
            }
        } catch (\ErrorException $exception)
        {
            // for types not supported for now
            $passData['effective_filter_location'] = '';
        }

        return $passData;
    }
}