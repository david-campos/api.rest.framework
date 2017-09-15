<?php

namespace model\formatters;

use controller\RequestParsingException;
use model\IFormatter;
use const model\IPARSEABLE_VERSION_TOTAL;

/**
 * Recibe un valor basico y lo devuelve tal cual, únicamente checkea los tipos.
 * @package model\formatters
 */
class DummyFormatter implements IFormatter {
    /** @var string[] tipos aceptados por el DummyFormatter */
    private $tiposAceptados;

    /**
     * DummyFormatter constructor.
     * @param string[] $tiposAceptados
     */
    public function __construct(array $tiposAceptados) {
        $this->tiposAceptados = $tiposAceptados;
        $this->tiposAceptados[] = 'NULL'; // Los nulos se controlan en TO, no aquí
    }

    /**
     * Formatea el valor indicado, este método será llamado por el TO para formatear el output
     * @param $value mixed
     * @param int $version Indica la versión a imprimir
     * @return mixed
     */
    public function format($value, $version = IPARSEABLE_VERSION_TOTAL) {
        $tipo = gettype($value)==='object'?get_class($value):gettype($value);
        if ( !in_array($tipo, $this->tiposAceptados) ) {
            throw new \InvalidArgumentException(
                "El valor a formatear no se corresponde a ninguno de los tipos esperados. ".
                "Se esperaban [".implode(', ', $this->tiposAceptados)."], se recibió ".$tipo);
        }
        return $value;
    }

    /**
     * Parsea el valor indicado, este método será llamado por el TO para formatear el input
     * @param $value mixed
     * @return mixed
     * @throws RequestParsingException
     */
    public function parse($value) {
        $tipo = gettype($value)==='object'?get_class($value):gettype($value);
        if ( !in_array($tipo, $this->tiposAceptados) ) {
            throw new RequestParsingException(
                "El valor no se corresponde a ninguno de los tipos esperados. ".
                "Se esperaban [".implode(', ', $this->tiposAceptados)."], se recibió ".$tipo);
        }
        return $value;
    }
}