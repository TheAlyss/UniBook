<?php

namespace App\Exceptions;

use RuntimeException;

/** Thrown by OcrService. The message is safe to show to the user. */
class OcrFailedException extends RuntimeException
{
}
