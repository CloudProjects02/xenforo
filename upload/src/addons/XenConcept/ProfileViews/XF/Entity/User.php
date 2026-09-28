<?php
/**
 * Created by PhpStorm.
 * User: Remi
 * Date: 01/06/2020
 * Time: 19:19
 */

namespace XenConcept\ProfileViews\XF\Entity;

use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int xc_pv_profile_view_count
 */
class User extends XFCP_User
{
    public function canViewMostProfilesViewed(&$error = null)
    {
        $visitor = \XF::visitor();
        return $visitor->hasPermission('xc_profile_views', 'viewMostProfilesViewed');
    }

    public function canViewUsersViewedProfile(&$error = null)
    {
        $visitor = \XF::visitor();

        if ($visitor->user_id == $this->user_id)
        {
            return true;
        }

        if (!$this->isPrivacyCheckMet('allow_view_users_who_viewed_profile', $visitor))
        {
            return false;
        }

        return true;
    }

    public function getNewProfileView()
    {
        /** @var \XenConcept\ProfileViews\Entity\ProfileViews $profileView */
        $profileView = $this->_em->create('XenConcept\ProfileViews:ProfileViews');
        $profileView->profile_user_id = $this->user_id;

        return $profileView;
    }

    protected function _postDelete()
    {
        parent::_postDelete();

        $this->db()->delete('xf_xc_profile_views', 'profile_user_id = ?', $this->user_id);
    }

    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->columns['xc_pv_profile_view_count'] = ['type' => self::UINT, 'forced' => true, 'default' => 0, 'api' => true];

        return $structure;
    }
}