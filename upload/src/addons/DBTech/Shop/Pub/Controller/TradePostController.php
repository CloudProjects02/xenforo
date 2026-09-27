<?php

namespace DBTech\Shop\Pub\Controller;

use DBTech\Shop\Entity\TradePost;
use DBTech\Shop\Entity\TradePostComment;
use DBTech\Shop\Pub\View;
use DBTech\Shop\Repository\TradePostRepository;
use DBTech\Shop\Service\TradePostComment\ApproverService;
use DBTech\Shop\Service\TradePostComment\CreatorService;
use DBTech\Shop\Service\TradePostComment\DeleterService;
use DBTech\Shop\Service\TradePostComment\EditorService;
use XF\ControllerPlugin\EditorPlugin;
use XF\ControllerPlugin\InlineModPlugin;
use XF\ControllerPlugin\IpPlugin;
use XF\ControllerPlugin\ReactionPlugin;
use XF\ControllerPlugin\ReportPlugin;
use XF\ControllerPlugin\UndeletePlugin;
use XF\ControllerPlugin\WarnPlugin;
use XF\Mvc\Entity\Finder;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Phrase;
use XF\PrintableException;
use XF\Pub\Controller\AbstractController;

class TradePostController extends AbstractController
{
	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIndex(ParameterBag $params): AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		if ($this->filter('_xfWithData', 'bool'))
		{
			$this->request->set('_xfDisableInlineMod', true);
			return $this->rerouteController(__CLASS__, 'show', $params);
		}

		$tradePostRepo = \XF::app()->repository(TradePostRepository::class);

		$tradePostFinder = $tradePostRepo->findTradePostsInTrade($tradePost->Trade);
		$tradePosts = $tradePostFinder->where('post_date', '>', $tradePost->post_date)->fetch();

		$page = floor($tradePosts->count() / \XF::app()->options()->messagesPerPage) + 1;

		return $this->redirectPermanently(
			$this->buildLink('dbtech-shop/trades', $tradePost->Trade, ['page' => $page]) . '#trade-post-' . $tradePost->trade_post_id
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionShow(ParameterBag $params): AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		$tradePostRepo = \XF::app()->repository(TradePostRepository::class);
		$tradePost = $tradePostRepo->addCommentsToTradePost($tradePost);

		$viewParams = [
			'tradePost' => $tradePost,
			'showTargetUser' => true,
			'canInlineMod' => $tradePost->canUseInlineModeration(),
			'allowInlineMod' => !$this->request->get('_xfDisableInlineMod'),
		];
		return $this->view(
			View\TradePost\ShowView::class,
			'dbtech_shop_trade_post',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionEdit(ParameterBag $params): AbstractReply
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

					'noInlineMod' => $noInlineMod,
				];
				$reply = $this->view(
					View\TradePost\EditNewTradePostView::class,
					'dbtech_shop_trade_post_edit_new_post',
					$viewParams
				);
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
				'noInlineMod' => $noInlineMod,
			];
			return $this->view(
				View\TradePost\EditView::class,
				'dbtech_shop_trade_post_edit',
				$viewParams
			);
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionDelete(ParameterBag $params): AbstractReply
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

			$deleter = \XF::app()->service(\DBTech\Shop\Service\TradePost\DeleterService::class, $tradePost);

			if ($this->filter('author_alert', 'bool') && $tradePost->canSendModeratorActionAlert())
			{
				$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
			}

			$deleter->delete($type, $reason);

			$this->plugin(InlineModPlugin::class)
				->clearIdFromCookie('dbtech_shop_trade_post', $tradePost->trade_post_id)
			;

			return $this->redirect(
				$this->getDynamicRedirect($this->buildLink('dbtech-shop/trades', $tradePost->Trade), false)
			);
		}
		else
		{
			$viewParams = [
				'tradePost' => $tradePost,
				'trade' => $tradePost->Trade,
			];
			return $this->view(
				View\TradePost\DeleteView::class,
				'dbtech_shop_trade_post_delete',
				$viewParams
			);
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionUndelete(ParameterBag $params): AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		$plugin = $this->plugin(UndeletePlugin::class);
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionIp(ParameterBag $params): AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);
		$breadcrumbs = $this->getTradePostBreadcrumbs($tradePost);

		$ipPlugin = $this->plugin(IpPlugin::class);
		return $ipPlugin->actionIp($tradePost, $breadcrumbs);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReport(ParameterBag $params): AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);
		if (!$tradePost->canReport($error))
		{
			return $this->noPermission($error);
		}

		$reportPlugin = $this->plugin(ReportPlugin::class);
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReact(ParameterBag $params): AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		$reactionPlugin = $this->plugin(ReactionPlugin::class);
		return $reactionPlugin->actionReactSimple($tradePost, 'dbtech-shop/trade-posts');
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionReactions(ParameterBag $params): AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		$breadcrumbs = $this->getTradePostBreadcrumbs($tradePost);

		$reactionPlugin = $this->plugin(ReactionPlugin::class);
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionWarn(ParameterBag $params): AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		if (!$tradePost->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $this->getTradePostBreadcrumbs($tradePost);

		$warnPlugin = $this->plugin(WarnPlugin::class);
		return $warnPlugin->actionWarn(
			'dbtech_shop_trade_post',
			$tradePost,
			$this->buildLink('dbtech-shop/trade-posts/warn', $tradePost),
			$breadcrumbs
		);
	}

	/**
	 * @param TradePost $tradePost
	 *
	 * @return CreatorService
	 */
	protected function setupTradePostComment(TradePost $tradePost): CreatorService
	{
		$message = $this->plugin(EditorPlugin::class)->fromInput('message');

		$creator = \XF::app()->service(CreatorService::class, $tradePost);
		$creator->setContent($message);

		return $creator;
	}

	/**
	 * @param CreatorService $creator
	 *
	 * @throws \Exception
	 */
	protected function finalizeTradePostComment(CreatorService $creator): void
	{
		$creator->sendNotifications();
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \Exception
	 */
	public function actionAddComment(ParameterBag $params): AbstractReply
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
			$tradePostRepo = \XF::app()->repository(TradePostRepository::class);

			$lastDate = $this->filter('last_date', 'uint');

			/** @var Finder $tradePostCommentList */
			$tradePostCommentList = $tradePostRepo->findNewestCommentsForTradePost($tradePost, $lastDate);
			$tradePostComments = $tradePostCommentList->fetch();

			// put the posts into oldest-first order
			$tradePostComments = $tradePostComments->reverse();

			$viewParams = [
				'tradePost' => $tradePost,
				'tradePostComments' => $tradePostComments,
			];
			$view = $this->view(
				View\TradePost\NewTradePostCommentsView::class,
				'dbtech_shop_trade_post_new_trade_post_comments',
				$viewParams
			);
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionLoadPrevious(ParameterBag $params): AbstractReply
	{
		$tradePost = $this->assertViewableTradePost($params->trade_post_id);

		$repo = \XF::app()->repository(TradePostRepository::class);

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
			'loadMore' => $loadMore,
		];
		return $this->view(
			View\TradePost\LoadPreviousView::class,
			'dbtech_shop_trade_post_comments',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionComments(ParameterBag $params): AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		$tradePost = $this->assertViewableTradePost($comment->trade_post_id);

		$tradePostRepo = \XF::app()->repository(TradePostRepository::class);

		$tradePostFinder = $tradePostRepo->findTradePostsInTrade($tradePost->Trade);
		$tradePosts = $tradePostFinder->where('post_date', '>', $tradePost->post_date)->fetch();

		$page = floor($tradePosts->count() / \XF::app()->options()->messagesPerPage) + 1;

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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsShow(ParameterBag $params): AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);

		$viewParams = [
			'comment' => $comment,
			'tradePost' => $comment->TradePost,
		];
		return $this->view(
			View\TradePost\Comments\ShowView::class,
			'dbtech_shop_trade_post_comment',
			$viewParams
		);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsEdit(ParameterBag $params): AbstractReply
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
					'comment' => $comment,
				];
				$reply = $this->view(
					View\TradePost\Comments\EditNewCommentView::class,
					'dbtech_shop_trade_post_comment_edit_new_comment',
					$viewParams
				);
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
				'quickEdit' => $this->responseType() == 'json',
			];
			return $this->view(
				View\TradePost\Comments\EditView::class,
				'dbtech_shop_trade_post_comment_edit',
				$viewParams
			);
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionCommentsDelete(ParameterBag $params): AbstractReply
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

			$deleter = \XF::app()->service(DeleterService::class, $comment);

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
				'tradePost' => $comment->TradePost,
			];
			return $this->view(
				View\TradePost\Comments\DeleteView::class,
				'dbtech_shop_trade_post_comment_delete',
				$viewParams
			);
		}
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsUndelete(ParameterBag $params): AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);

		$plugin = $this->plugin(UndeletePlugin::class);
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionCommentsApprove(ParameterBag $params): AbstractReply
	{
		$this->assertValidCsrfToken($this->filter('t', 'str'));

		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		if (!$comment->canApproveUnapprove($error))
		{
			return $this->noPermission($error);
		}

		$approver = \XF::app()->service(ApproverService::class, $comment);
		$approver->approve();

		return $this->redirect($this->buildLink('dbtech-shop/trade-posts/comments', $comment));
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws PrintableException
	 */
	public function actionCommentsUnapprove(ParameterBag $params): AbstractReply
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsWarn(ParameterBag $params): AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		if (!$comment->canWarn($error))
		{
			return $this->noPermission($error);
		}

		$breadcrumbs = $this->getTradePostBreadcrumbs($comment->TradePost);

		$warnPlugin = $this->plugin(WarnPlugin::class);
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsIp(ParameterBag $params): AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		$breadcrumbs = $this->getTradePostBreadcrumbs($comment->TradePost);

		$ipPlugin = $this->plugin(IpPlugin::class);
		return $ipPlugin->actionIp($comment, $breadcrumbs);
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsReport(ParameterBag $params): AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);
		if (!$comment->canReport($error))
		{
			return $this->noPermission($error);
		}

		$reportPlugin = $this->plugin(ReportPlugin::class);
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
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsReact(ParameterBag $params): AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);

		$reactionPlugin = $this->plugin(ReactionPlugin::class);
		return $reactionPlugin->actionReactSimple($comment, 'dbtech-shop/trade-posts/comments');
	}

	/**
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionCommentsReactions(ParameterBag $params): AbstractReply
	{
		$comment = $this->assertViewableComment($params->trade_post_comment_id);

		$breadcrumbs = [
			'href' => $this->buildLink('members', $comment->TradePost->Trade),
			'value' => $comment->TradePost->Trade->title,
		];

		$reactionPlugin = $this->plugin(ReactionPlugin::class);
		return $reactionPlugin->actionReactions(
			$comment,
			'dbtech-shop/trade-posts/comments/reactions',
			null,
			$breadcrumbs
		);
	}

	/**
	 * @param TradePost $tradePost
	 *
	 * @return \DBTech\Shop\Service\TradePost\EditorService
	 */
	protected function setupEdit(TradePost $tradePost): \DBTech\Shop\Service\TradePost\EditorService
	{
		$message = $this->plugin(EditorPlugin::class)->fromInput('message');

		$editor = \XF::app()->service(\DBTech\Shop\Service\TradePost\EditorService::class, $tradePost);
		$editor->setMessage($message);

		if ($this->filter('author_alert', 'bool') && $tradePost->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}

	/**
	 * @param \DBTech\Shop\Service\TradePost\EditorService $editor
	 */
	protected function finalizeEdit(\DBTech\Shop\Service\TradePost\EditorService $editor)
	{
	}

	/**
	 * @param TradePostComment $comment
	 *
	 * @return EditorService
	 */
	protected function setupCommentEdit(TradePostComment $comment): EditorService
	{
		$message = $this->plugin(EditorPlugin::class)->fromInput('message');

		$editor = \XF::app()->service(EditorService::class, $comment);
		$editor->setMessage($message);

		if ($this->filter('author_alert', 'bool') && $comment->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		return $editor;
	}

	/**
	 * @param EditorService $editor
	 */
	protected function finalizeCommentEdit(EditorService $editor)
	{
	}

	/**
	 * @param TradePost $tradePost
	 *
	 * @return array
	 */
	protected function getTradePostBreadcrumbs(TradePost $tradePost): array
	{
		return [
			[
				'href' => $this->buildLink('dbtech-shop/trades', $tradePost->Trade),
				'value' => $tradePost->Trade->title,
			],
		];
	}

	/**
	 * @param int|null $tradePostId
	 * @param array $extraWith
	 *
	 * @return TradePost
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableTradePost(?int $tradePostId, array $extraWith = []): TradePost
	{
		$extraWith[] = 'User';
		$extraWith[] = 'Trade';
		$extraWith = \array_unique($extraWith);

		$tradePost = \XF::app()->em()->find(TradePost::class, $tradePostId, $extraWith);
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
	 * @return TradePostComment
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableComment(?int $commentId, array $extraWith = []): TradePostComment
	{
		$extraWith[] = 'User';
		$extraWith[] = 'TradePost.Trade';
		$extraWith = \array_unique($extraWith);

		$comment = \XF::app()->em()->find(TradePostComment::class, $commentId, $extraWith);
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
	 * @param array $activities
	 *
	 * @return Phrase
	 */
	public static function getActivityDetails(array $activities): Phrase
	{
		return \XF::phrase('dbtech_shop_viewing_trades');
	}
}