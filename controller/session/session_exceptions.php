<?php

namespace controller\session;

use controller\PrintableSafeException;
use Exception;

class NotAbleToSetHttpOnlyException extends Exception {}

class LoginException extends Exception {}

class UserNotExistentException extends LoginException {}

class BlockedUserException extends LoginException {}

class PossibleBruteForceAttackException extends LoginException {}

class IncorrectPasswordException extends LoginException {}

class OpenSslRandomPseudoBytesNotStrong extends Exception {}

class OnlyHttpsException extends PrintableSafeException {}

class TokenNotReceivedException extends PrintableSafeException {}