<?php

namespace DBTech\Shop\Api\Controller;

use DBTech\Shop\Service\TradePostComment\DeleterService;
use DBTech\Shop\Service\TradePostComment\EditorService;
use XF\Api\Controller\AbstractController;
use XF\Api\ControllerPlugin\ReactionPlugin;
use XF\Api\Mvc\Reply\ApiResult;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Mvc\Reply\Exception;
use XF\PrintableException;

/**
 * @api-group Trade posts
 */
class TradePostComment extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params): void
	{
		$this->assertApiScopeByRequestMethod('dbtech_shop_trade_post');
	}

	/**
	 * @api-desc Gets information about the specified trade post comment.
	 *
	 * @api-out TradePostComment $comment
	 *
	 * @param ParameterBag $params
	 *
	 * @return ApiResult
	 * @throws Exception
	 */
	public function actionGet(ParameterBag $params): ApiResult
	{
		$comment = $this->assertViewableTradePostComment($params->trade_post_comment_id, ['api|post']);

		$result = $comment->toApiResult(Entity::VERBOSITY_VERBOSE, [
			'with_post' => true,
		]);

		return $this->apiResult(['comment' => $result]);
	}

	/**
	 * @api-desc Updates the specified trade post comment.
	 *
	 * @api-in str $message
	 * @api-in bool $author_alert
	 * @api-in bool $author_alert_reason
	 *
	 * @api-out true $success
	 * @api-out TradePostComment $comment
	 *
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 */
	public function actionPost(ParameterBag $params): AbstractReply
	{
		$comment = $this->assertViewableTradePostComment($params->trade_post_comment_id);

		if (\XF::isApiCheckingPermissions() && !$comment->canEdit($error))
		{
			return $this->noPermission($error);
		}

		$editor = $this->setupTradePostCommentEdit($comment);

		if (\XF::isApiCheckingPermissions())
		{
			$editor->checkForSpam();
		}

		if (!$editor->validate($errors))
		{
			return $this->error($errors);
		}

		$editor->save();

		return $this->apiSuccess([
			'comment' => $comment->toApiResult(),
		]);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePostComment $comment
	 *
	 * @return EditorService
	 */
	protected function setupTradePostCommentEdit(\DBTech\Shop\Entity\TradePostComment $comment): EditorService
	{
		$input = $this->filter([
			'message' => '?str',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str',
		]);

		$editor = \XF::app()->service(EditorService::class, $comment);

		if ($input['message'] !== null)
		{
			$editor->setMessage($input['message']);
		}

		if ($input['author_alert'] && $comment->canSendModeratorActionAlert())
		{
			$editor->setSendAlert(true, $input['author_alert_reason']);
		}

		return $editor;
	}

	/**
	 * @api-desc Deletes the specified trade post comment. Default to soft deletion.
	 *
	 * @api-in bool $hard_delete
	 * @api-in str $reason
	 * @api-in bool $author_alert
	 * @api-in str $author_alert_reason
	 *
	 * @api-out true $success
	 *
	 * @param ParameterBag $params
	 *
	 * @return AbstractReply
	 * @throws Exception
	 * @throws PrintableException
	 */
	public function actionDelete(ParameterBag $params): AbstractReply
	{
		$comment = $this->assertViewableTradePostComment($params->trade_post_comment_id);

		if (\XF::isApiCheckingPermissions() && !$comment->canDelete('soft', $error))
		{
			return $this->noPermission($error);
		}

		$type = 'soft';
		$reason = $this->filter('reason', 'str');

		if ($this->filter('hard_delete', 'bool'))
		{
			$this->assertApiScope('dbtech_shop_trade_post:delete_hard');

			if (\XF::isApiCheckingPermissions() && !$comment->canDelete('hard', $error))
			{
				return $this->noPermission($error);
			}

			$type = 'hard';
		}

		$deleter = \XF::app()->service(DeleterService::class, $comment);

		if ($this->filter('author_alert', 'bool') && $comment->canSendModeratorActionAlert())
		{
			$deleter->setSendAlert(true, $this->filter('author_alert_reason', 'str'));
		}

		$deleter->delete($type, $reason);

		return $this->apiSuccess();
	}

	/**
	 * @api-desc Reacts to the specified trade post comment
	 *
	 * @api-see \XF\Api\ControllerPlugin\Reaction::actionReact()
	 * @param ParameterBag $params
	 *
	 * @return ApiResult
	 * @throws Exception
	 */
	public function actionPostReact(ParameterBag $params): ApiResult
	{
		$comment = $this->assertViewableTradePostComment($params->trade_post_comment_id);

		$reactPlugin = $this->plugin(ReactionPlugin::class);
		return $reactPlugin->actionReact($comment);
	}

	/**
	 * @param int $id
	 * @param array $with
	 *
	 * @return \DBTech\Shop\Entity\TradePostComment
	 *
	 * @throws Exception
	 * @noinspection PhpReturnDocTypeMismatchInspection
	 * @noinspection PhpIncompatibleReturnTypeInspection
	 */
	protected function assertViewableTradePostComment(int $id, array $with = ['api']): \DBTech\Shop\Entity\TradePostComment
	{
		return $this->assertViewableApiRecord(\DBTech\Shop\Entity\TradePostComment::class, $id, $with);
	}
}