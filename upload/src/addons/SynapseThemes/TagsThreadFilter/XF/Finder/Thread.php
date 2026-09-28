<?php

namespace SynapseThemes\TagsThreadFilter\XF\Finder;

class Thread extends XFCP_Thread
{
    /**
     * Filters the finder to threads that have a specific tag.
     *
     * @param int|array $tagId Tag ID or array of Tag IDs
     * @return $this
     */
    public function whereTag($tagId)
    {
        if (!is_array($tagId))
        {
            $tagId = [$tagId];
        }
        $tagId = array_map('intval', $tagId);
        $tagId = array_filter($tagId);

        if (!$tagId)
        {
            // Prevent invalid SQL
            $this->where('1=0');
            return $this;
        }

        $inClause = $this->quote($tagId);

        $this->whereSql(
            'EXISTS (
                SELECT 1 FROM xf_tag_content AS tag_content
                WHERE tag_content.content_id = xf_thread.thread_id
                  AND tag_content.content_type = \'thread\'
                  AND tag_content.tag_id IN (' . $inClause . ')
            )'
        );

        return $this;
    }
}