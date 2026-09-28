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


namespace AL\ResourceFilter\XF\CustomField;

use AL\FilterFramework\ChoiceCacheTrait;
use AL\FilterFramework\Data\FacetedSearchData;
use AL\FilterFramework\FacetedDefinitionInterface;
use AL\ResourceFilter\App;

class  Definition extends XFCP_Definition implements FacetedDefinitionInterface
{
    // start-shared-code
    protected $choice_cache;

    /**
     * @return FacetedSearchData
     */
    public function getFacetData()
    {
        /** @var FacetedSearchData $data */
        return \XF::app()->data('AL\FilterFramework:FacetedSearchData');
    }

    public function offsetGet($offset)
    {
        if ($offset === 'field_choices' && $this->choice_cache !== null)
        {
            return $this->choice_cache;
        }

        $result = parent::offsetGet($offset);

        if ($offset === 'field_choices')
        {
            $this->choice_cache = $result;
        }

        if ($offset === 'field_value' && $this->field_id)
        {
            $result = App::getContextProvider()->fixFieldValueType($this->field_id, $result);
        }

        return $result;
    }

    public function getFormattedValueForColumn($value)
    {
        return App::getFieldIndexer()->getFormattedValueForColumn($this, $value);
    }

    public function getFormattedValue($value)
    {
        return parent::getFormattedValue(
            App::getContextProvider()->fixFieldValueType(
                $this->field_id,
                $value
            )
        );
    }
    // end-shared-code
}
