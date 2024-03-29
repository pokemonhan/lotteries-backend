<?php


// 标记符做替换用
$str = "##-----------------------------------------[{$this->startMark}]-----------------------------------------## \n";
$str.="######----------------------------------------- 守护 进程 -----------------------------------------###### \n";

//foreach($daemons as $name=>$command){
//    if(!isset($command['logfile'])) $command['logfile']='/dev/null';
//    $str.=gen_cron($command);
//}

$str.="######----------------------------------------- 一般 进程  -----------------------------------------###### \n";
foreach($commands as $name=>$command){
    if(!isset($command['logfile'])) $command['logfile']='/dev/null';
    if(strpos($command['command'],'{lotterys}')!==false){
        foreach($lotterys as $lottery){
            $comm = $command;
            $comm['command']=str_replace('{lotterys}',$lottery->id,$comm['command']);
            $str .= $this->genCron($comm);
        }
    }else{
        $str .= $this->genCron($command);
    }
}

$str.="##-----------------------------------------[{$this->endMark}]-----------------------------------------## \n";

return $str;