<?php

namespace XF\Attachment;

use XF\Entity\Attachment;
use XF\Http\Upload;
use XF\Mvc\Entity\Entity;
use XF\Repository\AttachmentRepository;
use XF\Service\Attachment\PreparerService;

use function count;

/**
 * @template TContainer of Entity
 * @template TContext of array<string, int|null>
 */
class Manipulator
{
	/**
	 * @var AbstractHandler<TContainer, TContext>
	 */
	protected $handler;

	/**
	 * @var AttachmentRepository
	 */
	protected $repo;

	/**
	 * @var TContext
	 */
	protected $context;

	/**
	 * @var string
	 */
	protected $hash;

	/**
	 * @var array{
	 *     extensions?: list<string>,
	 *     size?: int,
	 *     width?: int,
	 *     height?: int,
	 *     count?: int,
	 *     video_size?: int,
	 * }
	 */
	protected $constraints = [];

	/**
	 * @var int
	 */
	protected $unassociatedLimit;

	/**
	 * @var TContainer|null
	 */
	protected $container;

	/**
	 * @var int|null
	 */
	protected $unassociatedAttachmentCount;

	/**
	 * @var array<Attachment>
	 */
	protected $existingAttachments = [];

	/**
	 * @var array<Attachment>
	 */
	protected $newAttachments = [];

	/**
	 * @param AbstractHandler<TContainer, TContext> $handler
	 * @param TContext $context
	 * @param string $hash
	 */
	public function __construct(AbstractHandler $handler, AttachmentRepository $repo, array $context, $hash)
	{
		$this->handler = $handler;
		$this->repo = $repo;

		$this->setContext($context);
		$this->setHash($hash);
		$this->setConstraints($handler->getConstraints($context));
		$this->setUnassociatedLimits();
	}

	/**
	 * @return TContext
	 */
	public function getContext()
	{
		return $this->context;
	}

	/**
	 * @param TContext $context
	 *
	 * @return void
	 */
	public function setContext(array $context)
	{
		$this->context = $context;

		$this->container = $this->handler->getContainerFromContext($context);
		if ($this->container)
		{
			$containerId = $this->handler->getContainerIdFromContext($context);
			if ($containerId !== null)
			{
				$existing = $this->repo->findAttachmentsByContent(
					$this->handler->getContentType(),
					$containerId
				)->fetch();
				$this->existingAttachments = $existing->toArray();
			}
			else
			{
				$this->existingAttachments = [];
			}
		}
	}

	/**
	 * @return TContainer|null
	 */
	public function getContainer()
	{
		return $this->container;
	}

	/**
	 * @return string
	 */
	public function getHash()
	{
		return $this->hash;
	}

	/**
	 * @param string $hash
	 *
	 * @return void
	 */
	public function setHash($hash)
	{
		if (!$hash)
		{
			throw new \InvalidArgumentException("Hash must be specified");
		}

		$this->hash = $hash;

		$attachments = $this->repo->findAttachmentsByTempHash($hash)->fetch();
		$this->newAttachments = $attachments->toArray();
	}

	/**
	 * @return array{
	 *     extensions?: list<string>,
	 *     size?: int,
	 *     width?: int,
	 *     height?: int,
	 *     count?: int,
	 *     video_size?: int,
	 * }
	 */
	public function getConstraints()
	{
		return $this->constraints;
	}

	/**
	 * @param array{
	 *     extensions?: list<string>,
	 *     size?: int,
	 *     width?: int,
	 *     height?: int,
	 *     count?: int,
	 *     video_size?: int,
	 * } $constraints
	 *
	 * @return void
	 */
	public function setConstraints(array $constraints)
	{
		$this->constraints = $constraints;
	}

	/**
	 * @return void
	 */
	public function setUnassociatedLimits()
	{
		$this->unassociatedLimit = \XF::config('unassociatedAttachmentLimit');

		if ($this->unassociatedLimit && $this->unassociatedAttachmentCount === null)
		{
			$repo = $this->repo;
			$this->unassociatedAttachmentCount = $repo->countUnassociatedAttachmentsForUser();
		}
	}

	/**
	 * @param string|\Stringable|null &$error
	 *
	 * @return bool
	 */
	public function canUpload(&$error = null)
	{
		$constraints = $this->constraints;

		if (isset($constraints['count']) && $constraints['count'] > 0)
		{
			$uploaded = count($this->existingAttachments) + count($this->newAttachments);
			$allowed = ($uploaded < $constraints['count']);

			if (!$allowed)
			{
				$error = \XF::phraseDeferred('you_may_only_attach_x_files', ['count' => $constraints['count']]);
				return false;
			}
		}

		$unassociatedLimit = $this->unassociatedLimit;

		if ($unassociatedLimit)
		{
			$uploaded = $this->unassociatedAttachmentCount;
			$allowed = ($uploaded < $unassociatedLimit);

			if (!$allowed)
			{
				$error = \XF::phraseDeferred('you_have_reached_the_maximum_limit_for_attachment_uploads');
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array<int, Attachment>
	 */
	public function getExistingAttachments()
	{
		return $this->existingAttachments;
	}

	/**
	 * @return array<int, Attachment>
	 */
	public function getNewAttachments()
	{
		return $this->newAttachments;
	}

	/**
	 * @param int $id
	 *
	 * @return bool
	 */
	public function deleteAttachment($id)
	{
		if (isset($this->existingAttachments[$id]))
		{
			$this->existingAttachments[$id]->delete();
			unset($this->existingAttachments[$id]);
			return true;
		}
		else if (isset($this->newAttachments[$id]))
		{
			$this->newAttachments[$id]->delete();
			unset($this->newAttachments[$id]);
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * @param string|\Stringable|null &$error
	 *
	 * @return Attachment|null
	 */
	public function insertAttachmentFromUpload(Upload $upload, &$error = null)
	{
		$upload->applyConstraints($this->constraints);

		$handler = $this->handler;
		$handler->validateAttachmentUpload($upload, $this);

		if (!$upload->isValid($errors))
		{
			$error = reset($errors);
			return null;
		}

		$inserter = \XF::app()->service(PreparerService::class);

		return $inserter->insertAttachment(
			$handler,
			$upload->getFileWrapper(),
			\XF::visitor(),
			$this->hash
		);
	}
}
