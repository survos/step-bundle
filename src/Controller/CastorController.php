<?php declare(strict_types=1);
// File: src/Controller/CastorController.php

namespace Survos\StepBundle\Controller;

use Survos\StepBundle\Service\CastorStepExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
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
    public function slides(string $code,
        #[MapQueryParameter] bool $debug=false
    ): Response
    {
        $deck = $this->exporter->exportSlides($code);
        $slides = $deck['slides'] ?? [];
        dump(array_keys($deck), array_keys($deck['slides'][0] ?? []));

        return $this->render($debug ? '@SurvosStep/step/debug.html.twig' : '@SurvosStep/step/slides.html.twig', [
            'code'     => (string)($deck['code'] ?? $code),
            'slides'   => array_values($slides),
            'json_url' => $this->generateUrl('survos_step_json', ['code' => $code]),
            'deck'     => $deck,
        ]);
    }

}
