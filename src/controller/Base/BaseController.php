<?php

namespace src\controller\Base;

use src\model\Response\ResponseModel;

abstract class BaseController
{
    protected array $userParams;
    protected int $status = 200;

    public ResponseModel $response;

    public function __construct(
        private readonly array $get = [],
        private readonly array $post = [],
        private readonly array $files = [],
    ) {
        $this->userParams = $this->get;
        $this->userParams = array_merge($this->userParams, $this->post);
        $this->userParams = array_merge($this->userParams, $this->files);

        $this->execute();
    }

    private function execute(): void
    {
        $this->response = new ResponseModel();

        if ($this->post !== [] && method_exists(static::class, 'postAction')){
            $this->postAction();
        }else if (method_exists(static::class, 'getAction')){
            $this->getAction();
        }

        $this->response->status = $this->status;
        $this->response->headers->headers[] = 'Content-Type: application/json';
    }

    protected function postAction(): void {}

    abstract protected function getAction(): void;
}
