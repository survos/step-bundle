<?php declare(strict_types=1);
// File: src/Controller/CastorController.php

namespace Survos\StepBundle\Controller;

use Survos\StepBundle\Service\CastorStepExporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

final class CastorController extends AbstractController
{
    public function __construct(
        private readonly CastorStepExporter $exporter,
        #[Autowire('%kernel.project_dir%)')] private string $projectDir,
    ) {}

    #[Route('/steps', name: 'survos_step_index', methods: ['GET'])]
    public function index(): Response
    {
        $slideshows = $this->exporter->listSlideshows();

        return $this->render('@SurvosStep/step/index.html.twig', [
            'slideshows' => $slideshows,
        ]);
    }

    #[Route('/run-castor', name: 'run_castor')]
    public function runCastor(): Response
    {
        $process = new Process(['castor', 'foo:bar']);
//        $process->setWorkingDirectory($this->projectDir);
        $process->setTimeout(300); // 5 minutes timeout

        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $output = $process->getOutput();

        return new Response('Task executed: ' . $output);
    }


    #[Route('/steps/{code}.json', name: 'survos_step_json', methods: ['GET'])]
    public function castorJson(string $code): JsonResponse
    {
        $deck = $this->exporter->exportSlides($code);

        // Let clients cache briefly; server-side rendering uses the same exporter anyway.
        return $this->json($deck, 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

// file: src/Controller/StepSlidesController.php — flatten to slides[]

    #[Route('/slides/{code}', name: 'survos_step_slideshow', methods: ['GET'])]
    #[Route('/slides-overview/{code}', name: 'survos_step_slides', methods: ['GET'])]
    public function slides(Request $request, string $code,
        #[MapQueryParameter] bool $debug=false
    ): Response
    {
        $template = $request->get('_route') === 'survos_step_slideshow'
            ? '@SurvosStep/step/slides.html.twig'
            : '@SurvosStep/step/debug.html.twig';
        $deck = $this->exporter->exportSlides($code);
        $slides = $deck['slides'] ?? [];

        return $this->render($template, [
            'code'     => (string)($deck['code'] ?? $code),
            'slides'   => array_values($slides),
            'debug' => $debug,
            'json_url' => $this->generateUrl('survos_step_json', ['code' => $code]),
            'deck'     => $deck,
        ]);
    }

}
