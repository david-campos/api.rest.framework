<?php
/**
 * Created by PhpStorm.
 * User: davidcamposrodriguez
 * Date: 29/08/17
 * Time: 17:14
 */

namespace controller;


class PaginationInfo {
    /** @var int */
    public $paginas;
    /** @var int */
    public $sizePagina;
    /** @var int */
    public $paginaActual;

    /**
     * PaginationInfo constructor.
     * @param int $paginas
     * @param int $sizePagina
     * @param int $paginaActual
     */
    public function __construct($paginas, $sizePagina, $paginaActual) {
        $this->paginas = $paginas;
        $this->sizePagina = $sizePagina;
        $this->paginaActual = $paginaActual;
    }

    public function toArray(): array {
        return ['paginas-totales'=>$this->paginas, 'size'=>$this->sizePagina, 'page'=>$this->paginaActual];
    }
}