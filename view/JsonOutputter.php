<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */
namespace view;


class JsonOutputter extends Outputter {

    /**
     * Outputs the given object with the indicated httpCode, the execution should end always
     * on this.
     *
     * @see https://es.wikipedia.org/wiki/Anexo:C%C3%B3digos_de_estado_HTTP
     *
     * @param int $httpCode The http status code to send
     * @param array|null $output An object to be printed by the outputter into the body of the answer.
     * @return void
     */
    public function output(int $httpCode, ?array $output): void {
        // Headers y status code
        http_response_code($httpCode);
        if($output) {
            header('Content-Type: application/json; charset=UTF-8');

            // Impresión
            $json = json_encode($output, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                // Avoid echo of an empty string (invalid JSON)
                $json = json_encode(array("jsonError", json_last_error_msg()));
                if ($json === false) {
                    $json = '{"jsonError": "unknown"}'; // Extrem case
                }
                http_response_code(Outputter::HTTP_INTERNAL_SERVER_ERROR);
            }
            if($json !== '') {
                die($json);
            }
        }
        // Si no se envió nada
        http_response_code(Outputter::HTTP_NO_CONTENT);
        exit();
    }
}