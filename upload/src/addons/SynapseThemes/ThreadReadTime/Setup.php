<?php

namespace SynapseThemes\ThreadReadTime;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function install(array $stepParams = [])
    {
        $this->schemaManager()->alterTable('xf_thread', function(\XF\Db\Schema\Alter $table)
        {
            $table->addColumn('synapse_read_time', 'int')->setDefault(0);
        });

        $this->rebuildThreadReadTimes();
    }

    public function uninstall(array $stepParams = [])
    {
        $this->schemaManager()->alterTable('xf_thread', function(\XF\Db\Schema\Alter $table)
        {
            $table->dropColumns(['synapse_read_time']);
        });
    }

    public function rebuildThreadReadTimes()
    {
        $db = $this->db();
        
        // Process threads in batches to avoid memory issues
        $threadIds = $db->fetchAllColumn("
            SELECT thread_id 
            FROM xf_thread 
            WHERE synapse_read_time = 0
        ");

        if (!$threadIds)
        {
            return;
        }

        foreach ($threadIds as $threadId)
        {
            /** @var \XF\Entity\Thread $thread */
            $thread = \XF::em()->find('XF:Thread', $threadId);
            if ($thread)
            {
                /** @var \SynapseThemes\ThreadReadTime\Service\ReadTime $readTimeService */
                $readTimeService = \XF::service('SynapseThemes\ThreadReadTime:ReadTime');
                $readTime = $readTimeService->calculateForThread($thread);

                $db->update(
                    'xf_thread',
                    ['synapse_read_time' => $readTime],
                    'thread_id = ?',
                    $threadId
                );
            }
        }
    }
} 