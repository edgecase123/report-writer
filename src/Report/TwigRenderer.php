<?php

namespace ReportWriter\Report;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class TwigRenderer
{
    private Environment $twig;

    private static ?TwigRenderer $instance = null;

    public function __construct()
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../templates');

        $this->twig = new Environment($loader, [
            'debug' => true,
            'cache' => false, // makes development easier
        ]);
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function render(string $templateName, array $context = []): string
    {
        return $this->twig->render($templateName, $context);
    }
}