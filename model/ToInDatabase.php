<?php

namespace model;

abstract class ToInDatabase extends ExposedTO {
    /** @var array valores para las propiedades con setter del TO, indexado por label */
    private $valoresPropiedades;
    /** @var string[] labels de las propiedades indexados por sus setters */
    private $setters;
    /** @var string[] labels de las propiedades indexados por sus getters */
    private $getters;

    /**
     * ToInDatabase constructor.
     */
    public function __construct() {
        $props = static::interfazPropiedades();
        $this->valoresPropiedades = [];
        $this->setters = [];
        $this->getters = [];
        foreach($props as $prop) {
            $this->valoresPropiedades[$prop->getLabel()] = null; // Iniciamos a null
            if(!$prop->soloLectura()) {
                $this->setters[$prop->getUpdate()] = $prop->getLabel();
            }
            if(!$prop->soloEscritura()) {
                $this->getters[$prop->getShow()] = $prop->getLabel();
            }
        }
    }

    public function __call($name, $arguments) {
        if(key_exists($name, $this->setters)) {
            $this->valoresPropiedades[$this->setters[$name]] = $arguments[0];
            return null;
        } else if(key_exists($name, $this->getters)) {
            return $this->valoresPropiedades[$this->getters[$name]];
        } else {
            throw new \BadMethodCallException("No existe método con el nombre <$name>");
        }
    }

    /**
     * Las clases hijas deben implementar este método para indicar la interfaz que ofrecen de cara
     * a la API.
     * @return PropiedadInterfaz[]
     */
    protected static function generarInterfazPropiedades() {
        return DAOFactory::getInstance()->getApiConfigDAO()->getPropiedadesTo(static::getNombreEnLaBase());
    }

    protected static abstract function getNombreEnLaBase(): string;

    public static final function getName(): string {
        return static::getNombreEnLaBase();
    }

    static public function emptyConstruction() {
        parent::emptyConstruction();
    }


    /**
     * Las clases hijas deben implementar este método para indicar los links que desean exponer.
     * simplemente devolver un array del tipo "nombreDelLink" => "link".
     * @return array
     */
    public function interfazLinks(): array {
        // TODO: Implement interfazLinks() method.
    }
}