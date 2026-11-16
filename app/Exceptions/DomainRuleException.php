<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A business rule refused the requested action. The message is safe to show
 * to the authenticated officer or member who attempted the action.
 */
class DomainRuleException extends RuntimeException {}
