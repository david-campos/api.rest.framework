<?php

namespace model\formatters;

/**
 * Formatea horas, maneja DateTime
 * @package model\formatters
 */
class HoraCorta extends DateTimeFormatter {
    public function __construct() {
        parent::__construct(
            'hor:min',
            'H:i',
            'H:i:s');
    }
}