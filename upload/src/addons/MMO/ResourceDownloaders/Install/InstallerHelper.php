<?php

namespace MMO\ResourceDownloaders\Install;

trait InstallerHelper
{
    /**
     * @param string $old
     * @param string $new
     * @param bool $takeOwnership
     *
     * @throws \XF\PrintableException
     */
    protected function renameOption($old, $new, $takeOwnership = false)
    {
        /** @var \XF\Entity\Option $optionOld */
        $optionOld = \XF::finder('XF:Option')->whereId($old)->fetchOne();
        /** @var \XF\Entity\Option $optionNew */
        $optionNew = \XF::finder('XF:Option')->whereId($new)->fetchOne();
        if ($optionOld && !$optionNew)
        {
            $optionOld->option_id = $new;
            if ($takeOwnership)
            {
                $optionOld->addon_id = $this->addOn->getAddOnId();
            }
            $optionOld->saveIfChanged();
        }
        else if ($takeOwnership && $optionOld && $optionNew)
        {
            $optionNew->option_value = $optionOld->option_value;
            $optionNew->addon_id = $this->addOn->getAddOnId();
            $optionNew->save();
            $optionOld->delete();
        }
    }
}
 		    	 			 									    		 		   	 	  		 		 	    				 		 	  	 				  	 		 	  	 			 	 		    		 		    		   	 	 		   				 					 					 	
