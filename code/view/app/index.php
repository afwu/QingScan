{include file='public/head' /}
<div class="col-md-12 ">
    <?php
    $searchArr = [
        'action' => url('app/index'),
        'method' => 'get',
        'inputs' => [
            ['type' => 'select', 'name' => 'statuscode', 'options' => $statuscodeArr, 'frist_option' => '状态码'],
            ['type' => 'select', 'name' => 'cms', 'options' => $cmsArr, 'frist_option' => 'CMS系统'],
            ['type' => 'select', 'name' => 'server', 'options' => $serverArr, 'frist_option' => '服务'],
        ],
        'btnArr' => [
            ['text' => '添加', 'ext' => [
                "class" => "btn btn-outline-success",
                "data-bs-toggle" => "modal",
                "data-bs-target" => "#exampleModal",
            ]]
        ]]; ?>
    {include file='public/search' /}

    <div class="row tuchu">
        <div class="col-md-12">
            <form class="row g-3" id="frmUpload" action="<?php echo url('app/batch_import') ?>" method="post"
                  enctype="multipart/form-data">
                <div class="col-auto">
                    <input type="file" class="form-control form-control" name="file" accept=".xls,.csv" required/>
                </div>
                <div class="col-auto">
                    <input type="submit" class="btn btn-outline-info" value="批量添加项目">
                </div>
                <div class="col-auto">
                    <a href="<?php echo url('app/downloaAppTemplate') ?>"
                       class="btn btn-outline-success">下载模板</a>
                </div>
            </form>
        </div>
    </div>
    <div class="row tuchu">
        <div class="col-md-12 ">
            <table class="table table-bordered table-hover table-striped">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>名称</th>
                    <th>是否存在waf</th>
                    <th>创建时间</th>
                    <th>是否内网</th>
                    <th style="width: 200px">操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($list as $value) { ?>
                    <tr>
                        <td><?php echo $value['id'] ?></td>
                        <td class="ellipsis-type">
                            <a href="{$value['url']}" title="{$value['url']}" target="_blank">{$value['name']} </a>
                        </td>
                        <td><?php echo $value['is_waf'] ?></td>
                        <td><?php echo date('Y-m-d H:i', strtotime($value['create_time'])) ?></td>
                        <td>{$value['is_intranet']}</td>
                        <td>
                            <?php if($value['xray_agent_port'] ?? ''){?>
                                <a href="javascript:;" onclick="start_agent(<?php echo $value['id'] ?>)"
                                   class="btn btn-sm btn-outline-success">关闭代理</a>
                            <?php }else{?>
                                <a href="javascript:;" onclick="start_agent(<?php echo $value['id'] ?>)"
                                   class="btn btn-sm btn-outline-success">启动代理</a>
                            <?php }?>
                            <a href="<?php echo url('details', ['id' => $value['id']]) ?>"
                               class="btn btn-sm btn-outline-primary">查看详情</a>
                            <a href="<?php echo url('app/qingkong', ['id' => $value['id']]) ?>"
                               onClick="return confirm('确定要清空数据重新扫描吗?')"
                               class="btn btn-sm btn-outline-warning">重新扫描</a>
                            <a href="<?php echo url('app/del', ['id' => $value['id']]) ?>"
                               onClick="return confirm('确定要删除吗?')"
                               class="btn btn-sm btn-outline-danger">删除</a>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
                <?php if(empty($list)){?>
                    <tr><td colspan="8" class="text-center">暂无目标</td></tr>
                <?php }?>
            </table>
        </div>
        {include file='public/fenye' /}
    </div>

    <style>
        .modal-dialog {
            width: 600px;
        }
    </style>
    <!-- Modal -->
    {include file='app/add_modal' /}
    {include file='app/set_modal' /}

    {include file='public/footer' /}
    <script>
        function start_agent(id) {
            $.ajax({
                type: "post",
                url: "<?php echo url('start_agent')?>",
                data: {id: id},
                dataType: "json",
                success: function (data) {
                    alert(data.msg)
                    if (data.code) {
                        window.setTimeout(function(){
                            location.reload();
                        },1000);
                    }
                }
            });
        }
    </script>
