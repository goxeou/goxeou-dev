<?php
// +----------------------------------------------------------------------
// | 营销-团队管理奖 custom_file(yx_team_yeji_manage)
// +----------------------------------------------------------------------
namespace app\controller\yingxiao;
use app\controller\Common;
use think\facade\View;
use think\facade\Db;

class TeamYejiManage extends Common
{
    public function initialize(){
		parent::initialize();
		if(bid > 0) showmsg('无访问权限');
        $this->defaultSet();
	}
	//列表
    public function index(){
        if(request()->isAjax()){
            $page = input('param.page');
            $limit = input('param.limit');
            if(input('param.field') && input('param.order')){
                $order = 'team_yeji_manage.'.input('param.field').' '.input('param.order');
            }else{
                $order = 'team_yeji_manage.id desc';
            }
            $where = [];
            $where[] = ['team_yeji_manage.aid','=',aid];
            if(input('param.ctime') ){
                $ctime = explode(' ~ ',input('param.ctime'));
                $where[] = ['team_yeji_manage.createtime','>=',strtotime($ctime[0])];
                $where[] = ['team_yeji_manage.createtime','<',strtotime($ctime[1]) + 86400];
            }

            if(input('param.nickname')) $where[] = ['member.nickname','like','%'.trim(input('param.nickname')).'%'];
            if(input('param.mid')) $where[] = ['team_yeji_manage.mid','=',trim(input('param.mid'))];
            $count = 0 + Db::name('team_yeji_manage')->alias('team_yeji_manage')->field('member.nickname,member.headimg,team_yeji_manage.*')->join('member member','member.id=team_yeji_manage.mid')->where($where)->count();
            $data = Db::name('team_yeji_manage')->alias('team_yeji_manage')->field('member.nickname,member.headimg,team_yeji_manage.*')->join('member member','member.id=team_yeji_manage.mid')->where($where)->page($page,$limit)->order($order)->select()->toArray();
            return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
        }
        return View::fetch();
    }
	public function set(){
		if(request()->isAjax()){
//            dd(input('post.'));
			$info = input('post.info/a');
            if($info['gettj_children'])$info['gettj_children'] = implode(',',$info['gettj_children']);
			$configdata = array();
			$postlevelid = input('post.levelid/a');
            $postconfig_data = input('post.config_data/a');
            if(array_unique($postlevelid) != $postlevelid) return json(['status'=>0,'msg'=>'存在重复的等级，请修正数据']);
			foreach($postlevelid as $k=>$levelid){
                if($postconfig_data['levelNum'][$k] < 1 || empty($postconfig_data['levelNum'][$k])){
                    return json(['status'=>0,'msg'=>'级数最小值为1，请修正数据']);
                }
                if($postconfig_data['teamNum'][$k] < 1 || empty($postconfig_data['teamNum'][$k])){
                    return json(['status'=>0,'msg'=>'团队数量最小值为1，请修正数据']);
                }
                if($postconfig_data['commission'][$k] < 0 || $postconfig_data['yeji'][$k] < 0){
                    return json(['status'=>0,'msg'=>'数据无效，请修正数据']);
                }
                $configdata[$levelid] = array(
					'levelid'=>$levelid,
					'score'=>$postconfig_data['score'][$k],
                    'scoreMax'=>$postconfig_data['scoreMax'][$k],
                    'commission'=>$postconfig_data['commission'][$k],
                    'commissionMax'=>$postconfig_data['commissionMax'][$k],
                    'levelNum'=>$postconfig_data['levelNum'][$k],
                    'yeji'=>$postconfig_data['yeji'][$k],
                    'teamNum'=>$postconfig_data['teamNum'][$k],
				);
			}
			$info['config_data'] = json_encode($configdata,JSON_UNESCAPED_UNICODE);
            Db::name('team_yeji_manage_set')->where('aid',aid)->update($info);
            \app\common\System::plog('团队管理奖设置');
			return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
		}
		$info = Db::name('team_yeji_manage_set')->where('aid',aid)->find();
		if(!$info) $info = ['status'=>0];
        $info['config_data'] = json_decode($info['config_data'],true);
		View::assign('info',$info);

        $defaultCat = Db::name('member_level_category')->where('aid',aid)->where('isdefault', 1)->value('id');
        $defaultCat = $defaultCat ? $defaultCat : 0;
        $memberlevel = Db::name('member_level')->where('aid',aid)->where('cid', $defaultCat)->order('sort,id')->select()->toArray();
        View::assign('memberlevel',$memberlevel);
		return View::fetch();
	}

    public function defaultSet(){
        $set = Db::name('team_yeji_manage_set')->where('aid',aid)->find();
        if(!$set){
            Db::name('team_yeji_manage_set')->insert(['aid'=>aid,'status'=>0,'createtime'=>time(),'gettj_children'=>-1]);
        }
    }


}
