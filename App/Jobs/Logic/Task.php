<?php
/**
 * Created by PhpStorm.
 * User: yangcai
 * Date: 2018/6/5
 * Time: 16:28
 */

namespace App\Jobs\Logic;

use Core\AbstractInterface\ALogic;
use App\Jobs\Model\Task as Model;
use App\Jobs\Dispatcher\Tasks as JobsTasks;
use Cron\CronExpression;

/**
 * Class Task
 *
 * @package Jobs\Logic
 */
class Task extends ALogic
{
    function getList()
    {
        $model = new Model;
        $model->where('id', '>', 0);
        /* 分页 */
        $total = 0;
        if ($page = $this->request()->getPage()) {
            if ($page['is_first']) {
                $page['total'] = $model->count('id') | 0;
            }
            $model = $model->limit($page['start'], $page['limit']);
            $this->response()->setPage($page);
        }
        /* 查询 */
        if ($where = $this->request()->getWhere()) {
            $where = 0 < sizeof($where) ? join(' and ', $where) : array_shift($where);
            $model = $model->where($where);
        }
        if ($search = $this->request()->getExtend('search')) {
            $model = $model->whereLike('task_name', "%{$search}%");
            $model = $model->whereLike('command', "%{$search}%", 'OR');
        }
        /* 排序 */
        if ($order = $this->request()->getOrder()) {
            $model = $model->order($order);
        }
        try {
            $ret = $model->select();
        } catch (\Exception $e) {
            return $this->response()
                ->setMsg($e->getMessage())
                ->error();
        }
        $list         = $ret->toArray();
        $responseData = $list;
        return $this->response()
            ->setData($responseData)
            ->success();
    }

    function getInfo()
    {
        if (!$id = $this->request()->getId()) {
            return $this->response()->error();
        }
        if (!$model = (new Model)->get($id)) {
            return $this->response()->error();
        }
        $responseData = $model->toArray();
        return $this->response()
            ->setData($responseData)
            ->success();
    }

    function create()
    {
        if (!$requestData = $this->request()->getData()) {
            return $this->response()->error();
        }
        try {
            CronExpression::factory($requestData["cron_spec"]);
        } catch (\Exception $e) {
            return $this->response()->error('时间表达式格式错误！<br>' . " ( {$e->getMessage()} )");
        }
        $model = new Model;
        if (!$ret = $model->save($requestData)) {
            return $this->response()->error();
        }
        $responseData = $model->toArray();
        return $this->response()
            ->setData($responseData)
            ->success();
    }

    function update()
    {
        if (!$id = $this->request()->getId()) {
            return $this->response()->error();
        }
        if (!$requestData = $this->request()->getData()) {
            return $this->response()->error();
        }
        if (isset($requestData['cron_spec'])) {
            try {
                CronExpression::factory($requestData["cron_spec"]);
            } catch (\Exception $e) {
                return $this->response()->error('时间表达式格式错误！<br>' . " ( {$e->getMessage()} )");
            }
        }
        if (!$model = (new Model)->get($id)) {
            return $this->response()->error();
        }
        if ($model->getAttr('user_id')) {
            unset($requestData['user_id']);
        }
        if (!$ret = $model->save($requestData)) {
            return $this->response()->error();
        }
        return $this->response()
            ->success();
    }

    function delete()
    {
    }

    function _EVENT_beforeUpdate()
    {
        if (0 == $status = $this->request()->getData('status')) {
            if (!$id = $this->request()->getId()) {
                return $this->response()->error();
            }
            JobsTasks::getInstance()->deleteTask($id);
        }
    }

    function _EVENT_afterUpdate()
    {
        if (false === $status = $this->request()->getData()) {
            return;
        }
//        if (0 == $status = $this->request()->getData('status')) {
//            return;
//        }
        if (!$id = $this->request()->getId()) {
            return;
        }
        if (!$model = (new Model)->get($id)) {
            return;
        }
        $data = $model->toArray();
        JobsTasks::getInstance()->saveTask($data);
    }
}