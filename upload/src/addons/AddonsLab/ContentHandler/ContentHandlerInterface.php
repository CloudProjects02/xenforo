<?php
namespace AddonsLab\ContentHandler;

interface ContentHandlerInterface
{
	/**
	 * @return string
	 * Unique string identifier for the handler
     * Usually return XenForo content type if available, but not required
	 */
	public function getContentType();

    /**
     * @return mixed
     * Any string representation for content type, used in messages etc.
     */
	public function getContentTypeName();

    /**
     * @return mixed
     * Build the URL to the content based on $this->content array
     */
	public function getContentUrl();

    /**
     * @return mixed
     * Return the ID from $this->content array
     */
	public function getContentId();

    /**
     * @return int
     * User ID who created the content
     */
	public function getContentUserId();


    /**
     * @return int
     * Unix timestamp for content creation date
     */
	public function getContentDate();

    /**
     * @param $contentId
     * @return mixed
     * Setup $this->content array and other properties if needed
     */
	public function setContextFromContentId($contentId);

    /**
     * @param $parentContentType
     * @param $parentContentId
     * @return mixed
     * Setup the properties needed for the content as a context, like thread and forum for a post
     */
	public function setContextFromParentContentId($parentContentType, $parentContentId);
	
    /**
     * @param $contextArray
     * @return mixed
     * Should be used if we have context information readily available and want to avoid queries
     */
	public function setContextFromArray($contextArray);

    /**
     * @param $contentId
     * @return mixed
     * Return the full array of content information based on its ID
     */
    public function getContentById($contentId);

    /**
     * @param $start
     * @param $limit
     * @return array
     * Array of content IDs in some range
     */
    public function getContentIdsInRange($start, $limit);

    /**
     * @param array $contentIds
     * @return array
     * Associative array of content_id=>content_text
     */
    public function getContentArrayByIds(array $contentIds);

    /**
     * @return mixed
     * Usually useful for post and other content types, that have some message which can be interacted with
     */
    public function getContentMessage();
    public function setContentMessage($message);
    public function setContentData($contentDataKey, $contentDataValue);
    
    /**
     * @return bool
     * Consider setting up the content before calling this. Should check the permissions to moderate the content
     */
	public function canModerateContent();
    public function markContentAsModerated();
    public function markContentAsVisible();
    public function deleteContent();

    /**
     * @return bool
     */
	public function contentIsVisible();

	public function updateContentMessage(array $contentArray);
}