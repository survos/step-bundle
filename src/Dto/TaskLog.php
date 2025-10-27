<?php declare(strict_types=1);

namespace Survos\StepBundle\Dto;

final class TaskLog
{
    public function __construct(
        public readonly string  $type,
        public readonly ?string $taskName,
        public readonly ?string $cmdline,
        public readonly ?int    $exitCode,
        public readonly ?string $ts,
        public readonly ?string $castorFile,
        public readonly ?string $workingDir,
        /** @var array<string,mixed>|null */
        public readonly ?array  $env,
        /** raw row for debugging */
        public readonly array   $raw = [],
    ) {}

    /** @param array<string,mixed> $row */
    public static function fromArray(array $row): self
    {
        return new self(
            type:       (string)($row['type'] ?? ''),
            taskName:   isset($row['task_name']) ? (string)$row['task_name'] : null,
            cmdline:    isset($row['cmdline']) ? (string)$row['cmdline'] : null,
            exitCode:   isset($row['exit_code']) ? (int)$row['exit_code'] : null,
            ts:         isset($row['ts']) ? (string)$row['ts'] : null,
            castorFile: isset($row['castor_file']) ? (string)$row['castor_file'] : null,
            workingDir: isset($row['working_dir']) ? (string)$row['working_dir'] : null,
            env:        (isset($row['env']) && is_array($row['env'])) ? $row['env'] : null,
            raw:        $row,
        );
    }

    public function badgeClass(): string
    {
        return match (true) {
            $this->type === 'task_run_started'    => 'badge bg-warning text-dark',
            $this->type === 'task_run_finished'   => 'badge bg-success',
            str_starts_with($this->type, 'process_') => 'badge bg-info',
            default                                => 'badge bg-secondary',
        };
    }
}

