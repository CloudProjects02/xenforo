<?php

namespace Andrew\ModeratorPanel\XF\Repository;

use XF\Mvc\Entity\Finder;
use XF\Mvc\Entity\Repository;

class User extends XFCP_User
{
    public function warningPoints($userId)
    {
        $db = \XF::db();
        $warning_points = $db->fetchRow('select xf_user.user_id, xf_user.username, xf_user.message_count, count(xf_warning.warning_id) as warning_count, xf_user.warning_points as active_points, sum(xf_warning.points) total_points
                                    from xf_user
                                    inner join xf_warning on xf_user.user_id = xf_warning.user_id
                                    where xf_user.user_id = ?
                                    group by xf_user.user_id, xf_user.username, xf_user.message_count, xf_user.warning_points
                                    having sum(xf_warning.warning_id) > 0
                                    order by warning_count desc',$userId);

        return $warning_points;
    }
    public function reportCount($userId)
    {
        $db = \XF::db();
        $report_count = $db->fetchRow("select xf_user.user_id, xf_user.username, xf_user.message_count, xf_user.warning_points, sum(xf_report.report_count) as report_count
                                    from xf_user
                                    inner join xf_report on xf_user.user_id = xf_report.content_user_id
                                    where xf_user.user_id = ?
                                    group by xf_user.user_id, xf_user.username, xf_user.message_count, xf_user.warning_points
                                    having sum(xf_report.report_id) > 0
                                    order by report_count DESC",$userId);

        return $report_count;
    }

    public function getIpCountry($ipAddress)
    {
        $ipAPI = $this->app()->options()->andrewModeratorPanelIPService;

        // Ensure an API is selected and the server is running HTTPS
        if ($ipAPI == 0 || !isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] !== 'on') {
            return false;
        }

        if ($ipAddress == "") {
            return false;
        }

        $client = \XF::app()->http()->client();
        $country = null;

        if ($ipAPI == 1) {
            $apiUrl = "https://freeipapi.com/api/json/" . $ipAddress;
        } elseif ($ipAPI == 2) {
            $accessKey = \XF::app()->options()->andrewModeratorPanelProxyCheckAPIKey;
            $apiUrl = "http://proxycheck.io/v2/" . $ipAddress . "?key=" . $accessKey . "&vpn=1&asn=1";
        } elseif ($ipAPI == 3) {
            $accessKey = \XF::app()->options()->andrewModeratorPanelIPStackAPIKey;
            $apiUrl = "http://api.ipstack.com/" . $ipAddress . "?access_key=" . $accessKey . "&hostname=1";
        } elseif ($ipAPI == 4) {
            $accessKey = \XF::app()->options()->andrewModeratorPanelVPNAPIAPIKey;
            $apiUrl = "https://vpnapi.io/api/" . $ipAddress . "?key=" . $accessKey;
        } elseif ($ipAPI == 5) {
            $accessKey = \XF::app()->options()->andrewModeratorPanelAbstractAPIAPIKey;
            $apiUrl = "https://ipgeolocation.abstractapi.com/v1?api_key=" . $accessKey . "&ip_address=" . $ipAddress;
        }

        $response = $client->get($apiUrl, [
            'headers' => ['Content-Type' => 'application/json']
        ]);

        $responseData = (string) $response->getBody();
        $data = json_decode($responseData, true);

        if ($data && isset($data['success']) && $data['success'] === false) {
            return 'API Error: ' . $data['error']['info'];
        } else {
            if ($ipAPI == 1 && isset($data['countryName'])) {
                $country = $data['countryName'];
            } elseif ($ipAPI == 2) {
                foreach ($data as $key => $value) {
                    if (filter_var($key, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        $ipAddress = $key;
                        break;
                    }
                }
                if ($ipAddress !== null && isset($data[$ipAddress]['country'])) {
                    $country = $data[$ipAddress]['country'];
                }
            } elseif ($ipAPI == 3 && isset($data['country_name'])) {
                $country = $data['country_name'];
            } elseif ($ipAPI == 4 && isset($data['location']['country'])) {
                $country = $data['location']['country'];
            } elseif ($ipAPI == 5 && isset($data['country'])) {
                $country = $data['country'];
            }
        }

        return $country;
    }
}