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
use AL\FilterFramework\Entity\FieldData;
use XF\App;
use XF\Entity\AbstractField;
use XF\Mvc\Controller;
use XF\Service\AbstractService;

class FieldSaver extends AbstractService
{
    protected $contentTypeProvider;

    public function __construct(App $app, ContentTypeProviderInterface $contentTypeProvider)
    {
        parent::__construct($app);

        $this->contentTypeProvider = $contentTypeProvider;
    }

    public function setupFromInput(AbstractField $field, Controller $controller)
    {
        if ($field->field_id === null)
        {
            // field was not saved successfully
            return;
        }

        /** @var FieldData $fieldData */
        $fieldData = $field->getRelationOrDefault('FieldData');
        $fieldData->field_id = $field->field_id;
        $fieldData->content_type = $this->contentTypeProvider->getContentType();
        $fieldData->filter_template = $controller->filter('filter_template', 'str');
        if ($controller->filter('default_match_type', 'str'))
        {
            $fieldData->default_match_type = $controller->filter('default_match_type', 'str');
        }
        $fieldData->allow_filter = $controller->filter('allow_filter', 'bool');
        $fieldData->allow_search = $controller->filter('allow_search', 'bool');
        $fieldData->allow_sorting = $controller->filter('allow_sorting', 'bool');
        if (!$fieldData->filter_template)
        {
            $fieldData->filter_template = 'checkbox';
        }
    }
}
