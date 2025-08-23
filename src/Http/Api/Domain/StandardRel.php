<?php

declare(strict_types=1);

namespace PhoneBurner\Pinch\Framework\Http\Api\Domain;

/**
 * @link https://www.rfc-editor.org/rfc/rfc8288.html
 * @link https://www.rfc-editor.org/rfc/rfc5988.html
 * @link https://www.iana.org/assignments/link-relations/link-relations.xhtml
 */
class StandardRel
{
    // HAL Standard Relations
    public const string CURIES = 'curies';
    public const string SELF = 'self';

    // IANA Standard Relations for Pagination
    public const string START = 'start';
    public const string NEXT = 'next';
    public const string PREV = 'prev';

    /**
     * @link https://www.rfc-editor.org/rfc/rfc8631.html
     * Identifies general metadata for the context that is primarily intended for consumption by machines.
     */
    public const string SERVICE_DESC = 'service-desc';

    /**
     * @link https://www.rfc-editor.org/rfc/rfc8631.html
     * Identifies service documentation for the context that is primarily intended for human consumption.
     */
    public const string SERVICE_DOC = 'service-doc';
}
