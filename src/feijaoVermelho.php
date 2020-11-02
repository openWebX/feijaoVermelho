<?php
namespace openWebX\feijaoVermelho;

use openWebX\feijaoVermelho\Database\Database;
use openWebX\feijaoVermelho\Helper\Strings;
use openWebX\feijaoVermelho\Traits\MagicVariables;
use RedBeanPHP\OODBBean;
use RedBeanPHP\R;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;

/**
 * Class feijaoVermelho
 */
trait feijaoVermelho {
    use MagicVariables;

    /**
     * @var array|null
     */
    private ?array $feijaoFields = null;

    /**
     * @var OODBBean|null
     */
    private ?OODBBean $feijao = null;

    /**
     * @var bool
     */
    private bool $feijaoNovo = false;

    /**
     * @var array|null
     */
    private ?array $feijaoInspectedTables = null;

    /**
     * @var bool
     */
    private bool $feijaoCombinedField = false;

    /**
     * @param string $name
     * @param array $arguments
     * @return $this|null
     */
    public function __call(string $name, array $arguments)
    {

        // loadyByXXX called?
        if (strpos($name, 'loadBy') === 0) {
            $field = Strings::decamelize(substr($name, 6));
            $this->feijao = $this->loadBy($field, $arguments);
            if ($this->feijao) {
                $this->feijaoFields = $this->getFields();
                return $this;
            }
            throw new RuntimeException('Element could not be found!');
        }

        // upsertByXXX calledß
        if (strpos($name, 'upsertBy') === 0) {
            $field = Strings::decamelize(substr($name, 8));
            var_dump($arguments);
            if (count($arguments) === 0) {
                $arguments = null;
            }
            $this->feijao = $this->loadBy($field, $arguments);
            if (!$this->feijao) {
                $this->feijao = R::dispense($this->getTableName());
                if (!$this->feijaoCombinedField) {
                    $this->feijao->$field = $arguments[0];
                }
                $this->feijaoNovo = true;
            }
            $this->feijaoFields = $this->getFields();
            return $this;
        }

        if (strpos($name, 'get') === 0) {
            $field = Strings::decamelize(substr($name, 3));
            if (array_key_exists($field, $this->feijaoFields)) {
                return $this->feijao->$field;
            }
            return null;
        }

        if (strpos($name, 'set') === 0) {
            $field = Strings::decamelize(substr($name, 3));
            $this->feijao->$field = $arguments[0];
            return $this;
        }

        if (strpos($name, 'list') === 0) {
            $field = 'own' . substr($name, 4) . 'List';
            $this->feijao->$field[] = $arguments[0];
            return $this;
        }

        if (strpos($name, 'xlist') === 0) {
            $field = 'xown' . substr($name, 5) . 'List';
            $this->feijao->$field[] = $arguments[0];
            return $this;
        }

        throw new RuntimeException('Magic method "' . $name . '" could not be invoked!?');
    }

    /**
     * @return $this
     */
    public function prepare()
    {
        if ($this->feijao) {
            $properties = $this->getProperties();
            /**
             * @var $property ReflectionProperty
             */
            foreach ($properties as $property) {
                $name = $property->name;
                $value = $this->$name;
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                }
                $this->feijao->$name = $value;
            }
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function save() : bool
    {
        Database::store($this->feijao);
        return true;
    }

    /**
     * @param string $fieldName
     * @param mixed ...$fieldValue
     * @return OODBBean|null
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function loadBy(string $fieldName, ?array ...$fieldValue): ?OODBBean
    {
        $fieldSelectors = [];
        $fieldNames = [];
        $fieldValues = [];
        $fieldSelector = ' ' . $fieldName . ' = ? ';
        $orderBy = ' ';

        if (strpos($fieldName, '_order_by_') !== false) {
            [$fieldName, $orderTmp] = explode('_order_by_', $fieldName);
        }
        if (strpos($fieldName, '_and_') !== false) {
            $tmpNames = explode('_and_', $fieldName);
            foreach ($tmpNames as $tmpName) {
                $fieldNames[] = $tmpName;
                $fieldSelectors[] = ' ' . $tmpName . ' = ? ';
            }
            $fieldSelector = implode(' AND ', $fieldSelectors);
        }
        if (strpos($fieldName, '_or_') !== false) {
            $tmpNames = explode('_or_', $fieldName);
            foreach ($tmpNames as $tmpName) {
                $fieldNames[] = $tmpName;
                $fieldSelectors[] = ' ' . $tmpName . ' = ? ';
            }
            $fieldSelector = implode(' OR ', $fieldSelectors);
        }
        if (count($fieldSelectors)>0) {
            $this->feijaoCombinedField = true;
        }


        foreach ($fieldNames as $selector) {
            $field = Strings::camelize($selector);
            $fieldValues[] = $this->$field;
        }


        return Database::findBean($this->getTableName(), $fieldSelector . $orderBy, $fieldValues);
    }

    /**
     * @return string
     */
    private function getTableName() : string
    {
        [$childClass, $caller] = debug_backtrace(false, 2);
        $class_parts = explode('\\', $caller['class']);
        return strtolower(end($class_parts));
    }

    /**
     * @return array
     */
    private function getFields() : array
    {
        $table = $this->getTableName();
        if (!isset($this->feijaoInspectedTables[$table])) {
            $this->feijaoInspectedTables[$table] = Database::getTableFields($table);
        }
        return $this->feijaoInspectedTables[$table];
    }

    /**
     * @return array|null
     */
    private function getProperties() : ?array
    {
        try {
            $rc = new ReflectionClass($this);
            return $rc->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        } catch (ReflectionException $reflectionException) {
            echo $reflectionException->getMessage();
            return null;
        }
    }
}
