<?php

namespace model\formatters;

use model\IFormatter;
use model\IParseable;
use const model\IPARSEABLE_VERSION_TOTAL;

class ParseableFormatter implements IFormatter {
    /** @var string La clase de parseable a manejar por el formatter */
    private $parseableClass;

    /**
     * ParseableFormatter constructor.
     * @param string $parseableClass
     */
    public function __construct($parseableClass) {
        if(!is_subclass_of($parseableClass, 'model\IParseable')) {
            throw new \InvalidArgumentException("La clase de parseable $parseableClass no extiende de IParseable");
        }
        $this->parseableClass = $parseableClass;
    }


    /**
     * Formatea el valor indicado, este método será llamado por el TO para formatear el output
     * @param $value IParseable
     * @param int $version Indica la versión a imprimir
     * @return array
     */
    public function format($value, $version=IPARSEABLE_VERSION_TOTAL) {
        if($value===null) return null;

        if(is_a($value, $this->parseableClass)) {
            return $value->toAssocArray($version);
        } else {
            throw new \InvalidArgumentException(
                "Se esperaba '$this->parseableClass', pero se recibió '".get_class($value)."'");
        }
    }

    /**
     * Parsea el valor indicado, este método será llamado por el TO para formatear el input
     * @param $value array
     * @return IParseable
     */
    public function parse($value) {
        if($value===null) return null;

        /** @noinspection PhpUndefinedMethodInspection */
        /** @var IParseable $parseable */
        $parseable = $this->parseableClass::emptyConstruction();
        $parseable->fromAssocArray($value);
        return $parseable;
    }
}