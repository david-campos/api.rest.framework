<?php

namespace model;


use controller\RequestParsingException;
use controller\session\SessionManager;
use model\exceptions\RequiredPropertyException;
use model\formatters\DummyFormatter;
use model\formatters\ParseableArrayFormatter;
use model\formatters\ParseableFormatter;

/**
 * Los TransferObjects, expuestos o no, deben extender esta clase
 * Class TO
 * @package model
 */
abstract class TO implements IParseable{
    /** @var PropiedadInterfaz[][] array donde se guardarán las interfaces de cada TO que se vayan generando */
    private static $interfaces = [];

    /**
     * Función que devuelve un array asociativo con las propiedades del Transfer Object
     * al resultado puede variar de un TO a otro.
     * @param int $version Versión que se espera recibir
     * @return array
     */
    function toAssocArray(int $version=IPARSEABLE_VERSION_TOTAL): array {
        $array = [];
        $propiedades = $this->interfazPropiedades();
        $session = SessionManager::getInstance()->check_sesion();
        foreach($propiedades as $propiedad) {
            // Las que no se pueden leer se saltan
            if($propiedad->soloEscritura())
                continue;

            $valor =  call_user_func(array($this, $propiedad->getShow()));
            if( $session->checkEnoughUserLevel($propiedad->showTo()) &&
                // Es una propiedad requerida, o bien su valor es no nulo
                ($propiedad->esRequerida() || $valor !== null) &&
                // No se muestra en listas o la version actual no es la corta
                !($propiedad->isOnlyOnSingle() && $version == IPARSEABLE_VERSION_CORTA)) {

                $array[$propiedad->getLabel()] =
                    $this
                        ->formatterParaTipos($propiedad->getTipos())
                        ->format($valor);
            }
        }
        return $array;
    }

    /**
     * Función que actualiza el TO a partir de un array asociativo, empleando
     * la interfaz indicada por el método interfazPropiedades()
     * @param array $array
     * @throws RequestParsingException si uno de los parseable no implementa IParseable
     * @throws RequiredPropertyException si el array no contiene alguna propiedad requerida
     */
    final function fromAssocArray(array $array) {
        $propiedades = $this->interfazPropiedades();
        $session = SessionManager::getInstance()->check_sesion();
        foreach($propiedades as $propiedad) {
            $updateMethod = $propiedad->getUpdate();
            // Sólo las que tengan escritura y tengas permiso para ver
            if($propiedad->soloLectura() || !$session->checkEnoughUserLevel($propiedad->showTo())) {
                continue;
            }

            // Comprobamos si existe
            if(array_key_exists($propiedad->getLabel(), $array)) {
                $valor = $array[$propiedad->getLabel()];
                if(!$propiedad->admiteNull() && $valor===null) {
                    throw new RequestParsingException(
                        "La propiedad ".$propiedad->getLabel()." no admite valores nulos");
                }
                $tipos = $propiedad->getTipos();
                try {
                    $valor = $this
                        ->formatterParaTipos($tipos)
                        ->parse($valor);
                } catch(RequestParsingException $rpE) {
                    throw new RequestParsingException(
                        "Error parseando ".$propiedad->getLabel().": ".$rpE->getMessage());
                }
                call_user_func([$this, $updateMethod], $valor);
            } elseif($propiedad->esRequerida()) {
                // No existe y es requerida, qué horror! :O
                throw new RequiredPropertyException($propiedad);
            }
        }
    }

    /**
     * Nombre del TO, para debug y demás
     * @return string
     */
    public static function getName(): string {
        return get_called_class();
    }

    /**
     * Devolverá un array con información sobre la interfaz de este TO
     * @param bool $onlyIn si true, se devuelven solo los parametros que tengan posibilidad de entrada
     * @param bool $wrappedInOnlyOutput si true, se omite devolver información de si es de entrada/salida
     * @return array
     */
    public static final function arrayInfoInterfaz(bool $onlyIn=false, bool $wrappedInOnlyOutput=false): array {
        $result=[];
        $session = SessionManager::getInstance()->check_sesion();
        foreach(static::interfazPropiedades() as $prop) {
            // Ocultar las que no tienes nivel para ver
            if(!$session->checkEnoughUserLevel($prop->showTo()))
                continue;
            // Continuar
            if(     // No es solo los de entrada y sucede que no tiene entrada
                    !($onlyIn && $prop->soloLectura()) &&
                    // No es solo output y sucede que no tiene output
                    !($wrappedInOnlyOutput && $prop->soloEscritura()) )
                $result[$prop->getLabel()] = $prop->toArray($onlyIn, $wrappedInOnlyOutput);
        }
        return $result;
    }

    /**
     * Método que permite cosntruír el TO sin ningún tipo de parámetro.
     * @return ExposedTO El TO construído con valores por defecto.
     * @throws \Exception
     */
    static public function emptyConstruction() {
        throw new \Exception('emptyConstruction not implemented in'.get_called_class());
    }

    /**
     *  Este método será llamado por toAssocArray y por fromAssocArray para realizar
     * el GET o el UPDATE de los TO's, así como por otras clases,
     * facilitando el trabajo de los controladores y la implementación de nuevos TOs.
     * @return PropiedadInterfaz[]
     */
    static final public function interfazPropiedades() {
        if(self::$interfaces[get_called_class()] === null) {
            self::$interfaces[get_called_class()] = static::generarInterfazPropiedades();
            foreach(self::$interfaces[get_called_class()] as $prop) {
                if(!method_exists(get_called_class(), $prop->getShow())) {
                    throw new \InvalidArgumentException(
                        'La clase '.get_called_class().' especifica para la propiedad '.$prop->getLabel().
                        ' el getter \''.$prop->getShow().'\', pero este método no existe en la clase');
                }
                if($prop->getUpdate() && !method_exists(get_called_class(), $prop->getUpdate())) {
                    throw new \InvalidArgumentException(
                        'La clase '.get_called_class().' especifica para la propiedad '.$prop->getLabel().
                        ' el setter \''.$prop->getUpdate().'\', pero este método no existe en la clase');
                }

            }
        }
        return self::$interfaces[get_called_class()];
    }

    /**
     * Las clases hijas deben implementar este método para indicar la interfaz que ofrecen de cara
     * a la API.
     * @return PropiedadInterfaz[]
     */
    abstract protected static function generarInterfazPropiedades();

    /**
     * Encuentra el formatter para los tipos indicado
     * @param string[] $tipos
     * @return IFormatter
     */
    private function formatterParaTipos($tipos) {
        $tipo = $tipos[0];
        if (substr($tipo, -2) === '[]' &&
            is_subclass_of(substr($tipo, 0, -2), 'model\IParseable')) {
            $formatter = new ParseableArrayFormatter($tipo);
        } elseif (is_subclass_of($tipo, 'model\IParseable')) {
            $formatter = new ParseableFormatter($tipo);
        } elseif (is_subclass_of($tipo, 'model\IFormatter')) {
            /** @var IFormatter $formatter */
            $formatter = new $tipo();
        } else {
            $formatter = new DummyFormatter($tipos);
        }
        return $formatter;
    }
}