<?php

namespace HiEvents\Services\Domain\Order;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\DomainObjects\EventSettingDomainObject;
use HiEvents\DomainObjects\InvoiceDomainObject;
use HiEvents\DomainObjects\OrderDomainObject;
use HiEvents\DomainObjects\OrganizerDomainObject;
use Psr\Log\LoggerInterface;

class InvoicePdfRenderer
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function render(
        OrderDomainObject $order,
        EventDomainObject $event,
        OrganizerDomainObject $organizer,
        EventSettingDomainObject $eventSettings,
        InvoiceDomainObject $invoice,
    ): DomPdf {
        return Pdf::loadView('invoice', [
            'order' => $order,
            'event' => $event,
            'organizer' => $organizer,
            'eventSettings' => $eventSettings,
            'invoice' => $invoice,
            'embedCjkFont' => $this->fontCacheIsWritable(),
        ])->setOption('isFontSubsettingEnabled', true);
    }

    private function fontCacheIsWritable(): bool
    {
        foreach (array_unique([config('dompdf.options.font_dir'), config('dompdf.options.font_cache')]) as $directory) {
            if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                $this->logFontCacheUnavailable($directory);

                return false;
            }

            if (! is_writable($directory)) {
                $this->logFontCacheUnavailable($directory);

                return false;
            }
        }

        return true;
    }

    private function logFontCacheUnavailable(string $directory): void
    {
        $this->logger->warning('Invoice PDF font cache directory is not writable, rendering invoice without the CJK font', [
            'directory' => $directory,
        ]);
    }
}
