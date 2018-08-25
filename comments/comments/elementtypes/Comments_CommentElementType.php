<?php
namespace Craft;

class Comments_CommentElementType extends BaseElementType
{
    // Public Methods
    // =========================================================================

    public function getName()
    {
        return Craft::t('Comment');
    }

    public function hasContent()
    {
        return true;
    }

    public function hasTitles()
    {
        return false;
    }

    public function hasStatuses()
    {
        return true;
    }

    public function getStatuses()
    {
        return array(
            Comments_CommentModel::APPROVED => Craft::t('Approved'),
            Comments_CommentModel::PENDING => Craft::t('Pending'),
            Comments_CommentModel::SPAM => Craft::t('Spam'),
            Comments_CommentModel::TRASHED => Craft::t('Trashed')
        );
    }

    public function getSources($context = null)
    {
        $settings = craft()->comments->getSettings();

        $sources = array(
            '*' => array(
                'label' => Craft::t('All Comments'),
                'structureId' => $settings->structureId,
                'structureEditable' => false,
            ),
        );

        foreach (craft()->comments->getElementsWithComments() as $element) {
            if (isset($element->elementType)) {
                $elementType = craft()->elements->getElementType($element->elementType);
                $key = 'elements:'.$elementType->classHandle;

                $sources[$key] = array('heading' => $elementType->name);

                $sources[$key.':all'] = array(
                    'label' => Craft::t('All ' . $elementType->name),
                    'criteria' => array('elementType' => $element->elementType),
                    'structureId' => $settings->structureId,
                    'structureEditable' => false,
                );
            }
        }

        return $sources;
    }

    public function populateElementModel($row)
    {
        return Comments_CommentModel::populateModel($row);
    }

    public function defineTableAttributes($source = null)
    {
        return array(
            'comment'       => Craft::t('Comment'),
            'dateCreated'   => Craft::t('Date'),
            'elementId'     => Craft::t('Element'),
        );
    }

    public function defineSortableAttributes()
    {
        return array(
            'status'        => Craft::t('Status'),
            'comment'       => Craft::t('Comment'),
            'dateCreated'   => Craft::t('Date'),
            'elementId'     => Craft::t('Element'),
        );
    }

    public function getTableAttributeHtml(BaseElementModel $element, $attribute)
    {
        switch ($attribute) {
            case 'elementId': {
                $element = craft()->elements->getElementById($element->elementId);

                if ($element == null) {
                    return Craft::t('[Deleted element]');
                } else {
                    return "<a href='" . $element->cpEditUrl . "'>" . $element->title . "</a>";
                }
            }
            default: {
                return parent::getTableAttributeHtml($element, $attribute);
            }
        }
    }

    public function defineCriteriaAttributes()
    {
        return array(
            'elementId'     => array(AttributeType::Number),
            'elementType'   => array(AttributeType::String),
            'userId'        => array(AttributeType::Number),
            'structureId'   => array(AttributeType::Number),
            'status'        => array(AttributeType::String),
            'name'          => array(AttributeType::String),
            'email'         => array(AttributeType::Email),
            'url'           => array(AttributeType::Url),
            'ipAddress'     => array(AttributeType::String),
            'userAgent'     => array(AttributeType::String),
            'comment'       => array(AttributeType::Mixed),
            'order'         => array(AttributeType::String, 'default' => 'lft, commentDate desc'),
        );
    }

    public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
    {
        $query
        ->addSelect('comments.elementId, comments.userId, comments.elementType, comments.structureId, comments.status, comments.name, comments.email, comments.url, comments.ipAddress, comments.userAgent, comments.comment, comments.dateCreated AS commentDate')
        ->join('comments comments', 'comments.id = elements.id')
        ->leftJoin('comments_votes comments_votes', 'comments_votes.commentId = comments.id')
        ->leftJoin('structures structures', 'structures.id = comments.structureId')
        ->leftJoin('structureelements structureelements', array('and', 'structureelements.structureId = structures.id', 'structureelements.elementId = comments.id'));

        if ($criteria->elementId) {
            $query->andWhere(DbHelper::parseParam('comments.elementId', $criteria->elementId, $query->params));
        }

        if ($criteria->elementType) {
            $query->andWhere(DbHelper::parseParam('comments.elementType', $criteria->elementType, $query->params));
        }

        if ($criteria->userId) {
            $query->andWhere(DbHelper::parseParam('comments.userId', $criteria->userId, $query->params));
        }

        if ($criteria->structureId) {
            $query->andWhere(DbHelper::parseParam('comments.structureId', $criteria->structureId, $query->params));
        }

        if ($criteria->status) {
            $query->andWhere(DbHelper::parseParam('comments.status', $criteria->status, $query->params));
        }

        if ($criteria->name) {
            $query->andWhere(DbHelper::parseParam('comments.name', $criteria->name, $query->params));
        }

        if ($criteria->email) {
            $query->andWhere(DbHelper::parseParam('comments.email', $criteria->email, $query->params));
        }

        if ($criteria->url) {
            $query->andWhere(DbHelper::parseParam('comments.url', $criteria->url, $query->params));
        }

        if ($criteria->ipAddress) {
            $query->andWhere(DbHelper::parseParam('comments.ipAddress', $criteria->ipAddress, $query->params));
        }

        if ($criteria->userAgent) {
            $query->andWhere(DbHelper::parseParam('comments.userAgent', $criteria->userAgent, $query->params));
        }

        if ($criteria->comment) {
            $query->andWhere(DbHelper::parseParam('comments.comment', $criteria->comment, $query->params));
        }

        if ($criteria->dateCreated) {
            $query->andWhere(DbHelper::parseDateParam('comments.dateCreated', $criteria->dateCreated, $query->params));
        }

    }
    
    public function getAvailableActions($source = null)
    {
        $user = craft()->userSession->getUser();

        $actions[] = 'Comments_Status';

        if ($user->can('commentsDelete')) {
            $deleteAction = craft()->elements->getAction('Delete');
            $deleteAction->setParams(array(
                'confirmationMessage' => Craft::t('Are you sure you want to delete the selected comments?'),
                'successMessage'      => Craft::t('Comments deleted.'),
            ));

            $actions[] = $deleteAction;
        }

        return $actions;

        //return array('Comments_Status');
    }

}