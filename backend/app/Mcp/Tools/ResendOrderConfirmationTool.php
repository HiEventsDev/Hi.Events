<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\DomainObjects\Status\OrderStatus;
use HiEvents\Http\Actions\Orders\GetOrderAction;
use HiEvents\Http\Actions\Orders\ResendOrderConfirmationAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('resend_order_confirmation')]
#[Title('Resend order confirmation')]
#[Description('Emails the buyer of a completed order their order confirmation again, to the email address already on the order. Each order can be resent at most once every 10 minutes, and an email address gets at most 5 emails from these tools every 10 minutes.')]
#[IsDestructive]
class ResendOrderConfirmationTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'event_id' => $schema->integer()->description('The event id.')->required(),
            'order_id' => $schema->integer()->description('The order id.')->required(),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'event_id' => 'required|integer|min:1',
            'order_id' => 'required|integer|min:1',
        ]);

        $routeParameters = ['event_id' => (int) $validated['event_id'], 'order_id' => (int) $validated['order_id']];
        $recipientKey = 'order:'.implode(':', $routeParameters);

        if ($cooling = $this->resendCooldownResponse($recipientKey)) {
            return $cooling;
        }

        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $order = $this->api->get(GetOrderAction::class, $routeParameters);

        if (! $order->isSuccessful()) {
            return $this->errorResponse($order);
        }

        if (($order->data()['status'] ?? null) !== OrderStatus::COMPLETED->name) {
            return Response::error(__('Only completed orders have a confirmation to resend.'));
        }

        $email = (string) ($order->data()['email'] ?? '');

        if ($overBudget = $this->emailBudgetResponse($email)) {
            return $overBudget;
        }

        $response = $this->api->send('POST', ResendOrderConfirmationAction::class, $routeParameters);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $this->startResendCooldown($recipientKey);
        $this->spendEmailBudget($email);

        return Response::structured(['order_id' => $routeParameters['order_id'], 'confirmation_sent' => true]);
    }
}
