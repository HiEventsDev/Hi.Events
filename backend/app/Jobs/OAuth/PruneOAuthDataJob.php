<?php

declare(strict_types=1);

namespace HiEvents\Jobs\OAuth;

use HiEvents\Services\Domain\Auth\OAuth\OAuthPruneService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PruneOAuthDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(OAuthPruneService $pruneService): void
    {
        $pruneService->prune();
    }
}
