<?php
namespace AddonsLab\Core\StepRunner;

interface StepInterface
{
    /**
     * @return string
     */
    public function getId();

    /**
     * @param RunState $runState
     * @return RunState|true
     * If true is returned, the step is done
     * otherwise next run status should be returned
     */
    public function run(RunState $runState);

    /**
     * @return int Total number of items to import
     */
    public function getTotal();
}