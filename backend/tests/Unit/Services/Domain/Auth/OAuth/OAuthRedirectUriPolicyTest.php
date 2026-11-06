<?php

namespace Tests\Unit\Services\Domain\Auth\OAuth;

use HiEvents\DomainObjects\Enums\ApiKeyAccess;
use HiEvents\DomainObjects\Enums\OAuthScope;
use HiEvents\Services\Domain\Auth\OAuth\OAuthRedirectUriPolicy;
use Tests\TestCase;

class OAuthRedirectUriPolicyTest extends TestCase
{
    private OAuthRedirectUriPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new OAuthRedirectUriPolicy;
    }

    public function test_allowed_redirect_uris(): void
    {
        foreach ([
            'https://claude.ai/api/mcp/auth_callback',
            'http://localhost:3000/callback',
            'http://127.0.0.1/callback',
            'http://[::1]:8080/cb',
            'cursor://anysphere.cursor-retrieval/oauth/callback',
            'com.example.app:/oauth2redirect',
        ] as $uri) {
            $this->assertTrue($this->policy->isAllowed($uri), $uri);
        }
    }

    public function test_refused_redirect_uris(): void
    {
        foreach ([
            'http://example.com/callback',
            'https://user:pass@example.com/cb',
            'https://example.com/cb#frag',
            'javascript:alert(1)',
            'data:text/html,hi',
            'file:///etc/passwd',
            'not a uri',
            '/relative/path',
        ] as $uri) {
            $this->assertFalse($this->policy->isAllowed($uri), $uri);
        }
    }

    public function test_loopback_redirects_match_on_any_port_but_others_match_exactly(): void
    {
        $this->assertTrue($this->policy->matches(['http://localhost/callback'], 'http://localhost:61234/callback'));
        $this->assertTrue($this->policy->matches(['http://127.0.0.1:1/callback'], 'http://127.0.0.1:9/callback'));
        $this->assertFalse($this->policy->matches(['http://localhost/callback'], 'http://localhost:61234/other'));
        $this->assertFalse($this->policy->matches(['http://localhost/callback'], 'http://127.0.0.1/callback'));
        $this->assertFalse($this->policy->matches(['https://claude.ai/cb'], 'https://claude.ai/cb/'));
        $this->assertFalse($this->policy->matches(['https://claude.ai/cb'], 'https://claude.ai:444/cb'));
    }

    public function test_only_known_hosts_are_verified(): void
    {
        $this->assertTrue($this->policy->isVerified(['https://claude.ai/api/mcp/auth_callback']));
        $this->assertFalse($this->policy->isVerified(['https://claude.ai/cb', 'https://evil.example/cb']));
        $this->assertFalse($this->policy->isVerified(['http://localhost/callback']));
        $this->assertFalse($this->policy->isVerified([]));
    }

    public function test_descriptions_shown_on_the_consent_screen(): void
    {
        $this->assertSame('claude.ai', $this->policy->describe('https://claude.ai/api/mcp/auth_callback'));
        $this->assertSame('localhost', $this->policy->describe('http://127.0.0.1:3000/cb'));
        $this->assertSame('cursor://anysphere.cursor-retrieval', $this->policy->describe('cursor://anysphere.cursor-retrieval/oauth/callback'));
    }

    public function test_scopes_map_to_access_levels(): void
    {
        $this->assertSame(ApiKeyAccess::READ_WRITE, OAuthScope::accessFor(null));
        $this->assertSame(ApiKeyAccess::READ_WRITE, OAuthScope::accessFor('mcp:read mcp:write'));
        $this->assertSame(ApiKeyAccess::READ_ONLY, OAuthScope::accessFor('mcp:read'));
        $this->assertSame('mcp:read', OAuthScope::forAccess(ApiKeyAccess::READ_ONLY));
    }
}
