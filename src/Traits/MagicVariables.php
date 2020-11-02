<?php


namespace openWebX\feijaoVermelho\Traits;


use Exception;
use RuntimeException;
use Throwable;

trait MagicVariables {

    /**
     * @param string $name
     * @param $value
     * @return MagicVariables
     */
    public function __set(string $name, $value) : self {
        $this->$name = $value;
        return $this;
    }

    /**
     * @param string $name
     * @return mixed|null
     */
    public function __get(string $name) {
        return ($this->$name ?? null);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return MagicVariables|mixed|null
     * @throws Exception
     */
    public function __call(string $name, array $arguments) {
        $action = substr($name, 0, 3);
        $var = lcfirst(substr($name, 3));
        if ($action === 'set') {
            if ($var !== '') {
                $this->$var = $arguments[0];
                return $this;
            }
        } elseif ($action === 'get') {
            if ($var !== '') {
                return $this->$var;
            }
        }

        throw new RuntimeException('Magic method "' . $name . '" could not be invoked!?');
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return MagicVariables|mixed|null
     * @throws Exception
     */
    public static function __callStatic(string $name, array $arguments) {
        try {
            $action = substr($name, 0, 3);
            $var = lcfirst(substr($name, 3));
            if ($action == 'set') {
                if ($var !== '') {
                    self::${$var} = $arguments[0];
                    return true;
                }
            } elseif ($action == 'get') {
                if ($var !== '') {
                    return self::${$var};
                }
            }
            return self::${$name}(...$arguments);
        } catch (Throwable $throwable) {
            var_dump($throwable->getMessage());
            return null;
        }
    }

    /**
     * @param string $name
     * @return bool
     */
    public function __isset(string $name) : bool {
        return isset($this->values[$name]);
    }

    /**
     * @param string $name
     * @return MagicVariables
     */
    public function __unset(string $name) : self {
        if (isset($this->values[$name])) {
            unset($this->values[$name]);
        }
        return $this;
    }
}