<?php
/**
 * Script que recibe todas las peticiones de la API y lanza el controlador
 * para procesarlas.
 *
 * Nótese que aquí es donde empieza el proceso:
 * - La fachada del controlador analizará la URL solicitada y generará un controlador específico para ella,
 * pasándole los parámetros que estén definidos en el patrón.
 * - El controlador específico procesará la petición indicada, interactuando con la base de datos a través
 * del patrón DAO con AbstractFactory que implementa el modelo. Una vez disponga de los datos adecuados o
 * haya realizado su tarea, enviará la respuesta a la visa.
 * - La vista recogerá la respuesta y la imprimirá en JSON adecuadamente.
 *
 * Nótese que el flujo de información es unidireccional, controlador->modelo->vista. No hay actualizaciones
 * de la vista que necesiten llamar al controlador al no ser esta una vista interactiva realmente, más meramente
 * un output.
 *
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

/**
 * El archivo requires.php contiene todos los archivos que es necesario requerir
 * para el correcto funcionamiento de la aplicación.
 */
require_once dirname(__FILE__) . '/../requires.php';

try {
    // Creamos la fachada del controlador y pedimos que procese la petición
    (new \controller\ControllerFacade())->processRequest();
} catch(Throwable $e) {
    // Just in case everything else fails
    header('Content-Type: text/plain; charset=UTF-8');
    http_response_code(500); // Internal Sever Error
    die("Internal error");
}