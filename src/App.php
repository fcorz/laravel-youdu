<?php

namespace Huangdijia\Youdu;

use Huangdijia\Youdu\Contracts\AppMessage;
use Huangdijia\Youdu\Crypt\Prpcrypt;
use Huangdijia\Youdu\Exceptions\ErrorCode;
use Huangdijia\Youdu\Facades\HttpClient;
use Huangdijia\Youdu\Messages\App\Text;
use Huangdijia\Youdu\Messages\App\PopWindow;
use Illuminate\Support\Facades\Cache;

class App
{
    protected $api;
    protected $buin;
    protected $appId;
    protected $aesKey;
    protected $crypter;
    protected $dept;
    protected $group;
    protected $user;
    protected $session;
    protected $media;

    public function __construct(string $api = '', int $buin, string $appId = '', string $aesKey = '')
    {
        $this->api    = $api;
        $this->buin   = $buin;
        $this->appId  = $appId;
        $this->aesKey = $aesKey;

        $this->crypter = new Prpcrypt($aesKey);
        $this->dept    = new Dept($this);
        $this->group   = new Group($this);
        $this->user    = new User($this);
        $this->session = new Session($this);
        $this->media   = new Media($this);
    }

    /**
     * 部门
     *
     * @return \Huangdijia\Youdu\Dept
     */
    public function dept()
    {
        return $this->dept;
    }

    /**
     * 群
     *
     * @return \Huangdijia\Youdu\Group
     */
    public function group()
    {
        return $this->group;
    }

    /**
     * 用户
     *
     * @return \Huangdijia\Youdu\User
     */
    public function user()
    {
        return $this->user;
    }

    /**
     * 会话
     *
     * @return \Huangdijia\Youdu\Session
     */
    public function session()
    {
        return $this->session;
    }

    /**
     * 会话
     *
     * @return \Huangdijia\Youdu\Media
     */
    public function media()
    {
        return $this->media;
    }

    /**
     * 获取 buin
     *
     * @return int
     */
    public function getBuin()
    {
        return $this->buin;
    }

    /**
     * 获取 appId
     *
     * @return string
     */
    public function getAppId()
    {
        return $this->appId;
    }

    /**
     * 获取 aesKey
     *
     * @return string
     */
    public function getAesKey()
    {
        return $this->aesKey;
    }

    /**
     * 加密
     *
     * @param string $unencrypted
     * @return string|bool
     */
    public function encryptMsg(string $unencrypted = '')
    {
        [$errcode, $encrypted] = $this->crypter->encrypt($unencrypted, $this->appId);

        if ($errcode != 0) {
            throw new \Exception($encrypted, $errcode);
        }

        return $encrypted;

    }

    /**
     * 解密
     *
     * @param string|null $encrypted
     * @return bool|string
     */
    public function decryptMsg(?string $encrypted)
    {
        if (strlen($this->aesKey) != 44) {
            throw new \Exception('Illegal aesKey', ErrorCode::$IllegalAesKey);
        }

        [$errcode, $decrypted] = $this->crypter->decrypt($encrypted, $this->appId);

        if ($errcode != 0) {
            throw new \Exception('Decrypt faild', $errcode);
        }

        return $decrypted;
    }

    /**
     * Get access token
     *
     * @return string
     */
    public function getAccessToken()
    {
        $appId = $this->appId;
        $buin  = $this->buin;

        return Cache::remember('youdu:tokens:' . $appId, 2 * 3600, function () use ($buin, $appId) {
            $encrypted  = $this->encryptMsg((string) time());
            $parameters = [
                "buin"    => $buin,
                "appId"   => $appId,
                "encrypt" => $encrypted,
            ];

            $url  = $this->url('/cgi/gettoken', false);
            $resp = HttpClient::post($url, $parameters);
            $body = json_decode($resp['body'], true);

            if ($body['errcode'] != 0) {
                throw new \Exception($body['errmsg'], $body['errcode']);
            }

            $decrypted = $this->decryptMsg($body['encrypt']);
            $decoded   = json_decode($decrypted, true);

            return $decoded['accessToken'];
        });
    }

    /**
     * 组装 URL
     *
     * @param string $uri
     * @param boolean $withAccessToken
     *
     * @return string
     */
    public function url(string $uri = '', bool $withAccessToken = true)
    {
        $url = rtrim($this->api, '/') . '/' . ltrim($uri, '/');

        if ($withAccessToken) {
            $url .= '?accessToken=' . $this->getAccessToken();
        }

        return $url;
    }

    /**
     * 发送应用消息
     *
     * @param string $toUser 接收成员的帐号列表。多个接收者用竖线分隔，最多支持1000个
     * @param string $toDept 接收部门id列表。多个接收者用竖线分隔，最多支持100个
     * @param \Huangdijia\Youdu\Contracts\AppMessage|string $message
     * @return bool
     */
    public function send(string $toUser = '', string $toDept = '', $message = '')
    {
        if (is_string($message)) {
            $message = new Text($message);
        }

        if (!($message instanceof AppMessage)) {
            throw new \Exception("\$message must instanced of " . AppMessage::class, 1);
        }

        if ($toUser) {
            $message->toUser($toUser);
        }

        if ($toDept) {
            $message->toDept($toDept);
        }

        $encrypted  = $this->encryptMsg($message->toJson());
        $parameters = [
            "buin"    => $this->buin,
            "appId"   => $this->appId,
            "encrypt" => $encrypted,
        ];

        $url  = $this->url('/cgi/msg/send');
        $resp = HttpClient::post($url, $parameters);

        if ($resp['httpCode'] != 200) {
            throw new \Exception("http request code " . $resp['httpCode'], ErrorCode::$IllegalHttpReq);
        }

        $body = json_decode($resp['body'], true);

        if ($body['errcode'] !== 0) {
            throw new \Exception($body['errmsg'], $body['errcode']);
        }

        return true;
    }

    /**
     * 发送消息给用户
     *
     * @param string $toUser
     * @param \Huangdijia\Youdu\Contracts\AppMessage|string $message
     * @return bool
     */
    public function sendToUser(string $toUser = '', $message = '')
    {
        return $this->send($toUser, '', $message);
    }

    /**
     * 发送消息至部门
     *
     * @param string $toDept
     * @param \Huangdijia\Youdu\Contracts\AppMessage|string $message
     * @return bool
     */
    public function sendToDept(string $toDept = '', $message = '')
    {
        return $this->send('', $toDept, $message);
    }

    /**
     * 设置通知数
     *
     * @param string $account
     * @param string $tip
     * @param integer $msgCount
     * @return bool
     */
    public function setNoticeCount(string $account = '', string $tip = '', int $msgCount = 0)
    {
        $parameters = [
            'app_id'      => $this->appId,
            'msg_encrypt' => $this->encryptMsg(json_encode([
                "account" => $account,
                "tip"     => $tip,
                "count"   => $msgCount,
            ])),
        ];

        $resp = HttpClient::post($this->url('/cgi/set.ent.notice'), $parameters);

        if ($resp['httpCode'] != 200) {
            throw new \Exception("http request code " . $resp['httpCode'], ErrorCode::$IllegalHttpReq);
        }

        $body = json_decode($resp['body'], true);

        if ($body['errcode'] !== 0) {
            throw new \Exception($body['errmsg'], $body['errcode']);
        }

        return true;
    }

    /**
     * 应用弹窗
     *
     * @param string $toUser
     * @param string $toDept
     * @param \Huangdijia\Youdu\Messages\App\PopWindow $message
     * @return bool
     */
    public function popWindow(string $toUser = '', string $toDept = '', PopWindow $message)
    {
        if ($toUser) {
            $message->toUser($toUser);
        }

        if ($toDept) {
            $message->toDept($toDept);
        }

        $parameters = [
            'app_id'      => $this->appId,
            'msg_encrypt' => $this->encryptMsg($message->toJson()),
        ];

        $resp = HttpClient::post($this->url('/cgi/popwindow'), $parameters);

        if ($resp['httpCode'] != 200) {
            throw new \Exception("http request code " . $resp['httpCode'], ErrorCode::$IllegalHttpReq);
        }

        $body = json_decode($resp['body'], true);

        if ($body['errcode'] !== 0) {
            throw new \Exception($body['errmsg'], $body['errcode']);
        }

        return true;
    }
}
