<?php

namespace model;


use controller\session\SessionInfo;
use controller\URLController;

interface IApiConfigDAO {
    /**
     * Obtiene la lista de controladores con los patrones de url como clave y como valor
     * [controller=>controlador, dao=>dao]. El DAO será null si no es un simple dao
     * @param string[] $descriptions Indicar este parámetro si se desea almacenar las descripciones en un array en
     * la variable que aquí se pase.
     * @return string[]
     */
    public function getControllers(&$descriptions=null);

    /**
     * Setea en el controller los grupos requeridos para GET, POST, PUT y DELETE
     * @param URLController $controller
     * @param string $controllerName el nombre del controlador en la base de datos para poder encontrar sus niveles requeridos
     * @return void
     */
    public function setRequiredLevelsOnController(&$controller, $controllerName);

    /**
     * Realiza el login, creando una nueva sesión
     * @param string $user
     * @param string $pass
     * @return SessionInfo
     */
    public function login($user, $pass);

    /**
     * Cierra la sesión actual
     */
    public function closeSession();

    /**
     * Realiza el registro de un nuevo usuario
     * @param string $user nombre de usuario a registrar
     * @param string $pass contraseña para el usuario
     * @param int $tipoUsuario tipo de usuario, si la sesión actual no es un usuario con permiso para
     * elegir tipo el tipo será ignorado y seteado al tipo por defecto (ambas opciones se encuentran
     * en la tabla de configuración de la api, en la base de datos)
     * @param string $personaCifNif valor de cif/nif de la persona
     * asociada al usuario. Debe existir en la base
     * @return bool
     */
    public function registro($user, $pass, $tipoUsuario, $personaCifNif);

    /**
     * Obtiene la sesión correspondiente
     * @param int $sessionId Id de sesión
     * @param int $userId Id del usuario de la sesión
     * @param string $token Token de la sesión
     * @return SessionInfo El session info correspondiente, puede ser un NoSessionInfo si los datos no son coherentes
     */
    public function getSession($sessionId, $userId, $token);

    /**
     * Obtiene un valor de configuración de la API
     * @param string $configurationName nombre de la configuración
     * @return mixed el valor de la entrada de configuración
     * @throws
     */
    public function getApiConfig($configurationName);

    /**
     * Obtiene de la base las propiedades del TO indicado
     * @param string $nombreTo nombre del TO
     * @return PropiedadInterfaz[]
     */
    public function getPropiedadesTo($nombreTo);
}