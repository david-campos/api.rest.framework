<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace model\exceptions;

use controller\PrintableSafeException;
use model\PropiedadInterfaz;
use view\Outputter;

/**
 * Class RequiredPropertyException, lanzada cuando una propiedad requerida en un TO no se encuentra
 * en el array pasado para actualizarlo.
 * @package model\exceptions
 */
class RequiredPropertyException extends PrintableSafeException {
    public function __construct(PropiedadInterfaz $property) {
        parent::__construct('Required property not found: <'.$property->getLabel().'>', Outputter::HTTP_BAD_REQUEST);
    }
}

/**
 * Class RequiredFieldException, lanzada cuando algún array no contiene algún campo requerido
 * @package model\exceptions
 */
class RequiredFieldException extends PrintableSafeException {
    public function __construct(string $message='') {
        parent::__construct($message, Outputter::HTTP_BAD_REQUEST);
    }
}

/**
 * Class OnlySingleInsertionException, lanzada cuando se requiere una inserción múltiple a un
 * controlador que no la admite.
 * @package model\exceptions
 */
class OnlySingleInsertionException extends PrintableSafeException {
    public function __construct(string $message='') {
        parent::__construct($message, Outputter::HTTP_BAD_REQUEST);
    }
}

/**
 * Class UncontrolledMysqliException, lanzada ante una excepción de Mysqli que no hemos controlado
 * @package model\exceptions
 */
class UncontrolledMysqliException extends PrintableSafeException {
    public function __construct(\mysqli_sql_exception $e) {
        parent::__construct(
            'mysqli_sql_exception('.$e->getCode().'): '.$e->getMessage(),
            Outputter::HTTP_INTERNAL_SERVER_ERROR);
    }
}

/**
 * Class ForeignKeyConstraintException, lanzada ante una excepción Mysqli de foreign key constraint
 * al eliminar
 * @package model\exceptions
 */
class ForeignKeyConstraintException extends PrintableSafeException {
    public function __construct($message = "") {
        parent::__construct($message, Outputter::HTTP_CONFLICT);
    }
}

/**
 * Class AlreadyExistentResourceException, lanzada cuando un recurso que se intenta crear ya existe
 * @package model\exceptions
 */
class AlreadyExistentResourceException extends PrintableSafeException {
    public function __construct($message = "") {
        parent::__construct($message, Outputter::HTTP_CONFLICT);
    }
}

/**
 * Lanzada cuando una entrada de configuración buscada no existe en la base de datos.
 * @package model\exceptions
 */
class ConfigurationNotFoundException extends \Exception {}