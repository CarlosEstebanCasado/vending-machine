<?php

declare(strict_types=1);

namespace App\AdminPanel\User\Infrastructure\Token;

use App\AdminPanel\User\Application\Security\AccessToken;
use App\AdminPanel\User\Application\Security\AdminAccessTokenGenerator;
use App\AdminPanel\User\Application\Security\AdminAccessTokenValidator;
use App\AdminPanel\User\Application\Security\TokenClaims;
use App\AdminPanel\User\Domain\AdminUser;
use DateInterval;
use DateTimeImmutable;

use function base64_decode;
use function base64_encode;
use function hash_equals;
use function hash_hmac;
use function json_decode;
use function json_encode;
use function rtrim;
use function str_contains;
use function strlen;
use function strtr;

final class JwtAdminTokenService implements AdminAccessTokenGenerator, AdminAccessTokenValidator
{
    private const ALG = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly int $ttlInSeconds,
        private readonly ?string $issuer = null,
    ) {
    }

    public function generate(AdminUser $adminUser): AccessToken
    {
        $issuedAt = new DateTimeImmutable();
        $expiresAt = $issuedAt->add(new DateInterval(sprintf('PT%dS', $this->ttlInSeconds)));

        $header = [
            'alg' => self::ALG,
            'typ' => 'JWT',
        ];

        $payload = [
            'sub' => $adminUser->id(),
            'email' => $adminUser->email(),
            'roles' => $adminUser->roles(),
            'iat' => $issuedAt->getTimestamp(),
            'exp' => $expiresAt->getTimestamp(),
        ];

        if ($this->issuer) {
            $payload['iss'] = $this->issuer;
        }

        $token = $this->encode($header, $payload);

        return new AccessToken($token, $expiresAt);
    }

    public function validate(string $token): TokenClaims
    {
        if ('' === $token) {
            throw InvalidAdminToken::emptyToken();
        }

        if (!str_contains($token, '.')) {
            throw InvalidAdminToken::malformed();
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = explode('.', $token);

        $header = $this->decodeSegment($encodedHeader, 'header');
        $payload = $this->decodeSegment($encodedPayload, 'payload');

        if (!isset($header['alg']) || self::ALG !== $header['alg']) {
            throw InvalidAdminToken::unsupportedAlgorithm();
        }

        $expectedSignature = $this->sign($encodedHeader, $encodedPayload);

        if (!hash_equals($expectedSignature, $encodedSignature)) {
            throw InvalidAdminToken::signatureMismatch();
        }

        if (!isset($payload['sub'], $payload['email'], $payload['roles'], $payload['exp'])) {
            throw InvalidAdminToken::missingClaims();
        }

        $expiresAt = DateTimeImmutable::createFromFormat('U', (string) $payload['exp']);
        if (!$expiresAt || $expiresAt < new DateTimeImmutable()) {
            throw InvalidAdminToken::expired();
        }

        return new TokenClaims(
            userId: (string) $payload['sub'],
            email: (string) $payload['email'],
            roles: (array) $payload['roles'],
            expiresAt: $expiresAt,
        );
    }

    /**
     * @param array<string, mixed> $header
     * @param array<string, mixed> $payload
     */
    private function encode(array $header, array $payload): string
    {
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->sign($encodedHeader, $encodedPayload);

        return sprintf('%s.%s.%s', $encodedHeader, $encodedPayload, $signature);
    }

    private function sign(string $encodedHeader, string $encodedPayload): string
    {
        $data = sprintf('%s.%s', $encodedHeader, $encodedPayload);

        return $this->base64UrlEncode(hash_hmac('sha256', $data, $this->secret, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSegment(string $segment, string $type): array
    {
        $decoded = base64_decode($this->base64UrlDecode($segment), true);

        if (false === $decoded) {
            throw InvalidAdminToken::malformed();
        }

        $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw InvalidAdminToken::malformed();
        }

        return $data;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if (0 !== $remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return strtr($data, '-_', '+/');
    }
}
