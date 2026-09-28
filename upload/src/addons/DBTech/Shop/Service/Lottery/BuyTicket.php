<?php

namespace DBTech\Shop\Service\Lottery;

use DBTech\Shop\Entity\Lottery;
use DBTech\Shop\Entity\LotteryTicket;

/**
 * Class BuyTicket
 *
 * @package DBTech\Shop\Service\Lottery
 */
class BuyTicket extends \XF\Service\AbstractService
{
	use \XF\Service\ValidateAndSavableTrait;
	
	/** @var Lottery */
	protected $lottery;
	
	/** @var LotteryTicket */
	protected $lotteryTicket;
	
	/** @var \XF\Entity\User|null */
	protected $user;
	
	/** @var array */
	protected $numbers = [];
	
	
	/**
	 * Complete constructor.
	 *
	 * @param \XF\App $app
	 * @param Lottery $lottery
	 */
	public function __construct(\XF\App $app, Lottery $lottery)
	{
		parent::__construct($app);
		$this->setUser(\XF::visitor());
		$this->setLottery($lottery);
		
		$this->setDefaults();
	}
	
	/**
	 *
	 */
	protected function setDefaults(): void
	{
	}

	/**
	 * @param \XF\Entity\User|null $user
	 *
	 * @return $this
	 */
	public function setUser(?\XF\Entity\User $user = null): BuyTicket
	{
		$this->user = $user;

		return $this;
	}
	
	/**
	 * @return null|\XF\Entity\User
	 */
	public function getUser(): ?\XF\Entity\User
	{
		return $this->user;
	}

	/**
	 * @param \DBTech\Shop\Entity\Lottery|null $lottery
	 *
	 * @return $this
	 */
	public function setLottery(?Lottery $lottery = null): BuyTicket
	{
		$this->lottery = $lottery;

		return $this;
	}
	
	/**
	 * @return null|Lottery
	 */
	public function getLottery(): ?Lottery
	{
		return $this->lottery;
	}

	/**
	 * @param \DBTech\Shop\Entity\LotteryTicket|null $lotteryTicket
	 *
	 * @return $this
	 */
	public function setLotteryTicket(?LotteryTicket $lotteryTicket = null): BuyTicket
	{
		$this->lotteryTicket = $lotteryTicket;

		return $this;
	}
	
	/**
	 * @return null|LotteryTicket
	 */
	public function getLotteryTicket(): ?LotteryTicket
	{
		return $this->lotteryTicket;
	}

	/**
	 * @param array $numbers
	 *
	 * @return $this
	 */
	public function setNumbers(array $numbers = []): BuyTicket
	{
		$this->numbers = $numbers;

		return $this;
	}
	
	/**
	 * @return array
	 */
	public function getNumbers(): array
	{
		return $this->numbers;
	}
	
	/**
	 *
	 */
	protected function finalSetup(): void
	{
//		if (empty($this->numbers))
//		{
//			 TODO: Random numbers
//		}
	}
	
	/**
	 * @return array
	 * @throws \Exception
	 */
	protected function _validate(): array
	{
		$this->finalSetup();
		
		$errors = [];
		
		$lottery = $this->lottery;
		
		$canBuyTicket = \XF::asVisitor($this->user, function () use ($lottery): bool
		{
			return $lottery->canBuyTicket();
		});
		if (!$canBuyTicket)
		{
			$errors[] = \XF::phraseDeferred('dbtech_shop_cannot_buy_ticket_for_this_lottery');
		}
		
		$numbers = $this->numbers;
		
		$usedNumbers = [];
		for ($i = 0; $i < $lottery->numbers['main']; $i++)
		{
			if (empty($numbers[$i]))
			{
				// Too large number
				$errors[] = \XF::phraseDeferred('dbtech_shop_missing_lottery_ticket_numbers');
				break;
			}
			
			if (array_key_exists($numbers[$i], $usedNumbers))
			{
				// Used same number several times
				$errors[] = \XF::phraseDeferred('dbtech_shop_cannot_reuse_numbers');
				break;
			}
			
			if ($numbers[$i] > $lottery->numbers['total'])
			{
				// Too large number
				$errors[] = \XF::phraseDeferred('dbtech_shop_too_large_number');
				break;
			}
			
			// Used number
			$usedNumbers[$numbers[$i]] = true;
		}
		
		if (count($numbers) > $lottery->numbers['main'])
		{
			$errors[] = \XF::phraseDeferred('dbtech_shop_too_many_numbers_max_x', [
				'max' => $lottery->numbers['main']
			]);
		}
		
		return $errors;
	}
	
	/**
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws \XF\PrintableException
	 */
	protected function _save(): void
	{
		$lottery = $this->lottery;
		$user = $this->user;
		
		$db = $this->db();
		$db->beginTransaction();
		
		$ticket = $this->em()->create('DBTech\Shop:LotteryTicket');
		$ticket->lottery_id = $lottery->lottery_id;
		$ticket->user_id = $user->user_id;
		$ticket->draw_date = $lottery->next_draw_date;
		$ticket->numbers = $this->numbers;
		$ticket->save(true, false);
		
		$ticket->hydrateRelation('Lottery', $lottery);
		$ticket->hydrateRelation('User', $user);
		
		$this->setLotteryTicket($ticket);
		
		/** @var \DBTech\Shop\Repository\Currency $currencyRepo */
		$currencyRepo = $this->repository('DBTech\Shop:Currency');
		
		$currencyRepo->removeCurrencyAmount(
			$lottery->Currency,
			'lotteryticket',
			$lottery->ticket_price,
			$user,
			'dbtech_shop_ticket',
			$ticket->lottery_ticket_id
		);
		
		$this->afterPurchase();
		
		$db->commit();
	}
	
	/**
	 *
	 */
	public function afterPurchase(): void
	{
		$this->lottery->fastUpdate('tickets_sold', $this->lottery->tickets_sold + 1);
	}
}