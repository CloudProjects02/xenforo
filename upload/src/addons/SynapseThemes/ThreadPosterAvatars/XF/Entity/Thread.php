<?php

namespace SynapseThemes\ThreadPosterAvatars\XF\Entity;

class Thread extends XFCP_Thread
{
    protected static $topPostersCache = [];

    /**
     * @return \XF\Entity\User[]
     */
    public function getTopPosters()
    {
        $threadId = $this->thread_id;
        
        if (!isset(self::$topPostersCache[$threadId]))
        {
            /** @var \SynapseThemes\ThreadPosterAvatars\Service\Thread\TopPosters $service */
            $service = $this->app()->service('SynapseThemes\ThreadPosterAvatars:Thread\TopPosters');
            
            self::$topPostersCache[$threadId] = $service->getTopPosters($this, 3);
        }

        return self::$topPostersCache[$threadId];
    }

    /**
     * Structure definition for thread entity
     *
     * @return array
     */
    public static function getStructure(\XF\Mvc\Entity\Structure $structure)
    {
        $structure = parent::getStructure($structure);

        $structure->getters['TopPosters'] = true;

        return $structure;
    }
} 