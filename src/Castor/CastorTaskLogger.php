<?php declare(strict_types=1);

// file lives in bundle, but Castor requires global functions with attributes.
namespace Survos\StepBundle\Castor;

use Castor\Attribute\AsListener;
use Castor\Event\BeforeExecuteTaskEvent;
use Castor\Event\AfterExecuteTaskEvent;
use Castor\Event\ProcessCreatedEvent;
use Castor\Event\ProcessStartEvent;
use Castor\Event\ProcessTerminateEvent;
use Castor\Event\AfterBootEvent;
use Symfony\Component\Uid\Uuid;
use Survos\JsonlBundle\IO\JsonlWriter;

if (false && !function_exists(__NAMESPACE__ . '\\castor_after_boot')) {

    /**
     * Ensure slideshow row exists (optional). Will no-op if container/EM unavailable.
     */
    #[AsListener(AfterBootEvent::class, priority: 0)]
    function castor_after_boot(AfterBootEvent $event): void
    {

        $slideshowCode = $_ENV['SLIDESHOW'] ?? getenv('SLIDESHOW') ?: null;
        $slideshowClass = $_ENV['SLIDESHOW_CLASS'] ?? getenv('SLIDESHOW_CLASS') ?: null;
        if (!$slideshowCode || !$slideshowClass || !class_exists($slideshowClass) || !is_subclass_of($slideshowClass, BaseSlideshow::class)) {
            return;
        }

        try {
            $repo = $em->getRepository($slideshowClass);
            $existing = $repo->findOneBy(['code' => $slideshowCode]);
            if (!$existing) {
                /** @var BaseSlideshow $row */
                $row = new $slideshowClass($slideshowCode, strtoupper($slideshowCode) . ' Slideshow', 'Auto-created by Castor');
                $em->persist($row);
                $em->flush();
            }
        } catch (\Throwable) {
            // swallow; logging must not break Castor
        }
    }

    /**
     * Start a TaskRun just before execution.
     */
    #[AsListener(BeforeExecuteTaskEvent::class, priority: 0)]
    function castor_before_execute(BeforeExecuteTaskEvent $event): void
    {
        $logger = CastorKernelBridge::logger();
        $logger->error("We made it to " . __FUNCTION__);

        $em = CastorKernelBridge::em();
        if (!$em) { return; }

        $taskRunClass = $_ENV['TASKRUN_CLASS'] ?? getenv('TASKRUN_CLASS') ?: null;
        if (!$taskRunClass || !class_exists($taskRunClass) || !is_subclass_of($taskRunClass, BaseTaskRun::class)) {
            return;
        }

        $cmd = $event->getTaskCommand();
        $taskName = $cmd->getName();

        // best-effort capture; avoid heavy reflection
        $payload = [
            'definition_args' => array_keys($cmd->getDefinition()->getArguments()),
            'definition_opts' => array_keys($cmd->getDefinition()->getOptions()),
        ];

        $contextJson = json_encode([
            'php' => \PHP_VERSION,
            'symfony' => \defined('\Symfony\Component\HttpKernel\Kernel::VERSION') ? \Symfony\Component\HttpKernel\Kernel::VERSION : null,
        ], JSON_UNESCAPED_SLASHES);

        $envJson = json_encode([
            'SLIDESHOW'   => $_ENV['SLIDESHOW'] ?? getenv('SLIDESHOW') ?: null,
            'WORKING_DIR' => $_ENV['WORKING_DIR'] ?? getenv('WORKING_DIR') ?: getcwd(),
        ], JSON_UNESCAPED_SLASHES);

        $argsJson = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $slideshow = $_ENV['SLIDESHOW'] ?? getenv('SLIDESHOW') ?: null;
        $cwd = $_ENV['WORKING_DIR'] ?? getenv('WORKING_DIR') ?: getcwd();

        /** @var BaseTaskRun $run */
        $run = new $taskRunClass(
            taskName: $taskName,
            command: $taskName,
            argumentsJson: $argsJson,
            contextJson: $contextJson,
            envJson: $envJson,
            slideshowCode: $slideshow,
            workingDir: $cwd,
        );

        try {
            $em->persist($run);
            $em->flush();
            // Stash UUID on the Command object for correlation later
            $cmd->__taskRunId = $run->getId();
        } catch (\Throwable) {
            // swallow
        }
    }

    /**
     * Mark TaskRun complete and set exit code.
     */
    #[AsListener(AfterExecuteTaskEvent::class, priority: 0)]
    function castor_after_execute(AfterExecuteTaskEvent $event): void
    {
        $em = CastorKernelBridge::em();
        if (!$em) { return; }

        $taskRunClass = $_ENV['TASKRUN_CLASS'] ?? getenv('TASKRUN_CLASS') ?: null;
        if (!$taskRunClass || !class_exists($taskRunClass) || !is_subclass_of($taskRunClass, BaseTaskRun::class)) {
            return;
        }

        $cmd = $event->getTaskCommand();
        $runId = $cmd->__taskRunId ?? null;
        if (!$runId) { return; }

        try {
            $repo = $em->getRepository($taskRunClass);
            $uuid = $runId instanceof Uuid ? $runId : Uuid::fromString((string) $runId);
            /** @var BaseTaskRun|null $run */
            $run = $repo->find($uuid);
            if ($run) {
                $exit = (int) ($cmd->getExitCode() ?? 0);
                $run->markFinished($exit);
                $em->flush();
            }
        } catch (\Throwable) {
            // swallow
        }
    }

    /**
     * Record process lifecycle phases (created/started/terminated).
     */
    #[AsListener(ProcessCreatedEvent::class, priority: 0)]
    function castor_proc_created(ProcessCreatedEvent $event): void
    {
        castor_log_process_phase('created', $event->getProcess()?->getCommandLine(), null);
    }

    #[AsListener(ProcessStartEvent::class, priority: 0)]
    function castor_proc_started(ProcessStartEvent $event): void
    {
        castor_log_process_phase('started', $event->getProcess()?->getCommandLine(), null);
    }

    #[AsListener(ProcessTerminateEvent::class, priority: 0)]
    function castor_proc_terminated(ProcessTerminateEvent $event): void
    {
        castor_log_process_phase('terminated', $event->getProcess()?->getCommandLine(), $event->getExitCode());
    }

    /**
     * Helper used by the three process listeners above.
     */
    function castor_log_process_phase(string $phase, ?string $cmdline, ?int $exit): void
    {
        $em = CastorKernelBridge::em();
        if (!$em) { return; }

        $taskProcessClass = $_ENV['TASKPROCESS_CLASS'] ?? getenv('TASKPROCESS_CLASS') ?: null;
        if (!$taskProcessClass || !class_exists($taskProcessClass) || !is_subclass_of($taskProcessClass, BaseTaskProcess::class)) {
            return;
        }

        // Link to the most recent TaskRun if we can (best-effort).
        $taskRunId = null;
        try {
            $taskRunClass = $_ENV['TASKRUN_CLASS'] ?? getenv('TASKRUN_CLASS') ?: null;
            if ($taskRunClass && class_exists($taskRunClass) && is_subclass_of($taskRunClass, BaseTaskRun::class)) {
                $repo = $em->getRepository($taskRunClass);
                $latest = $repo->findOneBy([], ['startedAt' => 'DESC']);
                if ($latest && method_exists($latest, 'getId')) {
                    $taskRunId = $latest->getId();
                }
            }
        } catch (\Throwable) {
            $taskRunId = null;
        }
        if (!$taskRunId instanceof Uuid) {
            $taskRunId = Uuid::v7(); // fallback correlation id
        }

        try {
            $proc = new $taskProcessClass(
                taskRunId: $taskRunId,
                phase: $phase,
                commandLine: $cmdline,
                output: null,
                exitCode: $exit
            );
            $em->persist($proc);
            $em->flush();
        } catch (\Throwable) {
            // swallow
        }
    }
}
