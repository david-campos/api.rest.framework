<?php

namespace model\formatters;

use model\FormatterToBasicType;
use model\IMysqliSimpleParser;

/**
 * Formatea horas, maneja DateTime
 * @package model\formatters
 */
class HoraLarga extends DateTimeFormatter {
    public function __construct() {
        parent::__construct(
            'hor:min:seg',
            'H:i:s',
            'H:i:s');
    }
}