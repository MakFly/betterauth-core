<?php

declare(strict_types=1);

namespace BetterAuth\Storage\Pdo;

use BetterAuth\Core\Entities\AuthorizationCode;
use BetterAuth\Core\Interfaces\AuthorizationCodeRepositoryInterface;
use PDO;

/**
 * PDO implementation for authorization codes.
 */
class PdoAuthorizationCodeRepository implements AuthorizationCodeRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo,
        private readonly string $tableName = 'authorization_codes',
    ) {
    }

    public function findByCode(string $code): ?AuthorizationCode
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->tableName} WHERE code = :code");
        $stmt->execute(['code' => $code]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ? AuthorizationCode::fromArray($data) : null;
    }

    public function create(array $data): AuthorizationCode
    {
        $data['created_at'] ??= date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare("
            INSERT INTO {$this->tableName}
            (code, client_id, user_id, redirect_uri, scopes, expires_at, code_challenge, code_challenge_method, created_at, used)
            VALUES (:code, :client_id, :user_id, :redirect_uri, :scopes, :expires_at, :code_challenge, :code_challenge_method, :created_at, :used)
        ");

        $stmt->execute([
            'code' => $data['code'],
            'client_id' => $data['clientId'],
            'user_id' => $data['userId'],
            'redirect_uri' => $data['redirectUri'],
            'scopes' => is_array($data['scopes']) ? json_encode($data['scopes']) : $data['scopes'],
            'expires_at' => $data['expiresAt'],
            'code_challenge' => $data['codeChallenge'] ?? null,
            'code_challenge_method' => $data['codeChallengeMethod'] ?? null,
            'created_at' => $data['created_at'],
            'used' => $data['used'] ?? false,
        ]);

        $authCode = $this->findByCode($data['code']);
        if ($authCode === null) {
            throw new \RuntimeException('Failed to create authorization code');
        }

        return $authCode;
    }

    public function markAsUsed(string $code): void
    {
        $stmt = $this->pdo->prepare("
            UPDATE {$this->tableName}
            SET used = TRUE
            WHERE code = :code
        ");

        $stmt->execute(['code' => $code]);
    }

    public function deleteExpired(): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->tableName}
            WHERE expires_at < NOW()
        ");

        $stmt->execute();

        return $stmt->rowCount();
    }

    public function deleteByUserId(string $userId): int
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM {$this->tableName}
            WHERE user_id = :user_id
        ");

        $stmt->execute(['user_id' => $userId]);

        return $stmt->rowCount();
    }

    /**
     * Create the authorization_codes table.
     */
    public function createTable(): bool
    {
        return $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS {$this->tableName} (
                code VARCHAR(255) PRIMARY KEY,
                client_id VARCHAR(255) NOT NULL,
                user_id VARCHAR(50) NOT NULL,
                redirect_uri TEXT NOT NULL,
                scopes TEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                code_challenge VARCHAR(255) NULL,
                code_challenge_method VARCHAR(50) NULL,
                created_at DATETIME NOT NULL,
                used BOOLEAN DEFAULT FALSE,
                INDEX idx_user_id (user_id),
                INDEX idx_client_id (client_id),
                INDEX idx_expires_at (expires_at),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ") !== false;
    }
}
