<?php


namespace app\index\controller;
use think\facade\Cache;
use think\facade\Cookie;
use think\facade\Db;
use think\facade\Session;
use app\common\service\YpayUser as S;
use think\facade\View;
use think\facade\Request;

class Index extends \app\BaseController
{
    private const HOME_CACHE_TTL = 60;

    protected $middleware = ['Domain','Mtce'];
    
    public function index()
    {
        $config = getConfig();

        $this->rememberAffiliate($config);

        if (empty($config['is_weboff'])) {
            return redirect(Request::root().'/User/Login');
        }

        if ((int) $config['is_weboff'] === 2) {
            return $this->renderExternalHome($config['home_url'] ?? '');
        }

        $homeTemp = $this->resolveHomeTheme($config['home_temp'] ?? '');
        if ($homeTemp === '') {
            View::assign('error_tips', '请先配置首页界面');
            View::assign('error_url', '/');
            return $this->fetch('error/errorPage');
        }

        $homeData = $this->getHomeData();
        $index_array = [
            'nav' => $homeData['nav'],
            'is_login' => $this->isFrontLogin() ? 1 : 0,
        ];

        View::assign([
            'config' => $config,
            'index_array' => $index_array,
            'resource' => $homeTemp,
            'year' => date('Y'),
            'ver' => env('YuanVer'),
        ]);

        View::config(['view_path' => app()->getRootPath() . 'public/web/home/'.$homeTemp]);
        return $this->fetch('/index.html');
    }

    private function rememberAffiliate(array $config): void
    {
        if (empty($config['is_aff'])) {
            return;
        }

        $aff = (string) Request::param('aff', '');
        if ($aff !== '' && ctype_digit($aff)) {
            Session::set('aff_id', (int) $aff);
        }
    }

    private function renderExternalHome(string $url)
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array($scheme, ['http', 'https'], true)) {
            return redirect(Request::root().'/User/Login');
        }

        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        return '<!doctype html><html lang="zh-CN"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0"><iframe src="'.$safeUrl.'" title="home" style="display:block;width:100vw;height:100vh;border:0"></iframe></body></html>';
    }

    private function resolveHomeTheme(string $theme): string
    {
        $homeDir = app()->getRootPath() . 'public/web/home/';
        $theme = $this->sanitizeTheme($theme);

        if ($theme !== '' && $this->themeExists($homeDir, $theme)) {
            return $theme;
        }

        $themes = S::getHomeTheme();
        foreach (($themes['data'] ?? []) as $item) {
            $fallback = $this->sanitizeTheme((string) ($item['id'] ?? ''));
            if ($fallback !== '' && $this->themeExists($homeDir, $fallback)) {
                return $fallback;
            }
        }

        return '';
    }

    private function sanitizeTheme(string $theme): string
    {
        $theme = trim($theme);
        return preg_match('/^[A-Za-z0-9_-]+$/', $theme) ? $theme : '';
    }

    private function themeExists(string $homeDir, string $theme): bool
    {
        $templatePath = $homeDir . $theme;
        return is_dir($templatePath) && is_file($templatePath . '/index.html');
    }

    private function getHomeData(): array
    {
        $cacheKey = 'index_home_base';
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $data = [
            'nav' => $this->getHomeNav(),
        ];

        Cache::set($cacheKey, $data, self::HOME_CACHE_TTL);
        return $data;
    }

    private function getHomeNav(): array
    {
        $items = Db::name('ypay_navs')
            ->field('id,name,url,is_target,sort')
            ->where('status', 1)
            ->order('sort', 'asc')
            ->select()
            ->toArray();

        $nav = [];
        foreach ($items as $item) {
            $item['url'] = $this->safePublicUrl((string) ($item['url'] ?? ''));
            $item['is_target'] = (int) ($item['is_target'] ?? 0);

            if ($this->shouldHideHomeNav($item)) {
                continue;
            }

            $nav[] = $item;
        }

        return $nav;
    }

    private function shouldHideHomeNav(array $item): bool
    {
        $name = trim((string) ($item['name'] ?? ''));
        $path = parse_url((string) ($item['url'] ?? ''), PHP_URL_PATH) ?: '';

        return $name === '公告中心' || strcasecmp($path, '/News/Index') === 0;
    }

    private function safePublicUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '#';
        }

        if (preg_match('/^(https?:)?\/\//i', $url)) {
            return $url;
        }

        if ($url[0] === '/') {
            return $url;
        }

        if (preg_match('/^[A-Za-z0-9_\-.\/]+(?:\?.*)?$/', $url)) {
            return '/' . ltrim($url, '/');
        }

        return '#';
    }

    private function isFrontLogin(): bool
    {
        return Cookie::has('front_token') && S::isLogin();
    }
}
