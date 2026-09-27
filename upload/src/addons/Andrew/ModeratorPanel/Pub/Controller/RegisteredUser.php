<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class RegisteredUser extends AbstractController
{

    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewRecentlyRegistered())
        {
            return $this->noPermission();
        }

        $filterInput = $this->filter([
            'username' => 'str',
            'userState' => 'str',
            'userCountry' => 'str',
            'countryExclude' => 'bool',
            'notBanned' => 'bool',
            'isBanned' => 'bool',
            'order' => 'str',
            'sortby' => 'str'
        ]);

        $page = $this->filterPage();
        $perPage = 40;

        $order = $filterInput['order'] ?? 'desc';
        $sortby = $filterInput['sortby'] ?? 'registrationDate';

        $filterInput = $this->getRegisterFilterInput($filterInput);
        $finder = $this->getRegisterFilter($filterInput);
        $finder = $this->getRegisterSort($sortby, $finder, $order);

        $total = $finder->total();
        $finder->limitByPage($page, $perPage);

        $countryRepo = $this->repository('Andrew\ModeratorPanel:Country');
        $countriesList = $countryRepo->getDistinctRegistrationCountries();

        $viewParams = [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'users' => $finder->fetch(),
            'filters' => $filterInput,
            'countriesList' =>  $countriesList,
            'userFilter' => $filterInput['username'],
            'userStateFilter' => $filterInput['userState'],
            'userCountryFilter' => $filterInput['userCountry'],
            'countryExcludeFilter' => $filterInput['countryExclude'],
            'notBannedFilter' => $filterInput['notBanned'],
            'isBannedFilter' => $filterInput['isBanned']
        ];

        return $this->view('Andrew\ModeratorPanel\RecentRegistered:View', 'andrew_moderatorpanel_recentregistered_view',$viewParams);
    }

    protected function getRegisterFilterInput(array $filterInput): array
    {
        $filterInput['countryExclude'] = $filterInput['countryExclude'] ?? true;
        $filterInput['notBanned'] = $filterInput['notBanned'] ?? true;
        $filterInput['isBanned'] = $filterInput['isBanned'] ?? true;
        $filterInput['order'] = $filterInput['order'] ?? 'desc';
        $filterInput['sortby'] = $filterInput['sortby'] ?? 'registrationDate';

        return $filterInput;
    }
    protected function getRegisterFilter($filters)
    {
        $finder = \XF::finder('XF:User');

        if($filters['username'])
        {
            $finder->where('username',$filters['username']);
        }
        if($filters['userState'])
        {
            $finder->where('user_state',$filters['userState']);
        }
        if($filters['userCountry'] && !$filters['countryExclude'])
        {
            $finder->where('andrew_reg_country',$filters['userCountry']);
        }
        if($filters['userCountry'] && $filters['countryExclude'])
        {
            $finder->where('andrew_reg_country','!=',$filters['userCountry']);
            $finder->whereSql('LENGTH(andrew_reg_country) > 1');
        }
        if ($filters['notBanned'] && !$filters['isBanned'])
        {
            $finder->where('is_banned', 0);
        } elseif (!$filters['notBanned'] && $filters['isBanned'])
        {
            $finder->where('is_banned', 1);
        }

        return $finder;
    }
    protected function getRegisterSort($sortby, $finder, $order)
    {
        switch ($sortby) {
            case 'username':
                $finder->order('username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'userState':
                $finder->order('user_state', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'userCountry':
                $finder->order('andrew_reg_country', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'isBanned':
                $finder->order('is_banned', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'registrationDate':
            default:
                $finder->order('register_date', $order == 'asc' ? 'ASC' : 'DESC');
                break;
        }
        return $finder;
    }

}