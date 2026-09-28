<?php

namespace DBTech\Security\Repository;

use DBTech\Security\Util\Str;
use XF\Mvc\Entity\Repository;

class PasswordRepository extends Repository
{
	/**
	 * @param int $length
	 * @param array $rules
	 *
	 * @return string
	 */
	public function generatePassword(int $length, array $rules): string
	{
		$rules = array_replace([
			'lowercase' => false,
			'uppercase' => false,
			'numbers' => false,
			'symbols' => false,
		], $rules);

		$passwordTemplate = [];
		if (!empty($rules['lowercase']))
		{
			$passwordTemplate[] = 'abcdefghijklmnopqrstuvwxyz';
		}

		if (!empty($rules['uppercase']))
		{
			$passwordTemplate[] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		}

		if (!empty($rules['numbers']))
		{
			$passwordTemplate[] = '0123456789';
		}

		if (!empty($rules['symbols']))
		{
			$passwordTemplate[] = '-=~!@#$%^&*()_+,./<>?;:[]{}\|';
		}

		if (empty($passwordTemplate))
		{
			// Defaults
			$passwordTemplate[] = 'abcdefghijklmnopqrstuvwxyz';
			$passwordTemplate[] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$passwordTemplate[] = '0123456789';
		}

		if ($length <= 0)
		{
			// Defaults
			$length = 12;
		}

		// Find out how many of each password rule we can use
		// (e.g. length = 8 & number of rules = 4 means 2 characters from each type)
		$each = floor($length / count($passwordTemplate));

		// How many characters we have left over
		// (e.g. length = 9 & number of rules = 4 means 2 characters from each type and 1 left over)
		$remainder = $length % count($passwordTemplate);

		$password = '';
		foreach ($passwordTemplate AS $str)
		{
			// Add a random bit from this rule set
			$password .= Str::random_str($each, $str);
		}

		if ($remainder)
		{
			// Add remainder amount from all rule sets
			$password .= Str::random_str($remainder, implode('', $passwordTemplate));
		}

		return str_shuffle($password);
	}

	/**
	 * @param string $password
	 *
	 * @return string
	 */
	public function encryptPasswordForBasicAuth(string $password): string
	{
		$salt = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz0123456789'), 0, 8);
		$length = strlen($password);
		$text = $password . '$apr1$' . $salt;
		$bin = pack('H32', md5($password . $salt . $password));

		for ($i = $length; $i > 0; $i -= 16)
		{
			$text .= substr($bin, 0, min(16, $i));
		}

		for ($i = $length; $i > 0; $i >>= 1)
		{
			$text .= ($i & 1) ? chr(0) : $password[0];
		}

		$bin = pack('H32', md5($text));
		for ($i = 0; $i < 1000; $i++)
		{
			$new = ($i & 1) ? $password : $bin;
			if ($i % 3)
			{
				$new .= $salt;
			}

			if ($i % 7)
			{
				$new .= $password;
			}

			$new .= ($i & 1) ? $bin : $password;
			$bin = pack('H32', md5($new));
		}

		$tmp = '';
		for ($i = 0; $i < 5; $i++)
		{
			$k = $i + 6;
			$j = $i + 12;
			if ($j == 16)
			{
				$j = 5;
			}

			$tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
		}

		$tmp = chr(0) . chr(0) . $bin[11] . $tmp;
		$tmp = strtr(
			strrev(substr(base64_encode($tmp), 2)),
			'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/',
			'./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'
		);

		return '$' . 'apr1' . '$' . $salt . '$' . $tmp;
	}
}