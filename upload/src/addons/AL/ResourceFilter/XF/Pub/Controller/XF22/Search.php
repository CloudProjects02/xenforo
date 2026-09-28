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


namespace AL\ResourceFilter\XF\Pub\Controller\XF22;

use AL\ResourceFilter\App;
use AL\ResourceFilter\XF\Pub\Controller\SearchControllerTrait;
use AL\ResourceFilter\XF\Pub\Controller\XFCP_Search;

class  Search extends XFCP_Search
{
    use SearchControllerTrait;

    protected function runSearch(\XF\Search\Query\KeywordQuery $query, array $constraints, $allowCached = true)
    {
        if ($query->getTypes() === null)
        {
            // there is no type filter, which would potentially include custom field types, so we need to apply a filter
            $query->inTypes(array_keys(\XF::app()->getContentTypePhrases(false, 'search_handler_class')));
        }

        $types = array_diff($query->getTypes(), [App::getContentTypeProvider()->getIndexContentType()]);
        $query->inTypes(array_values($types)); // resetting the keys as it would generate wrong elastic search query

        return parent::runSearch($query, $constraints, $allowCached);
    }
}