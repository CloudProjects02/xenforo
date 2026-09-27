<?php

namespace Andrew\ModeratorPanel\Pub\Controller;

use XF\Pub\Controller\AbstractController;

class RecentLogin extends AbstractController
{

    public function actionIndex()
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewModeratorPanel() || !$visitor->canViewRecentLoginsMP())
        {
            return $this->noPermission();
        }

        $page = $this->filterPage();
        $perPage = 40;

        $filterInput = $this->filter([
            'username' => 'str',
            'loginCountry' => 'str',
            'loginCountryExclude' => 'bool',
            'registrationCountry' => 'str',
            'registrationCountryExclude' => 'bool',
            'order' => 'str',
            'sortby' => 'str'
        ]);

        $username = $filterInput['username'] ?? null;
        $loginCountry = $filterInput['loginCountry'] ?? null;
        $loginCountryExclude = $filterInput['loginCountryExclude'] ?? null;
        $registrationCountry = $filterInput['registrationCountry'] ?? null;
        $registrationCountryExclude = $filterInput['registrationCountryExclude'] ?? null;
        $order = $filterInput['order'] ?? 'desc';
        $sortBy = $filterInput['sortby'] ?? 'date';

        $finder = $this->fetchRecentLoginsFilters($filterInput);
        $finder = $this->sortRecentLogins($sortBy, $finder, $order);

        $total = $finder->total();
        $finder->limitByPage($page, $perPage);

        $countryRepo = $this->repository('Andrew\ModeratorPanel:Country');
        $loginCountriesList = $countryRepo->getDistinctLoginCountries();
        $registrationCountriesList = $countryRepo->getDistinctRegistrationCountries();

        $viewParams = [
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'entries' => $finder->fetch(),
            'filters' => $filterInput,
            'loginCountriesList' => $loginCountriesList,
            'registrationCountriesList' => $registrationCountriesList,
            'userFilter' => $username,
            'loginCountryFilter' => $loginCountry,
            'loginCountryExcludeFilter' => $loginCountryExclude,
            'registrationCountryFilter' => $registrationCountry,
            'registrationCountryExcludeFilter' => $registrationCountryExclude
        ];

        return $this->view('Andrew\ModeratorPanel\RecentLogin:View', 'andrew_moderatorpanel_recent_login_view', $viewParams);
    }

    protected function fetchRecentLoginsFilters($filters)
    {
        $finder = \XF::finder('Andrew\ModeratorPanel:RecentLogin')
            ->with('User', true);

        if ($filters['username'])
        {
            $finder->where('User.username', $filters['username']);
        }

        if ($filters['loginCountry'] && !$filters['loginCountryExclude'])
        {
            $finder->where('country', $filters['loginCountry']);
        }

        if ($filters['loginCountry'] && $filters['loginCountryExclude'])
        {
            $finder->where('country', '<>', $filters['loginCountry']);
            $finder->where('country', '<>', '-');
            $finder->where('country', '<>', null);
        }

        if ($filters['registrationCountry'] && !$filters['registrationCountryExclude'])
        {
            $finder->where('User.andrew_reg_country', $filters['registrationCountry']);
        }

        if ($filters['registrationCountry'] && $filters['registrationCountryExclude'])
        {
            $finder->where('User.andrew_reg_country', '<>', $filters['registrationCountry']);
            $finder->where('User.andrew_reg_country', '<>', '-');
            $finder->where('User.andrew_reg_country', '<>', null);
        }

        return $finder;
    }

    protected function sortRecentLogins($sortBy, $finder, $order)
    {
        switch ($sortBy) {
            case 'username':
                $finder->order('User.username', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'country':
                $finder->order('country', $order == 'asc' ? 'ASC' : 'DESC');
                break;
            case 'login_date':
            default:
                $finder->order('login_date', $order == 'asc' ? 'ASC' : 'DESC');
                break;
        }

        return $finder;
    }

}