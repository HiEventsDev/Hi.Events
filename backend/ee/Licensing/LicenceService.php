<?php

declare(strict_types=1);

namespace HiEvents\Enterprise\Licensing;

use Carbon\CarbonImmutable;
use HiEvents\Enterprise\Licensing\DTO\LicencePayloadDTO;
use HiEvents\Enterprise\Licensing\DTO\LicenceStateDTO;
use HiEvents\Enterprise\Licensing\Exceptions\InvalidLicenceKeyException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository;
use Psr\Log\LoggerInterface;
use Throwable;

class LicenceService
{
    public const GRACE_DAYS = 30;

    public const SIMULATION_HEADER = 'X-Hi-Licence-Simulation';

    private const DEVELOPMENT_KEY = 'development';

    private const DEVELOPMENT_ENVIRONMENTS = ['testing', 'e2e'];

    private const SIMULATION_ENVIRONMENTS = ['testing', 'e2e'];

    private const INVALID_KEY_REPORT_SECONDS = 86400;

    private ?LicenceStateDTO $state = null;

    public function __construct(
        private readonly LicenceKeyCodec $codec,
        private readonly Repository $config,
        private readonly LoggerInterface $logger,
        private readonly Cache $cache,
    ) {}

    public function allows(LicensedFeature $feature): bool
    {
        $state = $this->state();

        return $state->status->unlocksFeatures() && in_array($feature, $state->features, true);
    }

    public function status(): LicenceStatus
    {
        return $this->state()->status;
    }

    public function state(): LicenceStateDTO
    {
        return $this->state ??= $this->resolveState();
    }

    public function isCloud(): bool
    {
        return $this->state()->licence?->plan === Plan::CLOUD;
    }

    private function resolveState(): LicenceStateDTO
    {
        $simulated = $this->simulatedState();
        if ($simulated !== null) {
            return $simulated;
        }

        $key = trim((string) $this->config->get('licence.key'));

        if ($key === self::DEVELOPMENT_KEY || ($key === '' && $this->isDevelopmentEnvironment())) {
            return new LicenceStateDTO(status: LicenceStatus::DEV, features: self::developmentFeatures());
        }

        if ($key === '') {
            return new LicenceStateDTO(status: LicenceStatus::NONE, features: []);
        }

        try {
            $licence = $this->codec->decode($key);
        } catch (InvalidLicenceKeyException $exception) {
            $this->reportInvalidKey($key, $exception);

            return new LicenceStateDTO(
                status: LicenceStatus::NONE,
                features: [],
                invalid_reason: $exception->getMessage(),
            );
        }

        return $this->stateFor($licence);
    }

    private function stateFor(LicencePayloadDTO $licence): LicenceStateDTO
    {
        $graceEndsAt = $licence->expires_at->addDays(self::GRACE_DAYS);
        $now = CarbonImmutable::now();

        return new LicenceStateDTO(
            status: match (true) {
                $now->lt($licence->expires_at) => LicenceStatus::ACTIVE,
                $now->lt($graceEndsAt) => LicenceStatus::GRACE,
                default => LicenceStatus::LAPSED,
            },
            features: $licence->features,
            licence: $licence,
            grace_ends_at: $graceEndsAt,
        );
    }

    private function simulatedState(): ?LicenceStateDTO
    {
        $simulation = $this->config->get('licence.simulation');

        if (! is_string($simulation) || ! in_array($this->config->get('app.env'), self::SIMULATION_ENVIRONMENTS, true)) {
            return null;
        }

        [$status, $features] = array_pad(explode(':', trim($simulation), 2), 2, null);
        $status = strtoupper((string) $status);
        $plan = $status === 'CLOUD' ? Plan::CLOUD : Plan::ENTERPRISE;
        $features = $features === null
            ? $plan->features()
            : array_values(array_filter(array_map(
                static fn (string $feature) => LicensedFeature::tryFrom(trim($feature)),
                explode(',', $features),
            )));

        $expiresInDays = match ($status) {
            'ACTIVE', 'CLOUD' => 365,
            'EXPIRING' => 10,
            'GRACE' => -5,
            'LAPSED' => -(self::GRACE_DAYS + 30),
            default => null,
        };

        if ($expiresInDays !== null) {
            $now = CarbonImmutable::now('UTC')->startOfDay();

            return $this->stateFor(new LicencePayloadDTO(
                lid: 'lic_simulated',
                customer: 'Simulated licence',
                plan: $plan,
                features: $features,
                issued_at: $now->subYear(),
                expires_at: $now->addDays($expiresInDays),
            ));
        }

        return match ($status) {
            'NONE' => new LicenceStateDTO(status: LicenceStatus::NONE, features: []),
            'INVALID' => new LicenceStateDTO(
                status: LicenceStatus::NONE,
                features: [],
                invalid_reason: 'The licence key signature is invalid',
            ),
            'DEV' => new LicenceStateDTO(status: LicenceStatus::DEV, features: self::developmentFeatures()),
            default => null,
        };
    }

    private function isDevelopmentEnvironment(): bool
    {
        return in_array($this->config->get('app.env'), self::DEVELOPMENT_ENVIRONMENTS, true);
    }

    /**
     * @return LicensedFeature[]
     */
    private static function developmentFeatures(): array
    {
        return array_values(array_filter(
            LicensedFeature::cases(),
            static fn (LicensedFeature $feature) => $feature !== LicensedFeature::WHITE_LABEL,
        ));
    }

    private function reportInvalidKey(string $key, InvalidLicenceKeyException $exception): void
    {
        try {
            $isFirstReport = $this->cache->add('licence_key_rejected:'.hash('sha256', $key), true, self::INVALID_KEY_REPORT_SECONDS);
        } catch (Throwable) {
            $isFirstReport = true;
        }

        if ($isFirstReport) {
            $this->logger->warning('Hi.Events licence key rejected', ['reason' => $exception->getMessage()]);
        }
    }
}
