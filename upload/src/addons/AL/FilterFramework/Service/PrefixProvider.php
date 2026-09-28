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
use XF\Entity\AbstractPrefix;
use XF\Service\AbstractService;

class PrefixProvider extends AbstractService
{
    /**
     * @var ContentTypeProviderInterface
     */
    protected $contentTypeProvider;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);
        $this->contentTypeProvider = $contentTypeProvider;
    }

    public function getPrefixesByIds(array $ids)
    {
        $prefixEntityName = $this->contentTypeProvider->getPrefixEntityName();

        if (!$prefixEntityName)
        {
            return [];
        }

        $prefixes = \XF::em()->findByIds($prefixEntityName, $ids);

        return array_map(function (AbstractPrefix $prefix) {
            return $prefix->title;
        }, $prefixes->toArray());
    }
}
