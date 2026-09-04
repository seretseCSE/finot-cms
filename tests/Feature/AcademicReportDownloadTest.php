<?php

namespace Tests\Feature;

use App\Services\Academics\AcademicReportDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class AcademicReportDownloadTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function marklistReport(): array
    {
        return [
            'meta' => [
                'term' => 'Semester 1',
                'class' => 'Year 1',
            ],
            'offerings' => [
                [
                    'id' => 1,
                    'subject_id' => 10,
                    'subject' => 'Math',
                    'assessments' => [
                        ['id' => 5, 'name' => 'Midterm'],
                    ],
                ],
            ],
            'rows' => [
                [
                    'name' => 'Abebe Kebede',
                    'code' => 'STU-001',
                    'cells' => [
                        'a_5' => 40,
                        's_10' => 40,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function rosterReport(): array
    {
        return [
            'needs_compute' => false,
            'meta' => [
                'term' => 'Semester 1',
                'class' => 'Year 1',
            ],
            'subjects' => [
                ['id' => 10, 'name' => 'Math'],
            ],
            'rows' => [
                [
                    'name' => 'Abebe Kebede',
                    'code' => 'STU-001',
                    'subjects' => [
                        10 => ['total' => 85],
                    ],
                    'total' => 85,
                    'average' => 85,
                    'rank' => 1,
                    'rank_of' => 12,
                ],
            ],
        ];
    }

    #[Test]
    public function marklist_table_includes_assessment_columns(): void
    {
        $table = app(AcademicReportDownloader::class)->marklistTable($this->marklistReport());

        $this->assertSame(['Student', 'Code', 'Math · Midterm', 'Math total'], $table['headings']);
        $this->assertSame(['Abebe Kebede', 'STU-001', 40, 40], $table['rows'][0]);
        $this->assertSame('Semester 1 · Year 1', $table['subtitle']);
    }

    #[Test]
    public function roster_table_puts_rank_after_average(): void
    {
        $table = app(AcademicReportDownloader::class)->rosterTable($this->rosterReport());

        $this->assertSame(['Student', 'Code', 'Math', 'Total', 'Average', 'Rank'], $table['headings']);
        $this->assertSame(['Abebe Kebede', 'STU-001', 85, 85, 85, '1/12'], $table['rows'][0]);
        $this->assertSame('Rank', $table['headings'][array_key_last($table['headings'])]);
    }

    #[Test]
    public function marklist_downloads_excel_csv_and_pdf(): void
    {
        $this->actingAs($this->createEducationHeadUser());
        $this->freezeTime();

        $downloader = app(AcademicReportDownloader::class);
        $report = $this->marklistReport();

        $csv = $downloader->downloadMarklist($report, 'csv');
        $this->assertInstanceOf(StreamedResponse::class, $csv);
        $this->assertStringContainsString('marklist-report-'.now()->format('Y-m-d').'.csv', (string) $csv->headers->get('content-disposition'));
        $csvBody = $this->streamedBody($csv);
        $this->assertStringContainsString('Abebe Kebede', $csvBody);
        $this->assertStringContainsString('Math · Midterm', $csvBody);

        $xlsx = $downloader->downloadMarklist($report, 'xlsx');
        $this->assertInstanceOf(StreamedResponse::class, $xlsx);
        $this->assertStringContainsString('.xlsx', (string) $xlsx->headers->get('content-disposition'));
        $this->assertNotSame('', $this->streamedBody($xlsx));

        $pdf = $downloader->downloadMarklist($report, 'pdf');
        $this->assertInstanceOf(StreamedResponse::class, $pdf);
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $this->streamedBody($pdf));
    }

    #[Test]
    public function roster_download_is_blocked_until_results_are_computed(): void
    {
        $this->actingAs($this->createEducationHeadUser());

        $response = app(AcademicReportDownloader::class)->downloadRoster([
            'needs_compute' => true,
            'rows' => [],
        ], 'xlsx');

        $this->assertNull($response);
    }

    #[Test]
    public function marklist_and_roster_pages_are_accessible(): void
    {
        $user = $this->createEducationHeadUser();

        $marklist = $this->actingAs($user)->get('/admin/marklist-report-page');
        $this->assertNotEquals(404, $marklist->getStatusCode(), 'Marklist route not found');
        $this->assertNotEquals(403, $marklist->getStatusCode(), 'Marklist forbidden');
        $marklist->assertSee('Show marklist');

        $roster = $this->actingAs($user)->get('/admin/roster-report-page');
        $this->assertNotEquals(404, $roster->getStatusCode(), 'Roster route not found');
        $this->assertNotEquals(403, $roster->getStatusCode(), 'Roster forbidden');
        $roster->assertSee('Show roster');
    }

    private function streamedBody(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();

        return (string) ob_get_clean();
    }
}
