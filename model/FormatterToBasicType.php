<?php

namespace model;
use controller\RequestParsingException;

/**
 * Clase de la que pueden extender formatters para el TO para poder formatear ciertos
 * elementos de salida a un determinado formato, devolviendo un tipo básico y funcionando
 * como tipos básicos para la interfaz.
 * @package model
 */
abstract class FormatterToBasicType implements IFormatter {
    public const TYPES = ['string','boolean','integer','double'];

    /** @var string El tipo basico formateado */
    private $outputType;
    /** @var string Descripción textual del formato devuelto (para la interfaz) */
    private $formatDescription;

    /**
     * FormatedBasicType constructor.
     * @param string $outputType El tipo básico formateado
     * @param string $formatDescription Descripción textual muy breve del formato devuelto (para la interfaz).
     * Si la descripción mide más de 40 carácteres será cortada.
     */
    public function __construct(string $outputType, string $formatDescription) {
        if(in_array($outputType, self::TYPES)) {
            $this->outputType = $outputType;
            $this->formatDescription = substr($formatDescription, 0, 40);
        } else {
            throw new \InvalidArgumentException('Error en la creación del tipo básico '.get_class($this).', '.
                'el tipo básico indicado no es un tipo válido. Use la constante de clase TYPES.');
        }
    }

    /**
     * Obtiene el tipo básico al que da formato el FormattedBasicType en cuestión, este es el tipo
     * que se imprimirá en la interfaz. El que saldrá como resultado de format y entrará a parse para
     * ser parseado.
     * @return string
     */
    public final function getBasicType(): string {
        return $this->outputType;
    }

    /**
     * @return string
     */
    public function getFormatDescription(): string {
        return $this->formatDescription;
    }

    /**
     * Formatea el valor indicado, este método será llamado por el TO para formatear el output
     * @param int $version Indica la versión a imprimir
     * @param $value
     * @return mixed
     */
    public final function format($value, $version=IPARSEABLE_VERSION_TOTAL) {
        $result = $this->formatValue($value); // La version nos da igual, solo hay una versión de los tipos básicos
        if($result !== null && gettype($result) !== $this->outputType) {
            throw new \InvalidArgumentException(
                get_class($this)."::formatValue devolvió ".gettype($result).", se esperaba $this->outputType.");
        }
        return $result;
    }

    /**
     * Parsea el valor indicado, este método será llamado por el TO para formatear el input
     * @param $value mixed Tendrá el tipo indicado en el constructor
     * @return mixed
     * @throws RequestParsingException Si el tipo recibido no coincide con el de Output de este Formatter
     */
    public final function parse($value) {
        if($value !== null && gettype($value) !== $this->outputType) {
            throw new \InvalidArgumentException(
                get_class($this)."::formatValue devolvió ".gettype($value).", se esperaba $this->outputType.");
        }
        return $this->parseValue($value);
    }

    /**
     * Implementar para formatear los valores de los TO al tipo básico indicado
     * @param $value mixed Valor para salida, tener en cuenta que podría ser de cualquier tipo.
     * Si se desea, realizar la comprobación de clase/tipo manualmente.
     * @return mixed Debe devolver un valor del tipo devuelto indicado en __construct (coincidente
     * con self::getBasicOutputType())
     */
    protected abstract function formatValue($value);

    /**
     * Implementar para parsear los valores recibidos del tipo básico indicado
     * @param $value mixed Valor de entrada, será del tipo indicado en __construct (coincidente
     * con self::getBasicOutputType())
     * @return mixed Valor interno para el TO, puede ser cualquier tipo
     */
    protected abstract function parseValue($value);
}