<?php

namespace Andrew\ModeratorPanel\XF\ControllerPlugin;

use \XF\Mvc\Entity\Entity;

class Ip extends XFCP_Ip
{
    public function actionIp(Entity $content, array $breadcrumbs = [], $options = [])
    {

        $ipAPI = $this->options()->andrewModeratorPanelIPService;

        //require an API to be selected and for the server to be running https
        if ($ipAPI == 0 || !isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on')
        {
            return  parent::actionIp($content, $breadcrumbs, $options);
        }

        $options = array_merge([
            'id' => 'ip_id',
            'view' => 'XF:Ip\Ip',
            'template' => 'content_ip_view',
            'extraViewParams' => []
        ], $options);

        $visitor = \XF::visitor();

        if (!$visitor->canViewIps())
        {
            return $this->error(\XF::phrase('no_ip_information_available'));
        }

        $ip = null;
        if (!empty($content[$options['id']]))
        {
            $ip = $this->em()->find('XF:Ip', $content[$options['id']]);
        }
        if(!$ip)
        {
            return  parent::actionIp($content, $breadcrumbs, $options);
        }
        $parent = parent::actionIp($content, $breadcrumbs, $options);
        $ipAddress = urlencode(\XF\Util\Ip::convertIpBinaryToString($ip->ip));

        if($ipAddress == "")
        {
            return  parent::actionIp($content, $breadcrumbs, $options);
        }

        $client = $this->app->http()->client();

        //Set all possible variables to empty
        $country = '';
        $region = '';
        $city = '';
        $zip = '';
        $latitudeDecimal = '';
        $longitudeDecimal = '';
        $latitude = '';
        $longitude = '';
        $continent = '';
        $isp = '';
        $is_proxy = '';
        $connection_type = '';
        $is_tor = '';
        $is_relay = '';
        $vpn = '';
        $org = '';
        $country_flag = '';

        if($ipAPI == 1)
        {
            $apiUrl = "https://freeipapi.com/api/json/" . $ipAddress;
            $response = $client->post($apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            $responseData = (string)$response->getBody();
            $data = json_decode($responseData, true);

            // Check if the request was successful
            if ($data && isset($data['success']) && $data['success'] === false) {

                return 'API Error: ' . $data['error']['info'];

            } else {
                // Retrieve geolocation data
                if (isset($data['countryName']))
                {
                    $country = $data['countryName'];
                }
                if (isset($data['regionName']))
                {
                    $region = $data['regionName'];
                }
                if (isset($data['cityName']))
                {
                    $city = $data['cityName'];
                }
                if (isset($data['zipCode']))
                {
                    $zip = $data['zipCode'];
                }
                if (isset($data['latitude']))
                {
                    $latitudeDecimal = $data['latitude'];
                }
                if (isset($data['longitude']))
                {
                    $longitudeDecimal = $data['longitude'];
                }
                if (isset($data['continent']))
                {
                    $continent = $data['continent'];
                }
            }
        }
        elseif($ipAPI == 2)
        {

            $accessKey = $this->options()->andrewModeratorPanelProxyCheckAPIKey;
            $apiUrl = "http://proxycheck.io/v2/" . $ipAddress . "?key=" . $accessKey . "&vpn=1&asn=1";

            $response = $client->get($apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],

            ]);
            $responseData = (string)$response->getBody();
            $data = json_decode($responseData, true);

            // Check if the request was successful
            if ($data && isset($data['success']) && $data['success'] === false) {

                return 'API Error: ' . $data['error']['info'];

            } else {

                foreach ($data as $key => $value) {
                    if (filter_var($key, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        $ipAddress = $key;
                        break; // Stop the loop when the IP address key is found
                    }
                }

                if ($ipAddress !== null) {
                    // Access the value associated with the dynamically found IP address key
                    $dataIp = $data[$ipAddress];

                }

                // Retrieve geolocation data
                if (isset($dataIp['country']))
                {
                    $country = $dataIp['country'];
                }
                if (isset($dataIp['region']))
                {
                    $region = $dataIp['region'];
                }
                if (isset($dataIp['city']))
                {
                    $city = $dataIp['city'];
                }
                if (isset($dataIp['postcode']))
                {
                    $zip = $dataIp['postcode'];
                }
                if (isset($dataIp['latitude']))
                {
                    $latitudeDecimal = $dataIp['latitude'];
                }
                if (isset($dataIp['longitude']))
                {
                    $longitudeDecimal = $dataIp['longitude'];
                }
                if (isset($dataIp['continent']))
                {
                    $continent = $dataIp['continent'];
                }
                if (isset($dataIp['provider']))
                {
                    $isp = $dataIp['provider'];
                }
                if (isset($dataIp['organisation']))
                {
                    $org = $dataIp['organisation'];
                }
                if (isset($dataIp['proxy']))
                {
                    $is_proxy = $dataIp['proxy'];
                }
                if (isset($dataIp['type']))
                {
                    $connection_type = $dataIp['type'];
                }
            }
        }
        elseif($ipAPI == 3)
        {
            $accessKey = $this->options()->andrewModeratorPanelIPStackAPIKey;
            $apiUrl = "http://api.ipstack.com/" . $ipAddress . "?access_key=" . $accessKey . "&hostname=1";

            $response = $client->post($apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            $responseData = (string)$response->getBody();
            $data = json_decode($responseData, true);

            // Check if the request was successful
            if ($data && isset($data['success']) && $data['success'] === false) {

                return 'API Error: ' . $data['error']['info'];

            } else {

                // Retrieve geolocation data
                if (isset($data['country_name']))
                {
                    $country = $data['country_name'];
                }
                if (isset($data['region_name']))
                {
                    $region = $data['region_name'];
                }
                if (isset($data['city']))
                {
                    $city = $data['city'];
                }
                if (isset($data['zip']))
                {
                    $zip = $data['zip'];
                }
                if (isset($data['latitude']))
                {
                    $latitudeDecimal = $data['latitude'];
                }
                if (isset($data['longitude']))
                {
                    $longitudeDecimal = $data['longitude'];
                }
                if (isset($data['continent_name']))
                {
                    $continent = $data['continent_name'];
                }
                if (isset($data['connection']['isp']))
                {
                    $isp = $data['connection']['isp'];
                }
                if (isset($data['location']['country_flag']))
                {
                    $country_flag = $data['location']['country_flag'];
                }
            }

        }
        elseif($ipAPI == 4)
        {
            $accessKey = $this->options()->andrewModeratorPanelVPNAPIAPIKey;
            $apiUrl = "https://vpnapi.io/api/" . $ipAddress . "?key=" . $accessKey;

            $response = $client->get($apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            $responseData = (string)$response->getBody();
            $data = json_decode($responseData, true);

            // Check if the request was successful
            if ($data && isset($data['success']) && $data['success'] === false) {

                return 'API Error: ' . $data['error']['info'];

            } else {

                // Retrieve geolocation data
                if (isset($data['location']['country']))
                {
                    $country = $data['location']['country'];
                }
                if (isset($data['location']['region']))
                {
                    $region = $data['location']['region'];
                }
                if (isset($data['location']['city']))
                {
                    $city = $data['location']['city'];
                }
                if (isset($data['location']['latitude']))
                {
                    $latitudeDecimal = $data['location']['latitude'];
                }
                if (isset($data['location']['longitude']))
                {
                    $longitudeDecimal = $data['location']['longitude'];
                }
                if (isset($data['location']['continent']))
                {
                    $continent = $data['location']['continent'];
                }
                if (isset($data['network']['autonomous_system_organization']))
                {
                    $isp = $data['network']['autonomous_system_organization'];
                }
                if (isset($data['security']['proxy']))
                {
                    $is_proxy = $data['security']['proxy'];
                }
                if (isset($data['security']['vpn']))
                {
                    $vpn = $data['security']['vpn'];
                }
                if (isset($data['security']['tor']))
                {
                    $is_tor = $data['security']['tor'];
                }
                if (isset($data['security']['relay']))
                {
                    $is_relay = $data['security']['relay'];
                }

            }
        }
        elseif($ipAPI == 5)
        {
            $accessKey = $this->options()->andrewModeratorPanelAbstractAPIAPIKey;
            $apiUrl = "https://ipgeolocation.abstractapi.com/v1?api_key=" . $accessKey . "&ip_address=" . $ipAddress;

            $response = $client->get($apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ]
            ]);

            $responseData = (string)$response->getBody();
            $data = json_decode($responseData, true);

            // Check if the request was successful
            if ($data && isset($data['success']) && $data['success'] === false) {

                return 'API Error: ' . $data['error']['info'];

            } else {

                // Retrieve geolocation data
                if (isset($data['country']))
                {
                    $country = $data['country'];
                }
                if (isset($data['region']))
                {
                    $region = $data['region'];
                }
                if (isset($data['city']))
                {
                    $city = $data['city'];
                }
                if (isset($data['latitude']))
                {
                    $latitudeDecimal = $data['latitude'];
                }
                if (isset($data['longitude']))
                {
                    $longitudeDecimal = $data['longitude'];
                }
                if (isset($data['continent']))
                {
                    $continent = $data['continent'];
                }
                if (isset($data['connection']['isp_name']))
                {
                    $isp = $data['connection']['isp_name'];
                }
                if (isset($data['connection']['organization_name']))
                {
                    $org = $data['connection']['organization_name'];
                }
                if (isset($data['connection']['connection_type']))
                {
                    $connection_type = $data['connection']['connection_type'];
                }
                if (isset($data['security']['is_vpn']))
                {
                    $vpn = $data['security']['is_vpn'];
                }
                if (isset($data['flag']['svg']))
                {
                    $country_flag = $data['flag']['svg'];
                }
            }
        }


        if (isset($data['latitude']) && isset($data['longitude'])) {
            // Convert latitude from decimal to degrees
            $latitudeDegrees = abs($latitudeDecimal);
            $latitudeDegreesInt = floor($latitudeDegrees);
            $latitudeMinutes = number_format(($latitudeDegrees - $latitudeDegreesInt) * 60, 2);
            $latitudeSeconds = number_format(($latitudeMinutes - floor($latitudeMinutes)) * 60, 2);

            // Determine the direction (N or S) for latitude
            $latitudeDirection = ($latitudeDecimal >= 0) ? 'N' : 'S';

            // Convert longitude from decimal to degrees
            $longitudeDegrees = abs($longitudeDecimal);
            $longitudeDegreesInt = floor($longitudeDegrees);
            $longitudeMinutes = number_format(($longitudeDegrees - $longitudeDegreesInt) * 60, 2);
            $longitudeSeconds = number_format(($longitudeMinutes - floor($longitudeMinutes)) * 60, 2);

            // Determine the direction (E or W) for longitude
            $longitudeDirection = ($longitudeDecimal >= 0) ? 'E' : 'W';

            // Print the results or store them in variables
            $latitude = "{$latitudeDegreesInt}° {$latitudeMinutes}' {$latitudeSeconds}\" {$latitudeDirection}";
            $longitude = "{$longitudeDegreesInt}° {$longitudeMinutes}' {$longitudeSeconds}\" {$longitudeDirection}";
        }


        // Set the geolocation data in the parent's viewParams
        $parent->setParams([
            'country' => $country,
            'region' => $region,
            'city' => $city,
            'zip' => $zip,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'latitudeDecimal' => $latitudeDecimal,
            'longitudeDecimal' => $longitudeDecimal,
            'continent' => $continent,
            'isp' => $isp,
            'is_proxy' => $is_proxy,
            'connection_type' => $connection_type,
            'is_tor' => $is_tor,
            'vpn' => $vpn,
            'is_relay' => $is_relay,
            'org' => $org,
            'country_flag' => $country_flag
        ]);

        return $parent;
    }
}