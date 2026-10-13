<?php

namespace Tests\Unit\Services\Application\Handlers\User;

use HiEvents\DomainObjects\UserDomainObject;
use HiEvents\Services\Application\Handlers\User\ResendEmailConfirmationHandler;
use HiEvents\Services\Domain\User\EmailConfirmationService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class ResendEmailConfirmationHandlerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_sends_a_confirmation_to_unverified_users(): void
    {
        $user = (new UserDomainObject)->setId(1)->setEmailVerifiedAt(null);

        $service = Mockery::mock(EmailConfirmationService::class);
        $service->shouldReceive('sendConfirmation')->once()->with($user, 9);

        (new ResendEmailConfirmationHandler($service))->handle($user, 9);
    }

    public function test_does_not_email_users_who_are_already_verified(): void
    {
        $service = Mockery::mock(EmailConfirmationService::class);
        $service->shouldNotReceive('sendConfirmation');

        (new ResendEmailConfirmationHandler($service))->handle(
            (new UserDomainObject)->setId(1)->setEmailVerifiedAt('2026-01-01 00:00:00'),
            9,
        );
    }
}
