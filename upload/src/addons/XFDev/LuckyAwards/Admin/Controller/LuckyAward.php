<?php
/**
 * XFDev - Lucky Awards
 * Give Users Award Randomly Based on Lucky Awards Configuration
 *
 * Created by @XFDev
 */

namespace XFDev\LuckyAwards\Admin\Controller;


use AddonFlare\AwardSystem\Repository\Award;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\PrintableException;

class LuckyAward extends AbstractController
{
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('af_as');
    }

    /**
     * List of all Lucky awards
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionManage()
    {
        $this->setSectionContext('xfdev_lucky_awards_manage');

        $page = $this->filterPage();
        $perPage = 20;

        $luckyAwardsRepo = $this->getLuckyAwardRepo()
            ->getLuckyAwardsList();

        $luckyAwardsData = $luckyAwardsRepo->limitByPage($page, $perPage)->fetch();

        $viewParams = [
            'luckyAwardsData' => $luckyAwardsData,
            'count' => $luckyAwardsData->count(),
            'page' => $page,
            'perPage' => $perPage,
            'total' => $luckyAwardsRepo->total()
        ];

        return $this->view('XFDev\LuckyAwards:Manage', 'xfdev_lucky_awards_manage', $viewParams);
    }

    /**
     * Manages add and edit functionality of Lucky Awards
     *
     * @param \XFDev\LuckyAwards\Entity\LuckyAward $luckyAward
     * @return \XF\Mvc\Reply\View
     */
    public function luckyAwardAddEdit(\XFDev\LuckyAwards\Entity\LuckyAward $luckyAward)
    {
        $this->setSectionContext('xfdev_lucky_awards_manage');

        $awards = $this->getAwardRepo()->findAwardsForList()
            ->fetch()
            ->pluckNamed('title', 'award_id');

        $existingLuckyAwards = \XF::finder('XFDev\LuckyAwards:LuckyAward')
            ->with('Award')
            ->fetch()
            ->pluckNamed('lucky_award_title', 'lucky_award_id');

        if ($existingLuckyAwards && $luckyAward->lucky_award_id) {
            unset($existingLuckyAwards[$luckyAward->lucky_award_id]);
        }

        $viewParams = [
            'awards' => $awards,
            'existingLuckyAwards' => $existingLuckyAwards,
            'luckyAward' => $luckyAward
        ];

        return $this->view('', 'xfdev_lucky_awards_add', $viewParams);
    }

    /**
     * Loads Add View for Lucky Awards
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionAdd()
    {
        /** @var \XFDev\LuckyAwards\Entity\LuckyAward $luckyAward */
        $luckyAward = \XF::em()->create('XFDev\LuckyAwards:LuckyAward');

        return $this->luckyAwardAddEdit($luckyAward);
    }

    /**
     * Loads Edit view for lucky awards
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionEdit(ParameterBag $params)
    {
        $luckyAward = $this->assertLuckyAwardExists($params['lucky_award_id']);
        return $this->luckyAwardAddEdit($luckyAward);
    }

    /**
     * It is called by the form to save/update the lucky award
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect
     * @throws PrintableException
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionSave(ParameterBag $params)
    {
        $this->assertPostOnly();

        if ($luckyAwardId = $params['lucky_award_id']) {
            $luckyAward = $this->assertLuckyAwardExists($luckyAwardId);
        } else {
            $luckyAward = $this->em()->create('XFDev\LuckyAwards:LuckyAward');
        }

        /* Calls the Save Process for Lucky Award */
        $this->luckyAwardSaveProcess($luckyAward)->run();

        return $this->redirect($this->buildLink('lucky-awards/manage'));

    }

    /**
     * Saves or Updates the Lucky Awards
     *
     * @param \XFDev\LuckyAwards\Entity\LuckyAward $luckyAward
     * @return \XF\Mvc\FormAction
     */
    public function luckyAwardSaveProcess(\XFDev\LuckyAwards\Entity\LuckyAward $luckyAward)
    {
        $entityInput = $this->filter([
            'lucky_award_active' => 'bool',
            'lucky_award_title' => 'str',
            'award_id' => 'uint',
            'lucky_award_chances' => 'uint',
            'lucky_award_dependent' => 'array',
            'lucky_award_reason' => 'str'
        ]);

        $form = $this->formAction();

        $form->basicEntitySave($luckyAward, $entityInput);

        return $form;
    }

    /**
     * Handles deletion of Lucky Awards
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws PrintableException
     */
    public function actionDelete(ParameterBag $params)
    {
        $luckyAward = $this->assertLuckyAwardExists($params->lucky_award_id);

        if ($this->isPost()) {
            $db = \XF::db();
            $db->beginTransaction();

            $db->delete('xfdev_users_lucky_award', 'lucky_award_id = ?', $luckyAward->lucky_award_id);
            $luckyAward->delete();

            $db->commit();

            return $this->redirect($this->buildLink('lucky-awards/manage'));
        } else {
            $viewParams = [
                'luckyAward' => $luckyAward
            ];

            return $this->view('XFDev\LuckyAwards:LuckyAward\Delete', 'xfdev_lucky_awards_delete', $viewParams);
        }
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return \XF\Mvc\Entity\Entity
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertAwardExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('AddonFlare\AwardSystem:Award', $id, $with, $phraseKey);
    }

    /**
     * @return \AddonFlare\AwardSystem\Repository\Award
     */
    protected function getAwardRepo()
    {
        return $this->repository('AddonFlare\AwardSystem:Award');
    }

    /**
     * @param string $id
     * @param array|string|null $with
     * @param null|string $phraseKey
     *
     * @return \XFDev\LuckyAwards\Entity\LuckyAward
     */
    private function assertLuckyAwardExists($id, $with = null, $phraseKey = 'xfdev_invalid_lucky_awards_specified')
    {
        return $this->assertRecordExists('XFDev\LuckyAwards:LuckyAward', $id, $with, $phraseKey);
    }

    /**
     * @return \XFDev\LuckyAwards\Repository\LuckyAward
     */
    private function getLuckyAwardRepo()
    {
        return $this->repository('XFDev\LuckyAwards:LuckyAward');
    }

    public function actionUsers()
    {
        return $this->rerouteController(UserLuckyAward::class, 'users');
    }

    public function actionTest()
    {
        $excludedForums = \XF::options()->xfdev_lucky_awards_exclude_forums;

        \XF::dump($excludedForums);
    }
}
