<?php declare(strict_types=1);
// File: src/Controller/CastorController.php

namespace Survos\StepBundle\Controller;

use Survos\StepBundle\Service\CastorStepExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CastorController extends AbstractController
{
    public function __construct(
        private readonly CastorStepExporter $exporter,
    ) {}

    #[Route('/steps', name: 'survos_step_index', methods: ['GET'])]
    public function index(): Response
    {
        $slideshows = $this->exporter->listSlideshows();

        return $this->render('@SurvosStep/step/index.html.twig', [
            'slideshows' => $slideshows,
        ]);
    }

    #[Route('/steps/{code}.json', name: 'survos_step_json', methods: ['GET'])]
    public function castorJson(string $code): JsonResponse
    {
        $deck = $this->exporter->exportSteps($code);

        // Let clients cache briefly; server-side rendering uses the same exporter anyway.
        return $this->json($deck, 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

// file: src/Controller/StepSlidesController.php — flatten to slides[]

    #[Route('/slides/{code}', name: 'survos_step_slides', methods: ['GET'])]
    public function slides(string $code): Response
    {
        $deck = $this->exporter->exportSteps($code);

        $normalizedCode = (string)($deck['code'] ?? $code);
        $tasks = $deck['tasks'] ?? [];

        // Flatten tasks -> slides
        $slides = [];
        foreach ($tasks as $t) {
            $taskName  = (string)($t['name'] ?? $t['code'] ?? 'task');
            $taskTitle = (string)($t['title'] ?? $taskName);
            $taskDesc  = (string)($t['description'] ?? '');
            $taskBul   = (array)($t['bullets'] ?? []);

            // New shape: steps[]
            if (!empty($t['steps']) && is_array($t['steps'])) {
                foreach ($t['steps'] as $s) {
                    $slides[] = [
                        'task_name' => $taskName,
                        'title'     => (string)($s['title'] ?? $taskTitle),
                        'description'=> (string)($s['description'] ?? $taskDesc),
                        'bullets'   => (array)($s['bullets'] ?? $taskBul),
                        'actions'   => (array)($s['actions'] ?? []),
                    ];
                }
                continue;
            }

            // Legacy: the whole task is one slide
            $slides[] = [
                'task_name' => $taskName,
                'title'     => $taskTitle,
                'description'=> $taskDesc,
                'bullets'   => $taskBul,
                'actions'   => (array)($t['actions'] ?? []),
            ];
//            dd($slides, $deck);
        }

        return $this->render('@SurvosStep/step/slides.html.twig', [
            'code'     => $normalizedCode,
            'slides'   => $slides, // <-- now slides, not tasks
            'json_url' => $this->generateUrl('survos_step_json', ['code' => $normalizedCode]),
            'deck'     => $deck,
        ]);
    }

}
