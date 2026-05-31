<?php

namespace app\controllers;
use think\facade\View;
use think\facade\Db;

class MemberTeamLevel extends Common
{
	//
    public function index(){
		if(request()->isAjax()){
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
			$order = 'sort,id';
			}
			$where = [];
			$where[] = ['aid','=',aid];
			$data = [];
			$data = Db::name('member_team_level')->where('aid',aid)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
             	$tj = array();
             	if($v['up_self_levelid']) $tj['up_self_levelid']='自身会员等级达['.Db::name('member_level')->where('id',$v['up_self_levelid'])->value('name').']';
             	if($v['up_self_teamid']) $tj['up_self_teamid']='自身团队等级达['.Db::name('member_team_level')->where('id',$v['up_self_teamid'])->value('name').']';
				if($v['up_fxdowncount'] > 0) $tj['up_fxdowncount']='下级总人数满'.$v['up_fxdowncount'].'人';
				if($v['up_fxdowncount2'] > 0) $tj['up_fxdowncount2']='下级总人数满'.$v['up_fxdowncount2'].'个['. Db::name('member_team_level')->where('id',$v['up_fxdownlevelid2'])->value('name').']';
				if($v['up_fxordermoney'] > 0) $tj['up_fxordermoney']='下级总订单金额满'.$v['up_fxordermoney'].'元';
				if($v['up_fxordermoney2'] > 0) $tj['up_fxordermoney2']='下级总订单金额满'.$v['up_fxordermoney2'].'元';
				
				
				if($v['up_proid'] > 0 && $v['up_pronum'] > 0) $tj['up_proid']='购买商品['.Db::name('shop_product')->where('id',$v['up_proid'])->value('name').']*'.$v['up_pronum'];
				
				if($v['pronum'] > 0 ) $tj['pronum']='团队报单*'.$v['pronum'];

				
				if($tj){
				    $i = 0;
                    $data[$k]['uptj'] = '';
				    foreach($tj as $key => $item) {
				        if($i >= 1) {
                            $data[$k]['uptj'] .= ' 且 '.$item;
                        }else {
                            $data[$k]['uptj'] .= $item;
                        }
                        $i++;
                    }
				}
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>count($data),'data'=>$data]);
		}
		return View::fetch();
    }
	//编辑
	public function edit(){
		if(input('param.id')){
			$info = Db::name('member_team_level')->where('aid',aid)->where('id',input('param.id/d'))->find();
		}else{
			$info = array('id'=>'','rate1'=>0);
		}
	
		View::assign('info',$info);
		return View::fetch();
	}
	//保存
	public function save(){
		$info = input('post.info/a');
		$info['areafenhong'] = implode(',',$info['areafenhong']);
		if($info['id']){
		    
			Db::name('member_team_level')->where('aid',aid)->where('id',$info['id'])->update($info);
            \app\commons\System::plog('编辑会员团队等级'.$info['id']);
		}else{
			$info['aid'] = aid;
			
			$info['createtime'] = time();
			$id = Db::name('member_team_level')->insertGetId($info);
            \app\commons\System::plog('添加会员团队等级'.$id);
		}
		return json(['status'=>1,'msg'=>'操作成功','url'=>(string)url('index')]);
	}
	//删除
	public function del(){
		$ids = input('post.ids/a');
		if(empty($ids)) return json(['status'=>0,'msg'=>'请选择要删除的数据']);
	 	Db::name('member_team_level')->where('aid',aid)->where('id','in',$ids)->delete();
        \app\commons\System::plog('删除'.t('团队等级').implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
	//申请记录
	public function applyorder(){
		$levelList = Db::name('member_team_level')->where('aid',aid)->select()->toArray();
		$levelArr = array();
		foreach($levelList as $v){
			$levelArr[$v['id']] = [
				'name'=>$v['name'],
				'areafenhong'=>$v['areafenhong'],
				'field_list'=>json_decode($v['field_list'],true),
				'is_agree'=>$v['is_agree']??0,
			];
		}
		if(request()->isAjax()){
			$page = input('param.page');
			$limit = input('param.limit');
			if(input('param.field') && input('param.order')){
				$order = input('param.field').' '.input('param.order');
			}else{
				$order = 'vo.createtime desc,id desc';
			}
			$where = [];
			$where[] = ['vo.aid','=',aid];
            if(input('param.mid')) $where[] = ['m.id','=',input('param.mid')];
			if(input('param.pid')) $where[] = ['m.pid','=',input('param.pid')];
			if(input('param.nickname')) $where[] = ['m.nickname','like','%'.input('param.nickname').'%'];
			if(input('param.realname')) $where[] = ['m.realname','like','%'.input('param.realname').'%'];
		
			if(input('param.ctime')){
				$ctime = explode(' ~ ',$_GET['ctime']);
				$where[] = ['vo.createtime','>=',strtotime($ctime[0])];
				$where[] = ['vo.createtime','<',strtotime($ctime[1]) + 86400];
			}
			if(input('?param.type') && input('param.type')!='all'){
			    $where[] = ['vo.type','=',input('param.type')];
            }
			$count = 0 + Db::name('member_teamup_order')->alias('vo')->join('member m','vo.mid = m.id')->where($where)->count();
			$data = Db::name('member_teamup_order')->alias('vo')->join('member m','vo.mid = m.id')->field('vo.*,m.nickname,m.realname')->where($where)->page($page,$limit)->order($order)->select()->toArray();
			foreach($data as $k=>$v){
				$member = Db::name('member')->where('id',$v['mid'])->find();
				if(!$member){
					$data[$k]['nickname'] = '未找到该'.t('会员');
					$data[$k]['headimg'] = '';
				}else{
					$data[$k]['nickname'] = $member['nickname'];
					$data[$k]['headimg'] = $member['headimg'];
				}
				if($v['pid']) {
                    $parent = Db::name('member')->where('id', $v['pid'])->find();
                }elseif($member['pid']){
                    $parent = Db::name('member')->where('id',$member['pid'])->find();
                }else{
                    $parent = [];
                }
				if(!$parent){
					$data[$k]['pnickname'] = '';
					$data[$k]['pheadimg'] = '';
				}else{
					$data[$k]['pid'] = $parent['id'];
					$data[$k]['pnickname'] = $parent['nickname'];
					$data[$k]['pheadimg'] = $parent['headimg'];
				}
				$data[$k]['levelname'] = $v['beforelevelid'] ? $levelArr[$v['beforelevelid']]['name'] : $levelArr[$member['levelid']]['name'];
				$data[$k]['applylevelname'] = $levelArr[$v['levelid']]['name'];
			
			
			}
			return json(['code'=>0,'msg'=>'查询成功','count'=>$count,'data'=>$data]);
		}
		View::assign('levelArr',$levelArr);
		return View::fetch();
	}
		//审核删除
	public function applydel(){
		$ids = input('post.ids/a');
		Db::name('member_teamup_order')->where('aid',aid)->where('id','in',$ids)->delete();
		\app\commons\System::plog('删除'.t('团队等级').'记录'.implode(',',$ids));
		return json(['status'=>1,'msg'=>'删除成功']);
	}
}