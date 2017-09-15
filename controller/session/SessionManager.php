<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace controller\session;

use model\DAOFactory;

/**
 * Class SessionManager, manejador de sesión muy básico que controla las sesiones en la API.
 * @package controller
 */
class SessionManager {
    // LAS SIGUIENTES CONSTANTES SOLO SE USAN PARA LAS PropiedadInterfaz,
    // DADO QUE AUN NO SE HAN IMPLEMENTADO LAS PropiedadInterfaz EN LA BASE
    // TODO: Quizas mover InterfazPropiedad a la base (total o parcialmente)
    /** All the session levels */
    public const SESSION_GROUP_EVERYONE = [-1,0,1,2,3,4,5,6,7,8,9,10];
    /** Admin session levels */
    public const SESSION_GROUP_ADMIN = [7];
    /** Noone */
    public const SESSION_GROUP_NOONE = [];

    public const PHP_LABEL_NOT_LOGGED_TOKEN = 'dummy-token';
    public const PHP_LABEL_LAST_TIMESTAMP = 'last-time';

    /** @var null|self Instancia única, patrón singleton */
    private static $singletonInstance = null;
    /** @var SessionInfo */
    private $sessionInfo = null;

    private function __construct() {}

    private function __clone() {}

    public static function getInstance() {
        if (static::$singletonInstance === null) {
            return (static::$singletonInstance = new self()); // Using MySqli
        } else
            return static::$singletonInstance;
    }

    /**
     * Inicia una sesión php segura
     */
    public static function session_start() {
        $nombre_sesion = 'sec_session_tnr';
        $seguro = true;
        if( $seguro && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') ) {
            // No es una conexión https
            throw new OnlyHttpsException('Se requiere conexión HTTPS');
        }
        $httponly = true;

        if (ini_set('session.use_only_cookies', 1) === FALSE) {
            throw new NotAbleToSetHttpOnlyException("Error: No se pudo iniciar una sesión segura");
        }

        // Obtiene los params de los cookies actuales.
        $cookieParams = session_get_cookie_params();
        session_set_cookie_params($cookieParams["lifetime"],
            $cookieParams["path"],
            $cookieParams["domain"],
            $seguro,
            $httponly);
        // Configura el nombre de sesión al configurado arriba.
        session_name($nombre_sesion);
        session_start();            // Inicia la sesión PHP.
        session_regenerate_id();    // Regenera la sesión, borra la previa.
    }


    /**
     * Almacena algo de control en las sesiones sin login para poder ir registrando datos
     */
    public static function unknownSessionCreation() {
        if(!isset($_SESSION[self::PHP_LABEL_NOT_LOGGED_TOKEN])) {
            $_SESSION[self::PHP_LABEL_NOT_LOGGED_TOKEN] = hash('sha256', uniqid(rand(), true));
            $_SESSION[self::PHP_LABEL_LAST_TIMESTAMP] = date('Y-m-d H:i:s');
        }
    }

    /**
     * Realiza el login, creando una nueva sesión
     * @param string $user Nombre de usuario
     * @param string $pass Contraseña enviada
     * @return SessionInfo
     */
    public function login($user, $pass) {
        if(!($sessionInfo = $this->check_sesion())->isLogeada()) {
            $sessionInfo = DAOFactory::getInstance()->getApiConfigDAO()->login($user, $pass);
            $sessionInfo->saveToPhpSession();
        }
        return $sessionInfo;
    }

    /**
     * Cierra (destruye) la sesión si había alguna iniciada
     * @param bool $onlyInPhp Si se setea a true, no se llamará al dao para eliminar la sesión en la base
     */
    public function closeSession($onlyInPhp=false) {
        if(!$onlyInPhp) {
            DAOFactory::getInstance()->getApiConfigDAO()->closeSession();
        }
        // Recreamos la sesión (sin perder el token de no logeado)
        if(SessionInfo::isInPhpSession()) {
            $token = (
            isset($_SESSION[self::PHP_LABEL_NOT_LOGGED_TOKEN]) ?
                $_SESSION[self::PHP_LABEL_NOT_LOGGED_TOKEN] :
                null
            );
            session_unset();
            session_destroy();
            self::session_start();
            if ($token) {
                $_SESSION[self::PHP_LABEL_NOT_LOGGED_TOKEN] = $token;
                $_SESSION[self::PHP_LABEL_LAST_TIMESTAMP] = date('Y-m-d H:i:s');
            }
        }
    }

    /**
     * Checks if a session is correct and gets the session info
     * @return SessionInfo
     * @throws TokenNotReceivedException
     */
    public function check_sesion(): SessionInfo {
        if(!$this->sessionInfo) {
            self::session_start();
            self::unknownSessionCreation();
            if (!SessionInfo::isInPhpSession()) {
                $this->sessionInfo = $this->getNoSessionInfo();
            } else {
                $sessionId = $_SESSION[SessionInfo::PHP_LABEL_SESSION_ID];
                $userId = $_SESSION[SessionInfo::PHP_LABEL_USER_ID];
                $token = $_SESSION[SessionInfo::PHP_LABEL_SESSION_TOKEN];
                // La obtenemos (si ha expirado la cerrará y devolverá no session info)
                $this->sessionInfo = DAOFactory::getInstance()->getApiConfigDAO()
                    ->getSession($sessionId, $userId, $token);
            }
            $_SESSION[self::PHP_LABEL_LAST_TIMESTAMP] = date('Y-m-d H:i:s');
        }
        return $this->sessionInfo;
    }

    /**
     * Obtiene un no session info a partir del token en la sesión y cambia
     * el last timestamp
     * @param bool $expired si se setea a true el no session info será creado indicando que la sesión ha expirado
     * @return NoSessionInfo
     */
    public function getNoSessionInfo($expired=false): NoSessionInfo {
        // Eliminamos, por si acaso, la sesión de PHP si existe
        SessionManager::getInstance()->closeSession(true);
        return new NoSessionInfo(
            $_SESSION[self::PHP_LABEL_NOT_LOGGED_TOKEN],
            $_SESSION[self::PHP_LABEL_LAST_TIMESTAMP],
            $expired);
    }

    /**
     * Registra un nuevo usuario en la base de datos
     * @param string $userName nombre de usuario para registrarse
     * @param string $pass password para el usuario
     * @param string $personaCifNif nif o cif de la persona a la que se asociará el usuario
     * @param int $tipo tipo de usuario, si la sesión actual no es un usuario con permiso para
     * elegir tipo el tipo será ignorado y seteado al tipo por defecto (ambas opciones se encuentran
     * en la tabla de configuración de la api, en la base de datos)
     */
    public function registro(string $userName, string $pass, string $personaCifNif, int $tipo) {
        DAOFactory::getInstance()->getApiConfigDAO()->registro($userName, $pass, $tipo, $personaCifNif);
    }

    /**
     * Genera un nuevo token aleatorio para la sesión, será cambiado en cada consulta y el
     * nuevo será enviado en la respuesta
     * @return string el nuevo token aleatorio
     * @throws OpenSslRandomPseudoBytesNotStrong Si el sistema no tiene un algoritmo seguro para generar el token
     * de sesión (actualizar el sistema)
     */
    public function generarToken(): string {
        $randomPseudoBytes = bin2hex(openssl_random_pseudo_bytes(32,  $strong));
        if( !$strong ) {
            throw new OpenSslRandomPseudoBytesNotStrong();
        }
        return hash('sha256', uniqid(rand(), true).$randomPseudoBytes);
    }

    /**
     * Método que comprueba si el usuario de un session info tiene el nivel requerido
     * @param SessionInfo $session
     * @param mixed $requiredLevels niveles requeridos
     * @return bool true si tiene el nivel requerido, false si no
     */
    public final function checkEnoughLevel(SessionInfo $session, $requiredLevels): bool {
        return in_array($session->getSessionType(), $requiredLevels);
    }
}