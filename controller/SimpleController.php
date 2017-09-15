<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace controller;

use model\DAOFactory;
use model\exceptions\RequiredFieldException;
use model\Filtro;
use const model\IPARSEABLE_VERSION_CORTA;
use const model\IPARSEABLE_VERSION_TOTAL;
use model\PropiedadInterfaz;
use model\ExposedTO;
use model\simple_dao\ISimpleDAO;
use model\simple_dao\ISimpleFiltroParser;
use view\Outputter;

/**
 * Class SimpleController, crea un controlador simple que trabaja cómodamente
 * con un SimpleDAO. A la hora de añadir el url_pattern tener en cuenta que los
 * parámetros de la URl deben llevar identificadores de nombres que se correspondan
 * con el label de esa propiedad tal como se indique en la interfaz del ExposedTO.
 *
 * Este controlador permite de forma automática (sin sobreescribir nada más que los require de nivel):
 * - Peticiones GET:
 *      - Con el parámetro get 'interfaz' se obtiene la interfaz del TO, junto con las URL's 'maquilladas'
 *      - Con el PK completo devolverá el TO correspondiente
 *      - Con un PK incompleto devolverá la lista de todos los TO, pudiendo esta paginarse con los parámetros get
 *          'page' y 'size', y filtrarse indicando el nombre de una propiedad precedido por guión bajo (_). Por
 *          ejemplo: ?_idComunidad=2 filtrará según la propiedad con label idComunidad. Sólo funciona para propiedades
 *          de tipo (in/out), es decir, aquellas que tienen un setter no nulo.
 * - Peticiones POST:
 *      - Con el PK completo permite la inserción de un nuevo elemento
 *      - Con el PK requerido completo permite inserción múltiple de elementos
 *      - Sin PK completo permite la inserción de un nuevo elemento o múltiples elementos si estos contienen
 *          el PK en sus campos.
 * - Peticiones DELETE:
 *      - Con el PK completo permite la eliminación de un elemento
 *      - Sin el PK, pero con el parámetro get 'pks' conteniendo una lista con los pks en el orden en que
 *      aparecen en la interfaz del to, permite la eliminación de varios elementos.
 * - Peticiones PUT:
 *      - Con el PK completo permite actualizar el elemento concreto
 * - Las peticiones OPTIONS corresponderán a la funcionalidad anteriormente descrita.
 *
 * @see ISimpleDAO
 *
 * @package controller
 */
class SimpleController extends URLController {
    /** @var ISimpleDAO DAO con el que trabajará este SimpleController */
    private $dao;
    /** @var string La clase de ExposedTO que genera el DAO */
    private $claseTo;
    /** @var PropiedadInterfaz[] Guarda los campos PK indicados por el TO */
    private $pk;
    /** @var array Guarda las referencias a PK encontradas en los parámetros */
    private $referenciasPk;
    /** @var bool Indica si se han pasado todos los parametros requeridos del PK o no */
    private $todosLosParametrosRequeridos;
    /** @var PropiedadInterfaz[] una copia de las propiedades que devuelve el TO */
    private $propiedades;

    /**
     * SimpleController constructor.
     * @param array|string[] $params
     * @param array $filters
     * @param string $url
     * @param Outputter $outputter
     * @param ISimpleDAO $dao DAO con el que trabajará este Simple Controller
     */
    public function __construct($params, array $filters, $url, Outputter $outputter, ISimpleDAO $dao) {
        parent::__construct($params, $filters, $url, $outputter);

        $this->dao = $dao;
        $this->claseTo = $dao->getToClass();
        // Comprobamos que el TO suministrado por el dao es válido
        $this->checkValidToClass($this->claseTo);

        $this->referenciasPk = [];
        /** @noinspection PhpUndefinedMethodInspection */
        /** @var PropiedadInterfaz[] $propiedades */
        $this->propiedades = ($this->claseTo)::interfazPropiedades();

        $this->pk = [];
        $pkRequeridos = 0;
        foreach($this->propiedades as $propiedad) {
            if($propiedad->esPk()) {
                $this->pk[] = $propiedad;
                if($propiedad->esRequerida()) {
                    $pkRequeridos++;
                }
            }
        }

        // Hacemos referencias a los pk que se han enviado
        $requeridosPasados = 0;
        foreach ($this->pk as $propiedad) {
            if ($this->params[$propiedad->getLabel()]) {
                $this->referenciasPk[] = &$this->params[$propiedad->getLabel()];
                if ($propiedad->esRequerida()) $requeridosPasados++;
            }
        }
        $this->todosLosParametrosRequeridos = ($requeridosPasados == $pkRequeridos);
    }

    /**
     * Sobreescribir si se desea mostrar una vista bonita de las URL's del controlador
     * en la interfaz.
     * Por defecto devuelve los pattern que apunten a este controlador en URL_PATTERNS
     * un poco maquillados.
     * @return string[]
     */
    protected function getUrlsPointingToMe() {
        $me = get_class($this);
        $urls = [];
        foreach(DAOFactory::getInstance()->getApiConfigDAO()->getControllers() as $pattern=>$arr) {
            $controller = $arr['controller'];
            if($controller === $me) {
                $urls[] = ControllerFacade::maquillarUrlPattern($pattern);
            }
        }
        return $urls;
    }

    protected function getInterface($out=true, $in=true): array {
        /** @noinspection PhpUndefinedMethodInspection */
        return [
            'urls' => $this->getUrlsPointingToMe(),
            'interfaz '.(($this->claseTo)::simpleConstruction())->getName() =>
                (($this->claseTo)::simpleConstruction())->arrayInfoInterfaz($out?false:true, $in?false:true)];
    }

    public static function descripcionControlador(): string {
        return "Permite la creación, modificación, eliminación y consulta.";
    }

    private function seHaIndicadoPkCompleto(): bool {
        return count($this->referenciasPk) == count($this->pk);
    }

    public function get_impl() {
        $size = $this->filter('size', -1);
        $page = $this->filter('page',0);
        $filtros = $this->getFiltrosParaTos();
        if($this->seHaIndicadoPkCompleto()) {
            $listaTos = $this->dao->getTos($filtros,0,1);
            if(count($listaTos) > 0) {
                $this->outputter->output_parseable(Outputter::HTTP_OK, $listaTos[0]);
            } else {
                throw new ResourceNotFoundException(
                    'Couldn\'t find the resource with pk '.implode(',', $this->referenciasPk).
                    ' and the given filters');
            }
        } else {
            $listaTos = $this->dao->getTos($filtros, $page, $size, $paginacion);
            if ($size > 0) {
                $this->outputter->output_parseables(Outputter::HTTP_OK, $listaTos, $paginacion,
                    count($listaTos)>1?IPARSEABLE_VERSION_CORTA:IPARSEABLE_VERSION_TOTAL);
            } else {
                $this->outputter->output_parseables(Outputter::HTTP_OK, $listaTos, null,
                    count($listaTos)>1?IPARSEABLE_VERSION_CORTA:IPARSEABLE_VERSION_TOTAL);
            }
        }
    }

    private function getFiltrosParaTos() {
        $urlParams = $this->params; // obtenemos también los parámetros de la URL, porque podrían contener partes del PK
        $filtersParams = [];
        foreach($urlParams as $key=>$value) {
            $filtersParams['_'.$key] = $value; // Los añadimos con _ delante, porque luego se buscarán de esta forma
        }
        unset($urlParams);
        $filters = $this->filters();

        if( count(array_intersect_key($filtersParams, $filters)) > 0 ) {
            // Oops, repeated keys
            throw new RequestParsingException('Claves repetidas! ('.
                implode(', ', array_keys(array_intersect_key($filtersParams, $filters))).
                ')');
        }

        $filtersParams = array_merge(
            $filters,   // obtenemos todos los filtros por si contuviesen valores para filtrar campos
            $filtersParams);
        unset($filters);

        $filtroCompuestoNoTag = [];
        $filtrosCompuestos = [];
        foreach($filtersParams as $param=>$value) {
            if(substr($param, 0,1) === '_') {
                $param = substr($param, 1);
                // Con * podemos indicar un tag para formar varios filtros compuestos
                if(preg_match('/^(.+)\*/i', $param, $matches)) {
                    $tag = $matches[0];
                    $param = substr($param, strlen($tag));
                } else {
                    $tag = null;
                }
                $filtro = $this->filtroParaParam($this->claseTo, $param, $value);
                if($filtro) {
                    if($tag !== null) {
                        if (key_exists($tag, $filtrosCompuestos)) {
                            array_push($filtrosCompuestos[$tag], $filtro); // Añadimos a filtro compuesto
                        } else {
                            $filtrosCompuestos[$tag] = [$filtro]; // Nuevo filtro compuesto
                        }
                    } else {
                        $filtroCompuestoNoTag[] = $filtro; // Filtro sin tag
                    }
                }
            }
        }
        return array_merge([$filtroCompuestoNoTag], $filtrosCompuestos);
    }

    protected function post_impl(array $body) {
        $clase = $this->dao->getToClass();
        $this->checkValidToClass($clase);

        if( $this->notAssoc($body)) {
            // Si se ha especificado el PK completo, no se permite inserción múltiple
            if( $this->seHaIndicadoPkCompleto()) {
                throw new RequestParsingException(
                    'No se puede realizar una inserción múltiple con la URL de un objeto concreto (todos los '.
                    'parámetros de PK indicados)');
            }
            $prototipos = [];
            foreach($body as $item) {
                $prototipos[] = $this->creacionPrototipo($clase, $item);
            }
            $tos = $this->dao->createTos($prototipos);
            $this->outputter->output_parseables(Outputter::HTTP_CREATED, $tos);
        } else {
            $prototipo = $this->creacionPrototipo($clase, $body);
            $to = $this->dao->createTo($prototipo);
            $this->outputter->output_parseable(Outputter::HTTP_CREATED, $to);
        }
    }

    /**
     * @param string $clase
     * @param array $item
     * @return ExposedTO
     * @throws RequiredFieldException
     */
    private function creacionPrototipo(string $clase, array $item) {
        /** @var ExposedTO $prototipo */
        /** @noinspection PhpUndefinedMethodInspection */
        $prototipo = $clase::emptyConstruction(); // Sabemos que es válido por checkValidToClass
        // Hacemos set de las propiedades PK requeridas
        foreach($this->pk as $propiedad) {
            if($propiedad->esRequerida()) {
                if($this->params[$propiedad->getLabel()]) {
                    // Encontrada en la URL
                    $valor = $this->params[$propiedad->getLabel()];
                } elseif($item[$propiedad->getLabel()]) {
                    // Encontrada en el objeto
                    $valor = $item[$propiedad->getLabel()];
                } else {
                    throw new RequiredFieldException('No se pudo encontrar el valor para el PK requerido '.
                        $propiedad->getLabel());
                }
                $setter = $propiedad->getUpdate();
                call_user_func([$prototipo, $setter], $valor);
            }
        }
        // Recogemos el resto de actualizaciones
        $prototipo->fromAssocArray($item);
        return $prototipo;
    }

    private function notAssoc(array $array) {
        // Consideramos no asociativos aquellos
        // cuyas claves son todas numericas
        // Los arrays vacíos se considerarán asociativos
        if(count($array) < 1) return false;
        foreach(array_keys($array) as $key) {
            if(!is_numeric($key)) return false;
        }
        return true;
    }

    protected function del_impl() {
        if ($this->seHaIndicadoPkCompleto()) {
            $eliminados = $this->dao->deleteTo(...$this->referenciasPk);
        } elseif ($this->hasFilter('pks')) {
            // Recibimos un string del tipo pk11,pk12,pk21,pk22... y lo convertimos a un array
            // del tipo [[pk11,pk12],[pk21,pk22],...]
            $pks = explode(',', $this->filter('pks', ''));
            $pkN = count($this->pk);
            $currentPk = [];
            $pksBidimensional = [];

            if (count($pks) % $pkN != 0) {
                throw new RequestParsingException(
                    'Unable to parse pks, the number of pks should be multiple of ' . $pkN . ', but ' . count($pks) . ' given.');
            }

            for ($i = 0; $i < count($pks); $i++) {
                $currentPk[] = $pks[$i];
                if ($i % $pkN == $pkN - 1) {
                    $pksBidimensional[] = $currentPk;
                    $currentPk = [];
                }
            }
            $eliminados = $this->dao->deleteTos($pksBidimensional);
        } else {
            throw new MethodNotAuthorizedException('No se permite la elimnación filtrada ni general de TOs');
        }
        // LA ELIMINACIÓN FILTRADA SE HA ANULADO (el Wrapper tampoco es que la maneje especialmente bien)
        /*else {
            $filtros = $this->getFiltrosParaTos();
            if(count($filtros[0]) > 0) {
                $eliminados = $this->dao->filteredDeleteTos($filtros);
            } else {
                // No deseamos permitir la eliminación de todos los TOs desde la API
                throw new UnknownMethodException(
                    'Por seguridad, SimpleController no permite la eliminación de todos los TOs. '.
                    'Indique algún filtro válido, los pks o los valores de un pk concreto.', $this);
            }
        }*/

        if($eliminados > 0) {
            // Obtenemos lista completa
            $size = $this->filter('size', -1);
            $page = $this->filter('page',0);
            $listaTos = $this->dao->getTos(null, $page, $size, $paginacion);
            if ($size > 0) {
                $this->outputter->output_parseables(Outputter::HTTP_OK, $listaTos, $paginacion);
            } else {
                $this->outputter->output_parseables(Outputter::HTTP_OK, $listaTos);
            }
        } else {
            $this->outputter->output(Outputter::HTTP_NOT_MODIFIED, ['not_modified'=>'not_modified']);
        }
    }

    protected function put_impl(array $body) {
        if(!$this->seHaIndicadoPkCompleto()) {
            throw new UnknownMethodException('Se necesita '.implode(', ',$this->pk).' para actualizar', $this);
        }
        $to = $this->dao->getTo(...$this->referenciasPk); // Obtencion del TO

        if(!$to) {
            /** @noinspection PhpUndefinedMethodInspection */
            throw new ResourceNotFoundException(
                'No se ha encontrado '.($this->claseTo)::getName().
                ' con el pk <' . implode(',', $this->referenciasPk) . '>');
        }

        $to->fromAssocArray($body); // Actualizacion del TO
        $this->dao->saveTo($to); // Guardado

        // Obtenemos lo que hay en la base
        $to = $this->dao->getTo(...$this->referenciasPk);
        $this->outputter->output_parseable(Outputter::HTTP_OK, $to);
    }

    protected function options_impl(): array {
        if ($this->seHaIndicadoPkCompleto()) {
            return array("GET","PUT","DELETE","POST"); // Con pk
        } else {
            return $this->hasFilter('pks')?["GET","DELETE"]:["GET"]; // Sin pk
        }
    }

    private function checkValidToClass($clase) {
        if(!is_subclass_of($clase, 'model\ExposedTO')) {
            throw new \InvalidArgumentException(
                "ATENCIÓN: La clase indicada ($clase) no hereda de model\ExposedTO (obtenida de ".get_class($this->dao)."). ");
        }
        if(!method_exists($clase, 'emptyConstruction')) {
            throw new \InvalidArgumentException(
                "ATENCIÓN: La clase indicada ($clase) no contiene el método emptyConstruction, por lo tanto ".
                "no puede ser empleada en conjunto con SimpleController (obtenida de ".get_class($this->dao)."). ");
        }
    }

    private function filtroParaParam(string $claseTo, string $param, $valor, array $ruta=[]) {
        if(!is_subclass_of($claseTo, 'model\TO')) {
            return null;
        }
        $posicionPunto = strpos($param,'_'); // El punto se convierte a _ al pasar por $_GET parece ser
        if($posicionPunto !== false) {
            $label = substr($param, 0, $posicionPunto);
            $resto = substr($param, $posicionPunto+1);
            /** @noinspection PhpUndefinedMethodInspection */
            /** @var PropiedadInterfaz $prop */
            foreach($claseTo::interfazPropiedades() as $prop) {
                if($prop->getLabel() === $label) {
                    if(!$this->dao->propiedadEsFiltrable($ruta, $label))
                        throw new RequestParsingException(
                            "La propiedad '".
                            implode('', array_map(function($v){return $v.'.';}, $ruta)).$label.
                            "' no puede ser utilizada como filtro");
                    $ruta[] = $label;
                    $claseTo = $prop->getTipos()[0];
                    if(substr($claseTo, -2) === '[]') {
                        $claseTo = substr($claseTo, 0, -2);
                    }
                    return $this->filtroParaParam($claseTo, $resto, $valor, $ruta);
                }
            }
        } else {
            $comparacion = Filtro::CMP_IGUAL;
            // No tiene punto, comprobamos si tiene método de comparación especial
            if(preg_match('/!$/', $param)===1) {
                $comparacion = Filtro::CMP_NO_IGUAL;
                $param = substr($param, 0, -1);
            } elseif(preg_match('/^[^!].*~$/i', $param)===1) {
                $comparacion = Filtro::CMP_LIKE;
                $param = substr($param, 0, -1);
            } elseif(preg_match('/^!.*~$/i', $param)===1) {
                $comparacion = Filtro::CMP_NOT_LIKE;
                $param = substr($param, 1, -1);
            } elseif(preg_match('/^x-min@/i', $param)===1) {
                $comparacion = Filtro::CMP_MAYOR;
                $param = substr($param, 6);
            } elseif(preg_match('/^min@/', $param)===1) {
                $comparacion = Filtro::CMP_MAYOR_O_IGUAL;
                $param = substr($param, 4);
            } elseif(preg_match('/^x-max@/i', $param)===1) {
                $comparacion = Filtro::CMP_MENOR;
                $param = substr($param, 6);
            } elseif(preg_match('/^max@/', $param)===1) {
                $comparacion = Filtro::CMP_MENOR_O_IGUAL;
                $param = substr($param, 4);
            }

            /** @noinspection PhpUndefinedMethodInspection */
            /** @var PropiedadInterfaz $prop */
            foreach($claseTo::interfazPropiedades() as $prop) {
                if($prop->getLabel() === $param) {
                    $error = !$this->dao->propiedadEsFiltrable($ruta, $param); // Si no es filtrable tenemos un problema
                    if (in_array('boolean', $prop->getTipos())) {
                        // Tenemos que corregir los boolean, que son incorrectamente parseados
                        // de true y false
                        $valor = (
                        $valor === 'true' ?
                            true :
                            ($valor === 'false' ?
                                false : $valor)); // Solo convertimos esos valores
                    } elseif( is_subclass_of($prop->getTipos()[0],  'model\IFormatter') ) {
                        // Tiene un formatter
                        $tipo = $prop->getTipos()[0];
                        if ( is_subclass_of($tipo, 'model\simple_dao\ISimpleFiltroParser')) {
                            /** @var ISimpleFiltroParser $formatter */
                            $formatter = new $tipo();
                            $valor = $formatter->parsearValorParaFiltro($valor);
                        } else {
                            $error = true;
                        }
                    }
                    if($error)
                        throw new RequestParsingException(
                            "La propiedad '".
                            implode('', array_map(function($v){return $v.'.';}, $ruta)).$param.
                            "' no puede ser utilizada como filtro");
                    else
                        return new Filtro($ruta, $param, $comparacion, [$valor]);
                }
            }
        }
        return null;
    }
}