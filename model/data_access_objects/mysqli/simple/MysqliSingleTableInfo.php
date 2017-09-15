<?php

namespace model;

class MysqliSingleTableInfo {
    private const COMPARISONS = [
        Filtro::CMP_IGUAL => '=',
        Filtro::CMP_NO_IGUAL => '<>',
        Filtro::CMP_MAYOR => '>',
        Filtro::CMP_MAYOR_O_IGUAL => '>=',
        Filtro::CMP_MENOR => '<',
        Filtro::CMP_MENOR_O_IGUAL => '<=',
        Filtro::CMP_LIKE => 'LIKE',
        Filtro::CMP_NOT_LIKE => 'NOT LIKE'
    ];

    /** @var string Nombre de la tabla asociada al DAO, no se añaden comillas o escape de algún tipo */
    private $tabla;
    /** @var array[] Las claves son los label de la propiedades PK del TO y los valores arrays que indican el tipo
     * para bind_param y el campo en la tabla */
    private $camposPk;
    /** @var array[] Las claves son los label de la propiedades no PK del TO y los valores arrays que indican el tipo
     * para bind_param, el campo en la tabla y si son requeridos */
    private $campos;

    /**
     * MysqliTableInfo constructor.
     * @param string $tabla
     * @param array[] $camposPk
     * @param array[] $campos
     */
    public function __construct($tabla, array $camposPk, array $campos) {
        $this->tabla = $tabla;
        if(count(array_intersect_key($camposPk, $campos)) > 0) {
            throw new \InvalidArgumentException('Hay labels repetidas entre camposPk y campos');
        }
        if(count($camposPk) < 1) {
            throw new \InvalidArgumentException('Error, se ha intentado crear '.get_class($this).' con camposPk vacío '.
                'para la tabla '.$tabla.'; los campos (no pk) indicados son: ['.implode(', ', $campos).']');
        }
        $this->camposPk = $camposPk;
        $this->campos = $campos;
    }

    /**
     * Indica si este TableInfo conoce la propiedad o no
     * @param $label string Label de la propiedad
     * @return bool true si la conoce, false si no
     */
    public function conocePropiedad($label): bool {
        return key_exists($label, $this->camposPk) || key_exists($label, $this->campos);
    }

    /**
     * @return string
     */
    public function tabla(): string {
        return $this->tabla;
    }

    // SELECT //////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Devuelve el string para el select de este SimpleDao (primero los campos de PK y luego el resto de campos)
     * @return string
     */
    public final function select(): string {
        return implode(', ',
            array_map(function($element) {
                return '`'.$this->tabla.'`.`'.$element[1].'`'; // Cogemos el nombre del campo
            },
                array_merge(
                    $this->camposPk,
                    $this->campos
                )
            )
        );
    }

    /**
     * Crea un array con tantos valores null como campos tiene la tabla,
     * muy útil para bind_result (tras crear un array de referencias a el)
     * @return array
     */
    public final function arrayVacioForResult() {
        return array_fill(0, count($this->camposPk)+count($this->campos), null);
    }

    /**
     * Devuelve los nombres de los campos PK
     * @return string[]
     */
    public final function camposPk() {
        $campos = [];
        foreach($this->camposPk as $label=>$array)
            $campos[$label] = $array[1];
        return $campos;
    }

    // INSERT //////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Nombres de los campos para insertar en la tabla
     * @param string $tipos Variable donde guardar los tipos del into
     * @return string[]
     */
    public final function insertInto(&$tipos) {
        $tipos = '';
        $campos = array_merge(
            array_map(function($elemento) use(&$tipos) {
                $tipos .=  $elemento[0];
                return $elemento[1];
            }, array_filter($this->camposPk,
                function($campo){
                    return $campo[2]; // Indica si es requerido
                })), // Labels pk requeridos
            array_map(function($elemento) use(&$tipos) {
                $tipos .=  $elemento[0];
                return $elemento[1];
            }, $this->campos)); // Labels no pk

        return array_map(function($element){
                return '`'.$element.'`'; // El campo es el primer elemento
            }, $campos);
    }

    /**
     * Devuelve el número de campos que tiene el insert generado por insertInto
     *
     * @see MysqliSimpleBasicDAO::insertInto()
     *
     * @return int
     */
    public final function numeroCamposInsert() {
        return count($this->labelsNoPk())+count($this->labelsPkReq());
    }

    /**
     * Dado que al insertar hay campos del PK que no son requeridos,
     * este método facilita obtener los pk del ultimo elemento insertado y en el orden
     * correcto
     * @param mixed $last_insert_id valor que devuelve la llamada a last_insert_id
     * @param array $valoresInsertados valores que se han insertado
     * @return array valores de pk en el orden correcto
     */
    public final function obtenerPkUltimaInsercion($last_insert_id, $valoresInsertados): array {
        $pk = [];
        $i=0;
        foreach($this->labelsPk() as $label) {
            if($this->camposPk[$label][2]) { // Si la propiedad es requerida
                $pk[$label] = $valoresInsertados[$i];
                $i++;
            } else {
                // El parámetro no requerido, debe obtenerse de last_insert_id
                $pk[$label] = $last_insert_id;
            }
        }
        return $pk;
    }

    // UPDATE //////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Devuelve los nombres de los campos para actualizar los campos no PK de la tabla
     * @param string $tipos Variable donde guardar los tipos para el updateSet
     * @return string[]
     */
    public final function updateSet(&$tipos) {
        $tipos = '';
        return array_map(function($element) use(&$tipos) {
            $tipos .= $element[0];
            return '`'.$element[1].'`';
        }, $this->campos);
    }

    /**
     * Genera un array vacío para guardar el update set
     * @return array
     */
    public final function arrayVacioParaUpdateSet(): array {
        return array_fill(0, count($this->campos), null);
    }

    /**
     * Devuelve la cadena correspondiente a la cláusula WHERE del update de TO's
     * @param string $tipos Variable donde guardar los tipos para el updateWhere
     * @return string
     */
    public final function updateWhere(&$tipos) {
        $tipos = '';
        return implode(' AND ',
            array_map(function($array) use (&$tipos) {
                $tipos .= $array[0];
                return "`".$array[1]."`=?";
            }, $this->camposPk));
    }

    /**
     * Genera un array vacío para guardar el update where
     * @return array
     */
    public final function arrayVacioParaUpdateWhere(): array {
        return array_fill(0, count($this->camposPk), null);
    }

    // GENERICOS ///////////////////////////////////////////////////////////////////////////////////////////////////////
    // GENERICOS - LABELS //

    /**
     * Devuelve un array que sirve como filtro de PK, es usado para llamar a las funciones
     * que requieren filtros cuando se quiere obtener un elemento por dado su PK.
     * @return string[] los label de los PK del TO
     */
    public final function labelsPk(): array {
        return array_keys($this->camposPk);
    }

    /**
     * Devuelve un array que sirve como filtro de los PK requeridos,
     * se puede utilizar para la inserción
     * @return string[] los label de las propiedades requeridas
     */
    public final function labelsPkReq() {
        return array_filter(
            $this->labelsPk(),
            function($label) {
                return $this->camposPk[$label][2]; // El tercer campo indica si es requerida
            });
    }

    /**
     * Devuelve un array de labels de los campos no pk
     * @return string[] los label de los no PK del TO
     */
    public final function labelsNoPk(): array {
        return array_keys($this->campos);
    }

    /**
     * Coge un array the labels y devuelve un array con los campos de la tabla
     * correspondientes.
     * @param string[] $labels Labels de propiedades del TO cuyos campos se desea obtener
     * @return string[] Campos de la tabla correspondientes
     */
    public final function labelsToCampos($labels) {
        $campos = [];
        foreach($labels as $label) {
            $campos[] = $this->labelToCampo($label);
        }
        return $campos;
    }

    /**
     * Devuelve el campo correspondiente a un label de propiedad dado
     * @param $label string label de una propiedad
     * @return string|null el campo correspondiente o null si no se encuentra
     */
    public final function labelToCampo($label) {
        if(key_exists($label, $this->camposPk)) {
            return $this->camposPk[$label][1];
        } elseif (key_exists($label, $this->campos)) {
            return $this->campos[$label][1];
        } else {
            return null;
        }
    }

    // FILTROS /////////////////////////////////////////////////////////////////////////////////////////////////////////
    /**
     * Prepara la cláusula where según los filtros indicados. IGNORA LAS RUTAS DE LOS FILTROS
     * Los campos son devueltos en el orden en que se pasaron los label.
     * @param array $filtros filtros con los que elaborar el where array bidimensional
     * @param string $tipos Variable donde se guardarán los tipos para el where generado (para bind_param)
     * @param array $valuesArray Variable donde se guardará el array de valores
     * @param null $numCampos Referencia a la variable en la que guardar el número de campos
     * @return string la cláusula where (sin where, únicamente los campos, por ejemplo: `campoA`=? AND `campoB`=?
     */
    public final function whereFiltros($filtros, &$tipos, &$valuesArray, &$numCampos) {
        $numCampos = 0;
        $whereFiltrosCompuestos = [];
        $valuesArray = [];
        $tipos = '';
        foreach($filtros as $filtroCompuesto) {
            if(gettype($filtroCompuesto) !== 'array')
                throw new \InvalidArgumentException('El parametro filtros debe contener un array de arrays de filtros');

            $whereFiltros = [];
            foreach($filtroCompuesto as $filtro) {
                if(!$filtro instanceof Filtro)
                    throw new \InvalidArgumentException('El parametro filtros debe contener un array de arrays de filtros');

                if( !(key_exists($filtro->getLabel(), $this->camposPk) ||
                        key_exists($filtro->getLabel(), $this->campos)) )
                    continue; // Ignoramos los que no tienen campo asociado

                // Obtenemos datos del filtro
                $label = $filtro->getLabel();
                $campo = $this->labelToCampo($label);
                $comparison = self::COMPARISONS[$filtro->getComparison()];

                $values = $filtro->getValues();
                $isNull = in_array(null, $values, true);
                $values = array_filter($values, function($v){return $v!==null;}); // Quitamos nulls

                // Obtenemos el where de cada campo
                $whereCampos = array_map(function () use ($campo, $comparison) {
                    return "`".$this->tabla."`.`$campo` $comparison ?";
                }, $values);

                if($isNull) {
                    if($comparison === self::COMPARISONS[Filtro::CMP_IGUAL])
                        $whereCampos[] = "`".$this->tabla."`.`$campo` IS NULL";
                    else if($comparison === self::COMPARISONS[Filtro::CMP_NO_IGUAL])
                        $whereCampos[] = "`".$this->tabla."`.`$campo` IS NOT NULL";
                }

                $valuesArray = array_merge($valuesArray, $values);

                // Añadimos el where del filtro
                $whereFiltro = implode(' OR ', $whereCampos);
                if(count($whereCampos) > 1) {
                    $whereFiltro = "($whereFiltro)";
                }
                $whereFiltros[] = $whereFiltro;

                $numCampos += count($values); // Sumamos el numero de campos

                if(!$isNull) {
                    // Obtenemos el tipo
                    if (key_exists($label, $this->camposPk)) {
                        $tipo = $this->camposPk[$label][0];
                    } else {
                        $tipo = $this->campos[$label][0];
                    }

                    // Lo repetimos por el numero de valores
                    for ($i = 0; $i < count($values) - 1; $i++) { // Repetimos uno de menos porque el primero ya está puesto!
                        $tipo .= $tipo;
                    }

                    $tipos .= $tipo; // Añadimos ese string de tipos a tipos
                }
            }

            if(count($whereFiltros) > 0) {
                $whereFiltroCompuesto = implode(' AND ', $whereFiltros);
                if(count($whereFiltros) > 1) {
                    $whereFiltroCompuesto = "($whereFiltroCompuesto)";
                }
                $whereFiltrosCompuestos[] = $whereFiltroCompuesto;
            }
        }
        $resultado = implode(' OR ', $whereFiltrosCompuestos);
        if(count($whereFiltrosCompuestos) > 1) {
            $resultado = "($resultado)";
        }
        return $resultado;
    }
}