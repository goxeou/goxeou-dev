<?php




namespace app\commons\job;

use think\facade\Db;
use think\queue\job;
use think\facade\Log;

class SmsTencent
{
    public $delay = 3;
    public function fire(Job $job, $data){

        if ($job->attempts() > 3) {
            //通过这个方法可以检查这个任务已经重试了几次了
            $this->failed($job, $data);
            $job->delete();
        }

        //....这里执行具体的任务
        Log::write("tencentsms");
        Log::write($data);
        $rs = \app\commons\Sms::tencentsms($data['aid'],$data['smsset']['accesskey'],$data['smsset']['accesssecret'],$data['smsset']['sdkappid'],$data['smsset']['sign_name'],$data['templateCode'],$data['tel'],$data['params']);
        if($rs['status'] == 1) {
            //如果任务执行成功后 记得删除任务，不然这个任务会重复执行，直到达到最大重试次数后失败后，执行failed方法
            $job->delete();
            return ;
        }

        // 也可以重新发布这个任务
//        $job->release($this->delay); //$delay为延迟时间

    }

    public function failed($job, $data){
        // ...任务达到最大重试次数后，失败了
        $jobInfo = Db::name('jobs')->where('id', $job->getJobId())->find();
        if($jobInfo)
            Db::name('jobs_failed')->insert($jobInfo);
        Log::write("dismiss job has been retried more that 3 times");
        Log::write($data);
    }
}