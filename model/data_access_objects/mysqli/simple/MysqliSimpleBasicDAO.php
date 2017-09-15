<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace model;
use controller\PaginationInfo;

/**
 * Class MysqliSimpleDAO, clase abstracta que facilitará la implementación de ISimpleDAO en Mysqli,
 * para acelerar la creación de nuevos DAOs. Permite implementar sencillamente DAO's para
 * TO's que se corresponden a una única fila en una única tabla en la base de datos y tienen
 * interfaces sencillas.
 * @package model
 */
class MysqliSimpleBasicDAO extends MysqliAbstractSimpleDAO {
    /** @var string Clase del TO que genera este DAO */
    protected $classTo;
    /** @var MysqliToTableManager Manejador de campos de la tabla */
    protected $manager;

    /**
     * MysqliSimpleDAO constructor.
     * @param string $classTo Clase de los TO que genera el DAO
     * de la tabla donde estos deben guardarse.
     * @param ToToTable[]|ToToTable $toToTables
     */
    public function __construct(string $classTo, $toToTables) {
        $this->classTo = $classTo;
        $this->manager = new MysqliToTableManager($classTo, $toToTables);
        parent::__construct();
    }

    function getToClass() {
        return $this->classTo;
    }

    public function propiedadEsFiltrable(array $ruta, string $label): bool {
        if(count($ruta) === 0) {
            return $this->manager->propiedadEsFiltrable($label);
        } else {
            return false; // La ruta se acaba aquí, chico
        }
    }

    public function getCamposParaPks() {
        return $this->manager->getCamposParaPks();
    }

    public function elaborarWhereParaConsultaConFiltros(?array $filtros, &$types, &$copyValuesArray, &$where) {
        $filtroSize = $filtros?count($filtros):0; // Filtro size será 0 si no hay filtro ninguno

        if($filtroSize > 0) {
            if(!$types) $types = '';
            $nuevoWhere = $this->manager->whereFiltrosParaSelect(
                $filtros, $tiposGenerado, $copyValuesArray, $filtroSize); // Obtiene el where y el tamaño del filtro
            if($nuevoWhere !== '') {
                if(!$where) {
                    $where = $nuevoWhere;
                } else {
                    $where = "($where) AND $nuevoWhere";
                }
            }
            $filtroSize += strlen($tiposGenerado);
            $types .= $tiposGenerado;
        }
        if($filtroSize < 1) {
            $types = null;
            if($where === '') {
                $where = null;
            }
        }
    }

    public function elaborarConsultaConFiltros(?array $filtros, &$types, &$copyValuesArray, &$where=null,
                                               $select=null, $limit=null, $group=null, $having=null,
                                               $order=null): string {
        $this->elaborarWhereParaConsultaConFiltros($filtros, $types, $copyValuesArray, $where);
        return parent::elaborarConsulta(
            $this->manager->getTablaSelect(),
            $select?$select:$this->manager->select(),
            $where, $group,$having, $order, $limit, false);
    }

    /**
     * Realiza de forma genérica la obtención de TO's sin elaborar una transacción.
     *
     * @param array $filters array bidimensional de filtros
     * @param int $pagina número de página (si se desea paginar)
     * @param int $size tamaño de las páginas (si se desea paginar)
     * @param PaginationInfo $paginationInfo referencia donde guardar la información de paginación
     * @return array array con los TO's obtenidos
     * @throws \ArgumentCountError si el número de valores coincide con el número de labels para los filtros
     */
    public function getTosNoTransaction(?array $filters=null, int $pagina = 0, int $size = -1, &$paginationInfo=NULL): array {

        $limit = $this->limit($pagina, $size);
        $consulta = $this->elaborarConsultaConFiltros($filters, $types, $copyValuesArray, $where, null, $limit);

        if($types) {
            $this->refArray($arrayRefsValues, $copyValuesArray);
        }

        if(func_num_args() > 3) {   // El 4º argumento es el número total de páginas
            $paginationInfo = new PaginationInfo(
                $this->paginacion($this->manager->getTablaSelect(), $size, $where, $types, $arrayRefsValues),
                $size,
                $pagina);
        }

        $stmt = static::$link->prepare($consulta);

        try {
            if($types) {
                $stmt->bind_param($types, ...$arrayRefsValues);
            }
            // Copiamos valores para el bind (recordemos que arrayRefsValues contiene referencias a copyValuesArray)

            $stmt->execute();
            $tos = $this->fetchForTos($stmt);
        } finally {
            $stmt->close();
        }

        return $tos;
    }

    /**
     * Mediante un array de filtros permite la eliminación filtrada de to's.
     *
     * @param Filtro[] $filtros
     * @return int Número de elementos eliminados
     * @throws \ArgumentCountError
     */
    public function genericDeleteTosNoTransaction(array $filtros): int {
        if(count($filtros)<1) {
            return 0; // Necesitamos filtros y valores!
        }

        $eliminados = 0;
        // Para cada grupito de filtros (que internamente llamaremos filtro)
        // preparamos un prepared statement distinto (pues el where depende del filtro).

        $this->elaborarWhereParaConsultaConFiltros($filtros, $tipos, $arrayValues, $where);
        $this->refArray($arrayRefsValues, $arrayValues);

        if($where === '') {
            return 0;
        }

        $tablas = array_reverse($this->manager->getTablas());
        foreach($tablas as $tabla) {
            $stmt = parent::elaborarDelete(
                [$tabla],
                $this->manager->getTablaDelete(),
                $where
            );

            try {
                //$this->bind_param_reflection($stmt, $this->tiposPk(), $arrayRefsValues);
                $stmt->bind_param($tipos, ...$arrayRefsValues);
                $stmt->execute();
                $eliminados += $stmt->affected_rows;
            } finally {
                $stmt->close();
            }
        }

        return $eliminados;
    }

    /**
     * Dado un array de prototipos, realiza la inserción de todos esos
     * TO's en la base de datos
     * @param ExposedTO[] $prototypes prototipos de TO's a insertar
     * @return ExposedTO[] los TO's reales que hay en la base tras la inserción
     * @throws \ArgumentCountError
     */
    public function createTosNoTransaction(array $prototypes): array {
        if(count($prototypes) == 0) return [];

        $camposNecesarios = $this->manager->numeroCamposInsert();
        $insertIntos = $this->manager->insertInto($tiposIntos);

        // El ultimo PK para cada to es null en inicio
        $ultimosPk = array_fill(0, count($prototypes), null);

        // Para cada tabla
        foreach($this->manager->getTablas() as $iTabla=> $tabla) {
            $toPrepare = parent::elaborarInsercion(
                $tabla,
                $insertIntos[$iTabla],
                1, [], false
            );
            $stmt = static::$link->prepare($toPrepare);

            try {
                // En arrayValues copiaremos los valores para ejecutar la consulta con cada pk
                $arrayValues = array_fill(0, $camposNecesarios[$iTabla], null);
                $this->refArray($arrayRefsValues, $arrayValues);
                //echo "CONSULTA: $toPrepare\nTIPOS: $tiposIntos[$iTabla]\nVALUES: ".count($arrayRefsValues)."\n";
                $stmt->bind_param($tiposIntos[$iTabla], ...$arrayRefsValues);

                // En cada prototipo
                foreach($prototypes as $iProto=>$proto) {
                    $valores = $this->manager->getValoresInsercionFromTo($iTabla, $proto, $ultimosPk[$iProto]);

                    // Copiamos valores al array
                    for ($i = 0; $i < $camposNecesarios[$iTabla]; $i++) {
                        $arrayValues[$i] = $valores[$i];
                    }

                    $stmt->execute();

                    // Actualizamos ultimo PK para este TO
                    $ultimosPk[$iProto] = $this->manager->obtenerPkUltimaInsercion(
                        $iTabla, static::$link->insert_id, $valores);
                }
            } finally {
                $stmt->close();
            }
        }

        return $this->getTosNoTransaction($this->manager->filtroForPks($ultimosPk));
    }

    /**
     * Guarda en la base los TO's indicados
     * @param TO[] $tos
     */
    public function saveTosNoTransaction($tos): void {
        // Obtenemos los valores del TO
        $countTos = count($tos);

        $valSets = $this->manager->getUpdateSetValuesFromTos($tos);
        $valWheres = $this->manager->getUpdateWhereValuesFromTos($tos);
        $tablas = $this->manager->getTablas();

        $updateSets = $this->manager->updateSet($tiposSets, $copiasSet);
        $updateWheres = $this->manager->updateWhere($tiposWheres, $copiasWhere);

        foreach($tablas as $idxTabla=>$tabla) {
            $consulta = parent::elaborarUpdate(
                $tabla,
                $updateSets[$idxTabla],
                $updateWheres[$idxTabla],
                [], null, false
            );
            $stmt = static::$link->prepare($consulta);

            try {
                $this->refArray($refUpdateSet, $copiasSet[$idxTabla]);
                $this->refArray($refUpdateWhere, $copiasWhere[$idxTabla]);
                $stmt->bind_param(
                    $tiposSets[$idxTabla].$tiposWheres[$idxTabla],
                    ...array_merge($refUpdateSet, $refUpdateWhere) // Primero los campos porque el SET va antes que el WHERE
                );

                for ($idxTo = 0; $idxTo < $countTos; $idxTo++) {
                    // Copiamos los valores a lo que hemos bindeado
                    for ($idxValor = 0; $idxValor < count($copiasSet[$idxTabla]); $idxValor++) {
                        $copiasSet[$idxTabla][$idxValor] = $valSets[$idxTo][$idxTabla][$idxValor];
                    }
                    for ($idxValor = 0; $idxValor < count($copiasWhere[$idxTabla]); $idxValor++) {
                        $copiasWhere[$idxTabla][$idxValor] = $valWheres[$idxTo][$idxTabla][$idxValor];
                    }

                    // Ejecutamos
                    $stmt->execute();
                }
            } finally {
                $stmt->close();
            }
        }
    }

    /**
     * Obtiene un array de to's dado un statement ya ejecutado
     * @param \mysqli_stmt $stmt
     * @return ExposedTO[]
     */
    private final function fetchForTos(&$stmt) {
        $result = $this->manager->arrayVacioForResult();

        // Using ReflectionClass to call bind_result with variable number of arguments
        $this->refArray($resultRefs, $result);
        $reflection = new \ReflectionClass('mysqli_stmt');
        $method = $reflection->getMethod("bind_result");
        $method->invokeArgs($stmt, $resultRefs);

        $tos = [];
        while ($stmt->fetch())  {
            $tos[] = $this->manager->toFromResult($result);
        }

        return $tos;
    }
}