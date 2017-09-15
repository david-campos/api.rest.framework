<?php
namespace model;

class ToToTable {
    /** @var string Nombre de la tabla */
    public $tabla;
    /** @var string[] Labels de las propiedades como claves y los campos correspondientes en la tabla como valores */
    public $campos;

    /**
     * ToToTable constructor.
     * @param string $tabla
     * @param string[] $campos
     */
    public function __construct($tabla, array $campos) {
        $this->tabla = $tabla;
        $this->campos = $campos;
    }
}

/**
 * Maneja una serie de TableInfo's para un TO dado de forma jerárquica, es decir, para tablas que son realmente una
 * herencia unas de otras, y por lo tanto comparte la tabla hija el PK de la tabla padre de forma única.
 * @package model
 */
class MysqliToTableManager {
    /** @var string Información sobre el TO para que el se creó este field manager */
    private $classTo;
    /** @var MysqliSingleTableInfo[] */
    private $tableInfos;
    /** @var PropiedadInterfaz[] Las claves son los label de las propiedades */
    private $propiedades;

    /**
     * MysqliToTableManager constructor.
     * @param string $classTo Clase del TO a manejar
     * @param ToToTable[]|ToToTable $toToTables
     */
    public function __construct(string $classTo, $toToTables) {
        if(!is_subclass_of($classTo, 'model\TO')) {
            throw new \InvalidArgumentException(
                "ATENCIÓN: La clase indicada (" . $classTo . ") no hereda de model\TO. ");
        }

        // Si es uno solo lo convertimos en un array de uno
        if(getType($toToTables) !== 'array') {
            $toToTables = [$toToTables];
        }

        $this->classTo = $classTo;
        $this->propiedades = [];
        $this->tableInfos = [];

        /** @noinspection PhpUndefinedMethodInspection */
        /** @var PropiedadInterfaz[] $propiedades Las propiedades que no son de solo lectura */
        $propiedades = array_filter($classTo::interfazPropiedades(), function(PropiedadInterfaz $prop){
            return !$prop->soloLectura();
        });

        $pksPrevios = []; // Solo es para comprobar y logear
        foreach($toToTables as $toToTable) {
            if(!$toToTable instanceof ToToTable) {
                throw new \InvalidArgumentException('Uno de los ToToTable no es instancia de ToToTable');
            }
            $camposPk = [];
            $camposNoPk = [];
            $pksPreviosEnNuevo=0;
            foreach($propiedades as $propiedad) {
                if(key_exists($propiedad->getLabel(), $toToTable->campos)) {
                    $tipoElegido = MysqliAbstractSimpleDAO::elegirTipo($propiedad);
                    if($propiedad->esPk()) {
                        if(key_exists($propiedad->getLabel(), $pksPrevios)) {
                            $requerida = true; // Si la propiedad no es la primera vez que aparece, debe ser requerida
                            $pksPreviosEnNuevo += 1;
                        } else {
                            $requerida = $propiedad->esRequerida();
                        }

                        $camposPk[$propiedad->getLabel()] = [$tipoElegido, $toToTable->campos[$propiedad->getLabel()],
                            $requerida];
                    } else {
                        $camposNoPk[$propiedad->getLabel()] = [$tipoElegido, $toToTable->campos[$propiedad->getLabel()]];
                    }
                    if(!key_exists($propiedad->getLabel(), $this->propiedades)) {
                        $this->propiedades[$propiedad->getLabel()] = $propiedad;
                    }
                }
            }
            if($pksPreviosEnNuevo < count($pksPrevios)) {
                throw new \InvalidArgumentException(
                    'No se pueden manejar con un solo ToTableManager las tablas indicadas, pues algunas de las tablas '.
                    'no indican donde guardar los PKs de las tablas anteriores a ellas, con lo cual no forman una jerarquía');
            }
            $pksPrevios = array_merge($pksPrevios, $camposPk);
            $this->tableInfos[] = new MysqliSingleTableInfo($toToTable->tabla, $camposPk, $camposNoPk);
        }
    }

    /**
     * @return PropiedadInterfaz[]
     */
    public final function interfazPropiedades() {
        /** @noinspection PhpUndefinedMethodInspection */
        return $this->classTo::interfazPropiedades();
    }

    /**
     * @return string
     */
    public function getClassTo(): string {
        return $this->classTo;
    }


    /**
     * Comprueba si la propiedad con el label indicado es filtrable o no
     * @param string $label
     * @return bool
     */
    public function propiedadEsFiltrable(string $label): bool {
        if(!key_exists($label, $this->propiedades)) {
            return false; // La propiedad no existe
        }

        $propiedad = $this->propiedades[$label];
        if($propiedad->soloLectura()) {
            return false; // La propiedad es de solo lectura
        }

        foreach($this->tableInfos as $tI) {
            if($tI->conocePropiedad($label))
                return true; // Si alguno de nuestros table info la conoce lo es
        }
        return false; // Si no, no lo es
    }

    /**
     * @return string[]
     */
    public function getTablas() {
        $tablas = [];
        foreach($this->tableInfos as $tableInfo) {
            $tablas[] = $tableInfo->tabla();
        }
        return $tablas;
    }

    /**
     * Obtiene los campos para los pks del to,
     * @return string[] los label son los label de las propiedades y los valores los campos ya entrecomillados y con
     * tabla
     */
    public function getCamposParaPks() {
        $campos = [];
        foreach($this->tableInfos as $tI) {
            $tabla = $tI->tabla();
            $nuevosCampos = [];
            foreach($tI->camposPk() as $label=>$campoPk) {
                $nuevosCampos[$label] = "`$tabla`.`$campoPk`";
            }
            $campos = array_merge($campos, $nuevosCampos);
        }
        return $campos;
    }

    // SELECT //////////////////////////////////////////////////////////////////////////////////////////////////////////
    // En el select hacemos un JOIN de todas las tablas que maneja (pues son jerárquicas, cada una contiene el PK
    // completo de la anterior, teóricamente), para hacecrlas funcionar como una tabla sola.
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Devuelve el string para el select de este SimpleDao (primero los campos de PK y luego el resto de campos)
     * @return string
     */
    public final function select(): string {
        $selects = [];
        foreach($this->tableInfos as $tableInfo) {
            $selects[] = $tableInfo->select();
        }
        return implode(', ', $selects);
    }

    /**
     * Crea un array con tantos valores null como campos tiene la tabla,
     * muy útil para bind_result (tras crear un array de referencias a el)
     * @return array
     */
    public final function arrayVacioForResult() {
        $array = [];
        foreach($this->tableInfos as $tableInfo) {
            $array = array_merge($array, $tableInfo->arrayVacioForResult());
        }
        return $array;
    }

    /**
     * Prepara la cláusula where según los filtros indicados para cada tabla. IGNORA LAS RUTAS DE LOS FILTROS
     * Los campos son devueltos en el orden en que se pasaron los label.
     * @param array $filtros filtros con los que elaborar el where array bidimensional
     * @param string $tipos Variable donde se guardarán los tipos para el where generado (para bind_param)
     * @param array $valuesArray Variable donde se guardará el array de valores
     * @param null $numCampos Referencia a la variable en la que guardar el número de campos
     * @return string la cláusula where (sin where, únicamente los campos, por ejemplo: `campoA`=? AND `campoB`=?
     */
    public final function whereFiltrosParaSelect($filtros, &$tipos, &$valuesArray, &$numCampos) {
        $wheres = [];
        $tipos = '';
        $valuesArray = [];
        $numCampos = 0;
        foreach($this->tableInfos as $tableInfo) {
            $nuevoWhere = $tableInfo->whereFiltros($filtros, $tiposNuevo, $valuesArrayNuevo, $numCamposNuevo);
            $this->eliminarFiltrosPk($filtros, $tableInfo); // Los pk solo los queremos una vez
            if($nuevoWhere !== '') {
                $wheres[] = $nuevoWhere;
                $tipos .= $tiposNuevo;
                $valuesArray = array_merge($valuesArray, $valuesArrayNuevo);
                $numCampos += $numCampos;
            }
        }
        return implode(' AND ', $wheres);
    }

    /**
     * @param Filtro[][] $filtros
     * @param MysqliSingleTableInfo $tableInfo Table info que indica cuales son los pks a quitar
     */
    private function eliminarFiltrosPk(&$filtros, $tableInfo) {
        $nuevosFiltros = [];
        $camposPk = $tableInfo->camposPk();
        foreach($filtros as $kC=>$filtroCompuesto) {
            $nuevosFiltros[$kC] = [];
            foreach ($filtroCompuesto as $kF => $f) {
                if (!key_exists($f->getLabel(), $camposPk)) {
                    $nuevosFiltros[$kC][$kF] = $f; // Lo copiamos porque no es pk
                }
            }
        }
        $filtros = $nuevosFiltros;
    }

    /**
     * Devuelve la tabla para hacer un select
     * @param string $tipoDeJoin El tipo de join a realizar, por defecto JOIN
     * @return string
     */
    public function getTablaSelect($tipoDeJoin='JOIN'): string {
        $tablaPrevia = $tablasJoin = $this->tableInfos[0]->tabla();
        $pksPrevia = $this->tableInfos[0]->camposPk();
        for($i=1; $i<count($this->tableInfos); $i++) {
            $nuevaTabla = $this->tableInfos[$i]->tabla();
            $pks = $this->tableInfos[$i]->camposPk();

            $on = [];
            foreach($pksPrevia as $label=>$pk) {
                $nuevoPk = $pks[$label];
                $on[] = "`$tablaPrevia`.`$pk`=`$nuevaTabla`.`$nuevoPk`";
            }

            $tablasJoin .= " $tipoDeJoin ".$nuevaTabla.' ON('.
                implode(' AND ', $on).
                ')';

            $tablaPrevia = $nuevaTabla;
            $pksPrevia = $pks;
        }
        return $tablasJoin;
    }

    /**
     * Dado un array con un resultado de la consulta por un TO,
     * crea el TO correspondiente con sus parámetros seteados.
     * @param $result
     * @return mixed
     */
    public final function toFromResult($result) {
        /** @noinspection PhpUndefinedMethodInspection */
        $to = $this->getClassTo()::emptyConstruction();
        $i=0;
        foreach($this->tableInfos as $tI) {
            foreach ($tI->labelsPk() as $label) {
                $propiedad = $this->propiedades[$label];
                call_user_func(
                    [$to, $propiedad->getUpdate()],
                    $this->valueForToProperty($result[$i], $label));
                $i++;
            }
            foreach ($tI->labelsNoPk() as $label) {
                $propiedad = $this->propiedades[$label];
                call_user_func(
                    [$to, $propiedad->getUpdate()],
                    $this->valueForToProperty($result[$i], $label));
                $i++;
            }
        }
        return $to;
    }

    // DELETE //////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Obtiene la tabla para la consulta DELETE
     * @return string
     */
    public final function getTablaDelete() {
        return $this->getTablaSelect('LEFT JOIN'); // Es la misma que select pero haciendo un left join
    }

    // INSERT //////////////////////////////////////////////////////////////////////////////////////////////////////////
    // Las funciones de INSERT devolverán arrays con lo necesario para hacer los INSERT en el orden adecuado,
    // la clase cliente del ToTableManager deberá realizar varios insert.
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Nombres de los campos para insertar un TO en cada tabla
     * @param string[] $tipos Variable donde guardar los tipos para el into
     * @return string[][]
     */
    public final function insertInto(&$tipos) {
        $tipos = [];
        $insertsInto = [];
        foreach($this->tableInfos as $tableInfo) {
            $insertsInto[] = $tableInfo->insertInto($nuevosTipos);
            $tipos[] = $nuevosTipos;
        }
        return $insertsInto;
    }

    /**
     * Devuelve el número de campos que tiene el insert generado por insertInto para cada tabla
     * @see MysqliSimpleBasicDAO::insertInto()
     *
     * @return int[]
     */
    public final function numeroCamposInsert() {
        $numerosCampos = [];
        foreach($this->tableInfos as $tableInfo) {
            $numerosCampos[] = $tableInfo->numeroCamposInsert();
        }
        return $numerosCampos;
    }

    /**
     * @see MysqliToTableManager::getValoresParaInsercionFromTos()
     * @param int $numeroDeTabla Numero de tabla actual de inserción en la jerarquía
     * @param TO $to
     * @param array|null $pkUltimaInsercion Pk de la ultima inseción, devuelto por obtenerPkUltimaInsercion en
     * la tabla previa. Pasar null si no hay una inserción previa.
     * @return mixed[] Los valores a insertar en la tabla para insertar el TO
     */
    public function getValoresInsercionFromTo($numeroDeTabla, $to, $pkUltimaInsercion) {
        $tI = $this->tableInfos[$numeroDeTabla];
        $valoresTo = [];
        // Añadimos PK's requeridos en la inserción
        foreach ($tI->labelsPkReq() as $label) {
            if($pkUltimaInsercion && key_exists($label, $pkUltimaInsercion)) {
                $valoresTo[] = $pkUltimaInsercion[$label];
            } else {
                $propiedad = $this->propiedades[$label];
                $valoresTo[] = call_user_func([$to, $propiedad->getShow()]);
            }
        }
        // Añadimos el resto de campos
        foreach ($tI->labelsNoPk() as $label) {
            $propiedad = $this->propiedades[$label];
            $valoresTo[] = call_user_func([$to, $propiedad->getShow()]);
        }
        return $valoresTo;
    }

    /**
     * Dado que al insertar hay campos del PK que no son requeridos,
     * este método facilita obtener los pk del ultimo elemento insertado y en el orden
     * correcto
     * @param int $numeroDeTabla Numero de tabla actual de inserción en la jerarquía
     * @param mixed $last_insert_id valor que devuelve la llamada a last_insert_id
     * @param array $valoresInsertados valores que se han insertado
     * @return array valores de pk en el orden correcto
     */
    public final function obtenerPkUltimaInsercion($numeroDeTabla, $last_insert_id, $valoresInsertados): array {
        $tableInfo = $this->tableInfos[$numeroDeTabla];
        $pk = $tableInfo->obtenerPkUltimaInsercion($last_insert_id, $valoresInsertados);
        $i = 0;
        // Adicionalmente comprobamos que los tipos son correctos
        foreach($tableInfo->labelsPkReq() as $idx=>$label) {
            $propiedad = $this->propiedades[$label];
            // Si se requiere para inserción, será el siguiente valor insertado
            if(!in_array(gettype($valoresInsertados[$i]), $propiedad->getTipos())) {
                throw new \InvalidArgumentException(
                    'En la propiedad '.$label.' se esperaba un valor de alguno de los siguientes tipos: '.
                    implode('|', $propiedad->getTipos()).
                    '; pero el tipo del '.$idx.'º valor insertado es '.gettype($valoresInsertados[$i]));
            }
            $i++;
        }
        return $pk;
    }

    /**
     * Dado un array de PKs, ordenados de la forma en que aparecen en la interfaz del TO, obtiene
     * los filtros para seleccionar esos PKs
     * @param mixed[][] $pks
     * @return Filtro[][] Filtros para seleccionar esos pks
     */
    public function filtroForPks($pks) {
        $filtros = [];
        foreach($pks as $pk) {
            $filtroCompuesto = [];
            $i=0;
            foreach($this->propiedades as $label=>$propiedad) {
                if(!$propiedad->esPk()) {
                    continue;
                }

                if($pk[$i]) $v = $pk[$i];
                elseif($pk[$label]) $v = $pk[$label];
                else throw new \InvalidArgumentException("No se pudo encontrar el PK para $label");

                $filtroCompuesto[] = new Filtro([], $label, Filtro::CMP_IGUAL, [$v]);
                $i++;
            }
            $filtros[] = $filtroCompuesto;
        }
        return $filtros;
    }

    // UPDATE //////////////////////////////////////////////////////////////////////////////////////////////////////////
    // El update ha de realizarse al igual que el insert, repitiendo el update para cada tabla, pero en este caso todos
    // los valores se conocen previamente (están en el TO)
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Devuelve los nombres de los campos para el SET para actualizar el TO para cada tabla
     * @param string[] $tipos Variable para guardar los tipos
     * @param array[] $arraysParaSet Un array de arrays donde guardar los valores de set
     * @return string[][] [tablas[valores]]
     */
    public final function updateSet(&$tipos, &$arraysParaSet) {
        $tipos = [];
        $arraysParaSet = [];
        $update = [];
        foreach($this->tableInfos as $tableInfo) {
            $arraysParaSet[] = $tableInfo->arrayVacioParaUpdateSet();
            $update[] = $tableInfo->updateSet($nuevosTipos);
            $tipos[] = $nuevosTipos;
        }
        return $update;
    }

    /**
     * Devuelve la cadena correspondiente a la cláusula WHERE del update de TO's para cada tabla
     * @param string[] $tipos Variable para guardar los tipos
     * @param array[] $arraysParaWhere Un array donde guardar los valores de where
     * @return string[] [tablas]
     */
    public final function updateWhere(&$tipos, &$arraysParaWhere) {
        $tipos = [];
        $arraysParaWhere = [];
        $where = [];
        foreach($this->tableInfos as $tableInfo) {
            $arraysParaWhere[] = $tableInfo->arrayVacioParaUpdateWhere();
            $where[] = $tableInfo->updateWhere($nuevosTipos);
            $tipos[] = $nuevosTipos;
        }
        return $where;
    }

    /**
     * Obtiene los valores para Set a partir de los TO's dados
     * @param TO[] $tos
     * @return array[][] Los valores para set para cada to en cada tabla [tos[tablas[valores]]]
     */
    public final function getUpdateSetValuesFromTos($tos) {
        $valoresCampos = [];
        foreach($tos as $to) {
            if(!$to instanceof $this->classTo) {
                throw new \InvalidArgumentException('Uno de los prototipos pasados no es subclase de '.$this->classTo);
            }
            $valoresCampos[] = $this->getUpdateSetValuesFromTo($to);
        }
        return $valoresCampos;
    }

    /**
     * Obtiene los valores para Set a partir del TO dado
     * @param TO $to
     * @return array[] Los valores para set para el to en cada tabla [tablas[valores]]
     */
    private final function getUpdateSetValuesFromTo($to) {
        $tablas = [];
        if(!$to instanceof $this->classTo) {
            throw new \InvalidArgumentException('Uno de los prototipos pasados no es subclase de '.$this->classTo);
        }

        foreach($this->tableInfos as $tableInfo) {
            $valoresEnTabla = [];
            foreach ($tableInfo->labelsNoPk() as $label) {
                $valoresEnTabla[] = $this->getValueFromTo($to, $label);
            }
            $tablas[] = $valoresEnTabla;
        }
        return $tablas;
    }

    /**
     * Obtiene los valores para where para actualizar los tos en cada tabla
     * @param TO[] $tos Los tos deben ser instancias de la clase de TO generada por este DAO
     * @return array[][] cada array contendrá los valores para uno de los TO's de entrada en cada tabla [tos[tablas[valores]]]
     */
    public final function getUpdateWhereValuesFromTos($tos) {
        $valoresTos = [];
        /** @var PropiedadInterfaz $propiedad */
        $propiedad = null;
        foreach($tos as $to) {
            if(!$to instanceof $this->classTo) {
                throw new \InvalidArgumentException('Uno de los prototipos pasados no es subclase de '.$this->classTo);
            }

            $valoresTos[] = $this->getUpdateWhereValuesFromTo($to);
        }
        return $valoresTos;
    }

    /**
     * Obtiene los valores para where para actualizar el to en cada tabla
     * @param TO $to
     * @return array[] cada array contendrá los valores para el TO para cada tabla [tablas[valores]]
     */
    private final function getUpdateWhereValuesFromTo($to) {
        $tablas = [];
        if(!$to instanceof $this->classTo) {
            throw new \InvalidArgumentException('Uno de los prototipos pasados no es subclase de '.$this->classTo);
        }

        foreach($this->tableInfos as $tableInfo) {
            $valoresEnTabla = []; // Un array por cada prototipo
            foreach ($tableInfo->labelsPk() as $label) {
                $valoresEnTabla[] = $this->getValueFromTo($to, $label);
            }
            $tablas[] = $valoresEnTabla;
        }
        return $tablas;
    }

    /**
     * Obtiene el valor para la propiedad de label dado desde el TO, y lo formatea si es necesario
     * @param TO $to
     * @param string $label Label de la propiedad cuyo valor se desea obtener
     * @return mixed
     */
    public final function getValueFromTo($to, $label) {
        $propiedad = $this->propiedades[$label];
        $valor = call_user_func([$to, $propiedad->getShow()]);
        $formatter = $this->formatterFor($label);
        if($formatter) {
            $valor = $formatter->fromTransferObjectToMysqli($valor);
        }
        return $valor;
    }

    /**
     * Devuelve el valor formateado (en caso de hacer falta formatearlo) para
     * que se pueda setear en el TO en la propiedad indicada
     * @param mixed $valor
     * @param string $label
     * @return mixed
     */
    public final function valueForToProperty($valor, $label) {
        $formatter = $this->formatterFor($label);
        if($formatter) {
            $valor = $formatter->fromMysqliToTransferObject($valor);
        }
        return $valor;
    }

    /**
     * Obtiene el formatter adecuado para la propiedad indicada, o null si se desconoce
     * @param string $label Label de la propiedad
     * @return IMysqliSimpleParser|null
     */
    private final function formatterFor(string $label): ?IMysqliSimpleParser {
        $tipo = $this->propiedades[$label]->getTipos()[0];
        $parser = null;
        if (is_subclass_of($tipo, 'model\IMysqliSimpleParser')) {
            $parser = new $tipo();
        }
        return $parser;
    }

}