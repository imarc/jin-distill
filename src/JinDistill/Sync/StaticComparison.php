<?php

namespace JinDistill\Sync;

final class StaticComparison
{
    /**
     * @param list<StaticChange> $changes
     * @param array<string, string> $rootStatuses
     */
    public function __construct(private array $changes, private array $rootStatuses)
    {
    }

    public function status(): string
    {
        return self::classify($this->changes);
    }

    /** @return list<StaticChange> */
    public function changes(): array
    {
        return $this->changes;
    }

    /** @return array<string, string> */
    public function rootStatuses(): array
    {
        return $this->rootStatuses;
    }

    /** @param list<StaticChange> $changes */
    public static function classify(array $changes): string
    {
        $kinds = [];
        foreach ($changes as $change) {
            $kinds[$change->kind()] = true;
        }
        if ($kinds === []) {
            return 'unchanged';
        }
        if (count($kinds) > 1) {
            return 'mixed';
        }
        return match (array_key_first($kinds)) {
            'added' => 'additive',
            'removed' => 'removed',
            default => 'updated',
        };
    }
}
