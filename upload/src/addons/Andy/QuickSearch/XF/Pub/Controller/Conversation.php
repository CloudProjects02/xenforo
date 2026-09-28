<?php

namespace Andy\QuickSearch\XF\Pub\Controller;

use XF\Mvc\ParameterBag;

class Conversation extends XFCP_Conversation
{
	public function actionQuickSearch(ParameterBag $params)
	{
		//########################################
		// show quick search in conversation_list
		//########################################

		// get options
		$options = \XF::options();
		
		// get options from Admin CP -> Options -> Quick search -> Maximum results
		$maximumResults = $options->quickSearchMaximumResults;

		// get quickSearchTitle
		$quickSearchTitle = $this->filter('title', 'str');

		// check condition
		if (empty($quickSearchTitle))
		{
			return;
		}

		// get threads
		$finder = \XF::finder('XF:ConversationMaster');
		$conversations = $finder
			->where('title', 'LIKE', $finder->escapeLike($quickSearchTitle, '%?%'))
			->where('conversation_open', 1)
			->order('start_date', 'DESC')
			->limit($maximumResults)
			->fetch()
			->filterViewable();

		// prepare viewParams
		$viewParams = [
			'conversations' => $conversations
		];

		// send to template
		return $this->view('Andy\QuickSearch:QuickSearch', 'andy_quicksearch_conversation', $viewParams);
	}
}