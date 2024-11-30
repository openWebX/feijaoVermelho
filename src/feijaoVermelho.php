<?php
namespace openWebX\feijaoVermelho;

use openWebX\feijaoVermelho\Database\Database;
use openWebX\Strings\Strings;
use openWebX\openTraits\MagicVariables;
use Psr\Cache\InvalidArgumentException;
use RedBeanPHP\OODBBean;
use RedBeanPHP\R;
use ReflectionClass;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

/**
 * Trait feijaoVermelho
 * Implements common database handling functionality using RedBeanPHP.
 */
trait feijaoVermelho {
    use MagicVariables;

    private ?array $feijaoFields = null;
    private ?OODBBean $feijao = null;
    private bool $feijaoNovo = false;
    private ?array $feijaoInspectedTables = null;
    private bool $feijaoCombinedField = false;
    private bool $feijaoPrepared = false;

    /**
     * Magic method for dynamic function calls.
     * Handles 'loadBy', 'upsertBy', 'get', 'set', 'list', and 'xlist' prefixes.
     *
     * @param string $name
     * @param array $arguments
     * @return $this|null
     * @throws InvalidArgumentException
     */
    public function __call(string $name, array $arguments) {
        $prefix = $this->getMethodPrefix($name);
        if (!$prefix) {
            throw new RuntimeException("Magic method '{$name}' could not be invoked!?");
        }

        $field = $this->getFieldName($name, $prefix);

        return match ($prefix) {
            'loadBy' => $this->handleLoadBy($field, $arguments),
            'upsertBy' => $this->handleUpsertBy($field, $arguments),
            'get' => $this->handleGet($field),
            'set' => $this->handleSet($field, $arguments),
            'list', 'xlist' => $this->handleList($prefix, $name, $arguments),
            default => throw new RuntimeException("Unhandled prefix '{$prefix}' in magic method."),
        };
    }

    /**
     * Prepares the current object's properties for saving.
     *
     * @return $this
     */
    public function prepare(): static {
        if ($this->feijao) {
            foreach ($this->getProperties() as $property) {
                $name = $property->name;
                $value = $this->$name;
                $this->feijao->$name = is_array($value) || is_object($value) ? json_encode($value) : $value;
            }
            $this->feijaoPrepared = true;
        }
        return $this;
    }

    /**
     * Saves the current state of the object to the database.
     *
     * @return bool
     */
    public function save(): bool {
        $this->prepare();
        Database::store($this->feijao);
        return true;
    }

    /**
     * Loads a record by a specific field and its value.
     *
     * @param string $fieldName
     * @param array|null ...$fieldValue
     * @return OODBBean|null
     * @throws InvalidArgumentException
     */
    private function loadBy(string $fieldName, ?array ...$fieldValue): ?OODBBean {
        [$selectors, $values] = $this->buildFieldSelectors($fieldName);
        return Database::findBean(
            $this->getTableName(),
            implode(' AND ', $selectors),
            $values
        );
    }

    /**
     * Gets the table name for the current class.
     *
     * @return string
     */
    private function getTableName(): string {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1];
        return strtolower(basename(str_replace('\\', '/', $caller['class'])));
    }

    /**
     * Retrieves the fields of the table associated with this object.
     *
     * @return array
     */
    private function getFields(): array {
        $table = $this->getTableName();
        return $this->feijaoInspectedTables[$table] ??= Database::getTableFields($table);
    }

    /**
     * Gets the properties of the current class.
     *
     * @return array|null
     */
    private function getProperties(): ?array {
        try {
            return (new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
        } catch (ReflectionException $e) {
            echo $e->getMessage();
            return null;
        }
    }

    /**
     * Extracts the prefix from the method name.
     *
     * @param string $name
     * @return string|null
     */
    private function getMethodPrefix(string $name): ?string {
        foreach (['loadBy', 'upsertBy', 'get', 'set', 'list', 'xlist'] as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return $prefix;
            }
        }
        return null;
    }

    /**
     * Extracts the field name from the method name.
     *
     * @param string $name
     * @param string $prefix
     * @return string
     */
    private function getFieldName(string $name, string $prefix): string {
        return Strings::decamelize(substr($name, strlen($prefix)));
    }

    private function handleLoadBy(string $field, array $arguments): static {
        $this->feijao = $this->loadBy($field, $arguments);
        if (!$this->feijao) {
            throw new RuntimeException('Element could not be found!');
        }
        $this->feijaoFields = $this->getFields();
        return $this;
    }

    private function handleUpsertBy(string $field, array $arguments): static {
        $this->feijao = $this->loadBy($field, $arguments) ?: R::dispense($this->getTableName());
        $this->feijao->$field = $arguments[0];
        $this->feijaoNovo = true;
        $this->feijaoFields = $this->getFields();
        return $this;
    }

    private function handleGet(string $field) {
        return $this->feijaoFields[$field] ?? null;
    }

    private function handleSet(string $field, array $arguments): static {
        $this->feijao->$field = $arguments[0];
        return $this;
    }

    private function handleList(string $prefix, string $name, array $arguments): static {
        $listKey = ($prefix === 'list' ? 'own' : 'xown') . substr($name, strlen($prefix)) . 'List';
        $this->feijao->$listKey[] = $arguments[0];
        return $this;
    }

    private function buildFieldSelectors(string $fieldName): array {
        $selectors = [];
        $values = [];
        foreach (explode('_and_', $fieldName) as $field) {
            $selectors[] = "$field = ?";
            $values[] = $this->$field;
        }
        return [$selectors, $values];
    }
}
