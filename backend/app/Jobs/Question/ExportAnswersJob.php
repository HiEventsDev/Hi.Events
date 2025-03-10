<?php

namespace HiEvents\Jobs\Question;

use HiEvents\Exports\AnswersExport;
use HiEvents\Services\Application\Handlers\Question\ExportAnswersHandler;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ExportAnswersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public function __construct(
        private readonly int $eventId,
    )
    {
    }

    public function handle(ExportAnswersHandler $exportAnswersHandler, AnswersExport $export): void
    {
        $questions = $exportAnswersHandler->handle($this->eventId);

        Excel::store(
            export: $export->withData($questions),
            filePath: "event_$this->eventId/answers-$this->batchId.xlsx",
            disk: 's3-private'
        );
    }
}
