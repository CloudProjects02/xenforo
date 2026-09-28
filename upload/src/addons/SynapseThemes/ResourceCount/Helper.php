<?php

namespace SynapseThemes\ResourceCount;

class Helper
{
    /**
     * Gets the total resource count for use in templates
     * 
     * @return int
     */
    public static function getResourceCount()
    {
        static $count = null;
        
        if ($count === null) {
            try {
                $db = \XF::db();
                
                // Check if table exists
                $tables = $db->fetchAllColumn("SHOW TABLES LIKE 'xf_rm_resource'");
                if (empty($tables)) {
                    $count = 0;
                } else {
                    $count = $db->fetchOne("
                        SELECT COUNT(*)
                        FROM xf_rm_resource
                        WHERE resource_state = 'visible'
                    ") ?: 0;
                }
            } catch (\Exception $e) {
                $count = 0;
            }
        }
        
        return $count;
    }
} 