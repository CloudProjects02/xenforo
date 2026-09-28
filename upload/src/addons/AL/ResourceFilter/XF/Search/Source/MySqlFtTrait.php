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



namespace AL\ResourceFilter\XF\Search\Source;


use AL\FilterFramework\DataSourceInterface;
use AL\FilterFramework\Field\AbstractField;
use AL\ResourceFilter\App;

trait MySqlFtTrait
{
    /**
     * @param AbstractField[] $fieldList
     * @return array Resource ID array matching all parameters
     */
    public function searchResourceIdsByCustomFields(array $fieldList, array $metadata)
    {
        /** @var DataSourceInterface $dbSearcher */
        return App::getMySqlSource()->search($fieldList, $metadata);
    }

    public function countFacetsByResourceIds(array $threadIds, array $fieldIds)
    {
        return App::getMySqlSource()->countFacets($threadIds, $fieldIds);
    }

    protected function _handleSearch($maxResults, $callback)
    {
        $originalMaxResults = $maxResults;

        if ($fieldConstrains = App::getContextProvider()->getQueryConstrains())
        {
            // custom field filters may decrease the number of requests dramatically
            // therefore we need to get many more results to filter later
            $maxResults *= 100;
        }

        $result = call_user_func($callback, $maxResults);

        if ($fieldConstrains)
        {
            $result = App::getContextProvider()->filterSearchResults($result, $fieldConstrains);
            $result = array_slice($result, 0, $originalMaxResults, true);
        }

        return $result;
    }
}