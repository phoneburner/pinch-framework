<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421;

use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\Exception\InvalidHttpMessageSignatureInput;

/**
 * RFC9421 Signature Components value object.
 *
 * Represents the message components that will be included in the signature base,
 * handling both derived components (@method, @target-uri, @status) and header fields
 * with proper canonicalization per RFC9421.
 */
final readonly class HttpMessageSignatureComponents implements \Stringable
{
    private array $components;

    public function __construct(array $components)
    {
        if ($components === []) {
            throw new InvalidHttpMessageSignatureInput('Components list cannot be empty');
        }

        $validated_components = [];
        foreach ($components as $component_name => $component_value) {
            if (! \is_string($component_name) || ($component_name === '' || $component_name === '0')) {
                throw new InvalidHttpMessageSignatureInput('Component names must be non-empty strings');
            }

            if (! \is_string($component_value)) {
                throw new InvalidHttpMessageSignatureInput('Component values must be strings');
            }

            // Canonicalize component name (lowercase)
            $canonical_name = \strtolower($component_name);

            // Validate derived components format
            if (\str_starts_with($canonical_name, '@')) {
                $this->validateDerivedComponent($canonical_name, $component_value);
            }

            $validated_components[$canonical_name] = $component_value;
        }

        $this->components = $validated_components;
    }

    public static function fromArray(array $components): self
    {
        return new self($components);
    }

    public static function fromHttpMessage(
        string $method,
        string $target_uri,
        array $headers = [],
        int|null $status = null,
    ): self {
        $components = [];

        // Add derived components with proper canonicalization
        $components['@method'] = \strtoupper($method);
        $components['@target-uri'] = $target_uri;

        if ($status !== null) {
            $components['@status'] = (string)$status;
        }

        // Add header components with proper canonicalization
        foreach ($headers as $header_name => $header_value) {
            if (! \is_string($header_name) || ($header_name === '' || $header_name === '0')) {
                throw new InvalidHttpMessageSignatureInput('Header names must be non-empty strings');
            }

            if (! \is_string($header_value)) {
                throw new InvalidHttpMessageSignatureInput('Header values must be strings');
            }

            // Canonicalize header name (lowercase)
            $canonical_header_name = \strtolower($header_name);
            $components[$canonical_header_name] = $header_value;
        }

        return new self($components);
    }

    public function components(): array
    {
        return $this->components;
    }

    public function hasComponent(string $component_name): bool
    {
        $canonical_name = \strtolower($component_name);
        return \array_key_exists($canonical_name, $this->components);
    }

    public function getComponent(string $component_name): string|null
    {
        $canonical_name = \strtolower($component_name);
        return $this->components[$canonical_name] ?? null;
    }

    public function method(): string|null
    {
        return $this->getComponent('@method');
    }

    public function targetUri(): string|null
    {
        return $this->getComponent('@target-uri');
    }

    public function status(): string|null
    {
        return $this->getComponent('@status');
    }

    public function getHeader(string $header_name): string|null
    {
        return $this->getComponent($header_name);
    }

    public function withComponent(string $component_name, string $component_value): self
    {
        $new_components = $this->components;
        $canonical_name = \strtolower($component_name);

        // Validate derived components format if applicable
        if (\str_starts_with($canonical_name, '@')) {
            $this->validateDerivedComponent($canonical_name, $component_value);
        }

        $new_components[$canonical_name] = $component_value;

        return new self($new_components);
    }

    public function withoutComponent(string $component_name): self
    {
        $canonical_name = \strtolower($component_name);
        $new_components = $this->components;
        unset($new_components[$canonical_name]);

        if ($new_components === []) {
            throw new InvalidHttpMessageSignatureInput('Cannot remove component: components list would be empty');
        }

        return new self($new_components);
    }

    public function getComponentNames(): array
    {
        return \array_keys($this->components);
    }

    public function toString(): string
    {
        return $this->__toString();
    }

    public function __toString(): string
    {
        $component_lines = [];
        foreach ($this->components as $name => $value) {
            $component_lines[] = \sprintf('%s: %s', $name, $value);
        }

        return \implode("\n", $component_lines);
    }

    private function validateDerivedComponent(string $component_name, string $component_value): void
    {
        match ($component_name) {
            '@method' => $this->validateMethod($component_value),
            '@target-uri' => $this->validateTargetUri($component_value),
            '@status' => $this->validateStatus($component_value),
            '@authority' => $this->validateAuthority($component_value),
            '@scheme' => $this->validateScheme($component_value),
            '@path' => $this->validatePath($component_value),
            '@query' => $this->validateQuery(),
            default => null, // Allow other derived components
        };
    }

    private function validateMethod(string $method): void
    {
        if ($method === '' || $method === '0') {
            throw InvalidHttpMessageSignatureInput::invalidParameter('@method', 'cannot be empty');
        }

        // Validate method format per RFC7231 (allow uppercase and lowercase)
        if (! \preg_match('/^[A-Za-z]+$/', $method)) {
            throw InvalidHttpMessageSignatureInput::invalidParameter('@method', 'invalid format');
        }
    }

    private function validateTargetUri(string $target_uri): void
    {
        if ($target_uri === '' || $target_uri === '0') {
            throw InvalidHttpMessageSignatureInput::invalidParameter('@target-uri', 'cannot be empty');
        }

        // Basic URI validation
        if (! \filter_var($target_uri, \FILTER_VALIDATE_URL) && ! \str_starts_with($target_uri, '/')) {
            throw InvalidHttpMessageSignatureInput::invalidParameter('@target-uri', 'invalid format');
        }
    }

    private function validateStatus(string $status): void
    {
        if (! \preg_match('/^[1-5]\d{2}$/', $status)) {
            throw InvalidHttpMessageSignatureInput::invalidParameter('@status', 'invalid format');
        }
    }

    private function validateAuthority(string $authority): void
    {
        if ($authority === '' || $authority === '0') {
            throw InvalidHttpMessageSignatureInput::invalidParameter('@authority', 'cannot be empty');
        }
    }

    private function validateScheme(string $scheme): void
    {
        if ($scheme === '' || $scheme === '0') {
            throw InvalidHttpMessageSignatureInput::invalidParameter('@scheme', 'cannot be empty');
        }

        if (! \in_array(\strtolower($scheme), ['http', 'https'], true)) {
            throw InvalidHttpMessageSignatureInput::invalidParameter('@scheme', 'unsupported scheme');
        }
    }

    private function validatePath(string $path): void
    {
        // Path can be empty, which represents root path
        if (! \str_starts_with($path, '/') && ($path !== '' && $path !== '0')) {
            throw InvalidHttpMessageSignatureInput::invalidParameter('@path', 'must start with / or be empty');
        }
    }

    private function validateQuery(): void
    {
        // Query can be empty, no additional validation required
    }
}
