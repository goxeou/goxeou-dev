<?php



namespace app\models;

use think\Model;
use think\facade\Db;

class Commission extends Model
{
    //小市场业绩，去除业绩最高的一条线的业绩，其他的算小市场业绩
    public static function getMiniTeamCommission($aid,$mid,$starttime='',$endtime='')
    {
        if (getcustom('member_level_salary_bonus',$aid)) {
            //直推会员
            $ztmembers = Db::name('member')->where('aid', $aid)->where('pid', $mid)->field('id,levelid,pid')->select()->toArray();
            //去除最高业绩的小部门业绩
            $maxYeji = 0;
            $totalYeji = 0;
            writeLog('-----'.$mid.'-----');
            foreach ($ztmembers as $ztk => $ztmember) {
                //直推部门业绩
                $yejiwhere = [];
                $yejiwhere[] = ['status', '=', 3];
                if($starttime || $endtime){
                    if($starttime){
                        $yejiwhere[] = ['collect_time', '>', $starttime];
                    }
                    if($endtime){
                        $yejiwhere[] = ['collect_time', '<', $endtime];
                    }
                }else{
                    $yejiwhere[] = ['collect_time', '<', time()];
                }
                $downmids = \app\commons\Member::getteammids($aid, $ztmember['id']);
                $downmids[] = $ztmember['id'];
                if (empty($downmids)) {
                    continue;
                }
                $sumResult = Db::name('shop_order')->where('aid', $aid)->where('mid', 'in', $downmids)->where($yejiwhere)->field("sum(`totalprice`-`refund_money`) as totalamount")->find();
//                    dump(['amount'=>$sumResult['totalamount'],'mid'=>$ztmember['id']]);
                $teamYeji = $sumResult['totalamount'] ?round($sumResult['totalamount'],2): 0;
                writeLog('mid='.$ztmember['id'].'&amount='.$teamYeji);
                if ($teamYeji > $maxYeji) {
                    $maxYeji = $teamYeji;
                }
                $totalYeji = $totalYeji + $teamYeji;
            }
            $yejiAmount = round($totalYeji - $maxYeji,2);//去掉最大部门业绩算小部门业绩
            writeLog('-----'.$mid.'-----');
            return $yejiAmount;
        }
        return 0;
    }
}