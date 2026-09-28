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


namespace AL\FilterFramework\Service;

use AL\FilterFramework\ContentTypeProviderInterface;
use XF\App;
use XF\Service\AbstractService;

class FieldSorter extends AbstractService
{
    protected $contentTypeProvider;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);

        $this->contentTypeProvider = $contentTypeProvider;
    }

    public function sortFields(array $ids, array $groupOrder)
    {
        $fields = $this->contentTypeProvider->getFieldDefinitions($ids);

        $grouped = [];

        foreach ($groupOrder as $group)
        {
            $grouped[$group] = [];
        }

        $grouped['ungrouped'] = [];

        foreach ($ids as $fieldId)
        {
            if (!isset($fields[$fieldId]))
            {
                continue;
            }

            $field = $fields[$fieldId];
            $group = $field->display_group;

            if (!isset($grouped[$group]))
            {
                $group = 'ungrouped';
            }

            $grouped[$group][] = $fieldId;
        }

        // Flatten the array
        $ids = [];
        foreach ($grouped as $groupIds)
        {
            $ids = array_merge($ids, $groupIds);
        }

        return array_combine($ids, $ids);
    }

    public function orderDefinitions($fields, ?array $ids = null)
    {
        if (!$ids)
        {
            return $fields;
        }

        $ordered = [];

        foreach ($ids as $id)
        {
            if (isset($fields[$id]))
            {
                $ordered[$id] = $fields[$id];
                unset($fields[$id]);
            }
        }

        // Make sure the remaining fields are still in the array, just in case
        // the ids were not fully same the same as fields
        $ordered += $fields;

        return $ordered;
    }
}
