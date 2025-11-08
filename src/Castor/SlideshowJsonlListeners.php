<?php declare(strict_types=1);

// file: src/Castor/SlideshowJsonlListeners.php
namespace Survos\StepBundle\Castor;

use Castor\Attribute\AsListener;
use Castor\Event\BeforeExecuteTaskEvent;
use Castor\Event\AfterExecuteTaskEvent;
use Castor\Event\ProcessCreatedEvent;
use Castor\Event\ProcessStartEvent;
use Castor\Event\ProcessTerminateEvent;
use Survos\StepBundle\Slideshow\SlideshowJsonl as S;
use function Castor\io;

if (!function_exists(__NAMESPACE__ . '\\sjl_before_execute')) {

    /** Map TaskCommand object id -> run id (per-process). */
    function &sjl_run_map(): array { static $map = []; return $map; }

    #[AsListener(BeforeExecuteTaskEvent::class)]
    function sjl_before_execute(BeforeExecuteTaskEvent $event): void
    {
        $task  = $event->task;
        $oid   = \spl_object_id($task);
        $runId = S::genRunId();
        sjl_run_map()[$oid] = $runId;

        // Infer slideshow code from task name (prefix before ':') unless env overrides.
        $code       = S::slideshowCode(null, $task->getName());
        $castorFile = S::guessCastorFile($code);

        $rec = [
            'type'        => 'task_run_started',
            'run_id'      => $runId,
            'task_name'   => $task->getName(),
            'castor_file' => $castorFile,
            'php'         => \PHP_VERSION,
            'env'         => [
                // Keep these for context; not required for code resolution.
                'SLIDESHOW'   => $_ENV['SLIDESHOW']   ?? \getenv('SLIDESHOW')   ?: null,
                'WORKING_DIR' => $_ENV['WORKING_DIR'] ?? \getenv('WORKING_DIR') ?: null,
            ],
        ];
        // Pass $code so we write to {code}.jsonl
        S::append($rec, tokenCode: 'start|' . $runId, code: $code);
    }

    #[AsListener(AfterExecuteTaskEvent::class)]
    function sjl_after_execute(AfterExecuteTaskEvent $event): void
    {
        $task  = $event->task;
        $oid   = \spl_object_id($task);
        $runId = sjl_run_map()[$oid] ?? null;

        $code       = S::slideshowCode(null, $task->getName());
        $castorFile = S::guessCastorFile($code);
        $jsonlPath  = S::filePath($code);

        $rec = [
            'type'        => 'task_run_finished',
            'run_id'      => $runId,
            'task_name'   => $task->getName(),
            'castor_file' => $castorFile,
            'exit_code'   => $event->exitCode ?? 0,
        ];

        S::append($rec, tokenCode: 'finish|' . ($runId ?? 'na') . '|' . $task->getName(), code: $code);

        // Friendly hint (stderr)
        if (io()->isVerbose()) {
            $uiUrl = "/castor/logs/{$code}";
            $hint = "📼 Slideshow JSONL: {$jsonlPath}\n".
                "    UI: {$uiUrl}\n".
                "    tail: tail -n +1 {$jsonlPath} | jq";
            \fwrite(\STDERR, $hint . "\n");
        }
    }

    #[AsListener(ProcessCreatedEvent::class)]
    function sjl_proc_created(ProcessCreatedEvent $event): void
    {
        $cmdline = $event->process?->getCommandLine();
        $rec = ['type' => 'process_created', 'cmdline' => $cmdline];
        S::append($rec, tokenCode: 'proc|created|' . \substr((string)$cmdline, 0, 80));
    }

    #[AsListener(ProcessStartEvent::class)]
    function sjl_proc_started(ProcessStartEvent $event): void
    {
        $cmdline = $event->process?->getCommandLine();
        $rec = ['type' => 'process_started', 'cmdline' => $cmdline];
        S::append($rec, tokenCode: 'proc|started|' . \substr((string)$cmdline, 0, 80));
    }

    #[AsListener(ProcessTerminateEvent::class)]
    function sjl_proc_terminated(ProcessTerminateEvent $event): void
    {
        $cmdline = $event->process?->getCommandLine();
        $rec = ['type' => 'process_terminated', 'cmdline' => $cmdline,
            'exit_code' => $event->process->getExitCode()];
        S::append($rec, tokenCode: 'proc|terminated|' . \substr((string)$cmdline, 0, 80));
    }
}
