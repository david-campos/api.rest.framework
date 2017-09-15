<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */
namespace view;
use controller\PaginationInfo;
use controller\session\SessionManager;
use Exception;
use model\IParseable;
use const model\IPARSEABLE_VERSION_CORTA;

/**
 * Outputter abstracto que funciona a su vez de interfaz de todos los
 * outputters.
 * @package view
 */
abstract class Outputter {
    // Defino aquí unas constantes comunmente usadas en REST Apis,
    // es aconsejable utilizar estas constantes para conseguir
    // un código más legible
    public const HTTP_OK = 200;
    public const HTTP_CREATED = 201;
    public const HTTP_NO_CONTENT = 204;
    public const HTTP_NOT_MODIFIED = 304;
    public const HTTP_BAD_REQUEST = 400;
    public const HTTP_UNAUTHORIZED = 401;
    public const HTTP_FORBIDDEN = 403;
    public const HTTP_NOT_FOUND = 404;
    public const HTTP_METHOD_NOT_ALLOWED = 405;
    public const HTTP_CONFLICT = 409;
    public const HTTP_INTERNAL_SERVER_ERROR = 500;

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
    public abstract function output(int $httpCode, ?array $output): void;

    /**
     * Outputs the given IParseable with the indicated httpCode, the execution should end always
     * on this. This will parse the IParseable in its total version
     *
     * @see https://es.wikipedia.org/wiki/Anexo:C%C3%B3digos_de_estado_HTTP
     *
     * @param int $httpCode The http status code to send
     * @param IParseable $parseable
     */
    public function output_parseable(int $httpCode, IParseable $parseable): void {
        $this->output($httpCode, $parseable->toAssocArray());
    }

    /**
     * Outputs the given array of IParseable with the indicated httpCode, the execution should end always
     * on this. This will parse the IParseable in their short version by default
     *
     * @see https://es.wikipedia.org/wiki/Anexo:C%C3%B3digos_de_estado_HTTP
     *
     * @param int $httpCode The http status code to send
     * @param IParseable[] $parseables An array of IParseable to print
     * @param PaginationInfo|null $paginationInfo Información sobre la paginación (se añadirá al output)
     * @param int $version Versión del IParseable a utilizar, por defecto la versión corta
     * @return void
     */
    public function output_parseables(int $httpCode, array $parseables, ?PaginationInfo $paginationInfo=null,
                                      int $version=IPARSEABLE_VERSION_CORTA): void {
        $array = [];
        foreach($parseables as $parseable) {
            $array[] = $parseable->toAssocArray($version);
        }

        if($paginationInfo) {
            $this->output($httpCode, ['pagination' => $paginationInfo->toArray(), 'elements' => $array]);
        } else {
            $this->output($httpCode, $array);
        }
    }

    /**
     * Outputs an Exception, using its error code as HTTP status
     * @param Exception $error the exception to output
     */
    public function error_output(Exception $error): void {
        if($error->getMessage() !== '') {
            $this->output($error->getCode(),
                [
                    "error" => $error->getMessage(),
                    "session_info" => [
                        "logeada"=>SessionManager::getInstance()->check_sesion()->isLogeada(),
                        "expirada"=>SessionManager::getInstance()->check_sesion()->isExpirada()
                    ]
                ]);
        } else {
            $this->output($error->getCode(), null);
        }
    }

}