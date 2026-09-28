<?php

namespace DBTech\Shop\Api\Controller;

use XF\Api\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;

/**
 * @api-group Trade posts
 */
class TradePostComment extends AbstractController
{
	protected function preDispatchController($action, ParameterBag $params)
	{
		$this->assertApiScopeByRequestMethod('dbtech_shop_trade_post');
	}

	/**
	 * @api-desc Gets information about the specified trade post comment.
	 *
	 * @api-out TradePostComment $comment
	 *
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionGet(ParameterBag $params): \XF\Api\Mvc\Reply\ApiResult
	{
		$comment = $this->assertViewableTradePostComment($params->trade_post_comment_id, 'api|post');

		$result = $comment->toApiResult(Entity::VERBOSITY_VERBOSE, [
			'with_post' => true
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
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult|\XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionPost(ParameterBag $params)
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
			'comment' => $comment->toApiResult()
		]);
	}

	/**
	 * @param \DBTech\Shop\Entity\TradePostComment $comment
	 *
	 * @return \DBTech\Shop\Service\TradePostComment\Editor
	 */
	protected function setupTradePostCommentEdit(\DBTech\Shop\Entity\TradePostComment $comment): \DBTech\Shop\Service\TradePostComment\Editor
	{
		$input = $this->filter([
			'message' => '?str',
			'author_alert' => 'bool',
			'author_alert_reason' => 'str'
		]);

		/** @var \DBTech\Shop\Service\TradePostComment\Editor $editor */
		$editor = $this->service('DBTech\Shop:TradePostComment\Editor', $comment);

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
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult|\XF\Mvc\Reply\AbstractReply|\XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
	 * @throws \XF\Mvc\Reply\Exception
	 * @throws \XF\PrintableException
	 */
	public function actionDelete(ParameterBag $params)
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

		/** @var \DBTech\Shop\Service\TradePostComment\Deleter $deleter */
		$deleter = $this->service('DBTech\Shop:TradePostComment\Deleter', $comment);

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
	 * @param \XF\Mvc\ParameterBag $params
	 *
	 * @return \XF\Api\Mvc\Reply\ApiResult
	 * @throws \XF\Mvc\Reply\Exception
	 */
	public function actionPostReact(ParameterBag $params): \XF\Api\Mvc\Reply\ApiResult
	{
		$comment = $this->assertViewableTradePostComment($params->trade_post_comment_id);
		
		/** @var \XF\Api\ControllerPlugin\Reaction $reactPlugin */
		$reactPlugin = $this->plugin('XF:Api:Reaction');
		return $reactPlugin->actionReact($comment);
	}

	/**
	 * @param int $id
	 * @param string|array $with
	 *
	 * @return \DBTech\Shop\Entity\TradePostComment|\XF\Mvc\Entity\Entity
	 *
	 * @throws \XF\Mvc\Reply\Exception
	 */
	protected function assertViewableTradePostComment(int $id, $with = 'api')
	{
		return $this->assertViewableApiRecord('DBTech\Shop:TradePostComment', $id, $with);
	}
}