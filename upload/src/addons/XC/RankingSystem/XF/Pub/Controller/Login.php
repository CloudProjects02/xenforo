<?php

namespace XC\RankingSystem\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Login extends XFCP_Login
{

    public function actionLogin(ParameterBag $params)
    {

        $parent = parent::actionLogin($params);

        return $parent;



    }


}