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

    #[Route('/slides/{code}', name: 'survos_step_slides', methods: ['GET'])]
    public function slides(string $code): Response
    {
        // Export the deck server-side so Twig can render slides directly.
        $deck = $this->exporter->exportSteps($code);

        // Normalize for Twig: hand it what the template expects.
        $normalizedCode = (string)($deck['code'] ?? $code);
        $tasks          = $deck['tasks'] ?? [];

        return $this->render('@SurvosStep/step/slides.html.twig', [
            'code'     => $normalizedCode,
            'tasks'    => $tasks, // <-- key fix: provide `tasks` so the template's `{% for task in tasks %}` works
            'json_url' => $this->generateUrl('survos_step_json', ['code' => $normalizedCode]),
            // still pass deck if you need richer data for future customizations
            'deck'     => $deck,
        ]);
    }
}
