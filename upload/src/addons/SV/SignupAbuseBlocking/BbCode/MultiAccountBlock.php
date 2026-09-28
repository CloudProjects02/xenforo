<?php

namespace SV\SignupAbuseBlocking\BbCode;

use SV\SignupAbuseBlocking\Entity\LogEvent as LogEventEntity;
use SV\SignupAbuseBlocking\XF\Entity\User as ExtendedUserEntity;
use SV\StandardLib\Helper;
use XF\BbCode\Renderer\AbstractRenderer;
use XF\BbCode\Renderer\EditorHtml;
use XF\BbCode\Renderer\Html;
use XF\BbCode\Renderer\SimpleHtml;

class MultiAccountBlock
{
    /**  @var Html */
    protected $renderer;
    /** @var string */
    protected $type;

    public function __construct(AbstractRenderer $renderer, string $type)
    {
        $this->renderer = $renderer;
        $this->type = $type;
    }

    public function bindToRenderer()
    {
        if ($this->renderer instanceof EditorHtml)
        {
            $this->renderer->addTag(
                'multi_account_block',
                [
                    'replace'             => null,
                    'callback'            => null,
                    'trimAfter'           => 0,
                    'stopBreakConversion' => false
                ]
            );
        }
        else if ($this->renderer instanceof Html)
        {
            $this->renderer->addTag(
                'multi_account_block',
                [
                    'callback' => [$this, 'renderTagMultiAccountBlock']
                ]
            );
        }
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function renderTagMultiAccountBlock(array $children, $option, array $tag, array $options, Html $renderer)
    {
        /** @var ExtendedUserEntity $visitor */
        $visitor = \XF::visitor();
        if (!$visitor->canViewMultiAccountReport())
        {
            return '';
        }

        $text = $renderer->renderSubTreePlain($children);
        if (!$text)
        {
            return '';
        }
        $data = @\json_decode($text, true);
        if (!\is_array($data) || empty($data['event']))
        {
            return '';
        }

        $event = Helper::find(LogEventEntity::class, $data['event']);
        if (!$event)
        {
            return '';
        }

        $macroName = $this->renderer instanceof SimpleHtml ? 'renderSimpleHtml' : 'renderHtml';

        return \XF::app()->templater()->renderMacro('public:sv_multiple_account_macros', $macroName, [
            'event'         => $event,
            'primaryUser'   => $event->User,
            'primaryUserId' => $event->triggering_user_id,
        ]);
    }
}
 		 		 	   	 	  		  				  	 	 		 	 	   	    	 	  	 	  	 	 	 			    	 				 		    			 	 	  		  		  	 	 			 	   		  	 	 	  	 	   	 	 	 
