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


namespace AL\ResourceFilter\XFRM\Admin\Controller;

use AL\ResourceFilter\App;

class  Category extends XFCP_Category
{
    protected function categorySaveProcess(\XFRM\Entity\Category $category)
    {
        $form = parent::categorySaveProcess($category);

        $form->setup(function () use ($category)
        {
            $category->filter_location = $this->filter('filter_location', 'str');
        });

        $field_column_cache = $this->filter('field_column_cache', 'array-array');
        $form->complete(function () use ($category, $field_column_cache)
        {
            App::getContextProvider()->postSaveNode($category);

            $category->field_column_cache = $field_column_cache;
            $category->saveIfChanged();
        });

        return $form;
    }

    public function categoryAddEdit(\XFRM\Entity\Category $category)
    {
        $reply = parent::categoryAddEdit($category);
        $reply->setParam('fieldCache', App::getContentTypeProvider()->getFieldDefinitions());
        return $reply;
    }


}
