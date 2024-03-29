<?php namespace App\Console\Commands\Lottery;

use App\Console\Commands\Command;
use App\Lib\Clog;
use App\Models\Game\Issue;
use App\Models\Game\Lottery;

// 生成奖期
class CronGenIssue extends Command {

    protected $signature    = 'lottery:genIssue  {lottery_id}';
    protected $description  = "lottery:genIssue 生成奖期!!";

    public function handle()
    {
        $lotteryId  = $this->argument('lottery_id');

        $allLottery = [];
        if ($lotteryId == 'all') {
            $allData = Lottery::getAllLotteries();
            foreach ($allData as $data) {
                $allLottery[] = $data->en_name;
            }

        } else {
            $allLottery[] = $lotteryId;
        }

        foreach ($allLottery as $lotteryId) {
            $lottery = Lottery::getLottery($lotteryId);
            if (!$lottery) {
                Clog::issueGen("无效的彩种-" . $lotteryId);
                continue;
            }

            // 六合彩去除
            if ($lottery->issue_type == "random") {
                continue;
            }

            // 获取上一期
            $lastIssue = Issue::where("lottery_id", $lotteryId)->orderBy("id", "desc")->first();
            if (!$lastIssue) {
                $startTime  = time();
            } else {
                $startTime  = strtotime($lastIssue->day) + 86400;
            }

            $startDay   = date("Ymd", $startTime);
            $endDay     = date("Ymd", time() + 86400 * 5);

            // 时间
            if (strtotime($startDay) > strtotime($endDay)) {
                Clog::issueGen("生成奖期-{$lotteryId}-开始日期大于结束日期!");
                continue;
            }

            $this->info("生成奖期-{$lotteryId}-start:" . $startDay . "-end:" . $endDay );

            $res = $lottery->genIssue($startDay, $endDay);
            if (!is_array($res)) {
                Clog::issueGen("生成奖期-{$lotteryId}-error:" . $res);
            }
        }

        $this->info("生成奖期-结束!");

        return true;
    }

}
