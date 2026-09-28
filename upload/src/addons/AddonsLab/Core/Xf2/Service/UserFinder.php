<?php

namespace AddonsLab\Core\Xf2\Service;
class UserFinder
{
    public function applyUsergroupFilter(\XF\Mvc\Entity\Finder $finder, array $usergroups)
    {
        $finder->with('User');
        $condition = new \XF\Mvc\Entity\FinderExpression('(
            %s IN(' . implode(',', $usergroups) . ')
            OR %s REGEXP \'(^|,)(' . implode('|', $usergroups) . ')($|,)\'
        )', [
            'User.user_group_id',
            'User.secondary_group_ids',
        ]);
        $finder->where($condition);
    }
}
