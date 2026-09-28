<?php

namespace SynapseThemes\DMCAReport\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class Report extends Entity
{
    public function getContentUrls()
    {
        return $this->getValue('content_urls') ?: [];
    }

    public function setContentUrls($urls)
    {
        if (is_string($urls)) {
            $urls = array_filter(explode("\n", $urls));
        }
        $this->setValue('content_urls', array_values(array_filter($urls)));
    }

    public function getOriginalContentUrls()
    {
        return $this->getValue('original_content_urls') ?: [];
    }

    public function setOriginalContentUrls($urls)
    {
        if (is_string($urls)) {
            $urls = array_filter(explode("\n", $urls));
        }
        $this->setValue('original_content_urls', array_values(array_filter($urls)));
    }

    protected function _preSave()
    {
        if ($this->isChanged('content_urls') && is_string($this->getValue('content_urls'))) {
            $this->setContentUrls($this->getValue('content_urls'));
        }
        if ($this->isChanged('original_content_urls') && is_string($this->getValue('original_content_urls'))) {
            $this->setOriginalContentUrls($this->getValue('original_content_urls'));
        }
    }

    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_st_dmca_report';
        $structure->shortName = 'SynapseThemes\DMCAReport:Report';
        $structure->primaryKey = 'report_id';
        
        $structure->columns = [
            'report_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 255],
            'user_id' => ['type' => self::UINT, 'default' => 0],
            'username' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
            'email' => ['type' => self::STR, 'maxLength' => 120, 'default' => ''],
            'ip_address' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
            'copyright_owner' => ['type' => self::STR, 'required' => true],
            'dmca_type' => ['type' => self::STR, 'default' => 'copyright'],
            'content_urls' => ['type' => self::JSON_ARRAY, 'required' => true],
            'original_content_urls' => ['type' => self::JSON_ARRAY, 'required' => true],
            'content_description' => ['type' => self::STR, 'required' => true],
            'submit_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'status' => ['type' => self::STR, 'default' => 'pending'],
            'admin_notes' => ['type' => self::STR, 'default' => ''],
            'review_date' => ['type' => self::UINT, 'default' => 0],
            'reviewer_id' => ['type' => self::UINT, 'default' => 0]
        ];
        
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ],
            'Reviewer' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => [['user_id', '=', '$reviewer_id']],
                'primary' => true
            ]
        ];
        
        $structure->getters = [
            'content_urls' => true,
            'original_content_urls' => true
        ];

        $structure->setters = [
            'content_urls' => 'setContentUrls',
            'original_content_urls' => 'setOriginalContentUrls'
        ];
        
        $structure->options = [];
        
        return $structure;
    }
} 