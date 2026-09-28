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


namespace AL\ResourceFilter\XF\Search\Source\XF21;

use AL\ResourceFilter\App;
use AL\ResourceFilter\ResourceSearchInterface;
use AL\ResourceFilter\XF\Search\Source\MySqlFtTrait;
use AL\ResourceFilter\XF\Search\Source\XFCP_MySqlFt;

class MySqlFt extends XFCP_MySqlFt implements ResourceSearchInterface
{
    use MySqlFtTrait;

    /**
     * @param \XF\Search\Query\Query $query
     * @param $maxResults
     * @return array
     * @noinspection PhpHierarchyChecksInspection
     */
    public function search(\XF\Search\Query\Query $query, $maxResults)
    {
        return $this->_handleSearch($maxResults, function($maxResults) use($query) {
            return parent::search($query, $maxResults);
        });
    }
}