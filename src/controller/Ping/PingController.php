<?php

namespace src\controller\Ping;

use src\controller\Base\BaseController;
use src\model\Translation\TranslationModel;

class PingController extends BaseController
{
    public function getAction(): void
    {
        $this->response->data['ping'] = TranslationModel::getTranslation('pong');
        $this->response->responseMessage = TranslationModel::getTranslation('pingMessage');
    }
}
