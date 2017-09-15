<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace model;
use controller\RequestParsingException;
use model\exceptions\RequiredPropertyException;

/**
 * Todos los Transfer Object deben extender de esta clase si están expuestos,
 * útil de cara a la implementación de la Vista de la API.
 *
 * @package model
 */
abstract class ExposedTO extends TO {
    final function toAssocArray(int $version = IPARSEABLE_VERSION_TOTAL): array {
        $array =  parent::toAssocArray($version);
        if(getenv('PRINT_LINKS')!==false) {
            $links = $this->interfazLinks();
            if (count($links) > 0) $array['links'] = [];
            foreach ($links as $nombre => $link) {
                $array['links'][$nombre] = $link;
            }
        }
        return $array;
    }


    /**
     * Las clases hijas deben implementar este método para indicar los links que desean exponer.
     * simplemente devolver un array del tipo "nombreDelLink" => "link".
     * @return array
     */
    abstract public function interfazLinks(): array;
}