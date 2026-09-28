<?php

namespace AddonsLab\Core\StepRunner;

/**
 * Class StepRunner
 * @package AddonsLab\Core\StepRunner
 * Abstracts any scenario tasks should run multiple times (e.g. by reloading the page)
 */
class StepRunner
{
    protected $runState;

    /**
     * @var StepInterface[]
     */
    protected $steps = array();

    /**
     * @var \Closure
     * Will run before the first step
     */
    protected $install_callback;
    /**
     * @var \Closure
     * Will run after the last step
     */
    protected $uninstall_callback;

    public function __construct(RunState $runState)
    {
        $this->runState = $runState;
    }

    public function addStep(StepInterface $step)
    {
        $this->steps[$step->getId()] = $step;
    }

    /**
     * @param StepInterface[] $steps
     */
    public function addSteps(array $steps)
    {
        foreach ($steps AS $step) {
            $this->addStep($step);
        }
    }

    /**
     * @return RunState|true
     * If true is returned, the caller can assume all steps are processed
     * Otherwise RunState is returned
     */
    public function run()
    {
        // nothing to run
        if (empty($this->steps)) {
            return true;
        }

        $stepId = &$this->runState->step_id;

        // a predefined step name to stop the loop
        if ($stepId === 'done') {
            return true;
        }

        if ($stepId === false) {
            // no step run yet
            $stepId = $this->_getFirstStepId();
            if ($this->install_callback !== null) {
                $this->install_callback->call($this);
            }
        }

        $step = $this->steps[$stepId];

        if ($this->runState->total === false) {
            $this->runState->total = intval($step->getTotal());
        }

        if ($this->runState->total === 0) {
            // not items to import, step is done anyway
            return true;
        }

        $result = $step->run($this->runState);

        if ($result === true) {
            // the step completed, check for the next step
            $nextStepId = $this->_getNextStepId($stepId);
            if ($nextStepId === false) {
                // all steps are done, return true to indicate all tasks are done
                if ($this->uninstall_callback !== null) {
                    $this->uninstall_callback->call($this);
                }

                $nextStepId = 'done';
                $total=0;
            } else {
                $nextStep=$this->steps[$nextStepId];
                $total=$nextStep->getTotal();
            }

            $runStatus = $this->runState;
            $runStatus->step_id = $nextStepId;
            $runStatus->index = 0;
            $runStatus->total = $total;
            return $runStatus;
        }

        // the step should still run
        return $result;
    }

    public function getStepId($stepId)
    {
        return $this->steps[$stepId]->getId();
    }

    /**
     * @param $stepId
     * @return string|false
     * String if the next step is defined, false if no next step
     */
    public function _getNextStepId($stepId)
    {
        $stepIds = array_keys($this->steps);

        $stepIndex = array_search($stepId, $stepIds, true);

        if ($stepIndex === count($stepIds)-1) {
            // this was the last step, no next step
            return false;
        }

        return $stepIds[$stepIndex + 1];
    }

    protected function _getFirstStepId()
    {
        $array = array_keys($this->steps);
        
        return $array[0];
    }

    public function setInstallCallback(\Closure $install_callback)
    {
        $this->install_callback = $install_callback;
    }

    public function setUninstallCallback(\Closure $uninstall_callback)
    {
        $this->uninstall_callback = $uninstall_callback;
    }
}