<?php

declare(strict_types=1);

namespace ReportWriter\App\Tests\Unit\Reports;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReportWriter\App\Reports\JsonTemplateRepository;
use ReportWriter\Template\ReportTemplate;
use ReportWriter\Template\TemplateLoader;

final class JsonTemplateRepositoryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/rw-json-repo-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir);
        file_put_contents(
            $this->tmpDir . '/hello.json',
            json_encode([
                'report_definition_id' => 'hello',
                'data_source'          => 'noop',
                'bands'                => [],
            ], JSON_THROW_ON_ERROR)
        );
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
            unlink($f);
        }
        rmdir($this->tmpDir);
    }

    public function testLoadReturnsReportTemplateForKnownName(): void
    {
        $repo = new JsonTemplateRepository($this->tmpDir, new TemplateLoader());
        $tmpl = $repo->load('hello');
        $this->assertInstanceOf(ReportTemplate::class, $tmpl);
        $this->assertSame('hello', $tmpl->getReportDefinitionId());
    }

    public function testLoadRejectsPathTraversal(): void
    {
        $repo = new JsonTemplateRepository($this->tmpDir, new TemplateLoader());
        $this->expectException(InvalidArgumentException::class);
        $repo->load('../evil');
    }

    public function testLoadRejectsAbsolutePath(): void
    {
        $repo = new JsonTemplateRepository($this->tmpDir, new TemplateLoader());
        $this->expectException(InvalidArgumentException::class);
        $repo->load('/etc/passwd');
    }

    public function testLoadRejectsDotSegment(): void
    {
        $repo = new JsonTemplateRepository($this->tmpDir, new TemplateLoader());
        $this->expectException(InvalidArgumentException::class);
        $repo->load('foo.bar');
    }

    public function testLoadThrowsForUnknownName(): void
    {
        $repo = new JsonTemplateRepository($this->tmpDir, new TemplateLoader());
        $this->expectException(InvalidArgumentException::class);
        $repo->load('does-not-exist');
    }
}
