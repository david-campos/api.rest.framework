<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace controller;

use Throwable;
use view\Outputter;

/**
 * Class PrintableSafeException, clase de excepción abstracta de la que deben heredar
 * todas las excepciones que sean seguras para imprimir al usuario.
 * @package controller
 */
abstract class PrintableSafeException extends \Exception {}

/**
 * Class InvalidControllerException, lanzada cuando el controlador obtenido de la base
 * no es una clase de controlador válida
 * @package controller
 */
class InvalidControllerException extends \Exception {}

/**
 * Class UnknownMethodException, excepción lanzada cuando el método de consulta HTTP
 * empleado no se encuentra entre los que puede manejar el controlador
 * @package controller
 */
class UnknownMethodException extends PrintableSafeException {
    public function __construct(string $message, URLController $controller) {
        parent::__construct($message, Outputter::HTTP_METHOD_NOT_ALLOWED);
        $controller->options();
    }
}

/**
 * Class UnknownControllerException, excepción lanzada cuando no existe un
 * controlador adecuado para la URL solicitada.
 * @package controller
 */
class UnknownControllerException extends PrintableSafeException {
    public function __construct($message = "") {
        parent::__construct($message, Outputter::HTTP_NOT_FOUND);
    }
}

/**
 * Class ResourceNotFoundException, excepción lanzada cuando no se encuentra un recurso
 * @package controller
 */
class ResourceNotFoundException extends PrintableSafeException {
    function __construct($message = "") {
        parent::__construct($message, Outputter::HTTP_NOT_FOUND);
    }
}

/**
 * Class MethodNotAuthorizedException, excepción lanzada cuando el usuario no tiene nicel suficiente para
 * realizar la consulta con el método indicado sobre la url indicada.
 * @package controller
 */
class MethodNotAuthorizedException extends PrintableSafeException {
    public function __construct($message = "") {
        parent::__construct($message, Outputter::HTTP_UNAUTHORIZED);
    }
}

class RequestParsingException extends PrintableSafeException {
    public function __construct($message = "") {
        parent::__construct($message, Outputter::HTTP_BAD_REQUEST);
    }

}