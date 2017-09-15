<?php
/**
 * @author David Campos Rodríguez <david.campos.r96@gmail.com>
 */

namespace model;

use controller\session\SessionManager;

class PropiedadInterfaz {
    public const IN_OUT=0;
    public const ONLY_IN=1;
    public const ONLY_OUT=2;

    /** @var string Label de la propiedad de cara a la interfaz */
    private $label;
    /** @var callable Método que se puede llamar para obtener un valor para la propiedad (como tipo básico o array) */
    private $show;
    /** @var callable|null Método que se puede llamar para actualizar el valor de la propiedad (recibiendo como parámetro un tipo básico o array) */
    private $update;
    /** @var bool Si true indica que la propiedad no debe listarse cuando su valor es nulo (en lugar de poner propiedad: null) */
    private $ocultarNull;
    /** @var string[] Tipos de la propiedad, pueden ser 'boolean', 'bool', 'integer', 'int', 'float', 'double',
     * 'string', 'str', 'array', '[]' o el nombre de cualquier clase que implemente IParseable.
     * Pueden indicarse varios tipos básicos aceptados separándolos por "|". Ejemplo: integer|boolean */
    private $tipos;
    /** @var bool Indica si la propiedad es PK, es decir, si identifica (junto con las demás propiedades PK) el to de forma única */
    private $esPk;
    /** @var bool Indica si la propiedad es autogenerada, es decir, si no se requiere su especificación en la inserción */
    private $requerida;
    /** @var null|string Descripción de la propiedad a efectos de interfaz (para ayuda) */
    private $descripcion;
    /** @var bool Especifica que la propiedad se devolverá sólo si se consulta un elemento concreto y no
     * cuando se listan varios */
    private $onlyOnSingle;
    /** @var int[] array de session levels para los que esta propiedad será mostrada */
    private $showTo;
    /** @var int Indica si es de entrada, de salida o de entrada y salida */
    private $direccion;
    /** @var bool Indica si la propiedad admite null o no */
    private $admiteNull;

    /**
     * PropiedadesInterfaz constructor.
     * @param string $label Label de la propiedad de cara a la interfaz
     * @param string $tipo Tipo de la propiedad, puede ser 'boolean', 'bool', 'integer', 'int', 'float', 'double',
     * 'string', 'str', 'array', '[]', 'null' o el nombre de cualquier clase que implemente IParseable.
     * Pueden indicarse varios tipos básicos aceptados separándolos por "|". Ejemplo: integer|boolean.  Si el tipo
     * es un IParseable tiene que se únicamente ese IParseable, no se admiten varios tipos en ese caso.
     * @param string $get Método que se puede llamar para obtener un valor para la propiedad
     * @param null|string $set Método que se puede llamar para setear el valor de la propiedad
     * @param string|null $descripcion Descripción de la propiedad a efectos de imprimir la interfaz
     * @param bool $esPk Indica si la propiedad es PK, es decir, si es lo que identifica (junto con las demás propiedades PK) el TO de forma única.
     * @param bool $requerida Indica si la propiedad es requerida (puede ser no requerida, por ejemplo, si se genera automáticamente)-
     * Una propiedad con set=NULL no es requerida, independientemente del valor de este campo.
     * @param bool $onlyOnSingle Especifica que la propiedad se devolverá sólo si se consulta un elemento concreto y no
     * cuando se listan varios.
     * @param array|int[] $showTo Array de SessionLevels a los que esta propiedad será mostrada
     * @param int $inOut Indica si la propiedad es de entrada, de salida, o de entrada/salida, si tiene setter nulo
     * será sólo de salida
     */
    public function __construct(string $label, string $tipo, string $get, ?string $set=null, ?string $descripcion=null,
                                bool $esPk=false, bool $requerida=true, bool $onlyOnSingle=false,
                                $showTo=SessionManager::SESSION_GROUP_EVERYONE, $inOut=PropiedadInterfaz::IN_OUT) {
        if(preg_match('/[^A-Z0-9-]/i', $label) === 1) {
            throw new \InvalidArgumentException(
                "Una propiedad tiene por label <$label>. Los label de las propiedades sólo pueden ser carácteres ".
                "alfanuméricos o guiones");
        }
        $this->label = $label;
        $this->esPk = $esPk?true:false;
        $this->tipos = $this->procesaTipo($tipo);
        $this->show = $get;
        if($set===null && $esPk) {
            throw new \InvalidArgumentException('Error: Las propiedades PK no pueden tener setter nulo');
        }
        $this->update = $set;
        $this->requerida = ($requerida&&$set!==null)?true:false;
        $this->descripcion = $descripcion;
        $this->onlyOnSingle = $onlyOnSingle;
        $this->showTo = $showTo;
        $this->direccion = ($esPk?PropiedadInterfaz::IN_OUT:($set!==null?$inOut:PropiedadInterfaz::ONLY_OUT));
    }

    public function procesaTipo(string $tipo) {
        $yaIndicados = [];
        $this->admiteNull = false;
        $array = // Mapear todas las alternativas al mismo nombre (el devuelto por gettype)
            array_filter(
                array_map(function($t) {
                    if($t==='bool') return 'boolean';
                    if($t==='int') return 'integer';
                    if($t==='float') return 'double';
                    if($t==='str') return 'string';
                    if($t==='[]') return 'array';
                    if($t==='null') return 'NULL';
                    return $t;
                }, explode('|', $tipo)),
                // Filter function
                function($t) use(&$yaIndicados) {
                    if($t === 'NULL') {
                        // Eliminamos null pero le ponemos que admite nulls
                        $this->admiteNull = true;
                        return false;
                    }
                    $yaIndicado = in_array($t, $yaIndicados);
                    if($yaIndicado) return false;
                    $yaIndicados[] = $t;
                    return true;
                });

        if(count($array) < 1) {
            throw new \InvalidArgumentException('Se necesita algún tipo para la propiedad');
        }

        for($i=0; $i<count($array); $i++) {
            if($array[$i] === 'double' ) {
                // Los double aceptan tambien integer
                if(!in_array('integer', $array)) {
                    $array[] = 'integer';
                }
            } elseif($array[$i] !=='boolean' && $array[$i] !== 'integer' && $array[$i] !== 'string') {
                $t = $array[$i];

                // Filtrar los que no sean válidos
                if ($t !== 'array' && $t !== 'NULL') {
                    // Todos los demás solo pueden ser tipos únicos
                    if (count($array) !== 1) {
                        throw new \InvalidArgumentException('Un IParseable debe ser el ÚNICO tipo de la propiedad.');
                    }

                    // Corrige si te olvidas de poner model\formatters\
                    if (is_subclass_of('model\\formatters\\' . $t, 'model\FormatterToBasicType')) {
                        $array[$i] = 'model\\formatters\\' . $array[$i];
                        $t = 'model\\formatters\\' . $t;
                    }

                    // Si no son Formatters a tipos básicos
                    if(!is_subclass_of($t, 'model\FormatterToBasicType')) {
                        // Puede ser array de IParseable, le quitamos el final para hacer la comprobación
                        if (substr($t, -2) === '[]') {
                            $t = substr($t, 0, -2);
                        }

                        // Corrige si te olvidas de poner model\
                        if (is_subclass_of('model\\' . $t, 'model\IParseable')) {
                            $array[$i] = 'model\\' . $array[$i];
                            $t = 'model\\' . $t;
                        }

                        if (!is_subclass_of($t, 'model\IParseable')) {
                            throw new \InvalidArgumentException('El tipo ' . $t . ' no es un tipo de propiedad válido.');
                        }
                    }
                }
            }
        }

        return $array;
    }

    /**
     * @return string
     */
    public function getLabel(): string {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getShow(): string {
        return $this->show;
    }

    /**
     * @return string|null
     */
    public function getUpdate(): ?string {
        return $this->update;
    }

    /**
     * @return bool
     */
    public function ocultarNull(): bool {
        return $this->ocultarNull;
    }

    /**
     * @return string[]
     */
    public function getTipos() {
        return $this->tipos;
    }

    /**
     * @return bool
     */
    public function esPk(): bool {
        return $this->esPk;
    }

    /**
     * @return bool
     */
    public function esRequerida(): bool {
        return $this->requerida;
    }

    /**
     * @return null|string
     */
    public function getDescripcion() {
        return $this->descripcion;
    }

    /**
     * @return bool
     */
    public function isOnlyOnSingle(): bool {
        return $this->onlyOnSingle;
    }

    /**
     * @param bool $onlyOnSingle
     */
    public function setOnlyOnSingle(bool $onlyOnSingle) {
        $this->onlyOnSingle = $onlyOnSingle;
    }

    private const TEXTOS_DIRECCIONES = [
        self::IN_OUT => '(in/out)',
        self::ONLY_OUT => '(out)',
        self::ONLY_IN => '(in)'
    ];

    /**
     * Imprime la interfaz como array
     * @param bool $onlyIn
     * @param bool $wrappedInOnlyOutput
     * @return array
     * @see ExposedTO::arrayInfoInterfaz()
     */
    public function toArray(bool $onlyIn=false, bool $wrappedInOnlyOutput=false) {
        $array = ['requerida'=>$this->requerida];
        if(!$wrappedInOnlyOutput || $this->onlyOnSingle) {
            $array['def'] = ($wrappedInOnlyOutput ? '' : (
                    $this->esPk ? '(PriKey)' : self::TEXTOS_DIRECCIONES[$this->direccion]
                )).
                ($this->onlyOnSingle ? ' [!lista]' : '');
        }
        $t = $this->tipos[0];
        if(is_subclass_of($t, 'model\IParseable')) {
            // Parseable
            /** @noinspection PhpUndefinedMethodInspection */
            $array['tipos'] = [$t => $t::arrayInfoInterfaz($onlyIn, $this->update === null)];
        } elseif (substr($t, -2) === '[]' &&
            // Array de parseables
            is_subclass_of(substr($t,0,-2), 'model\IParseable')) {
            /** @noinspection PhpUndefinedMethodInspection */
            $array['tipos'] = [$t => substr($t, 0, strlen($t) - 2)::arrayInfoInterfaz($onlyIn, $this->update === null)];
        } elseif (is_a($t, 'model\FormatterToBasicType', true)) {
            /** @noinspection PhpUndefinedMethodInspection */
            $array['tipos'] = (new $t())->getFormatDescription();
        } else {
            // Pues no sé qué es, así que ponlo tal cual
            if(gettype($this->tipos) !== 'array' || count($this->tipos) > 1)
                $array['tipos'] = $this->tipos;
            else
                $array['tipos'] = $this->tipos[0];
        }
        if($this->descripcion) {
            $array['descripcion'] = $this->descripcion;
        }
        return $array;
    }

    // Para debug
    public function __toString() {
        return
            ($this->requerida?'':'[').$this->getLabel().($this->requerida?' ':'] ').
            ($this->esPk ? '(PriKey)' : self::TEXTOS_DIRECCIONES[$this->direccion]).
            ($this->onlyOnSingle?' [!lista]':'').
            ' ('.implode(',', $this->tipos).')'.
            ($this->descripcion?' - '.$this->descripcion:'');
    }

    /**
     * @return int[]
     */
    public function showTo(): array {
        return $this->showTo;
    }

    /**
     * @return int
     */
    public function getDireccion(): int {
        return $this->direccion;
    }

    public function soloLectura(): bool {
        return $this->direccion === self::ONLY_OUT;
    }

    public function soloEscritura(): bool {
        return $this->direccion === self::ONLY_IN;
    }

    /**
     * @return bool
     */
    public function admiteNull(): bool {
        return $this->admiteNull;
    }
}