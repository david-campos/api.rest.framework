<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace model;
use model\simple_dao\ISimpleDAO;

/**
 * Factory for all the DAOs in the model. Para más información acerca del patrón DAO
 * o Abstract Factory consultar online.
 *
 * @see http://www.oracle.com/technetwork/java/dataaccessobject-138824.html
 *
 * @package model
 */
abstract class DAOFactory
{
    /** @var null|self Instancia única (patrón Singleton) */
    private static $singletonInstance = null;
    /** El constructor debe ser privado (patrón Singleton) */
    private function __construct() {}

    /** La copia también debe ser privada (patrón Singleton) */
    private function __clone() {}

    /**
     * Creates or gets the singleton instance of the factory.
     * Editing this method you can change the complete family in use.
     * @return DAOFactory
     */
    static public function getInstance(): DAOFactory {
        if (static::$singletonInstance === null) {
            return (static::$singletonInstance = new MysqliDAOFactory()); // Using MySqli
        } else
            return static::$singletonInstance;
    }

    // Abstract methods each concrete factory should implement

    /**
     * Obtiene un DAO para manejar asuntos de configuración de la API
     * @return IApiConfigDAO
     */
    abstract public function getApiConfigDAO(): IApiConfigDAO;

    /**
     * Se conecta a la base, y utilizando la información de configuración (consultar diagrama "4 - api sessions"
     * de la base de datos) instancia el arbol de SimpleDAOs de forma correcta.
     * @param string $toClass La clase del to que se desea guardar en la base
     * @return ISimpleDAO El dao para el to solicitado
     */
    abstract public function getSimpleDaoForTo(string $toClass): ISimpleDAO;
}