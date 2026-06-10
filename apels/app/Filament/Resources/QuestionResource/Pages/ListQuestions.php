<?php

namespace App\Filament\Resources\QuestionResource\Pages;

use App\Filament\Resources\QuestionResource;
use App\Services\Csv\CsvQuestionExporter;
use App\Services\Csv\CsvQuestionImporter;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * ListQuestions — includes Import header action (Req 20.1) and Export (Req 20.5).
 */
class ListQuestions extends ListRecords
{
    protected static string $resource = QuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            // Import CSV/XLSX (Req 20.1-20.4)
            Actions\Action::make('import')
                ->label('Import')
                ->icon('heroicon-o-arrow-up-tray')
                ->form([
                    Forms\Components\FileUpload::make('file')
                        ->label('File CSV atau XLSX')
                        ->acceptedFileTypes([
                            'text/csv',
                            'application/vnd.ms-excel',
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        ])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $filePath = $data['file'];

                    // Get the uploaded file from Livewire temp storage
                    $fullPath = Storage::disk('local')->path($filePath);

                    if (!file_exists($fullPath)) {
                        Notification::make()
                            ->title('File tidak ditemukan')
                            ->danger()
                            ->send();
                        return;
                    }

                    $content = file_get_contents($fullPath);
                    $importer = app(CsvQuestionImporter::class);
                    $result = $importer->parse($content);

                    // Create valid records
                    $created = 0;
                    foreach ($result['valid'] as $row) {
                        \App\Models\Question::create($row);
                        $created++;
                    }

                    $errorCount = count($result['errors']);

                    if ($errorCount > 0) {
                        $errorSummary = collect($result['errors'])
                            ->take(5)
                            ->map(fn ($e) => "Baris {$e['row']}: " . implode(', ', $e['reasons']))
                            ->implode("\n");

                        Notification::make()
                            ->title("Import selesai: {$created} berhasil, {$errorCount} gagal")
                            ->body($errorSummary)
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()
                            ->title("Import berhasil: {$created} soal ditambahkan")
                            ->success()
                            ->send();
                    }

                    // Cleanup temp file
                    Storage::disk('local')->delete($filePath);
                }),

            // Export CSV (Req 20.5)
            Actions\Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $questions = \App\Models\Question::all();
                    $exporter = app(CsvQuestionExporter::class);
                    $csv = $exporter->toCsvString($questions);

                    return response()->streamDownload(function () use ($csv) {
                        echo $csv;
                    }, 'questions_export_' . now()->format('Ymd_His') . '.csv', [
                        'Content-Type' => 'text/csv',
                    ]);
                }),
        ];
    }
}
