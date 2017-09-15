<?php

namespace model\formatters;


use model\FormatterToBasicType;
use model\IMysqliSimpleParser;
use model\simple_dao\ISimpleFiltroParser;

class DateTimeFormatter extends FormatterToBasicType implements IMysqliSimpleParser, ISimpleFiltroParser {
    /** @var string Formato de salida */
    private $formatoExterno;
    /** @var string Formato de mysqli */
    private $formatoMysqli;

    /**
     * DateTimeFormatter constructor.
     *
     * @see DateTime::createFromFormat()
     *
     * @param string $descripcionFormato Descripción del formato
     * @param string $formatoExterno Formato que se mostrará de cara a la interfaz (al igual que lo usa DateTime)
     * @param string $formatoMysqli Formato que utiliza mysqli para guardarlo (al igual que lo usa DateTime)
     */
    public function __construct($descripcionFormato, $formatoExterno, $formatoMysqli) {
        parent::__construct(
            'string', $descripcionFormato);
        $this->formatoExterno = $formatoExterno;
        $this->formatoMysqli = $formatoMysqli;
    }


    /**
     * Implementar para formatear los valores de los TO al tipo básico indicado
     * @param $value mixed Valor para salida, tener en cuenta que podría ser de cualquier tipo.
     * Si se desea, realizar la comprobación de clase/tipo manualmente.
     * @return mixed Debe devolver un valor del tipo devuelto indicado en __construct (coincidente
     * con self::getBasicOutputType())
     */
    protected function formatValue($value) {
        if ($value instanceof \DateTime) {
            return $value->format($this->formatoExterno);
        }
        return null;
    }

    /**
     * Implementar para parsear los valores recibidos del tipo básico indicado
     * @param $value mixed Valor de entrada, será del tipo indicado en __construct (coincidente
     * con self::getBasicOutputType())
     * @return mixed Valor interno para el TO, puede ser cualquier tipo
     */
    protected function parseValue($value) {
        return \DateTime::createFromFormat($this->formatoExterno, $value);
    }

    /**
     * Funcion que convierte el tipo básico salido de mysqli al valor que espera recibir
     * el setter del transfer object
     * @param string $value
     * @return \DateTime
     */
    public function fromMysqliToTransferObject($value) {
        return \DateTime::createFromFormat($this->formatoMysqli, $value);
    }

    /**
     * Función que convierte el valor que devuelve el getter del transfer object al
     * tipo básico que espera recibir Mysqli
     * @param \DateTime $value
     * @return string
     */
    public function fromTransferObjectToMysqli($value) {
        return $value->format($this->formatoMysqli);
    }

    /**
     * Obtiene el tipo básico al que da formato para bind_param
     * @see mysqli_stmt::bind_param()
     * @return string
     */
    public function getBindParamType(): string {
        return 's';
    }

    /**
     * Adapta lo que el SimpleController recibe al valor que el filtro debe contener
     * (nótese que puede cambiar el formato o incluso el tipo).
     * @param mixed $value El valor recibido por el simple controller, bien en la URL, bien en los parámetros GET
     * @return mixed El valor que se debe insertar en el filtro, ya formateado
     */
    public function parsearValorParaFiltro($value) {
        return $this->fromTransferObjectToMysqli($this->parse($value));
    }
}