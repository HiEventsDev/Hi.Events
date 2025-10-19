<?php

use HiEvents\Http\Actions\Accounts\CreateAccountAction;
use HiEvents\Http\Actions\Accounts\GetAccountAction;
use HiEvents\Http\Actions\Accounts\Stripe\CreateStripeConnectAccountAction;
use HiEvents\Http\Actions\Accounts\UpdateAccountAction;
use HiEvents\Http\Actions\Affiliates\CreateAffiliateAction;
use HiEvents\Http\Actions\Affiliates\DeleteAffiliateAction;
use HiEvents\Http\Actions\Affiliates\ExportAffiliatesAction;
use HiEvents\Http\Actions\Affiliates\GetAffiliateAction;
use HiEvents\Http\Actions\Affiliates\GetAffiliatesAction;
use HiEvents\Http\Actions\Affiliates\UpdateAffiliateAction;
use HiEvents\Http\Actions\Attendees\CheckInAttendeeAction;
use HiEvents\Http\Actions\Attendees\CreateAttendeeAction;
use HiEvents\Http\Actions\Attendees\EditAttendeeAction;
use HiEvents\Http\Actions\Attendees\ExportAttendeesAction;
use HiEvents\Http\Actions\Attendees\GetAttendeeAction;
use HiEvents\Http\Actions\Attendees\GetAttendeeActionPublic;
use HiEvents\Http\Actions\Attendees\GetAttendeesAction;
use HiEvents\Http\Actions\Attendees\PartialEditAttendeeAction;
use HiEvents\Http\Actions\Attendees\ResendAttendeeTicketAction;
use HiEvents\Http\Actions\Auth\AcceptInvitationAction;
use HiEvents\Http\Actions\Auth\ForgotPasswordAction;
use HiEvents\Http\Actions\Auth\GetUserInvitationAction;
use HiEvents\Http\Actions\Auth\LoginAction;
use HiEvents\Http\Actions\Auth\LogoutAction;
use HiEvents\Http\Actions\Auth\RefreshTokenAction;
use HiEvents\Http\Actions\Auth\ResetPasswordAction;
use HiEvents\Http\Actions\Auth\ValidateResetPasswordTokenAction;
use HiEvents\Http\Actions\CapacityAssignments\CreateCapacityAssignmentAction;
use HiEvents\Http\Actions\CapacityAssignments\DeleteCapacityAssignmentAction;
use HiEvents\Http\Actions\CapacityAssignments\GetCapacityAssignmentAction;
use HiEvents\Http\Actions\CapacityAssignments\GetCapacityAssignmentsAction;
use HiEvents\Http\Actions\CapacityAssignments\UpdateCapacityAssignmentAction;
use HiEvents\Http\Actions\CheckInLists\CreateCheckInListAction;
use HiEvents\Http\Actions\CheckInLists\DeleteCheckInListAction;
use HiEvents\Http\Actions\CheckInLists\GetCheckInListAction;
use HiEvents\Http\Actions\CheckInLists\GetCheckInListsAction;
use HiEvents\Http\Actions\CheckInLists\Public\CreateAttendeeCheckInPublicAction;
use HiEvents\Http\Actions\CheckInLists\Public\DeleteAttendeeCheckInPublicAction;
use HiEvents\Http\Actions\CheckInLists\Public\GetCheckInListAttendeePublicAction;
use HiEvents\Http\Actions\CheckInLists\Public\GetCheckInListAttendeesPublicAction;
use HiEvents\Http\Actions\CheckInLists\Public\GetCheckInListPublicAction;
use HiEvents\Http\Actions\CheckInLists\UpdateCheckInListAction;
use HiEvents\Http\Actions\Common\GetColorThemesAction;
use HiEvents\Http\Actions\Common\Webhooks\StripeIncomingWebhookAction;
use HiEvents\Http\Actions\Events\CreateEventAction;
use HiEvents\Http\Actions\Events\DuplicateEventAction;
use HiEvents\Http\Actions\Events\GetEventAction;
use HiEvents\Http\Actions\Events\GetEventPublicAction;
use HiEvents\Http\Actions\Events\GetEventsAction;
use HiEvents\Http\Actions\Events\GetOrganizerEventsPublicAction;
use HiEvents\Http\Actions\Events\Images\CreateEventImageAction;
use HiEvents\Http\Actions\Events\Images\DeleteEventImageAction;
use HiEvents\Http\Actions\Events\Images\GetEventImagesAction;
use HiEvents\Http\Actions\Events\Stats\GetEventStatsAction;
use HiEvents\Http\Actions\Events\UpdateEventAction;
use HiEvents\Http\Actions\Events\UpdateEventStatusAction;
use HiEvents\Http\Actions\EventSettings\EditEventSettingsAction;
use HiEvents\Http\Actions\EventSettings\GetEventSettingsAction;
use HiEvents\Http\Actions\EventSettings\PartialEditEventSettingsAction;
use HiEvents\Http\Actions\Images\CreateImageAction;
use HiEvents\Http\Actions\Images\DeleteImageAction;
use HiEvents\Http\Actions\Messages\GetMessagesAction;
use HiEvents\Http\Actions\Messages\SendMessageAction;
use HiEvents\Http\Actions\Orders\CancelOrderAction;
use HiEvents\Http\Actions\Orders\DownloadOrderInvoiceAction;
use HiEvents\Http\Actions\Orders\EditOrderAction;
use HiEvents\Http\Actions\Orders\ExportOrdersAction;
use HiEvents\Http\Actions\Orders\GetOrderAction;
use HiEvents\Http\Actions\Orders\GetOrdersAction;
use HiEvents\Http\Actions\Orders\MarkOrderAsPaidAction;
use HiEvents\Http\Actions\Orders\MessageOrderAction;
use HiEvents\Http\Actions\Orders\Payment\RefundOrderAction;
use HiEvents\Http\Actions\Orders\Payment\Stripe\CreatePaymentIntentActionPublic;
use HiEvents\Http\Actions\Orders\Payment\Stripe\GetPaymentIntentActionPublic;
use HiEvents\Http\Actions\Orders\Public\CompleteOrderActionPublic;
use HiEvents\Http\Actions\Orders\Public\CreateOrderActionPublic;
use HiEvents\Http\Actions\Orders\Public\DownloadOrderInvoicePublicAction;
use HiEvents\Http\Actions\Orders\Public\GetOrderActionPublic;
use HiEvents\Http\Actions\Orders\Public\TransitionOrderToOfflinePaymentPublicAction;
use HiEvents\Http\Actions\Orders\ResendOrderConfirmationAction;
use HiEvents\Http\Actions\Organizers\CreateOrganizerAction;
use HiEvents\Http\Actions\Organizers\EditOrganizerAction;
use HiEvents\Http\Actions\Organizers\GetOrganizerAction;
use HiEvents\Http\Actions\Organizers\GetOrganizerEventsAction;
use HiEvents\Http\Actions\Organizers\GetOrganizersAction;
use HiEvents\Http\Actions\Organizers\GetPublicOrganizerAction;
use HiEvents\Http\Actions\Organizers\Orders\GetOrganizerOrdersAction;
use HiEvents\Http\Actions\Organizers\Public\SendOrganizerContactMessagePublicAction;
use HiEvents\Http\Actions\Organizers\Settings\GetOrganizerSettingsAction;
use HiEvents\Http\Actions\Organizers\Settings\PartialUpdateOrganizerSettingsAction;
use HiEvents\Http\Actions\Organizers\Stats\GetOrganizerStatsAction;
use HiEvents\Http\Actions\Organizers\UpdateOrganizerStatusAction;
use HiEvents\Http\Actions\ProductCategories\CreateProductCategoryAction;
use HiEvents\Http\Actions\ProductCategories\DeleteProductCategoryAction;
use HiEvents\Http\Actions\ProductCategories\EditProductCategoryAction;
use HiEvents\Http\Actions\ProductCategories\GetProductCategoriesAction;
use HiEvents\Http\Actions\ProductCategories\GetProductCategoryAction;
use HiEvents\Http\Actions\Products\CreateProductAction;
use HiEvents\Http\Actions\Products\DeleteProductAction;
use HiEvents\Http\Actions\Products\EditProductAction;
use HiEvents\Http\Actions\Products\GetProductAction;
use HiEvents\Http\Actions\Products\GetProductsAction;
use HiEvents\Http\Actions\Products\SortProductsAction;
use HiEvents\Http\Actions\PromoCodes\CreatePromoCodeAction;
use HiEvents\Http\Actions\PromoCodes\DeletePromoCodeAction;
use HiEvents\Http\Actions\PromoCodes\GetPromoCodeAction;
use HiEvents\Http\Actions\PromoCodes\GetPromoCodePublic;
use HiEvents\Http\Actions\PromoCodes\GetPromoCodesAction;
use HiEvents\Http\Actions\PromoCodes\UpdatePromoCodeAction;
use HiEvents\Http\Actions\Questions\CreateQuestionAction;
use HiEvents\Http\Actions\Questions\DeleteQuestionAction;
use HiEvents\Http\Actions\Questions\EditQuestionAction;
use HiEvents\Http\Actions\Questions\EditQuestionAnswerAction;
use HiEvents\Http\Actions\Questions\ExportQuestionAnswersAction;
use HiEvents\Http\Actions\Questions\GetQuestionAction;
use HiEvents\Http\Actions\Questions\GetQuestionsAction;
use HiEvents\Http\Actions\Questions\GetQuestionsPublicAction;
use HiEvents\Http\Actions\Questions\SortQuestionsAction;
use HiEvents\Http\Actions\Reports\GetReportAction;
use HiEvents\Http\Actions\TaxesAndFees\CreateTaxOrFeeAction;
use HiEvents\Http\Actions\TaxesAndFees\DeleteTaxOrFeeAction;
use HiEvents\Http\Actions\TaxesAndFees\EditTaxOrFeeAction;
use HiEvents\Http\Actions\TaxesAndFees\GetTaxOrFeeAction;
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
use HiEvents\Http\Actions\Users\UpdateMeAction;
use HiEvents\Http\Actions\Users\UpdateUserAction;
use HiEvents\Http\Actions\Webhooks\CreateWebhookAction;
use HiEvents\Http\Actions\Webhooks\DeleteWebhookAction;
use HiEvents\Http\Actions\Webhooks\EditWebhookAction;
use HiEvents\Http\Actions\Webhooks\GetWebhookAction;
use HiEvents\Http\Actions\Webhooks\GetWebhookLogsAction;
use HiEvents\Http\Actions\Webhooks\GetWebhooksAction;
use Illuminate\Routing\Router;

/** @var Router|Router $router */
$router = app()->get('router');

$router->prefix('/auth')->group(
    function (Router $router): void {
        // Auth
        $router->post('/login', LoginAction::class)->name('auth.login');
        $router->post('/logout', LogoutAction::class)->name('auth.logout');
        $router->post('/register', CreateAccountAction::class)->name('auth.register');
        $router->post('/forgot-password', ForgotPasswordAction::class)->name('auth.forgot-password');

        // Invitations
        $router->get('/invitation/{invite_token}', GetUserInvitationAction::class)->name('auth.invitation');
        $router->post('/invitation/{invite_token}', AcceptInvitationAction::class)->name('auth.accept-invitation');

        // Reset Passwords
        $router->get('/reset-password/{reset_token}', ValidateResetPasswordTokenAction::class)->name('auth.validate-reset-password-token');
        $router->post('/reset-password/{reset_token}', ResetPasswordAction::class)->name('auth.reset-password');
    }
);

/**
 * Logged In Routes
 */
$router->middleware(['auth:api'])->group(
    function (Router $router): void {
        // Auth
        $router->get('/auth/logout', LogoutAction::class);
        $router->post('/auth/refresh', RefreshTokenAction::class);

        // Users
        $router->get('/users/me', GetMeAction::class);
        $router->put('/users/me', UpdateMeAction::class);
        $router->post('/users', CreateUserAction::class);
        $router->get('/users', GetUsersAction::class);
        $router->get('/users/{user_id}', GetUserAction::class);
        $router->put('/users/{user_id}', UpdateUserAction::class);
        $router->post('/users/{user_id}/email-change/{changeToken}', ConfirmEmailChangeAction::class);
        $router->post('/users/{user_id}/invitation', ResendInvitationAction::class);
        $router->delete('/users/{user_id}/invitation', DeleteInvitationAction::class);
        $router->delete('/users/{user_id}/email-change', CancelEmailChangeAction::class);
        $router->post('/users/{user_id}/confirm-email/{resetToken}', ConfirmEmailAddressAction::class);
        $router->post('/users/{user_id}/resend-email-confirmation', ResendEmailConfirmationAction::class);
        $router->post('/users/{user_id}/confirm-email-with-code', ConfirmEmailWithCodeAction::class);

        // Accounts
        $router->get('/accounts/{account_id?}', GetAccountAction::class);
        $router->put('/accounts/{account_id?}', UpdateAccountAction::class);
        $router->post('/accounts/{account_id}/stripe/connect', CreateStripeConnectAccountAction::class);

        // Organizers
        $router->post('/organizers', CreateOrganizerAction::class);
        // This is POST instead of PUT because you can't upload files via PUT in PHP (at least not easily)
        $router->post('/organizers/{organizer_id}', EditOrganizerAction::class);
        $router->put('/organizers/{organizer_id}/status', UpdateOrganizerStatusAction::class);
        $router->get('/organizers', GetOrganizersAction::class);
        $router->get('/organizers/{organizer_id}', GetOrganizerAction::class);
        $router->get('/organizers/{organizer_id}/events', GetOrganizerEventsAction::class);
        $router->get('/organizers/{organizer_id}/stats', GetOrganizerStatsAction::class);
        $router->get('/organizers/{organizer_id}/orders', GetOrganizerOrdersAction::class);
        $router->get('/organizers/{organizer_id}/settings', GetOrganizerSettingsAction::class);
        $router->patch('/organizers/{organizer_id}/settings', PartialUpdateOrganizerSettingsAction::class);

        // Taxes and Fees
        $router->post('/accounts/{account_id}/taxes-and-fees', CreateTaxOrFeeAction::class);
        $router->get('/accounts/{account_id}/taxes-and-fees', GetTaxOrFeeAction::class);
        $router->put('/accounts/{account_id}/taxes-and-fees/{tax_or_fee_id}', EditTaxOrFeeAction::class);
        $router->delete('/accounts/{account_id}/taxes-and-fees/{tax_or_fee_id}', DeleteTaxOrFeeAction::class);

        // Events
        $router->post('/events', CreateEventAction::class);
        $router->get('/events', GetEventsAction::class);
        $router->get('/events/{event_id}', GetEventAction::class);
        $router->put('/events/{event_id}', UpdateEventAction::class);
        $router->put('/events/{event_id}/status', UpdateEventStatusAction::class);
        $router->post('/events/{event_id}/duplicate', DuplicateEventAction::class);

        // Product Categories
        $router->post('/events/{event_id}/product-categories', CreateProductCategoryAction::class);
        $router->get('/events/{event_id}/product-categories', GetProductCategoriesAction::class);
        $router->get('/events/{event_id}/product-categories/{category_id}', GetProductCategoryAction::class);
        $router->put('/events/{event_id}/product-categories/{category_id}', EditProductCategoryAction::class);
        $router->delete('/events/{event_id}/product-categories/{category_id}', DeleteProductCategoryAction::class);

        // Products
        $router->post('/events/{event_id}/products', CreateProductAction::class);
        $router->post('/events/{event_id}/products/sort', SortProductsAction::class);
        $router->put('/events/{event_id}/products/{ticket_id}', EditProductAction::class);
        $router->get('/events/{event_id}/products/{ticket_id}', GetProductAction::class);
        $router->delete('/events/{event_id}/products/{ticket_id}', DeleteProductAction::class);
        $router->get('/events/{event_id}/products', GetProductsAction::class);

        // Stats
        $router->get('/events/{event_id}/stats', GetEventStatsAction::class);

        // Attendees
        $router->post('/events/{event_id}/attendees', CreateAttendeeAction::class);
        $router->get('/events/{event_id}/attendees', GetAttendeesAction::class);
        $router->get('/events/{event_id}/attendees/{attendee_id}', GetAttendeeAction::class);
        $router->put('/events/{event_id}/attendees/{attendee_id}', EditAttendeeAction::class);
        $router->patch('/events/{event_id}/attendees/{attendee_id}', PartialEditAttendeeAction::class);
        $router->post('/events/{event_id}/attendees/export', ExportAttendeesAction::class);
        $router->post('/events/{event_id}/attendees/{attendee_public_id}/resend-ticket', ResendAttendeeTicketAction::class);
        $router->post('/events/{event_id}/attendees/{attendee_public_id}/check_in', CheckInAttendeeAction::class);

        // Orders
        $router->get('/events/{event_id}/orders', GetOrdersAction::class);
        $router->get('/events/{event_id}/orders/{order_id}', GetOrderAction::class);
        $router->put('/events/{event_id}/orders/{order_id}', EditOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/message', MessageOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/refund', RefundOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/resend_confirmation', ResendOrderConfirmationAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/cancel', CancelOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/mark-as-paid', MarkOrderAsPaidAction::class);
        $router->post('/events/{event_id}/orders/export', ExportOrdersAction::class);
        $router->get('/events/{event_id}/orders/{order_id}/invoice', DownloadOrderInvoiceAction::class);

        // Questions
        $router->post('/events/{event_id}/questions', CreateQuestionAction::class);
        $router->put('/events/{event_id}/questions/{question_id}', EditQuestionAction::class);
        $router->get('/events/{event_id}/questions/{question_id}', GetQuestionAction::class);
        $router->delete('/events/{event_id}/questions/{question_id}', DeleteQuestionAction::class);
        $router->get('/events/{event_id}/questions', GetQuestionsAction::class);
        $router->post('/events/{event_id}/questions/export', ExportOrdersAction::class);
        $router->post('/events/{event_id}/questions/sort', SortQuestionsAction::class);
        $router->put('/events/{event_id}/questions/{question_id}/answers/{answer_id}', EditQuestionAnswerAction::class);
        $router->match(['get', 'post'], '/events/{event_id}/questions/answers/export', ExportQuestionAnswersAction::class);

        // Images
        $router->post('/events/{event_id}/images', CreateEventImageAction::class);
        $router->get('/events/{event_id}/images', GetEventImagesAction::class);
        $router->delete('/events/{event_id}/images/{image_id}', DeleteEventImageAction::class);

        // Promo Codes
        $router->post('/events/{event_id}/promo-codes', CreatePromoCodeAction::class);
        $router->put('/events/{event_id}/promo-codes/{promo_code_id}', UpdatePromoCodeAction::class);
        $router->get('/events/{event_id}/promo-codes', GetPromoCodesAction::class);
        $router->get('/events/{event_id}/promo-codes/{promo_code_id}', GetPromoCodeAction::class);
        $router->delete('/events/{event_id}/promo-codes/{promo_code_id}', DeletePromoCodeAction::class);

        // Affiliates
        $router->post('/events/{event_id}/affiliates', CreateAffiliateAction::class);
        $router->put('/events/{event_id}/affiliates/{affiliate_id}', UpdateAffiliateAction::class);
        $router->get('/events/{event_id}/affiliates', GetAffiliatesAction::class);
        $router->get('/events/{event_id}/affiliates/{affiliate_id}', GetAffiliateAction::class);
        $router->delete('/events/{event_id}/affiliates/{affiliate_id}', DeleteAffiliateAction::class);
        $router->post('/events/{event_id}/affiliates/export', ExportAffiliatesAction::class);

        // Messages
        $router->post('/events/{event_id}/messages', SendMessageAction::class);
        $router->get('/events/{event_id}/messages', GetMessagesAction::class);

        // Event Settings
        $router->get('/events/{event_id}/settings', GetEventSettingsAction::class);
        $router->put('/events/{event_id}/settings', EditEventSettingsAction::class);
        $router->patch('/events/{event_id}/settings', PartialEditEventSettingsAction::class);

        // Capacity Assignments
        $router->post('/events/{event_id}/capacity-assignments', CreateCapacityAssignmentAction::class);
        $router->get('/events/{event_id}/capacity-assignments', GetCapacityAssignmentsAction::class);
        $router->get('/events/{event_id}/capacity-assignments/{capacity_assignment_id}', GetCapacityAssignmentAction::class);
        $router->put('/events/{event_id}/capacity-assignments/{capacity_assignment_id}', UpdateCapacityAssignmentAction::class);
        $router->delete('/events/{event_id}/capacity-assignments/{capacity_assignment_id}', DeleteCapacityAssignmentAction::class);

        // Check-In Lists
        $router->post('/events/{event_id}/check-in-lists', CreateCheckInListAction::class);
        $router->get('/events/{event_id}/check-in-lists', GetCheckInListsAction::class);
        $router->get('/events/{event_id}/check-in-lists/{check_in_list_id}', GetCheckInListAction::class);
        $router->put('/events/{event_id}/check-in-lists/{check_in_list_id}', UpdateCheckInListAction::class);
        $router->delete('/events/{event_id}/check-in-lists/{check_in_list_id}', DeleteCheckInListAction::class);

        // Webhooks
        $router->post('/events/{event_id}/webhooks', CreateWebhookAction::class);
        $router->get('/events/{event_id}/webhooks', GetWebhooksAction::class);
        $router->put('/events/{event_id}/webhooks/{webhook_id}', EditWebhookAction::class);
        $router->get('/events/{event_id}/webhooks/{webhook_id}', GetWebhookAction::class);
        $router->delete('/events/{event_id}/webhooks/{webhook_id}', DeleteWebhookAction::class);
        $router->get('/events/{event_id}/webhooks/{webhook_id}/logs', GetWebhookLogsAction::class);

        // Reports
        $router->get('/events/{event_id}/reports/{report_type}', GetReportAction::class);

        // Images
        $router->post('/images', CreateImageAction::class);
        $router->delete('/images/{image_id}', DeleteImageAction::class);
    }
);

/**
 * Public routes
 */
$router->prefix('/public')->group(
    function (Router $router): void {
        // Events
        $router->get('/events/{event_id}', GetEventPublicAction::class);

        // Organizers
        $router->get('/organizers/{organizer_id}', GetPublicOrganizerAction::class);
        $router->get('/organizers/{organizer_id}/events', GetOrganizerEventsPublicAction::class);
        $router->post('/organizers/{organizer_id}/contact', SendOrganizerContactMessagePublicAction::class);

        // Products
        $router->get('/events/{event_id}/products', GetEventPublicAction::class);

        // Orders
        $router->post('/events/{event_id}/order', CreateOrderActionPublic::class);
        $router->put('/events/{event_id}/order/{order_short_id}', CompleteOrderActionPublic::class);
        $router->get('/events/{event_id}/order/{order_short_id}', GetOrderActionPublic::class);
        $router->post('/events/{event_id}/order/{order_short_id}/await-offline-payment', TransitionOrderToOfflinePaymentPublicAction::class);
        $router->get('/events/{event_id}/order/{order_short_id}/invoice', DownloadOrderInvoicePublicAction::class);

        // Attendees
        $router->get('/events/{event_id}/attendees/{attendee_short_id}', GetAttendeeActionPublic::class);

        // Promo codes
        $router->get('/events/{event_id}/promo-codes/{promo_code}', GetPromoCodePublic::class);

        // Stripe payment gateway
        $router->post('/events/{event_id}/order/{order_short_id}/stripe/payment_intent', CreatePaymentIntentActionPublic::class);
        $router->get('/events/{event_id}/order/{order_short_id}/stripe/payment_intent', GetPaymentIntentActionPublic::class);

        // Questions
        $router->get('/events/{event_id}/questions', GetQuestionsPublicAction::class);

        // Webhooks
        $router->post('/webhooks/stripe', StripeIncomingWebhookAction::class);

        // Check-In
        $router->get('/check-in-lists/{check_in_list_short_id}', GetCheckInListPublicAction::class);
        $router->get('/check-in-lists/{check_in_list_short_id}/attendees', GetCheckInListAttendeesPublicAction::class);
        $router->get('/check-in-lists/{check_in_list_short_id}/attendees/{attendee_public_id}', GetCheckInListAttendeePublicAction::class);
        $router->post('/check-in-lists/{check_in_list_short_id}/check-ins', CreateAttendeeCheckInPublicAction::class);
        $router->delete('/check-in-lists/{check_in_list_short_id}/check-ins/{check_in_short_id}', DeleteAttendeeCheckInPublicAction::class);

        // Color themes
        $router->get('/color-themes', GetColorThemesAction::class);
    }
);

include_once __DIR__ . '/mail.php';
