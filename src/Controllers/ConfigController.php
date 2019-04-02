<?php

namespace Yggdrasil\Controllers;

use DB;
use Log;
use Exception;
use Datatables;
use Yggdrasil\Utils\UUID;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Yggdrasil\Exceptions\NotFoundException;
use Yggdrasil\Exceptions\IllegalArgumentException;
use Yggdrasil\Exceptions\ForbiddenOperationException;

class ConfigController extends Controller
{
    public function hello(Request $request)
    {
        // Default skin domain whitelist:
        // - Specified by option 'site_url'
        // - Extract host from current URL
        $extra = option('ygg_skin_domain') === '' ? [] : explode(',', option('ygg_skin_domain'));
        $skinDomains = array_map('trim', array_unique(array_merge($extra, [
            parse_url(option('site_url'), PHP_URL_HOST),
            $request->getHost()
        ])));

        $privateKey = openssl_pkey_get_private(option('ygg_private_key'));

        if (! $privateKey) {
            throw new IllegalArgumentException('无效的 RSA 私钥，请访问插件配置页重新设置');
        }

        $keyData = openssl_pkey_get_details($privateKey);

        if ($keyData['bits'] < 4096) {
            throw new IllegalArgumentException('RSA 私钥的长度至少为 4096，请访问插件配置页重新设置');
        }

        $result = [
            'meta' => [
                'serverName' => option('site_name'),
                'implementationName' => 'Yggdrasil API for Blessing Skin',
                'implementationVersion' => plugin('yggdrasil-api')->version,
                'links' => [
                    'homepage' => url('/')
                ]
            ],
            'skinDomains' => $skinDomains,
            'signaturePublickey' => $keyData['key']
        ];

        if (option('user_can_register')) {
            $result['meta']['links']['register'] = url('auth/register');
        }

        return json($result);
    }

    public function logData()
    {
        $query = DB::table('ygg_log')
            ->join('users', 'ygg_log.user_id', '=', 'users.uid')
            ->leftJoin('players', 'ygg_log.player_id', '=', 'players.pid')
            ->select('id', 'action', 'user_id', 'email', 'player_id', 'players.name', 'parameters', 'ygg_log.ip', 'time');

        return Datatables::of($query)->make(true);
    }

    public function generate()
    {
        try {
            return json([
                'errno' => 0,
                'key' => ygg_generate_rsa_keys()['private']
            ]);
        } catch (Exception $e) {
            return json('自动生成私钥时出错，请尝试手动设置私钥。错误信息：'.$e->getMessage(), 1);
        }
    }
}
