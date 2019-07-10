<?php

namespace top\extend\wechat;

/**
 * 微信API
 * Class WeChatAPI
 * @package top\extend\wechat
 */
class WeChatAPI
{

    /**
     * @var null|string 当前页面的URL
     */
    private $url = null;

    /**
     * @var null 错误信息
     */
    private $error = null;

    /**
     * @var string 获取ACCESS_TOKEN的接口
     */
    private $accessTokenAPI = 'https://api.weixin.qq.com/cgi-bin/token?';

    /**
     * @var string 获取OAuth ACCESS_TOKEN的接口
     */
    private $oauthAccessTokenAPI = 'https://api.weixin.qq.com/sns/oauth2/access_token?';

    /**
     * @var string 获取CODE的接口
     */
    private $codeAPI = 'https://open.weixin.qq.com/connect/oauth2/authorize?';

    /**
     * @var string 拉取用户信息接口
     */
    private $userinfoAPI = 'https://api.weixin.qq.com/sns/userinfo?';

    /**
     * @var string 拉取用户信息接口（UnionID机制）
     */
    private $userinfoUnionIdAPI = 'https://api.weixin.qq.com/cgi-bin/user/info?';

    /**
     * @var string 自定义菜单创建接口
     */
    private $menuAPI = 'https://api.weixin.qq.com/cgi-bin/menu/create';

    /**
     * @var array 微信配置
     */
    private $config = [];

    public function __construct($config = [])
    {
        $this->url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        $this->config = $config;
    }

    /**
     * 获取ACCESS_TOKEN
     * @return bool|mixed
     */
    private function getAccessToken()
    {
        $file = './access_token.json';
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $result = json_decode($content, true);
            $expires = $result['expires_in'] - (time() - filemtime($file));
            if ($expires <= 5) {
                @unlink($file);
                return $this->getAccessToken();
            }
        } else {
            $api = $this->accessTokenAPI . "grant_type=client_credential&appid={$this->config['appid']}&secret={$this->config['appsecret']}";
            $json = create_http_request($api);
            $result = json_decode($json, true);
            if (isset($result['errcode']) && $result['errcode'] != 0) {
                throw new WeChatAPIException('code:' . $result['errcode'] . ',' . $result['errmsg']);
            }
            file_put_contents($file, $json);
        }
        return $result;
    }

    /**
     * 获取CODE
     * @param $scope
     */
    private function getCode($scope)
    {
        $redirect = $this->url;
        $api = $this->codeAPI . "appid={$this->config['appid']}&redirect_uri={$redirect}&response_type=code&scope={$scope}&state=0#wechat_redirect";
        header('location:' . $api);
    }

    /**
     * 获取OAuth的ACCESS_TOKEN
     * @param string $scope
     * @return bool|mixed|void
     */
    private function getOAuthAccessToken($scope = 'snsapi_base')
    {
        $file = './oauth_access_token.json';
        if (file_exists($file)) {
            $content = file_get_contents($file);
            $result = json_decode($content, true);
            $expires = $result['expires_in'] - (time() - filemtime($file));
            if ($expires <= 5) {
                @unlink($file);
                return $this->getOAuthAccessToken();
            }
        } else {
            $code = isset($_GET['code']) ? $_GET['code'] : null;
            if (!$code) {
                return $this->getCode($scope);
            }
            $api = $this->oauthAccessTokenAPI . "appid={$this->config['appid']}&secret={$this->config['appsecret']}&code={$code}&grant_type=authorization_code";
            $json = create_http_request($api);
            $result = json_decode($json, true);
            if (isset($result['errcode']) && $result['errcode'] != 0) {
                throw new WeChatAPIException('code:' . $result['errcode'] . ',' . $result['errmsg']);
            }
            file_put_contents($file, $json);
        }
        return $result;
    }

    /**
     * 拉取用户信息
     * @param string $openid
     * @return bool|mixed
     */
    public function getUserInfo($openid = null)
    {
        $postData = [];
        if ($openid) {
            $accessToken = $this->getAccessToken();
            if (is_array($openid)) {
                $postData = [
                    'user_list' => $openid
                ];
                $api = $this->userinfoUnionIdAPI . "access_token={$accessToken['access_token']}";
            } else {
                $api = $this->userinfoUnionIdAPI . "access_token={$accessToken['access_token']}&openid={$openid}&lang=zh_CN";
            }
        } else {
            $accessToken = $this->getOAuthAccessToken('snsapi_userinfo');
            $api = $this->userinfoAPI . "access_token={$accessToken['access_token']}&openid={$accessToken['openid']}&lang=zh_CN";
        }
        $json = create_http_request($api, json_encode($postData));
        $result = json_decode($json, true);
        if (isset($result['errcode']) && $result['errcode'] != 0) {
            $this->error = 'code:' . $result['errcode'] . ',' . $result['errmsg'];
            return false;
        }
        return $result;
    }

    /**
     * 创建公众号菜单
     * $menu数据示例
     * [
     *     [
     *         'type' => 'view',
     *         'name' => 'TOP糯米',
     *         'url' => 'https://www.topnuomi.com/'
     *     ],
     *     [
     *         'name' => '测试多级',
     *         'sub_button' => [
     *             [
     *                 'type' => 'view',
     *                 'name' => '我的主页',
     *                 'url' => 'https://topnuomi.com/'
     *             ],
     *             [
     *                 'type' => 'click',
     *                 'name' => '点击',
     *                 'key' => 'V1001_TODAY_MUSIC'
     *             ]
     *         ]
     *     ]
     * ]
     * @param array $menu
     * @return bool
     */
    public function createMenu($menu = [])
    {
        $accessToken = $this->getAccessToken();
        $api = $this->menuAPI . "?access_token={$accessToken['access_token']}";
        $menu = json_encode([
            'button' => $menu
        ], JSON_UNESCAPED_UNICODE);
        $json = create_http_request($api, $menu);
        $result = json_decode($json, true);
        if (isset($result['errcode']) && $result['errcode'] != 0) {
            $this->error = 'code:' . $result['errcode'] . ',' . $result['errmsg'];
            return false;
        }
        return true;
    }

    /**
     * 获取错误信息
     * @return null
     */
    public function getError()
    {
        return $this->error;
    }

}
