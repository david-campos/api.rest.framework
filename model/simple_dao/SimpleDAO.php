<?php

namespace model\simple_dao;

use controller\ResourceNotFoundException;
use model\ExposedTO;
use model\Filtro;
use model\PropiedadInterfaz;

class SimpleDAO implements ISimpleDAO {
    /** @var string Clase del TO que genera este DAO */
    protected $classTo;
    /** @var string Nombre del TO a efectos de excepciones y DEBUG */
    protected $nameTo;
    /** @var bool Indica si el TO pasado en el constructor es válido, si no lo es la clase no funcionará. */
    protected $toValido;
    /** @var IInternalSimpleDAO DAO real que realizará las acciones aquí implementadas */
    private $internalDao;

    /**
     * MysqliSimpleDAO constructor.
     * @param IInternalSimpleDAO $internalDao El DAO interno a emplear
     */
    public function __construct(IInternalSimpleDAO $internalDao) {
        $this->classTo = $internalDao->getToClass();
        $this->toValido = is_subclass_of($this->classTo, 'model\ExposedTO');
        $this->onlyOnValidTo();
        /** @noinspection PhpUndefinedMethodInspection */
        $this->nameTo = $this->classTo::getName(); // Definido en TO
        $this->internalDao = $internalDao;
    }

    /**
     * Lanza una excepción si el TO no es válido
     */
    protected final function onlyOnValidTo() {
        if(!$this->toValido) {
            throw new \InvalidArgumentException(
                "ATENCIÓN: La clase indicada (" . $this->classTo . ") no hereda de model\ExposedTO. " .
                "El DAO no funcionará correctamente.");
        }
    }

    public function getToClass(): string {
        $this->onlyOnValidTo();
        return $this->classTo;
    }

    public function propiedadEsFiltrable(array $ruta, string $label): bool {
        return $this->internalDao->propiedadEsFiltrable($ruta, $label);
    }

    public function getTo(...$valoresPk): ExposedTO {
        $this->onlyOnValidTo();
        $tos = $this->getTos([$this->filtrosPk($valoresPk)]);
        if(count($tos) > 1)
            throw new \InvalidArgumentException('Los valores de PK introducidos devuelven más de un TO, cómo?');
        elseif(count($tos) === 1)
            return $tos[0];
        else
            return null;
    }

    public function getTos(?array $filters=null, int $pagina = 0, int $size = -1, &$paginationInfo=NULL): array {
        $this->onlyOnValidTo();
        $this->validarFiltros($filters);
        if(func_num_args() > 3)
            return $this->internalDao->getTosWithTransaction($filters, $pagina, $size, $paginationInfo);
        else
            return $this->internalDao->getTosWithTransaction($filters, $pagina, $size);
    }

    public function createTo(ExposedTO $to): ExposedTO {
        $this->onlyOnValidTo();
        return $this->internalDao->createTosWithTransaction([$to])[0];
    }

    public function createTos(array $prototypes): array {
        $this->onlyOnValidTo();
        return $this->internalDao->createTosWithTransaction($prototypes);
    }

    public function deleteTo(&...$valoresPk): int {
        $this->onlyOnValidTo();
        // Llamamos a delete TOs con un sólo grupo de valores de pk
        return $this->deleteTos([$valoresPk]);
    }

    public function deleteTos(array $valoresPk): int {
        $this->onlyOnValidTo();
        return $this->internalDao->genericDeleteTosWithTransaction(
            $this->filtrosPks($valoresPk));
    }

    public function filteredDeleteTos(array $filters): int {
        $this->onlyOnValidTo();
        $this->validarFiltros($filters);
        return $this->internalDao->genericDeleteTosWithTransaction($filters);
    }

    public function saveTo($to): void {
        $this->onlyOnValidTo();
        $this->internalDao->saveTosWithTransaction([$to]);
    }

    public function saveTos($tos): void {
        $this->onlyOnValidTo();
        $this->internalDao->saveTosWithTransaction($tos);
    }

    protected final function labelsPK(): array {
        /** @noinspection PhpUndefinedMethodInspection */
        return array_map(function(PropiedadInterfaz $prop){
            return $prop->getLabel();
        },
            array_filter($this->classTo::interfazPropiedades(),
            function(PropiedadInterfaz $prop){
                return $prop->esPk();
            })
        );
    }

    private function filtrosPks($valoresPk) {
        /** @var Filtro[] $filtros */
        $filtros = [];
        foreach($valoresPk as $grupoValoresPk) {
            $filtros[] = $this->filtrosPk($grupoValoresPk);
        }
        return $filtros;
    }

    private function filtrosPk($valoresPk) {
        $filtros = [];
        $i = 0;
        foreach($this->labelsPK() as $label) {
            $filtros[] = new Filtro([], $label, Filtro::CMP_IGUAL, [$valoresPk[$i]]);
            $i++;
        }
        return $filtros;
    }

    private function validarFiltros(?array $filtros) {
        if(!$filtros) return;
        foreach($filtros as $filtroCompuesto) {
            if(gettype($filtroCompuesto) !== 'array') {
                throw new \InvalidArgumentException(
                    'El parámetro filtros debe contener un array de arrays de filtros, pero el argumento '.
                    'pasado contiene en lugar del segundo array algún elemento de tipo '.gettype($filtroCompuesto));
            }
            foreach($filtroCompuesto as $filtro) {
                if(!$filtro instanceof Filtro) {
                    throw new \InvalidArgumentException(
                        'El array bidimensional de filtros no es de filtros, contiene algún '.
                        get_class($filtro));
                }
            }
        }
    }

}