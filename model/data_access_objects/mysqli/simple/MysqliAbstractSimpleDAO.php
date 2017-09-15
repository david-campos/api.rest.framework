<?php

namespace model;

use model\exceptions\AlreadyExistentResourceException;
use model\exceptions\ForeignKeyConstraintException;
use model\exceptions\UncontrolledMysqliException;
use model\simple_dao\IInternalSimpleDAO;

abstract class MysqliAbstractSimpleDAO extends MysqliDAO implements IInternalSimpleDAO {
    /**
     * Envuelve getTosNoTransaction en una transacción
     *
     * @see MysqliSimpleBasicDAO::getTosNoTransaction()
     *
     * @param array|null $filters
     * @param int $pagina
     * @param int $size
     * @param null $paginationInfo
     * @return array
     */
    public final function getTosWithTransaction(?array $filters, int $pagina = 0, int $size = -1, &$paginationInfo=NULL): array {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);

        if(func_num_args() > 3)
            $tos = $this->getTosNoTransaction($filters, $pagina, $size, $paginationInfo);
        else
            $tos = $this->getTosNoTransaction($filters, $pagina, $size);

        static::$link->commit();

        return $tos;
    }

    /**
     * Envuelve genericDeleteTosNoTransaction en una transacción
     *
     * @see MysqliSimpleBasicDAO::genericDeleteTosNoTransaction()
     *
     * @param Filtro[] $filtros
     * @return int
     * @throws ForeignKeyConstraintException
     * @throws UncontrolledMysqliException
     * @throws \Exception
     */
    public final function genericDeleteTosWithTransaction(array $filtros) {
        // Para mejorar la eficiencia será realizada toda la eliminación en una
        // única transacción y con la preparación de un único preparedStatement
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);

        try {
            $eliminados = $this->genericDeleteTosNoTransaction($filtros);
        } catch (\mysqli_sql_exception $e) {
            static::$link->rollback();
            if ($e->getCode() === 1451) {
                throw new ForeignKeyConstraintException('Hay claves foráneas que impiden eliminar el TO');
            } else {
                throw new UncontrolledMysqliException($e);
            }
        } catch (\Exception $ex) {
            static::$link->rollback();
            throw $ex;
        }

        static::$link->commit(); // Commit si no hay excepciones

        return $eliminados;
    }

    /**
     * Envuelve createTosNoTransaction en una transacción
     *
     * @see MysqliSimpleBasicDAO::createTosNoTransaction()
     *
     * @param $prototypes
     * @return TO[]
     * @throws AlreadyExistentResourceException
     * @throws UncontrolledMysqliException
     * @throws \Exception
     */
    public final function createTosWithTransaction($prototypes) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        try {
            $tos = $this->createTosNoTransaction($prototypes);
        } catch(\mysqli_sql_exception $e) {
            static::$link->rollback();
            if ($e->getCode() == 1062) {
                // 1062 es Duplicate entry for key 'PRIMARY'
                throw new AlreadyExistentResourceException('TO ya existente');
            } else {
                // Todos los demas codigos se encapsulan en UncontrolledMysqliException y se lanzan
                throw new UncontrolledMysqliException($e);
            }
        } catch(\Exception $exc) {
            static::$link->rollback();
            throw $exc;
        }
        static::$link->commit();

        return $tos;
    }

    /**
     * Envuelve saveTosNoTransaction en una transacción
     *
     * @see MysqliSimpleBasicDAO::saveTosNoTransaction()
     *
     * @param $tos
     */
    public final function saveTosWithTransaction($tos) {
        static::$link->begin_transaction(MYSQLI_TRANS_START_READ_WRITE);
        $this->saveTosNoTransaction($tos);
        static::$link->commit();
    }

    /**
     * Elabora el where para una consulta con filtros.
     *
     * @see MysqliAbstractSimpleDAO::elaborarConsultaConFiltros()
     *
     * @param array|null $filtros Filtros a emplear, observar que no se tendrán en cuenta las rutas de los filtros
     * @param &_ $types Variable para guardar los tipos empleados en la consulta, o null si ningun filtro es aplicable.
     * Puede emplearse también para indicar un type añadido al principio de la consulta interna
     * @param &_ $copyValuesArray Variable para guardar el array de valores de los filtros
     * @param &_ $where variable donde guardar el where de la consulta si se desea, o null si ningún filtro es aplicable,
     * puede emplearse también para indicar un where añadido al principio de la consulta interna
     * @return void
     */
    public abstract function elaborarWhereParaConsultaConFiltros(?array $filtros, &$types, &$copyValuesArray, &$where);

    /**
     * Elabora una consulta SELECT para los filtros dados
     *
     * @see MysqliToTableManager::whereFiltrosParaSelect()
     * @see MysqliToTableManager::tiposForFiltros()
     *
     * @param array|null $filtros Filtros a emplear, observar que no se tendrán en cuenta las rutas de los filtros
     * @param &_ $types Variable para guardar los tipos empleados en la consulta, o null si ningun filtro es aplicable.
     * Puede emplearse también para indicar un type añadido al principio de la consulta interna
     * @param &_ $copyValuesArray Variable para guardar el array de valores de los filtros
     * @param &_ $where variable donde guardar el where de la consulta si se desea, o null si ningún filtro es aplicable,
     * puede emplearse también para indicar un where añadido al principio de la consulta interna
     * @param string|null $select Select a aplicar o null para aplicar el select genérico
     * @param string|null $limit Limit a aplicar
     * @param string|null $group
     * @param string|null $having
     * @param string|null $order
     * @return string Consulta completa
     */
    public abstract function elaborarConsultaConFiltros(?array $filtros, &$types, &$copyValuesArray, &$where=null,
                                                        $select=null, $limit=null, $group=null, $having=null,
                                                        $order=null): string;

    /**
     * Realiza de forma genérica la obtención de TO's sin elaborar una transacción.
     * Cada grupo de filtros es relacionado al otro a modo de OR, por ejemplo:
     * [[FiltroA, FiltroB],[FiltroC, FiltroD]] = (FiltroA AND FiltroB) OR (FiltroC AND FiltroD)
     *
     * @param array|null $filters Array bidimensional de filtros
     * @param int $pagina número de página (si se desea paginar)
     * @param int $size tamaño de las páginas (si se desea paginar)
     * @param null $paginationInfo referencia donde guardar el número total de páginas (si se desea paginar)
     * @return array array con los TO's obtenidos
     */
    public abstract function getTosNoTransaction(?array $filters, int $pagina = 0, int $size = -1, &$paginationInfo=NULL): array;

    /**
     * Mediante un array bidimensional de filtros permite la eliminación filtrada de to's.
     * Cada grupo de filtros es relacionado al otro a modo de OR, por ejemplo:
     * [[FiltroA, FiltroB],[FiltroC, FiltroD]] = (FiltroA AND FiltroB) OR (FiltroC AND FiltroD)
     *
     * @param Filtro[] $filtros
     * @return int Número de elementos eliminados
     */
    abstract public function genericDeleteTosNoTransaction(array $filtros): int;

    /**
     * Dado un array de prototipos, realiza la inserción de todos esos
     * TO's en la base de datos
     * @param TO[] $prototypes prototipos de TO's a insertar
     * @return TO[] los TO's reales que hay en la base tras la inserción
     * @throws \ArgumentCountError
     */
    abstract public function createTosNoTransaction(array $prototypes): array;

    /**
     * Guarda en la base los TO's indicados
     * @param TO[] $tos
     */
    abstract public function saveTosNoTransaction($tos): void;

    static public final function elegirTipo(PropiedadInterfaz $propiedad) {
        // Convertimos el tipo al formato de bind_param
        $tipoElegido = null;
        foreach($propiedad->getTipos() as $tipo) {
            if (is_subclass_of($tipo, 'model\IMysqliSimpleParser')) {
                /** @var IMysqliSimpleParser $parser */
                $parser = new $tipo();
                $tipoElegido = $parser->getBindParamType();
            } elseif( $tipo === 'boolean' || $tipo === 'integer') {
                $tipoElegido = 'i';
            } elseif ($tipo === 'double') {
                $tipoElegido = 'd';
            } elseif ($tipo === 'string') {
                $tipoElegido = 's';
            } else {
                continue; // Si no se ha elegido tipo, probar siguiente tipo
            }
            break; // Si se ha encontrado uno, acabar
        }
        if($tipoElegido === null) {
            /** @noinspection PhpUndefinedMethodInspection */
            throw new \InvalidArgumentException(
                'MysqliSimpleDAO no puede manejar alguna de las propiedades del to '.
                "debido a su tipo: $propiedad");
        }
        return $tipoElegido;
    }

    /**
     * @return string[] Campos para pks, entrecomillados, con su tabla, y con el label como clave del array.
     */
    public abstract function getCamposParaPks();

    /**
     * Crea un array de referencias a los elementos de un array dado,
     * necesario cuando se llama a bind_param y bind_result
     * @param &_ $refsArray Array que contendrá las referencias al otro array
     * @param array $array Array cuyos elementos se desean referenciar
     */
    protected final function refArray(&$refsArray, array &$array) {
        $refsArray=[];
        foreach ($array as &$value) {
            $refsArray[] = &$value;
        }
    }
}