<?php

namespace XF\Poll;

use XF\Entity\Poll;
use XF\Entity\PollResponse;
use XF\Util\Str;

use function count, strlen;

class ResponseEditor
{
	/**
	 * @var Poll
	 */
	protected $poll;

	/**
	 * @var array<int, PollResponse>
	 */
	protected $existingResponses = [];

	/**
	 * @var array<int, int>
	 */
	protected $deleteResponses = [];

	/**
	 * @var array<int, string>
	 */
	protected $replaceResponses = [];

	/**
	 * @var list<string>
	 */
	protected $addResponses = [];

	public function __construct(Poll $poll)
	{
		if ($poll->poll_id)
		{
			$responses = $poll->Responses->toArray();
		}
		else
		{
			$responses = [];
		}

		$this->poll = $poll;
		$this->existingResponses = $responses;
	}

	/**
	 * @return array<int, PollResponse>
	 */
	public function getExistingResponses()
	{
		return $this->existingResponses;
	}

	/**
	 * @param list<string> $responses
	 *
	 * @return void
	 */
	public function addResponses(array $responses)
	{
		foreach ($responses AS $response)
		{
			$this->addResponse($response);
		}
	}

	/**
	 * @param string $response
	 *
	 * @return bool
	 */
	public function addResponse($response)
	{
		$response = trim($response);
		if (strlen($response))
		{
			$this->addResponses[] = $response;
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @return list<string>
	 */
	public function getAddedResponses()
	{
		return $this->addResponses;
	}

	/**
	 * @param int $responseId
	 *
	 * @return bool
	 */
	public function deleteResponse($responseId)
	{
		if (!isset($this->existingResponses[$responseId]))
		{
			return false;
		}

		$this->deleteResponses[$responseId] = $responseId;
		return true;
	}

	/**
	 * @return array<int, int>
	 */
	public function getDeletedResponses()
	{
		return $this->deleteResponses;
	}

	/**
	 * @param int $responseId
	 * @param string $newResponse
	 *
	 * @return bool
	 */
	public function replaceResponse($responseId, $newResponse)
	{
		if (!isset($this->existingResponses[$responseId]))
		{
			return false;
		}

		$newResponse = trim($newResponse);
		if (!strlen($newResponse))
		{
			$this->deleteResponse($responseId);
		}
		else
		{
			$this->replaceResponses[$responseId] = $newResponse;
		}

		return true;
	}

	/**
	 * @return array<int, string>
	 */
	public function getReplacedResponses()
	{
		return $this->replaceResponses;
	}

	/**
	 * @param array<int, string> $responses
	 *
	 * @return void
	 */
	public function updateResponses(array $responses)
	{
		foreach ($responses AS $id => $response)
		{
			// this handles deleting if needed
			$this->replaceResponse($id, $response);
		}
	}

	/**
	 * @return int
	 */
	public function countResponses()
	{
		return count($this->existingResponses) + count($this->addResponses) - count($this->deleteResponses);
	}

	/**
	 * @return void
	 */
	public function saveChanges()
	{
		if (!$this->poll->poll_id)
		{
			throw new \LogicException("Poll must be saved before responses can be saved");
		}

		if (!$this->addResponses && !$this->deleteResponses && !$this->replaceResponses)
		{
			return;
		}

		$db = $this->poll->em()->getDb();
		$existingResponses = $this->existingResponses;

		$db->beginTransaction();

		foreach ($this->deleteResponses AS $responseId)
		{
			$response = $existingResponses[$responseId];
			$response->delete(true, false);
		}

		foreach ($this->replaceResponses AS $responseId => $value)
		{
			$response = $existingResponses[$responseId];
			$response->response = Str::substr($value, 0, 100);
			$response->save(true, false);
		}

		foreach ($this->addResponses AS $value)
		{
			$response = \XF::em()->create(PollResponse::class);
			$response->poll_id = $this->poll->poll_id;
			$response->response = Str::substr($value, 0, 100);
			$response->save(true, false);
		}

		$db->commit();

		$this->poll->clearCache('Responses');
	}

	/**
	 * @param int|null $maxResponses
	 *
	 * @return string|\Stringable|null
	 */
	public function getResponseCountErrorMessage($maxResponses = null)
	{
		$count = $this->countResponses();
		if ($count < 2)
		{
			return \XF::phrase('please_enter_at_least_two_poll_responses');
		}

		if ($maxResponses && $count > $maxResponses)
		{
			return \XF::phrase('too_many_poll_responses_have_been_entered');
		}

		return null;
	}
}
