<?php

namespace model;

use controller\session\BlockedUserException;
use controller\session\IncorrectPasswordException;
use controller\session\OpenSslRandomPseudoBytesNotStrong;
use controller\session\PossibleBruteForceAttackException;
use controller\session\SessionInfo;
use controller\session\SessionManager;
use controller\session\UserNotExistentException;
use controller\URLController;
use model\exceptions\ConfigurationNotFoundException;

class MysqliApiConfigDAO extends MysqliDAO implements IApiConfigDAO {
    /**
     * Obtiene la lista de controladores con los patrones de url como clave
     * @param string[] $description
     * @return string[]
     */
    public function getControllers(&$description=null) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        $result = static::$link->query(
            'SELECT p.`pattern` AS \'pat\', p.`claseController` AS \'cont\', c.descripcion AS \'desc\',
                      c.simpleDao AS \'dao\'
                    FROM `tnr_api_mul_urlControllers_urlPatterns` p
                      LEFT JOIN `tnr_api_ent_urlControllers` c ON (p.claseController = c.clase)');
        $list = [];
        $description = [];
        while($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $list[$row['pat']] = ['controller'=>$row['cont'], 'dao'=>$row['dao']];
            if(!key_exists($row['cont'], $description)) {
                $description[$row['cont']] = $row['desc'];
            }
        }
        $result->close();
        static::$link->commit();
        return $list;
    }

    /**
     * Setea en el controller los grupos requeridos para GET, POST, PUT y DELETE
     * @param URLController $controller
     * @param string $controllerName nombre del controlador
     * @return void
     */
    public function setRequiredLevelsOnController(&$controller, $controllerName) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        $claseController = static::$link->real_escape_string($controllerName);
        $query = [
            "SELECT s.typeId, g.incluyeNoLogeados
                    FROM `tnr_api_ent_urlControllers` c
                    LEFT JOIN `tnr_api_ent_userTypeGroups` g ON (c.levelsFor",
            "=g.typesGroupId)
                    LEFT JOIN `tnr_api_rel_userTypesGroups` s 
                    ON (g.typesGroupId=s.groupId) WHERE c.`clase`='$claseController'"
        ];
        $queryGet = $query[0].'Get'.$query[1];
        $queryDel = $query[0].'Delete'.$query[1];
        $queryPos = $query[0].'Post'.$query[1];
        $queryPut = $query[0].'Put'.$query[1];

        $result = static::$link->query($queryGet);
        $list = [];
        if($row = $result->fetch_row()) {
            if($row[1]) {
                $list[] = SessionInfo::NO_SESSION_TYPE_ID; // No logeados tambien
            }
            do {
                $list[] = $row[0];
            } while($row = $result->fetch_row());
        }
        $controller->setRequiredGroupGet($list);
        $result->close();

        $result = static::$link->query($queryPos);
        $list = [];
        if($row = $result->fetch_row()) {
            if($row[1]) {
                $list[] = SessionInfo::NO_SESSION_TYPE_ID; // No logeados tambien
            }
            do {
                $list[] = $row[0];
            } while($row = $result->fetch_row());
        }
        $controller->setRequiredGroupPost($list);
        $result->close();

        $result = static::$link->query($queryDel);
        $list = [];
        if($row = $result->fetch_row()) {
            if($row[1]) {
                $list[] = SessionInfo::NO_SESSION_TYPE_ID; // No logeados tambien
            }
            do {
                $list[] = $row[0];
            } while($row = $result->fetch_row());
        }
        $controller->setRequiredGroupDelete($list);
        $result->close();

        $result = static::$link->query($queryPut);
        $list = [];
        if($row = $result->fetch_row()) {
            if($row[1]) {
                $list[] = SessionInfo::NO_SESSION_TYPE_ID; // No logeados tambien
            }
            do {
                $list[] = $row[0];
            } while($row = $result->fetch_row());
        }
        $controller->setRequiredGroupPut($list);
        $result->close();

        static::$link->commit();
    }

    /**
     * Realiza el login, creando una nueva sesión
     * @param string $user Nombre de usuario
     * @param string $pass Contraseña que ha enviado
     * @throws BlockedUserException si el usuario está bloqueado y por tanto no puede realizar el login
     * @throws IncorrectPasswordException Si la contraseña introducida no es válida
     * @throws OpenSslRandomPseudoBytesNotStrong Si el sistema no tiene un algoritmo seguro para generar el token
     * de sesión (actualizar el sistema)
     * @throws PossibleBruteForceAttackException si el usuario es sospechoso de estar sufriendo un ataque por fuerza
     * bruta
     * @throws UserNotExistentException si el usuario no existe
     * @return SessionInfo
     */
    public function login($user, $pass) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        $this->checkUser(
            $user, $pass, $userId, $cifNif, $tipoUsuario, $eligeTipo, $email, $tipoPersona, $nombre, $apellidos, $activado);
        $sessionInfo = $this->createSession(
            $userId, $cifNif, $tipoUsuario, $eligeTipo, $email, $tipoPersona, $nombre, $apellidos, $activado);
        static::$link->commit();
        return $sessionInfo;
    }

    /**
     * Comprueba que el usuario y contraseña son correctos para el login.
     * Se asegura que el usuario es correcto, la contraseña también, el usuario no está bloqueado y de
     * que esto no es un ataque por fuerza bruta.
     * @param string $user usuario
     * @param string $pass contraseña
     * @param int $userId se guardará aquí el id de usuario
     * @param string $cifNif se guardará aquí el cifNif de usuario
     * @param int $tipoUsuario se guardará el id de tipo de usuario
     * @param boolean $eligeTipo se guardará si el usuario puede elegir el tipo de usuarios que registra o no
     * @param string $email se guardará aquí el email del usuario
     * @param string $tipoPersona se guardará aquí el tipo de persona (fisica o juridica)
     * @param string $nombre se guardará el nombre del usuario
     * @param string $apellidos se guardarán los apellidos del usuario
     * @param bool $activado se guardará aquí si está activado o no
     * @throws BlockedUserException si el usuario está bloqueado y por tanto no puede realizar el login
     * @throws IncorrectPasswordException Si la contraseña introducida no es válida
     * @throws PossibleBruteForceAttackException si el usuario es sospechoso de estar sufriendo un ataque por fuerza
     * bruta
     * @throws UserNotExistentException si el usuario no existe
     */
    private function checkUser($user, $pass, &$userId, &$cifNif, &$tipoUsuario, &$eligeTipo, &$email, &$tipoPersona,
                               &$nombre, &$apellidos, &$activado) {
        // OJO, consulta repetida en getSession, por eficiencia
        $stmt = parent::elaborarConsulta(
            '`tnr_api_ent_usuarios` u '.
                'JOIN `tnr_ent_personas` p ON (u.`personaCifNif` = p.`cif_nif`) '.
                'JOIN `tnr_api_ent_user_types` t ON (u.`tipoUsuario` = t.`typeId`)',
            'u.`id`, p.`cif_nif`, u.`tipoUsuario`, t.`registroEligeTipo`, p.`email`, p.`tipoPersona`, p.`nombre_razonSocial`,'.
            'p .`apellidos`, u.`pass`, u.`salt`, u.`bloqueado`, '.
            'IF(u.`codigoDeActivacion` IS NULL, 1, 0) AS \'activado\'',
            '`userName`=?',
            null, null, null, 1
        );
        $stmt->bind_param('s', $user);
        $stmt->execute();
        $stmt->store_result();
        if($stmt->num_rows !== 1) {
            throw new UserNotExistentException();
        }
        $stmt->bind_result($userId, $cifNif, $tipoUsuario, $eligeTipo, $email, $tipoPersona, $nombre, $apellidos,
            $db_pass, $sal, $blocked, $activado);
        $stmt->fetch();
        $stmt->close();
        if($blocked) {
            throw new BlockedUserException(); // Bloqueado
        }
        if($this->checkBruteForce($userId) === true) {
            throw new PossibleBruteForceAttackException(); // Ataque por fuerza bruta?
        }
        $pass = hash('sha512', $pass.$sal); // Lo mezclamos con la sal :)
        if($db_pass !== $pass) {
            throw new IncorrectPasswordException();
        }
    }

    /**
     * Comprueba los intentos de login del usuario para comprobar si está
     * bloqueado por ser sospechoso de un ataque por fuerza bruta
     * @param $idUsuario
     * @return bool
     */
    private function checkBruteForce($idUsuario) {
        return false; // TODO: implementar comprobación de fuerza bruta
    }

    /**
     * Crea una nueva sesión para el usuario de id indicado y devuelve el sessionInfo correspondiente
     * @param int $userId id del usuario
     * @param string $cifNif cif o nif del usuario
     * @param string $tipoUsuario el tipo de usuario que crea la sesión
     * @param bool $eligeTipo Indica si el usuario puede elegir el tipo de los usuarios que registra
     * @param string $email email del usuario
     * @param string $tipoPersona tipo de persona que el usuario es
     * @param string $nombre Nombre del usuario
     * @param string $apellidos Apellidos del usuario
     * @param bool $activado true si está activado, false si no
     * @return SessionInfo
     */
    private function createSession($userId, $cifNif, $tipoUsuario, $eligeTipo, $email, $tipoPersona, $nombre, $apellidos, $activado) {
        $token = SessionManager::getInstance()->generarToken();
        $timestamp = date('Y-m-d H:i:s'); // Fecha y hora actual
        $ip = $_SERVER['REMOTE_ADDR'];
        // Eliminamos las sesiones muy antiguas
        $configMaxTimeName = API_CONFIG_SESSION_MAX_TIME;
        static::$link->query(
            "DELETE FROM tnr_api_ent_sessions
              WHERE UNIX_TIMESTAMP(TIMEDIFF('$timestamp', lastTimestamp)) > CONVERT((
                SELECT value FROM tnr_api_ent_apiConfigurations WHERE name='$configMaxTimeName'
              ), SIGNED)");
        // Guardamos sesión en la base de datos
        $stmt = parent::elaborarInsercion(
            '`tnr_api_ent_sessions`',
            ['`token`','`initialTimestamp`', '`lastTimestamp`', '`lastIp`', '`userId`']
        );
        $stmt->bind_param('ssssi', $token, $timestamp, $timestamp, $ip, $userId);
        $stmt->execute();
        $sessionInfo = new SessionInfo($stmt->insert_id, $userId, $cifNif, $email,
            $tipoPersona,$nombre,$apellidos,$activado,$token,$timestamp,$tipoUsuario, $eligeTipo);
        $stmt->close();

        return $sessionInfo;
    }

    public function closeSession() {
        $currentSession = SessionManager::getInstance()->check_sesion();
        $this->destroySessionById($currentSession->getId());
    }

    private function destroySessionById($sessionId) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        $stmt = parent::elaborarDelete(
            ['`tnr_api_ent_sessions`'],
            '`tnr_api_ent_sessions`',
            'sessionId=?'
        );
        $stmt->bind_param('i', $sessionId);
        $stmt->execute();
        $stmt->close();
        static::$link->commit();
    }

    /**
     * Obtiene la sesión correspondiente
     * @param int $sessionId Id de sesión
     * @param int $userId Id del usuario de la sesión
     * @param string $token Token de la sesión
     * @return SessionInfo El session info correspondiente, puede ser un NoSessionInfo si los datos no son coherentes
     */
    public function getSession($sessionId, $userId, $token) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        // OJO, consulta repetida en checkUser, por eficiencia
        $stmt = static::$link->prepare(
            'SELECT s.lastTimestamp, u.tipoUsuario, t.registroEligeTipo,
                      p.cif_nif, p.nombre_razonSocial, p.apellidos, p.email, p.tipoPersona,
                      IF(u.`codigoDeActivacion` IS NULL, 1, 0) AS \'activado\'
                    FROM tnr_api_ent_sessions s JOIN tnr_api_ent_usuarios u ON (s.userId = u.id)
                      JOIN tnr_ent_personas p ON (u.personaCifNif = p.cif_nif)
                      JOIN tnr_api_ent_user_types t ON (u.tipoUsuario = t.typeId)
                    WHERE s.sessionId=? AND s.token=? AND s.userId=?');
        $stmt->bind_param('isi', $sessionId, $token, $userId);
        $stmt->execute();
        $stmt->bind_result($ultimoAcceso, $userType, $eligeTipo, $nifCif, $nombre, $apellidos, $email,
            $tipoPersona, $activado);
        // Comprobamos que la sesión exista y el token y el usuario sean correctos
        if(!$stmt->fetch()) {
            return SessionManager::getInstance()->getNoSessionInfo();
        }
        $stmt->close();
        static::$link->commit();

        // Ha expirado?
        $timestamp = date('Y-m-d H:i:s'); // Fecha y hora actual
        // Obtenemos el tiempo máximo que puede durar una sesión, en segundos
        $maxInactiveTime =
            intval(DAOFactory::getInstance()->getApiConfigDAO()->getApiConfig(API_CONFIG_SESSION_MAX_TIME)['value']);
        if(strtotime($timestamp) - strtotime($ultimoAcceso) > $maxInactiveTime) {
            // Expirada!
            $this->destroySessionById($sessionId); // Ha sido comprobado con la base al principio de esta función
            return SessionManager::getInstance()->getNoSessionInfo(true);
        }

        // Actualizamos lastTimestamp
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        $stmt = static::$link->prepare('UPDATE tnr_api_ent_sessions SET lastTimestamp=? WHERE sessionId=?');
        $stmt->bind_param('si', $timestamp, $sessionId);
        $stmt->execute();
        $stmt->close();
        static::$link->commit();
        return new SessionInfo(
            $sessionId, $userId, $nifCif, $email, $tipoPersona, $nombre, $apellidos,
            $activado, $token, $ultimoAcceso, $userType, $eligeTipo);
    }

    /**
     * Realiza el registro de un nuevo usuario
     * @param string $user nombre de usuario a registrar
     * @param string $pass contraseña para el usuario
     * @param int $tipoUsuario tipo de usuario, si la sesión actual no es un usuario con permiso para
     * elegir tipo el tipo será ignorado y seteado al tipo por defecto (ambas opciones se encuentran
     * en la tabla de configuración de la api, en la base de datos)
     * @param string $personaCifNif el cif/nif de la persona a asociar al usuario
     * @return bool
     * @throws OpenSslRandomPseudoBytesNotStrong
     */
    public function registro($user, $pass, $tipoUsuario, $personaCifNif) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        $usuarioPorDefecto = intval($this->getApiConfigNoTransaction(API_CONFIG_DEFAULT_USER_TYPE)['value'], 10);
        if($usuarioPorDefecto != $tipoUsuario) {
            // Comprobamos si tienes permiso para elegir tipo
            $sesInfo = SessionManager::getInstance()->check_sesion();
            if(!$sesInfo->puedeElegirTipo()) {
                $tipoUsuario = $usuarioPorDefecto; // El tipo de usuario por defecto será el elegido
            }
        }
        $randomPseudoBytes = bin2hex(openssl_random_pseudo_bytes(32,  $strong));
        if( !$strong ) {
            throw new OpenSslRandomPseudoBytesNotStrong();
        }
        $sal = hash('sha512', uniqid(rand(), true).$randomPseudoBytes);
        $pass = hash('sha512', $pass.$sal); // Lo mezclamos con la sal :)
        // TODO: Generar codigo de activacion (no por ahora)
        $codigoActivacion = null;
        $fecha = date('Y-m-d');
        $stmt = static::$link->prepare(
            'INSERT INTO tnr_api_ent_usuarios(
              personaCifNif, pass, salt, userName, codigoDeActivacion, bloqueado, fechaDeAlta, tipoUsuario)
              VALUES (?,?,?,?,?,0,?,?)'
        );
        $stmt->bind_param('ssssssi', $personaCifNif, $pass, $sal, $user, $codigoActivacion, $fecha, $tipoUsuario);
        $stmt->execute();
        $stmt->close();
        static::$link->commit();
    }

    public function getApiConfig($configurationName) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        $config = $this->getApiConfigNoTransaction($configurationName);
        static::$link->commit();
        return $config;
    }

    private function getApiConfigNoTransaction($configurationName) {
        $stmt = static::$link->prepare(
            'SELECT `name`,`value`,`description` FROM tnr_api_ent_apiConfigurations WHERE name=? LIMIT 1'
        );
        $stmt->bind_param('s', $configurationName);
        $stmt->execute();
        $stmt->bind_result($name, $value, $description);

        $failed = (!$stmt->fetch());
        $stmt->close();
        if($failed) {
            throw new ConfigurationNotFoundException(
                'La configuración <'.$configurationName.'> no se encuentra en la base de datos');
        }
        return ['name'=>$name,'value'=>$value,'description'=>$description];
    }

    /**
     * Obtiene de la base las propiedades del TO indicado
     * @param string $nombreTo nombre del TO
     * @return PropiedadInterfaz[]
     */
    public function getPropiedadesTo($nombreTo) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        $stmt = static::$link->prepare(
            'SELECT label, tipo, esPk, sePuedeEscribir, descripcion, requerida, onlyOnSingle, `inOut`, showToTypeGroup
                    FROM tnr_api_ent_propiedadesTos WHERE transferObject=?'
        );
        $stmt->bind_param('s', $nombreTo);
        $stmt->execute();
        $stmt->bind_result($label, $tipo, $esPk, $sePuedeEscribir, $descripcion, $requerida,
            $onlyOnSingle, $inOut, $showToTypeGroup);
        $array = [];
        while($stmt->fetch()) {
            $array[] = ;
        }
        $stmt->close();
        static::$link->commit();
        return $array;
    }
}