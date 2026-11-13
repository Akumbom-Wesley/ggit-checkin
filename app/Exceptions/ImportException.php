<?php

namespace App\Exceptions;

use RuntimeException;

/** A problem with an uploaded import file, with a message that is safe to show to staff. */
class ImportException extends RuntimeException
{
}