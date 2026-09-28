<?php

namespace AddonsLab\Core\Xf1;

use AddonsLab\Core\Service\ThreadCopyProviderInterface;

class ThreadCopyProvider implements ThreadCopyProviderInterface
{
    public function copyThread($threadId, $forumId, $newMessage = '')
    {
        /** @var \XenForo_Model_Forum $forumModel */
        $forumModel = \XenForo_Model::create('XenForo_Model_Forum');

        /** @var \XenForo_Model_Thread $threadModel */
        $threadModel = \XenForo_Model::create('XenForo_Model_Thread');

        /** @var \XenForo_Model_ThreadPrefix $prefixModel */
        $prefixModel = \XenForo_Model::create('XenForo_Model_ThreadPrefix');

        /** @var \XenForo_Model_ThreadWatch $threadWatchModel */
        $threadWatchModel = \XenForo_Model::create('XenForo_Model_ThreadWatch');

        /** @var \XenForo_Model_Tag $tagModel */
        $tagModel = \XenForo_Model::create('XenForo_Model_Tag');

        $forum = $forumModel->getForumById($forumId);
        
        if(!$forum) {
            // might be a category ID or any other node where is not possible to post
            return false;
        }

        $sourceThread = \XenForo_Application::getDb()->fetchRow('
            SELECT thread.*, post.message, post.attach_count
            FROM xf_thread AS thread
            INNER JOIN xf_post AS post ON post.post_id=thread.first_post_id
            WHERE thread.thread_id=?
        ', array($threadId));

        if ($newMessage !== '') {
            $sourceThread['message'] = $newMessage;
            
        }

        if (!$prefixModel->verifyPrefixIsUsable($sourceThread['prefix_id'], $forumId)) {
            $sourceThread['prefix_id'] = 0; // not usable, just blank it out
        }

        /** @var \XenForo_DataWriter_Discussion_Thread $writer */
        $writer = \XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
        $writer->bulkSet(array(
            'user_id' => $sourceThread['user_id'],
            'username' => $sourceThread['username'],
            'title' => $sourceThread['title'],
            'prefix_id' => $sourceThread['prefix_id'],
            'node_id' => $forumId,
            'discussion_state' => $sourceThread['discussion_state'],
            'discussion_open' => $sourceThread['discussion_open'],
            'sticky' => $sourceThread['sticky'],
        ));

        $postWriter = $writer->getFirstMessageDw();
        $postWriter->set('message', $sourceThread['message']);
        $postWriter->setExtraData(\XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
        $postWriter->set('attach_count', $sourceThread['attach_count']);

        $tags = unserialize($sourceThread['tags']);

        $writer->preSave();

        if ($writer->hasErrors()) {
            \XenForo_Error::logError('Failed to duplicate the thread. ' . implode("\n", $writer->getErrors()));
            return false;
        }

        $writer->save();

        $thread = $writer->getMergedData();

        $thread['message'] = $postWriter->get('message');
        $thread['attach_count'] = $postWriter->get('attach_count');

        if (!empty($tags)) {
            $tagModel->adjustContentTags('thread', $thread['thread_id'], array_keys($tags), array(), $thread['user_id']);
        }

        $watch = $threadWatchModel->getUserThreadWatchByThreadId($sourceThread['user_id'], $sourceThread['thread_id']);

        if ($watch) {
            $dw = \XenForo_DataWriter::create('XenForo_DataWriter_ThreadWatch');
            $dw->set('user_id', $thread['user_id']);
            $dw->set('thread_id', $thread['thread_id']);
            $dw->set('email_subscribe', $watch['email_subscribe']);
            $dw->save();
        }

        $threadModel->markThreadRead($thread, $forum, \XenForo_Application::$time);

        // copy the attachments
        $this->duplicatePostAttachments($sourceThread['first_post_id'], $thread['first_post_id']);

        return $thread;
    }

    public function duplicatePostAttachments($sourcePostId, $targetPostId)
    {
        $db = \XenForo_Application::getDb();
        $sourceAttachments = $db->fetchAll('
            SELECT * FROM xf_attachment
            WHERE content_type=?
            AND content_id=?
        ', array('post', $sourcePostId));

        foreach ($sourceAttachments AS $sourceAttachment) {
            unset($sourceAttachment['attachment_id']);
            $sourceAttachment['content_id'] = $targetPostId;

            $db->insert('xf_attachment', $sourceAttachment);
            $db->query('
                UPDATE xf_attachment_data
                SET attach_count=attach_count+1
                WHERE data_id=?
            ', $sourceAttachment['data_id']);
        }

        return true;
    }
}