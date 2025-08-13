<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421;

use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\Exception\InvalidHttpMessageSignatureInput;
use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\HttpMessageSignatureComponents;
use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\HttpMessageSignatureInput;

/**
 * RFC9421 Signature Base value object.
 *
 * Represents the canonical signature base string that will be signed,
 * built from signature components with proper ordering and formatting per RFC9421.
 */
final readonly class HttpMessageSignatureBase implements \Stringable
{
    private string $signature_base;

    public function __construct(
        HttpMessageSignatureInput $signature_input,
        HttpMessageSignatureComponents $signature_components,
    ) {
        $this->signature_base = $this->buildSignatureBase($signature_input, $signature_components);
    }

    public static function fromComponents(
        HttpMessageSignatureInput $signature_input,
        HttpMessageSignatureComponents $signature_components,
    ): self {
        return new self($signature_input, $signature_components);
    }

    public function signatureBase(): string
    {
        return $this->signature_base;
    }

    public function toString(): string
    {
        return $this->signature_base;
    }

    public function __toString(): string
    {
        return $this->signature_base;
    }

    public function toBytes(): string
    {
        return $this->signature_base;
    }

    private function buildSignatureBase(
        HttpMessageSignatureInput $signature_input,
        HttpMessageSignatureComponents $signature_components,
    ): string {
        $lines = [];

        // Process each covered component in order
        foreach ($signature_input->coveredComponents() as $component_name) {
            $component_value = $signature_components->getComponent($component_name);

            if ($component_value === null) {
                throw InvalidHttpMessageSignatureInput::missingComponent($component_name);
            }

            // Build component line: "component-name": value
            $canonical_component_name = \strtolower((string)$component_name);
            $canonical_value = $this->canonicalizeComponentValue($canonical_component_name, $component_value);
            $component_line = \sprintf('"%s": %s', $canonical_component_name, $canonical_value);

            $lines[] = $component_line;
        }

        // Add signature parameters line
        $signature_params_line = $this->buildSignatureParametersLine($signature_input);
        $lines[] = $signature_params_line;

        // Join with LF (line feed) characters per RFC9421
        return \implode("\n", $lines);
    }

    private function canonicalizeComponentValue(string $component_name, string $component_value): string
    {
        // Handle derived components
        if (\str_starts_with($component_name, '@')) {
            return match ($component_name) {
                '@method' => \strtoupper($component_value),
                '@target-uri' => $component_value, // URI is already canonical
                '@status' => $component_value, // Status code is already canonical
                '@authority' => \strtolower($component_value),
                '@scheme' => \strtolower($component_value),
                '@path' => $component_value, // Path is already canonical
                '@query' => $component_value, // Query is already canonical
                default => $component_value,
            };
        }

        // Handle header fields - trim whitespace and normalize whitespace
        return $this->normalizeHeaderValue($component_value);
    }

    private function normalizeHeaderValue(string $header_value): string
    {
        // Trim leading and trailing whitespace
        $normalized = \trim($header_value);

        // Collapse multiple consecutive spaces/tabs into a single space
        $normalized = \preg_replace('/\s+/', ' ', $normalized);

        return $normalized ?? $header_value;
    }

    private function buildSignatureParametersLine(HttpMessageSignatureInput $signature_input): string
    {
        // Build the signature parameters line format per RFC9421
        // Format: "@signature-params": (covered components);param1=value1;param2=value2

        // Build covered components list
        $components_list = \implode(' ', \array_map(
            static fn(string $component): string => \sprintf('"%s"', \strtolower($component)),
            $signature_input->coveredComponents(),
        ));

        // Build parameters string
        $param_strings = [];
        foreach ($signature_input->parameters() as $key => $value) {
            $param_strings[] = \is_string($value) ? \sprintf('%s="%s"', $key, $value) : \sprintf('%s=%d', $key, $value);
        }

        $signature_params_value = \sprintf('(%s)', $components_list);
        if ($param_strings !== []) {
            $signature_params_value .= ';' . \implode(';', $param_strings);
        }

        return \sprintf('"@signature-params": %s', $signature_params_value);
    }
}
