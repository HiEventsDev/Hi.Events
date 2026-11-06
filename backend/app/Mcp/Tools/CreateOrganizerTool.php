<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Organizers\CreateOrganizerAction;
use HiEvents\Mcp\Tools\Support\McpPresenter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create_organizer')]
#[Title('Create organizer')]
#[Description('Creates an organizer in the connected account. Its timezone and currency become the defaults for its events.')]
#[IsDestructive]
class CreateOrganizerTool extends WriteApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('Organizer name, up to 100 characters.')->required(),
            'email' => $schema->string()->description('Contact email shown to attendees.')->required(),
            'timezone' => $schema->string()->description('IANA timezone, e.g. Europe/Dublin.')->required(),
            'currency' => $schema->string()->description('ISO 4217 currency code, e.g. EUR.')->required(),
            'description' => $schema->string()->description('Short description, up to 1200 characters.'),
            'website' => $schema->string()->description('Website URL.'),
            'phone' => $schema->string()->description('Contact phone number.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        if ($limited = $this->writeLimitResponse($request)) {
            return $limited;
        }

        $response = $this->api->send('POST', CreateOrganizerAction::class, [], $request->only([
            'name', 'email', 'timezone', 'currency', 'description', 'website', 'phone',
        ]));

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        return Response::structured(['organizer' => McpPresenter::organizer($response->data())]);
    }
}
