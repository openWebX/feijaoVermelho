<?php
declare(strict_types=1);

namespace openWebX\feijaoVermelho;

use openWebX\feijaoVermelho\Database\Database;
use openWebX\Strings\Strings;
use openWebX\openTraits\MagicVariables;
use Psr\Cache\InvalidArgumentException;
use RedBeanPHP\OODBBean;
use RedBeanPHP\R;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

/**
 * Trait feijaoVermelho
 * Implements common database handling functionality using RedBeanPHP.
 */
trait FeijaoVermelho
{
    use MagicVariables;

    private ?array    $feijaoFields           = null;
    private ?OODBBean $feijao                 = null;
    private bool      $feijaoNovo             = false;
    private array     $feijaoInspectedTables  = [];
    private bool      $feijaoPrepared         = false;

    public function __call(string $name, array $arguments): static
    {
        $prefix = $this->getMethodPrefix($name)
            ?? throw new RuntimeException("Método mágico '{$name}' não reconhecido.");

        $field = $this->getFieldName($name, $prefix);

        return match ($prefix) {
            'loadBy'   => $this->handleLoadBy($field, $arguments),
            'upsertBy' => $this->handleUpsertBy($field, $arguments),
            'get'      => $this->handleGet($field),
            'set'      => $this->handleSet($field, $arguments[0]),
            'list', 'xlist' => $this->handleList($prefix, substr($name, strlen($prefix)), $arguments[0]),
            default    => throw new RuntimeException("Prefix '{$prefix}' não tratado."),
        };
    }

    public function prepare(): static
    {
        if ($this->feijao) {
            foreach ($this->getProperties() as $property) {
                $n = $property->getName();
                $v = $this->$n;
                $this->feijao->{$n} = (is_array($v) || is_object($v))
                    ? json_encode($v, JSON_THROW_ON_ERROR)
                    : $v;
            }
            $this->feijaoPrepared = true;
        }

        return $this;
    }

    public function save(): bool
    {
        $this->prepare();
        Database::store($this->feijao);
        return true;
    }

    private function handleLoadBy(string $field, array $args): static
    {
        $this->feijao = $this->loadBy($field, $args)
            ?? throw new RuntimeException('Elemento não encontrado.');
        $this->feijaoFields = $this->getFields();
        return $this;
    }

    private function handleUpsertBy(string $field, array $args): static
    {
        $this->feijao = $this->loadBy($field, $args)
            ?? R::dispense($this->getTableName());
        $this->feijao->{$field} = $args[0];
        $this->feijaoNovo        = true;
        $this->feijaoFields      = $this->getFields();
        return $this;
    }

    private function handleGet(string $field): mixed
    {
        return $this->feijaoFields[$field] ?? null;
    }

    private function handleSet(string $field, mixed $value): static
    {
        $this->feijao->{$field} = $value;
        return $this;
    }

    private function handleList(string $prefix, string $suffix, mixed $item): static
    {
        $key = ($prefix === 'list' ? 'own' : 'xown') . $suffix . 'List';
        $this->feijao->{$key}[] = $item;
        return $this;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function loadBy(string $field, array $arguments): ?OODBBean
    {
        [$selectors, $values] = $this->buildFieldSelectors($field);
        return Database::findBean(
            $this->getTableName(),
            implode(' AND ', $selectors),
            $values
        );
    }

    private function buildFieldSelectors(string $fieldName): array
    {
        $sel = $vals = [];
        foreach (explode('_and_', $fieldName) as $f) {
            $sel[]  = "$f = ?";
            $vals[] = $this->{$f};
        }
        return [$sel, $vals];
    }

    private function getTableName(): string
    {
        return strtolower((new ReflectionClass($this))->getShortName());
    }

    private function getFields(): array
    {
        $table = $this->getTableName();
        return $this->feijaoInspectedTables[$table]
            ??= Database::getTableFields($table);
    }

    /** @return ReflectionProperty[] */
    private function getProperties(): array
    {
        return (new ReflectionClass($this))
            ->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);
    }

    private function getMethodPrefix(string $name): ?string
    {
        foreach (['loadBy','upsertBy','get','set','list','xlist'] as $p) {
            if (str_starts_with($name, $p)) {
                return $p;
            }
        }
        return null;
    }

    private function getFieldName(string $name, string $prefix): string
    {
        return Strings::decamelize(substr($name, strlen($prefix)));
    }
}
