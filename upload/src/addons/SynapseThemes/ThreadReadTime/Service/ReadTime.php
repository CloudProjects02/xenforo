<?php

namespace SynapseThemes\ThreadReadTime\Service;

use XF\Entity\Thread;

class ReadTime
{
    /**
     * Average words per minute for reading
     */
    const WORDS_PER_MINUTE = 200;

    public function calculateForThread(Thread $thread)
    {
        $firstPost = $thread->FirstPost;
        if (!$firstPost)
        {
            return 0;
        }

        $content = $thread->title . ' ' . $firstPost->message;
        
        // Strip BBCode
        $content = preg_replace('/\[.*?\]/', '', $content);
        
        // Strip HTML
        $content = strip_tags($content);
        
        // Count words
        $wordCount = str_word_count($content);
        
        // Calculate minutes (rounded up to nearest minute)
        $minutes = ceil($wordCount / self::WORDS_PER_MINUTE);
        
        return max(1, $minutes);
    }
} 