<?php

namespace model\formatters;

/**
 * Formatea fechas, maneja DateTime
 * @package model\formatters
 */
class Fecha extends DateTimeFormatter {
    public function __construct() {
        parent::__construct(
            'dia/mes/año',
            'd/m/Y',
            'Y-m-d');
    }
}