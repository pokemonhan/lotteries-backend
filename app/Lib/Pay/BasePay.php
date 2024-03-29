<?php

namespace App\Lib\Pay;
use App\Models\Finance\RechargeLog;
use App\Models\Finance\Withdraw;
use App\Models\Finance\WithdrawLog;

class BasePay
{
    // 充值参数
    public $rechargeCallbackParams  = [];
    public $rechargeParams          = [];
    public $rechargeOrder           = null;
    public $rechargeUser            = null;
    public $rechargeLog             = null;

    // 提现参数
    public $withdrawQueryParams  = [];
    public $withdrawParams          = [];
    public $withdrawOrder           = null;
    public $withdrawUser            = null;
    public $withdrawLog             = null;

    /**
     * 保存日志
     */
    public function initRechargeLog() {
        $order  = $this->rechargeOrder;
        $user   = $this->rechargeUser;
        $params = $this->rechargeParams ;

        $log = RechargeLog::initLog($user,  $order, $params);
        $this->rechargeLog = $log;
    }

    /**
     * 更新充值日志
     * @param $data
     */
    public function updateRechargeLog($data) {
        RechargeLog::where("id", $this->rechargeLog->id)->update($data);
    }

    /**
     * 更新充值日志-回调
     * @param $data
     */
    public function updateCallbackLog($data) {
        $orderId = $this->rechargeOrder->id;
        RechargeLog::where("order_id", $orderId)->update($data);
    }

    /**
     * 设置充值订单
     * @param $order
     */
    public function setRechargeOrder($order) {
        $this->rechargeOrder = $order;
    }

    /**
     * 设置充值用户
     * @param $user
     */
    public function setRechargeUser($user) {
        $this->rechargeUser = $user;
    }

    /**
     * 设置充值参数
     * @param $params
     */
    public function setRechargeParams($params) {
        $this->rechargeParams = $params;
    }

    /**
     * 设置充值参数-回调
     * @param $params
     */
    public function setRechargeCallbackParams($params) {
        $this->rechargeCallbackParams = $params;
    }

    /** ========================= 提现日志 ====================== */

    /**
     * 保存日志
     */
    public function initWithdrawLog() {
        $order  = $this->withdrawOrder;
        $user   = $this->withdrawUser;
        $params = $this->withdrawParams ;

        $log = WithdrawLog::initLog($user,  $order, $params);
        $this->withdrawLog = $log;
    }

    /**
     * 更新充值日志
     * @param $data
     */
    public function updateWithdrawLog($data) {
        WithdrawLog::where("id", $this->withdrawLog->id)->update($data);
    }

    /**
     * 更新代发日志-查询
     * @param $data
     */
    public function updateWithdrawQueryLog($data) {
        $orderId = $this->withdrawOrder->id;
        WithdrawLog::where("order_id", $orderId)->update($data);
    }

    /**
     * 设置提现订单
     * @param $order
     */
    public function setWithdrawOrder($order) {
        $this->withdrawOrder = $order;
    }

    /**
     * 设置提现用户
     * @param $user
     */
    public function setWithdrawUser($user) {
        $this->withdrawUser = $user;
    }

    /**
     * 设置提现参数
     * @param $params
     */
    public function setWithdrawParams($params) {
        $this->withdrawParams = $params;
    }

    /**
     * 设置提现参数-回调
     * @param $params
     */
    public function setWithdrawQueryParams($params) {
        $this->withdrawQueryParams = $params;
    }
}