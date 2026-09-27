<?php

namespace XF\Attachment;

use XF\Entity\Attachment;
use XF\Entity\LinkableInterface;
use XF\FileWrapper;
use XF\Http\Upload;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;

use function get_class;

/**
 * @template TContainer of Entity
 * @template TContext of array<string, int|null>
 */
abstract class AbstractHandler
{
	/**
	 * @var string
	 */
	protected $contentType;

	/**
	 * @param string $contentType
	 */
	public function __construct($contentType)
	{
		$this->contentType = $contentType;
	}

	/**
	 * @param TContainer $container
	 * @param string|\Stringable|null $error
	 *
	 * @return bool
	 */
	abstract public function canView(Attachment $attachment, Entity $container, &$error = null);

	/**
	 * @param TContext $context
	 * @param string|\Stringable|null $error
	 *
	 * @return bool
	 */
	abstract public function canManageAttachments(array $context, &$error = null);

	/**
	 * @param TContainer|null $container
	 *
	 * @return void
	 */
	abstract public function onAttachmentDelete(Attachment $attachment, ?Entity $container = null);

	/**
	 * @param TContext $context
	 *
	 * @return array{
	 *     extensions?: list<string>,
	 *     size?: int,
	 *     width?: int,
	 *     height?: int,
	 *     count?: int,
	 *     video_size?: int,
	 * }
	 */
	abstract public function getConstraints(array $context);

	/**
	 * @param TContext $context
	 *
	 * @return int|null
	 */
	abstract public function getContainerIdFromContext(array $context);

	/**
	 * @param TContext $extraContext
	 *
	 * @return TContext
	 */
	abstract public function getContext(?Entity $entity = null, array $extraContext = []);

	/**
	 * @param TContainer $container
	 * @param array<string, string> $extraParams
	 *
	 * @return string
	 */
	public function getContainerLink(Entity $container, array $extraParams = [])
	{
		if ($container instanceof LinkableInterface)
		{
			return $container->getContentUrl(false, $extraParams);
		}

		throw new \LogicException(
			'Implement XF\Entity\LinkableInterface for ' . get_class($container)
			. ' or override ' . get_class($this) . '::getContentLink'
		);
	}

	/**
	 * @param TContainer $container
	 *
	 * @return string|\Stringable
	 */
	public function getContainerTitle(Entity $container)
	{
		if ($container instanceof LinkableInterface)
		{
			return $container->getContentTitle('attachment');
		}

		return '';
	}

	/**
	 * @param Manipulator<TContainer, TContext> $manipulator
	 *
	 * @return void
	 */
	public function validateAttachmentUpload(Upload $upload, Manipulator $manipulator)
	{
		return;
	}

	/**
	 * @param array{file_path?: string, upload_date?: int|null} $extra
	 *
	 * @return void
	 */
	public function beforeNewAttachment(FileWrapper $file, array &$extra = [])
	{
		return;
	}

	/**
	 * @return void
	 */
	public function onNewAttachment(Attachment $attachment, FileWrapper $file)
	{
		return;
	}

	/**
	 * @param TContext $context
	 * @param array{
	 *     attachment: array{
	 *         attachment_id: int|null,
	 *         filename: string,
	 *         file_size: int,
	 *         file_size_printable: string,
	 *         thumbnail_url: string,
	 *         retina_thumbnail_url: string,
	 *         width: int,
	 *         height: int,
	 *         icon: string,
	 *         icon_name: string,
	 *         is_video: bool,
	 *         is_audio: bool,
	 *         link: string,
	 *         type_grouping: string,
	 *     },
	 *     link: string,
	 * } $json
	 *
	 * @return array{
	 *     attachment: array{
	 *         attachment_id: int|null,
	 *         filename: string,
	 *         file_size: int,
	 *         file_size_printable: string,
	 *         thumbnail_url: string,
	 *         retina_thumbnail_url: string,
	 *         width: int,
	 *         height: int,
	 *         icon: string,
	 *         icon_name: string,
	 *         is_video: bool,
	 *         is_audio: bool,
	 *         link: string,
	 *         type_grouping: string,
	 *     },
	 *     link: string,
	 * }
	 */
	public function prepareAttachmentJson(Attachment $attachment, array $context, array $json)
	{
		return $json;
	}

	/**
	 * @param TContainer|null $container
	 *
	 * @return void
	 */
	public function onAssociation(Attachment $attachment, ?Entity $container = null)
	{
		return;
	}

	/**
	 * @param TContainer|null $container
	 *
	 * @return void
	 */
	public function beforeAttachmentDelete(Attachment $attachment, ?Entity $container = null)
	{
		return;
	}

	/**
	 * @param TContext $context
	 *
	 * @return TContainer|null
	 */
	public function getContainerFromContext(array $context)
	{
		$id = $this->getContainerIdFromContext($context);
		return $id ? $this->getContainerEntity($id) : null;
	}

	/**
	 * @param int|list<int> $id
	 *
	 * @return ($id is int ? TContainer|null : AbstractCollection<TContainer>)
	 */
	public function getContainerEntity($id)
	{
		return \XF::app()->findByContentType($this->contentType, $id, $this->getContainerWith());
	}

	/**
	 * @return list<string>
	 */
	public function getContainerWith()
	{
		return [];
	}

	/**
	 * @return string
	 */
	public function getContentType()
	{
		return $this->contentType;
	}

	/**
	 * @return string|\Stringable
	 */
	public function getContentTypePhrase()
	{
		return \XF::app()->getContentTypePhrase($this->contentType);
	}
}
