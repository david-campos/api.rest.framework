<?php

namespace model\formatters;

/**
 * Formatea fechas, maneja DateTime
 * @package model\formatters
 */
class FechaHora extends DateTimeFormatter {
    public function __construct() {
        parent::__construct(
            'dd/mm/aaaa hh:mm:ss',
            'd/m/Y H:i:s',
            'Y-m-d H:i:s');
    }
}