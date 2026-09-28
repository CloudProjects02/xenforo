<?php

namespace Jace\TokenDownloads;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function install(array $stepParams = [])
    {
        // Insert the purchasable type
        $this->db()->insert('xf_purchasable', [
            'purchasable_type_id' => 'jace_token_downloads',
            'purchasable_class' => 'Jace\TokenDownloads:TokenPackage',
            'addon_id' => $this->addOn->getAddOnId()
        ]);

        $this->schemaManager()->createTable('jace_token_package', function(Create $table)
        {
            $table->addColumn('package_id', 'int')->autoIncrement();
            $table->addColumn('package_icon_ext', 'varchar', 10)->setDefault('');
            $table->addColumn('package_icon_date', 'int')->setDefault(0);
            $table->addColumn('title', 'varchar', 100);
            $table->addColumn('description', 'text');
            $table->addColumn('cost_amount', 'decimal', '10,2')->setDefault(0);
            $table->addColumn('cost_currency', 'varchar', 3)->setDefault('USD');
            $table->addColumn('download_limit', 'int')->setDefault(0);
            $table->addColumn('active', 'tinyint', 1)->setDefault(1);
            $table->addColumn('display_order', 'int')->setDefault(0);
            $table->addColumn('payment_profile_ids', 'varbinary', 255)->nullable();
        });

        $this->schemaManager()->createTable('jace_token_purchase', function(Create $table)
        {
            $table->addColumn('purchase_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('package_id', 'int');
            $table->addColumn('purchase_date', 'int');
            $table->addColumn('tokens_total', 'int');
            $table->addColumn('tokens_remaining', 'int');
            $table->addColumn('expire_date', 'int')->nullable();
            $table->addKey(['user_id', 'package_id']);
        });

        $this->schemaManager()->createTable('jace_token_log', function(Create $table)
        {
            $table->addColumn('log_id', 'int')->autoIncrement();
            $table->addColumn('user_id', 'int');
            $table->addColumn('purchase_id', 'int');
            $table->addColumn('resource_id', 'int');
            $table->addColumn('resource_version_id', 'int');
            $table->addColumn('log_date', 'int');
            $table->addKey(['user_id', 'resource_id']);
        });

        
    }

    public function uninstall(array $stepParams = [])
        {
            $this->schemaManager()->dropTable('jace_token_log');
            $this->schemaManager()->dropTable('jace_token_purchase');
            $this->schemaManager()->dropTable('jace_token_package');
            $this->db()->delete('xf_purchasable', 'purchasable_type_id = ?', 'jace_token_downloads');
        }
}
