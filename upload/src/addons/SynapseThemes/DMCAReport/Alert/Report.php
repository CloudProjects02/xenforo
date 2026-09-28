<?php

namespace SynapseThemes\DMCAReport\Alert;

use XF\Mvc\Entity\Entity;

class Report extends \XF\Alert\AbstractHandler
{
    public function canViewContent(Entity $entity, &$error = null)
    {
        return \XF::visitor()->is_staff;
    }

    public function getTemplateName($action)
    {
        return 'public:alert_st_dmca_report_new_report';
    }

    public function getEntityWith()
    {
        return ['User'];
    }

    public function render(\XF\Entity\UserAlert $alert, \XF\Mvc\Entity\Entity $content = null)
    {
        $template = $this->getTemplateName($alert->action);
        return \XF::app()->templater()->renderTemplate($template, [
            'report' => $content,
            'alert' => $alert,
            'extra' => $alert->extra_data,
            'action' => $alert->action
        ]);
    }
} 