<?php

namespace app\controller;

use app\model\AppModel;
use app\model\CodeCheckModel;
use app\model\FortifyModel;
use app\model\CodeModel;
use think\facade\Db;
use think\facade\View;
use think\Request;


class Code extends Common
{

    public function index(Request $request)
    {
        $pageSize = 25;

        $where[] = ['is_delete', '=', 0];
        $map = [];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $where[] = ['user_id', '=', $this->userId];
            $map[] = ['user_id', '=', $this->userId];
        }
        $search = $request->param('search');
        if (!empty($search)) {
            $where[] = ['name', 'like', "%{$search}%"];
        }
        $list = Db::table('code')->where($where)->order('scan_time', 'desc')->paginate($pageSize);

        $data['list'] = $list->toArray()['data'];

        //查询数量
        $codeIds = array_column($data['list'], 'id');
        $data = array_merge($data, CodeModel::getScanNum($codeIds));
        // 获取分页显示
        $data['page'] = $list->render();

        return View::fetch('list', $data);
    }

    public function rescan(Request $request)
    {
        $id = $request->param('id');
        $map[] = ['id', '=', $id];

        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $map[] = ['user_id', '=', $this->userId];
        }

        $array = [
            'scan_time' => '2000-01-01 00:00:00',
            'sonar_scan_time' => '2000-01-01 00:00:00',
            'kunlun_scan_time' => '2000-01-01 00:00:00',
            'semgrep_scan_time' => '2000-01-01 00:00:00',
            'composer_scan_time' => '2000-01-01 00:00:00',
            'java_scan_time' => '2000-01-01 00:00:00',
            'python_scan_time' => '2000-01-01 00:00:00',
            'webshell_scan_time' => '2000-01-01 00:00:00',
        ];
        Db::table('code')->where(['id' => $id])->save($array);
        Db::table('fortify')->where(['code_id' => $id])->delete();
        Db::table('semgrep')->where(['code_id' => $id])->delete();
        Db::table('code_webshell')->where(['code_id' => $id])->delete();
        Db::table('code_composer')->where(['code_id' => $id])->delete();
        Db::table('code_python')->where(['code_id' => $id])->delete();
        Db::table('code_java')->where(['code_id' => $id])->delete();

        return redirect($_SERVER['HTTP_REFERER']);

    }

    public function details(Request $request)
    {

        $codeId = $request->param('id');
        $where[] = ['code_id', '=', $codeId];
        $map[] = ['id', '=', $codeId];

        $where1 = [];
//        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
//            //$where[] = ['user_id','=',$this->userId];
//            $map[] = ['user_id', '=', $this->userId];
//            $where1[] = ['user_id', '=', $this->userId];
//        }
        $data['info'] = Db::name('code')->where($map)->find();

        $data['fortify'] = Db::table('fortify')->where($where)->where($where1)->order("id", 'desc')->limit(0, 10)->select()->toArray();
        $data['semgrep'] = Db::table('semgrep')->where($where)->where($where1)->order("id", 'desc')->limit(0, 10)->select()->toArray();
        $data['hema'] = Db::table('code_webshell')->where($where)->where($where1)->order("id", 'desc')->limit(0, 10)->select()->toArray();
        $data['java'] = Db::table('code_java')->where($where)->where($where1)->order("id", 'desc')->limit(0, 10)->select()->toArray();
        $data['python'] = Db::table('code_python')->where($where)->where($where1)->order("id", 'desc')->limit(0, 10)->select()->toArray();
        $data['php'] = Db::table('code_composer')->where($where)->where($where1)->order("id", 'desc')->limit(0, 10)->select()->toArray();
        $projectArr = Db::table('code')->where($map)->select()->toArray();
        $data['projectArr'] = array_column($projectArr, null, 'id');

        return View::fetch('details', $data);
    }

    public function code_del(Request $request)
    {
        $id = $request->param('id');
        $map[] = ['id', '=', $id];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $map[] = ['user_id', '=', $this->userId];
        }
        if (Db::name('code')->where($map)->update(['is_delete' => 1])) {
            return redirect($_SERVER['HTTP_REFERER']);
        } else {
            $this->error('删除失败');
        }
    }

    public function bug_list(Request $request)
    {
        //接收参数
        $page = $request->param('page', 1);
        $search = $request->param('search', '');
        $pageSize = 25;
        $pid = $request->param('code_id');
        $Folder = $request->param('Folder');
        $Category = $request->param('Category');
        $Primary_filename = $request->param('Primary_filename');
        $check_status = $request->param('check_status', '-2');

        //准备查询条件
        //$where = ['check_status' => 0];
        $where = ['is_delete' => 0];
        $where = $pid ? array_merge($where, ['code_id' => $pid]) : $where;
        $where = $Primary_filename ? array_merge($where, ['Primary_filename' => $Primary_filename]) : $where;
        $where = !empty($Folder) ? array_merge($where, ['Folder' => $Folder]) : $where;
        $where = !empty($Category) ? array_merge($where, ['Category' => $Category]) : $where;
        if (!empty($search)) {
            $where[] = ['Primary', 'like', "%{$search}%"];
        }
        if (in_array($check_status, [0, 1, 2])) {
            $where = array_merge($where, ['check_status' => $check_status]);
        }
        $map[] = ['is_delete', '=', 0];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $where = array_merge($where, ['user_id' => $this->userId]);
            $map[] = ['user_id', '=', $this->userId];
        }

        $fortifyApi = Db::table('fortify')->where($where)->order('id', 'desc');;
        $fortifyApi = $fortifyApi->where("Folder != 'Low'");
        $fortifyCountApi = Db::table('fortify')->where($where)->where("Folder != 'Low'");
        //获取分类分组
        $categoryList = Db::table('fortify')->where($where)->where("Folder != 'Low'")->group('Category')->field('Category')->select()->toArray();
        $CategoryList = array_column($categoryList, 'Category');
        //查询项目数据
        $projectArr = Db::table('code')->where($map)->select()->toArray();
        $projectArr = array_column($projectArr, null, 'id');
        //获取文件分组
        $fileList = Db::table('fortify')->where("Folder != 'Low'")->where($where)->field('Primary_filename')->group('Primary_filename')->select()->toArray();
        $fileList = array_column($fileList, 'Primary_filename');
        //查询项目列表
        $fortifyProjectList = Db::table('fortify')->where($where)->where("Folder != 'Low'")->field('code_id')->group('code_id')->select()->toArray();
        $fortifyProjectList = array_column($fortifyProjectList, 'code_id');
        $fortifyProjectList = Db::table('code')->whereIn('id', $fortifyProjectList)->field('id,name')->select()->toArray();
        $fortifyProjectList = array_column($fortifyProjectList, 'name', 'id');
        $objData = $fortifyApi->order('id', 'desc')->paginate(['list_rows' => $pageSize, 'query' => request()->param()]);
        $list = $objData->items();
        $pageRaw = $objData->render();
        //获取列表数据
        //$list = $fortifyApi->order('id', 'desc')->limit($pageSize)->page($page)->select()->toArray();
        foreach ($list as &$value) {
            $value['Source'] = json_decode($value['Source'], true);
            $value['Primary'] = json_decode($value['Primary'], true);
        }
        // 获取分页显示
        //$pageRaw = $fortifyApi->paginate($pageSize)->render();

        //分配数据
        $data = [
            'search' => $search,
            'GET' => $_GET,
            'page' => $pageRaw,
            'list' => $list,
            'count' => $fortifyCountApi->count(), 'pageSize' => $pageSize,
            'CategoryList' => $CategoryList,
            'projectArr' => $projectArr,
            'fortifyProjectList' => $fortifyProjectList,
            'fileList' => $fileList,
            'check_status_list' => ['未审计', '有效漏洞', '无效漏洞']
        ];

        return View::fetch('bug_list', $data);
    }

    public function bug_details(Request $request)
    {
        $id = $request->param('id');
        if (!$id) {
            $this->error('参数不能为空');
        }
        $where[] = ['id', '=', $id];
        $map = [];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $where[] = ['user_id', '=', $this->userId];
            $map[] = ['user_id', '=', $this->userId];
        }
        $info = Db::table('fortify')->where($where)->find();
        if (!$info) {
            $this->error('数据不存在');
        }
        $info['Source'] = json_decode($info['Source'], true);
        $info['Primary'] = json_decode($info['Primary'], true);

        $upper_id = Db::name('fortify')->where('id', '<', $id)->where($map)->order('id', 'desc')->value('id');
        $info['upper_id'] = $upper_id ?: $id;
        $lower_id = Db::name('fortify')->where('id', '>', $id)->where($map)->order('id', 'asc')->value('id');
        $info['lower_id'] = $lower_id ?: $id;


        $data['info'] = $info;

        $projectArr = Db::table('code')->where($map)->select()->toArray();
        $data['projectArr'] = array_column($projectArr, null, 'id');
        //var_dump($info['Source']);exit;

        return View::fetch('bug_details', $data);
    }

    public function bug_del(Request $request)
    {
        $id = $request->param('id');
        $map[] = ['id', '=', $id];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $map[] = ['user_id', '=', $this->userId];
        }
        if (Db::name('fortify')->where($map)->update(['is_delete' => 1])) {
            return redirect($_SERVER['HTTP_REFERER']);
        } else {
            $this->error('删除失败');
        }
    }

    public function semgrep_details(Request $request)
    {
        $id = $request->param('id');
        if (!$id) {
            $this->error('参数不能为空');
        }
        $where[] = ['id', '=', $id];
        $map = [];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $where[] = ['user_id', '=', $this->userId];
            $map[] = ['user_id', '=', $this->userId];
        }
        $info = Db::table('semgrep')->where($where)->find();
        if (!$info) {
            $this->error('数据不存在');
        }
        $upper_id = Db::name('semgrep')->where('id', '<', $id)->where($map)->order('id', 'desc')->value('id');
        $info['upper_id'] = $upper_id ?: $id;
        $lower_id = Db::name('semgrep')->where('id', '>', $id)->where($map)->order('id', 'asc')->value('id');
        $info['lower_id'] = $lower_id ?: $id;
        $projectInfo = Db::name('code')->where($map)->where('id', $info['code_id'])->find();
        $data['project'] = $projectInfo;
        $info['project_name'] = $projectInfo['name'] ?? '';

        $data['info'] = $info;
        return View::fetch('semgrep_details', $data);
    }

    public function kunlun_list(Request $request)
    {
        $data = [];

        $where[] = ['is_delete', '=', 0];
        $pageSize = 25;
        $search = $request->param('search', '');
        $pid = $request->param('code_id');
        $level = $request->param('level'); // 等级
        $Category = $request->param('Category');   // 分类
        $filename = $request->param('filename');   // 文件名
        $check_status = $request->param('check_status');   // 审核状态
        if (!empty($pid)) {
            $where[] = ['scan_project_id', '=', $pid];
        }
        if (!empty($level)) {
            $where[] = ['is_active', '=', $level];
        }
        if (!empty($Category)) {
            $where[] = ['result_type', '=', $Category];
        }
        if (!empty($filename)) {
            $where[] = ['vulfile_path', '=', $filename];
        }
        if ($check_status !== null && in_array($check_status, [0, 1, 2])) {
            $where[] = ['check_status', '=', $check_status];
        }
        $map = [];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $where[] = ['user_id', '=', $this->userId];
            $map[] = ['user_id', '=', $this->userId];
        }
        $semgrepApi = Db::connect('kunlun')->table("index_scanresulttask");

        $list = $semgrepApi->where($where)->order('id', 'desc')->paginate($pageSize);
        // 获取分页显示
        $data['list'] = $list->toArray()['data'];
        $data['page'] = $list->render();

        $projectArr = Db::connect('kunlun')->table("index_project")->where($map)->select()->toArray();
        $projectArr = array_column($projectArr, null, 'id');
        $data['projectArr'] = $projectArr;
        $data['CategoryList'] = $semgrepApi->where($where)->group('result_type')->column('result_type');
        $projectList = Db::connect('kunlun')->table("index_scanresulttask")->alias('a')
            ->leftJoin('index_project b', 'b.id=a.scan_project_id')
            ->where($where)
            ->group('scan_project_id')
            ->field('b.id,b.project_name as name')
            ->select()
            ->toArray();
        $data['projectList'] = array_column($projectList, 'name', 'id');
        $data['fileList'] = $semgrepApi->where($where)->group('vulfile_path')->column('vulfile_path');
        $data['check_status_list'] = ['未审计', '有效漏洞', '无效漏洞'];
        return View::fetch('kunlun_list', $data);
    }

    public function kunlun_details(Request $request)
    {
        $id = $request->param('id');
        if (!$id) {
            $this->error('参数不能为空');
        }
        $where[] = ['id', '=', $id];
        $map = [];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $where[] = ['user_id', '=', $this->userId];
            $map[] = ['user_id', '=', $this->userId];
        }
        $semgrepApi = Db::connect('kunlun')->table("index_scanresulttask");
        $info = $semgrepApi->where($where)->find();
        if (!$info) {
            $this->error('数据不存在');
        }
        $upper_id = Db::connect('kunlun')->table("index_scanresulttask")->where($map)->where('id', '<', $id)->order('id', 'desc')->value('id');
        $info['upper_id'] = $upper_id ?: $id;
        $lower_id = Db::connect('kunlun')->table("index_scanresulttask")->where($map)->where('id', '>', $id)->order('id', 'asc')->value('id');
        $info['lower_id'] = $lower_id ?: $id;

        $data['info'] = $info;
        //var_dump($info);exit;
        return View::fetch('kunlun_details', $data);
    }

    public function kunlun_del(Request $request)
    {
        $id = $request->param('id');
        $map[] = ['id', '=', $id];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $map[] = ['user_id', '=', $this->userId];
        }
        if (Db::connect('kunlun')->table("index_scanresulttask")->where($map)->update(['is_delete' => 1])) {
            return redirect($_SERVER['HTTP_REFERER']);
        } else {
            $this->error('删除失败');
        }
    }

    public function semgrep_list(Request $request)
    {
        $data = [];
        $search = $request->param('search', '');
        $pageSize = 25;
        $where[] = ['is_delete', '=', 0];
        $pid = $request->param('code_id');
        $level = $request->param('level'); // 等级
        $Category = $request->param('Category');   // 分类
        $filename = $request->param('filename');   // 文件名
        $check_status = $request->param('check_status');   // 审核状态
        if (!empty($pid)) {
            $where[] = ['code_id', '=', $pid];
        }
        if (!empty($level)) {
            $where[] = ['extra_severity', '=', $level];
        }
        if (!empty($Category)) {
            $where[] = ['check_id', '=', $Category];
        }
        if (!empty($filename)) {
            $where[] = ['path', '=', $filename];
        }
        if ($check_status !== null && in_array($check_status, [0, 1, 2])) {
            $where[] = ['check_status', '=', $check_status];
        }
        if (!empty($search)) {
            $where[] = ['check_id', 'like', "%{$search}%"];
        }
        $map = [];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $where[] = ['user_id', '=', $this->userId];
            $map[] = ['user_id', '=', $this->userId];
        }
        $list = Db::table('semgrep')->where($where)->order('id', 'desc')->paginate(['list_rows' => $pageSize, 'query' => request()->param()]);
        $data['list'] = $list->toArray()['data'];
        $data['page'] = $list->render();

        $projectArr = Db::table('code')->where($map)->select()->toArray();
        $projectArr = array_column($projectArr, null, 'id');
        $data['projectArr'] = $projectArr;
        $data['CategoryList'] = Db::table('semgrep')->where($where)->group('check_id')->column('check_id');

        $data['fileList'] = Db::table('semgrep')->where($where)->group('path')->column('path');
        $data['check_status_list'] = ['未审计', '有效漏洞', '无效漏洞'];
        //查询项目列表
        $projectList = Db::table('semgrep')->where($where)->group('code_id')->column('code_id');
        $projectList = Db::table('code')->whereIn('id', $projectList)->field('id,name')->select()->toArray();
        $data['projectList'] = array_column($projectList, 'name', 'id');

        return View::fetch('semgrep_list', $data);
    }

    public function semgrep_del(Request $request)
    {
        $id = $request->param('id');
        $map[] = ['id', '=', $id];
        if ($this->auth_group_id != 5 && !in_array($this->userId, config('app.ADMINISTRATOR'))) {
            $map[] = ['user_id', '=', $this->userId];
        }
        if (Db::name('semgrep')->where($map)->update(['is_delete' => 1])) {
            return redirect($_SERVER['HTTP_REFERER']);
        } else {
            $this->error('删除失败');
        }
    }

    public function bug_detail(Request $request)
    {
        $id = $request->param('id');
        $data['base'] = FortifyModel::getInfo($id);
        $data['project'] = Db::table('code')->where('id', $data['base']['code_id'])->find();


        $data['Primary'] = json_decode($data['base']['Primary'], true);
        $data['Source'] = json_decode($data['base']['Source'], true);

        unset($data['base']['Primary']);
        unset($data['base']['Source']);

        $this->show('code/bug_detail', $data);
    }

    public function edit_modal(Request $request)
    {
        $id = $request->param('id');
        if (!$id) {
            $this->error('参数错误');
        }
        if ($request->isPost()) {
            $data['name'] = $request->param('name');
            $data['is_private'] = $request->param('is_private');
            $data['pulling_mode'] = $request->param('pulling_mode');
            $data['ssh_url'] = $request->param('ssh_url');
            $data['username'] = $request->param('username');
            $data['password'] = $request->param('password', '', 'htmlspecialchars');
            $data['private_key'] = $request->param('private_key');
            $data['fortify_scan_time'] = $request->param('fortify_scan_time');
            $data['semgrep_scan_time'] = $request->param('semgrep_scan_time');
            $data['kunlun_scan_time'] = $request->param('kunlun_scan_time');
            if ($data['is_private']) {
                if (strtolower($data['pulling_mode']) == 'ssh') {
                    if (!$data['private_key']) {
                        $this->error('私钥不能为空');
                    }
                } else {
                    if (!$data['username'] || !$data['password']) {
                        $this->error('用户名密码不能为空');
                    }
                }
            }
            if (Db::name('code')->where('id', $id)->update($data)) {
                return redirect(url('code/index'));
            } else {
                $this->error('修改失败');
            }
        } else {
            $data['info'] = Db::name('code')->where('id', $id)->find();
            return view('code/edit_modal', $data);
        }
    }


    public function hooks(Request $request)
    {
        $page = $request->param('page', 1);
        $author = $request->param('author', '');
        $code_id = $request->param('code_id', '');
        $pageSize = 25;

        $where = ['author' => ['>', 1]];
        $where = !empty($author) ? array_merge($where, ['code_check.author' => $author]) : $where;
        $where = !empty($code_id) ? array_merge($where, ['code_check.code_id' => $code_id]) : $where;

        //查询提交人
        $authList = Db::table('code_check')->where($where)->group('author')->field('author')->select()->toArray();
        $authList = array_column($authList, 'author');

        //查询项目ID
        $projectList = Db::table('code_check')->where($where)->group('code_id')->field('code_id')->select()->toArray();
        $projectList = array_column($projectList, 'code_id');

        //项目列表
        $projectArr = Db::table('code')->field('id,ssh_url,name')->select()->toArray();
        $projectArr = array_column($projectArr, null, 'id');

        //查询列表数据
        $data = ['pageSize' => $pageSize,
            'projectArr' => $projectArr,
            'authList' => $authList,
            'projectList' => $projectList,
            'GET' => $_GET,
        ];
        $data['count'] = Db::table('code_check')->count();
        $data['list'] = Db::table('code_check')
            ->LeftJoin('fortify', 'fortify.id = code_check.code_id')
            ->LeftJoin('gitlab_project p', 'p.id = code_check.code_id')
            ->where($where)
            ->order('code_check.id', 'desc')
            ->field("code_check.*,p.name,p.web_url")
            ->limit(25)
            ->page($page)
            ->select()->toArray();
        // 获取分页显示

        $data['page'] = Db::name('code_check')
            ->where($where)
            ->paginate($pageSize)->render();

        foreach ($data['list'] as &$value) {
            preg_match_all("/\/tmp.*?\.php/", $value['content'], $result);
            $result[0] = array_unique($result[0]);
            $tempStr = implode("\n", $result[0]);
            $tempStr = preg_replace("/\/tmp\/.*-\d{2}\//", "/", $tempStr);
            $value['bugFile'] = $tempStr;
        }

        return View::fetch('hooks', $data);
    }

    public function hook_detail(Request $request)
    {
        $id = intval($request->param('id'));

        $detail = Db::table('code_check')
                ->LeftJoin('gitlab_project p', 'p.id = code_check.code_id')
                ->where(['code_check.id' => $id])
                ->field("code_check.*,p.name,p.web_url")
                ->select()->toArray()[0] ?? [];

        $detail['error'] = json_decode($detail['content'], true)['error'];
        $detail['results'] = json_decode($detail['content'], true)['results'];
        unset($detail['content']);

        $data = ['detail' => $detail];
        $this->show('code_check/hook_detail', $data);
    }

    public function add()
    {
        $data['app_list'] = AppModel::getListByWhere([]);

        $this->show('code_check/add', $data);
    }

    public function _add_code(Request $request)
    {
        $data['name'] = $request->param('name');
        $data['is_private'] = $request->param('is_private');
        $data['pulling_mode'] = $request->param('pulling_mode');
        $data['ssh_url'] = $request->param('ssh_url');
        $data['username'] = $request->param('username');
        $data['password'] = $request->param('password');
        $data['private_key'] = $request->param('private_key');
        if ($data['is_private']) {
            if (strtolower($data['pulling_mode']) == 'ssh') {
                if (!$data['private_key']) {
                    $this->error('私钥不能为空');
                }
            } else {
                if (!$data['username'] || !$data['password']) {
                    $this->error('用户名密码不能为空');
                }
            }
        }
        CodeModel::addData($data);

        return redirect(url('code/index'));
    }

    public function _add(Request $request)
    {
        $content = base64_decode(urldecode($_REQUEST['content']));
        $author = $_REQUEST['author'];
        $version = $_REQUEST['version'];
        $project_hash = $_REQUEST['project_hash'];

        $where = ['hash' => $project_hash];

        $code_id = Db::name('gitlab_project')->where($where)->value('id');
        $temp = preg_match_all("/\/tmp.*?\.php/", $content, $result);
        $tempStr = implode("\n", $result[0]);
        $bugFile = preg_replace("/\/tmp\/.*-\d{2}\//", "/", $tempStr);


        if (empty($content) || empty(json_decode($content, true)['results'])) {
            echo "检测结果为空,暂不存储";
            return false;
        }


        $data = ['content' => $content,
            'author' => $author,
            'version' => $version,
            'project_hash' => $project_hash,
            'code_id' => $code_id,
            'files' => $bugFile
        ];

        $result = CodeCheckModel::addData($data);
        // $this->Location("index.php?s=code_check/index");
    }


    public function add_api_url()
    {
        $data['app_list'] = AppModel::getListByWhere([]);
        $this->show('code_check/add_api_url', $data);
    }

    public function _add_api_url()
    {
        CodeCheckModel::addData($_POST);
        $this->Location("index.php?s=code_check/index");
    }


    public function load_xml()
    {
        $cmd = "ls /data/fortify_result/*.xml";
        $fortifyRetDir = "/data/fortify_result";

        execLog($cmd, $result);
        foreach ($result as $value) {

            $fullPath = $value;
            $value = str_replace('/data/fortify_result/', "", $value);
            $value = str_replace('.xml', "", $value);

            $info = Db::name('gitlab_project')->where(['name' => $value])->find();
            $prName = $info['name'];
            //3. 转换结果
            $list = FortifyModel::getFortifData("{$fortifyRetDir}/{$prName}.xml");


            //4. 存储结果
            FortifyModel::addDataAll($info['id'], $list);
            //5. 更新状态
            if (file_exists($fullPath)) {
                $info['scan_time'] = date('Y-m-d H:i:s');
                Db::table('code')->update($info);
            }
        }
    }

    public function updateFileName()
    {
        $list = Db::name("fortify")->select()->toArray();

        foreach ($list as $value) {
            $Primary = empty($value['Primary']) ? [] : json_decode($value['Primary'], true);
            $Source = empty($value['Source']) ? [] : json_decode($value['Source'], true);

            $data = ['Source_filename' => $Source['FilePath'] ?? '', 'Primary_filename' => $Primary['FilePath']];

            Db::name("fortify")->where(['id' => $value['id']])->update($data);
        }
    }

    public function sonarQube()
    {
        $codePath = "/root/MyCode/work/codeCheck";
        $host = "http://127.0.0.1:9090";
        //判断目录是否存在
        if (!file_exists($codePath)) {
            mkdir($codePath, 0777, true);
        }

        while (true) {
            $endTime = date('Y-m-d', time() - 86400 * 15);
            $list = Db::table('code')->whereTime('sonar_scan_time', '<=', $endTime)->orderRand()->limit(1)->select()->toArray();

            foreach ($list as $value) {
                $prName = $value['name'];
                $prName = trimName($prName);

                $codeUrl = $value['ssh_url_to_repo'];
                //1. 拉取代码
                if (!file_exists("{$codePath}/{$prName}")) {
                    $cmd = "cd {$codePath}/ && git clone --depth=1 {$codeUrl}  $prName";
                    systemLog($cmd);
                }

                //创建项目
                $url = "{$host}/api/projects/create?";
                $param = ['name' => $prName, 'project' => $prName, 'visibility' => 'private'];
                $url .= http_build_query($param);
                $data = get_token($url);

                var_dump($data);
                //创建令牌
                $url = "{$host}/api/user_tokens/generate?";
                $param = ['name' => time()];
                $url .= http_build_query($param);
                $data = get_token($url);

                var_dump($data);
                //扫描代码
                $token = $data['token'];
                $cmd = "sonar-scanner  -Dsonar.projectKey={$prName}  -Dsonar.sources=.  -Dsonar.host.url={$host}  -Dsonar.login={$token}";

                $cmd = "cd {$codePath}/{$prName}  && {$cmd}";
                $result = systemLog($cmd);

                if (strstr(implode("", $result), "EXECUTION SUCCESS")) {
                    $value['sonar_scan_time'] = date('Y-m-d H:i:s');
                    Db::table('code')->update($value);
                }
            }
        }
    }


    public function scanBitbuket()
    {

        $codePath = "/data/codeCheck/";
        $list = scandir($codePath);

        array_shift($list);
        array_shift($list);

        $prName = "";
        foreach ($list as $prName) {
            $fortifyRetDir = "/data/fortify_result";
            //2. 扫描代码
            FortifyModel::startScan("{$codePath}/{$prName}", "{$fortifyRetDir}/{$prName}");
            //3. 转换结果
            $list = FortifyModel::getFortifData("{$fortifyRetDir}/{$prName}.xml");
        }
    }


    public function svnScan()
    {

        $codePath = "/root/mycode/work/svnCode_e";
        $fortifyRetDir = "/data/fortify_result";
        $username = "username";
        $password = "password";

        while (true) {
            $endTime = date('Y-m-d', time() - 86400 * 15);
            $list = Db::table('svn_project')->whereTime('scan_time', '<=', $endTime)->orderRand()->limit(1)->select()->toArray();

            foreach ($list as $value) {
                $prName = $value['name'];
                //1. 拉取代码
                if (!file_exists("{$codePath}/{$prName}")) {
                    $cmd = "cd {$codePath}/ && {$value['command']}  --username={$username}   --password={$password}";
                    echo $cmd . PHP_EOL;
                    systemLog($cmd);
                }

                //2. 扫描代码
                FortifyModel::startScan("{$codePath}/{$prName}", "{$fortifyRetDir}/{$prName}");

                //3. 转换结果
                $list = FortifyModel::getFortifData("{$fortifyRetDir}/{$prName}.xml");

                //4. 存储结果
                FortifyModel::addDataAll($value['id'], $list);

                //5. 更新
                if (file_exists("{$fortifyRetDir}/{$prName}.xml")) {
                    $value['scan_time'] = date('Y-m-d H:i:s');
                    Db::table('svn_project')->update($value);
                }
            }
        }
    }
}
