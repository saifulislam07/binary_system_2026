<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An SMS/WhatsApp gateway failed in a way worth retrying (timeout, 5xx,
 * provider-side error, rate limit). Thrown from a channel so the queued
 * notification job for that one channel is retried; permanent refusals
 * (bad number, bad credentials, low balance) are logged instead.
 */
class MessageDeliveryException extends RuntimeException {}
