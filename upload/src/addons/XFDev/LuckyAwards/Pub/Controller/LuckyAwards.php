<?php
/**
 * XFDev - Lucky Awards
 * Give Users Award Randomly Based on Lucky Awards Configuration
 *
 * Created by @XFDev
 */

namespace XFDev\LuckyAwards\Pub\Controller;

use XF\Mvc\ParameterBag;

class LuckyAwards extends \XF\Pub\Controller\AbstractController
{

    public function actionIndex(ParameterBag $params)
    {
        return $this->rerouteController(__CLASS__,'list');
    }

    public function actionList()
    {
        $luckyAwardsData = $this->getLuckyAwardRepo()
            ->getLuckyAwardsList()->fetch();

        $luckyAwardsCount = $luckyAwardsData->count();

        $viewParams = [
            'luckyAwardsData'=> $luckyAwardsData,
            'luckyAwardsCount'=> $luckyAwardsCount
        ];

        return $this->view('XFDev\LuckyAwards:LuckyAward','xfdev_lucky_awards_list',$viewParams);
    }

    /**
     * @return \XFDev\LuckyAwards\Repository\LuckyAward
     */
    private function getLuckyAwardRepo()
    {
        return $this->repository('XFDev\LuckyAwards:LuckyAward');
    }

    public static function getActivityDetails(array $activities)
    {
        return \XF::phrase('Viewing Lucky Awards');
    }

}