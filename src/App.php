<?php
namespace Otus;

use Symfony\Component\Yaml\Yaml;
use Otus\ActiveRecord\AttributeValue;

final class App
{
    private $config;
    private $router;
    private $pdo;
    private $response;

    private const INIT_ROW_COUNTS = 2;

    public function __construct()
    {
        $this->config = Yaml::parseFile(dirname(__DIR__) . '/config.yml');
        $this->response = new \stdClass();
        $this->response->error = [];
        $this->response->payload = [];

        $this->router = new \AltoRouter();
        $this->mapRoutes();

        $this->pdo = new \PDO(
            $this->config['db']['driver'] . ':host=' . $this->config['db']['host'] . ';dbname=' . $this->config['db']['dbname'],
            $this->config['db']['username'],
            $this->config['db']['password']
        );
    }

    public function run(): void
    {
        // match current request url
        $match = $this->router->match();

        // call closure or throw 404 status
        if (is_array($match) && is_callable($match['target'])) {
            call_user_func_array($match['target'], $match['params']);
        } else {
            // no route was matched
            $this->addResponseError('Incorrect request');
            $this->showResponse();
        }
    }

    /**
     * In console mode is used
     * @param string $migration
     */
    public function migrate(string $migration = 'init'): void
    {
        // refresh DB
        if ($migration == 'init') {
            $this->initDB();
            AttributeValue::addRandomRows($this->pdo, self::INIT_ROW_COUNTS);
        }
    }

    private function mapRoutes(): void
    {
        // map homepage
        $this->router->map('GET', '/', function () {
            $this->addResponsePayload('App db_random_string');

            $this->showResponse();
        });

        $this->router->map('GET', '/init', function () {
            if ($this->initDB()) {
                $this->addResponsePayload('DB initialization successful');
            } else {
                $this->addResponseError('DB initialization error');
            }

            $this->showResponse();
        });

        $this->router->map('GET', '/list', function () {
           $list = AttributeValue::getList($this->pdo);
           $result = [];

           foreach ($list as $dbItem) {
               $result[] = [
                   'id' => $dbItem->getId(),
                   'attribute_id' => $dbItem->getAttributeId(),
                   'film_id' => $dbItem->getFilmId(),
                   'val_text' => $dbItem->getValText(),
                   'val_numeric' => $dbItem->getValNumeric(),
                   'val_bool' => $dbItem->getValBool(),
                   'val_date' => $dbItem->getValDate(),
               ];
           }

           $this->addResponsePayload($result);

           $this->showResponse();
        });

        // Sync method to add new rows
        $this->router->map('POST', '/add/[i:count]', function ($count) {
            $result = AttributeValue::addRandomRows($this->pdo, $count);

            if ($result === true) {
                $this->addResponsePayload("Request to add $count rows executed");
            } else {
                $this->addResponseError('Error is occurred during attempt to add new rows');
            }

            $this->showResponse();
        });

    }

    /**
     * @return bool
     */
    private function initDB(): bool
    {
        $script = dirname(__DIR__) . '/sql/init.sql';
        $initSql = file_get_contents($script);

        if ($this->pdo->exec($initSql) === false) {
            return false;
        }

        return true;
    }

    private function addResponsePayload($payload)
    {
        $this->response->payload[] = $payload;
    }

    private function addResponseError($error)
    {
        $this->response->error[] = $error;
    }

    private function showResponse(): void
    {
        header("Content-Type: application/json; charset=UTF-8");
        print json_encode($this->response);
    }
}