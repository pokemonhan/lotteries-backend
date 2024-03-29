<?php

namespace App\Models\Finance;

use App\Lib\Locker\AccountLocker;
use App\Lib\Clog;
use App\Lib\Moon\AccountChange;
use App\Lib\Pay\Pay;
use App\Models\Admin\Province;
use App\Models\Base;
use App\Models\Player\Player;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class Withdraw extends Base
{
    public $rules = [
        'owner_name'        => 'required|min:2|max:128',
        'card_number'       => 'required|integer',
        'province'          => 'required|integer',
        'city'              => 'required|integer',
        'branch'            => 'required|min:4|max:128',
    ];

    protected $table = 'user_withdraw';

    const STATUS_SEND_SUCCESS       = 3;
    const STATUS_SEND_FAIL          = -3;

    const STATUS_CALLBACK_SUCCESS   = 4;
    const STATUS_CALLBACK_FAIL      = -4;

    const STATUS_HAND_SUCCESS       = 5;
    const STATUS_HAND_FAIL          = -5;

    static $status = [
        0   => '初始化',
        1   => '已领取',
        2   => '审核成功',
        3   => '代发成功',
        4   => '回调成功',
        5   => '人工成功',
        -2  => '审核失败',
        -3  => '代发失败',
        -4  => '回调失败',
        -5  => '人工失败',
    ];

    static function getList($c, $pageSize = 20) {
        $query = self::select(
            DB::raw('user_withdraw.*'),
            DB::raw('user_bank_cards.card_number')
        )->leftJoin('user_bank_cards', 'user_withdraw.card_id', '=', 'user_bank_cards.id')->orderBy('user_withdraw.id', 'desc');

        // 用户名
        if (isset($c['username']) && $c['username']) {
            $query->where('user_withdraw.username', $c['username']);
        }

        // 状态
        if (isset($c['status']) && $c['status']) {
            $query->whereIn('user_withdraw.status', $c['status']);
        }

        // 昵称
        if (isset($c['nickname']) && $c['nickname']) {
            $query->where('nickname', $c['nickname']);
        }

        // uid
        if (isset($c['user_id']) && $c['user_id']) {
            $query->where('user_withdraw.user_id', $c['user_id']);
        }

        // 订单号
        if (isset($c['order_id']) && $c['order_id']) {
            $query->where('user_withdraw.order_id', $c['order_id']);
        }

        // 时间
        if (isset($c['start_time']) && $c['start_time']) {
            $query->where('user_withdraw.request_time', ">=", strtotime($c['start_time']));
        }

        // 时间
        if (isset($c['end_time']) && $c['end_time']) {
            $query->where('user_withdraw.request_time', "<=", strtotime($c['end_time']));
        }

        if (isset($c['pageSize']) && $c['pageSize'] && intval($c['pageSize']) == $c['pageSize']) {
            $pageSize = intval($c['pageSize']) > 100 ? 100 : intval($c['pageSize']);
        }

        $currentPage    = isset($c['pageIndex']) ? intval($c['pageIndex']) : 1;
        $offset         = ($currentPage - 1) * $pageSize;

        $total  = $query->count();
        $menus  = $query->skip($offset)->take($pageSize)->get();

        return ['data' => $menus, 'total' => $total, 'currentPage' => $currentPage, 'totalPage' => intval(ceil($total / $pageSize))];
    }

    // 保存
    public function saveItem($adminId = 0) {
        $data       = \Request::all();
        $validator  = Validator::make($data, $this->rules);

        if ($validator->fails()) {
            return $validator->errors()->first();
        }

        $user = User::where('username', $data['username'])->first();
        if (!$user->id) {
            return "无效的用户!";
        }

        // 卡号
        if (strlen($data['card_number']) < 15 || strlen($data['card_number']) > 19) {
            return "银行卡号只能是15位和19位之间!";
        }

        // 银行
        $banks = config("web.banks");
        if (!isset($data['bank_sign']) || !isset($banks[$data['bank_sign']])) {
            return "无效的开户行!";
        }

        // 省份
        $provinceList = Province::getProvince();
        if (!isset($data['province']) || !isset($provinceList[$data['province']])) {
            return "无效的省份!";
        }

        // 市区
        $cityList = $provinceList[$data['province']]['city'];
        if (!isset($data['city']) || !isset($cityList[$data['city']])) {
            return "无效的市!";
        }

        $this->username             = $data['username'];
        $this->nickname             = $data['nickname'];
        $this->user_id              = $user->id;
        $this->bank_sign            = $data['bank_sign'];
        $this->card_number          = $data['card_number'];
        $this->branch               = $data['branch'];
        $this->owner_name           = $data['owner_name'];
        $this->province             = $provinceList[$data['province']]['name'];
        $this->city                 = $cityList[$data['city']];
        $this->admin_id             = $adminId;
        $this->save();
        return true;
    }

    /**
     * 是否提现时间
     * @return bool
     */
    static function isDrawTime() {
        $drawTimeRange = configure('finance_withdraw_time_range', "00:00:00-02:00:00|09:30:00-24:00:00");
        $range = explode('|', $drawTimeRange);

        $nowSeconds = time();
        $nowDay     = date('Y-m-d ');
        foreach ($range as $r) {
            $r_time = explode('-', $r);

            if ($nowSeconds >= strtotime($nowDay . $r_time[0]) && $nowSeconds <= strtotime($nowDay . $r_time[1])) {
                return true;
            }
        }
        return false;
    }

    /**
     * 保存审核记录 == 审核写入
     * @param $user
     * @param $amount
     * @param $card
     * @param string $source
     * @return WithdrawCheck|bool
     */
    static function request($user, $amount, $card, $source = "iphone") {

        $locker = new AccountLocker($user->id);
        if (!$locker->getLock()) {
            return "对不起,　获取用户锁失败!";
        }

        $account = $user->account();
        // 余额不够
        if($account->balance < $amount * 10000){
            return "对不起,　余额不足!";
        }

        db()->beginTransaction();
        try {

            // 提现冻结
            $accountChange = new AccountChange();
            $ret = $accountChange->change($account, 'withdraw_frozen', array(
                'user_id'   => $user->id,
                'amount'    => $amount * 10000,
            ));

            if ($ret !== true) {
                $locker->release();
                db()->rollback();
                return $ret;
            }

            $request = new Withdraw();
            $request->user_id           = $user->id;
            $request->top_id            = $user->top_id;
            $request->parent_id         = $user->parent_id;
            $request->rid               = $user->rid;
            $request->username          = $user->username;
            $request->nickname          = $user->nickname;
            $request->card_id           = $card['id'];
            $request->bank_sign         = $card['bank_sign'];
            $request->amount            = $amount * 10000;
            $request->desc              = "";

            $request->source            = $source;
            $request->client_ip         = real_ip();
            $ret = $request->save();

            if(!$ret){
                $locker->release();
                db()->rollback();
                return "对不起,　保存记录失败!";
            }

            $rechargeOrderPlus = configure("finance_withdraw_order_plus", 10000000);

            $request->order_id = "HB" . ($request->id + $rechargeOrderPlus);
            $ret    = $request->save();

            if(!$ret) {
                $locker->release();
                db()->rollback();
                return false;
            }
            db()->commit();

        } catch (\Exception $e) {
            db()->rollback();
            Clog::withdrawLog("提现:请求:" . $e->getMessage() . "|" . $e->getFile() . "|" . $e->getLine());
            return "对不起, " . $e->getMessage();
        }

        $pay    = Pay::getHandle('panda');

        $pay->setWithdrawOrder($request);
        $pay->setWithdrawUser($user);

        $r      = $pay->withdrawal($card['bank_sign'], $request->order_id, $amount, $card['card_number'], $card['owner_name']);
        if($r['status']){
            $request->request_time = time();
            $request->status = self::STATUS_SEND_SUCCESS;
            $request->save();
        } else {
            $request->request_time = time();
            $request->status = self::STATUS_SEND_FAIL;
            $request->save();
        }

        $locker->release();
        return true;
    }

    public function process($amount, $adminId = 0, $reason = "") {
        $user   = Player::find($this->user_id);

        if ($amount != $this->amount) {
            return "对不起, 金额不匹配!";
        }

        $locker = new AccountLocker($user->id);
        if(!$locker->getLock()){
            Clog::withdrawLog("对不起, 获取用户锁失败!!:");
            return "对不起, 获取用户锁失败!";
        }

        $account    = $user->account();

        db()->beginTransaction();
        try {
            $params = [
                'user_id'       => $user->id,
                'amount'        => $this->amount,
                'desc'          => "提现成功"
            ];

            $accountChange = new AccountChange();
            $res = $accountChange->change($account, 'withdraw_finish',  $params);

            if ($res !== true) {
                $locker->release();
                db()->rollback();
                Clog::withdrawLog("对不起, 提现回调帐变失败!!:", [$res]);
                return "对不起, 提现成功帐变失败!";
            }

            $this->real_amount      = $this->amount;
            $this->process_time     = time();
            $this->admin_id         = $adminId;
            $this->desc             = $reason . "-" . $this->status;

            $this->status = Withdraw::STATUS_HAND_SUCCESS;
            $this->save();

            db()->commit();
        } catch (\Exception $e) {
            db()->rollback();
            $locker->release();
            Clog::withdrawLog("提现成功异常:" . $e->getMessage() . "-" . $e->getLine());
        }

        return true;
    }

    static function getStatusDesc($status) {
        switch ($status) {
            case 0:
                return "<span>初始化</span>";
                break;
            case 1:
                return "<span>审核领取</span>";
                break;
            case 2:
                return "<span>审核成功</span>";
                break;
            case 3:
                return "<span>代发成功</span>";
                break;
            case 4:
                return "<span style='color: green;'>提现成功</span>";
                break;
            case -2:
                return "<span style='color: red;'>审核失败</span>";
                break;
            case -3:
                return "<span style='color: red;'>代发失败</span>";
                break;
            case -4:
                return "<span style='color: red;'>回调失败</span>";
                break;
            default:
                return "未知状态";
        }
    }
}
