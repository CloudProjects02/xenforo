<?php

namespace XFES\XF\Pub\Controller;

use XF\Entity\Thread;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

use function count;

class Forum extends XFCP_Forum
{
	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 */
	public function actionFindSuggestedThreads(ParameterBag $params)
	{
		$options = $this->options();
		$xfesEnabled = $options->xfesEnabled;
		$similarThreadOptions = $options->xfesSimilarThreads;
		if (!$xfesEnabled || !$similarThreadOptions['suggestionsEnabled'])
		{
			return $this->noPermission();
		}

		$forum = $this->assertViewableForum(
			$params->node_id ?: $params->node_name
		);
		if (!$forum->canCreateThread($error) && !$forum->canCreateThreadPreReg())
		{
			return $this->noPermission($error);
		}

		$title = $this->filter('title', 'str');
		if (!$title)
		{
			return $this->error(\XF::phrase('please_enter_valid_title'));
		}

		$thread = $forum->getNewThread();
		$thread->title = $title;
		$thread->user_id = \XF::visitor()->user_id;
		$thread->post_date = \XF::$time;
		$thread->discussion_type = $forum->TypeHandler->getDefaultThreadType($forum);

		/** @var \XFES\XF\Repository\Thread $threadRepo */
		$threadRepo = $this->repository('XF:Thread');
		$suggestedThreadIds = $threadRepo->getSimilarThreadIds(
			$thread,
			$similarThreadOptions['maxResults']
		);

		$suggestedThreads = $this->em()->findByIds(
			'XF:Thread',
			$suggestedThreadIds,
			[
				'Forum',
				'FirstPost',
				'User',
				'Forum.Node.Permissions|' . \XF::visitor()->permission_combination_id,
			]
		);
		$suggestedThreads = $suggestedThreads->filter(function (Thread $thread)
		{
			return $thread->canView() && !$thread->isIgnored();
		});

		$viewParams = [
			'forum' => $forum,
			'thread' => $thread,
			'suggestedThreads' => $suggestedThreads,
		];
		$view = $this->view(
			'XF:Forum\FindSuggestedThreads',
			'xfes_forum_find_suggested_threads',
			$viewParams
		);
		$view->setJsonParams([
			'title' => $title,
			'resultCount' => count($suggestedThreads),
		]);
		return $view;
	}
}
