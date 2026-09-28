<?php

namespace AddonsLab\Core\Xf2\Service;

use XF\Entity\AbstractField;
use XF\Entity\AbstractNode;
use XF\Entity\Attachment;
use XF\Entity\Post;
use XF\Http\Upload;
use XF\Mvc\Entity\Entity;
use XF\Util\File;
use XF\Widget\Html;

class ContentCreator
{
    /**
     * Creates a node with a title, if it does not exist.
     * @param $nodeTitle
     * @param array $nodeInfo
     * @param array $dataInfo
     * @return null|\XF\Entity\Node|\XF\Mvc\Entity\Entity
     * @throws \Exception
     * @throws \XF\PrintableException
     */
    public function assertNode($nodeTitle, $nodeInfo = array(), $dataInfo = array())
    {
        $node = \XF::finder('XF:Node')->where('title', $nodeTitle)->fetchOne();
        if ($node === null)
        {
            $node = \XF::em()->create('XF:Node');
            $node->title = $nodeTitle;
            $node->node_type_id = 'Forum';
            $node->display_order = 999;
            $node->parent_node_id = 0;
            $node->description = '';

            $node->bulkSet($nodeInfo);

            /** @var AbstractNode $data */
            $data = $node->getDataRelationOrDefault();

            $data->allow_posting = 1;
            $data->bulkSet($dataInfo);

            $node->addCascadedSave($data);
            $node->save();
        }

        return $node;
    }

    public function attachFileToPost(Post $post, $filePath)
    {
        return $this->attachFile($post, 'post', $filePath);
    }

    public function createTempAttachment(Entity $entity, $contentType, $filePath, \closure $setupCallback = null, &$error = null)
    {
        $handler = \XF::repository('XF:Attachment')->getAttachmentHandler($contentType);
        $hash = md5(mt_rand());
        $manipulator = new \XF\Attachment\Manipulator($handler, \XF::repository('XF:Attachment'), array(), $hash);
        $upload = $setupCallback ? $setupCallback($filePath) : new Upload($filePath, basename($filePath));

        return $manipulator->insertAttachmentFromUpload($upload, $error);
    }

    /**
     * @param Entity $entity
     * @param $contentType
     * @param $filePath
     * @param \closure|null $setupCallback The callback that creates the upload object (if custom initialization is needed)
     * @param null $error
     * @return \XF\Entity\Attachment|null
     */
    public function attachFile(Entity $entity, $contentType, $filePath, \closure $setupCallback = null, &$error = null)
    {
        $attachment = $this->createTempAttachment($entity, $contentType, $filePath, $setupCallback, $error);

        if (!$attachment)
        {
            return null;
        }

        /** @var \XF\Service\Attachment\Preparer $inserter */
        $inserter = \XF::service('XF:Attachment\Preparer');
        $associated = $inserter->associateAttachmentsWithContent($attachment->temp_hash, $contentType, $entity->getEntityId());
        if ($associated)
        {
            if ($entity->offsetExists('attach_count'))
            {
                $entity->fastUpdate('attach_count', $entity->attach_count + $associated);
            }
        }

        return $attachment;
    }

    public function copyAttachmentFileToTmpFile(Attachment $attachment)
    {
        $abstractedDataPath = $attachment->Data->getAbstractedDataPath() ?? '';
        if (!$abstractedDataPath)
        {
            return null;
        }

        $tmpPath = File::copyAbstractedPathToTempFile($abstractedDataPath);

        $targetPath = $tmpPath . '-' . $attachment->getFilename();

        rename($tmpPath, $targetPath);

        return $targetPath;
    }

    /**
     * Ensures custom fields with the info provided are created
     * @param array $fields field_id=>field_info mapping
     * @param array $nodes The nodes to associate the created fields with
     * @param int $displayOrder
     */
    public function assertThreadCustomFields(array $fields, array $nodes, $displayOrder = 1)
    {
        $this->assertCustomFields(
            $fields,
            $nodes,
            'XF:ThreadField',
            'XF:ForumField',
            $displayOrder
        );
    }

    public function assertCustomFields(
        array $fields,
        array $fieldHolders,
              $fieldEntityName,
              $holderRepositoryName,
              $displayOrder = 1
    )
    {
        foreach ($fields as $field_id => $fieldInfo)
        {
            $field = \XF::finder($fieldEntityName)->whereId($field_id)->fetchOne();

            $field = $this->assertCustomField($field_id, $fieldInfo, $fieldEntityName, $displayOrder * 10);

            $categoryIds = array_map(function (Entity $entity)
            {
                return $entity->getEntityId();
            }, $fieldHolders);

            $this->associateFieldWithHolder(
                $holderRepositoryName,
                $field,
                $categoryIds
            );
            $displayOrder++;
        }
    }

    public function _saveCustomField(AbstractField $field)
    {
        \XF::db()->beginTransaction();

        $field->saveIfChanged();
        $field->MasterTitle->saveIfChanged();
        $field->MasterDescription->saveIfChanged();

        \XF::db()->commit();

        return $field;
    }

    public static function assertHtmlWidget(string $widget_key, string $title, string $html, array $positions)
    {
        $widget = \XF::finder('XF:Widget')->where('widget_key', $widget_key)->fetchOne();
        if (!$widget)
        {
            $widget = \XF::em()->create('XF:Widget');
            $widget->widget_key = $widget_key;
        }

        $widget->definition_id = 'html';
        $widget->positions = $positions;
        $templateTitle = '_' . $widget_key;
        $widget->options = [
            'advanced_mode' => true,
            'template_title' => $templateTitle,
        ];

        $widget->save();

        $masterPhrase = $widget->getMasterPhrase();
        $masterPhrase->phrase_text = $title;
        $masterPhrase->save();

        // Make sure the master template exists
        $templateEnt = \XF::em()->findOne('XF:Template', [
            'style_id' => 0,
            'type' => 'public',
            'title' => $templateTitle
        ]);

        if (!$templateEnt)
        {
            // Create it
            $templateEnt = \XF::em()->create('XF:Template');
            $templateEnt->type = 'public';
            $templateEnt->title = $templateTitle;
            $templateEnt->style_id = 0;
            $templateEnt->addon_id = '';
        }

        $templateEnt->template = $html;
        $templateEnt->save();

        return $widget;
    }

    public function associateFieldWithHolder($repositoryShortName, $field, $categoryIds)
    {
        /** @var \XF\Repository\AbstractFieldMap $repo */
        $repo = \XF::repository($repositoryShortName);
        $repo->updateFieldAssociations($field, $categoryIds);
    }

    public function deleteThreadCustomFields(array $fields)
    {
        $this->deleteCustomFields($fields, 'XF:ThreadField');
    }

    public function deleteCustomFields(array $fields, $fieldEntityName)
    {
        foreach ($fields as $field_id => $fieldInfo)
        {
            $field = \XF::finder($fieldEntityName)->whereId($field_id)->fetchOne();

            if ($field)
            {
                $field->delete();
            }
        }
    }

    public function assertCustomField($field_id, $fieldInfo, $fieldEntityName, $displayOrder, $setupCallback = null)
    {
        $field = \XF::finder($fieldEntityName)->whereId($field_id)->fetchOne();
        if (!$field)
        {
            $field = $this->_createCustomFieldEntity($fieldEntityName, $field_id);
        }

        $this->_setupCustomFieldData($field, $displayOrder, $fieldInfo);

        if ($setupCallback !== null)
        {
            $setupCallback($field);
        }

        return $this->_saveCustomField($field);
    }

    public function createCustomField($field_id, $fieldInfo, $fieldEntityName, $displayOrder)
    {
        $field = $this->_createCustomFieldEntity($fieldEntityName, $field_id);
        $this->_setupCustomFieldData($field, $displayOrder, $fieldInfo);
        return $this->_saveCustomField($field);
    }

    /**
     * @param AbstractField $field
     * @param $displayOrder
     * @param $fieldInfo
     * @return array
     */
    protected function _setupCustomFieldData(AbstractField $field, $displayOrder, $fieldInfo)
    {
        $field->display_order = $displayOrder;
        /** @var \XF\Entity\Phrase $titlePhrase */
        $titlePhrase = $field->getMasterPhrase(true);
        $titlePhrase->phrase_text = $fieldInfo['title'];

        $field->hydrateRelation('MasterTitle', $titlePhrase);

        $field->field_type = $fieldInfo['field_type'];

        $descriptionPhrase = $field->getMasterPhrase(false);

        if (isset($fieldInfo['description']))
        {
            $descriptionPhrase->phrase_text = $fieldInfo['description'];
        }
        else
        {
            $descriptionPhrase->phrase_text = '';
        }

        $field->hydrateRelation('MasterDescription', $descriptionPhrase);

        if (isset($fieldInfo['match_type']))
        {
            $field->match_type = $fieldInfo['match_type'];
        }
        if (isset($fieldInfo['match_params']))
        {
            $field->match_params = $fieldInfo['match_params'];
        }
        if (isset($fieldInfo['field_choices']))
        {
            $field->field_choices = $fieldInfo['field_choices'];
        }
        if (isset($fieldInfo['display_group']))
        {
            $field->display_group = $fieldInfo['display_group'];
        }
        if (isset($fieldInfo['display_template']))
        {
            $field->display_template = $fieldInfo['display_template'];
        }

        if (isset($fieldInfo['wrapper_template']))
        {
            $field->wrapper_template = $fieldInfo['wrapper_template'];
        }

        if (isset($fieldInfo['required']))
        {
            $field->required = $fieldInfo['required'];
        }
    }

    /**
     * @param $fieldEntityName
     * @param $field_id
     * @return AbstractField
     */
    protected function _createCustomFieldEntity($fieldEntityName, $field_id): AbstractField
    {
        /** @var AbstractField $field */
        $field = \XF::em()->create($fieldEntityName);
        $field->field_id = $field_id;
        return $field;
    }
}