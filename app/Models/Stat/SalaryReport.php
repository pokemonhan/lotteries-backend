<?php

namespace App\Models\Stat;

use App\Models\Base;

class SalaryReport extends Base {
    protected $table = 'salary_report';


    /**
     * 获取列表
     * @param $c
     * @param int $pageSize
     * @return array
     */
    static function getList($c, $pageSize = 15) {
        $query = self::orderBy('id', 'desc');

        // 平台
        if(isset($c['p_sign'])) {
            $query->where('p_sign', $c['p_sign']);
        }

        // 用户名
        if(isset($c['username'])) {
            $query->where('username', $c['username']);
        }

        // 日期
        if(isset($c['parent_id'])) {
            $query->where('parent_id', $c['parent_id']);
        }

        // 日期 开始
        if(isset($c['start_day'])) {
            $query->where('day', ">=", $c['start_day']);
        }

        // 日期 结束
        if(isset($c['end_day'])) {
            $query->where('day', "<=", $c['end_day']);
        }

        $currentPage    = isset($c['pageIndex']) ? intval($c['pageIndex']) : 1;
        $offset         = ($currentPage - 1) * $pageSize;

        $total  = $query->count();
        $menus  = $query->skip($offset)->take($pageSize)->get();

        return ['data' => $menus, 'total' => $total, 'currentPage' => $currentPage, 'totalPage' => intval(ceil($total / $pageSize))];
    }
}
