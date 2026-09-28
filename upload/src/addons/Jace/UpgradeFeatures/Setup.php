<?php

namespace Jace\UpgradeFeatures;

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

    public function installStep1()
    {
        // Create upgrade feature categories table
        $this->schemaManager()->createTable('xf_upgrade_feature_category', function(Create $table)
        {
            $table->addColumn('category_id', 'int')->autoIncrement();
            $table->addColumn('display_order', 'int')->setDefault(0);
            $table->addColumn('display_mode', 'enum')->values(['visible', 'step', 'hidden'])->setDefault('visible');
            $table->addColumn('overwrite', 'tinyint', 1)->setDefault(0);
            $table->addPrimaryKey('category_id');
            $table->addKey('display_order');
        });
    }

    public function installStep2()
    {
        // Create upgrade features table
        $this->schemaManager()->createTable('xf_upgrade_feature', function(Create $table)
        {
            $table->addColumn('feature_id', 'int')->autoIncrement();
            $table->addColumn('category_id', 'int')->setDefault(0);
            $table->addColumn('title', 'varchar', 100);
            $table->addColumn('description', 'text');
            $table->addColumn('feature_type', 'enum')->values(['boolean', 'numeric', 'icon', 'userbar']);
            $table->addColumn('default_value', 'text')->nullable();
            $table->addColumn('display_order', 'int')->setDefault(10);
            $table->addColumn('active', 'tinyint', 1)->setDefault(1);
            $table->addPrimaryKey('feature_id');
            $table->addKey(['category_id', 'display_order']);
            $table->addKey('display_order');
        });
    }

    public function installStep3()
    {
        // Create upgrade feature values table
        $this->schemaManager()->createTable('xf_upgrade_feature_value', function(Create $table)
        {
            $table->addColumn('user_upgrade_id', 'int');
            $table->addColumn('feature_id', 'int');
            $table->addColumn('feature_value', 'text')->nullable();
            $table->addPrimaryKey(['user_upgrade_id', 'feature_id']);
            $table->addKey('feature_id');
            $table->addKey('user_upgrade_id');
        });
    }

    public function installStep4()
    {
        // Create upgrade feature icons table
        $this->schemaManager()->createTable('xf_upgrade_feature_icon', function(Create $table)
        {
            $table->addColumn('feature_id', 'int');
            $table->addColumn('user_upgrade_id', 'int');
            $table->addColumn('upload_date', 'int')->setDefault(0);
            $table->addColumn('icon_ext', 'varchar', 10)->setDefault('');
            $table->addColumn('icon_date', 'int')->setDefault(0);
            $table->addPrimaryKey(['feature_id', 'user_upgrade_id']);
            $table->addKey('user_upgrade_id');
            $table->addKey('upload_date');
        });
    }

    public function installStep5()
    {
        // Create upgrade feature userbar table
        $this->schemaManager()->createTable('xf_upgrade_feature_userbar', function(Create $table)
        {
            $table->addColumn('feature_id', 'int');
            $table->addColumn('user_upgrade_id', 'int');
            $table->addColumn('upload_date', 'int')->setDefault(0);
            $table->addColumn('icon_ext', 'varchar', 10)->setDefault('');
            $table->addColumn('icon_date', 'int')->setDefault(0);
            $table->addPrimaryKey(['feature_id', 'user_upgrade_id']);
            $table->addKey('user_upgrade_id');
            $table->addKey('upload_date');
        });
    }

    public function installStep6()
    {
        // Insert default category
        $this->db()->insert('xf_upgrade_feature_category', [
            'display_order' => 10,
            'display_mode' => 'visible',
            'overwrite' => 0
        ]);

        $defaultCategoryId = $this->db()->lastInsertId();

        // Insert default features
        $this->db()->insert('xf_upgrade_feature', [
            'category_id' => $defaultCategoryId,
            'title' => 'Email Limit',
            'description' => 'Maximum number of emails per month',
            'feature_type' => 'numeric',
            'default_value' => '1000',
            'display_order' => 10
        ]);

        $this->db()->insert('xf_upgrade_feature', [
            'category_id' => $defaultCategoryId,
            'title' => 'Private Messages',
            'description' => 'Can send private messages',
            'feature_type' => 'boolean',
            'default_value' => '1',
            'display_order' => 20
        ]);

        $this->db()->insert('xf_upgrade_feature', [
            'category_id' => $defaultCategoryId,
            'title' => 'Award Icon',
            'description' => 'Custom award icon for the upgrade',
            'feature_type' => 'icon',
            'default_value' => null,
            'display_order' => 30
        ]);

        $this->db()->insert('xf_upgrade_feature', [
            'category_id' => $defaultCategoryId,
            'title' => 'Custom Userbar',
            'description' => 'Custom userbar image for the upgrade',
            'feature_type' => 'userbar',
            'default_value' => null,
            'display_order' => 40
        ]);
    }

    public function installStep7()
    {
        // Insert default phrases for the default category
        $phrases = [
            'upgrade_features_cat_title.1' => 'Default Features',
            'upgrade_features_cat_desc.1' => 'Standard upgrade features available to all upgrades.',
            'upgrade_features_cat_title.uncategorized' => 'Uncategorized',
            'upgrade_features_cat_desc.uncategorized' => 'Features without a specific category.',
        ];

        foreach ($phrases as $title => $text)
        {
            $this->db()->insert('xf_phrase', [
                'language_id' => 0,
                'title' => $title,
                'phrase_text' => $text,
                'addon_id' => 'Jace/UpgradeFeatures'
            ]);
        }
    }

    // Upgrade methods for future versions
    public function upgrade1000010Step1()
    {
        // Example upgrade step - add new column to feature table
        $this->schemaManager()->alterTable('xf_upgrade_feature', function(Alter $table)
        {
            $table->addColumn('extra_data', 'blob')->nullable()->after('default_value');
        });
    }

    // Uninstall steps (reverse order)
    public function uninstallStep1()
    {
        // Clean up any uploaded files
        \XF\Util\File::deleteAbstractedDirectory('data://jace/upgradefeatures');
    }

    public function uninstallStep2()
    {
        // Remove phrases
        $this->db()->delete('xf_phrase', "addon_id = 'Jace/UpgradeFeatures'");
    }

    public function uninstallStep3()
    {
        $this->schemaManager()->dropTable('xf_upgrade_feature_userbar');
    }

    public function uninstallStep4()
    {
        $this->schemaManager()->dropTable('xf_upgrade_feature_icon');
    }

    public function uninstallStep5()
    {
        $this->schemaManager()->dropTable('xf_upgrade_feature_value');
    }

    public function uninstallStep6()
    {
        $this->schemaManager()->dropTable('xf_upgrade_feature');
    }

    public function uninstallStep7()
    {
        $this->schemaManager()->dropTable('xf_upgrade_feature_category');
    }
}