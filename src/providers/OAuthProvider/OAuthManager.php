<?php

declare(strict_types=1);

namespace BetterAuth\Providers\OAuthProvider;

use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Interfaces\OAuthProviderInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\SessionService;
use BetterAuth\Core\Utils\Crypto;

/**
 * OAuth manager for handling OAuth authentication flow.
 */
class OAuthManager
{
    /** @var array<string, OAuthProviderInterface> */
    private array $providers = [];

    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly SessionService $sessionService,
    ) {
    }

    /**
     * Register an OAuth provider.
     *
     * @param OAuthProviderInterface $provider
     */
    public function addProvider(OAuthProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    /**
     * Get the authorization URL for a provider.
     *
     * @param string $providerName
     * @param array<string, mixed> $options
     * @return array{url: string, state: string}
     * @throws \Exception
     */
    public function getAuthorizationUrl(string $providerName, array $options = []): array
    {
        $provider = $this->getProvider($providerName);
        $state = Crypto::randomToken(16);

        $url = $provider->getAuthorizationUrl($state, $options);

        return [
            'url' => $url,
            'state' => $state,
        ];
    }

    /**
     * Handle OAuth callback and create/login user.
     *
     * @param string $providerName
     * @param string $code
     * @param string $redirectUri
     * @param string $ipAddress
     * @param string $userAgent
     * @return array{user: User, session: Session, isNewUser: bool}
     * @throws \Exception
     */
    public function handleCallback(
        string $providerName,
        string $code,
        string $redirectUri,
        string $ipAddress,
        string $userAgent
    ): array {
        $provider = $this->getProvider($providerName);

        // Exchange code for access token
        $accessToken = $provider->getAccessToken($code, $redirectUri);

        // Get user info from provider
        $providerUser = $provider->getUserInfo($accessToken);

        // Find existing user by provider
        $user = $this->userRepository->findByProvider($providerName, $providerUser->providerId);

        $isNewUser = false;

        if ($user === null) {
            // Check if user exists with same email
            $user = $this->userRepository->findByEmail($providerUser->email);

            if ($user === null) {
                // Create new user
                $userData = [
                    'email' => $providerUser->email,
                    'password_hash' => null,
                    'name' => $providerUser->name,
                    'avatar' => $providerUser->avatar,
                    'email_verified' => $providerUser->emailVerified,
                    'email_verified_at' => $providerUser->emailVerified ? date('Y-m-d H:i:s') : null,
                    'metadata' => [
                        'oauth_providers' => [
                            $providerName => [
                                'provider_id' => $providerUser->providerId,
                                'connected_at' => date('Y-m-d H:i:s'),
                            ],
                        ],
                    ],
                ];

                // Only set 'id' if repository generates one (UUID/ULID strategy)
                // For auto-increment (INT strategy), repository will let DB handle it
                $generatedId = $this->userRepository->generateId();
                if ($generatedId !== null) {
                    $userData['id'] = $generatedId;
                }

                $user = $this->userRepository->create($userData);

                $isNewUser = true;
            } else {
                // Link provider to existing user
                $metadata = $user->metadata ?? [];
                $metadata['oauth_providers'][$providerName] = [
                    'provider_id' => $providerUser->providerId,
                    'connected_at' => date('Y-m-d H:i:s'),
                ];

                $user = $this->userRepository->update($user->id, ['metadata' => $metadata]);
            }
        }

        // Create session
        $session = $this->sessionService->create($user, $ipAddress, $userAgent);

        return [
            'user' => $user,
            'session' => $session,
            'isNewUser' => $isNewUser,
        ];
    }

    /**
     * Get a provider by name.
     *
     * @param string $name
     * @return OAuthProviderInterface
     * @throws \InvalidArgumentException
     */
    private function getProvider(string $name): OAuthProviderInterface
    {
        if (!isset($this->providers[$name])) {
            throw new \InvalidArgumentException("OAuth provider '$name' not found");
        }

        return $this->providers[$name];
    }
}
