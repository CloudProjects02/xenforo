<?php
/**
 * XFDev - Lucky Awards
 * Give Users Award Randomly Based on Lucky Awards Configuration
 *
 * Created by @XFDev
 */

namespace XFDev\LuckyAwards\Admin\Controller;


class UserLuckyAward extends \XF\Admin\Controller\AbstractController
{

    /**
     * @return \XF\Mvc\Reply\View
     */
    public function actionUsers()
    {
        $userLuckyAwardRepo = $this->getUserLuckyAwardRepo();

        $this->setSectionContext('xfdev_lucky_awards_users');

        $page = $this->filterPage();
        $perPage = 20;

        $userLuckyAwardFinder = $userLuckyAwardRepo->findUserLuckyAwardsForList()
            ->with('LuckyAward',true)
            ->with('User',true)
            ->with('Post')
            ->with('Post.Thread')
            ->order('date_received','desc')
            ->limitByPage($page,$perPage);

        $userLuckyAwards = $userLuckyAwardFinder->fetch();

        $viewParams = [
            'userLuckyAwardsTotal'  =>  $userLuckyAwards->count(),
            'userLuckyAwards'   =>  $userLuckyAwards,
            'page'              =>  $page,
            'perPage'           =>  $perPage,
            'total'             =>  $userLuckyAwardFinder->total()
        ];

        return $this->view('XFDev\LuckyAwards:UserLuckyAward','xfdev_lucky_awards_users_list',$viewParams);

    }

    /**
     * @return \XFDev\LuckyAwards\Repository\UserLuckyAward
     */
    public function getUserLuckyAwardRepo()
    {
        return $this->repository('XFDev\LuckyAwards:UserLuckyAward');
    }
}