<?php

namespace AddonsLab\ContentHandler;

/**
 * Class AbstractContentHandler
 * @package AddonsLab\ContentHandler
 * Typical implementation of @AbstractContentHandler that stores content information in $content property;
 */
abstract class AbstractContentHandler implements ContentHandlerInterface
{
    protected $content;

    abstract protected function _postSetContextFromContentId($contentId);

    public function setContextFromContentId($contentId)
    {
        if ($content = $this->getContentById($contentId)) {
            $this->content = $content;
            $this->_postSetContextFromContentId($contentId);
        }

        return $this->content;
    }

    public function setContent($content)
    {
        $this->content = $content;
        $this->_postSetContextFromContentId($this->getContentId());
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContentData($contentDataKey, $contentDataValue)
    {
        $this->content[$contentDataKey] = $contentDataValue;
    }


}