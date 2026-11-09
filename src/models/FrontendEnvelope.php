<?php
namespace verbb\comments\models;

use craft\base\Model;

/**
 * Contract v1 JSON envelope for enhanced frontend requests.
 */
class FrontendEnvelope extends Model
{
    // Static Methods
    // =========================================================================

    public static function ok(array $fragments = [], array $options = []): self
    {
        return new self([
            'contractVersion' => 1,
            'status' => 'ok',
            'fragments' => $fragments,
            'operationId' => $options['operationId'] ?? null,
            'resource' => $options['resource'] ?? null,
            'workflowVersion' => $options['workflowVersion'] ?? null,
            'replayed' => (bool)($options['replayed'] ?? false),
            'secondaryWorkPending' => (bool)($options['secondaryWorkPending'] ?? false),
            'state' => $options['state'] ?? [],
            'announcement' => $options['announcement'] ?? null,
            'rootsNext' => $options['rootsNext'] ?? null,
            'repliesNext' => $options['repliesNext'] ?? null,
        ]);
    }

    public static function error(array $errors, string $status = 'error'): self
    {
        return new self([
            'contractVersion' => 1,
            'status' => $status,
            'errors' => $errors,
        ]);
    }


    // Properties
    // =========================================================================

    public int $contractVersion = 1;
    public string $status = 'ok';
    public ?string $operationId = null;
    public ?string $resource = null;
    public ?string $workflowVersion = null;
    public bool $replayed = false;
    public bool $secondaryWorkPending = false;

    public array $fragments = [];

    public array $state = [];

    public array $errors = [];

    public ?string $announcement = null;
    public ?string $rootsNext = null;
    public ?string $repliesNext = null;
}
