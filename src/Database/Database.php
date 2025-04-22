<?php
declare(strict_types=1);

namespace openWebX\feijaoVermelho\Database;

use Dotenv\Dotenv;
use openWebX\openCache\Cache;
use Psr\Cache\InvalidArgumentException;
use RedBeanPHP\OODBBean;
use RedBeanPHP\R;
use RedBeanPHP\RedException\SQL;
use RedBeanPHP\ToolBox;


class Database
{
    private static ?ToolBox $toolbox = null;

    // Prevent instantiation or cloning
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}

    public static function init(): ToolBox
    {
        if (self::$toolbox === null) {
            self::loadEnvVariables();

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $_ENV['FEIJAO_DB_HOST'],
                $_ENV['FEIJAO_DB_NAME']
            );

            self::$toolbox = R::setup(
                dsn: $dsn,
                username: $_ENV['FEIJAO_DB_USER'],
                password: $_ENV['FEIJAO_DB_PASS']
            );

            R::useJSONFeatures(true);
            R::freeze(false);
        }

        return self::$toolbox;
    }

    public static function close(): void
    {
        R::close();
    }

    public static function store(OODBBean $bean): void
    {
        try {
            self::init();
            R::store($bean);
        } catch (SQL $e) {
            error_log(sprintf(
                '[%s] store() failed in %s:%d – %s',
                $e::class,
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ));
        }
    }

    /** @return string[] */
    public static function getAllTables(): array
    {
        self::init();
        return R::inspect();
    }

    /** @return string[] */
    public static function getTableFields(string $table): array
    {
        self::init();

        $all = self::getAllTables();
        return in_array($table, $all, true)
            ? R::inspect($table)
            : [];
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function findBean(
        string $tableName,
        string $fieldSelector,
        array $fieldValues = []
    ): ?OODBBean {
        self::init();

        $cacheKey = sha1($tableName . '|' . $fieldSelector . '|' . serialize($fieldValues));
        if (false !== $cached = Cache::get($cacheKey)) {
            return $cached;
        }

        try {
            $bean = R::findOne($tableName, $fieldSelector, $fieldValues);
            Cache::set($cacheKey, $bean);
            return $bean;
        } catch (SQL $e) {
            error_log(sprintf(
                '[%s] findBean() failed in %s:%d – %s',
                $e::class,
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ));
            return null;
        }
    }

    private static function loadEnvVariables(): void
    {
        if (($_ENV['FEIJAO_CONFIGURED'] ?? false) !== true) {
            Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();
            $_ENV['FEIJAO_CONFIGURED'] = true;
        }
    }
}
