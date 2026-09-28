<?php
/** 
* @package [AddonsLab] Resource Filter
* @author AddonsLab
* @license https://addonslab.com/
* @link https://addonslab.com/
* @version 3.6.1
This software is furnished under a license and may be used and copied
only  in  accordance  with  the  terms  of such  license and with the
inclusion of the above copyright notice.  This software  or any other
copies thereof may not be provided or otherwise made available to any
other person.  No title to and  ownership of the  software is  hereby
transferred.                                                         
                                                                     
You may not reverse  engineer, decompile, defeat  license  encryption
mechanisms, or  disassemble this software product or software product
license.  AddonsLab may terminate this license if you don't comply with
any of these terms and conditions.  In such event,  licensee  agrees 
to return licensor  or destroy  all copies of software  upon termination 
of the license.
*/


namespace AL\ResourceFilter;

use AddonsLab\Core\Xf2\SetupTrait;
use AL\FilterFramework\FilterSetupInterface;
use AL\FilterFramework\FrameworkSetup;
use AL\ResourceFilter\Licensing\Engine\Xf2;
use XF\AddOn\AbstractSetup;
use XF\Db\Exception;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Column;
use XF\Db\Schema\Create;
use XF\Entity\Widget;
use XF\Entity\WidgetDefinition;

class Setup extends \AddonsLab\Core\Xf2\AbstractCoreSetup implements FilterSetupInterface
{
    use FrameworkSetup;

    public function install(array $stepParams = [])
    {
        parent::install($stepParams);
        $this->assertLicense(false);
    }

    public function upgrade(array $stepParams = [])
    {
        parent::upgrade($stepParams);
        $this->assertLicense(true);
    }

    protected function _installUpgradeCommon()
    {
        parent::_installUpgradeCommon();
        $this->assertFilterWidget();
    }

    public function uninstall(array $stepParams = [])
    {
        // $this->deleteColumns($this->_getResourceAlterMapping([]));
        $this->deleteWidget('default_resource_filter_widget');
    }

    public function getCreateMapping()
    {
        return $this->_getCreateMapping();
    }

    public function assertFilterWidget()
    {
        $widget = \XF::finder('XF:Widget')->where('widget_key', 'default_resource_filter_widget')->fetchOne();
        if ($widget === null)
        {
            /** @var WidgetDefinition $widgetPosition */
            $widgetPosition = \XF::finder('XF:WidgetDefinition')
                ->where('addon_id', 'AL/ResourceFilter')
                ->where('definition_class', '\AL\ResourceFilter\Widget\ResourceFilter')
                ->fetchOne();

            if ($widgetPosition === null)
            {
                return;
            }

            $this->createWidget(
                'default_resource_filter_widget',
                $widgetPosition->definition_id,
                ['positions' => [
                    'xfrm_category_sidenav' => 1,
                    'xfrm_overview_sidenav' => 1,
                ]],
                'Resource Filter'
            );
        }
    }

    public function getAlterMapping()
    {
        $alter = $this->_getAlterMapping();

        return $this->_getResourceAlterMapping($alter);
    }

    protected function _getResourceAlterMapping(array $alter)
    {
        $alter['xf_rm_category'][] = [
            'column' => 'filter_location',
            'install' => function (Column $column)
            {
                $column->type('enum')->values(['', 'popup', 'sidebar', 'above_resource_list'])->setDefault('');
            },
            'uninstall' => function ($columnName, Alter $table)
            {
                $table->dropColumns([$columnName]);
            }
        ];

        $alter['xf_rm_category'][] = [
            'column' => 'effective_filter_location',
            'install' => function (Column $column)
            {
                $column->type('enum')->values(['', 'popup', 'sidebar', 'above_resource_list'])->setDefault('');
            },
            'uninstall' => function ($columnName, Alter $table)
            {
                $table->dropColumns([$columnName]);
            }
        ];

        $alter['xf_rm_category'][] = [
            'column' => 'field_column_cache',
            'install' => function (Column $column)
            {
                $column->type('mediumblob')->nullable();
            },
            'uninstall' => function ($columnName, Alter $table)
            {
                $table->dropColumns([$columnName]);
            }
        ];

        return $alter;
    }

    public function assertLicense($validateNow = true)
    {
        Xf2::installDrivers();

        if ($validateNow && \XF::finder('XF:AddOn')->whereId('AL/ResourceFilter')->fetchOne())
        {
            try
            {
                $licenseKey = App::getOptionProvider()->getOption('alrf_license_key');
            } catch (\Exception $exception)
            {
                // the option does not exist yet
                return true;
            }

            try
            {
                $licenseValidationService = App::getLicenseValidationService('\AL\ResourceFilter\Licensing\Engine\Xf2');
                $licenseValidationService->licenseReValidation(
                    $licenseKey,
                    false
                );
            } catch (Exception $dbException)
            {
                // ignore DB exceptions, in case the table is missing etc.
            } catch (\Exception $ex)
            {
                throw new \RuntimeException($ex->getMessage(), true);
            }
        }

        return true;
    }
}
