<?php

namespace AddonsLab\Core\Xf2;

use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Entity\Widget;

abstract class AbstractCoreSetup extends \XF\AddOn\AbstractSetup
{
    use /** @noinspection TraitsPropertiesConflictsInspection */
        StepRunnerInstallTrait
    {
        install as public _traitInstall;
    }

    use StepRunnerUpgradeTrait
    {
        // access the upgrade method of the trait under another name as we need to override the method ourselves
        upgrade as public _stepUpgrade;
    }

    use StepRunnerUninstallTrait
    {
        uninstall as public _traitUninstall;
    }


    /**
     * Provided by AddonsLab core package, used for automatic creation and synchronization of entity tables
     */
    use SetupTrait;

    public function install(array $stepParams = [])
    {
        $stepParams = array_replace([
            'step' => 0
        ], $stepParams);

        if ($stepParams['step'] === 0)
        {
            // the first step of upgrade, assert entity tables exist and are synced
            $this->_installUpgradeCommon();
        }

        // handle the call to the parent from XF trait
        return $this->_traitInstall($stepParams);
    }

    public function upgrade(array $stepParams = [])
    {
        $stepParams = $this->getStepParams($stepParams);
        if ($stepParams['step'] === 0)
        {
            $this->_installUpgradeCommon();
        }

        // handle the call to the parent from XF trait
        return $this->_stepUpgrade($stepParams);
    }

    public function uninstall(array $stepParams = [])
    {
        $installer = new Installer();
        $installer->deleteTables($this->getCreateMapping());
        $installer->deleteColumns($this->getAlterMapping());
        $this->_traitUninstall($stepParams);
    }


    protected function _installUpgradeCommon()
    {
        $this->assertCreateTable($this->getCreateMapping());
        $this->assertAlterTable($this->getAlterMapping());
    }

    protected function _assertWidget($key, $addonId, $class, $title, $positions = []): Widget
    {
        /** @var Widget $widget */
        $widget = \XF::finder('XF:Widget')->where('widget_key', $key)->fetchOne();
        if ($widget === null)
        {
            /** @var \XF\Entity\WidgetDefinition $widgetPosition */
            $widgetPosition = \XF::finder('XF:WidgetDefinition')
                ->where('addon_id', $addonId)
                ->where('definition_class', $class)
                ->fetchOne();

            if ($widgetPosition === null)
            {
                throw new \RuntimeException("Widget definition $class in addon $addonId could not be found.");
            }

            $this->createWidget(
                $key,
                $widgetPosition->definition_id,
                ['positions' => $positions],
                $title
            );

            $widget = \XF::finder('XF:Widget')->where('widget_key', $key)->fetchOne();
        }

        return $widget;
    }

    public function recreateTableFromMapping(string $tableName)
    {
        $this->schemaManager()->dropTable($tableName);
        $callback = $this->getCreateMapping()[$tableName]['install'];
        $this->schemaManager()->createTable($tableName, $callback);
    }
}
