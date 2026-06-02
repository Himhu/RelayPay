<?php
namespace app\common\util;
use think\facade\Filesystem;
use app\common\service\AdminPhoto;
use think\exception\ValidateException;
use think\facade\Config;
use ZipArchive;
use think\facade\Db;
use Zxing\QrReader as qrReader;

class Upload
{

    //通用上传
    public static function putFile($file, $path)
    {
        if (!$path) {
            $path = 'default';
        }

        try {
            validate(['file' => [
                'fileSize' => 410241024,
                'fileExt' => 'jpg,jpeg,png,bmp,gif',
                'fileMime' => 'image/jpeg,image/png,image/gif',
            ]])->check(['file' => $file]);
        } catch (\think\exception\ValidateException $e) {
            return ['msg' => '上传失败', 'code' => 201, 'data' => $e->getMessage()];
        }
        foreach ($file as $k) {
            if (getConfig()['file-type'] == 2) {
                //阿里云上传
                $res = Oss::alYunOSS($k, $k->extension(), $path);
                if ($res["code"] == 201) {
                    return ['msg' => '上传失败', 'code' => 201, 'data' => $res["msg"]];
                }

                $name = $res['src'];
                AdminPhoto::add($k, $name, $path, 2);
            } elseif (getConfig()['file-type'] == 3) {
                //七牛上传
                $res = Qiniu::QiniuOSS($k, $k->extension(), $path);
                if ($res["code"] == 201) {
                    return ['msg' => '上传失败', 'code' => 201, 'data' => $res["msg"]];
                }

                $name = $res['src'];
                AdminPhoto::add($k, $name, $path, 3);
            } else {
                $savename = '/' . 'upload' . '/' . \think\facade\Filesystem::disk('public')->putFile($path, $k);
                $name = str_replace("\\", "/", $savename);
                AdminPhoto::add($k, $name, $path, 1);
            }
        }
        return ['msg' => '上传成功', 'code' => 0, 'data' => ['src' => $name, 'thumb' => $name]];
    }
    
    //收款码上传
    public static function qrputFile($file, $path ,$code = '' ,$qr_type = null)
    {
        if (!$path) {
            $path = 'qrcode';
        }

        try {
            validate(['file' => [
                'fileSize' => 410241024,
                'fileExt' => 'jpg,jpeg,png,bmp,gif',
                'fileMime' => 'image/jpeg,image/png,image/gif',
            ]])->check(['file' => $file]);
        } catch (\think\exception\ValidateException $e) {
            return ['msg' => '上传失败', 'code' => 201, 'data' => $e->getMessage()];
        }
        
        if($code == 'wxpay_cloud' || $code == 'wxpay_cloudzs' || ($code == 'wxpay_software' && $qr_type == "appreciate")){
            foreach ($file as $k) {
            if (getConfig()['file-type'] == 2) {
                //阿里云上传
                $res = Oss::alYunOSS($k, $k->extension(), $path);
                if ($res["code"] == 201) {
                    return ['msg' => '上传失败', 'code' => 201, 'data' => $res["msg"]];
                }

                $name = $res['src'];
                AdminPhoto::add($k, $name, $path, 2);
            } elseif (getConfig()['file-type'] == 3) {
                //七牛上传
                $res = Qiniu::QiniuOSS($k, $k->extension(), $path);
                if ($res["code"] == 201) {
                    return ['msg' => '上传失败', 'code' => 201, 'data' => $res["msg"]];
                }

                $name = $res['src'];
                AdminPhoto::add($k, $name, $path, 3);
            } else {
                $savename = '/' . 'upload' . '/' . \think\facade\Filesystem::disk('public')->putFile($path, $k);
                $name = str_replace("\\", "/", $savename);
                AdminPhoto::add($k, $name, $path, 1);
            }
        }
        return ['msg' => '上传成功', 'code' => 0, 'data' => ['src' => $name, 'thumb' => $name]];
        }else{
            foreach ($file as $k) {
            $savename = '/' . 'upload' . '/' . \think\facade\Filesystem::disk('public')->putFile($path, $k);
            $name = str_replace("\\", "/", $savename);
            // AdminPhoto::add($k, $name, $path, 1);
                $request = \think\facade\Request::instance();
                $erweima = $request->root(true).$name;//二维码的网络地址
                //1为API解码 2为本地解码
                $qr_source = null;
                $remoteUrl = urlencode($erweima);
                $remoteDecode = function () use ($remoteUrl, &$qr_source) {
                    $qr_url = null;
                    // 草聊二维码识别
                    $primaryApi = 'https://api.2dcode.biz/v1/read-qr-code?file_url=' . $remoteUrl;
                    $ret = get_curl($primaryApi);
                    $data = json_decode($ret, true);
                    if (is_array($data) && (int)($data['code'] ?? -1) === 0) {
                        $contents = $data['data']['contents'] ?? [];
                        if (is_array($contents) && !empty($contents[0])) {
                            $qr_source = 'caoliao';
                            return $contents[0];
                        }
                    }
                    // api.qrserver.com 兜底
                    $fallbackApi = 'https://api.qrserver.com/v1/read-qr-code/?fileurl=' . $remoteUrl;
                    $ret = get_curl($fallbackApi);
                    $data = json_decode($ret, true);
                    if (is_array($data) && !empty($data[0]['symbol'][0]['data'])) {
                        $qr_source = 'qrserver';
                        return $data[0]['symbol'][0]['data'];
                    }
                    return null;
                };
                $qr_url = null;
                if(getConfig()['qr_codeType'] == 1){
                    $qr_url = $remoteDecode();
                }elseif(getConfig()['qr_codeType'] == 2){
                    $defaultOpts = [
                        'ssl' => [
                          'verify_peer' => false,
                          'verify_peer_name' => false,
                        ]
                    ];
                    stream_context_set_default($defaultOpts);
                    $qr_source = 'local';
                    if (PHP_VERSION_ID >= 80100) {
                        try{
                            $qrReader = new qrReader($erweima);
                            $qr_url = $qrReader->text();
                        }catch(\Throwable $e){
                            $qr_url = null;
                        }
                    } else {
                        $qr_url = null;
                    }
                    if (empty($qr_url)) {
                        $qr_url = $remoteDecode();
                    }
                }
                if(!empty($qr_url))
                {
                    if (file_exists(app()->getRootPath().'public'.$name)) unlink(app()->getRootPath().'public'.$name);//删除本地文件
                }
                else
                {
                    if (file_exists(app()->getRootPath().'public'.$name)) unlink(app()->getRootPath().'public'.$name);//删除本地文件
                    return ['code'=>201,'msg'=>'二维码解码失败,请手动解码输入'];
                }
            }
            return ['msg' => '解析成功', 'code' => 0, 'data' => ['src' => $qr_url, 'thumb' => $qr_url, 'source' => $qr_source ?? '']];
        }
        
        
    }
    
    //更新包上传解压
    public static function updatePutFile($file)
    {

        try {
            validate(['file' => [
                'fileExt' => 'zip',
            ]])->check(['file' => $file]);
        } catch (\think\exception\ValidateException $e) {
            return ['msg' => '上传失败', 'code' => 201, 'data' => $e->getMessage()];
        }
        // 上传目录
        $uploadPath = app()->getRootPath() . "runtime/upgrade/";
    
        // 将文件移动到上传目录
        $info = $file->move($uploadPath, $file->getOriginalName());
       
        if ($info) {
            // 获取上传后的文件路径和文件名
            $fileName = $info->getFileName();
            $filePath = $uploadPath .$fileName;
 
                    // 解压文件夹
                    $zip = new \ZipArchive();
                    //打开压缩包
                if ($zip->open($filePath) === true) {
 
                    $toPath = app()->getRootPath();
                    try {
                    //解压文件到toPath路径下，用于覆盖差异文件
                    if (!self::extractZipSafely($zip, $toPath)) {
                        $zip->close();
                        unlink($filePath);
                        return ["msg" => "更新包解压失败，请重试！", "code" => 201];
                    }
                    $zip->close();
 
                    //判断文件是否存在
                    if (file_exists($toPath . '/upDelete.txt')) {
                        $deleteList = self::readDeleteManifest($toPath . '/upDelete.txt');
                        self::removePaths($deleteList, $toPath);
                        unlink($toPath . '/upDelete.txt'); //删除-删除文件文本
                    }
                    unlink($filePath); //删除更新包
 
                    } catch (\Throwable $e) {
                    if ($zip instanceof \ZipArchive) {
                        $zip->close();
                    }
                    unlink($filePath);
                    return ["msg" => $e->getMessage(), "code" => 201];
                }
                    //文件差异覆盖完成，开始更新数据库
                    //执行数据库
                    $dbpk = '';
                    $dbhost = Config::get('database.connections.mysql.hostname');
                    $dbport = Config::get('database.connections.mysql.hostport');
                    $dbname = Config::get('database.connections.mysql.database');
                    $dsn = "mysql:host=$dbhost:$dbport;dbname=$dbname";
                    $db = new \PDO($dsn, Config::get('database.connections.mysql.username'), Config::get('database.connections.mysql.password'));
    
                    $list = scandir($_SERVER['DOCUMENT_ROOT'] . '/../app/update');
                    // 文件头两个是 . 和 .. 要去掉
                    unset($list[0]);
                    unset($list[1]);
                    
                  
                    
                    // 获取当前数据库版本号
                    $db_version = Db::name('admin_config')->where(['config_name' => 'db_version'])->find();
                    $last = '';
                    foreach ($list as $item) {
                        $tmp = str_replace('.sql', '', $item);
                        
                        if ((int)$tmp > (int)$db_version['config_value']) {
                            self::createTables($db, $dbpk, $_SERVER['DOCUMENT_ROOT'] . '/../app/update/' . $tmp . '.sql');
                        }
    
                        $last = $tmp;
                    }
                    // 将最后一次更新的版本号记录到数据库
                    if (!$db_version) {
                        Db::name('admin_config')->insert([
                            'config_name' => 'db_version',
                            'config_value' => $last,
                        ]);
                    } else {
                        Db::name('admin_config')->where(['config_name' => 'db_version'])->save([
                            'config_name' => 'db_version',
                            'config_value' => $last,
                        ]);
                    }
     
                    return ['code' => 200, 'msg' => '版本更新成功请刷新缓存!'];
                } else {
                    unlink($filePath); //删除更新包
                    return ["msg" => "更新包解压失败，请重试！", "code" => 201];
                }
            }  else {
                // 删除上传的文件
                unlink($filePath);
                return ['code' => 201 ,'msg' => '不支持的文件类型'];
            }
    }
    
    
    //通道插件包上传解压
    public static function channelPutFile($file)
    {

        try {
            validate(['file' => [
                'fileExt' => 'zip',
            ]])->check(['file' => $file]);
        } catch (\think\exception\ValidateException $e) {
            return ['msg' => '上传失败', 'code' => 201, 'data' => $e->getMessage()];
        }
        
        // 上传目录
        $uploadPath = base_path() . 'plugins/';
    
        // 将文件移动到上传目录
        $info = $file->move($uploadPath, $file->getOriginalName());
       
                if ($info) {
            // 获取上传后的文件路径和文件名
            $filePath = $info->getPathName();
            $fileName = $info->getFileName();
    
            // 判断文件类型
            $extension = $info->getExtension();
            if ($extension == 'zip') {
                // 判断是否是一个文件夹
                if (is_dir($filePath)) {
                    // 解压文件夹
                    $zip = new \ZipArchive();
                    if ($zip->open($filePath) === true) {
                        if (!self::extractZipSafely($zip, $uploadPath)) {
                            $zip->close();
                            unlink($filePath);
                            return ['code' => 201 ,'msg' => '无效的插件压缩包'];
                        }
                        $zip->close();
                        //文件差异覆盖完成，开始更新数据库
                        //执行数据库
                        $dbpk = '';
                        $dbhost = Config::get('database.connections.mysql.hostname');
                        $dbport = Config::get('database.connections.mysql.hostport');
                        $dbname = Config::get('database.connections.mysql.database');
                        $dsn = "mysql:host=$dbhost:$dbport;dbname=$dbname";
                        $db = new \PDO($dsn, Config::get('database.connections.mysql.username'), Config::get('database.connections.mysql.password'));
                        $arr = explode('.',$fileName);
                        self::createTables($db, $dbpk, $uploadPath . $arr['0'].'/create.sql');
                         // 删除上传的压缩包文件
                        unlink($filePath);
                        return ['code' => 200 ,'msg' => '上传成功'];
                    } else {
                        return ['code' => 201 ,'msg' => '无法打开压缩文件'];
                    }
                } else {
                    // 创建以压缩包名字命名的文件夹并解压
                    $folderName = pathinfo($fileName, PATHINFO_FILENAME);
                    $folderPath = $uploadPath . $folderName;
                    if (!is_dir($folderPath)) {
                        mkdir($folderPath, 0755, true);
                    }
                    $zip = new \ZipArchive();
                    if ($zip->open($filePath) === true) {
                        if (!self::extractZipSafely($zip, $folderPath)) {
                            $zip->close();
                            unlink($filePath);
                            return ['code' => 201 ,'msg' => '无效的插件压缩包'];
                        }
                        $zip->close();
                        //文件差异覆盖完成，开始更新数据库
                        //执行数据库
                        $dbpk = '';
                        $dbhost = Config::get('database.connections.mysql.hostname');
                        $dbport = Config::get('database.connections.mysql.hostport');
                        $dbname = Config::get('database.connections.mysql.database');
                        $dsn = "mysql:host=$dbhost:$dbport;dbname=$dbname";
                        $db = new \PDO($dsn, Config::get('database.connections.mysql.username'), Config::get('database.connections.mysql.password'));
                        $arr = explode('.',$fileName);
                        self::createTables($db, $dbpk, $uploadPath . $arr['0'].'/create.sql');
                       // 删除上传的压缩包文件
                        unlink($filePath);
                        return ['code' => 200 ,'msg' => '上传成功'];
                    } else {
                        return ['code' => 201 ,'msg' => '无法打开压缩文件'];
                    }
                }
            }  else {
                // 删除上传的文件
                unlink($filePath);
                return ['code' => 201 ,'msg' => '不支持的文件类型'];
            }
        } else {
            return ['code' => 201 ,'msg' => '文件上传失败'];
        }
    }

    public static function createTables($db, $pk, $sql_file = '')
    {
        $sql = str_replace(
            ['{{$pk}}'],
            [$pk],
            file_get_contents($sql_file)
        );
        $sql_array = preg_split("/;[\r\n]+/", $sql);
        foreach ($sql_array as $k => $v) {
            if (!empty($v)) {
                try {
                    if (substr($v, 0, 12) == 'CREATE TABLE') {
                        $name = preg_replace("/^CREATE TABLE `(\w+)` .*/s", "\\1", $v);
                        $msg = "创建数据表{$name}";
                        $res = $db->query($v);
                        if ($res == false) {
                            return $msg . '失败';
                        }
                    } else {
                        $res = $db->query($v);
                        if ($res == false) {
                            return '数据插入失败';
                        }
                    }
                } catch (Exception $exception) {

                }
            }
        }
        return false;
    }

    /**
     * 校验并解压 zip，防止 Zip-Slip。
     */
    private static function extractZipSafely(\ZipArchive $zip, string $destination): bool
    {
        if (!is_dir($destination)) {
            @mkdir($destination, 0755, true);
        }
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entryName = $zip->getNameIndex($i);
            if (!self::isSafeZipPath($entryName)) {
                return false;
            }
        }
        return $zip->extractTo($destination);
    }

    private static function isSafeZipPath(string $path): bool
    {
        $normalized = str_replace('\\', '/', $path);
        if ($normalized === '') {
            return false;
        }
        if ($normalized[0] === '/' || str_contains($normalized, '../') || str_contains($normalized, '..\\')) {
            return false;
        }
        if (preg_match('/^[A-Za-z]:/', $normalized)) {
            return false;
        }
        return true;
    }

    /**
     * 解析 upDelete 文本，支持 JSON 或按行列举的路径。
     */
    private static function readDeleteManifest(string $path): array
    {
        $content = trim((string)@file_get_contents($path));
        if ($content === '') {
            return [];
        }
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $result = [];
        foreach ($lines as $line) {
            $line = trim($line, " \t\n\r\0\x0B,'\"");
            if ($line === '' || $line === '[' || $line === ']') {
                continue;
            }
            $result[] = $line;
        }
        return $result;
    }

    private static function removePaths(array $entries, string $rootPath): void
    {
        foreach ($entries as $value) {
            if (!is_string($value)) {
                continue;
            }
            $safePath = self::sanitizeRelativePath($value);
            if ($safePath === null) {
                continue;
            }
            $base = rtrim($rootPath, "\\/");
            $target = $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $safePath);
            if (is_dir($target)) {
                delete_dir($target);
            } elseif (file_exists($target)) {
                unlink($target);
            }
        }
    }

    private static function sanitizeRelativePath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', $path);
        $normalized = preg_replace('#^(\./)+#', '', $normalized);
        if ($normalized === null) {
            return null;
        }
        $normalized = ltrim((string)$normalized, '/');
        if ($normalized === '' || str_contains($normalized, '../')) {
            return null;
        }
        if (preg_match('/^[A-Za-z]:/', $normalized)) {
            return null;
        }
        return $normalized;
    }
}
