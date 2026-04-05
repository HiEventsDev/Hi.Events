<?php

namespace HiEvents\Http\Actions\Federation;

use HiEvents\Http\Actions\BaseAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WebFinger endpoint for Fediverse actor discovery.
 * Responds to: /.well-known/webfinger?resource=acct:organizer-slug@domain
 */
class WebFingerAction extends BaseAction
{
    public function __invoke(Request $request): JsonResponse
    {
        $resource = $request->query('resource', '');

        if (!str_starts_with($resource, 'acct:')) {
            return response()->json(['error' => 'Invalid resource'], 400);
        }

        $parts = explode('@', substr($resource, 5));
        if (count($parts) !== 2) {
            return response()->json(['error' => 'Invalid resource format'], 400);
        }

        $slug = $parts[0];
        $domain = $parts[1];
        $expectedDomain = parse_url(config('app.url'), PHP_URL_HOST);

        if ($domain !== $expectedDomain) {
            return response()->json(['error' => 'Unknown domain'], 404);
        }

        $baseUrl = rtrim(config('app.url'), '/');

        return response()->json([
            'subject' => $resource,
            'links' => [
                [
                    'rel' => 'self',
                    'type' => 'application/activity+json',
                    'href' => sprintf('%s/federation/actors/organizers/slug/%s', $baseUrl, $slug),
                ],
                [
                    'rel' => 'http://webfinger.net/rel/profile-page',
                    'type' => 'text/html',
                    'href' => sprintf('%s/o/%s', $baseUrl, $slug),
                ],
            ],
        ], 200, [
            'Content-Type' => 'application/jrd+json',
        ]);
    }
}
