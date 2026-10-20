<?php

namespace Tests\Feature\Http\TeamAccess;

use HiEvents\DomainObjects\Enums\Permission;
use HiEvents\Enterprise\BoxOffice\Http\Actions\GetBoxOfficeStatsAction;
use HiEvents\Http\Actions\Accounts\DeletionRequest\CancelAccountDeletionAction;
use HiEvents\Http\Actions\Accounts\DeletionRequest\GetAccountDeletionStatusAction;
use HiEvents\Http\Actions\Accounts\DeletionRequest\RequestAccountDeletionAction;
use HiEvents\Http\Actions\Accounts\GetAccountAction;
use HiEvents\Http\Actions\Accounts\UpdateAccountAction;
use HiEvents\Http\Actions\Affiliates\ExportAffiliatesAction;
use HiEvents\Http\Actions\Announcements\DismissAnnouncementAction;
use HiEvents\Http\Actions\Announcements\GetActiveAnnouncementsAction;
use HiEvents\Http\Actions\ApiKeys\CreateApiKeyAction;
use HiEvents\Http\Actions\ApiKeys\DeleteApiKeyAction;
use HiEvents\Http\Actions\ApiKeys\GetApiKeysAction;
use HiEvents\Http\Actions\ApiReference\GetApiReferenceAction;
use HiEvents\Http\Actions\Attendees\ExportAttendeesAction;
use HiEvents\Http\Actions\Auth\JoinInvitationAction;
use HiEvents\Http\Actions\Auth\LogoutAction;
use HiEvents\Http\Actions\Auth\RefreshTokenAction;
use HiEvents\Http\Actions\Auth\Sso\DeleteSsoIdentityAction;
use HiEvents\Http\Actions\Auth\Sso\ExchangeAuthenticatedSsoCodeAction;
use HiEvents\Http\Actions\Auth\Sso\GetSsoIdentitiesAction;
use HiEvents\Http\Actions\Auth\Sso\StartSsoLinkAction;
use HiEvents\Http\Actions\Auth\Sso\StartSsoReauthAction;
use HiEvents\Http\Actions\EmailTemplates\GetAvailableTokensAction;
use HiEvents\Http\Actions\EmailTemplates\GetDefaultEmailTemplateAction;
use HiEvents\Http\Actions\EmailTemplates\PreviewEventEmailTemplateAction;
use HiEvents\Http\Actions\EmailTemplates\PreviewOrganizerEmailTemplateAction;
use HiEvents\Http\Actions\Events\CreateEventAction;
use HiEvents\Http\Actions\Events\GetEventsAction;
use HiEvents\Http\Actions\EventSettings\GetPlatformFeePreviewAction;
use HiEvents\Http\Actions\Locations\GetGeoStatusAction;
use HiEvents\Http\Actions\Orders\DownloadOrderInvoiceAction;
use HiEvents\Http\Actions\Orders\ExportOrdersAction;
use HiEvents\Http\Actions\Orders\MarkOrderAsPaidAction;
use HiEvents\Http\Actions\Orders\Payment\RefundOrderAction;
use HiEvents\Http\Actions\Organizers\CreateOrganizerAction;
use HiEvents\Http\Actions\Organizers\GetOrganizersAction;
use HiEvents\Http\Actions\Organizers\Vat\GetOrganizerVatSettingAction;
use HiEvents\Http\Actions\Organizers\Webhooks\CreateOrganizerWebhookAction;
use HiEvents\Http\Actions\Organizers\Webhooks\DeleteOrganizerWebhookAction;
use HiEvents\Http\Actions\Organizers\Webhooks\EditOrganizerWebhookAction;
use HiEvents\Http\Actions\Organizers\Webhooks\GetOrganizerWebhookAction;
use HiEvents\Http\Actions\Organizers\Webhooks\GetOrganizerWebhookLogsAction;
use HiEvents\Http\Actions\Organizers\Webhooks\GetOrganizerWebhooksAction;
use HiEvents\Http\Actions\Products\Purchases\ExportProductPurchasesAction;
use HiEvents\Http\Actions\Products\Purchases\GetProductPurchasesAction;
use HiEvents\Http\Actions\Products\Purchases\GetProductPurchaseSummaryAction;
use HiEvents\Http\Actions\Questions\ExportQuestionAnswersAction;
use HiEvents\Http\Actions\Reports\GetReportAction;
use HiEvents\Http\Actions\Users\CancelEmailChangeAction;
use HiEvents\Http\Actions\Users\ConfirmEmailAddressAction;
use HiEvents\Http\Actions\Users\ConfirmEmailChangeAction;
use HiEvents\Http\Actions\Users\ConfirmEmailWithCodeAction;
use HiEvents\Http\Actions\Users\CreateUserAction;
use HiEvents\Http\Actions\Users\DeleteInvitationAction;
use HiEvents\Http\Actions\Users\GetMeAction;
use HiEvents\Http\Actions\Users\GetUserAction;
use HiEvents\Http\Actions\Users\GetUsersAction;
use HiEvents\Http\Actions\Users\ResendEmailConfirmationAction;
use HiEvents\Http\Actions\Users\ResendInvitationAction;
use HiEvents\Http\Actions\Users\TwoFactor\BeginTwoFactorSetupAction;
use HiEvents\Http\Actions\Users\TwoFactor\ConfirmTwoFactorSetupAction;
use HiEvents\Http\Actions\Users\TwoFactor\DisableTwoFactorAction;
use HiEvents\Http\Actions\Users\TwoFactor\GetTwoFactorStatusAction;
use HiEvents\Http\Actions\Users\TwoFactor\RegenerateRecoveryCodesAction;
use HiEvents\Http\Actions\Users\TwoFactor\ResetAccountUserTwoFactorAction;
use HiEvents\Http\Actions\Users\TwoFactor\RevokeTrustedDeviceAction;
use HiEvents\Http\Actions\Users\TwoFactor\RevokeTrustedDevicesAction;
use HiEvents\Http\Actions\Users\UpdateMeAction;
use HiEvents\Http\Actions\Users\UpdateUserAction;
use HiEvents\Http\Actions\Webhooks\CreateWebhookAction;
use HiEvents\Http\Actions\Webhooks\DeleteWebhookAction;
use HiEvents\Http\Actions\Webhooks\EditWebhookAction;
use HiEvents\Http\Actions\Webhooks\GetWebhookAction;
use HiEvents\Http\Actions\Webhooks\GetWebhookLogsAction;
use HiEvents\Http\Actions\Webhooks\GetWebhooksAction;
use HiEvents\Http\Attributes\AllowedForReadOnlyUsers;
use HiEvents\Http\Attributes\RequiresPermission;
use Illuminate\Routing\Route;
use ReflectionClass;
use Tests\TestCase;

class AccessCoverageTest extends TestCase
{
    private const ENTITY_CHECKS = '/isActionAuthorized\(|isOrganizerReadAuthorized\(|isAccountReadAuthorized\(|authorizeSeatMapAccess\(|authorizeSeatMapSetup\(|authorizeEventSeatMapAccess\(/';

    private const SELF_SERVICE_OR_STATIC = [
        GetMeAction::class,
        UpdateMeAction::class,
        GetTwoFactorStatusAction::class,
        BeginTwoFactorSetupAction::class,
        ConfirmTwoFactorSetupAction::class,
        RegenerateRecoveryCodesAction::class,
        DisableTwoFactorAction::class,
        RevokeTrustedDevicesAction::class,
        RevokeTrustedDeviceAction::class,
        ResendEmailConfirmationAction::class,
        ConfirmEmailWithCodeAction::class,
        LogoutAction::class,
        RefreshTokenAction::class,
        GetActiveAnnouncementsAction::class,
        DismissAnnouncementAction::class,
        GetDefaultEmailTemplateAction::class,
        GetAvailableTokensAction::class,
        GetGeoStatusAction::class,
        GetAccountAction::class,
        GetSsoIdentitiesAction::class,
        StartSsoLinkAction::class,
        StartSsoReauthAction::class,
        ExchangeAuthenticatedSsoCodeAction::class,
        DeleteSsoIdentityAction::class,
        JoinInvitationAction::class,
        GetApiKeysAction::class,
        CreateApiKeyAction::class,
        DeleteApiKeyAction::class,
        GetApiReferenceAction::class,
    ];

    private const SCOPE_FILTERED_OR_CHECKED_INLINE = [
        GetEventsAction::class,
        GetOrganizersAction::class,
        CreateEventAction::class,
        CreateOrganizerAction::class,
    ];

    private const ACCOUNT_LEVEL_WITH_PERMISSION = [
        UpdateAccountAction::class => Permission::MANAGE_ACCOUNT,
        RequestAccountDeletionAction::class => Permission::MANAGE_ACCOUNT,
        CancelAccountDeletionAction::class => Permission::MANAGE_ACCOUNT,
        GetAccountDeletionStatusAction::class => Permission::MANAGE_ACCOUNT,
        GetUsersAction::class => Permission::MANAGE_TEAM,
        GetUserAction::class => Permission::MANAGE_TEAM,
        CreateUserAction::class => Permission::MANAGE_TEAM,
        ResendInvitationAction::class => Permission::MANAGE_TEAM,
        ResetAccountUserTwoFactorAction::class => Permission::MANAGE_TEAM,
        UpdateUserAction::class => Permission::MANAGE_TEAM,
        DeleteInvitationAction::class => Permission::MANAGE_TEAM,
    ];

    private const ALLOWED_FOR_READ_ONLY_USERS = [
        BeginTwoFactorSetupAction::class,
        CreateApiKeyAction::class,
        DeleteApiKeyAction::class,
        DeleteSsoIdentityAction::class,
        ExchangeAuthenticatedSsoCodeAction::class,
        GetSsoIdentitiesAction::class,
        JoinInvitationAction::class,
        StartSsoLinkAction::class,
        StartSsoReauthAction::class,
        CancelEmailChangeAction::class,
        ConfirmEmailAddressAction::class,
        ConfirmEmailChangeAction::class,
        ConfirmEmailWithCodeAction::class,
        ConfirmTwoFactorSetupAction::class,
        DisableTwoFactorAction::class,
        DismissAnnouncementAction::class,
        ExportAffiliatesAction::class,
        ExportAttendeesAction::class,
        ExportOrdersAction::class,
        ExportProductPurchasesAction::class,
        ExportQuestionAnswersAction::class,
        PreviewEventEmailTemplateAction::class,
        PreviewOrganizerEmailTemplateAction::class,
        RegenerateRecoveryCodesAction::class,
        ResendEmailConfirmationAction::class,
        RevokeTrustedDeviceAction::class,
        RevokeTrustedDevicesAction::class,
        UpdateMeAction::class,
    ];

    private const FINANCIAL_ACTIONS = [
        GetReportAction::class => Permission::VIEW_FINANCIALS,
        ExportOrdersAction::class => Permission::VIEW_FINANCIALS,
        ExportProductPurchasesAction::class => Permission::VIEW_FINANCIALS,
        ExportAffiliatesAction::class => Permission::VIEW_FINANCIALS,
        DownloadOrderInvoiceAction::class => Permission::VIEW_FINANCIALS,
        GetProductPurchasesAction::class => Permission::VIEW_FINANCIALS,
        GetProductPurchaseSummaryAction::class => Permission::VIEW_FINANCIALS,
        GetPlatformFeePreviewAction::class => Permission::VIEW_FINANCIALS,
        GetBoxOfficeStatsAction::class => Permission::VIEW_FINANCIALS,
        GetOrganizerVatSettingAction::class => Permission::VIEW_FINANCIALS,
        CreateWebhookAction::class => Permission::VIEW_FINANCIALS,
        GetWebhooksAction::class => Permission::VIEW_FINANCIALS,
        EditWebhookAction::class => Permission::VIEW_FINANCIALS,
        GetWebhookAction::class => Permission::VIEW_FINANCIALS,
        DeleteWebhookAction::class => Permission::VIEW_FINANCIALS,
        GetWebhookLogsAction::class => Permission::VIEW_FINANCIALS,
        CreateOrganizerWebhookAction::class => Permission::VIEW_FINANCIALS,
        GetOrganizerWebhooksAction::class => Permission::VIEW_FINANCIALS,
        EditOrganizerWebhookAction::class => Permission::VIEW_FINANCIALS,
        GetOrganizerWebhookAction::class => Permission::VIEW_FINANCIALS,
        DeleteOrganizerWebhookAction::class => Permission::VIEW_FINANCIALS,
        GetOrganizerWebhookLogsAction::class => Permission::VIEW_FINANCIALS,
        RefundOrderAction::class => Permission::ISSUE_REFUNDS,
        MarkOrderAsPaidAction::class => Permission::ISSUE_REFUNDS,
    ];

    public function test_financial_actions_require_their_permission(): void
    {
        foreach (self::FINANCIAL_ACTIONS as $action => $permission) {
            $this->assertContains($permission, $this->requiredPermissions($action), $action);
        }
    }

    public function test_every_logged_in_action_checks_access_or_is_explicitly_accounted_for(): void
    {
        $actions = $this->loggedInActions();
        $this->assertGreaterThan(150, count($actions));

        $unaccounted = collect($actions)
            ->reject(fn (string $action) => preg_match(self::ENTITY_CHECKS, file_get_contents((new ReflectionClass($action))->getFileName())) === 1)
            ->reject(fn (string $action) => in_array($action, self::SELF_SERVICE_OR_STATIC, true))
            ->reject(fn (string $action) => in_array($action, self::SCOPE_FILTERED_OR_CHECKED_INLINE, true))
            ->reject(fn (string $action) => array_key_exists($action, self::ACCOUNT_LEVEL_WITH_PERMISSION))
            ->values()
            ->all();

        $this->assertSame([], $unaccounted, 'These actions neither check event/organizer access nor appear in an allowlist here.');
    }

    public function test_only_self_service_exports_and_previews_are_open_to_read_only_members(): void
    {
        $allowed = collect($this->loggedInActions())
            ->filter(fn (string $action) => (new ReflectionClass($action))->getAttributes(AllowedForReadOnlyUsers::class) !== [])
            ->sort()
            ->values()
            ->all();

        $this->assertSame(collect(self::ALLOWED_FOR_READ_ONLY_USERS)->sort()->values()->all(), $allowed);
    }

    public function test_account_level_actions_require_their_permission(): void
    {
        foreach (self::ACCOUNT_LEVEL_WITH_PERMISSION as $action => $permission) {
            $this->assertContains($permission, $this->requiredPermissions($action), $action);
        }
    }

    /**
     * @return class-string[]
     */
    private function loggedInActions(): array
    {
        return collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => array_intersect(['auth:api', 'auth:api,sanctum'], $route->gatherMiddleware()) !== [])
            ->reject(fn (Route $route) => str_starts_with($route->uri(), 'admin/'))
            ->map(fn (Route $route) => $route->getControllerClass())
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Permission[]
     */
    private function requiredPermissions(string $action): array
    {
        return collect((new ReflectionClass($action))->getAttributes(RequiresPermission::class))
            ->flatMap(fn ($attribute) => $attribute->newInstance()->permissions)
            ->all();
    }
}
