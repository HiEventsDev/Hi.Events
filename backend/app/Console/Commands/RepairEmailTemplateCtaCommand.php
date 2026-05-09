<?php

namespace HiEvents\Console\Commands;

use HiEvents\DomainObjects\Enums\EmailTemplateType;
use HiEvents\Models\EmailTemplate;
use HiEvents\Services\Domain\Email\EmailTemplateService;
use Illuminate\Console\Command;

class RepairEmailTemplateCtaCommand extends Command
{
    protected $signature = 'email-templates:repair-cta-url-token
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Reset cta.url_token on email_templates rows to the default for each template_type. Repairs damage from a prior bug where attendee_ticket templates were saved with order.url.';

    public function __construct(private readonly EmailTemplateService $emailTemplateService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $defaults = [];
        foreach (EmailTemplateType::cases() as $type) {
            $defaults[$type->value] = $this->emailTemplateService->getDefaultTemplate($type)['cta']['url_token'] ?? null;
        }

        $rows = EmailTemplate::query()->get();
        $changed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $type = $row->template_type;
            $expected = $defaults[$type] ?? null;

            if ($expected === null) {
                $skipped++;

                continue;
            }

            $cta = is_array($row->cta) ? $row->cta : (json_decode((string) $row->cta, true) ?: null);

            if ($cta === null) {
                $skipped++;

                continue;
            }

            $current = $cta['url_token'] ?? null;

            if ($current === $expected) {
                $skipped++;

                continue;
            }

            $this->line(sprintf(
                'id=%d type=%s url_token: %s -> %s',
                $row->id,
                $type,
                var_export($current, true),
                var_export($expected, true),
            ));

            if (! $dryRun) {
                $cta['url_token'] = $expected;
                $row->cta = $cta;
                $row->save();
            }

            $changed++;
        }

        $this->info(sprintf('%s%d changed, %d unchanged.', $dryRun ? '[dry-run] ' : '', $changed, $skipped));

        return self::SUCCESS;
    }
}
