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

namespace AL\ResourceFilter\Finder;

use AL\ResourceFilter\App;
use XF\Mvc\Entity\Finder;

class ResourceFieldIndex extends Finder
{
    public function fetch($limit = null, $offset = null)
    {
        // add the condition of content type to any fetch
        $this->where('content_type', App::getContentTypeProvider()->getContentType());
        return parent::fetch($limit, $offset);
    }

}