<?php

declare(strict_types=1);
// File: src/Controller/CastorController.php

namespace Survos\StepBundle\Controller;

use Survos\StepBundle\Renderer\DebugActionRenderer;
use Survos\StepBundle\Renderer\MarkdownActionRenderer;
use Survos\StepBundle\Renderer\RevealActionRenderer;
use Survos\StepBundle\Service\CastorStepExporter;
use Survos\StepBundle\Service\MarkdownDeckRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/slideshow')]
final class CastorController extends AbstractController
{
    public function __construct(
        private readonly CastorStepExporter $exporter,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
    ) {
    }

    #[Route('/', name: 'survos_step_index', methods: ['GET'])]
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

        return new Response('Task executed: '.$output);
    }

    #[Route('/json/{code}.json', name: 'survos_step_json', methods: ['GET'])]
    public function castorJson(string $code): JsonResponse
    {
        $deck = $this->exportCode($code);

        // Let clients cache briefly; server-side rendering uses the same exporter anyway.
        return $this->json($deck, 200, [
            'Cache-Control' => 'public, max-age=60',
        ]);
    }

    // file: src/Controller/StepSlidesController.php — flatten to slides[]

    #[Route('/show/{code}', name: 'survos_step_slideshow', methods: ['GET'])]
    #[Route('/overview/{code}', name: 'survos_step_slides', methods: ['GET'])]
    #[Route('/markdown/{code}', name: 'survos_step_markdown', methods: ['GET'])]
    public function slides(Request $request, string $code,
        DebugActionRenderer $debugActionRenderer,
        RevealActionRenderer $revealActionRenderer,
        MarkdownActionRenderer $markdownActionRenderer,
        RequestStack $requestStack,
        #[MapQueryParameter] bool $debug = false,
    ): Response {
        switch ($route = $request->attributes->get('_route')) {
            case 'survos_step_slideshow':
                $renderer = $revealActionRenderer;
                $template = '@SurvosStep/step/slides.html.twig';
                break;
            case 'survos_step_slides':
                $renderer = $debugActionRenderer;
                $template = '@SurvosStep/step/debug.html.twig';
                break;
            case 'survos_step_markdown':
                $renderer = $markdownActionRenderer;
                $template = '@SurvosStep/step/md.html.twig';
                break;
        }
        //        $template = sprintf('slides/%s.html.twig', $code);

        $deck = $this->exportCode($code);

        if ('survos_step_markdown' === $route) {
            // plain text, not text/markdown, so a browser shows it instead of downloading it
            return new Response(
                new MarkdownDeckRenderer()->render($deck, $request->getSchemeAndHttpHost()),
                200,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $slides = $deck['slides'] ?? [];

        return $this->render($template, [
            'renderer' => $renderer,
            'code' => (string) ($deck['code'] ?? $code),
            'slides' => array_values($slides),
            'debug' => $debug,
            'json_url' => $this->generateUrl('survos_step_json', ['code' => $code]),
            'deck' => $deck,
        ]);
    }

    /**
     * A code is either one deck (conference/<code>.castor.php) or a directory of decks (<project>/<code>/),
     * whose slides are concatenated. Shared by the slideshow, overview, markdown and JSON routes, so they
     * cannot disagree about what a code means.
     *
     * @return array{code:string,path:string,meta:array<string,mixed>,slides:list<array<string,mixed>>}
     */
    private function exportCode(string $code): array
    {
        $dir = $this->projectDir.DIRECTORY_SEPARATOR.$code;
        if (!is_dir($dir)) {
            return $this->exporter->exportSlides($code);
        }

        $slides = [];
        foreach (new Finder()->in($dir)->files()->name('*.castor.php')->sortByName() as $file) {
            $deck = $this->exporter->exportSlides($file->getBasename('.castor.php'), $file->getRealPath());
            foreach ($deck['slides'] as $slide) {
                $slides[] = $slide;
            }
        }

        return ['code' => $code, 'path' => $dir, 'meta' => [], 'slides' => $slides];
    }
}
