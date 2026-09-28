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
use XFES\Elasticsearch\Api;

class EsMappingHelper extends AbstractService
{
    protected $contentTypeProvider;

    public function __construct(
        App $app,
        ContentTypeProviderInterface $contentTypeProvider
    ) {
        parent::__construct($app);
        $this->contentTypeProvider = $contentTypeProvider;
    }

    /**
     * @return array
     *               es2 requires store=true for string fields to be able to fetch them back
     *               this is needed for field_id as we need it to group search results
     */
    public function addFieldIdStore(array $mappings, Api $api)
    {
        $type = $this->contentTypeProvider->getIndexContentType();

        $properties = null;

        if (isset($mappings['properties']['field_id']))
        {
            $mappings['properties'] = $this->_applyStoreValue($mappings['properties']);
        }
        elseif ($api->getSingleTypeName() && isset($mappings[$api->getSingleTypeName()]['properties']))
        {
            $mappings[$api->getSingleTypeName()]['properties'] = $this->_applyStoreValue($mappings[$api->getSingleTypeName()]['properties']);
        }
        elseif (isset($mappings[$type]['properties']))
        {
            $mappings = array_map(function ($typeMapping) {
                $typeMapping['properties'] = $this->_applyStoreValue($typeMapping['properties']);

                return $typeMapping;
            }, $mappings);
        }
        else
        {
            \XF::logError(sprintf(
                'Could not correctly detect mapping type. ES version - %s, Single type: %s, type: %s, mapping: %s',
                $api->majorVersion(),
                $api->isSingleTypeIndex(),
                $type,
                json_encode($mappings)
            ));
        }

        return $mappings;
    }

    protected function _applyStoreValue(array $props)
    {
        if (
            isset($props['field_id']['type'], $props['field_id']['index'])
            && $props['field_id']['type'] === 'string'
            && $props['field_id']['index'] === 'not_analyzed'
        ) {
            $props['field_id']['store'] = true;
        }

        return $props;
    }
}
