<?php

namespace src\module\Page;

use src\model\Api\Api;
use src\model\Response\ResponseModel;

class Page
{
    public function route(): ResponseModel
    {
        $route = Page::readRoute();

        $api = new Api();

        $api->setRoute($route);
        $api->setGet($_GET);
        $api->setPost($_POST);
        $api->setFiles($_FILES);

        try{
            return $api->send();
        } catch (\Throwable $e) {
            return self::createExceptionResponse($e);
        }
    }

    public static function createExceptionResponse(\Throwable $e = null): ResponseModel
    {
        $response = new ResponseModel();
        $response->responseMessage = 'An error has occured. Check logs';
        $response->headers->headers[] = 'Content-Type: application/json';
        $response->status = 500;

        if ($e !== null) {
            self::writeLog($e);
        }

        return $response;
    }

    private static function writeLog(\Throwable $e): void
    {
        $log = sprintf('[%s] %s: %s in %s on line %s', date('Y-m-d H:i:s'), $e::class, $e->getMessage(), $e->getFile(), $e->getLine());

        // Write into logs/error.log
        $logFolder = __DIR__ . '/../../../logs/';

        if (!is_dir($logFolder) && !mkdir($logFolder) && !is_dir($logFolder)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $logFolder));
        }

        $logFile = fopen($logFolder . 'errors.log', 'ab') ?: throw new \Exception();
        fwrite($logFile, $log . PHP_EOL);
        fclose($logFile);
    }

    public static function create404ExceptionResponse(): ResponseModel
    {
        $response = new ResponseModel();
        $response->responseMessage = 'Page not found';
        $response->headers->headers[] = 'Content-Type: application/json';
        $response->status = 404;

        return $response;
    }

    public static function readRoute(): string
    {
        $parsed_url_string = $_GET['route'] ?? '/index';

        $parsed_url_string = ltrim((string) $parsed_url_string, '/');
        $parsed_url_string = '/' . $parsed_url_string;
        if ($parsed_url_string === '/') {
            $parsed_url_string = '/index';
        }
        return $parsed_url_string;
    }
}
