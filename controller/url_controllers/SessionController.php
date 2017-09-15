<?php

namespace controller;
use controller\session\LoginException;
use controller\session\SessionManager;
use model\DAOFactory;
use model\formatters\FechaHora;
use view\Outputter;

/**
 * Controla las sesiones
 * - POST inicia una nueva sesión (hace login)
 * - PUT permite registrar un nuevo usuario (la persona debe existir previamente)
 * - DELETE cierra la sesion
 * - GET obtiene información de tu propia sesión (la que es seguro mostrar)
 * @package controller
 */
class SessionController extends URLController {
    /**
     * Método que deben implementar las clases hijas para manejar las solicitudes GET a las URL que se le asignen.
     * Usar para consultas.
     * @return void
     */
    protected function get_impl() {
        $sessionInfo = SessionManager::getInstance()->check_sesion();
        $array = [
            'nombre'=> $sessionInfo->getName(),
            'apellidos' => $sessionInfo->getSurname(),
            'tipo' => $sessionInfo->getTipoPersona(),
            'activado' => $sessionInfo->getUserActivado(),
            'email' => $sessionInfo->getUserEmail(),
            'cifNif' => $sessionInfo->getUserCifNif(),
            'logeado' => $sessionInfo->isLogeada(),
            'expirada' => $sessionInfo->isExpirada(),
            'interaccionPrevia' => (new FechaHora())->format($sessionInfo->getUltimoAcceso())
        ];
        $this->outputter->output(Outputter::HTTP_OK, $array);
    }

    /**
     * Método que deben implementar las clases hijas para manejar las solicitudes POST a las URL que se le asignen.
     * Usar para inserciones.
     * @param array $body Cuerpo de la petición ya parseado a array asociativo
     * @return void
     * @throws RequestParsingException
     */
    protected function post_impl(array $body) {
        if(!isset($body['user'], $body['pass'])) {
            throw new RequestParsingException('Envíe nombre y contraseña');
        }

        try {
            SessionManager::getInstance()->login($body['user'], $body['pass']);
        } catch(LoginException $exception) {
            $this->outputter->error_output(
                new \Exception('Login incorrecto', Outputter::HTTP_UNAUTHORIZED));
        }
        $this->outputter->output(Outputter::HTTP_OK, ['logged'=>true]);
    }

    /**
     * Método que deben implementar las clases hijas para manejar las solicitudes DELETE a las URL que se le asignen.
     * Usar para borrados.
     * @return void
     */
    protected function del_impl() {
        SessionManager::getInstance()->closeSession();
        $this->outputter->output(Outputter::HTTP_OK, ['logged'=>false]);
    }

    /**
     * Método que deben implementar las clases hijas para manejar las solicitudes PUT a las URL que se le asignen.
     * Usar para actualizaciones.
     * @param array $body Cuerpo de la petición ya parseado a array asociativo
     * @return void
     * @throws UnknownMethodException
     */
    protected function put_impl(array $body) {
        // Temporalmente usaremos esto para probar el registro
        if(isset($body['user'], $body['cif/nif'], $body['pass'])) {
            $tipo = intval(DAOFactory::getInstance()->getApiConfigDAO()
                ->getApiConfig(API_CONFIG_DEFAULT_USER_TYPE)['value']);
            if(isset($body['tipo'])) {
                $tipo = $body['tipo'];
            }
            SessionManager::getInstance()->registro($body['user'], $body['pass'], $body['cif/nif'], $tipo);
            $this->outputter->output(Outputter::HTTP_OK, ['registrado'=>true]);
        }
        $this->outputter->error_output(new \Exception('Envie usuario, contraseña y nif/cif', Outputter::HTTP_BAD_REQUEST));
    }

    protected function getInterface($out = true, $in = true): array {
        return [
            'login' => ['metodo'=>'POST', 'datos a enviar'=>[
                'user'=>'Nombre de usuario',
                'pass'=>'Contraseña para el login']],
            'logout' => 'DELETE',
            'registro' => ['metodo'=>'PUT', 'datos a enviar'=>[
                'user'=>'Nombre de usuario',
                'pass'=>'Contraseña para el login',
                'cif/nif'=>'El cif o nif de la persona (física o jurídica) asociada al usuario']],
            'session-info' => 'GET'
        ];
    }


    /**
     * Respuesta a la consulta http con método OPTIONS, debe devolver un array de los métodos
     * aceptados para la URL consultada. No hay necesidad de filtrar por nivel pues este
     * método se encuentra decorado/enmascarado por self::options(), que ya comprueba el nivel
     * antes de elaborar la respuesta. Este método debe devolver el array y no escribir nada.
     * @return string[]
     */
    protected function options_impl(): array {
        return ['GET','POST','DELETE', 'PUT'];
    }
}