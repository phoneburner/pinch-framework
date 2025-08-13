<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421;

use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\Exception\InvalidHttpMessageSignatureInput;

/**
 * RFC9421 Signature-Input header value object.
 *
 * Represents a parsed Signature-Input header containing signature parameters
 * and covered components list according to RFC9421.
 *
 * Example: sig1=("@method" "@target-uri" "content-digest");created=1618884473;alg="ed25519";keyid="test-key-ed25519"
 */
final readonly class HttpMessageSignatureInput implements \Stringable
{
    private array $parameters;
    private array $covered_components;

    public function __construct(
        private string $signature_label,
        array $covered_components,
        array $parameters = [],
    ) {
        if ($signature_label === '' || $signature_label === '0') {
            throw new InvalidHttpMessageSignatureInput('Signature label cannot be empty');
        }

        if ($covered_components === []) {
            throw new InvalidHttpMessageSignatureInput('Covered components list cannot be empty');
        }

        // Validate signature label format (alphanumeric and underscore only)
        if (! \preg_match('/^\w+$/', $signature_label)) {
            throw InvalidHttpMessageSignatureInput::invalidComponentName($signature_label);
        }

        // Validate covered components
        foreach ($covered_components as $component) {
            if (! \is_string($component) || ($component === '' || $component === '0')) {
                throw new InvalidHttpMessageSignatureInput('All covered components must be non-empty strings');
            }
        }

        // Validate parameters
        foreach ($parameters as $key => $value) {
            if (! \is_string($key) || ($key === '' || $key === '0')) {
                throw new InvalidHttpMessageSignatureInput('Parameter keys must be non-empty strings');
            }

            if (! \is_string($value) && ! \is_int($value)) {
                throw new InvalidHttpMessageSignatureInput('Parameter values must be strings or integers');
            }

            // Validate specific parameter formats per RFC9421
            match ($key) {
                'created', 'expires' => $this->validateTimestampParameter($value),
                'nonce' => $this->validateNonceParameter($value),
                'alg' => $this->validateAlgorithmParameter($value),
                'keyid' => $this->validateKeyIdParameter($value),
                default => null, // Other parameters are allowed
            };
        }

        $this->covered_components = $covered_components;
        $this->parameters = $parameters;
    }

    public static function fromString(string $signature_input_header): self
    {
        // Parse format: label=("component1" "component2");param1=value1;param2=value2
        if (! \preg_match('/^(\w+)=\(([^)]+)\)(.*)$/', \trim($signature_input_header), $matches)) {
            throw InvalidHttpMessageSignatureInput::malformedHeader($signature_input_header);
        }

        $signature_label = $matches[1];
        $components_string = $matches[2];
        $parameters_string = $matches[3];

        // Parse covered components
        $covered_components = [];
        if (! \preg_match_all('/"([^"]+)"/', $components_string, $component_matches)) {
            throw new InvalidHttpMessageSignatureInput('Invalid covered components format');
        }
        $covered_components = $component_matches[1];

        // Parse parameters
        $parameters = [];
        if ($parameters_string !== '' && $parameters_string !== '0') {
            $param_pairs = \explode(';', \ltrim($parameters_string, ';'));
            foreach ($param_pairs as $pair) {
                if (\in_array(\trim($pair), ['', '0'], true)) {
                    continue;
                }

                if (! \preg_match('/^([^=]+)=(.+)$/', \trim($pair), $param_matches)) {
                    throw new InvalidHttpMessageSignatureInput(\sprintf('Invalid parameter format: %s', $pair));
                }

                $param_key = \trim($param_matches[1]);
                $param_value = \trim($param_matches[2], '"');

                // Convert numeric strings to integers for timestamp parameters
                if (\in_array($param_key, ['created', 'expires'], true) && \ctype_digit($param_value)) {
                    $param_value = (int)$param_value;
                }

                $parameters[$param_key] = $param_value;
            }
        }

        return new self($signature_label, $covered_components, $parameters);
    }

    public function signatureLabel(): string
    {
        return $this->signature_label;
    }

    public function coveredComponents(): array
    {
        return $this->covered_components;
    }

    public function parameters(): array
    {
        return $this->parameters;
    }

    public function hasParameter(string $key): bool
    {
        return \array_key_exists($key, $this->parameters);
    }

    public function getParameter(string $key): string|int|null
    {
        return $this->parameters[$key] ?? null;
    }

    public function created(): int|null
    {
        $created = $this->getParameter('created');
        return \is_int($created) ? $created : null;
    }

    public function expires(): int|null
    {
        $expires = $this->getParameter('expires');
        return \is_int($expires) ? $expires : null;
    }

    public function algorithm(): string|null
    {
        $alg = $this->getParameter('alg');
        return \is_string($alg) ? $alg : null;
    }

    public function keyId(): string|null
    {
        $keyid = $this->getParameter('keyid');
        return \is_string($keyid) ? $keyid : null;
    }

    public function nonce(): string|null
    {
        $nonce = $this->getParameter('nonce');
        return \is_string($nonce) ? $nonce : null;
    }

    public function toString(): string
    {
        return $this->__toString();
    }

    public function __toString(): string
    {
        // Build components list
        $components_string = \implode(' ', \array_map(
            static fn(string $component): string => \sprintf('"%s"', $component),
            $this->covered_components,
        ));

        // Build parameters string
        $param_strings = [];
        foreach ($this->parameters as $key => $value) {
            $param_strings[] = \is_string($value) ? \sprintf('%s="%s"', $key, $value) : \sprintf('%s=%d', $key, $value);
        }

        $result = \sprintf('%s=(%s)', $this->signature_label, $components_string);
        if ($param_strings !== []) {
            $result .= ';' . \implode(';', $param_strings);
        }

        return $result;
    }

    private function validateTimestampParameter(mixed $value): void
    {
        if (! \is_int($value) || $value < 0) {
            throw InvalidHttpMessageSignatureInput::invalidParameter('timestamp', 'must be non-negative integer');
        }
    }

    private function validateNonceParameter(mixed $value): void
    {
        if (! \is_string($value) || ($value === '' || $value === '0')) {
            throw InvalidHttpMessageSignatureInput::invalidParameter('nonce', 'must be non-empty string');
        }
    }

    private function validateAlgorithmParameter(mixed $value): void
    {
        if (! \is_string($value) || ($value === '' || $value === '0')) {
            throw InvalidHttpMessageSignatureInput::invalidParameter('alg', 'must be non-empty string');
        }
    }

    private function validateKeyIdParameter(mixed $value): void
    {
        if (! \is_string($value) || ($value === '' || $value === '0')) {
            throw InvalidHttpMessageSignatureInput::invalidParameter('keyid', 'must be non-empty string');
        }
    }
}
