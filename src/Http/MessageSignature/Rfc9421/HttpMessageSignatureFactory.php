<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421;

use PhoneBurner\Pinch\Component\Cryptography\Exception\InvalidSignature;
use PhoneBurner\Pinch\Component\Cryptography\Hash\Hash;
use PhoneBurner\Pinch\Component\Cryptography\Hash\HashAlgorithm;
use PhoneBurner\Pinch\Component\Cryptography\KeyManagement\KeyChain;
use PhoneBurner\Pinch\Component\Cryptography\Natrium;
use PhoneBurner\Pinch\Component\Cryptography\String\MessageSignature;
use PhoneBurner\Pinch\Component\Http\Domain\HttpHeader;
use PhoneBurner\Pinch\Component\Http\MessageSignature\HttpMessageSignatureFactory as HttpMessageSignatureFactoryContract;
use PhoneBurner\Pinch\Component\Http\Psr7;
use PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\Exception\InvalidHttpMessageSignatureInput;
use PhoneBurner\Pinch\String\Encoding\Encoding;
use PhoneBurner\Pinch\Time\Clock\Clock;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * RFC 9421 HTTP Message Signatures implementation.
 *
 * Provides signing and verification of HTTP messages according to RFC 9421 specification.
 * Uses Ed25519 signatures with proper canonicalization and timing-safe verification.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc9421
 */
class HttpMessageSignatureFactory implements HttpMessageSignatureFactoryContract
{
    public function __construct(
        private readonly Natrium $natrium,
        private readonly KeyChain $key_chain,
        private readonly Clock $clock,
    ) {
    }

    /**
     * Signs an HTTP message according to RFC 9421 specification.
     *
     * Creates a signature covering the specified message components using Ed25519.
     * The signature is added via Signature-Input and Signature headers.
     *
     * @template T of MessageInterface
     * @param T $message The HTTP message to sign
     * @param string $signature_input_name The signature label (default: 'sig1')
     * @param array<string> $additional_headers Additional headers to include in signature
     * @return T The message with signature headers added
     * @throws InvalidHttpMessageSignatureInput If message components are invalid
     */
    public function sign(
        MessageInterface $message,
        string $signature_input_name = 'sig1',
        array $additional_headers = [HttpHeader::CONTENT_TYPE, HttpHeader::IDEMPOTENCY_KEY],
    ): MessageInterface {
        $content = (string)$message->getBody();

        // be kind, rewind (if possible, otherwise mutate the request with a new body stream)
        if ($message->getBody()->isSeekable()) {
            $message->getBody()->rewind();
        } else {
            $message = $message->withBody(Psr7::stream($content));
        }

        // Build message components from HTTP message
        $message_components = $this->buildMessageComponents($message, $additional_headers, $content);
        $signature_components = HttpMessageSignatureComponents::fromArray($message_components);

        // Generate key ID as SHA-256 hash of public key
        $public_key = $this->key_chain->signature()->public();
        $key_id = Hash::string($public_key->bytes(), HashAlgorithm::SHA256)->digest(Encoding::Hex);

        // Create signature input with RFC 9421 parameters
        $covered_components = \array_keys($message_components);
        $signature_parameters = [
            'created' => $this->clock->now()->getTimestamp(),
            'alg' => 'ed25519',
            'keyid' => $key_id,
        ];

        $signature_input = new HttpMessageSignatureInput($signature_input_name, $covered_components, $signature_parameters);

        // Build canonical signature base
        $signature_base = HttpMessageSignatureBase::fromComponents($signature_input, $signature_components);

        // Sign the signature base
        $signature = $this->natrium->signWithSecretKey($signature_base->toBytes());

        $message->getBody()->rewind(); // be kind, rewind the body stream
        return $message->withHeader(HttpHeader::CONTENT_DIGEST, $message_components['content-digest'])
            ->withHeader(HttpHeader::SIGNATURE_INPUT, $signature_input->toString())
            ->withHeader(HttpHeader::SIGNATURE, \sprintf('%s=:%s:', $signature_input_name, $signature->export(Encoding::Base64)));
    }

    /**
     * Builds message components array for signing.
     *
     * @param MessageInterface $message The HTTP message
     * @param array<string> $additional_headers Additional headers to include
     * @param string $content The message body content
     * @return array<string, string> Message components keyed by component name
     */
    private function buildMessageComponents(
        MessageInterface $message,
        array $additional_headers,
        string $content,
    ): array {
        $components = [];

        // Add default signature components based on message type
        if ($message instanceof RequestInterface) {
            $components['@method'] = \strtoupper($message->getMethod());
            $components['@target-uri'] = (string)$message->getUri();
        } elseif ($message instanceof ResponseInterface) {
            $components['@status'] = (string)$message->getStatusCode();
        }

        // Add additional headers if present
        foreach ($additional_headers as $header_name) {
            if ($message->hasHeader($header_name)) {
                $canonical_name = \strtolower($header_name);
                $components[$canonical_name] = $message->getHeaderLine($header_name);
            }
        }

        // Add content digest
        $components['content-digest'] = $this->calculateContentDigest($content);

        return $components;
    }

    /**
     * Extracts message components for verification.
     *
     * @param MessageInterface $message The HTTP message
     * @param array<string> $covered_components List of component names to extract
     * @param string $content The message body content
     * @return array<string, string> Message components keyed by component name
     * @throws InvalidHttpMessageSignatureInput If required components are missing
     */
    private function extractMessageComponents(
        MessageInterface $message,
        array $covered_components,
        string $content,
    ): array {
        $components = [];

        foreach ($covered_components as $component_name) {
            $canonical_name = \strtolower($component_name);

            // Handle derived components
            if (\str_starts_with($canonical_name, '@')) {
                $components[$canonical_name] = match ($canonical_name) {
                    '@method' => $message instanceof RequestInterface
                        ? \strtoupper($message->getMethod())
                        : throw InvalidHttpMessageSignatureInput::missingComponent($canonical_name),
                    '@target-uri' => $message instanceof RequestInterface
                        ? (string)$message->getUri()
                        : throw InvalidHttpMessageSignatureInput::missingComponent($canonical_name),
                    '@status' => $message instanceof ResponseInterface
                        ? (string)$message->getStatusCode()
                        : throw InvalidHttpMessageSignatureInput::missingComponent($canonical_name),
                    default => throw InvalidHttpMessageSignatureInput::invalidComponentName($canonical_name),
                };
            } elseif ($canonical_name === 'content-digest') {
                // Recalculate content digest for verification
                $components[$canonical_name] = $this->calculateContentDigest($content);
            } else {
                // Handle header components
                if (! $message->hasHeader($canonical_name)) {
                    throw InvalidHttpMessageSignatureInput::missingComponent($canonical_name);
                }
                $components[$canonical_name] = $message->getHeaderLine($canonical_name);
            }
        }

        return $components;
    }

    /**
     * Calculates RFC 3230 content digest for message body.
     *
     * @param string $content The message body content
     * @return string Content digest in format "sha-512=:base64:"
     */
    private function calculateContentDigest(string $content): string
    {
        $hash = Hash::string($content, HashAlgorithm::SHA512);
        return \sprintf('%s=:%s:', 'sha-512', $hash->digest(Encoding::Base64));
    }

    /**
     * Verifies HTTP message signatures according to RFC 9421 specification.
     *
     * Parses Signature-Input and Signature headers, recreates the signature base,
     * looks up the public key, and verifies the signature using timing-safe comparison.
     *
     * @param MessageInterface $message The HTTP message to verify
     * @return bool True if any signature is valid, false otherwise
     * @throws InvalidHttpMessageSignatureInput If signature headers are malformed
     */
    public function verify(MessageInterface $message): bool
    {
        // Check for required signature headers
        if (! $message->hasHeader('Signature-Input') || ! $message->hasHeader('Signature')) {
            return false;
        }

        $signature_input_header = $message->getHeaderLine('Signature-Input');
        $signature_header = $message->getHeaderLine('Signature');

        try {
            // Parse signature input header
            $signature_input = HttpMessageSignatureInput::fromString($signature_input_header);

            // Parse signature value from Signature header
            $signature_label = $signature_input->signatureLabel();
            if (! \preg_match(\sprintf('/^%s=:([A-Za-z0-9+\/]+={0,2}):$/', $signature_label), $signature_header, $matches)) {
                throw new InvalidSignature('Invalid signature format in Signature header');
            }
            $signature_bytes = $matches[1];

            // Get key ID and look up public key
            $key_id = $signature_input->keyId();
            if ($key_id === null) {
                throw new InvalidHttpMessageSignatureInput('Missing keyid parameter in signature input');
            }

            $public_key = $this->key_chain->lookup($key_id);
            if ($public_key === null) {
                return false; // Key not found, signature invalid
            }

            // Validate algorithm
            $algorithm = $signature_input->algorithm();
            if ($algorithm !== 'ed25519') {
                throw new InvalidHttpMessageSignatureInput('Unsupported signature algorithm: ' . ($algorithm ?? 'none'));
            }

            // Validate timestamp if present (optional)
            $created = $signature_input->created();
            if ($created !== null) {
                $current_time = $this->clock->now()->getTimestamp();
                // Allow 5 minute clock skew
                if ($created > $current_time + 300 || $created < $current_time - 300) {
                    return false; // Signature timestamp out of acceptable range
                }
            }

            // Build signature components from current message
            $content = (string)$message->getBody();
            if ($message->getBody()->isSeekable()) {
                $message->getBody()->rewind();
            }

            $message_components = $this->extractMessageComponents($message, $signature_input->coveredComponents(), $content);
            $signature_components = HttpMessageSignatureComponents::fromArray($message_components);

            // Recreate signature base
            $signature_base = HttpMessageSignatureBase::fromComponents($signature_input, $signature_components);

            // Verify signature using timing-safe comparison
            $message_signature = MessageSignature::import($signature_bytes, Encoding::Base64);
            return $this->natrium->verifyWithPublicKey(
                $public_key,
                $message_signature,
                $signature_base->toBytes(),
            );
        } catch (InvalidHttpMessageSignatureInput | InvalidSignature) {
            // Invalid signature format or parameters
            return false;
        }
    }
}
