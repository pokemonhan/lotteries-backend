<?php

namespace App\Models\Finance;

use App\Lib\Locker\AccountLocker;
use App\Lib\Clog;
use App\Lib\Moon\AccountChange;
use App\Models\Admin\Province;
use App\Models\Base;
use App\Models\User;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Validator;

class RechargeLog extends Base
{


    protected $table = 'user_recharge_log';

    static function getList($c, $pageSize = 20) {
        $query = self::orderBy('id', 'desc');

        // 用ID
        if (isset($c['user_id']) && $c['user_id']) {
            $query->where('user_id', $c['user_id']);
        }

        // 用户名
        if (isset($c['username']) && $c['username']) {
            $query->where('username', $c['username']);
        }

        // 昵称
        if (isset($c['nickname']) && $c['nickname']) {
            $query->where('nickname', $c['nickname']);
        }

        // 上级
        if (isset($c['status']) && $c['status'] && $c['status'] != 'all') {
            if (is_array($c['status'])) {
                $query->whereIn('status', $c['status']);
            } else {
                $query->where('status', $c['status']);
            }

        }

        // 上级
        if (isset($c['order_id']) && $c['order_id']) {
            $query->where('order_id', trim($c['order_id']));
        }

        // 时间
        if (isset($c['start_time']) && $c['start_time']) {
            $query->where('init_time', ">=", strtotime($c['start_time']));
        }

        // 时间
        if (isset($c['end_time']) && $c['end_time']) {
            $query->where('init_time', "<=", strtotime($c['end_time']));
        }

        if (isset($c['pageSize']) && $c['pageSize'] && intval($c['pageSize']) == $c['pageSize']) {
            $pageSize = intval($c['pageSize']) > 100 ? 100 : intval($c['pageSize']);
        }

        $currentPage    = isset($c['pageIndex']) ? intval($c['pageIndex']) : 1;
        $offset         = ($currentPage - 1) * $pageSize;

        $total  = $query->count();
        $data  = $query->skip($offset)->take($pageSize)->get();

        return ['data' => $data, 'total' => $total, 'currentPage' => $currentPage, 'totalPage' => intval(ceil($total / $pageSize))];
    }

    // 保存
    static function initLog($user, $order, $params = "") {

        $model = new self();
        $model->ip                  = real_ip();
        $model->user_id             = $user->id;
        $model->username            = $user->username;
        $model->nickname            = $user->nickname;
        $model->order_id            = $order->id;
        $model->amount              = $order->amount;

        $model->request_time        = time();
        $model->request_params      = $params ? json_encode($params) : '';
        $model->request_back        = '';
        $model->content             = "";
        $model->save();
        return $model;
    }
}
