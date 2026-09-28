<?php

namespace SynapseThemes\ThreadReadTime\XF\Entity;

class Thread extends XFCP_Thread
{
    protected function _postSave()
    {
        parent::_postSave();
        
        // Only update read time on updates, not inserts
        // For inserts, let the Post's postSave listener handle it after FirstPost is saved
        if ($this->isUpdate() && $this->isChanged(['title', 'FirstPost.message']))
        {
            $this->updateReadTime();
        }
    }

    public function updateReadTime()
    {
        /** @var \SynapseThemes\ThreadReadTime\Service\ReadTime $readTimeService */
        $readTimeService = $this->app()->service('SynapseThemes\ThreadReadTime:ReadTime');
        $readTime = $readTimeService->calculateForThread($this);

        $readTime = intval($readTime);
        
        \XF::db()->update(
            'xf_thread',
            ['synapse_read_time' => $readTime],
            'thread_id = ?',
            $this->thread_id
        );

        $this->set('synapse_read_time', $readTime, ['forceSet' => true]);
        $this->clearCache('FirstPost');
    }

    public function getSynapseReadTimeFormatted()
    {
        return intval($this->synapse_read_time);
    }

    public function getSynapseReadTime()
    {
        return intval($this->getValue('synapse_read_time'));
    }

    public function getReadTime()
    {
        return strval($this->synapse_read_time);
    }

    public function getReadTimeMinutes()
    {
        $value = $this->synapse_read_time;
        \XF::logError('Getting Read Time Minutes for Thread ' . $this->thread_id . ': ' . $value);
        return max(1, intval($value));
    }
} 