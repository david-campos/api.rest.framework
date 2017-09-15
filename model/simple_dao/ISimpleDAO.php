<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace model\simple_dao;
use controller\PaginationInfo;
use model\ExposedTO;
use model\Filtro;

/**
 * Class ISimpleDAO, interfaz que facilitará la implementación de DAO's simples,
 * para acelerar la creación de nuevos DAOs. Permite implementar sencillamente DAO's para
 * TO's que se corresponden a una única fila en una única tabla en la base de datos.
 * @package model
 */
interface ISimpleDAO {
    /**
     * Obtiene el string que identifica la clase de TO que genera este SimpleDAO,
     * SimpleController lo utilizará para saber qué TO instanciar al crear un nuevo.
     * @return string
     */
    public function getToClass(): string;

    /**
     * Dada una ruta de labels y un label de propiedad, indica si esa propiedad sirve para el filtrado
     * o no.
     * @param string[] $ruta Los label en el orden en que se debe avanzar en la jerarquía de TO's asociada
     * @param string $label El label de la propiedad final, la que se desea saber si sirve como filtro
     * @return bool
     */
    public function propiedadEsFiltrable(array $ruta, string $label): bool;

    /**
     * Obtiene el TO correpsondiente a los valores de PK dados
     * @param array ...$valoresPk
     * @return ExposedTO
     */
    public function getTo(...$valoresPk): ExposedTO;

    /**
     * Obtiene los TOs de la tabla asociada a este SimpleDAO, permite consulta paginada
     *
     * @see Filtro
     *
     * @param array[] Array bidimensional de filtros sobre el que filtrar los TOs
     * @param int $pagina número de página a solicitar (empezando en 0)
     * @param int $size número de filas por página
     * @param PaginationInfo $paginacion variable para guardar la paginación
     * @return array
     */
    public function getTos(?array $filters=null, int $pagina = 0, int $size = -1, &$paginacion=NULL): array;

    /**
     * Elimina la fila correspondiente al TO de la base de datos
     * @param array ...$valoresPk valores de clave primaria para obtener el TO concreto
     * @return int número de elementos eliminados
     */
    public function deleteTo(&...$valoresPk): int;

    /**
     * Elimina las filas correspondientes, recibe un array bidimensional con los valores de pk
     * @param array $valoresPk valores de pk en un array bidimensional
     * @return int número de elementos eliminados
     */
    public function deleteTos(array $valoresPk): int;

    /**
     * Elimina de la base los tos correspondientes, filtrados según algunas de sus propiedades
     *
     * @see ISimpleDAO::getTos()
     *
     * @param array $filters array bidimensional de filtros
     * @return int número de elementos eliminados
     */
    public function filteredDeleteTos(array $filters): int;

    /**
     * Crea el TO correspondiente en la base, a partir de un prototipo
     * @param ExposedTO $prototype prototipo de TO
     * @return ExposedTO el TO representando la fila creada en la base
     */
    public function createTo(ExposedTO $prototype): ExposedTO;

    /**
     * Permite la inserción bulkanizada de TO's
     * @param ExposedTO[] $prototypes prototipos de los TO's
     * @return ExposedTO[] lista de los TO's representando las filas creadas
     */
    public function createTos(array $prototypes): array;

    /**
     * Guarda el TO en la base de datos
     * @param ExposedTO $to el TO a guardar
     */
    public function saveTo($to): void;

    /**
     * Guarda TOs en la base de datos
     * @param ExposedTO[] $tos los TOs a guardar
     */
    public function saveTos($tos): void;
}