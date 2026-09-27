<?php

namespace DBTech\Shop\Service\Lottery;

use DBTech\Shop\Entity\Lottery;
use DBTech\Shop\Entity\LotteryTicket;
use DBTech\Shop\Repository\CurrencyRepository;
use XF\App;
use XF\Entity\User;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;

class BuyTicketService extends AbstractService
{
	use ValidateAndSavableTrait;

	protected Lottery $lottery;
	protected ?LotteryTicket $lotteryTicket = null;
	protected User $user;
	protected array $numbers = [];


	/**
	 * @param App $app
	 * @param Lottery $lottery
	 */
	public function __construct(App $app, Lottery $lottery)
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
	 * @param User|null $user
	 *
	 * @return $this
	 */
	public function setUser(?User $user = null): BuyTicketService
	{
		$this->user = $user;

		return $this;
	}

	/**
	 * @return null|User
	 */
	public function getUser(): ?User
	{
		return $this->user;
	}

	/**
	 * @param Lottery|null $lottery
	 *
	 * @return $this
	 */
	public function setLottery(?Lottery $lottery = null): BuyTicketService
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
	 * @param LotteryTicket|null $lotteryTicket
	 *
	 * @return $this
	 */
	public function setLotteryTicket(?LotteryTicket $lotteryTicket = null): BuyTicketService
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
	public function setNumbers(array $numbers = []): BuyTicketService
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
				'max' => $lottery->numbers['main'],
			]);
		}

		return $errors;
	}

	/**
	 * @throws \LogicException
	 * @throws \Exception
	 * @throws PrintableException
	 */
	protected function _save(): void
	{
		$lottery = $this->lottery;
		$user = $this->user;

		$db = $this->db();
		$db->beginTransaction();

		$ticket = \XF::app()->em()->create(LotteryTicket::class);
		$ticket->lottery_id = $lottery->lottery_id;
		$ticket->user_id = $user->user_id;
		$ticket->draw_date = $lottery->next_draw_date;
		$ticket->numbers = $this->numbers;
		$ticket->save(true, false);

		$ticket->hydrateRelation('Lottery', $lottery);
		$ticket->hydrateRelation('User', $user);

		$this->setLotteryTicket($ticket);

		$currencyRepo = \XF::app()->repository(CurrencyRepository::class);

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