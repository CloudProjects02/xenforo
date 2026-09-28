<?php

namespace DBTech\Shop\Pub\Controller;

use XF\Pub\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Class TradePost
 *
 * @package DBTech\Shop\Pub\Controller
 */
class TradePost extends AbstractController
{
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Reroute
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(ParameterBag $params)
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		if ($this->filter('_xfWithData', 'bool'))
		{
			$this->request->set('_xfDisableInlineMod', true);
			return $this->rerouteController(__CLASS__, 'show', $params);
		}

		$tradePostRepo = $this->getTradePostRepo();

		$tradePostFinder = $tradePostRepo->findTradePostsInTrade($tradePost->Trade);
		$tradePosts = $tradePostFinder->where('post_date', '>', $tradePost->post_date)->fetch();

		$page = floor($tradePosts->count() / $this->options()->messagesPerPage) + 1;

		return $this->redirectPermanently(
			$this->buildLink('dbtech-shop/trades', $tradePost->Trade, ['page' => $page]) . '#trade-post-' . $tradePost->trade_post_id
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionShow(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		$tradePostRepo = $this->getTradePostRepo();
		$tradePost = $tradePostRepo->addCommentsToTradePost($tradePost);

		$viewParams = [
			'tradePost' => $tradePost,
			'showTargetUser' => true,
			'canInlineMod' => $tradePost->canUseInlineModeration(),
			'allowInlineMod' => !$this->request->get('_xfDisableInlineMod')
		];
		return $this->view('DBTech\Shop:TradePost\Show', 'dbtech_shop_trade_post', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params)
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);
		if (!$tradePost->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$noInlineMod = $this->filter('_xfNoInlineMod', 'bool');

		if ($this->isPost())
		{
			$editor = $this->setupEdit($tradePost);
			$editor->checkForSpam();

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}
			$editor->save();

			$this->finalizeEdit($editor);

			if ($this->filter('_xfWithData', 'bool') && $this->filter('_xfInlineEdit', 'bool'))
			{
				$viewParams = [
					'tradePost' => $tradePost,

					'noInlineMod' => $noInlineMod
				];
				$reply = $this->view('DBTech\Shop:TradePost\EditNewTradePost', 'dbtech_shop_trade_post_edit_new_post', $viewParams);
				$reply->setJsonParam('message', \XF::phrase('your_changes_have_been_saved'));
				return $reply;
			}
			else
			{
				return $this->redirect($this->buildLink('dbtech-shop/trade-posts', $tradePost));
			}
		}
		else
		{
			$viewParams = [
				'tradePost' => $tradePost,
				'trade' => $tradePost->Trade,

				'quickEdit' => $this->filter('_xfWithData', 'bool'),
				'noInlineMod' => $noInlineMod
			];
			return $this->view('DBTech\Shop:TradePost\Edit', 'dbtech_shop_trade_post_edit', $viewParams);
		}
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionDelete(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);
		if (!$tradePost->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$tradePost->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			/** @var \DBTech\Shop\Service\TradePost\Deleter $deleter */
			$deleter = $this->service('DBTech\Shop:TradePost\Deleter', $tradePost);

			if ($this->filter('author_alert', 'bool') && $tradePost->canSendModeratorActionAlert())
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			$this->plugin('XF:InlineMod')->clearIdFromCookie('dbtech_shop_trade_post', $tradePost->trade_post_id);

			return $this->redirect(
				$this->getDynamicRedirect($this->buildLink('dbtech-shop/trades', $tradePost->Trade), false)
			);
		}
		else
		{
			$viewParams = [
				'tradePost' => $tradePost,
				'trade' => $tradePost->Trade
			];
			return $this->view('DBTech\Shop:TradePost\Delete', 'dbtech_shop_trade_post_delete', $viewParams);
		}
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionUndelete(ParameterBag $params)
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		/** @var \XF\ControllerPlugin\Undelete $plugin */
		$plugin = $this->plugin('XF:Undelete');
		return $plugin->actionUndelete(
			$tradePost,
			$this->buildLink('dbtech-shop/trade-posts/undelete', $tradePost),
			$this->buildLink('dbtech-shop/trade-posts', $tradePost),
			\XF::phrase('dbtech_shop_trade_post_by_x', ['name' => $tradePost->username]),
			'message_state'
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIp(ParameterBag $params)
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);
		$breadcrumbs = $this->getTradePostBreadcrumbs($tradePost);

		/** @var \XF\ControllerPlugin\Ip $ipPlugin */
		$ipPlugin = $this->plugin('XF:Ip');
		return $ipPlugin->actionIp($tradePost, $breadcrumbs);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReport(ParameterBag $params)
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);
		if (!$tradePost->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XF\ControllerPlugin\Report $reportPlugin */
		$reportPlugin = $this->plugin('XF:Report');
		return $reportPlugin->actionReport(
			'dbtech_shop_trade_post',
			$tradePost,
			$this->buildLink('dbtech-shop/trade-posts/report', $tradePost),
			$this->buildLink('dbtech-shop/trade-posts', $tradePost)
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReact(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactSimple($tradePost, 'dbtech-shop/trade-posts');
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Message|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReactions(ParameterBag $params)
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		$breadcrumbs = $this->getTradePostBreadcrumbs($tradePost);

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactions(
			$tradePost,
			'dbtech-shop/trade-posts/reactions',
			null,
			$breadcrumbs
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionWarn(ParameterBag $params)
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		if (!$tradePost->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $this->getTradePostBreadcrumbs($tradePost);

		/** @var \XF\ControllerPlugin\Warn $warnPlugin */
		$warnPlugin = $this->plugin('XF:Warn');
		return $warnPlugin->actionWarn(
			'dbtech_shop_trade_post',
			$tradePost,
			$this->buildLink('dbtech-shop/trade-posts/warn', $tradePost),
			$breadcrumbs
		);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return \DBTech\Shop\Service\TradePostComment\Creator
	 */
	protected function setupTradePostComment(\DBTech\Shop\Entity\TradePost $tradePost): \DBTech\Shop\Service\TradePostComment\Creator
	{
		$message = $this->plugin('XF:Editor')->fromInput('message');

		/** @var \DBTech\Shop\Service\TradePostComment\Creator $creator */
		$creator = $this->service('DBTech\Shop:TradePostComment\Creator', $tradePost);
		$creator->setContent($message);

		return $creator;
	}
	
	/**
	 * @param \DBTech\Shop\Service\TradePostComment\Creator $creator
	 *
	 * @throws \Exception
	 */
	protected function finalizeTradePostComment(\DBTech\Shop\Service\TradePostComment\Creator $creator)
	{
		$creator->sendNotifications();
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionAddComment(ParameterBag $params)
	{
		$this->assertPostOnly();

		$tradePost = $this->assertViewableTradePost($params->trade_post_id);
		if (!$tradePost->canComment($error))
		{
			return $this->noPermission($error);
		}

		$creator = $this->setupTradePostComment($tradePost);
		$creator->checkForSpam();

		if (!$creator->validate($errors))
		{
			return $this->error($errors);
		}
		$this->assertNotFlooding('post');
		$comment = $creator->save();

		$this->finalizeTradePostComment($creator);

		if ($this->filter('_xfWithData', 'bool') && $this->request->exists('last_date') && $tradePost->canView())
		{
			$tradePostRepo = $this->getTradePostRepo();

			$lastDate = $this->filter('last_date', 'uint');

			/** @var \XF\Mvc\Entity\Finder $tradePostCommentList */
			$tradePostCommentList = $tradePostRepo->findNewestCommentsForTradePost($tradePost, $lastDate);
			$tradePostComments = $tradePostCommentList->fetch();

			// put the posts into oldest-first order
			$tradePostComments = $tradePostComments->reverse(true);

			$viewParams = [
				'tradePost' => $tradePost,
				'tradePostComments' => $tradePostComments
			];
			$view = $this->view('DBTech\Shop:TradePost\NewTradePostComments', 'dbtech_shop_trade_post_new_trade_post_comments', $viewParams);
			$view->setJsonParam('lastDate', $tradePostComments->last()->comment_date);
			return $view;
		}
		else
		{
			return $this->redirect($this->buildLink('dbtech-shop/trade-posts/comments', $comment));
		}
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionLoadPrevious(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		$repo = $this->getTradePostRepo();

		$comments = $repo->findTradePostComments($tradePost)
			->with('full')
			->where('comment_date', '<', $this->filter('before', 'uint'))
			->order('comment_date', 'DESC')
			->limit(20)
			->fetch()
			->reverse();

		if ($comments->count())
		{
			$firstCommentDate = $comments->first()->comment_date;

			$moreCommentsFinder = $repo->findTradePostComments($tradePost)
				->where('comment_date', '<', $firstCommentDate);

			$loadMore = ($moreCommentsFinder->total() > 0);
		}
		else
		{
			$firstCommentDate = 0;
			$loadMore = false;
		}

		$viewParams = [
			'tradePost' => $tradePost,
			'comments' => $comments,
			'firstCommentDate' => $firstCommentDate,
			'loadMore' => $loadMore
		];
		return $this->view('DBTech\Shop:TradePost\LoadPrevious', 'dbtech_shop_trade_post_comments', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionComments(ParameterBag $params): \XF\Mvc\Reply\Redirect
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		$tradePost = $this->assertViewableTradePost($comment->trade_post_id);

		$tradePostRepo = $this->getTradePostRepo();

		$tradePostFinder = $tradePostRepo->findTradePostsInTrade($tradePost->Trade);
		$tradePosts = $tradePostFinder->where('post_date', '>', $tradePost->post_date)->fetch();

		$page = floor($tradePosts->count() / $this->options()->messagesPerPage) + 1;

		$commentId = $comment->trade_post_comment_id;
		$anchor = '#trade-post-comment-' . $commentId;
		if (!isset($tradePost->latest_comment_ids[$commentId]))
		{
			$anchor = '#trade-post-' . $tradePost->trade_post_id;
		}

		return $this->redirectPermanently(
			$this->buildLink('dbtech-shop/trades', $tradePost->Trade, ['page' => $page]) . $anchor
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsShow(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);

		$viewParams = [
			'comment' => $comment,
			'tradePost' => $comment->TradePost,
		];
		return $this->view('DBTech\Shop:TradePost\Comments\Show', 'dbtech_shop_trade_post_comment', $viewParams);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsEdit(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		if (!$comment->canEdit($error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$editor = $this->setupCommentEdit($comment);
			$editor->checkForSpam();

			if (!$editor->validate($errors))
			{
				return $this->error($errors);
			}
			$editor->save();

			$this->finalizeCommentEdit($editor);

			if ($this->filter('_xfWithData', 'bool') && $this->filter('_xfInlineEdit', 'bool'))
			{
				$viewParams = [
					'tradePost' => $comment->TradePost,
					'comment' => $comment
				];
				$reply = $this->view('DBTech\Shop:TradePost\Comments\EditNewComment', 'dbtech_shop_trade_post_comment_edit_new_comment', $viewParams);
				$reply->setJsonParam('message', \XF::phrase('your_changes_have_been_saved'));
				return $reply;
			}
			else
			{
				return $this->redirect($this->buildLink('dbtech-shop/trade-posts/comments', $comment));
			}
		}
		else
		{
			$viewParams = [
				'comment' => $comment,
				'tradePost' => $comment->TradePost,
				'quickEdit' => $this->responseType() == 'json'
			];
			return $this->view('DBTech\Shop:TradePost\Comments\Edit', 'dbtech_shop_trade_post_comment_edit', $viewParams);
		}
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionCommentsDelete(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		if (!$comment->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		if ($this->isPost())
		{
			$type = $this->filter('hard_delete', 'bool') ? 'hard' : 'soft';
			$reason = $this->filter('reason', 'str');

			if (!$comment->canDelete($type, $error))
			{
				return $this->noPermission($error);
			}

			/** @var \DBTech\Shop\Service\TradePostComment\Deleter $deleter */
			$deleter = $this->service('DBTech\Shop:TradePostComment\Deleter', $comment);

			if ($this->filter('author_alert', 'bool') && $comment->canSendModeratorActionAlert())
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			return $this->redirect(
				$this->getDynamicRedirect($this->buildLink('dbtech-shop/trade-posts', $comment), false)
			);
		}
		else
		{
			$viewParams = [
				'comment' => $comment,
				'tradePost' => $comment->TradePost
			];
			return $this->view('DBTech\Shop:TradePost\Comments\Delete', 'dbtech_shop_trade_post_comment_delete', $viewParams);
		}
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsUndelete(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);

		/** @var \XF\ControllerPlugin\Undelete $plugin */
		$plugin = $this->plugin('XF:Undelete');
		return $plugin->actionUndelete(
			$comment,
			$this->buildLink('dbtech-shop/trade-posts/comments/undelete', $comment),
			$this->buildLink('dbtech-shop/trade-posts/comments', $comment),
			\XF::phrase('dbtech_shop_trade_post_comment_by_x', ['username' => $comment->username]),
			'message_state'
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionCommentsApprove(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		if (!$comment->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}

		/** @var \DBTech\Shop\Service\TradePostComment\Approver $approver */
		$approver = \XF::service('DBTech\Shop:TradePostComment\Approver', $comment);
		$approver->approve();

		return $this->redirect($this->buildLink('dbtech-shop/trade-posts/comments', $comment));
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionCommentsUnapprove(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		if (!$comment->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}

		$comment->message_state = 'moderated';
		$comment->save();

		return $this->redirect($this->buildLink('dbtech-shop/trade-posts/comments', $comment));
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsWarn(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		if (!$comment->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $this->getTradePostBreadcrumbs($comment->TradePost);

		/** @var \XF\ControllerPlugin\Warn $warnPlugin */
		$warnPlugin = $this->plugin('XF:Warn');
		return $warnPlugin->actionWarn(
			'dbtech_shop_trade_comment',
			$comment,
			$this->buildLink('dbtech-shop/trade-posts/comments/warn', $comment),
			$breadcrumbs
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsIp(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		$breadcrumbs = $this->getTradePostBreadcrumbs($comment->TradePost);

		/** @var \XF\ControllerPlugin\Ip $ipPlugin */
		$ipPlugin = $this->plugin('XF:Ip');
		return $ipPlugin->actionIp($comment, $breadcrumbs);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsReport(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		if (!$comment->canReport($error))
		{
			return $this->noPermission($error);
		}

		/** @var \XF\ControllerPlugin\Report $reportPlugin */
		$reportPlugin = $this->plugin('XF:Report');
		return $reportPlugin->actionReport(
			'dbtech_shop_trade_comment',
			$comment,
			$this->buildLink('dbtech-shop/trade-posts/comments/report', $comment),
			$this->buildLink('dbtech-shop/trade-posts/comments', $comment)
		);
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsReact(ParameterBag $params): \XF\Mvc\Reply\AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactSimple($comment, 'dbtech-shop/trade-posts/comments');
	}
	
	/**
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\Message|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsReactions(ParameterBag $params)
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);

		$breadcrumbs = [
			'href' => $this->buildLink('members', $comment->TradePost->Trade),
			'value' => $comment->TradePost->Trade->title
		];

		/** @var \XF\ControllerPlugin\Reaction $reactionPlugin */
		$reactionPlugin = $this->plugin('XF:Reaction');
		return $reactionPlugin->actionReactions(
			$comment,
			'dbtech-shop/trade-posts/comments/reactions',
			null,
			$breadcrumbs
		);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return \DBTech\Shop\Service\TradePost\Editor
	 */
	protected function setupEdit(\DBTech\Shop\Entity\TradePost $tradePost): \DBTech\Shop\Service\TradePost\Editor
	{
		$message = $this->plugin('XF:Editor')->fromInput('message');

		/** @var \DBTech\Shop\Service\TradePost\Editor $editor */
		$editor = $this->service('DBTech\Shop:TradePost\Editor', $tradePost);
		$editor->setMessage($message);

		if ($this->filter('author_alert', 'bool') && $tradePost->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}
	
	/**
	 * @param \DBTech\Shop\Service\TradePost\Editor $editor
	 */
	protected function finalizeEdit(\DBTech\Shop\Service\TradePost\Editor $editor)
	{
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePostComment $comment
	 *
	 * @return \DBTech\Shop\Service\TradePostComment\Editor
	 */
	protected function setupCommentEdit(\DBTech\Shop\Entity\TradePostComment $comment): \DBTech\Shop\Service\TradePostComment\Editor
	{
		$message = $this->plugin('XF:Editor')->fromInput('message');

		/** @var \DBTech\Shop\Service\TradePostComment\Editor $editor */
		$editor = $this->service('DBTech\Shop:TradePostComment\Editor', $comment);
		$editor->setMessage($message);

		if ($this->filter('author_alert', 'bool') && $comment->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}
	
	/**
	 * @param \DBTech\Shop\Service\TradePostComment\Editor $editor
	 */
	protected function finalizeCommentEdit(\DBTech\Shop\Service\TradePostComment\Editor $editor)
	{
	}
	
	/**
	 * @param \DBTech\Shop\Entity\TradePost $tradePost
	 *
	 * @return array
	 */
	protected function getTradePostBreadcrumbs(\DBTech\Shop\Entity\TradePost $tradePost): array
	{
		return [
			[
				'href' => $this->buildLink('dbtech-shop/trades', $tradePost->Trade),
				'value' => $tradePost->Trade->title
			]
		];
	}

	/**
	 * @param int|null $tradePostId
	 * @param array $extraWith
	 *
	 * @return \DBTech\Shop\Entity\TradePost
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableTradePost(?int $tradePostId, array $extraWith = []): \DBTech\Shop\Entity\TradePost
	{
		$extraWith[] = 'User';
		$extraWith[] = 'Trade';
		$extraWith = \array_unique($extraWith);

		/** @var \DBTech\Shop\Entity\TradePost $tradePost */
		$tradePost = $this->em()->find('DBTech\Shop:TradePost', $tradePostId, $extraWith);
		if (!$tradePost)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_message_not_found')));
		}
		if (!$tradePost->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $tradePost;
	}

	/**
	 * @param int|null $commentId
	 * @param array $extraWith
	 *
	 * @return \DBTech\Shop\Entity\TradePostComment
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableComment(?int $commentId, array $extraWith = []): \DBTech\Shop\Entity\TradePostComment
	{
		$extraWith[] = 'User';
		$extraWith[] = 'TradePost.Trade';
		$extraWith = \array_unique($extraWith);

		/** @var \DBTech\Shop\Entity\TradePostComment $comment */
		$comment = $this->em()->find('DBTech\Shop:TradePostComment', $commentId, $extraWith);
		if (!$comment)
		{
			throw $this->exception($this->notFound(\XF::phrase('requested_comment_not_found')));
		}
		if (!$comment->canView($error))
		{
			throw $this->exception($this->noPermission($error));
		}

		return $comment;
	}

	/**
	 * @return \DBTech\Shop\Repository\TradePost
	 */
	protected function getTradePostRepo(): \DBTech\Shop\Repository\TradePost
	{
		return $this->repository('DBTech\Shop:TradePost');
	}
	
	/**
	 * @param array $activities
	 *
	 * @return \XF\Phrase
	 */
	public static function getActivityDetails(array $activities): \XF\Phrase
	{
		return \XF::phrase('dbtech_shop_viewing_trades');
	}
}