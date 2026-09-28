<?php
namespace AddonsLab\Core\Service;

/**
 * Class FlashMessageProvider
 * @package AddonsLab\Core\Service
 * Container for one-time message storage
 */
class FlashMessageProvider
{
    protected $message_queue=array();
    protected $message_map=array();
    
    public function addMessage($message, $key='')
    {
        if($key) {
            $this->message_map[$key]=$message;
        } else {
            $this->message_queue[]=$message;
        }
    }
    
    public function getMessage($key='')
    {
        if(!$key) {
            if(empty($this->message_queue)) {
                return false;
            }
            
            $message=array_pop($this->message_queue);
            return $message;
        }
        
        if(!isset($this->message_map[$key])) {
            return false;
        }
        
        $message=$this->message_map[$key];
        
        unset($this->message_map[$key]);
        
        return $message;
    }
}