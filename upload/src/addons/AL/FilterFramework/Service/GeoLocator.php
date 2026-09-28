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

use AL\LocationField\Service\GeoCoder;
use XF\Service\AbstractService;

class GeoLocator extends AbstractService
{
    public function getAddressCoordinates($address, $country_code = '', $place_id = '', &$error = null)
    {
        /** @var GeoCoder $service */
        $service = $this->service('AL\LocationField:GeoCoder');

        if ($place_id)
        {
            $result = $service->getPlaceInfo($place_id);
            if ($result)
            {
                return $this->_getInfoFromResult($result);
            }
        }

        $info = $service->getAddressInformation($address, $country_code ? ['country:' . $country_code] : []);

        if ($info['is_okay'])
        {
            if ($info['results'])
            {
                return $this->_getInfoFromResult($info['results'][0]);
            }

            // Empty results
            return null;
        }

        $error = $info['exception']->message;

        return null;
    }

    public function getCoordinateIntervals($coordinates, $distance, $unit)
    {
        if ($unit === 'mile')
        {
            $km = 1.60934 * $distance;
        }
        else
        {
            $km = $distance;
        }

        $R = 6371;
        $coordinates['lat'] = deg2rad($coordinates['lat']);
        $coordinates['lng'] = deg2rad($coordinates['lng']);

        $angleLng = deg2rad(90);
        $angleLat = deg2rad(0);

        $latHorizontal = asin(sin($coordinates['lat']) * cos($km / $R))
            + cos($coordinates['lat']) * sin($km / $R) * cos($angleLng);
        $lngMax = $coordinates['lng'] + atan2(
            sin($angleLng) * sin($km / $R) * cos($coordinates['lat']),
            cos($km / $R) - sin($coordinates['lat']) * sin($latHorizontal)
        );
        $lngMin = 2 * $coordinates['lng'] - $lngMax;

        $latMax = asin(sin($coordinates['lat']) * cos($km / $R))
            + cos($coordinates['lat']) * sin($km / $R) * cos($angleLat);

        $latMin = 2 * $coordinates['lat'] - $latMax;

        return ['latMax' => rad2deg($latMax), 'lngMax' => rad2deg($lngMax), 'latMin' => rad2deg($latMin), 'lngMin' => rad2deg($lngMin)];
    }

    protected function _getInfoFromResult($result)
    {
        $lat = round($result->geometry->location->lat, 6);
        $lng = round($result->geometry->location->lng, 6);

        return [
            'lat' => $lat,
            'lng' => $lng,
        ];
    }
}
