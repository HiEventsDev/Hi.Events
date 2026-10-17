<?php

namespace HiEvents\Console\Commands;

use HiEvents\OpenApi\IntegrationApiReference;
use Illuminate\Console\Command;

class ExportApiReferenceCommand extends Command
{
    protected $signature = 'api-reference:export {--check : Fail if the committed reference is out of date instead of writing it}';

    protected $description = 'Write the in-app API reference (the endpoints API keys can call) to resources/api-reference/openapi.json.';

    public function handle(IntegrationApiReference $reference): int
    {
        ini_set('memory_limit', '1G');

        $generated = $reference->generate();
        $committed = is_file($reference->path()) ? file_get_contents($reference->path()) : null;

        if ($this->option('check')) {
            if ($generated === $committed) {
                $this->info('The API reference is up to date.');

                return self::SUCCESS;
            }

            $this->error('The API reference is out of date. Run `php artisan api-reference:export` and commit resources/api-reference/openapi.json.');

            return self::FAILURE;
        }

        file_put_contents($reference->path(), $generated);
        $this->info('Wrote '.$reference->path());

        return self::SUCCESS;
    }
}
