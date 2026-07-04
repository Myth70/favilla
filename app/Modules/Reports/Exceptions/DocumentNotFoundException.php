<?php

declare(strict_types=1);

namespace App\Modules\Reports\Exceptions;

/**
 * Thrown by DocumentService when the binding or source record required to
 * generate a document does not exist.
 *
 * Controllers map this to HTTP 404, distinct from generic generation failures
 * (misconfigured template, rendering errors) which remain HTTP 500.
 */
class DocumentNotFoundException extends \RuntimeException
{
}
