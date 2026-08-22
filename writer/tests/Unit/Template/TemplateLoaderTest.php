<?php

declare(strict_types=1);

namespace ReportWriter\Tests\Unit\Template;

use ReportWriter\Template\TemplateLoader;
use PHPUnit\Framework\TestCase;

class TemplateLoaderTest extends TestCase
{
    private TemplateLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new TemplateLoader();
    }

    private function minimalArray(): array
    {
        return [
            'report_definition_id' => 'test-report',
            'data_source'          => 'my_source',
            'bands'                => [
                [
                    'id'       => 'col_hdr',
                    'type'     => 'col-header',
                    'elements' => [
                        ['id' => 'h_name', 'x' => 0, 'width' => 100, 'height' => 15,
                         'content' => ['type' => 'text', 'value' => 'Name']],
                    ],
                ],
            ],
        ];
    }

    public function testLoadsMinimalArray(): void
    {
        $template = $this->loader->loadFromArray($this->minimalArray());

        $this->assertSame('test-report', $template->getReportDefinitionId());
        $this->assertSame('my_source', $template->getDataSource());
        $this->assertCount(1, $template->getBands());
    }

    public function testNoParamsByDefault(): void
    {
        $template = $this->loader->loadFromArray($this->minimalArray());
        $this->assertEmpty($template->getParams());
    }

    public function testLoadsParams(): void
    {
        $data = $this->minimalArray();
        $data['params'] = [
            'courseId'  => ['type' => 'int',  'required' => true],
            'startDate' => ['type' => 'date', 'required' => true],
            'notes'     => ['type' => 'string'],
        ];

        $params = $this->loader->loadFromArray($data)->getParams();

        $this->assertArrayHasKey('courseId', $params);
        $this->assertTrue($params['courseId']->isRequired());
        $this->assertSame('int', $params['courseId']->getType());
        $this->assertFalse($params['notes']->isRequired());
    }

    public function testLoadsBandDefinitions(): void
    {
        $data = $this->minimalArray();
        $data['bands'][] = [
            'id' => 'detail', 'type' => 'detail',
            'elements' => [
                ['id' => 'name', 'x' => 0, 'width' => 100, 'height' => 12,
                 'content' => ['type' => 'field', 'field' => 'name']],
            ],
        ];

        $bands = $this->loader->loadFromArray($data)->getBands();

        $this->assertCount(2, $bands);
        $this->assertSame('col-header', $bands[0]->getType());
        $this->assertSame('detail',     $bands[1]->getType());
    }

    public function testBandRowSpacingLoaded(): void
    {
        $data = $this->minimalArray();
        $data['bands'][0]['row_spacing'] = 4.0;

        $band = $this->loader->loadFromArray($data)->getBands()[0];
        $this->assertSame(4.0, $band->getRowSpacing());
    }

    public function testBandRowSpacingDefaultsToZero(): void
    {
        $band = $this->loader->loadFromArray($this->minimalArray())->getBands()[0];
        $this->assertSame(0.0, $band->getRowSpacing());
    }

    public function testBandGroupByLoaded(): void
    {
        $data = $this->minimalArray();
        $data['bands'][] = [
            'id' => 'grp_hdr', 'type' => 'group-header', 'group_by' => 'category',
            'elements' => [
                ['id' => 'gv', 'x' => 0, 'width' => 200, 'height' => 15,
                 'content' => ['type' => 'group_value']],
            ],
        ];

        $bands = $this->loader->loadFromArray($data)->getBands();
        $grpHdr = $bands[1];

        $this->assertSame('category', $grpHdr->getGroupBy());
    }

    public function testElementContentTypesLoaded(): void
    {
        $data = $this->minimalArray();
        $data['bands'][0]['elements'][] = [
            'id' => 'amount', 'x' => 110, 'width' => 80, 'height' => 15, 'align' => 'right',
            'content' => ['type' => 'aggregate', 'field' => 'amount', 'fn' => 'sum', 'format' => 'currency'],
        ];

        $el = $this->loader->loadFromArray($data)->getBands()[0]->getElements()[1];

        $this->assertSame('aggregate', $el->getContent()->getType());
        $this->assertSame('amount',    $el->getContent()->getField());
        $this->assertSame('sum',       $el->getContent()->getFn());
        $this->assertSame('currency',  $el->getContent()->getFormat());
        $this->assertSame('right',     $el->getAlign());
    }

    public function testThrowsOnMissingReportDefinitionId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $data = $this->minimalArray();
        unset($data['report_definition_id']);
        $this->loader->loadFromArray($data);
    }

    public function testThrowsOnMissingDataSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $data = $this->minimalArray();
        unset($data['data_source']);
        $this->loader->loadFromArray($data);
    }

    public function testThrowsOnMissingBands(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $data = $this->minimalArray();
        unset($data['bands']);
        $this->loader->loadFromArray($data);
    }

    public function testThrowsWhenFileNotFound(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->loader->load('/nonexistent/path/report.json');
    }
}
