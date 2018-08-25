<?php
namespace verbb\comments\elements\actions;

use Craft;
use craft\base\Element;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;

class SetStatus extends ElementAction
{
    // Properties
    // =========================================================================

    public $status;

    // Public Methods
    // =========================================================================

    public function getTriggerLabel(): string
    {
        return Craft::t('app', 'Set Status');
    }

    // Public Methods
    // =========================================================================

    public function getTriggerHtml()
    {
        return Craft::$app->getView()->renderTemplate('comments/_elementactions/status');
    }

    public function performAction(ElementQueryInterface $query): bool
    {
        $elementsService = Craft::$app->getElements();

        $elements = $query->all();
        $failCount = 0;

        foreach ($elements as $element) {
            // Skip if there's nothing to change
            if ($element->status == $this->status) {
                continue;
            }

            $element->status = $this->status;

            if ($elementsService->saveElement($element, false, false) === false) {
                // Validation error
                $failCount++;
            }
        }

        // Did all of them fail?
        if ($failCount === count($elements)) {
            if (count($elements) === 1) {
                $this->setMessage(Craft::t('comments', 'Could not update status due to a validation error.'));
            } else {
                $this->setMessage(Craft::t('comments', 'Could not update statuses due to validation errors.'));
            }

            return false;
        }

        if ($failCount !== 0) {
            $this->setMessage(Craft::t('comments', 'Status updated, with some failures due to validation errors.'));
        } else {
            if (count($elements) === 1) {
                $this->setMessage(Craft::t('comments', 'Status updated.'));
            } else {
                $this->setMessage(Craft::t('comments', 'Statuses updated.'));
            }
        }

        return true;
    }
}
