<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace controller;

use controller\session\SessionInfo;
use controller\session\SessionManager;
use view\Outputter;

/**
 * Clase abstracta de la que deben heredar todos los controladores de URL's.
 * Las clases que desciendan de esta deben implementar los métodos adecuados
 * para manejar en cada caso las solicitudes HTTP a las URL que se encargue de manejar.
 * @package controller
 */
abstract class URLController {
    /** @var string[] parámetros recogidos de la URL que ha producido la creación del controlador */
    protected $params;
    /** @var string URL que ha producido la creación del controlador */
    protected $url;
    /** @var Outputter Outputter a usar para imprimir el resultado */
    protected $outputter;
    /** @var SessionInfo Información de sesión */
    protected $sessionInfo;
    /** @var string[] Filters passed in the request URL */
    private $filters;
    /** @var array */
    private $requiredGroupGet;
    /** @var array */
    private $requiredGroupPost;
    /** @var array */
    private $requiredGroupDelete;
    /** @var array */
    private $requiredGroupPut;

    /**
     * URLController constructor.
     * @param string[] $params
     * @param array $filters
     * @param string $url
     * @param Outputter $outputter
     */
	public function __construct(array $params, array $filters, string $url, Outputter $outputter) {
		$this->params = $params;
		$this->filters = [];
        foreach($filters as $k=>$v) {
            $this->filters[$k] = ($v===''?null:$v);
        }

        // Iniciamos todos vacíos (usar setter)
        $this->requiredGroupGet =
        $this->requiredGroupPost =
        $this->requiredGroupDelete =
        $this->requiredGroupPut = [];

		$this->url = $url;
		$this->outputter = $outputter;
        $this->sessionInfo = SessionManager::getInstance()->check_sesion();
	}

    /**
     * Obtiene el grupo de tipos requeridos para GET
     * @return array
     */
    public function getRequiredGroupGet(): array {
        return $this->requiredGroupGet;
    }

    /**
     * Cambia el grupo de tipos requeridos para GET
     * @param array $requiredGroupGet
     */
    public function setRequiredGroupGet(array $requiredGroupGet) {
        $this->requiredGroupGet = $requiredGroupGet;
    }

    /**
     * Obtiene el grupo de tipos requeridos para POST
     * @return array
     */
    public function getRequiredGroupPost(): array {
        return $this->requiredGroupPost;
    }

    /**
     * Cambia el grupo de tipos requeridos para POST
     * @param array $requiredGroupPost
     */
    public function setRequiredGroupPost(array $requiredGroupPost) {
        $this->requiredGroupPost = $requiredGroupPost;
    }

    /**
     * Obtiene el grupo de tipos requeridos para DELETE
     * @return array
     */
    public function getRequiredGroupDelete(): array {
        return $this->requiredGroupDelete;
    }

    /**
     * Cambia el grupo de tipos requeridos para DELETE
     * @param array $requiredGroupDelete
     */
    public function setRequiredGroupDelete(array $requiredGroupDelete) {
        $this->requiredGroupDelete = $requiredGroupDelete;
    }

    /**
     * Obtiene el grupo de tipos requeridos para PUT
     * @return array
     */
    public function getRequiredGroupPut(): array {
        return $this->requiredGroupPut;
    }

    /**
     * Cambia el grupo de tipos requeridos para PUT
     * @param array $requiredGroupPut
     */
    public function setRequiredGroupPut(array $requiredGroupPut) {
        $this->requiredGroupPut = $requiredGroupPut;
    }

    /**
     * Función que devuelve el array que representa la interfaz del
     * URLController, puede utilizarse TO::arrayInfoInterfaz() para este propósito
     *
     * @see ExposedTO::arrayInfoInterfaz()
     *
     * @param bool $out indica si se desean los parametros de salida
     * @param bool $in indica si se desean los parametros de entrada
     * @return array Los campos y sus descripciones.
     */
	protected function getInterface($out=true, $in=true): array {
	    return ['interfaz '.get_class($this)=>'No disponible (sobrecargue getInterface() en el controlador)'];
    }

    /**
     * Obtiene el filter de nombre dado y devuelve un valor default si el filter no está en el array de filters
     * @param string $name nombre del filter
     * @param $default mixed valor a devolver por defecto
     * @return mixed|null
     */
	public function filter(string $name, $default=null) {
	    return isset($this->filters[$name])?$this->filters[$name]:$default;
    }

    /**
     * Obtiene una copia del array de filters
     * @return string[]
     */
    public function filters() {
	    return $this->filters;
    }

    /**
     * Indica si es posible obtener el filter de nombre dado y su valor no es nulo
     * @param string $name nombre del filter
     * @return bool true si es posible obtenerlo y su valor no es nulo
     */
    public function hasFilter(string $name): bool {
	    return isset($this->filters[$name])&&($this->filters[$name]!==null);
    }

    /**
     * Método que maneja las peticiones GET, comprobando el nivel y delegando en get_impl,
     * por seguridad no se debe sobreescribir este método, las clases hijas deben
     * implementar get_impl
     */
	final public function get() {
        //var_dump($this->sessionInfo);
	    if( $this->sessionInfo->checkEnoughUserLevel($this->requiredGroupGet) ) {
	        if(key_exists('interfaz', $this->filters)) {
	            $interfaz = $this->filter('interfaz', null);
	            $in = $interfaz !== 'out';
	            $out = $interfaz !== 'in';
	            $this->outputter->output(Outputter::HTTP_OK, $this->getInterface($out, $in));
            } else {
	            $this->get_impl();
            }
        } else {
	        throw new MethodNotAuthorizedException('No tiene permiso para realizar una petición GET a este controlador'.
                'PERMISOS: '.implode(', ', $this->requiredGroupGet));
        }
    }

    /**
     * Método que maneja las peticiones POST, comprobando el nivel y delegando en post_impl,
     * por seguridad no se debe sobreescribir este método, las clases hijas deben
     * implementar post_impl
     * @param array $body Cuerpo de la petición ya parseado a array asociativo
     * @throws MethodNotAuthorizedException
     */
    final public function post(array $body) {
        if( $this->sessionInfo->checkEnoughUserLevel($this->requiredGroupPost) ) {
            $this->post_impl($body);
        } else {
            throw new MethodNotAuthorizedException('No tiene permiso para realizar una petición POST a este controlador');
        }
    }

    /**
     * Método que maneja las peticiones DELETE, comprobando el nivel y delegando en del_impl,
     * por seguridad no se debe sobreescribir este método, las clases hijas deben
     * implementar del_impl
     */
    final public function del() {
        if( $this->sessionInfo->checkEnoughUserLevel($this->requiredGroupDelete) ) {
            $this->del_impl();
        } else {
            throw new MethodNotAuthorizedException('No tiene permiso para realizar una petición DELETE a este controlador');
        }
    }

    /**
     * Método que maneja las peticiones get, comprobando el nivel y delegando en get_impl,
     * por seguridad no se debe sobreescribir este método, las clases hijas deben
     * implementar get_impl
     * @param array $body Cuerpo de la petición ya parseado a array asociativo
     * @throws MethodNotAuthorizedException
     */
    final public function put(array $body) {
        if( $this->sessionInfo->checkEnoughUserLevel($this->requiredGroupPut) ) {
            $this->put_impl($body);
        } else {
            throw new MethodNotAuthorizedException('No tiene permiso para realizar una petición PUT a este controlador');
        }
    }

    /**
     * Método que deben implementar las clases hijas para manejar las solicitudes GET a las URL que se le asignen.
     * Usar para consultas.
     * @return void
     */
	abstract protected function get_impl();

    /**
     * Método que deben implementar las clases hijas para manejar las solicitudes POST a las URL que se le asignen.
     * Usar para inserciones.
     * @param array $body Cuerpo de la petición ya parseado a array asociativo
     * @return void
     */
	abstract protected function post_impl(array $body);

    /**
     * Método que deben implementar las clases hijas para manejar las solicitudes DELETE a las URL que se le asignen.
     * Usar para borrados.
     * @return void
     */
    abstract protected function del_impl();

    /**
     * Método que deben implementar las clases hijas para manejar las solicitudes PUT a las URL que se le asignen.
     * Usar para actualizaciones.
     * @param array $body Cuerpo de la petición ya parseado a array asociativo
     * @return void
     */
    abstract protected function put_impl(array $body);

    /**
     * Método que la fachada del controlador llamará ante una consulta options.
     * Este método obtiene los métodos http aceptados y coloca la cabecera
     * 'Allow' apropiada
     */
    final public function options() {
        $options = $this->options_impl();
        $allowed = [];
        foreach($options as $option) {
            $option = strtoupper($option);

            $method = '';
            if($option === 'GET')
                $method = 'Get';
            elseif($option === 'POST')
                $method = 'Post';
            elseif($option === 'DELETE')
                $method = 'Del';
            elseif($option === 'PUT')
                $method = 'Put';

            $f = "requiredLevel$method";
            // Check if the method exists and if the user has the required level
            if (method_exists($this, $f) &&
                $this->sessionInfo->checkEnoughUserLevel(call_user_func(array($this, $f)))) {
                $allowed[] = strtoupper($option);
            }
        }
        header('Allow: '.implode(', ', $allowed));
    }

    /**
     * Respuesta a la consulta http con método OPTIONS, debe devolver un array de los métodos
     * aceptados para la URL consultada. No hay necesidad de filtrar por nivel pues este
     * método se encuentra decorado/enmascarado por self::options(), que ya comprueba el nivel
     * antes de elaborar la respuesta. Este método debe devolver el array y no escribir nada.
     * @return string[]
     */
    abstract protected function options_impl(): array;
}