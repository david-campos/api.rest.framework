<?php

namespace model\simple_dao;

/**
 * Debe ser implementada por todos los Parser que se desee que puedan trabajar con SimpleController
 * y su función es adaptar lo que el SimpleController recibe al valor que el filtro debe contener
 * (nótese que puede cambiar el formato o incluso el tipo)
 * @package model\simple_dao
 */
interface ISimpleFiltroParser {
    /**
     * Adapta lo que el SimpleController recibe al valor que el filtro debe contener
     * (nótese que puede cambiar el formato o incluso el tipo).
     * @param mixed $value El valor recibido por el simple controller, bien en la URL, bien en los parámetros GET
     * @return mixed El valor que se debe insertar en el filtro, ya formateado
     */
    public function parsearValorParaFiltro($value);
}