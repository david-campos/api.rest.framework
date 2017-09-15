<?php

namespace model;

class MysqliSimpleWrapper extends MysqliSimpleBasicDAO {
    /** @var MysqliAbstractSimpleDAO[] Las claves son los label correspondientes */
    private $daos;
    /** @var PropiedadInterfaz[] Sólo las que nos resultan interesantes (las que tienen DAO asociado aquí), las claves
     * son los label correspondientes */
    private $props;
    /** @var PropiedadInterfaz[] propiedades de PK del main TO (para poder setearlas en los to hijos, que deberán tener las mismas) */
    private $pks;

    /**
     * MysqliSimpleWrapper constructor.
     * @param string $classTo
     * @param ToToTable|ToToTable[] $toToTables
     * @param array $daos DAOS anidados
     */
    public function __construct($classTo, $toToTables, array $daos) {
        parent::__construct($classTo, $toToTables);

        $propiedades = $this->manager->interfazPropiedades();
        $this->daos = [];
        $this->props = [];

        // Obtenemos los pks
        $this->pks = array_filter($propiedades, function(PropiedadInterfaz $prop){
            return $prop->esPk();
        });

        foreach($propiedades as $prop) {
            if ($prop->soloLectura())
                continue; // No queremos las virtuales

            $ts = $prop->getTipos();
            $t = $ts[0];
            // Propiedad parseable en array?
            if(substr($t, -2) === '[]') {
                $t = substr($t, 0, strlen($t)-2);
                if (count($ts) === 1 && is_subclass_of($t, 'model\IParseable')) {
                    if (is_subclass_of($t, 'model\TO')) {
                        if (key_exists($ts[0], $daos)) {
                            $dao = $daos[$ts[0]];
                            // Puede que el DAO ya se haya añadido en un momento anterior
                            $this->daos[$prop->getLabel()] = $dao;
                            $this->props[$prop->getLabel()] = $prop;
                        }
                    } else {
                        // Lo imprimimos en error log, pero no producimos una excepción por esto
                        error_log(
                            'La propiedad ' . $prop->getLabel() . ' de ' .
                            $this->manager->getClassTo() .
                            ' no es un array de un tipo que extienda de model\TO. Esta propiedad no será' .
                            ' guardada en la base por este Wrapper');
                    }
                }
            }
        }
    }

    function getToClass() {
        return $this->manager->getClassTo();
    }

    public function propiedadEsFiltrable(array $ruta, string $label): bool {
        if(count($ruta) === 0) {
            if(key_exists($label, $this->props)) {
                return !$this->props[$label]->soloLectura();
            }
            return $this->manager->propiedadEsFiltrable($label);
        } else {
            if(key_exists($ruta[0], $this->daos)) {
                $dao = $ruta[0];
                array_splice($ruta, 0, 1);
                return $this->daos[$dao]->propiedadEsFiltrable($ruta, $label);
            } else {
                return false;
            }
        }
    }

    public function getTosNoTransaction(?array $filters = null,
                                           int $pagina = 0, int $size = -1, &$paginationInfo = NULL): array {
        if(func_num_args() === 4) {
            $tos = parent::getTosNoTransaction($filters, $pagina, $size, $paginationInfo);
        } else {
            $tos = parent::getTosNoTransaction($filters, $pagina, $size);
        }

        foreach($tos as $mainTo) {
            foreach ($this->daos as $label => $dao) {
                $filtrosParaPkMain = $this->filtrosParaPk($mainTo);
                $tosAnidados = $dao->getTosNoTransaction([$filtrosParaPkMain]);
                $updateMethod = $this->props[$label]->getUpdate();
                call_user_func([$mainTo, $updateMethod], $tosAnidados); // Seteamos los TO's
            }
        }
        return $tos;
    }

    public function elaborarWhereParaConsultaConFiltros(?array $filtros, &$types, &$copyValuesArray, &$where) {
        $filtrosParaMain = $filtros?$this->filtrosParaMain($filtros):null;
        parent::elaborarWhereParaConsultaConFiltros($filtrosParaMain, $types, $copyValuesArray, $where);

        if($where === null) {
            $where = '';
        }
        if($types === null) {
            $types = '';
        }
        $filtroSize = strlen($types);

        foreach($this->daos as $label => $dao) {
            // Cada hijo elabora su consulta y se la pegamos aquí al final del where
            $filtrosParaDao = $filtros?$this->filtrosParaLabel($filtros, $label):null;
            if(count($filtrosParaDao) > 0) {
                $whereDeInicio = $this->whereDeInicioParaAnidado($dao);
                $typesDao = null;
                $consulta = $dao->elaborarConsultaConFiltros($filtrosParaDao, $typesDao, $copyValuesArrayDao, $whereDeInicio,
                    '\'yes\'', '1');

                if ($typesDao != null) {
                    $where .= ($where !== '' ? ' AND ' : '') . 'EXISTS (' . $consulta . ')';
                    $types .= $typesDao;
                    $copyValuesArray = array_merge($copyValuesArray, $copyValuesArrayDao);
                    $filtroSize += strlen($typesDao);
                }
            }
        }

        if($filtroSize < 1) {
            $types = null;
            if($where === '') {
                $where = null;
            }
        }
    }

    public function genericDeleteTosNoTransaction(array $filtros): int {
        // Solo los que afectan al main, el resto se eliminará en cascada
        $filtros = $this->filtrosParaMain($filtros);

        // Eliminamos los TOS anidados y luego los main
        $n = 0;
        foreach($this->daos as $dao) {
            $n += $dao->genericDeleteTosNoTransaction($filtros);
        }
        $n += parent::genericDeleteTosNoTransaction($filtros);
        return $n;
    }

    /**
     * Dado un array de prototipos, realiza la inserción de todos esos
     * TO's en la base de datos
     * @param TO[] $prototypes prototipos de TO's a insertar
     * @return array los TO's reales que hay en la base tras la inserción
     * @throws \Exception
     */
    public function createTosNoTransaction(array $prototypes): array {
        $tos = [];
        foreach($prototypes as $prototype) {
            // Creamos uno a uno, necesario para crear los anidados
            $to = parent::createTosNoTransaction([$prototype])[0];

            // Obtenemos su pk
            $pk = [];
            foreach($this->pks as $pkProp) {
                $pk[$pkProp->getLabel()] = call_user_func([$to, $pkProp->getShow()]);
            }

            foreach($this->daos as $label => $dao) {
                // Vamos a crear cada uno de los hijos
                $propiedadParaAnidado = $this->props[$label];
                $protosAnidados = $this->obtenerTosAnidadosSeteandoPk($propiedadParaAnidado, $prototype, $pk);
                $tosAnidados = $dao->createTosNoTransaction($protosAnidados);
                call_user_func([$to, $propiedadParaAnidado->getUpdate()], $tosAnidados);
            }

            $tos[] = $to;
        }
        return $tos;
    }

    /**
     * @param PropiedadInterfaz $propiedad
     * @param TO $prototype
     * @param mixed[] $pk
     * @return TO[]
     * @throws \Exception
     */
    private function obtenerTosAnidadosSeteandoPk($propiedad, $prototype, $pk) {
        // Obtenemos prototipos del TO anidado
        /** @var TO[] $protosAnidados */
        $protosAnidados = call_user_func([$prototype, $propiedad->getShow()]);

        foreach($protosAnidados as $protoAnidado) {
            // Seteamos el pk de este
            $protoPropiedades = $protoAnidado::interfazPropiedades();
            foreach ($this->pks as $pkProp) {
                $found = false;
                foreach($protoPropiedades as $protoProp) {
                    if($pkProp->getLabel() === $protoProp->getLabel()) {
                        call_user_func([$protoAnidado, $protoProp->getUpdate()], $pk[$pkProp->getLabel()]);
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    throw new \Exception(
                        'Imposible crear el TO anidado ' . $protoAnidado->getName() . ', el PK del padre ' .
                        'no aparece completo entre las propiedades del anidado (falta ' . $pkProp->getLabel() . ')');
                }
            }
        }

        return $protosAnidados;
    }

    /**
     * Guarda en la base los TO's indicados
     * @param TO[] $tos
     */
    public function saveTosNoTransaction($tos): void {
        parent::saveTosNoTransaction($tos);
        foreach($tos as $to) {
            // Obtenemos su pk
            $pk = [];
            $filtroPk = [];
            foreach($this->pks as $pkProp) {
                $pk[$pkProp->getLabel()] = call_user_func([$to, $pkProp->getShow()]);
                $filtroPk[] =
                    new Filtro([], $pkProp->getLabel(), Filtro::CMP_IGUAL, [$pk[$pkProp->getLabel()]]);
            }

            foreach($this->daos as $label => $dao) {
                // Eliminamos todos para crearlos de nuevo
                $dao->genericDeleteTosNoTransaction([$filtroPk]);

                // Vamos a crear cada uno de los hijos de nuevo
                $propiedadParaAnidado = $this->props[$label];
                $protosAnidados = $this->obtenerTosAnidadosSeteandoPk($propiedadParaAnidado, $to, $pk);
                $tosAnidados = $dao->createTosNoTransaction($protosAnidados);
                // Se lo ponemos al to en lugar de los viejos
                call_user_func([$to, $propiedadParaAnidado->getUpdate()], $tosAnidados);
            }
        }
    }

    private function filtrosParaMain($filters) {
        $filtros = [];
        foreach($filters as $filtroCompuesto) {
            $filtroCompuestoResultante = [];
            /** @var Filtro $filtro */
            foreach($filtroCompuesto as $filtro) {
                if($filtro->getRoute() === []) {
                    $filtroCompuestoResultante[] = $filtro;
                }
            }
            $filtros[] = $filtroCompuestoResultante;
        }
        return $filtros;
    }

    private function filtrosParaLabel($filters, $label) {
        $filtros = [];
        // Al inferior le pasamos los de PK de main y los que tienen su label
        foreach($filters as $filtroCompuesto) {
            $filtroCompuestoResultante = [];
            /** @var Filtro $filtro */
            foreach($filtroCompuesto as $filtro) {
                $ruta = $filtro->getRoute();
                if( (count($ruta) === 0 && $this->esPropPk($label)) ||
                    $ruta[0] === $label) {
                    if(count($ruta) > 0) array_splice($ruta, 0, 1);
                    $filtro->setRoute($ruta);
                    $filtroCompuestoResultante[] = $filtro;
                }
            }
            $filtros[] = $filtroCompuestoResultante;
        }
        return $filtros;
    }

    /**
     * @param TO $mainTo Debe ser una instancia del TO que maneja
     * el DAO principal
     * @return Filtro[]
     */
    private function filtrosParaPk($mainTo) {
        $filtros = [];
        foreach ($this->pks as $pkProp) {
            $valor = call_user_func([$mainTo, $pkProp->getShow()]);
            $filtros[] = new Filtro([], $pkProp->getLabel(), Filtro::CMP_IGUAL, [$valor]);
        }
        return $filtros;
    }

    private function esPropPk($label) {
        foreach($this->manager->interfazPropiedades() as $prop) {
            if( $prop->getLabel() === $label) {
                return $prop->esPk();
            }
        }
        return false;
    }

    private function whereDeInicioParaAnidado(MysqliAbstractSimpleDAO $daoAnidado) {
        // Ponemos el PK en la tabla y en el anidado
        $wheres = [];
        $camposPks = $this->getCamposParaPks();
        $anidadoCamposPks = $daoAnidado->getCamposParaPks();
        foreach($camposPks as $label=>$pk) {
            $wheres[] =
                $pk.'='.$anidadoCamposPks[$label];
        }
        return implode(' AND ', $wheres);
    }
}