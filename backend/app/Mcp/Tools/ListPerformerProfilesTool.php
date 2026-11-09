<?php

namespace HiEvents\Mcp\Tools;

use HiEvents\Http\Actions\Lineup\GetProfilesAction;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list_performer_profiles')]
#[Title('List performer profiles')]
#[Description('Lists an organizer\'s saved performer and speaker profiles, which can be added to any of its events\' lineups.')]
#[IsReadOnly]
class ListPerformerProfilesTool extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'organizer_id' => $schema->integer()->description('The organizer id.')->required(),
            'search' => $schema->string()->description('Text to match in the profile name.'),
        ];
    }

    public function handle(Request $request): Response|ResponseFactory
    {
        $validated = $request->validate([
            'organizer_id' => 'required|integer|min:1',
            'search' => 'nullable|string|max:255',
        ]);

        $response = $this->api->get(GetProfilesAction::class, ['organizer_id' => $validated['organizer_id']]);

        if (! $response->isSuccessful()) {
            return $this->errorResponse($response);
        }

        $search = mb_strtolower((string) ($validated['search'] ?? ''));

        return Response::structured([
            'profiles' => collect($response->data())
                ->filter(static fn (array $profile): bool => $search === '' || str_contains(mb_strtolower($profile['name']), $search))
                ->map(static fn (array $profile): array => [
                    'id' => $profile['id'],
                    'name' => $profile['name'],
                    'tagline' => $profile['tagline'] ?? null,
                    'has_image' => ($profile['images'] ?? []) !== [],
                ])
                ->values()
                ->all(),
        ]);
    }
}
