<?php
namespace verbb\comments\elements\actions;

use Craft;
use craft\elements\actions\SetStatus;
use craft\elements\db\ElementQueryInterface;
use craft\helpers\ArrayHelper;

class SetCommentStatus extends SetStatus
{
    // Properties
    // =========================================================================

    public ?string $status = null;
    public ?array $statuses = null;


    // Public Methods
    // =========================================================================

    public function getTriggerLabel(): string
    {
        return Craft::t('app', 'Set Status');
    }

    public function getTriggerHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('comments/_components/actions/set-status/trigger', [
            'statuses' => $this->statuses,
        ]);
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

            if ($elementsService->saveElement($element) === false) {
                // Validation error
                $failCount++;
            }
        }

        // Did all of them fail?
        if ($failCount === count($elements)) {
            if (count($elements) === 1) {
                $this->setMessage(Craft::t('app', 'Could not update status due to a validation error.'));
            } else {
                $this->setMessage(Craft::t('app', 'Could not update statuses due to validation errors.'));
            }

            return false;
        }

        if ($failCount !== 0) {
            $this->setMessage(Craft::t('app', 'Status updated, with some failures due to validation errors.'));
        } else if (count($elements) === 1) {
            $this->setMessage(Craft::t('app', 'Status updated.'));
        } else {
            $this->setMessage(Craft::t('app', 'Statuses updated.'));
        }

        return true;
    }


    // Protected Methods
    // =========================================================================

    protected function defineRules(): array
    {
        // Override the parent status validator
        $rules = [];

        $statusIds = ArrayHelper::getColumn($this->statuses, 'id');

        $rules[] = [['status'], 'required'];
        $rules[] = [['status'], 'in', 'range' => $statusIds];

        return $rules;
    }
}
