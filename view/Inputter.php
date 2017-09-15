<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace view;

/**
 * Class Inputter, se encarga de parsear el cuerpo de las solicitudes que se
 * realicen a la API.
 * @package view
 */
abstract class Inputter {
    /**
     * Esta función debe parsear el cuerpo de la consulta realizada por el cliente y
     * devolver un array asociativo con su contenido.
     * @param string $requestBody el cuerpo de la consulta en texto plano
     * @return array array asociativo con el cuerpo parseado
     */
    public abstract function parse(string $requestBody): array;
}