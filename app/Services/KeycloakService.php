<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class KeycloakService
{
    private string $baseUrl;
    private string $realm;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('keycloak.base_url'), '/');
        $this->realm   = config('keycloak.realm');
    }

    public function issuer(): string
    {
        return "{$this->baseUrl}/realms/{$this->realm}";
    }

    /**
     * Client-credentials token for the confidential backend client, cached
     * until shortly before it expires so every admin-API call doesn't re-auth.
     */
    private function serviceAccessToken(): string
    {
        return Cache::remember('keycloak.service_token', 55, function () {
            $response = Http::asForm()->post("{$this->issuer()}/protocol/openid-connect/token", [
                'grant_type'    => 'client_credentials',
                'client_id'     => config('keycloak.backend_client_id'),
                'client_secret' => config('keycloak.backend_client_secret'),
            ])->throw();

            return $response->json('access_token');
        });
    }

    private function admin(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->serviceAccessToken())
            ->baseUrl("{$this->baseUrl}/admin/realms/{$this->realm}")
            ->acceptJson();
    }

    /**
     * Create a Keycloak user. If $password is null, the account is created
     * with no credential at all (cannot log in until a password is set via
     * setPassword()) — used for HR/cadre-provisioned and freshly self-registered-
     * but-unverified accounts.
     *
     * $name/$role/$districtId/$blockId/$status are mirrored into Keycloak as
     * plain user attributes purely so an admin can cross-check them directly
     * in the Keycloak console — Laravel's local `users` table remains the
     * source of truth; nothing reads these back out of Keycloak. We deliberately
     * avoid Keycloak's built-in firstName/lastName fields: they're subject to
     * realm User Profile "required" validation (VERIFY_PROFILE), which has
     * repeatedly locked users out with "Account is not fully set up" whenever
     * that field ended up empty.
     */
    public function createUser(
        string $email,
        string $name,
        bool $enabled,
        ?string $password = null,
        ?string $role = null,
        ?int $districtId = null,
        ?int $blockId = null,
        ?string $status = null,
    ): string {
        $payload = [
            'username'      => $email,
            'email'         => $email,
            'enabled'       => $enabled,
            'emailVerified' => true,
            'attributes'    => $this->normalizeAttributes($name, $role, $districtId, $blockId, $status),
        ];

        if ($password !== null) {
            $payload['credentials'] = [[
                'type'      => 'password',
                'value'     => $password,
                'temporary' => false,
            ]];
        }

        $response = $this->admin()->post('/users', $payload);

        if ($response->status() === 409) {
            throw new \RuntimeException("Keycloak user already exists for email {$email}.");
        }
        $response->throw();

        $location = $response->header('Location');
        $parts    = explode('/', rtrim($location, '/'));

        return end($parts);
    }

    public function setEnabled(string $keycloakId, bool $enabled): void
    {
        $this->admin()->put("/users/{$keycloakId}", ['enabled' => $enabled])->throw();
    }

    /**
     * Re-sync the mirrored attributes (name/role/district_id/block_id/status)
     * on an existing Keycloak user. A PUT that sends only a partial
     * representation (e.g. just {"attributes": {...}}) causes Keycloak to
     * treat every omitted top-level field — email, firstName, username — as
     * cleared, silently wiping them. We fetch the full current representation
     * and PUT it back verbatim with only `attributes` merged in, so every
     * other field round-trips untouched.
     */
    public function syncAttributes(string $keycloakId, ?string $name, ?string $role, ?int $districtId, ?int $blockId, ?string $status): void
    {
        $current = $this->admin()->get("/users/{$keycloakId}")->throw()->json();
        $current['attributes'] = array_merge(
            $current['attributes'] ?? [],
            $this->normalizeAttributes($name, $role, $districtId, $blockId, $status),
        );

        $this->admin()->put("/users/{$keycloakId}", $current)->throw();
    }

    private function normalizeAttributes(?string $name, ?string $role, ?int $districtId, ?int $blockId, ?string $status): array
    {
        return [
            'name'        => [(string) $name],
            'role'        => [(string) $role],
            'district_id' => [$districtId !== null ? (string) $districtId : ''],
            'block_id'    => [$blockId !== null ? (string) $blockId : ''],
            'status'      => [(string) $status],
        ];
    }

    public function setPassword(string $keycloakId, string $password): void
    {
        $this->admin()->put("/users/{$keycloakId}/reset-password", [
            'type'      => 'password',
            'value'     => $password,
            'temporary' => false,
        ])->throw();
    }

    public function hasCredential(string $keycloakId): bool
    {
        $response = $this->admin()->get("/users/{$keycloakId}/credentials")->throw();

        return count($response->json()) > 0;
    }

    public function deleteUser(string $keycloakId): void
    {
        $this->admin()->delete("/users/{$keycloakId}")->throw();
    }

    private function jwks(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget('keycloak.jwks');
        }

        return Cache::remember('keycloak.jwks', 3600, function () {
            return Http::get("{$this->issuer()}/protocol/openid-connect/certs")->throw()->json();
        });
    }

    /**
     * Verify a Keycloak-issued access token's signature and issuer, returning
     * its decoded payload (contains ->sub, ->email, ->exp, etc.).
     */
    public function verifyAccessToken(string $jwt): object
    {
        try {
            $keys    = JWK::parseKeySet($this->jwks());
            $decoded = JWT::decode($jwt, $keys);
        } catch (\UnexpectedValueException $e) {
            // Possibly a rotated signing key — refetch JWKS once and retry.
            $keys    = JWK::parseKeySet($this->jwks(fresh: true));
            $decoded = JWT::decode($jwt, $keys);
        }

        if ($decoded->iss !== $this->issuer()) {
            throw new \UnexpectedValueException('Unexpected token issuer.');
        }

        return $decoded;
    }
}
