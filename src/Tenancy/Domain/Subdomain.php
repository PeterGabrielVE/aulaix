<?php

declare(strict_types=1);

namespace AulaX\Tenancy\Domain;

/**
 * The label in front of APP_DOMAIN that identifies an institution
 * ({subdomain}.APP_DOMAIN). It becomes a hostname, so it must be a single
 * lower-case DNS label. The database enforces the same rule with the
 * institutions_subdomain_format check constraint (defence in depth).
 */
final readonly class Subdomain
{
    /**
     * Subdomains of APP_DOMAIN that belong to the platform itself, never to
     * an institution. "www" is also redirected to the central domain.
     */
    public const RESERVED = ['www', 'api', 'admin', 'app', 'mail', 'static', 'assets', 'cdn'];

    private const DNS_LABEL = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?$/';

    private function __construct(public string $value) {}

    public static function fromString(string $value): self
    {
        $subdomain = self::normalize($value);

        if (preg_match(self::DNS_LABEL, $subdomain) !== 1) {
            throw InvalidSubdomain::notADnsLabel($subdomain);
        }

        if (in_array($subdomain, self::RESERVED, true)) {
            throw InvalidSubdomain::reserved($subdomain);
        }

        return new self($subdomain);
    }

    /**
     * Hostnames are case-insensitive and the request's host always arrives
     * lower-cased, so a stored subdomain must be lower-case to match it.
     */
    public static function normalize(string $value): string
    {
        return strtolower(trim($value));
    }
}
