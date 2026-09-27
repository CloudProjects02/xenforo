<?php

namespace DBTech\Shop\Service\Item;

use DBTech\Shop\Entity\Item;
use XF\App;
use XF\Repository\UserRepository;
use XF\Service\AbstractService;
use XF\Service\Message\PreparerService;

class MessagePreparerService extends AbstractService
{
	protected Item $item;
	protected string $key;
	protected array $mentionedUsers = [];
	protected CreateService|EditService $service;


	/**
	 * @param App $app
	 * @param Item $item
	 * @param string $key
	 * @param CreateService|EditService $service
	 */
	public function __construct(App $app, Item $item, string $key, CreateService|EditService $service)
	{
		parent::__construct($app);
		$this->item = $item;
		$this->key = $key;
		$this->service = $service;
	}

	/**
	 * @return Item
	 */
	public function getItem(): Item
	{
		return $this->item;
	}

	/**
	 * @param bool $limitPermissions
	 *
	 * @return array
	 */
	public function getMentionedUsers(bool $limitPermissions = true): array
	{
		if ($limitPermissions)
		{
			$user = $this->item->User ?: \XF::app()->repository(UserRepository::class)->getGuestUser();
			return $user->getAllowedUserMentions($this->mentionedUsers);
		}

		return $this->mentionedUsers;
	}

	/**
	 * @param bool $limitPermissions
	 *
	 * @return array
	 */
	public function getMentionedUserIds(bool $limitPermissions = true): array
	{
		return array_keys($this->getMentionedUsers($limitPermissions));
	}

	/**
	 * @param string $message
	 * @param bool $format
	 * @param bool $checkValidity
	 *
	 * @return bool
	 * @throws \LogicException
	 * @throws \InvalidArgumentException
	 */
	public function setMessage(string $message, bool $format = true, bool $checkValidity = true): bool
	{
		$preparer = $this->getMessagePreparer($format);
		$this->item->set($this->key, $preparer->prepare($message, $checkValidity));
		//		$this->item->embed_metadata = $preparer->getEmbedMetadata();

		$this->mentionedUsers = $preparer->getMentionedUsers();

		return $preparer->pushEntityErrorIfInvalid($this->item);
	}

	/**
	 * @param bool $format
	 *
	 * @return PreparerService
	 */
	protected function getMessagePreparer(bool $format = true): PreparerService
	{
		$options = \XF::app()->options();

		// If we have a message length, then set the image/media limit based on that.
		// Otherwise, place very high limits on each that are unlikely to ever legitimately be hit.
		if ($options->messageMaxLength)
		{
			$maxImages = $options->messageMaxImages;
			$maxMedia = $options->messageMaxMedia;
		}
		else
		{
			$maxImages = 100;
			$maxMedia = 30;
		}

		$preparer = \XF::app()->service(PreparerService::class, 'dbtech_shop_item', $this->item);
		$preparer->setConstraint('maxLength', $options->messageMaxLength);
		$preparer->setConstraint('maxImages', $maxImages);
		$preparer->setConstraint('maxMedia', $maxMedia);

		if (!$format)
		{
			$preparer->disableAllFilters();
		}

		return $preparer;
	}

	/**
	 *
	 */
	public function checkForSpam(): void
	{
		$item = $this->item;

		$user = $item->User ?: \XF::app()->repository(UserRepository::class)->getGuestUser($item->username);

		$message = $item->title . "\n" .
			$this->service->getTagline() . "\n" .
			$item->get($this->key);

		$checker = \XF::app()->spam()->contentChecker();
		$checker->check($user, $message, [
			'permalink' => \XF::app()->router('public')->buildLink('canonical:dbtech-shop', $item),
			'content_type' => 'dbtech_shop_item',
		]);

		$decision = $checker->getFinalDecision();
		switch ($decision)
		{
			case 'moderated':
				$item->item_state = 'moderated';
				break;

			case 'denied':
				$checker->logSpamTrigger('dbtech_shop_item', null);
				$item->error(\XF::phrase('your_content_cannot_be_submitted_try_later'));
				break;
		}
	}

	/**
	 *
	 */
	public function beforeInsert()
	{
	}

	/**
	 *
	 */
	public function beforeUpdate()
	{
	}

	/**
	 *
	 */
	public function afterInsert()
	{
	}

	/**
	 *
	 */
	public function afterUpdate()
	{
	}
}