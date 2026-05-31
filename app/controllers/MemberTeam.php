<?php
//custom_file(team_auth)
// +----------------------------------------------------------------------
// | 后台账号 子账号
// +----------------------------------------------------------------------
namespace app\controllers;
use think\facade\View;
use think\facade\Db;
use app\models\Member as m;
class MemberTeam extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
	}

    //会员团队列表
    public function index(){
        $mid = input('mid');
        if(!$mid){
            $mid = Db::name('member')->where('aid',aid)->order('id asc')->value('id');
            if(!$mid && !request()->isAjax()){
                return View::fetch();
            }
        }
        $date_start = 0;
        $date_end = 0;
        if(input('date_start') && input('date_end')){
            $date_start = strtotime(input('date_start'));
            $date_end = strtotime(input('date_end'));
        }
        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $date_start2 = strtotime($ctime[0]);
            $date_end2 = strtotime($ctime[1]) + 86399;
        }
        $userinfo = Db::name('member')->field('id,nickname,headimg,levelid')->where('aid',aid)->where('id',$mid)->find();
        //总业绩
        $yejiwhere = [];
        $yejiwhere[] = ['status','in','1,2,3'];
        if($date_start2 && $date_end2){
            $yejiwhere[] = ['createtime','between',[$date_start2,$date_end2]];
        }
        $downmids = \app\commons\Member::getteammids(aid,$userinfo['id']);
        //下级人数
        $userinfo['team_down_total'] = count($downmids);
        if($downmids){
            $userinfo['teamyeji'] = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
        }else{
            $userinfo['teamyeji'] = 0;
        }

        if ($mid && getcustom('member_level_salary_bonus')) {
            $userinfo['teamyeji_mini'] = \app\models\Commission::getMiniTeamCommission(aid,$mid,$date_start,$date_end);
        }
        $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$userinfo['levelid'])->find();

        if(request()->isAjax()){
            $admin_set = Db::name('admin_set')->where('aid',aid)->find();
            $downdeep = input('param.type/d');
            $pernum =  input('param.limit');;
            $pagenum = input('page');
            $keyword = input('keyword');
            $page_count = 0;
            $where2 = "1=1";
            if($keyword) $where2 = "(id like '%{$keyword}%' or nickname like '%{$keyword}%' or realname like '%{$keyword}%' or tel like '%{$keyword}%')";
            if($date_start && $date_end){
                $where_date = "createtime>=".$date_start." and createtime<=".$date_end;
                if($where2=='1=1'){
                    $where2 = $where_date;
                }else{
                    $where2 = $where2.' and '.$where_date;
                }
            }
            if(!$pagenum) $pagenum = 1;
            if(!$downdeep) $downdeep = 1;
            if(!$mid){
                $datalist = [];
            }else{
                if($downdeep == 1){
                    $datalist = Db::name('member')
                        ->field("id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid")
                        ->where("aid=".aid." and (pid=".$mid." or id=".$mid.")")
                        ->where($where2)->page($pagenum,$pernum)->order('id desc')->select()->toArray();

                    $page_count = Db::name('member')
                        ->field("id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid")
                        ->where("aid=".aid." and pid=".$mid."")
                        ->where($where2)->order('id desc')->count();
                }elseif($downdeep == 2){
                    $datalist = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and $where2 and pid in(select id from ".table_name('member')." where pid=".$mid.") order by id desc limit ".($pagenum*$pernum-$pernum).','.$pernum);
                    $page_count_data = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and $where2 and pid in(select id from ".table_name('member')." where pid=".$mid.") order by id desc ");
                    $page_count = count($page_count_data);
                }elseif($downdeep == 3){
                    $datalist = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where aid=".aid." and $where2 and pid in(select id from ".table_name('member')." where pid=".$mid.")) order by id desc limit ".($pagenum*$pernum-$pernum).','.$pernum);
                    $page_count_data = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where aid=".aid." and $where2 and pid in(select id from ".table_name('member')." where pid=".$mid.")) order by id desc ");
                    $page_count = count($page_count_data);
                }elseif($downdeep == 4){
                    //团队
                    $downmids =  \app\commons\Member::getdowntotalmids(aid,$mid,3);

                    if($downmids){
                        $downmids = implode(',',$downmids);
                        $datalist = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and $where2 and id in(".$downmids.") order by id desc limit ".($pagenum*$pernum-$pernum).','.$pernum);
                        $page_count_data = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and $where2 and id in(".$downmids.") order by id desc ");
                        $page_count = count($page_count_data);
                    }
                }
            }
            if(!$datalist) $datalist = [];
            foreach($datalist as $k=>$v){
                //if($downdeep==1){
                //	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent1',mid)->where('status',3)->sum('parent1commission');
                //}
                //if($downdeep==2){
                //	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent2',mid)->where('status',3)->sum('parent2commission');
                //}
                //if($downdeep==3){
                //	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent3',mid)->where('status',3)->sum('parent3commission');
                //}

                $commission_where = [];
                $commission_where[] = ['aid','=',aid];
                $commission_where[] = ['mid','=',$mid];
                $commission_where[] = ['frommid','=',$v['id']];
                $commission_where[] = ['status','=',1];
                if($date_start && $date_end){
                    $commission_where[] = ['createtime','between',[$date_start,$date_end]];
                }
                $commission = Db::name('member_commission_record')
                    ->where($commission_where)
                    ->sum('commission');

                $datalist[$k]['commission'] = 0 + dd_money_format($commission,2);
                $downcount_where = [];
                $downcount_where[] = ['aid','=',aid];
                $downcount_where[] = ['pid','=',$v['id']];
                if($date_start && $date_end){
                    $downcount_where[] = ['createtime','between',[$date_start,$date_end]];
                }
                $datalist[$k]['downcount'] = 0 + Db::name('member')->where($downcount_where)->count();

                $level = Db::name('member_level')->where('aid',aid)->where('id',$v['levelid'])->find();
                $datalist[$k]['levelname'] = $level['name'];
                $datalist[$k]['levelsort'] = $level['sort'];
                if($userlevel['team_showtel'] == 0){
                    //$datalist[$k]['tel'] = '';
                }

                //if($userlevel['team_yeji']){
                    //团队业绩
                    //总业绩
                    $yejiwhere = [];
                    $yejiwhere[] = ['status','in','1,2,3'];
                    if($date_start2 && $date_end2){
                        $yejiwhere[] = ['createtime','between',[$date_start2,$date_end2]];
                    }
                    $downmids = \app\commons\Member::getteammids(aid,$v['id']);
                    //下级人数
                    $team_down_total = count($downmids);
                    $datalist[$k]['team_down_total'] = $team_down_total;
                    $teamyeji = 0;
                    if($downmids){
                        $teamyeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
                    }

                    //个人业绩
                    $self_yeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where($yejiwhere)->sum('real_totalprice');
                    //业绩包含自身
                    if($admin_set['teamyeji_self']==1){
                        $teamyeji = bcadd($teamyeji,$self_yeji,2);
                    }
                    $datalist[$k]['teamyeji'] = $teamyeji;
                    $datalist[$k]['selfyeji'] = $self_yeji;
                //}

            }


            return json(['code'=>0,'msg'=>'查询成功','count'=>$page_count,'data'=>$datalist,'userinfo'=>$userinfo]);
        }
        //我的团队
        $team_where = [];
        $team_where[] = ['aid','=',aid];
        $team_where[] = ['pid','=',$mid];
        if($date_start && $date_end){
            $team_where[] = ['createtime','between',[$date_start,$date_end]];
        }
        $userinfo['myteamCount1'] = 0 + Db::name('member')->where($team_where)->count();
        $team_where = '1=1';
        if($date_start && $date_end){
            $team_where = 'createtime>='.$date_start.' and '.'createtime<='.$date_end;
        }
        $userinfo['myteamCount2'] = 0;
        $userinfo['myteamCount3'] = 0;
        if($userinfo['myteamCount1']>0){
            $myteamCount2 = Db::query("select count(1)c from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where pid=".$mid.") and ".$team_where);
            $userinfo['myteamCount2'] = 0 + $myteamCount2[0]['c'];
        }
        if($userinfo['myteamCount2']>0){
            $myteamCount3 = Db::query("select count(1)c from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where pid=".$mid.")) and ".$team_where);
            $userinfo['myteamCount3'] = 0 + $myteamCount3[0]['c'];
        }

        $userinfo['myteamCount'] = $userinfo['myteamCount1'] + $userinfo['myteamCount2'];
        //三级以后的团队人数
        $downmids =  \app\commons\Member::getdowntotalmids(aid,$mid,3);

        $userinfo['myteamCount4'] = count($downmids);

        View::assign('userinfo',$userinfo);
        View::assign('mid',$mid);
        return View::fetch();
    }
	//导出
	public function excel(){
        $mid = input('mid');
        if(!$mid){
            $mid = Db::name('member')->order('id asc')->value('id');
        }
        $date_start = 0;
        $date_end = 0;
        if(input('date_start') && input('date_end')){
            $date_start = strtotime(input('date_start'));
            $date_end = strtotime(input('date_end'));
        }
        if(input('param.ctime') ){
            $ctime = explode(' ~ ',input('param.ctime'));
            $date_start2 = strtotime($ctime[0]);
            $date_end2 = strtotime($ctime[1]) + 86399;
        }
        $userinfo = Db::name('member')->field('id,nickname,headimg,levelid')->where('aid',aid)->where('id',$mid)->find();
        $userlevel = Db::name('member_level')->where('aid',aid)->where('id',$userinfo['levelid'])->find();
        $pernum =  input('param.limit');;
        $pagenum = input('page');
        $admin_set = Db::name('admin_set')->where('aid',aid)->find();
        $downdeep = input('param.type/d');

        $keyword = input('keyword');
        $where2 = "1=1";
        if($keyword) $where2 = "(id like '%{$keyword}%' or nickname like '%{$keyword}%' or realname like '%{$keyword}%' or tel like '%{$keyword}%')";
        if($date_start && $date_end){
            $where_date = "createtime>=".$date_start." and createtime<=".$date_end;
            if($where2=='1=1'){
                $where2 = $where_date;
            }else{
                $where2 = $where2.' and '.$where_date;
            }
        }
        if(!$downdeep) $downdeep = 1;
        if(!$mid){
            $datalist = [];
        }else{
            if($downdeep == 1){
                $datalist = Db::name('member')
                    ->field("id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid")
                    ->where("aid=".aid." and (pid=".$mid." or id=".$mid.")")
                    ->where($where2)->page($pagenum,$pernum)->order('id desc')->select()->toArray();

                $page_count = Db::name('member')
                    ->field("id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid")
                    ->where("aid=".aid." and pid=".$mid."")
                    ->where($where2)->order('id desc')->count();
            }elseif($downdeep == 2){
                $datalist = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and $where2 and pid in(select id from ".table_name('member')." where pid=".$mid.") order by id desc limit ".($pagenum*$pernum-$pernum).','.$pernum);
                $page_count_data = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and $where2 and pid in(select id from ".table_name('member')." where pid=".$mid.") order by id desc ");
                $page_count = count($page_count_data);
            }elseif($downdeep == 3){
                $datalist = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where aid=".aid." and $where2 and pid in(select id from ".table_name('member')." where pid=".$mid.")) order by id desc limit ".($pagenum*$pernum-$pernum).','.$pernum);
                $page_count_data = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and pid in(select id from ".table_name('member')." where aid=".aid." and $where2 and pid in(select id from ".table_name('member')." where pid=".$mid.")) order by id desc ");
                $page_count = count($page_count_data);
            }elseif($downdeep == 4){
                //团队
                $downmids =  \app\common\Member::getdowntotalmids(aid,$mid,3);

                if($downmids){
                    $downmids = implode(',',$downmids);
                    $datalist = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and $where2 and id in(".$downmids.") order by id desc limit ".($pagenum*$pernum-$pernum).','.$pernum);
                    $page_count_data = Db::query("select id,nickname,headimg,tel,pid,score,totalcommission,from_unixtime(createtime)createtime,levelid from ".table_name('member')." where aid=".aid." and $where2 and id in(".$downmids.") order by id desc ");
                    $page_count = count($page_count_data);
                }
            }
        }
        if(!$datalist) $datalist = [];
        foreach($datalist as $k=>$v){
            //if($downdeep==1){
            //	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent1',mid)->where('status',3)->sum('parent1commission');
            //}
            //if($downdeep==2){
            //	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent2',mid)->where('status',3)->sum('parent2commission');
            //}
            //if($downdeep==3){
            //	$commission = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where('parent3',mid)->where('status',3)->sum('parent3commission');
            //}

            $commission_where = [];
            $commission_where[] = ['aid','=',aid];
            $commission_where[] = ['mid','=',$mid];
            $commission_where[] = ['frommid','=',$v['id']];
            $commission_where[] = ['status','=',1];
            if($date_start && $date_end){
                $commission_where[] = ['createtime','between',[$date_start,$date_end]];
            }
            $commission = Db::name('member_commission_record')
                ->where($commission_where)
                ->sum('commission');

            $datalist[$k]['commission'] = 0 + dd_money_format($commission,2);
            $downcount_where = [];
            $downcount_where[] = ['aid','=',aid];
            $downcount_where[] = ['pid','=',$v['id']];
            if($date_start && $date_end){
                $downcount_where[] = ['createtime','between',[$date_start,$date_end]];
            }
            $datalist[$k]['downcount'] = 0 + Db::name('member')->where($downcount_where)->count();

            $level = Db::name('member_level')->where('aid',aid)->where('id',$v['levelid'])->find();
            $datalist[$k]['levelname'] = $level['name'];
            $datalist[$k]['levelsort'] = $level['sort'];

           // if($userlevel['team_yeji']){
                //团队业绩
                //总业绩
                $yejiwhere = [];
                $yejiwhere[] = ['status','in','1,2,3'];
                if($date_start2 && $date_end2){
                    $yejiwhere[] = ['createtime','between',[$date_start2,$date_end2]];
                }
                $downmids = \app\common\Member::getteammids(aid,$v['id']);
                //下级人数
                $team_down_total = count($downmids);
                $datalist[$k]['team_down_total'] = $team_down_total;

                $teamyeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid','in',$downmids)->where($yejiwhere)->sum('real_totalprice');
                //个人业绩
                $self_yeji = Db::name('shop_order_goods')->where('aid',aid)->where('mid',$v['id'])->where($yejiwhere)->sum('real_totalprice');
                //业绩包含自身
                if($admin_set['teamyeji_self']==1){
                    $teamyeji = bcadd($teamyeji,$self_yeji,2);
                }
                $datalist[$k]['teamyeji'] = $teamyeji;
                $datalist[$k]['selfyeji'] = $self_yeji;
           // }
        }
        $title = ['会员ID','会员昵称','会员头像','注册时间','等级','手机号','推荐人ID','团队业绩','个人业绩','下级人数','积分','产生佣金'];
        $data = [];
        foreach($datalist as $v){
            $data[] = [
                $v['id'],
                $v['nickname'],
                $v['headimg'],
                $v['createtime'],
                $v['levelname'],
                $v['tel'],
                $v['pid'],
                $v['teamyeji'],
                $v['selfyeji'],
                $v['team_down_total'],
                $v['score'],
                $v['commission']
            ];
        }

        return json(['code'=>0,'msg'=>'查询成功','count'=>$page_count,'data'=>$data,'title'=>$title]);
		$this->export_excel($title,$data);
	}
}
