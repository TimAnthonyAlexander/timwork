<?php

namespace src\model\Api;

use src\controller\Base\BaseController;
use src\model\Response\ResponseModel;
use src\module\Page\Page;
use src\module\RouteConfig\RouteConfig;

class Api
{
    private string $route = '/';
    private array $get = [];
    private array $post = [];
    private array $files = [];

    public function send(): ResponseModel
    {
        $routeConfig = new RouteConfig();

        if (!$routeConfig->existsConfigItem($this->route)) {
            return Page::create404ExceptionResponse();
        }

        $controllers = $routeConfig->getConfigItem($this->route);

        if ($controllers === null) {
            return Page::createExceptionResponse();
        }

        $response = new ResponseModel();

        foreach ($controllers as $controller) {
            $currentResponse = $this->sendToController($controller);

            if ($currentResponse->status !== 200) {
                $response->status = $currentResponse->status;
            }

            if ($currentResponse->responseMessage !== null) {
                $response->responseMessage = $currentResponse->responseMessage;
            }

            $response->data = array_merge_recursive($response->data, $currentResponse->data);
            $response->headers->headers = array_merge($response->headers->headers, $currentResponse->headers->headers);
        }

        return $response;
    }

    private function sendToController(string $controller): ResponseModel {
        // $controller = 'Index'
        // $class = 'src\\controller\\Index\\IndexController'

        $class = sprintf('src\\controller\\%s\\%sController', $controller, $controller);

        if (!class_exists($class)) {
            #shell_exec('composer dump');
            throw new \Exception(sprintf('Class %s does not exist, reload for dump-autoload.', $class));
        }

        $object = new $class($this->get, $this->post, $this->files);
        assert($object instanceof BaseController);

        return $object->response;
    }

    public function setRoute(string $route): void
    {
        $this->route = $route;
    }

    public function setGet(array $get): void
    {
        $this->get = $get;
    }

    public function setPost(array $post): void
    {
        $this->post = $post;
    }

    public function setFiles(array $files): void
    {
        $this->files = $files;
    }
}
