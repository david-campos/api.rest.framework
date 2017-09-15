<?php

namespace model;


class Filtro {
    public const CMP_IGUAL = 0;
    public const CMP_NO_IGUAL = 1;
    public const CMP_MAYOR = 2;
    public const CMP_MENOR = 3;
    public const CMP_LIKE = 4;
    public const CMP_NOT_LIKE = 5;
    public const CMP_MAYOR_O_IGUAL = 6;
    public const CMP_MENOR_O_IGUAL = 7;

    /** @var string[] route to the property */
    private $route;
    /** @var string label of the filtered property */
    private $label;
    /** @var mixed[] values to the filtered property to accept */
    private $values;
    /** @var int method of comparison of the property */
    private $comparison;

    /**
     * Filtro constructor.
     * @param string[] $route Ruta a la propiedad (indica a las propiedades de qué TO anidado afecta el filtro),
     * esta ruta va siendo acortada conforme el filtro avanza en el WrapperDAO, por ejemplo, para que sepa cuando
     * filtrar y como.
     * @param string $label Label de la propiedad
     * @param mixed[] $values Valores según los cuales filtrar (se hará un OR entre ellos)
     * @param int $comparison Valor de comparación a realizar sobre la propiedad
     */
    public function __construct($route, $label, $comparison, $values) {
        $this->route = $route;
        $this->label = $label;
        $this->values = $values;
        $this->comparison = $comparison;
    }

    /**
     * @return string[]
     */
    public function getRoute(): array {
        return $this->route;
    }

    /**
     * @param string[] $route
     */
    public function setRoute(array $route) {
        $this->route = $route;
    }

    /**
     * @return string
     */
    public function getLabel(): string {
        return $this->label;
    }

    /**
     * @param string $label
     */
    public function setLabel(string $label) {
        $this->label = $label;
    }

    /**
     * @return mixed[]
     */
    public function getValues(): array {
        return $this->values;
    }

    /**
     * @param mixed[] $values
     */
    public function setValues(array $values) {
        $this->values = $values;
    }

    /**
     * @return int
     */
    public function getComparison(): int {
        return $this->comparison;
    }

    /**
     * @param int $comparison
     */
    public function setComparison(int $comparison) {
        $this->comparison = $comparison;
    }
}