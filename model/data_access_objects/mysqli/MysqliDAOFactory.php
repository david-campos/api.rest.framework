<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace model;

use model\simple_dao\IInternalSimpleDAO;
use model\simple_dao\ISimpleDAO;
use model\simple_dao\SimpleDAO;

require_once dirname(__FILE__) . '/MysqliDAO.php';
require_once dirname(__FILE__) . '/SimpleDaosDAO.php';
require_once dirname(__FILE__) . '/simple/MysqliAbstractSimpleDAO.php';
require_once dirname(__FILE__) . '/simple/MysqliSimpleBasicDAO.php';
require_once dirname(__FILE__) . '/simple/MysqliSimpleWrapper.php';
require_once dirname(__FILE__) . '/simple/MysqliToTableManager.php';
require_once dirname(__FILE__) . '/simple/MysqliSingleTableInfo.php';
require_once dirname(__FILE__) . '/simple/IMysqliSimpleParser.php';
require_once dirname(__FILE__) . '/MysqliApiConfigDAO.php';
// END REQUIRES
// Este archivo se encarga de requerir todos los archivos relativos a mysqli,
// al editar tenga en cuenta, por favor, que el script add.py (scripts/add.py)
// buscará la línea "// END REQUIRES" e insertará el nuevo require encima de ella.

/**
 * Implementación de la DAOFactory para Mysqli. Para más información sobre el funcionamiento
 * del patrón DAO con AbstractFactory consultar online.
 *
 * @see http://www.oracle.com/technetwork/java/dataaccessobject-138824.html
 */
final class MysqliDAOFactory extends DAOFactory {
    public function getApiConfigDAO(): IApiConfigDAO {
        return new MysqliApiConfigDAO();
    }

    /**
     * Se conecta a la base, y utilizando la información de configuración (consultar diagrama "4 - api sessions"
     * de la base de datos) instancia el arbol de SimpleDAOs de forma correcta.
     * @param string $toClass La clase del to que se desea guardar en la base
     * @return ISimpleDAO El dao para el to solicitado
     */
    public function getSimpleDaoForTo(string $toClass): ISimpleDAO {
        $estructura = (new SimpleDaosDAO())->obtenerArrayEstructuralParaTo($toClass);
        if(count($estructura) == 0) {
            throw new \InvalidArgumentException("No existe Simple Controller para <$toClass>");
        }
        return new SimpleDao($this->simpleDaoParaEstructura($estructura));
    }

    private function simpleDaoParaEstructura($estructura): IInternalSimpleDAO {
        $toToTables = array_map(function($estructuraToToTable) {
                return new ToToTable(
                    $estructuraToToTable['tabla'],
                    $estructuraToToTable['campos']);
            }, $estructura['toToTables']);

        if($estructura['tipo'] === 'basic') {
            return new MysqliSimpleBasicDAO(
                $estructura['to'],
                $toToTables);
        } elseif ($estructura['tipo'] === 'wrapper') {
            $daosHijos = [];
            foreach($estructura['others'] as $other) {
                $daosHijos[$other['to'].'[]'] = $this->simpleDaoParaEstructura($other);
            }
            return new MysqliSimpleWrapper(
                $estructura['to'],
                $toToTables,
                $daosHijos
            );
        } else {
            throw new \InvalidArgumentException("Tipo desconocido <".$estructura['tipo'].">");
        }
    }
}