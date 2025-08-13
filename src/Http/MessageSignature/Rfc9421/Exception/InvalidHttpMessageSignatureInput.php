<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\MessageSignature\Rfc9421\Exception;

use PhoneBurner\Pinch\Component\Cryptography\Exception\CryptographicLogicException;

/**
 * Exception thrown when signature input is malformed or invalid per RFC9421.
 */
class InvalidHttpMessageSignatureInput extends CryptographicLogicException
{
    public static function malformedHeader(string $header): self
    {
        return new self(\sprintf('Malformed Signature-Input header: %s', $header));
    }

    public static function invalidComponentName(string $component_name): self
    {
        return new self(\sprintf('Invalid component name: %s', $component_name));
    }

    public static function missingComponent(string $component_name): self
    {
        return new self(\sprintf('Required component missing: %s', $component_name));
    }

    public static function invalidParameter(string $parameter_name, string $reason): self
    {
        return new self(\sprintf('Invalid parameter "%s": %s', $parameter_name, $reason));
    }
}
