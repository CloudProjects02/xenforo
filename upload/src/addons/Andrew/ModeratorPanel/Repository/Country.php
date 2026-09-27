<?php

namespace Andrew\ModeratorPanel\Repository;

use XF\Mvc\Entity\Repository;

class Country extends Repository
{
    public function getDistinctRegistrationCountries(): array
    {
        $db = \XF::db();
        return $db->fetchAllColumn('
            SELECT DISTINCT andrew_reg_country
            FROM xf_user
            WHERE andrew_reg_country != "" AND LENGTH(andrew_reg_country) > 1
            ORDER BY andrew_reg_country ASC
        ');
    }

    public function getDistinctLoginCountries(): array
    {
        $db = \XF::db();
        return $db->fetchAllColumn('
            SELECT DISTINCT country
            FROM xf_andrew_mp_recent_login
            WHERE country != "" AND LENGTH(country) > 1
            ORDER BY country ASC
        ');
    }
}