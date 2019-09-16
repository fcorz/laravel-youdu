<?php

namespace Huangdijia\Youdu\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Huangdijia\Youdu\Youdu
 * @method static int getBuin()
 * @method static string getAppId()
 * @method static string getAesKey()
 * @method string url(string $msg = '', bool $withAccessToken = true)
 * @method string encryptMsg(string $msg = '')
 * @method string decryptMsg(?string $encrypted)
 * @method static bool send(string $toUser = '', string $toDept = '', $message = '')
 * @method static bool sendToUser(string $toUser = '', $message = '')
 * @method static bool sendToDept(string $toDept = '', $message = '')
 * @method static string uploadFile(string $file = '', string $fileType = 'file')
 * @method static bool downloadFile(string $mediaId = '', ?string $savePath = null)
 */
class Youdu extends Facade
{
    public static function getFacadeAccessor()
    {
        return 'youdu.manager';
    }
}