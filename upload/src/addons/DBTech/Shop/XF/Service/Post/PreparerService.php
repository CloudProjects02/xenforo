<?php

/** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Service\Post;

use DBTech\Shop\Entity\Currency;
use DBTech\Shop\Repository\CurrencyRepository;
use XF\PrintableException;

/**
 * @extends \XF\Service\Post\PreparerService
 */
class PreparerService extends XFCP_PreparerService
{
	/**
	 * @throws PrintableException
	 */
	public function afterInsert()
	{
		parent::afterInsert();

		$post = $this->post;

		$container = \XF::app()->container();
		if ($post->user_id
			&& $post->User
			&& isset($container['dbtechShop.currencies'])
			&& $currencies = $container['dbtechShop.currencies']
		)
		{
			/** @var \XF\Mvc\Entity\AbstractCollection<\DBTech\Shop\Entity\Currency> $currencies */
			$currencies = $currencies->filter(function (Currency $currency) use ($post)
			{
				if (!$currency->isActive())
				{
					return null;
				}

				if ($currency->isIntegrated())
				{
					return null;
				}

				if (
					($post->isFirstPost() && !$currency->per_thread)
					|| (!$post->isFirstPost() && !$currency->per_reply)
				)
				{
					return null;
				}

				return $currency;
			});

			$currencyRepo = \XF::app()->repository(CurrencyRepository::class);
			foreach ($currencies AS $currency)
			{
				if ($post->isFirstPost())
				{
					$currencyRepo->addCurrencyAmount(
						$currency,
						'perthread',
						$currency->per_thread,
						$post->User,
						'thread',
						$post->Thread->thread_id,
						'',
						false,
						$this->logIp
					);
				}
				else
				{
					$currencyRepo->addCurrencyAmount(
						$currency,
						'perreply',
						$currency->per_reply,
						$post->User,
						'post',
						$post->post_id,
						'',
						false,
						$this->logIp
					);
				}
			}
		}
	}
}