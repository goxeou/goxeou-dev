<?php


namespace app\models;
use think\Model;
use think\facade\Db;
use app\commons\Wechat;
class Member extends Model
{
	//添加会员
	function add($aid,$data){
		if(!$data['levelid']){
			$data['levelid'] = Db::name('member_level')->where('aid',$aid)->where('isdefault',1)->value('id');
		}
		if($data['pid']){
			$parent = Db::name('member')->where('aid',$aid)->where('id',$data['pid'])->find();
			if(!$parent){
				$data['pid'] = 0;
			}else{
				if($parent['path']){
					$data['path'] = $parent['path'] . ',' .$parent['id'];
				}else{
					$data['path'] = ''.$parent['id'];
				}
                }
		}else{
			$data['path'] = '';
		}
// 		if($data['_pid']){
// 			$_parent = Db::name('member')->where('aid',$aid)->where('id',$data['_pid'])->find();
// 			if(!$_parent){
// 				$data['_pid'] = 0;
// 			}else{
// 				if($_parent['_path']){
// 					$data['_path'] = $_parent['_path'] . ',' .$_parent['id'];
// 				}else{
// 					$data['_path'] = ''.$_parent['id'];
// 				}
// 				}
// 		}else{
// 			$data['_path'] = '';
// 		}
		$data['random_str'] = random(16);
		$set = Db::name('admin_set')->where('aid',$aid)->find();
		if($set['reg_invite_code'] != 0 && $set['reg_invite_code_type']==1){
			$data['yqcode'] = self::getyqcode($aid);
		}
		if($set['reg_check'] == 1) $data['checkst'] = 0;
		$data['iscountscore'] = 1;
		$member = self::create($data);
		return $member->id;
	}
    //生成邀请码，目前仅6位数字格式
	function getyqcode($aid){
		$code = rand(100000,999999);
		$member = Db::name('member')->where('aid',$aid)->where('yqcode',$code)->find();
		if($member){
			return self::getyqcode($aid);
		}
		return $code;
	}
	//修改会员
	function edit($aid,$data){
		$member = self::where('aid',$aid)->where('id',$data['id'])->find();
		if(!$member) return ['status'=>0,'msg'=>t('会员').'不存在'];
		$oldpath = $member['path'];
        $oldlevelid = $member['levelid'];
		if(isset($data['pid'])){
            if($data['pid'] > 0){
                if($data['pid'] == $member['id']) return ['status'=>0,'msg'=>'不能设置自己为自己的上级'];
                $parent = Db::name('member')->where('aid',$aid)->where('id',$data['pid'])->find();
                if($parent['pid'] == $member['id']) return ['status'=>0,'msg'=>'不能互为上下级关系'];
                $parent_pids = explode(',',$parent['path']);
                if(in_array($data['id'],$parent_pids)){
                    return ['status'=>0,'msg'=>'不能以自己的网体会员作为上级'];
                }
            }
			if(!$parent){
				$data['pid'] = 0;
                $data['path'] = '';
			}else{
				if($parent['path']){
					$data['path'] = $parent['path'] . ',' .$parent['id'];
				}else{
					$data['path'] = ''.$parent['id'];
				}
			}
		}else{
			$data['path'] = '';
		}
		  $data['path'] = ltrim($data['path'],',');

		if(getcustom('2+1')){
    		if(isset($data['_pid'])){
                if($data['_pid'] > 0){
                    $_parent = Db::name('member')->where('aid',$aid)->where('id',$data['_pid'])->find();
                    if($_parent['_pid'] == $member['id']) return ['status'=>0,'msg'=>'不能互为上下级关系'];
                     $parentlevel = Db::name('member_level')->where('aid',$aid)->where('id',$_parent['levelid'])->find();
                    if($parentlevel['level_type'] != 2) return ['status'=>0,'msg'=>'id用户不满足条件'];
                }
    			if(!$_parent){
    				$data['_pid'] = 0;
                    $data['_path'] = '';
    			}else{
    				if($_parent['_path']){
    					$data['_path'] = $_parent['_path'] . ',' .$_parent['id'];
    				}else{
    					$data['_path'] = ''.$_parent['id'];
    				}
    			}
    		}else{
    			$data['_path'] = '';
    		}
		}
// 		if(isset($data['_pid'])){
//             if($data['_pid'] > 0){
//                 $_parent = Db::name('member')->where('aid',$aid)->where('id',$data['_pid'])->find();
//                 if($_parent['_pid'] == $member['id']) return ['status'=>0,'msg'=>'不能互为上下级关系'];
//             }
// 			if(!$_parent){
// 				$data['_pid'] = 0;
//                 $data['_path'] = '';
// 			}else{
// 				if($_parent['_path']){
// 					$data['_path'] = $_parent['_path'] . ',' .$_parent['id'];
// 				}else{
// 					$data['_path'] = ''.$_parent['id'];
// 				}
// 			}
// 		}else{
// 			$data['_path'] = '';
// 		}
		
		if(!$data['aid']) $data['aid'] = $aid;
		if(!$data['random_str']) $data['random_str'] = random(16);
		$member->save($data);
		if($data['path'] != $oldpath){
			$allpath = trim($oldpath.','.$data['path'].','.$member->id);
		//	self::updatepath_bak($allpath);
			self::updatepath($member->id,$data['path']);

		}
		if(getcustom('2+1')){
    		if ($oldlevelid!= $data['levelid']) {
    		    $member = self::where('aid',$aid)->where('id',$data['id'])->find();
    		    $newlv = Db::name('member_level')->where('aid',$aid)->where('id',$data['levelid'])->find();
    		    if ($newlv["level_type"]==1) {
    		        \app\commons\Aaa::edit2($aid,$data['id']);
    		    }
    		}
    		if($data['_path'] != $old_path){
    			$all_path = trim($old_path.','.$data['_path'].','.$member->id);
    			self::updatepath2($all_path);
    		}
		}
		
		
		\app\commons\Wechat::updatemembercard($aid,$data['id']);
		 return ['status'=>1,'msg'=>'修改成功'];
	}
	//删除会员
	function del($aid,$mids,$bid = 0){
		if(!$mids) return ['status'=>0,'msg'=>'没有要删除的会员'];
		$where = [];
		$where[] = ['aid','=',aid];
        $where[] = ['id','in',$mids];
        $memberlist = self::where($where)->select();
		foreach($memberlist as $member){
			$downlist = self::where('aid',aid)->where('find_in_set('.$member['id'].',path)')->select();
			foreach($downlist as $down){
				if($down['pid'] == $member['id']){
					$down->pid = $member['pid'];
					$down->path = $member['path'];
				}else{
					$pathArr = explode(',',$down['path']);
					foreach($pathArr as $k=>$v){
						if($v == $member['id']){
							unset($pathArr[$k]);
						}
					}
					$down->path = implode(',',$pathArr);
				}
				$down->save();
			}
			if(getcustom('2+1')){
    			$downlist2 = self::where('aid',aid)->where('find_in_set('.$member['id'].',_path)')->select();
    			foreach($downlist2 as $down2){
    				if($down2['_pid'] == $member['id']){
    					$down2->_pid = $member['_pid'];
    					$down2->_path = $member['_path'];
    				}else{
    					$pathArr2 = explode(',',$down2['_path']);
    					foreach($pathArr2 as $k=>$v){
    						if($v == $member['id']){
    							unset($pathArr2[$k]);
    						}
    					}
    					$down2->_path = implode(',',$pathArr2);
    				}
    				$down2->save();
    				
    			}
			}
			self::where('aid',aid)->where('id',$member['id'])->delete();
            Db::name('member_level_record')->where('aid',aid)->where('mid',$member['id'])->delete();
            Db::name('member_favorite')->where('aid',aid)->where('mid',$member['id'])->delete();
            Db::name('member_address')->where('aid',aid)->where('mid',$member['id'])->delete();
            Db::name('member_history')->where('aid',aid)->where('mid',$member['id'])->delete();
            Db::name('member_poster')->where('aid',aid)->where('mid',$member['id'])->delete();
            Db::name('kefu_message')->where('aid',aid)->where('mid',$member['id'])->delete();
		}
		return ['status'=>1,'msg'=>'删除成功'];
	}

    //会员path
    function updatepath($pid,$new_path=''){
        if($new_path){
            //处理path异常问题
            $patharr = explode(',',$new_path);
            $patharr = array_filter($patharr);
            $patharr = array_unique($patharr);
            $new_path = implode(',',$patharr);
        }
        $memberlist = Db::name('member')->where("find_in_set({$pid},path)")->field('id,pid,path')->order('id')->select()->toArray();
        //按path长度排序，path越长层级越大
        usort($memberlist,function($a,$b){
            $al = count(explode(',',$a['path']));
            $bl = count(explode(',',$b['path']));
            if ($al == $bl)
                return 0;
            return ($al > $bl) ? 1 : -1;
        });
        $path_arr = [];
        $path_arr[$pid] = $new_path;
        foreach($memberlist as $member){
            $parent_path = $path_arr[$member['pid']];
            if($parent_path){
                $path = $parent_path.','.$member['pid'];
            }else{
                $path = $member['pid'];
            }
            $path = ltrim($path,',');
            Db::name('member')->where('id',$member['id'])->update(['path'=>$path]);
            $path_arr[$member['id']] = $path;
        }
    }
	
	//会员path 废弃
	function updatepath_bak($pathstr){
		if(!$pathstr) return;
		$pathArr = explode(',',$pathstr);
        $pathArr = array_unique($pathArr);
		foreach($pathArr as $pid){
			if(!$pid) continue;
			$memberlist = Db::name('member')->where("find_in_set({$pid},path)")->order('id')->select()->toArray();
			foreach($memberlist as $member){
				$pathArr = self::getpath($member,$path=[]);
				if($pathArr){
					$pathArr = array_reverse($pathArr);
					$pathstr = implode(',',$pathArr);
					Db::name('member')->where('id',$member['id'])->update(['path'=>$pathstr]);
				}else{
					Db::name('member')->where('id',$member['id'])->update(['path'=>'']);
				}
			}
		}
	}
	function getpath($member,$path,$deep=1){
		if($member['pid'] && $deep < 20){
			if(in_array($member['pid'],$path)) return $path;
			$path[] = $member['pid'];
			$parent = Db::name('member')->where('id',$member['pid'])->find();
			$deep = $deep +1;
			return self::getpath($parent,$path,$deep);
		}
		return $path;
	}
	public function send_free_notice($data){

    }
	//会员打标签
	function member_tag(){
		//查询标签
		$taglist = Db('member_tag')->where('aid',aid)->where('status',1)->where('type',1)->select()->toArray();
		foreach($taglist as $t){
			//$where = [];
			$where = '';
			if($t['regdatestatus']==1){
				$starttime = time() - $t['maxdays']*86400;
				$endtime = time() - $t['mindays']*86400;
				
				$where .= '  createtime between '.$starttime.' and '.$endtime;
			}

			if($t['levelstatus']==1){
				$where.= ' '.($t['regdatestatus']==1?$t['condition']:'').' levelid = '.$t['levelid'];
			}
	
			if($t['buystatus']==1){
				$where.= ' '.(($t['regdatestatus']==1 || $t['levelstatus']==1)?$t['condition']:'').' buynum  >='.$t['buynum'];
			}
			if($t['buymoneystatus']==1){
				$where.= ' '.(($t['regdatestatus']==1 || $t['levelstatus']==1 || $t['buystatus']==1)?$t['condition']:'').' buymoney >='.$t['buymoney'];
			}
			$memberlist = Db('member')->where('aid',aid)->where('checkst',1)->where($where)->column('id');
			//查订单表
			if($t['prostatus']==1){
				$proids = explode(',',$t['productids']);
				$mids = Db::name('member')->alias('m')->leftJoin('shop_order_goods og','m.id=og.mid')->where('og.aid',aid)->where('proid','in',$proids)->where('og.status','in','1,2,3')->group('og.mid')->column('og.mid');
				if($t['condition']=='or'){
					foreach($mids as $m){
						if(!in_array($m,$memberlist)){
							$memberlist[] = $m;
						}
					}
				}
				if($t['condition']=='and'){
					foreach($memberlist as $m){
						if(!in_array($m,$mids)){
							unset($m);
						}
					}
				}
			}
			foreach($memberlist as $m2){
				$member = Db::name('member')->where('aid', aid)->where('id', $m2)->find();
				$mtags = $t['id'];
				if($member['tags']){
					$tagarr = explode(',',$member['tags']);
					if(!in_array($t['id'],$tagarr)){
						$mtags = $member['tags'].','.$t['id'];
						Db::name('member')->where('aid', aid)->where('id', $member['id'])->update(['tags' => $mtags]);
					}
				}else{
					Db::name('member')->where('aid', aid)->where('id', $member['id'])->update(['tags' => $mtags]);
				}
			}
		}
	}
	//会员path
	function updatepath2($pathstr){
		if(!$pathstr) return;
		$pathArr = explode(',',$pathstr);
		foreach($pathArr as $pid){
			if(!$pid) continue;
			$memberlist = Db::name('member')->where("find_in_set({$pid},_path)")->order('id')->select()->toArray();
			foreach($memberlist as $member){
				$pathArr = self::getpath2($member,$path=[]);
				if($pathArr){
					$pathArr = array_reverse($pathArr);
					$pathstr = implode(',',$pathArr);
					Db::name('member')->where('id',$member['id'])->update(['_path'=>$pathstr]);
				}else{
					Db::name('member')->where('id',$member['id'])->update(['_path'=>'']);
				}
			}
		}
	}
	function getpath2($member,$path,$deep=1){
		if($member['_pid'] && $deep < 50){
			if(in_array($member['_pid'],$path)) return $path;
			$path[] = $member['_pid'];
			$parent = Db::name('member')->where('id',$member['_pid'])->find();
			$deep = $deep +1;
			return self::getpath2($parent,$path,$deep);
		}
		return $path;
	}
}