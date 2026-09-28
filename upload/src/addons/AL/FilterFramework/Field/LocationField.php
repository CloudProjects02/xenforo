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


namespace AL\FilterFramework\Field;

use AL\LocationField\Constants;

class LocationField extends AbstractField
{
    protected $location;

    protected $search_format;

    public function __construct(array $location)
    {
        $this->location = array_merge(
            ['unit' => 'km', 'range' => 0, 'country_code' => '', 'address' => '', 'state_id' => '', 'city_id' => '', 'place_id' => ''],
            $location
        );
    }

    /**
     * @param mixed $search_format
     *
     * @return LocationField
     */
    public function setSearchFormat($search_format)
    {
        if (!in_array($search_format, [
            Constants::SEARCH_FORMAT_RANGE,
            Constants::SEARCH_FORMAT_ADDRESS,
        ], true))
        {
            $search_format = Constants::SEARCH_FORMAT_RANGE;
        }

        $this->search_format = $search_format;
    }

    /**
     * @return mixed
     */
    public function getSearchFormat()
    {
        return $this->search_format;
    }

    public function getAsString()
    {
        return '';
    }

    public function getCacheId()
    {
        return $this->search_format . serialize($this->location);
    }

    public function getRange()
    {
        return $this->location['range'];
    }

    public function getUnit()
    {
        return $this->location['unit'];
    }

    public function getGetAddress()
    {
        return $this->location['address'];
    }

    public function getCountryCode()
    {
        return $this->location['country_code'];
    }

    public function getStateId()
    {
        return $this->location['state_id'];
    }

    public function getCityId()
    {
        return $this->location['city_id'];
    }

    public function getPlaceId()
    {
        return $this->location['place_id'];
    }

    public function getFilterOrder()
    {
        return 100;
    }
}
