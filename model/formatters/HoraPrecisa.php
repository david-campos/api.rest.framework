<?php

namespace model\formatters;

use model\FormatterToBasicType;
use model\IMysqliSimpleParser;

/**
 * Formatea horas, maneja DateTime
 * @package model\formatters
 */
class HoraPrecisa extends DateTimeFormatter {
    public function __construct() {
        parent::__construct(
            'hor:min:seg.microseg',
            'H:i:s.u',
            'H:i:s.u');
    }
}