<?php
namespace verbb\comments\migrations\support;

final readonly class V3IntegrityReport
{
    // Public Methods
    // =========================================================================

    public function __construct(
        public array $summary,
        public array $issues,
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->countBySeverity('error') > 0;
    }

    public function countBySeverity(string $severity): int
    {
        return array_sum(array_map(
            static fn(array $issue): int => $issue['severity'] === $severity ? $issue['count'] : 0,
            $this->issues,
        ));
    }

    public function countByCode(string $code): int
    {
        return array_sum(array_map(
            static fn(array $issue): int => $issue['code'] === $code ? $issue['count'] : 0,
            $this->issues,
        ));
    }

    public function toArray(): array
    {
        return [
            'summary' => $this->summary,
            'issues' => $this->issues,
        ];
    }
}
