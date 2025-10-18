<?php declare(strict_types=1);

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

        return $this->json($deck, 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    #[Route('/slides/{code}', name: 'survos_step_slides', methods: ['GET'])]
    public function slides(string $code): Response
    {
        // We can pass JSON inline or let front-end fetch from /steps/{code}.json.
        // Here we render server-side sections from data to keep it simple.
        $deck = $this->exporter->exportSteps($code);

        return $this->render('@SurvosStep/step/slides.html.twig', [
            'code' => $code,
            'deck' => $deck,
            // A client-side fetch alternative:
            'json_url' => $this->generateUrl('survos_step_json', ['code' => $code]),
        ]);
    }
}
