<?php

namespace model\formatters;

use controller\RequestParsingException;
use model\IFormatter;
use model\IParseable;
use const model\IPARSEABLE_VERSION_TOTAL;

class ParseableArrayFormatter implements IFormatter {
    /** @var  ParseableFormatter */
    private $parseableFormatter;
    /** @var string La clase de parseable a manejar por el formatter */
    private $parseableClass;

    /**
     * ParseableFormatter constructor.
     * @param string $parseableClassArray El tipo de parseable con [] al final
     */
    public function __construct($parseableClassArray) {
        $this->parseableClass = substr($parseableClassArray, 0, -2);
        $this->parseableFormatter = new ParseableFormatter($this->parseableClass);
    }


    /**
     * Formatea el valor indicado, este método será llamado por el TO para formatear el output
     * @param $value IParseable[]
     * @param int $version Indica la versión a imprimir
     * @return array
     * @throws RequestParsingException
     */
    public function format($value, $version = IPARSEABLE_VERSION_TOTAL) {
        if($value===null) return null;

        if(gettype($value) !== 'array') {
            throw new \InvalidArgumentException("Se esperaba un array, pero se obtuvo un ".gettype($value));
        }
        return array_map(
            function(IParseable $p) use ($version) {
                return $this->parseableFormatter->format($p, $version);
            }, $value);
    }

    /**
     * Parsea el valor indicado, este método será llamado por el TO para formatear el input
     * @param $value array
     * @return IParseable[]
     * @throws RequestParsingException
     */
    public function parse($value) {
        if($value===null) return null;

        if(gettype($value) !== 'array') {
            throw new RequestParsingException("Se esperaba un array, pero se obtuvo un ".gettype($value));
        }
        return array_map(function($subValue){
            return $this->parseableFormatter->parse($subValue);
        }, $value);
    }
}