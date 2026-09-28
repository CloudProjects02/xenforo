<?php

namespace CloudCheats\ProfileLayout\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Scammer extends AbstractController
{
    public function actionMark(ParameterBag $params)
    {
        $this->assertPostOnly();

        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('general', 'manageAnyUser') &&
            !$visitor->hasPermission('general', 'warn'))
        {
            return $this->noPermission();
        }

        $userId  = $this->filter('user_id', 'uint');
        $action  = $this->filter('action', 'str'); // 'mark' or 'unmark'

        /** @var \XF\Entity\User $user */
        $user = $this->em()->find(\XF\Entity\User::class, $userId);
        if (!$user || $user->user_id === $visitor->user_id)
        {
            return $this->error(\XF::phrase('requested_user_not_found'));
        }

        $groupId = (int)\XF::options()->ccScammerGroupId;
        if (!$groupId)
        {
            return $this->error('Scammer usergroup not configured. Ask an admin to set ccScammerGroupId in Admin CP > Options > CloudCheats: Profile Layout.');
        }

        $secondary = $user->secondary_group_ids ?: [];
        $isScammer = in_array($groupId, $secondary, true);

        if ($action === 'unmark')
        {
            if ($isScammer)
            {
                $user->secondary_group_ids = array_values(array_filter($secondary, fn($id) => $id !== $groupId));
                $user->save();
            }
            $msg = "Scammer flag removed from {$user->username}.";
        }
        else
        {
            if (!$isScammer)
            {
                $secondary[]               = $groupId;
                $user->secondary_group_ids = array_values($secondary);
                $user->save();
            }
            $msg = "{$user->username} has been marked as scammer.";
        }

        if ($this->request->isXhr())
        {
            return $this->message($msg);
        }

        return $this->redirect($this->buildLink('members', $user), $msg);
    }

    protected function assertPostOnly(): void
    {
        if (!$this->request->isPost())
        {
            throw $this->exception($this->error(\XF::phrase('action_not_permitted'), 403));
        }
    }
}
