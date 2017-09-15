<?php

namespace model;


interface IFormatter {
    /**
     * Formatea el valor indicado, este método será llamado por el TO para formatear el output
     * @param $value mixed
     * @param int $version Indica la versión a imprimir
     * @return mixed
     */
    public function format($value, $version=IPARSEABLE_VERSION_TOTAL);

    /**
     * Parsea el valor indicado, este método será llamado por el TO para formatear el input
     * @param $value mixed
     * @return mixed
     */
    public function parse($value);
}