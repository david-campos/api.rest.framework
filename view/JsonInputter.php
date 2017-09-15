<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace view;


use controller\RequestParsingException;

class JsonInputter extends Inputter {

    /**
     * Esta función debe parsear el cuerpo de la consulta realizada por el cliente y
     * devolver un array asociativo con su contenido.
     * @param string $requestBody el cuerpo de la consulta en texto plano
     * @return array array asociativo con el cuerpo parseado
     * @throws RequestParsingException
     */
    public function parse(string $requestBody): array {
        $array = json_decode($requestBody, true);
        if($array === null) {
            throw new RequestParsingException(json_last_error_msg()." Error while parsing: ".$requestBody);
        }
        return $array;
    }
}