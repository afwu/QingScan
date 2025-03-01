<?php

namespace app\controller;


use app\model\AppModel;
use app\model\UrlsModel;
use app\model\TaskModel;
use app\model\XrayModel;
use app\model\GroupModel;

class Targets extends Common
{

    private $vulnField = [
        'affects_url' => 'url',
        'last_seen' => 'create_time',
        'target_id' => 'app_id',
        'vuln_id' => 'id',
        'vt_name' => 'poc'
    ];

    private $targetField = [
        'address' => 'url',
        'target_id' => 'id',
    ];

    public function target_groups()
    {
        $paramJson = file_get_contents('php://input');

        $data = json_decode($paramJson, true);
        $groupId = GroupModel::addData($data);

        echo json_encode(['group_id' => $groupId]);
    }


    public function add()
    {
        $paramJson = file_get_contents('php://input');


        $param = json_decode($paramJson, true);

        foreach ($param['targets'] as $key => $data) {
            $data['url'] = $data['address'];
            $data['group_id'] = $param['groups'][$key];

            if ($this->auth_group_id != 5 && !in_array($this->userId,config('app.ADMINISTRATOR'))) {
                $data['user_id'] = $this->userId;
            }

            $ids[]['target_id'] = AppModel::addData($data);
        }

        echo json_encode(['targets' => $ids]);
    }


    public function getTargetByGroup()
    {
        $l = getParam('l', 10);
        $groupId = getParam('group_id');

        $targetList = AppModel::getListByGroup($groupId, $l);

        foreach ($targetList as &$value) {
            foreach ($this->targetField as $k => $v) {
                $value[$k] = $value[$v];
                unset($value[$v]);
            }
            $value['last_scan_date'] = '2021-02-26T10:00:13.134630+00:00';
        }
        echo json_encode($targetList);
    }

    public function scans()
    {
        $paramJson = file_get_contents('php://input');


        $param = json_decode($paramJson, true);


        //查询目标ID
        $id = intval($param['target_id']);

        $appInfo = AppModel::getInfo($id);
        $user_id = $appInfo['user_id'];
        $url = $appInfo['url'];
        TaskModel::startTask($id, $url,$user_id);

        echo $paramJson;
    }


    public function vulnerabilities()
    {

        $group_id = getParam('group_id');

        $where['group_id'] = $group_id;
        $appList = AppModel::getListByWhere($where, 20);


        $appIdArr = array_column($appList, 'id');

        $bugList = XrayModel::getListByWhere(['app_id' => ['in', $appIdArr]]);


        foreach ($bugList as &$value) {
            foreach ($this->vulnField as $k => $v) {
                $value[$k] = $value[$v];
                unset($value[$v]);
            }
            $value['severity'] = 3;
            $value['status'] = 'open';
        }

        echo json_encode($bugList);
    }

    public function getBugInfo()
    {
        $id = getParam('vuln_id');

        $vulnInfo = XrayModel::getInfo($id);
        foreach ($this->vulnField as $k => $v) {
            $vulnInfo[$k] = $vulnInfo[$v];
            unset($vulnInfo[$v]);
        }

        $vulnInfo['description'] = '漏洞描述';
        $vulnInfo['detail'] = json_encode($vulnInfo['detail']);
        $vulnInfo['request'] = '请求参数';
        $vulnInfo['response_info'] = '返回数据';
        $vulnInfo['severity'] = 3;
        $vulnInfo['status'] = 'open';
        $vulnInfo['severity'] = 3;

        echo json_encode($vulnInfo);
    }

    public function http_response()
    {
        $vulnId = getParam('vuln_id');

        echo 'HTTP/1.1 200 OK
Date: Fri, 26 Feb 2021 10:03:20 GMT
Content-Type: text/html
Connection: keep-alive
Server: WAF3.0
X-Powered-By: PHP/5.4.16
Cache-Control: no-cache, must-revalidate
Content-Length: 265

Array
(
    [id] => 1
    [num] => 11
)
<br><br> has this record. 

<br><br><br>';
    }


    public function get_target_groups()
    {
        $size = getParam('l');
        $q = addslashes(getParam('q'));


        $where = [];

        if (!empty($q)) {
            $where['name'] = ['like', "%$q%"];
        }
        $list = GroupModel::getListByWhere($where, $size);

        foreach ($list as &$value) {
            $value['group_id'] = $value['id'];
            $value['target_count'] = GroupModel::getTargetNum($value['id']);
            $value['vuln_count'] = GroupModel::getVulnNum($value['id']);
        }


        echo json_encode($list);
    }

}