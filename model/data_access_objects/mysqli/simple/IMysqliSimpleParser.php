<?php

namespace model;

/**
 * Las propiedades que se deseen manejar con un SimpleDAO y no sean de tipos básicos deben
 * tener como tipo alguna clase que implemente esta interfaz.
 * @package model\data_access_objects\mysqli\simple
 */
interface IMysqliSimpleParser {
    /**
     * Funcion que convierte el tipo básico salido de mysqli al valor que espera recibir
     * el setter del transfer object
     * @param mixed $value
     * @return mixed
     */
    public function fromMysqliToTransferObject($value);
    /**
     * Función que convierte el valor que devuelve el getter del transfer object al
     * tipo básico que espera recibir Mysqli
     * @param mixed $value
     * @return mixed
     */
    public function fromTransferObjectToMysqli($value);
    /**
     * Obtiene el tipo básico al que da formato para bind_param
     * @see mysqli_stmt::bind_param()
     * @return string Puede ser 'd', 'i', 's', 'b'
     */
    public function getBindParamType(): string;
}