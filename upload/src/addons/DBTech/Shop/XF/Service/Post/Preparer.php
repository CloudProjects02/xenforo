<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Service\Post;

/**
 * Class Preparer
 *
 * @package DBTech\Shop\XF\Service\Post
 */
class Preparer extends XFCP_Preparer
{
	/**
	 * @throws \XF\PrintableException
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
		) {
			/** @var \DBTech\Shop\Entity\Currency[]|\XF\Mvc\Entity\ArrayCollection $currencies */
			$currencies = $currencies->filter(function (\DBTech\Shop\Entity\Currency $currency) use ($post)
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
				) {
					return null;
				}
				
				return $currency;
			});
			
			$currencyRepo = $this->repository('DBTech\Shop:Currency');
			foreach ($currencies as $currency)
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