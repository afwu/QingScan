<?php

namespace app\controller;

use think\facade\Db;
use think\facade\View;
use think\Request;

class PluginStore extends Common
{
    public $plugin_store_domain;

    public function initialize()
    {
        $this->plugin_store_domain = config('app.plugin_store.domain_name');

        parent::initialize();
    }

    public function index(){
        $result = curl_get($this->plugin_store_domain.'plugin_store/list');
        $list = json_decode($result,true);
        if (!isset($list['data'])) {
            $this->error('获取插件信息失败');
        }
        $list = $list['data'];
        foreach ($list as &$v) {
            $v['is_install'] = 0;
            $v['status'] = '未安装';
            $where['name'] = $v['name'];
            $info = Db::name('plugin_store')->where($where)->find();
            if ($info) {
                $v['is_install'] = 1;
                if ($info['status']) {
                    $v['status'] = '开启';
                } else {
                    $v['status'] = '禁用';
                }
                $v['plugin_id'] = $info['id'];
            }
        }
        $data['list'] = $list;
        return View::fetch('index',$data);
    }

    public function install(Request $request){
        //echo '<pre>';
        $code = $request->param('code');
        //echo md5('dsaewqvdi43tbnkjad21');exit;
        $result = curl_get($this->plugin_store_domain.'plugin_store/code_info?code='.$code);
        $result = json_decode($result,true);
        if (!$result['code']) {
            return $this->apiReturn(0,[],'插件安装失败，错误原因：'.$result['msg']);
        }
        $id = $request->param('id',0,'intval');
        $result = curl_get($this->plugin_store_domain.'plugin_store/info?id='.$id);
        $info = json_decode($result,true);
        if (!$info['code']) {
            return $this->apiReturn(0,[],'插件安装失败，错误原因：'.$result['msg']);
        }
        $info = $info['data'];
        if (Db::name('plugin_store')->where('name',$info['name'])->count('id')) {
            return $this->apiReturn(0,[],'插件安装失败，错误原因：插件已安装');
        }
        // 兑换
        $result = curl_get($this->plugin_store_domain.'plugin_store/use_code?code='.$code.'&id='.$id);
        $result = json_decode($result,true);
        if (!$result['code']) {
            return $this->apiReturn(0,[],'插件安装失败，错误原因：'.$result['msg']);
        }
        // 下载链接
        $download_url = $result['data']['download_url'];
        $save_dir = \think\facade\App::getRuntimePath().'plugins/'; // 服务资源目录
        $filename = substr($download_url, strrpos($download_url, '/') + 1);
        if (downloadFile($download_url, $save_dir, $filename) === true) {
            $file_path = $save_dir.$filename;
            // 解压目录
            $zip = new \ZipArchive();
            if ($zip->open($file_path) === TRUE) {//中文文件名要使用ANSI编码的文件格式
                $zip->extractTo($save_dir);//提取全部文件
                $zip->close();

                $app = \think\facade\App::getAppPath();
                $temp_plugin_path = $save_dir.$info['name'].'/';

                // 执行sql文件
                try {
                    foreach (scandir($temp_plugin_path.'sqlOrsh') as $value){
                        if($value != '.' && $value != '..'){
                            $preg = "/(.*?)\.sql/";
                            if (preg_match($preg,$value)) {
                                $content = file_get_contents($temp_plugin_path.'sqlOrsh'.'/'.$value);
                                $sqlArr = explode(';',$content);
                                foreach ($sqlArr as $sql) {
                                    if ($sql) {
                                        @Db::execute($sql.';');
                                    }
                                }
                            }
                        }
                    }
                    // 移动相应的文件
                    copydir($temp_plugin_path.'sqlOrsh',$app.'plugins/'.$info['name']);
                    copydir($temp_plugin_path.'controller',$app.'controller');
                    copydir($temp_plugin_path.'model',$app.'model');
                    copydir($temp_plugin_path.'view',$app.'../view');
                    copydir($temp_plugin_path.'tools',$app.'../../tools/plugins/'.$info['name']);

                    // 删除压缩包目录文件
                    deldir($temp_plugin_path);
                    @unlink($file_path);

                    $data = [
                        'status'=>1,
                        'create_time'=>date('Y-m-d H:i:s',time()),
                        'name'=>$info['name'],
                        'title'=>$info['title'],
                        'version'=>$info['version'],
                        'description'=>$info['description'],
                        'code'=>$code,
                    ];
                    Db::name('plugin_store')->insert($data);

                    return $this->apiReturn(1,[],'插件安装成功');

                } catch (\Exception $e) {
                    // 回退兑换码
                    $result = curl_get($this->plugin_store_domain.'plugin_store/no_use_code?code='.$code);
                    $result = json_decode($result,true);
                    if (!$result['code']) {
                        return $this->apiReturn(0,[],'插件安装失败，错误原因：'.$result['msg']);
                    } else {
                        return $this->apiReturn(0,[],'插件安装失败，错误原因'.$e->getMessage());
                    }
                }
            } else {
                // 回退兑换码
                $result = curl_get($this->plugin_store_domain.'plugin_store/no_use_code?code='.$code);
                $result = json_decode($result,true);
                if (!$result['code']) {
                    return $this->apiReturn(0,[],'插件安装失败，错误原因：'.$result['msg']);
                } else {
                    return $this->apiReturn(0,[],'插件安装失败，解压失败');
                }
            }
        } else {
            // 回退兑换码
            $result = curl_get($this->plugin_store_domain.'plugin_store/no_use_code?code='.$code);
            $result = json_decode($result,true);
            if (!$result['code']) {
                return $this->apiReturn(0,[],'插件安装失败，错误原因：'.$result['msg']);
            } else {
                return $this->apiReturn(0,[],'插件安装失败，请稍候重试');
            }
        }
    }

    public function uninstall(Request $request){
        $id = $request->param('id',0,'intval');
        $info = Db::name('plugin_store')->where('id',$id)->find();
        if (!$info) {
            $this->error('插件未安装');
        }
        // 删除相关信息
        $pathArr = getUninstallPath('TestDemo');
        foreach ($pathArr as $value) {
            if(!is_dir($value)) {
                @unlink($value);
            } else {
                deldir($value);
            }
        }
        Db::name('plugin_store')->where('id',$id)->delete();
        $this->success('插件卸载成功',url('index'));
    }
}