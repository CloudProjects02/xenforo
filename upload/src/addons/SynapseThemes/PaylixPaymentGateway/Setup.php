<?php

namespace SynapseThemes\PaylixPaymentGateway;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;

class Setup extends AbstractSetup
{
	use StepRunnerInstallTrait;
	use StepRunnerUpgradeTrait;
	use StepRunnerUninstallTrait;


	/**
	 * Add Payment Provider
	 */
	
	public function installStep1(){
		$this->db()->insertBulk('xf_payment_provider',[
			[
				'provider_id' => 'synapse_paylix_gateway',
				'provider_class' => 'SynapseThemes\\PaylixPaymentGateway\\Payment\\Paylix',
				'addon_id' => 'SynapseThemes/PaylixPaymentGateway'
			]
		], 'provider_id');
	}

	public function uninstallStep1(){
		$this->db()->delete('xf_payment_provider', 'provider_id IN (?)', implode(',', [
			'synapse_paylix_gateway'
		]));
	}
}