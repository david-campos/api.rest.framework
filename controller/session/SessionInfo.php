<?php
namespace controller\session;

class SessionInfo {
    public const NO_SESSION_TYPE_ID = -1;
    public const PHP_LABEL_SESSION_ID = 'session-id';
    public const PHP_LABEL_SESSION_TOKEN = 'session-token';
    public const PHP_LABEL_USER_ID = 'user-id';

    /** @var int|null id de la sesión */
    private $id;
    /** @var int|null id de usuario de la sesión */
    private $userId;
    /** @var string|null cif o nif del usuario de la sesión */
    private $userCifNif;
    /** @var string|null email del usuario de la sesión */
    private $userEmail;
    /** @var string|null indica si el usuario es una empresa o no */
    private $tipoPersona;
    /** @var bool|null indica si el usuario está activado o no */
    private $userActivado;
    /** @var string|null token de la sesión */
    private $token;
    /** @var \DateTime|null ultimo acceso del usuario*/
    private $ultimoAcceso;
    /** @var int Nivel del usuario conectado */
    private $sessionType;
    /** @var string|null Nombre del usuario */
    private $name;
    /** @var string|null Apellidos del usuario si los tiene */
    private $surname;
    /** @var boolean Indica si es una sesión logeada o no */
    private $logeada;
    /** @var boolean Indica si puede elegir el tipo de usuario que registra */
    private $puedeElegirTipo;
    /** @var boolean si esta es una sesión no logeada, indica si la anterior expiró y por eso estamos no logeados */
    private $expirada;

    /**
     * SessionInfo constructor.
     * @param int|null $id
     * @param int|null $userId
     * @param null|string $userCifNif
     * @param null|string $userEmail
     * @param string|null $tipoPersona
     * @param string|null $name
     * @param string|null $surname
     * @param bool|null $userActivado
     * @param null|string $token
     * @param \DateTime|string|null $ultimoAcceso
     * @param int $sessionType
     * @param bool $eligeTipo
     * @param bool $logeada
     * @param bool $anteriorExpirada si esta es una sesión no logeada, indica si la anterior expiró y por eso estamos no logeados
     */
    public function __construct($id, $userId, $userCifNif, $userEmail, $tipoPersona,
                                $name, $surname,
                                $userActivado, $token, $ultimoAcceso, int $sessionType, $eligeTipo,
                                $logeada=true, $anteriorExpirada=false) {
        $this->id = $id;
        $this->userId = $userId;
        $this->userCifNif = $userCifNif;
        $this->userEmail = $userEmail;
        $this->tipoPersona = $tipoPersona;
        $this->name = $name;
        $this->surname = $surname;
        $this->userActivado = ($userActivado?true:false);
        $this->token = $token;
        if($ultimoAcceso !== null && !$ultimoAcceso instanceof \DateTime) {
            $ultimoAcceso = new \DateTime($ultimoAcceso);
        }
        $this->ultimoAcceso = $ultimoAcceso;
        $this->sessionType = $sessionType;
        $this->puedeElegirTipo = $eligeTipo;
        $this->logeada = $logeada;
        $this->expirada = $anteriorExpirada;
    }


    /**
     * @return int|null
     */
    public function getId() {
        return $this->id;
    }

    /**
     * @return int|null
     */
    public function getUserId() {
        return $this->userId;
    }

    /**
     * @return null|string
     */
    public function getUserCifNif() {
        return $this->userCifNif;
    }

    /**
     * @return null|string
     */
    public function getUserEmail() {
        return $this->userEmail;
    }

    /**
     * @return bool|null
     */
    public function getUserActivado() {
        return $this->userActivado;
    }

    /**
     * @return null|string
     */
    public function getToken() {
        return $this->token;
    }

    /**
     * @return \DateTime|null
     */
    public function getUltimoAcceso() {
        return $this->ultimoAcceso;
    }

    /**
     * @return int
     */
    public function getSessionType(): int {
        return $this->sessionType;
    }

    /**
     * @return null|string
     */
    public function getTipoPersona() {
        return $this->tipoPersona;
    }

    /**
     * @return null|string
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return null|string
     */
    public function getSurname() {
        return $this->surname;
    }

    /**
     * @return bool
     */
    public function isLogeada(): bool {
        return $this->logeada;
    }

    /**
     * @return boolean
     */
    public function puedeElegirTipo(): bool {
        return $this->puedeElegirTipo;
    }

    /**
     * @return boolean
     */
    public function isExpirada(): bool {
        return $this->expirada;
    }

    /**
     * Método que comprueba si la sesión tiene el nivel requerido
     * @param mixed $requiredLevel nivel requerido
     * @return bool true si tiene el nivel requerido, false si no
     */
    public final function checkEnoughUserLevel($requiredLevel): bool {
        return SessionManager::getInstance()->checkEnoughLevel($this, $requiredLevel);
    }

    /**
     * Guarda el session info en la sesión de PHP
     */
    public function saveToPhpSession() {
        $_SESSION[self::PHP_LABEL_SESSION_ID] = $this->id;
        $_SESSION[self::PHP_LABEL_USER_ID] = $this->userId;
        $_SESSION[self::PHP_LABEL_SESSION_TOKEN] = $this->token;
    }

    /**
     * Comprueba si hay una sesión guardada en la sesión de PHP
     */
    public static function isInPhpSession() {
        return isset(
            $_SESSION[SessionInfo::PHP_LABEL_SESSION_ID],
            $_SESSION[SessionInfo::PHP_LABEL_USER_ID],
            $_SESSION[SessionInfo::PHP_LABEL_SESSION_TOKEN]);
    }
}

class NoSessionInfo extends SessionInfo {
    /**
     * NoSessionInfo constructor.
     * @param string $dummy_token El token de la sesión no logeada
     * @param string $lastTimestamp Ultimo acceso realizado
     * @param boolean $expired Indica si la no-sesión se debe a que la sesión anterior ha expirado o no
     */
    public function __construct($dummy_token, $lastTimestamp, $expired) {
        parent::__construct(null, null, null, null, null, null, null,
            false, $dummy_token, $lastTimestamp, SessionInfo::NO_SESSION_TYPE_ID, false, false, $expired);
    }
}