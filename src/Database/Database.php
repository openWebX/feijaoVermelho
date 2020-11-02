<?php


namespace openWebX\feijaoVermelho\Database;

use Dotenv\Dotenv;
use openWebX\feijaoVermelho\Cache\Cache;
use Psr\Cache\InvalidArgumentException;
use RedBeanPHP\OODBBean;
use RedBeanPHP\R;
use RedBeanPHP\RedException\SQL;
use RedBeanPHP\ToolBox;
use Webmozart\Assert\Assert;


/**
 * Class Database
 *
 * @package openWebX\feijaoVermelho
 */
class Database {

    /**
     * @var ToolBox|null
     */
    private static ?ToolBox $toolbox = null;

    /**
     * @return ToolBox
     */
    public static function init(): ToolBox {
        if (null === self::$toolbox) {
            if (!isset($_ENV['FEIJAO_CONFIGURED']) || $_ENV['FEIJAO_CONFIGURED'] !== true) {
                $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
                $dotenv->load();
            }
            $dsn = 'mysql:host=' . $_ENV['FEIJAO_DB_HOST'] . ';dbname=' . $_ENV['FEIJAO_DB_NAME'];
            $user = $_ENV['FEIJAO_DB_USER'];
            $pass = $_ENV['FEIJAO_DB_PASS'];
            Assert::string($dsn);
            self::$toolbox = R::setup($dsn, $user, $pass);
            R::useJSONFeatures(true);
            R::freeze(false);
        }
        return self::$toolbox;
    }

    /**
     * @return bool
     */
    public static function exit(): bool {
        R::close();
        return true;
    }

    /**
     * @param OODBBean $bean
     */
    public static function store(OODBBean $bean) {
        self::init();
        try {
            R::store($bean);
        } catch (SQL $sQL) {
            echo $sQL->getMessage();
        }
    }

    /**
     * @return array
     */
    public static function getAllTables() : array {
        self::init();
        return R::inspect();
    }

    /**
     * @param string $table
     * @return array
     */
    public static function getTableFields(string $table) : array {
        self::init();
        $currTables = self::getAllTables();
        if (!in_array($table, $currTables)) {
            return [];
        }
        return R::inspect($table);
    }

    /**
     * @param string $tableName
     * @param string $fieldSelector
     * @param $fieldValues
     * @return OODBBean|null
     * @throws InvalidArgumentException
     */
    public static function findBean(string $tableName, string $fieldSelector, ?array $fieldValues) : ?OODBBean {
        self::init();
        $hash = sha1($tableName . $fieldSelector . serialize($fieldValues));
        $ret = null;
        //if ($ret = Cache::get($hash)) {
        //    return $ret;
        //}
        var_dump($fieldValues);
        var_dump($tableName);
        R::fancyDebug(true);
        if (!$fieldValues) {
            $fieldValues = [];
        }
        $ret = R::findOne($tableName, $fieldSelector, $fieldValues);
        Cache::set($hash, $ret);
        return $ret;
    }
}