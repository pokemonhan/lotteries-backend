<?php namespace App\Console\Commands\Tool;

use App\Console\Commands\Command;

use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Process\Process;

class Crontab extends Command {

    protected $signature    = 'tool:crontab {action}';
    protected $description  = 'gen 生成crontab文件,stop:停止,start:启动, restart:重启';

    public $tpl     = "";
    public $path    = "";
    public $file    = "";

    public $startMark   = "";
    public $endMark     = "";

    public function __construct() {
        parent::__construct();
        $this->tpl    = __DIR__ . '/tpl/crontab.tpl.php';
        $this->path   = __DIR__ . '/../Runtime/';
        $this->file   = $this->path . 'crontab.conf';

        $md5 = md5(config('app.name'));
        $this->startMark =   'start_mark_' . $md5;
        $this->endMark   =   'end_mark_' . $md5;
    }

    public function handle() {

        $action = $this->argument('action');

        if($action=='stop' || $action=='end'){
            if ($this->confirm('Do you wish to clear all crontab from this? [yes|no]'))
            {
                $this->stop();
            }
        }elseif($action=='gen'){
            if ($this->confirm('Do you wish to gen crontab from this? [yes|no]'))
            {
                $this->gen();
            }
        }elseif($action=='run' || $action=='start'){
            if ($this->confirm('Do you wish to refresh all crontab from this? [yes|no]'))
            {
                $this->gen();
                $this->start();
            }
        }elseif($action=='restart'){
            if ($this->confirm('Do you wish to refresh all crontab from this? [yes|no]'))
            {
                $this->stop();
                $this->gen();
                $this->start();
            }
        }

        $this->info($action . " end!");
	}


    /**
     * 生成 crontab 文件
     */
    public function gen() {
        $this->info("gen file ......");

        if(!file_exists($this->tpl)){
            $this->error('crontab template file not exists or need gen!');
            return ;
        }

        $commands = config("command.crontab");
        if(empty($commands)){
            $this->error('no command need to gen!');
        }


        $phpbin     = config("command.phpbin");
        $basepath   = config("command.basepath");
        $cron_path  = config("command.cron_path");

        $out = include($this->tpl);

        // 做crontab替换
        $out = $this->renewCrontabContent($out);
        file_put_contents($this->file, $out);
    }

    /**
     * 应用 crontab 配置
     */
    public function start() {
        $this->info("starting ......");

        if(!file_exists($this->file)){
            $this->error('crontab file not exists or need gen!');
            return ;
        }

        $crontab = 'crontab';

        $process = new Process("{$crontab} {$this->file} ");
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->info($buffer);
            }
        });
    }

    /**
     * 清除crontab配置使用
     */
    public function stop() {
        $this->info("stoping ......");

        // 做crontba替换 清空
        $out = $this->renewCrontabContent('');
        file_put_contents($this->file, $out);

        $crontab = 'crontab';

        $process = new Process("{$crontab} {$this->file} ");
        $process->run(function ($type, $buffer) {
            if (Process::ERR === $type) {
                $this->error($buffer);
            } else {
                $this->info($buffer);
            }
        });

    }

    // 只替换startMark 和 endMark中间的脚本
    public function renewCrontabContent($crontab) {
        // 查看当前的crontab内容
        $lists   = [];
        $process = new Process("crontab -l");
        $process->run();
        $output = $process->getOutput();
        $lists  = explode("\n", $output);

        //检测开始和结束
        $start  = null;
        $end    = null;
        foreach($lists as $index => $line){
            if(strpos($line, $this->startMark) !== false){
                $start=$index;
            }
            if(strpos($line, $this->endMark) !== false){
                $end=$index;
            }
        }

        if($start !== null && $end !== null && $end > $start ) {
            // 其他的crontab
            $prefix_exts = $suffix_exts = [];
            foreach($lists as $index => $line){
                if($index < $start) {
                    $prefix_exts[] = $line;
                }elseif($index > $end) {
                    $suffix_exts[] = $line;
                }
            }
            $new = array_merge($prefix_exts, [$crontab], $suffix_exts);
            return implode("\n",$new);
        } else {
            // 附加内容到 原有内容后
            $new = array_merge($lists, [$crontab]);
            return implode("\n",$new);
        }
    }

    public function genCron($command){
        $str= <<<EOF
###----[{$command['name']}]----###
{$command['cron']}  {$command['command']} >> {$command['logfile']}  2>&1 &

EOF;
        return $str;
    }
}
