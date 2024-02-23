<?php

use Illuminate\Routing\Router;
use TicketKitten\Http\Actions\Accounts\CreateAccountAction;
use TicketKitten\Http\Actions\Accounts\GetAccountAction;
use TicketKitten\Http\Actions\Accounts\Stripe\CreateStripeConnectAccountAction;
use TicketKitten\Http\Actions\Accounts\UpdateAccountAction;
use TicketKitten\Http\Actions\Attendees\CheckInAttendeeAction;
use TicketKitten\Http\Actions\Attendees\CreateAttendeeAction;
use TicketKitten\Http\Actions\Attendees\EditAttendeeAction;
use TicketKitten\Http\Actions\Attendees\ExportAttendeesAction;
use TicketKitten\Http\Actions\Attendees\GetAttendeeAction;
use TicketKitten\Http\Actions\Attendees\GetAttendeeActionPublic;
use TicketKitten\Http\Actions\Attendees\GetAttendeesAction;
use TicketKitten\Http\Actions\Attendees\PartialEditAttendeeAction;
use TicketKitten\Http\Actions\Auth\AcceptInvitationAction;
use TicketKitten\Http\Actions\Auth\ForgotPasswordAction;
use TicketKitten\Http\Actions\Auth\GetUserInvitationAction;
use TicketKitten\Http\Actions\Auth\LoginAction;
use TicketKitten\Http\Actions\Auth\LogoutAction;
use TicketKitten\Http\Actions\Auth\RefreshTokenAction;
use TicketKitten\Http\Actions\Auth\ResetPasswordAction;
use TicketKitten\Http\Actions\Auth\ValidateResetPasswordTokenAction;
use TicketKitten\Http\Actions\Common\Webhooks\StripeIncomingWebhookAction;
use TicketKitten\Http\Actions\Events\CreateEventAction;
use TicketKitten\Http\Actions\Events\GetEventAction;
use TicketKitten\Http\Actions\Events\GetEventPublicAction;
use TicketKitten\Http\Actions\Events\GetEventsAction;
use TicketKitten\Http\Actions\Events\Images\CreateEventImageAction;
use TicketKitten\Http\Actions\Events\Images\DeleteEventImageAction;
use TicketKitten\Http\Actions\Events\Images\GetEventImagesAction;
use TicketKitten\Http\Actions\Events\Stats\GetEventCheckInStatsAction;
use TicketKitten\Http\Actions\Events\Stats\GetEventStatsAction;
use TicketKitten\Http\Actions\Events\UpdateEventAction;
use TicketKitten\Http\Actions\Events\UpdateEventStatusAction;
use TicketKitten\Http\Actions\EventSettings\EditEventSettingsAction;
use TicketKitten\Http\Actions\EventSettings\GetEventSettingsAction;
use TicketKitten\Http\Actions\EventSettings\PartialEditEventSettingsAction;
use TicketKitten\Http\Actions\Messages\GetMessagesAction;
use TicketKitten\Http\Actions\Messages\SendMessageAction;
use TicketKitten\Http\Actions\Orders\CancelOrderAction;
use TicketKitten\Http\Actions\Orders\CompleteOrderActionPublic;
use TicketKitten\Http\Actions\Orders\CreateOrderActionPublic;
use TicketKitten\Http\Actions\Orders\ExportOrdersAction;
use TicketKitten\Http\Actions\Orders\GetOrderAction;
use TicketKitten\Http\Actions\Orders\GetOrderActionPublic;
use TicketKitten\Http\Actions\Orders\GetOrdersAction;
use TicketKitten\Http\Actions\Orders\MessageOrderAction;
use TicketKitten\Http\Actions\Orders\Payment\RefundOrderAction;
use TicketKitten\Http\Actions\Orders\Payment\Stripe\CreatePaymentIntentActionPublic;
use TicketKitten\Http\Actions\Orders\Payment\Stripe\GetPaymentIntentActionPublic;
use TicketKitten\Http\Actions\Orders\ResendOrderConfirmationAction;
use TicketKitten\Http\Actions\Organizers\CreateOrganizerAction;
use TicketKitten\Http\Actions\Organizers\EditOrganizerAction;
use TicketKitten\Http\Actions\Organizers\GetOrganizerAction;
use TicketKitten\Http\Actions\Organizers\GetOrganizersAction;
use TicketKitten\Http\Actions\PromoCodes\CreatePromoCodeAction;
use TicketKitten\Http\Actions\PromoCodes\DeletePromoCodeAction;
use TicketKitten\Http\Actions\PromoCodes\GetPromoCodeAction;
use TicketKitten\Http\Actions\PromoCodes\GetPromoCodePublic;
use TicketKitten\Http\Actions\PromoCodes\GetPromoCodesAction;
use TicketKitten\Http\Actions\PromoCodes\UpdatePromoCodeAction;
use TicketKitten\Http\Actions\Questions\CreateQuestionAction;
use TicketKitten\Http\Actions\Questions\DeleteQuestionAction;
use TicketKitten\Http\Actions\Questions\EditQuestionAction;
use TicketKitten\Http\Actions\Questions\GetQuestionAction;
use TicketKitten\Http\Actions\Questions\GetQuestionsAction;
use TicketKitten\Http\Actions\Questions\GetQuestionsPublicAction;
use TicketKitten\Http\Actions\Questions\SortQuestionsAction;
use TicketKitten\Http\Actions\TaxesAndFees\CreateTaxOrFeeAction;
use TicketKitten\Http\Actions\TaxesAndFees\DeleteTaxOrFeeAction;
use TicketKitten\Http\Actions\TaxesAndFees\EditTaxOrFeeAction;
use TicketKitten\Http\Actions\TaxesAndFees\GetTaxOrFeeAction;
use TicketKitten\Http\Actions\Tickets\CreateTicketAction;
use TicketKitten\Http\Actions\Tickets\DeleteTicketAction;
use TicketKitten\Http\Actions\Tickets\EditTicketAction;
use TicketKitten\Http\Actions\Tickets\GetTicketAction;
use TicketKitten\Http\Actions\Tickets\GetTicketsAction;
use TicketKitten\Http\Actions\Tickets\SortTicketsAction;
use TicketKitten\Http\Actions\Users\CancelEmailChangeAction;
use TicketKitten\Http\Actions\Users\ConfirmEmailAddressAction;
use TicketKitten\Http\Actions\Users\ConfirmEmailChangeAction;
use TicketKitten\Http\Actions\Users\CreateUserAction;
use TicketKitten\Http\Actions\Users\DeactivateUsersAction;
use TicketKitten\Http\Actions\Users\DeleteInvitationAction;
use TicketKitten\Http\Actions\Users\GetMeAction;
use TicketKitten\Http\Actions\Users\GetUserAction;
use TicketKitten\Http\Actions\Users\GetUsersAction;
use TicketKitten\Http\Actions\Users\ResendInvitationAction;
use TicketKitten\Http\Actions\Users\UpdateMeAction;
use TicketKitten\Http\Actions\Users\UpdateUserAction;

/** @var Router|Router $router */
$router = app()->get('router');

$router->prefix('/auth')->group(
    function (Router $router): void {
        $router->post('/login', LoginAction::class)->name('login');
        $router->post('/logout', LogoutAction::class);
        $router->post('/register', CreateAccountAction::class);
        $router->post('/forgot-password', ForgotPasswordAction::class);

        $router->get('/invitation/{invite_token}', GetUserInvitationAction::class);
        $router->post('/invitation/{invite_token}', AcceptInvitationAction::class);

        $router->get('/reset-password/{reset_token}', ValidateResetPasswordTokenAction::class);
        $router->post('/reset-password/{reset_token}', ResetPasswordAction::class);
    }
);

/**
 * Logged In Routes
 */
$router->middleware(['auth:api'])->group(
    function (Router $router): void {
        $router->get('/auth/logout', LogoutAction::class);
        $router->get('/auth/refresh', RefreshTokenAction::class);

        $router->get('/users/me', GetMeAction::class);
        $router->put('/users/me', UpdateMeAction::class);
        $router->post('/users', CreateUserAction::class);
        $router->get('/users', GetUsersAction::class);
        $router->get('/users/{user_id}', GetUserAction::class);
        $router->put('/users/{user_id}', UpdateUserAction::class);
        $router->delete('/users/{user_id}', DeactivateUsersAction::class);
        $router->post('/users/{user_id}/email-change/{token}', ConfirmEmailChangeAction::class);
        $router->post('/users/{user_id}/invitation', ResendInvitationAction::class);
        $router->delete('/users/{user_id}/invitation', DeleteInvitationAction::class);
        $router->delete('/users/{user_id}/email-change', CancelEmailChangeAction::class);
        $router->post('/users/{user_id}/confirm-email/{token}', ConfirmEmailAddressAction::class);

        $router->get('/accounts/{account_id?}', GetAccountAction::class);
        $router->put('/accounts/{account_id?}', UpdateAccountAction::class);
        $router->post('/accounts/{account_id}/stripe/connect', CreateStripeConnectAccountAction::class);

        $router->post('/organizers', CreateOrganizerAction::class);
        $router->post('/organizers/{organizer_id}', EditOrganizerAction::class);
        $router->get('/organizers', GetOrganizersAction::class);
        $router->get('/organizers/{organizer_id}', GetOrganizerAction::class);

        $router->post('/accounts/{account_id}/taxes-and-fees', CreateTaxOrFeeAction::class);
        $router->get('/accounts/{account_id}/taxes-and-fees', GetTaxOrFeeAction::class);
        $router->put('/accounts/{account_id}/taxes-and-fees/{tax_or_fee_id}', EditTaxOrFeeAction::class);
        $router->delete('/accounts/{account_id}/taxes-and-fees/{tax_or_fee_id}', DeleteTaxOrFeeAction::class);

        $router->post('/events', CreateEventAction::class);
        $router->get('/events', GetEventsAction::class);
        $router->get('/events/{event_id}', GetEventAction::class);
        $router->put('/events/{event_id}', UpdateEventAction::class);
        $router->put('/events/{event_id}/status', UpdateEventStatusAction::class);

        $router->post('/events/{event_id}/tickets', CreateTicketAction::class);
        $router->post('/events/{event_id}/tickets/sort', SortTicketsAction::class);
        $router->put('/events/{event_id}/tickets/{ticket_id}', EditTicketAction::class);
        $router->get('/events/{event_id}/tickets/{ticket_id}', GetTicketAction::class);
        $router->delete('/events/{event_id}/tickets/{ticket_id}', DeleteTicketAction::class);
        $router->get('/events/{event_id}/tickets', GetTicketsAction::class);
        $router->get('/events/{event_id}/check_in_stats', GetEventCheckInStatsAction::class);
        $router->get('/events/{event_id}/stats', GetEventStatsAction::class);

        $router->post('/events/{event_id}/attendees', CreateAttendeeAction::class);
        $router->get('/events/{event_id}/attendees', GetAttendeesAction::class);
        $router->get('/events/{event_id}/attendees/{attendee_id}', GetAttendeeAction::class);
        $router->put('/events/{event_id}/attendees/{attendee_id}', EditAttendeeAction::class);
        $router->patch('/events/{event_id}/attendees/{attendee_id}', PartialEditAttendeeAction::class);
        $router->post('/events/{event_id}/attendees/export', ExportAttendeesAction::class);
        $router->post('/events/{event_id}/attendees/{attendee_public_id}/check_in', CheckInAttendeeAction::class);

        $router->get('/events/{event_id}/orders', GetOrdersAction::class);
        $router->get('/events/{event_id}/orders/{order_id}', GetOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/message', MessageOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/refund', RefundOrderAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/resend_confirmation', ResendOrderConfirmationAction::class);
        $router->post('/events/{event_id}/orders/{order_id}/cancel', CancelOrderAction::class);
        $router->post('/events/{event_id}/orders/export', ExportOrdersAction::class);

        $router->post('/events/{event_id}/questions', CreateQuestionAction::class);
        $router->put('/events/{event_id}/questions/{question_id}', EditQuestionAction::class);
        $router->get('/events/{event_id}/questions/{question_id}', GetQuestionAction::class);
        $router->delete('/events/{event_id}/questions/{question_id}', DeleteQuestionAction::class);
        $router->get('/events/{event_id}/questions', GetQuestionsAction::class);
        $router->post('/events/{event_id}/questions/export', ExportOrdersAction::class);
        $router->post('/events/{event_id}/questions/sort', SortQuestionsAction::class);

        $router->post('/events/{event_id}/images', CreateEventImageAction::class);
        $router->get('/events/{event_id}/images', GetEventImagesAction::class);
        $router->delete('/events/{event_id}/images/{image_id}', DeleteEventImageAction::class);

        $router->post('/events/{event_id}/promo-codes', CreatePromoCodeAction::class);
        $router->put('/events/{event_id}/promo-codes/{promo_code_id}', UpdatePromoCodeAction::class);
        $router->get('/events/{event_id}/promo-codes', GetPromoCodesAction::class);
        $router->get('/events/{event_id}/promo-codes/{promo_code_id}', GetPromoCodeAction::class);
        $router->delete('/events/{event_id}/promo-codes/{promo_code_id}', DeletePromoCodeAction::class);

        $router->post('/events/{event_id}/messages', SendMessageAction::class);
        $router->get('/events/{event_id}/messages', GetMessagesAction::class);

        $router->get('/events/{event_id}/settings', GetEventSettingsAction::class);
        $router->put('/events/{event_id}/settings', EditEventSettingsAction::class);
        $router->patch('/events/{event_id}/settings', PartialEditEventSettingsAction::class);
    }
);

/**
 * Public routes
 */
$router->prefix('/public')->group(
    function (Router $router): void {
        $router->get('/events/{event_id}', GetEventPublicAction::class);
        $router->get('/events/{event_id}/tickets', GetEventPublicAction::class);

        $router->post('/events/{event_id}/order', CreateOrderActionPublic::class);
        $router->put('/events/{event_id}/order/{order_short_id}', CompleteOrderActionPublic::class);
        $router->get('/events/{event_id}/order/{order_short_id}', GetOrderActionPublic::class);

        $router->get('/events/{event_id}/attendees/{attendee_short_id}', GetAttendeeActionPublic::class);

        $router->get('/events/{event_id}/promo-codes/{promo_code}', GetPromoCodePublic::class);

        // Stripe payment gateway
        $router->post('/events/{event_id}/order/{order_short_id}/stripe/payment_intent', CreatePaymentIntentActionPublic::class);
        $router->get('/events/{event_id}/order/{order_short_id}/stripe/payment_intent', GetPaymentIntentActionPublic::class);

        $router->get('/events/{event_id}/questions', GetQuestionsPublicAction::class);

        // Webhooks
        $router->post('/webhooks/stripe', StripeIncomingWebhookAction::class);
    }
);
