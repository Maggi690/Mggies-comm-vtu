<?php

namespace App\Exceptions;

use Exception;

class AuthException extends Exception {}
class InsufficientBalanceException extends Exception {}
class InvalidPinException extends Exception {}
class VtuException extends Exception {}
class PaymentException extends Exception {}
class NoProviderAvailableException extends Exception {}
class BlacklistException extends Exception {}
