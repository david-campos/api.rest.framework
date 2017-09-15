<?php

namespace model\simple_dao;

interface IInternalSimpleDAO {
    /**
     * Devuelve la clase de TO que maneja el DAO
     * @return string
     */
    function getToClass();

    function propiedadEsFiltrable(array $ruta, string $label): bool;

    function getTosWithTransaction(?array $filters, int $pagina = 0, int $size = -1, &$paginationInfo=NULL): array;

    function genericDeleteTosWithTransaction(array $filtros);

    function createTosWithTransaction($prototypes);

    function saveTosWithTransaction($tos);
}