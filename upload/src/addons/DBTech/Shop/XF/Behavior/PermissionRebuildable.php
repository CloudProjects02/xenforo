<?php /** @noinspection PhpMissingReturnTypeInspection */

namespace DBTech\Shop\XF\Behavior;

/**
 * Class PermissionRebuildable
 * @package DBTech\Shop\XF\Entity
 */
class PermissionRebuildable extends XFCP_PermissionRebuildable
{
	public function postSave()
	{
		parent::postSave();
		
		if (
			$this->config['permissionContentType']
			&& $this->getOption('rebuildCache')
			&& $this->entity->isInsert()
			&& \XF::app()->get('app.classType') == 'Pub'
		) {
			// Public doesn't immediately run this
			$this->app()->jobManager()->runUnique('permissionRebuild', 2);
		}
	}
}