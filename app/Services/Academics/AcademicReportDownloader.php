<?php

namespace App\Services\Academics;

use App\Exports\AcademicArrayExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AcademicReportDownloader
{
    /**
     * @param  array{offerings?: list<array>, rows?: list<array>, meta?: array}  $report
     * @return array{title: string, subtitle: string, headings: list<string>, rows: list<list<mixed>>}
     */
    public function marklistTable(array $report): array
    {
        $headings = ['Student', 'Code'];
        foreach ($report['offerings'] ?? [] as $offering) {
            foreach ($offering['assessments'] ?? [] as $assessment) {
                $headings[] = ($offering['subject'] ?? 'Subject').' · '.($assessment['name'] ?? '');
            }
            $headings[] = ($offering['subject'] ?? 'Subject').' total';
        }

        $rows = [];
        foreach ($report['rows'] ?? [] as $row) {
            $line = [$row['name'] ?? '', $row['code'] ?? ''];
            foreach ($report['offerings'] ?? [] as $offering) {
                foreach ($offering['assessments'] ?? [] as $assessment) {
                    $line[] = $row['cells']['a_'.$assessment['id']] ?? '';
                }
                $line[] = $row['cells']['s_'.$offering['subject_id']] ?? '';
            }
            $rows[] = $line;
        }

        return $this->tablePayload('Marklist report', $report['meta'] ?? [], $headings, $rows);
    }

    /**
     * @param  array{subjects?: list<array>, rows?: list<array>, meta?: array}  $report
     * @return array{title: string, subtitle: string, headings: list<string>, rows: list<list<mixed>>}
     */
    public function rosterTable(array $report): array
    {
        $headings = ['Student', 'Code'];
        foreach ($report['subjects'] ?? [] as $subject) {
            $headings[] = $subject['name'] ?? 'Subject';
        }
        $headings[] = 'Total';
        $headings[] = 'Average';
        $headings[] = 'Rank';

        $rows = [];
        foreach ($report['rows'] ?? [] as $row) {
            $line = [$row['name'] ?? '', $row['code'] ?? ''];
            foreach ($report['subjects'] ?? [] as $subject) {
                $cell = $row['subjects'][$subject['id']] ?? null;
                $line[] = $cell['total'] ?? '';
            }
            $rank = $row['rank'] ?? '';
            if (! empty($row['rank_of'])) {
                $rank = $rank.'/'.$row['rank_of'];
            }
            $line[] = $row['total'] ?? '';
            $line[] = $row['average'] ?? '';
            $line[] = $rank;
            $rows[] = $line;
        }

        return $this->tablePayload('Roster report', $report['meta'] ?? [], $headings, $rows);
    }

    /**
     * @param  array{offerings?: list<array>, rows?: list<array>, meta?: array}  $report
     */
    public function downloadMarklist(array $report, string $format): ?StreamedResponse
    {
        return $this->downloadTable($this->marklistTable($report), $format, 'marklist-report');
    }

    /**
     * @param  array{subjects?: list<array>, rows?: list<array>, meta?: array, needs_compute?: bool}  $report
     */
    public function downloadRoster(array $report, string $format): ?StreamedResponse
    {
        if ($report['needs_compute'] ?? false) {
            Notification::make()
                ->title('Compute results first')
                ->body('The roster snapshot is empty. Click Compute results, then download.')
                ->warning()
                ->send();

            return null;
        }

        return $this->downloadTable($this->rosterTable($report), $format, 'roster-report');
    }

    /**
     * @param  array{title: string, subtitle: string, headings: list<string>, rows: list<list<mixed>>}  $table
     */
    public function downloadTable(array $table, string $format, string $basename): ?StreamedResponse
    {
        $format = strtolower($format);
        if (! in_array($format, ['xlsx', 'csv', 'pdf'], true)) {
            Notification::make()->title('Unsupported download format')->danger()->send();

            return null;
        }

        $stamp = now()->format('Y-m-d');

        if ($format === 'pdf') {
            $pdfContent = Pdf::loadView('pdf.academic-table-report', $table)
                ->setPaper('a4', 'landscape')
                ->output();

            return response()->streamDownload(
                function () use ($pdfContent) {
                    echo $pdfContent;
                },
                "{$basename}-{$stamp}.pdf",
                ['Content-Type' => 'application/pdf'],
            );
        }

        $writer = $format === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX;
        $extension = $format === 'csv' ? 'csv' : 'xlsx';
        $mime = $format === 'csv'
            ? 'text/csv; charset=UTF-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        $content = Excel::raw(
            new AcademicArrayExport($table['headings'], $table['rows'], $table['title']),
            $writer,
        );

        return response()->streamDownload(
            function () use ($content) {
                echo $content;
            },
            "{$basename}-{$stamp}.{$extension}",
            ['Content-Type' => $mime],
        );
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<mixed>>  $rows
     * @param  array<string, mixed>  $meta
     * @return array{title: string, subtitle: string, headings: list<string>, rows: list<list<mixed>>}
     */
    private function tablePayload(string $title, array $meta, array $headings, array $rows): array
    {
        return [
            'title' => $title,
            'subtitle' => implode(' · ', array_filter([
                $meta['term'] ?? null,
                $meta['class'] ?? null,
                $meta['batch'] ?? null,
            ], fn ($value) => filled($value))),
            'headings' => $headings,
            'rows' => $rows,
        ];
    }
}
