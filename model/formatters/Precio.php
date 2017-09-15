<?php

namespace model\formatters;

use model\FormatterToBasicType;
use model\IMysqliSimpleParser;
use model\simple_dao\ISimpleFiltroParser;

/**
 * Class Precio, formatea precios
 * @package model\formatters
 */
class Precio extends FormatterToBasicType implements IMysqliSimpleParser,ISimpleFiltroParser {
    public function __construct() {
        // Hacia afuera es un string
        parent::__construct('string', 'precio (dos decimales)');
    }

    /**
     * Implementar para formatear los valores de los TO al tipo básico indicado
     * @param $value mixed Valor para salida, tener en cuenta que podría ser de cualquier tipo.
     * Si se desea, realizar la comprobación de clase/tipo manualmente.
     * @return mixed Debe devolver un valor del tipo devuelto indicado en __construct (coincidente
     * con self::getBasicOutputType())
     */
    protected function formatValue($value) {
        return number_format($value, 2, '.', '');
    }

    /**
     * Implementar para parsear los valores recibidos del tipo básico indicado
     * @param $value mixed Valor de entrada, será del tipo indicado en __construct (coincidente
     * con self::getBasicOutputType())
     * @return mixed Valor interno para el TO, puede ser cualquier tipo
     */
    protected function parseValue($value) {
        return $value;
    }

    /**
     * Obtiene el tipo básico al que da formato para bind_param
     * @see mysqli_stmt::bind_param()
     * @return string
     */
    public function getBindParamType(): string {
        return 'd';
    }

    /**
     * Funcion que convierte el tipo básico salido de mysqli al valor que espera recibir
     * el setter del transfer object
     * @param mixed $value
     * @return mixed
     */
    public function fromMysqliToTransferObject($value) {
        return $value;
    }

    /**
     * Función que convierte el valor que devuelve el getter del transfer object al
     * tipo básico que espera recibir Mysqli
     * @param mixed $value
     * @return mixed
     */
    public function fromTransferObjectToMysqli($value) {
        return $value;
    }

    /**
     * Adapta lo que el SimpleController recibe al valor que el filtro debe contener
     * (nótese que puede cambiar el formato o incluso el tipo).
     * @param mixed $value El valor recibido por el simple controller, bien en la URL, bien en los parámetros GET
     * @return mixed El valor que se debe insertar en el filtro, ya formateado
     */
    public function parsearValorParaFiltro($value) {
        return $value; // No tenemos que hacer nada en este caso
    }
}