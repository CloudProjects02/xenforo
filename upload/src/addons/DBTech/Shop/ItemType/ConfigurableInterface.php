<?php

namespace DBTech\Shop\ItemType;

interface ConfigurableInterface
{
	public function filterUserConfig(array $input = []);
	public function validateUserConfig(array &$configuration = [], &$errors = null);
	public function getConfigurationForConversation();
}