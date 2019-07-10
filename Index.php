<?php

namespace app\home\controller;

use top\blocks\Json;
use top\library\Register;
use top\extend\wechat\WeChatAPI;

class Index extends Common
{
    use Json;

    public function index()
    {
        $config = Register::get('Config')->get('wechat');
        $wechat = new WeChatAPI($config);
        $info = $wechat->getUserInfo();
        return [
            'userinfo' => $info
        ];
    }
}
