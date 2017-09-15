<?php
/**
 * Este script realiza todos los requires necesarios.
 * Hay un par de excepciones, para facilitar la realización
 * de ciertos cambios:
 *  - MysqliDAOFactory incluye todos los archivos de mysqli/
 *  - MysqliDAO        incluye /secure/mysqli_access.php él mismo
 *
 * Para el buen funcionamiento de scripts/add.py, no editar los comentarios de este archivo (//CONTROLLER,//END CONTROLLER,etc.)
 *
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

$dirname = dirname(__FILE__);

require_once $dirname.'/api_config.php';

// VIEW
require_once $dirname.'/view/Inputter.php';
require_once $dirname.'/view/JsonInputter.php';
require_once $dirname.'/view/Outputter.php';
require_once $dirname.'/view/JsonOutputter.php';
// END VIEW

// CONTROLLER
require_once $dirname . '/controller/controller_exceptions.php';
require_once $dirname . '/controller/session/session_exceptions.php';
require_once $dirname . '/controller/session/SessionInfo.php';
require_once $dirname . '/controller/session/SessionManager.php';
require_once $dirname . '/controller/ControllerFacade.php';
require_once $dirname . '/controller/URLController.php';
require_once $dirname . '/controller/SimpleController.php';
require_once $dirname . '/controller/PaginationInfo.php';
require_once $dirname . '/controller/url_controllers/SessionController.php';
// END CONTROLLER

// MODEL
require_once $dirname . '/model/DAOFactory.php';

// DAO
require_once $dirname . '/model/simple_dao/IInternalSimpleDAO.php';
require_once $dirname . '/model/simple_dao/ISimpleFiltroParser.php';
require_once $dirname . '/model/simple_dao/ISimpleDAO.php';
require_once $dirname . '/model/simple_dao/SimpleDAO.php';
require_once $dirname . '/model/data_access_objects/IApiConfigDAO.php';
// END DAO

require_once $dirname . '/model/PropiedadInterfaz.php';
require_once $dirname . '/model/IParseable.php';
require_once $dirname . '/model/IFormatter.php';
require_once $dirname . '/model/FormatterToBasicType.php';
require_once $dirname . '/model/TO.php';
require_once $dirname . '/model/ExposedTO.php';
require_once $dirname . '/model/Filtro.php';
require_once $dirname . '/model/model_exceptions.php';

// GENERIC FORMATTERS
require_once $dirname . '/model/formatters/DummyFormatter.php';
require_once $dirname . '/model/formatters/ParseableFormatter.php';
require_once $dirname . '/model/formatters/ParseableArrayFormatter.php';
// END GENERIC FORMATTERS

// No mover hacia arriba en el archivo, pues debe ser requerido despues de que todas las interfaces
// y demás han sido requeridas ya.
require_once $dirname . '/model/data_access_objects/mysqli/MysqliDAOFactory.php'; // Cambiar cuando se cambie la familia empleada

// Los formatters pueden requerir alguna interfaz de lo anterior
// FORMATTERS
require_once $dirname . '/model/formatters/DateTimeFormatter.php';
require_once $dirname . '/model/formatters/Fecha.php';
require_once $dirname . '/model/formatters/HoraCorta.php';
require_once $dirname . '/model/formatters/HoraLarga.php';
require_once $dirname . '/model/formatters/HoraPrecisa.php';
require_once $dirname . '/model/formatters/FechaHora.php';
require_once $dirname . '/model/formatters/Telefono.php';
require_once $dirname . '/model/formatters/Precio.php';
// END FORMATTERS

// END MODEL