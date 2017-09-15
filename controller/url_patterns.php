<?php
/**
 * En este archivo se crea la constante URL_PATTERNS, usada por
 * la fachada del controlador (ControllerFacade) para crear el
 * controlador específico adecuado para cada URL.
 * El pattern debe ser dado en forma de expresión regular, y
 * será comprobada con la url relativa, NO INCLUYE EL DOMINIO.
 *
 * El controlador debe ser indicado mediante el nombre completo
 * de su clase.
 *
 * @deprecated
 *
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace controller;

const URL_BASE = 'http://tournride.info/';