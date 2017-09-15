<?php
/**
 * Archivo con el código referente a la fachada del controlador y sus excepciones.
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace controller;

use model\DAOFactory;
use view\JsonInputter;
use view\JsonOutputter;
use view\Outputter;

/**
 * Class ControllerFacade, conforma un patrón Facade para la parte del controlador,
 * esta clase es la interfaz pública del controlador hacia el exterior.
 * @package controller
 */
final class ControllerFacade {
    /** @var  Outputter The outputter to print the output */
    protected $outputter;
    /** @var JsonInputter The inputter to parse the body of the request */
    protected $inputter;

    /**
     * ControllerFacade constructor.
     */
    public function __construct() {
        $this->outputter = new JsonOutputter();
        $this->inputter = new JsonInputter();
    }

    /**
     * Procesa la petición HTTP actual, empleando las variables de $_SERVER
     * @return void
     * @throws UnknownMethodException si no se puede manejar el método HTTP empleado
     */
	public function processRequest() {
	    try {
	        if(isset($_GET['urls'])) {
	            $this->outputter->output(Outputter::HTTP_OK, $this->listaDeUrls());
	            return;
            }

            $request_uri = urldecode(explode('?', $_SERVER['REQUEST_URI'], 2)[0]);
            $controller = $this->matchUriForController($request_uri);

            switch ($_SERVER['REQUEST_METHOD']) {
                case 'GET': $controller->get(); return;
                case 'POST': $controller->post($this->inputter->parse(file_get_contents('php://input'))); return;
                case 'DELETE': $controller->del(); return;
                case 'PUT': $controller->put($this->inputter->parse(file_get_contents('php://input'))); return;
                case 'OPTIONS': $controller->options(); return;
                default:
                    throw new UnknownMethodException('Unknown method '.$_SERVER['REQUEST_METHOD'], $controller);
            }
        } catch (PrintableSafeException $exception) {
	        $this->outputter->error_output($exception);
        }
	}

    /**
     * Devuelve un controlador adecuado para la url indicada.
     * @param string $url la url a manejar por el controlador devuelto
     * @return URLController el controlador adecuado para la url dada
     * @throws InvalidControllerException en caso de que el controlador obtenido no sea instancia de URLController
     * @throws UnknownControllerException en caso de que no se encuentre ningún controlador
     *  para la URL indicada
     */
	private function matchUriForController(string $url): URLController {
	    $dao = DAOFactory::getInstance()->getApiConfigDAO();
		$list = $dao->getControllers();
	    foreach($list as $pattern => $arr) {
	        $controllerClass = $arr['controller'];
            if (preg_match($pattern, $url, $matches)) {
                $params = $matches;
                array_splice($params, 0, 1);

                $controllerSimpleDao = $arr['dao'];
                if($controllerSimpleDao) {
                    $daoInterno = DAOFactory::getInstance()->getSimpleDaoForTo($controllerSimpleDao);
                    $controller = new SimpleController(
                        $params, $_GET, $url, $this->outputter,
                        $daoInterno);
                } else {
                    if (!is_subclass_of($controllerClass, 'controller\URLController')) {
                        throw new InvalidControllerException('El controlador ' . $controllerClass . ' no es válido.');
                    }
                    $controller = new $controllerClass($params, $_GET, $url, $this->outputter);
                }

                $dao->setRequiredLevelsOnController($controller, $controllerClass);
                return $controller;
            }
        }
		throw new UnknownControllerException("Not suitable controller for url: $url");
	}

    /**
     * Función que maquilla un URL pattern para que se vea más bonito
     * @param string $pattern El pattern a maquillar
     * @return string El pattern maquillado
     */
    public static final function maquillarUrlPattern($pattern) {
        $pattern = substr($pattern, strpos($pattern, '/')+1);
        $pattern = substr($pattern, 0, strrpos($pattern, '/'));
        $url = preg_replace(
            '/\(\?<([^>]*)>[^\)]*\)/',':$1', //Reemplaza los ids por :nombre
            preg_replace(
                '/([^\\\\])?\:/', '$1',
                $pattern));
        // Reemplaza lo opcional por [opcion]
        do {
            $url =  preg_replace(
                '/\(((?<pr>(?:[^()]|\((?&pr)\))*))\)\?/', '[$1]',
                $url, -1,
                $count);
        } while($count > 0);
        return
            preg_replace(
                '/([^\\\\])?\*/', '[$1]',
            preg_replace(
                '/([^\\\\])?\$/', '$1',
                preg_replace(
                    '/([^\\\\])?\^/', '$1',
                    str_replace(
                        '\/','/',
                        preg_replace(
                            '/([^\\\\])?\+/', '$1',
                                preg_replace(
                                    '/([^\\\\])?\)/', '$1',
                                    preg_replace(
                                        '/([^\\\\])?\(/', '$1',
                                        preg_replace(
                                            '/([^\\\\])?\?/', '$1',
                                            $url
                                        ))))))));
    }

    /**
     * Devuelve una lista de las URL's que se reconocen en la API
     * @return array
     */
    private function listaDeUrls() {
        $lista = [];
        $controllers = DAOFactory::getInstance()->getApiConfigDAO()->getControllers($descripciones);
        foreach($controllers as $pattern=>$arr) {
            $controller = $arr['controller'];
            $descripcion = ($descripciones[$controller]?$descripciones[$controller]:'Descripción inexistente');
            $lista[self::maquillarUrlPattern($pattern)] = $descripcion;
        }
        return $lista;
    }
}