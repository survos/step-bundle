<?php declare(strict_types=1);

namespace Survos\StepBundle\Controller;

use Survos\JsonlBundle\IO\JsonlReader;
use Survos\StepBundle\Dto\TaskLog;
use Survos\StepBundle\Service\CastorLogLocator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/castor/logs', name: 'survos_castor_logs_')]
class CastorLogController extends AbstractController
{
    public function __construct(private readonly CastorLogLocator $locator) {}

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $logs = $this->locator->listLogs();
        return $this->render('@SurvosStep/castor/logs_index.html.twig', [
            'logs' => $logs,
            'dir'  => $this->locator->logDir(),
        ]);
    }

    #[Route('/{code}', name: 'show', methods: ['GET'])]
    public function show(string $code, Request $request): Response
    {
        $path   = $this->locator->pathFor($code);
        $limit  = max(50, min(5000, (int) $request->query->get('limit', 500)));
        $filter = (string) $request->query->get('q', '');
        $type   = (string) $request->query->get('type', ''); // task_run_started|task_run_finished|process_*

        /** @var TaskLog[] $entries */
        $entries = [];

        if (is_file($path)) {
            $reader = new JsonlReader($path);

            foreach ($reader as $row) {
                if (!is_array($row)) { continue; }

                if ($type !== '' && (($row['type'] ?? '') !== $type)) { continue; }
                if ($filter !== '' && stripos(json_encode($row, JSON_UNESCAPED_SLASHES), $filter) === false) { continue; }

                $entries[] = TaskLog::fromArray($row);

                // keep a bounded buffer during scan
                if (count($entries) > $limit * 2) {
                    $entries = array_slice($entries, -$limit);
                }
            }
        }

        // newest first (JSONL is append-only)
        $entries = array_slice($entries, -$limit);
        $entries = array_reverse($entries);

        return $this->render('@SurvosStep/castor/logs_show.html.twig', [
            'code'    => $code,
            'path'    => $path,
            'dir'     => $this->locator->logDir(),
            'limit'   => $limit,
            'filter'  => $filter,
            'type'    => $type,
            'entries' => $entries,
        ]);
    }

    #[Route('/{code}/raw', name: 'raw', methods: ['GET'])]
    public function raw(string $code): Response
    {
        $path = $this->locator->pathFor($code);
        if (!is_file($path)) {
            throw $this->createNotFoundException("No log for code '{$code}'");
        }
        return new BinaryFileResponse($path);
    }
}
