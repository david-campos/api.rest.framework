<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace model;

const IPARSEABLE_VERSION_TOTAL=0;
const IPARSEABLE_VERSION_CORTA=1;

/**
 * Interface IParseable, interfaz que deben cumplir los objetos que se puedan parsear a
 * un array asociativo y adquirir desde un array asociativo.
 * @package model
 */
interface IParseable {
    /**
     * Devolverá un array con información sobre la interfaz de este parseable
     * @param bool $onlyIn Indica si SOLO se deben imprimir los atributos de la interfaz
     * que permitan entrada
     * @param bool $isWrappedInOnlyOutput Indica si esta interfaz está wrappeada en una interfaz de sólo salida,
     * dado que esto puede afectar a la información que la interfaz desea mostrar.
     * @return array
     */
    public static function arrayInfoInterfaz(bool $onlyIn=false, bool $isWrappedInOnlyOutput=false): array;

    /**
     * Función que devuelve un array asociativo con las propiedades del Transfer Object
     * al resultado puede variar de un TO a otro.
     * @param int $version Indica la versión a imprimir
     * @return array
     */
    function toAssocArray(int $version=IPARSEABLE_VERSION_TOTAL): array;

    /**
     * Función que actualiza el TO a partir de un array asociativo, empleando
     * la interfaz indicada por el método interfazPropiedades()
     * @param array $array
     */
    function fromAssocArray(array $array);

    /**
     * Método que permite cosntruír el Parseable sin ningún tipo de parámetro.
     * @return IParseable El Parseable construído con valores por defecto.
     */
    static public function emptyConstruction();
}