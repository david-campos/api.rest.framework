<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace model;

/**
 * Require realizado aquí para facilitar el cambio de familia, este archivo
 * no sería necesario si no se estuviese empleando MySQLi, por lo que se
 * referencia aquí y no en requires.php
 *
 * Ver requires.php para más información.
 */
require_once dirname(__FILE__) . '/../../../secure/mysqli_access.php';

use controller\PaginationInfo;
use mysqli;
use mysqli_stmt;

/**
 * Class MysqliDAO, clase abstracta de la que heredan todos los DAO que encapsulen
 * accesos a la base de datos mediante Mysqli.
 *
 * Esta clase maneja dinámicamente el número de instancias de la misma construídas para
 * hacer que varias instancias compartan la misma conexión abierta la base de datos, y la misma
 * sea cerrada cuando no haya más estancias creadas.
 * @package model
 */
abstract class MysqliDAO {
    /** @var null|mysqli Static link to mysqli, we try to keep all the DAO's connected with the same link to the database */
    protected static $link = null;
    /** @var int número de instancias de la clase que existen en memoria */
    private static $instances = 0;

    /**
     * MysqliDAO constructor. El constructor crea el enlace a mysqli si no ha sido
     * creado aún y lo almacena en la variable estática $link
     */
    function __construct() {
        if (!static::$link) {
            // Reporte estricto, mysqli lanzará excepciones ante fallos en las consultas
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            // Realizamos la conexión
            static::$link = new mysqli(MYSQLI_HOST, MYSQLI_USER, MYSQLI_PASS, MYSQLI_DB);
            // Cambiamos el charset a UTF8, en caso contrario json_encode fallaría
            static::$link->set_charset("utf8");
        }
        self::$instances++; // Nueva instancia
    }

    /**
     * Destructor de MysqliDAO, si no hay más instancias de esta clase creadas, la conexión a mysqli
     * será cerrada.
     */
    function __destruct() {
        self::$instances--;
        if (self::$instances < 1 && static::$link) {
            static::$link->close();
            static::$link = null;
        }
    }

    /**
     * Elabora la consulta Mysql con los parámetros indicados
     * @param string $from Contenido de la cláusula FROM
     * @param string $select Contenido de la cláusula SELECT
     * @param string|null $where Contenido de la cláusula WHERE
     * @param string|null $group Contenido de la cláusula GROUP BY
     * @param string|null $having Contenido de la cláusula HAVING
     * @param string|null $order Contenido de la cláusula ORDER BY
     * @param string|null $limit Contenido de la cláusula LIMIT
     * @param bool $preparar true si se desea obtener el mysqli_stmt preparado, false para obtener el string
     * @return mysqli_stmt|string Consulta mysqli correspondiente preparada (o no)
     */
    protected final function elaborarConsulta(string $from, string $select, string $where=null, string $group = null,
                                        string $having = null, string $order=null, string $limit = null,
                                        bool $preparar=true) {
        $result = "SELECT " . $select .
            "\nFROM " . $from;
        if( $where )
            $result .= "\nWHERE " . $where;
        if( $group )
            $result .= "\nGROUP BY " . $group;
        if( $having )
            $result .= "\nHAVING " . $having;
        if( $order )
            $result .= "\nORDER BY " . $order;
        if( $limit )
            $result .= "\nLIMIT " . $limit;
        return ($preparar?static::$link->prepare($result):$result);
    }

    /**
     * Crea la consulta para una inserción, y opcionalmente la prepara
     * @param string $tabla Tabla en la que realizar la inserción
     * @param string[] $into Campos en los que insertar
     * @param int $numberOfRows Número de filas para insertar, en función de este número se crearán varios
     * value lists. Por ejemplo, con un valor de 2 y poniendo en el parametro $into 1 campo: 'VALUES (?) (?)'
     * @param string[] $assignmentList Lista de campos para el ON DUPLICATE KEY UPDATE
     * @param bool $preparar true si se desea obtener el mysqli_stmt preparado, false para obtener el string
     * @return mysqli_stmt|string
     */
    protected final function elaborarInsercion(string $tabla, array $into, int $numberOfRows=1, array $assignmentList=[],
                                         bool $preparar=true) {
        if($numberOfRows < 1)
            throw new \InvalidArgumentException('El numero de filas a insertar no puede ser menor que 1');

        $result = "INSERT INTO $tabla (".
            implode(', ', $into).
            ") VALUES ".
            implode(' ', array_fill(0, $numberOfRows,
                '('. implode(', ',
                    array_fill(0, count($into),
                        '?'
                    )
                ).')'
            ));
        if(count($assignmentList) > 0) {
            $result .= "\nON DUPLICATE KEY UPDATE ".
                implode(', ', array_map(function($colName){
                    return $colName.'=?';
                }, $assignmentList));
        }
        return ($preparar?static::$link->prepare($result):$result);
    }

    /**
     * Elabora una consulta delete, y la prepara si se desea
     * @param string[] $tablas Tablas en las que eliminar, o [] si se desea usar solo una tabla en FROM
     * @param string $from Contenido de la clausula FROM
     * @param string $where Contenido de la clausula WHERE
     * @param array $orderBy Array de columnas para la cláusula ORDER BY
     * @param int|null $limit Si se desea limitar la eliminación, valor para LIMIT
     * @param bool $preparar true si se desea obtener el mysqli_stmt preparado, false para obtener el string
     * @return mysqli_stmt|string
     */
    protected final function elaborarDelete(array $tablas, string $from, string $where, array $orderBy=[], ?int $limit=null,
                                            bool $preparar=true) {
        $result = "DELETE";
        if(count($tablas) > 0) {
            $result .= ' '.implode(',', $tablas);
        }
        $result .= " FROM $from WHERE $where";
        if(count($orderBy) > 0) {
            $result .= "\nORDER BY ".implode(', ', $orderBy);
        }
        if($limit !== null) {
            $result .= "\nLIMIT $limit";
        }
        return ($preparar?static::$link->prepare($result):$result);
    }

    /**
     * Elabora una consulta update y la prepara si se desea
     * @param string $tabla Tabla a actualizar
     * @param string[] $assignmentColNames Nombres de las columnas a las que asignar valores
     * @param null|string $where Contenido de la cláusula WHERE
     * @param array $orderBy Array de columnas para la cláusula ORDER BY
     * @param int|null $limit Si se desea limitar la eliminación, valor para LIMIT
     * @param bool $preparar true si se desea obtener el mysqli_stmt preparado, false para obtener el string
     * @return mysqli_stmt|string
     */
    protected final function elaborarUpdate(string $tabla, array $assignmentColNames, ?string $where=null, array $orderBy=[],
                                            ?int $limit=null, bool $preparar=true) {
        $result = "UPDATE $tabla \nSET ".
            implode(', ', array_map(function($colName){
                return $colName.'=?';
            }, $assignmentColNames));
        if($where) {
            $result .= "\nWHERE $where";
        }
        if(count($orderBy) > 0) {
            $result .= "\nORDER BY ".implode(', ', $orderBy);
        }
        if($limit !== null) {
            $result .= "\nLIMIT $limit";
        }
        return ($preparar?static::$link->prepare($result):$result);
    }

    /**
     * Obtiene el número de páginas consultando la base, requiere un tamaño de página y el where a aplicar con sus valores
     * @param string $tabla la tabla
     * @param int $size
     * @param string|null $where cláusula where para la búsqueda (o null si no la hay)
     * @param string|null $types tipos para el bind_param del where, sólo necesario si se indica where no nulo
     * @param array|null $refWhere array con referencias a los valores para el where, sólo necesario si se indica where no nulo
     * @return int
     */
    protected final function paginacion($tabla, $size, $where=null, $types=null, &$refWhere=null) {
        if($size < 1) return 1; // Si el tamaño es cero o negativo no hay paginación, una sola página

        // Paginas
        $stmt = $this->elaborarConsulta($tabla, 'COUNT(*)', $where);
        try {
            if($where) {
                $stmt->bind_param($types, ...$refWhere);
            }

            $stmt->execute();
            $stmt->bind_result($nTos);
            $stmt->fetch();
        } finally {
            $stmt->close();
        }

        return ceil($nTos / $size);
    }

    /**
     * Genera el texto para la cláusula FROM de una consulta en la que se pretenda hacer unión simple
     * de varias tablas.
     * @param string $tabla nombre de la primera tabla
     * @param string $apodo apodo de la primera tabla
     * @param string[] ...$tablasApodosUsing parámetros para las demás tablas, por cada una debe añadirse tres
     * parámetros: nombre, apodo y contenido de la cláusula using
     * @return string Cadena del tipo "tabla AS apodo JOIN tabla2 AS apodo2 USING(using) JOIN ..."
     */
    protected function tablasJoin(string $tabla, string $apodo, string ...$tablasApodosUsing) {
        if(count($tablasApodosUsing) % 3 == 0) {
            $result = " ".$tabla ." AS ".$apodo." ";
            foreach($tablasApodosUsing as $idx => $tablaApodo) {
                if($idx % 3 == 0)       $result .= " JOIN ".$tablaApodo." ";
                elseif($idx % 3 == 1)   $result .= " AS ".$tablaApodo." ";
                else                    $result .= " USING (".$tablaApodo.") ";
            }
            return $result;
        } else
            throw new \InvalidArgumentException('TablasApodosOn debe contener un número múltiplo de 3 de argumentos.');
    }

    /**
     * Prepara una insercióny bindea los parámetros, puede ser reutilizada por las clases hijas para realizar
     * inserciones de forma sencilla.
     * @param string $tabla tabla en la que se realizará la inserción
     * @param null|string[] $into campos en los que se insertarán valores
     *  las expresiones a realizar sobre ellas en caso de que la clave primaria sea duplicada. Si se deja en nulo
     *  simplemente se lanzará una excepción
     * @param null|string $valuesTypes cadena con los tipos de los valores pasados, tal como se indicaría a bind_param
     * @param null|array $values referencias a las variables con los valores para los campos indicados
     * @param null|array $onDuplicateKeyUpdate si se define, se añadirá un update de valores en caso de clave
     *  primaria repetida, el array debe contener las claves a actualizar como claves y como valores un array con
     *  el tipo de variable y una referencia a la variable que contiene el nuevo valor.
     *  ejemplo: array('nombreCampoString'=>array('s', &$nuevoValorString))
     *
     * @see mysqli_stmt::bind_param()
     * @return mysqli_stmt
     */
    protected function prepareInsert(string $tabla, ?array $into=null, ?string $valuesTypes=null, ?array $values=null,
                                     ?array $onDuplicateKeyUpdate = null): mysqli_stmt {
        if($into && count($into) > 0) {
            $consulta = "INSERT INTO $tabla(" . implode(',', $into) . ') ' .
                'VALUES(' .
                implode(',', array_map(function () {
                    return '?';
                }, $values)) .
                ')';
            if ($onDuplicateKeyUpdate) {
                $consulta .= ' ON DUPLICATE KEY UPDATE ' .
                    implode(', ', array_map(function ($k) {
                        return $k . '=?';
                    }, array_keys($onDuplicateKeyUpdate)));
            }
            $stmt = static::$link->prepare($consulta);

            // Using ReflectionClass to call bind_param with variable number of arguments
            $reflection = new \ReflectionClass('mysqli_stmt');
            $method = $reflection->getMethod("bind_param");
            if ($onDuplicateKeyUpdate) {
                // Añadimos los tipos de on duplicate key
                $valuesTypes .= implode('', array_map(function ($v) {
                    return $v[0];
                }, $onDuplicateKeyUpdate));
            }
            $paramTypesArray = array(&$valuesTypes);
            $refArg = array_merge($paramTypesArray, $values);
            if ($onDuplicateKeyUpdate) {
                $refArg = array_merge($refArg, array_map(function ($v) {
                    return $v[1];
                }, $onDuplicateKeyUpdate));
            }
            $method->invokeArgs($stmt, $refArg);
        } else {
            $stmt = static::$link->prepare("INSERT INTO $tabla() VALUES()");
        }

        return $stmt;
    }

    /**
     * Obtiene el limit para la consulta dada una pagina y un tamaño de pagina
     * @param $pagina
     * @param $size
     * @return string|null El valor para limit o null si size es negativo
     */
    protected final function limit($pagina, $size) {
        if($size > 0) {
            $offset = $pagina * $size;
            return $offset . ',' . $size;
        } else {
            return null;
        }
    }

    /**
     * Método auxiliar que realiza una consulta simple a mysqli que espere una única respuesta. Puede ser usado
     * por las clases que hereden.
     *
     * @see mysqli::prepare()
     * @see mysqli_stmt::bind_param()
     * @see mysqli_stmt::bind_result()
     *
     * @param string $consulta Consulta a realizar, en MYSQL y tal como se introduciría en mysqli::prepare
     * @param string $paramTypes String con los tipos de parámetros que se pasarán a la consulta, tal y como se
     *  introducirían para mysqli_stmt::bind_param
     * @param array $paramValues Array con REFERENCIAS para los valores de los parámetros para bind_param
     * @param array $answerRefs Array de REFERENCIAS a las variables donde se almacenará el resultado de la consulta
     */
    protected function realizarConsultaSimple(string $consulta, string $paramTypes, array $paramValues, array $answerRefs, $transaction=true) {
        if($transaction) static::$link->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
        $stmt = static::$link->prepare($consulta);

        // Using ReflectionClass to call bind_param with variable number of arguments
        $reflection = new \ReflectionClass('mysqli_stmt');
        $method = $reflection->getMethod("bind_param");
        $paramTypesArray = array(&$paramTypes);
        $refArg = array_merge($paramTypesArray, $paramValues);
        $method->invokeArgs($stmt, $refArg);

        $stmt->execute();
        $stmt->bind_result(...$answerRefs);
        $stmt->fetch();
        $stmt->close();
        if($transaction) static::$link->commit();
    }

    /**
     * Realiza una inserción, puede ser reutilizada por las clases hijas para realizar
     * inserciones de forma sencilla.
     * @param string $tabla tabla en la que se realizará la inserción
     * @param null|string[] $into campos en los que se insertarán valores
     *  las expresiones a realizar sobre ellas en caso de que la clave primaria sea duplicada. Si se deja en nulo
     *  simplemente se lanzará una excepción
     * @param null|string $valuesTypes cadena con los tipos de los valores pasados, tal como se indicaría a bind_param
     * @param null|array $values referencias a las variables con los valores para los campos indicados
     * @param null|array $onDuplicateKeyUpdate si se define, se añadirá un update de valores en caso de clave
     *  primaria repetida, el array debe contener las claves a actualizar como claves y como valores un array con
     *  el tipo de variable y una referencia a la variable que contiene el nuevo valor.
     *  ejemplo: array('nombreCampoString'=>array('s', &$nuevoValorString))
     *
     * @see mysqli_stmt::bind_param()
     * @return void
     */
    protected function realizarInsercionSimple(string $tabla, ?array $into=null, ?string $valuesTypes=null,
                                               ?array $values=null, ?array $onDuplicateKeyUpdate = null): void {
        $stmt = $this->prepareInsert($tabla, $into, $valuesTypes, $values, $onDuplicateKeyUpdate);
        try{
            $stmt->execute();
        } finally {
            $stmt->close();
        }
    }
}