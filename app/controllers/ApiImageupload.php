<?php


namespace app\controllers;
use think\facade\Db;
class ApiImageupload extends ApiCommon{

	public function uploadbase64(){
		$base64_image_content = $_POST['imgFileBase'];
		//\think\facade\Log::write($base64_image_content);
		//匹配出图片的格式
		if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)){
			$type = strtolower($result[2]);
			if($type=='jpeg') $type = 'jpg';
			if(!in_array($type,explode(',',config('upload_type')))){
				return '';
			}
			$name = date('Ym/d_His').rand(1000,9999).'.'.$type;
            $path = 'upload/'.aid.'/';
			if (!file_exists(dirname(ROOT_PATH.$path.$name))) {
				mk_dir(dirname(ROOT_PATH.$path.$name));
			}
			if(file_put_contents(ROOT_PATH.$path.$name, base64_decode(str_replace($result[1], '', $base64_image_content)))){
				$url = PRE_URL.'/'.$path.$name;

				if(in_array($type,array('jpg','jpeg','png','bmp','webp'))) {
					$remote = Db::name('sysset')->where('name','remote')->value('value');
					$remote = json_decode($remote,true);
					$maxwidth = ($remote['thumb']==1 ? $remote['thumb_width'] : 0);
					$maxheight = ($remote['thumb']==1 ? $remote['thumb_height'] : 0);

					$size = getimagesize(ROOT_PATH.$path.$name);
					$width = $size[0];
					$height = $size[1];
					if($maxwidth > 0 && $maxheight > 0){
						if($width > $maxwidth || $height > $maxheight){
							$image = \think\Image::open(ROOT_PATH.$path.$name);
							$filepath = substr($path.$name,0,strlen($path.$name) - strlen($type) - 1).'_thumb.'.$type;
							$image->thumb($maxwidth,$maxheight)->save(ROOT_PATH.$filepath,null,100);
							$url = PRE_URL.'/'.$filepath;
						}
					}
				}

				iphoneimgrotate($url);
				$url= \app\commons\Pic::uploadoss($url);
				return $this->json(['status'=>1,'msg'=>'上传成功','url'=>$url]);
			}else{
				return $this->json(['status'=>0,'msg'=>'保存失败']);
			}
		}else{
			return $this->json(['status'=>0,'msg'=>'上传失败']);
		}
	}
	//旋转图片 顺时针90度
	public function xuanzhuan(){
		$url = input('param.url');
		$url = \app\commons\Pic::tolocal($url);
		$picpath = str_replace(PRE_URL.'/','',$url);
		$image = \think\Image::open(ROOT_PATH.$picpath);
		
		$picurlArr = explode('.',$picpath);
		$ext = end($picurlArr);
		if(!$ext || strlen($ext) > 6) $ext = 'jpg';
		if(!in_array($ext,explode(',',config('upload_type')))){
			return '';
		}
		$dir = 'upload/'.aid.'/'.date('Ym');
		if(!is_dir(ROOT_PATH.$dir)) mk_dir(ROOT_PATH.$dir);
		$filename = date('d_His').rand(1000,9999).'.'.$ext;
		$mediapath = $dir.'/'.$filename;

		$image->rotate()->save(ROOT_PATH.$mediapath);
		$url= \app\commons\Pic::uploadoss(PRE_URL.'/'.$mediapath);
		return $this->json(['status'=>1,'url'=>$url,'msg'=>'旋转成功']);
	}
	//上传图片
	function uploadImg(){
		$file = request()->file('file');
		ll(input('param.') ,'param');
		if($file){
			try {
			    if($file->extension()) {
                    validate(['file'=>['fileExt:'.config('app.upload_type')]])->check(['file' => $file]);
                }else{
                    validate(['file'=>['fileMime:'.config('app.upload_mime')]])->check(['file' => $file]);
                    $file->getOriginalExtension();
                }

				$savename = \think\facade\Filesystem::putFile(''.aid,$file);//上传目录增加aid
				$filepath = 'upload/'.str_replace("\\",'/',$savename);

				$maxwidth = input('param.maxwidth');
				$maxheight = input('param.maxheight');

				$rinfo = array();
				$rinfo['extension'] = strtolower($file->getOriginalExtension());
                $rinfo['extension'] = $rinfo['extension'] ? $rinfo['extension'] : 'png';
				$rinfo['name'] = $file->getOriginalName();
				if(in_array($rinfo['extension'],array('jpg','jpeg','png','bmp','webp'))) {

					if(!$maxwidth && !$maxheight){
						$remote = Db::name('sysset')->where('name','remote')->value('value');
						$remote = json_decode($remote,true);
						$maxwidth = ($remote['thumb']==1 ? $remote['thumb_width'] : 0);
						$maxheight = ($remote['thumb']==1 ? $remote['thumb_height'] : 0);
					}
					$size = getimagesize(ROOT_PATH.$filepath);
					$rinfo['width'] = $size[0];
					$rinfo['height'] = $size[1];
					if(input('param.isheadimg') == 1 || input('param.other_param') == 'headimg'){
                        ini_set ('memory_limit', '256M') ;
						$image = \think\Image::open(ROOT_PATH.$filepath);
						$filepath = substr($filepath,0,strlen($filepath) - strlen($rinfo['extension']) - 1).'_thumb.'.$rinfo['extension'];
						if($rinfo['width'] > $rinfo['height']){
							$cropwidth = $rinfo['height'];
							$cropheight = $rinfo['height'];
						}else{
							$cropwidth = $rinfo['width'];
							$cropheight = $rinfo['width'];
						}
						$image->crop($cropwidth,$cropheight)->thumb(160,160)->save(ROOT_PATH.$filepath,null,100);
						$size = getimagesize(ROOT_PATH.$filepath);
						$rinfo['width'] = $size[0];
						$rinfo['height'] = $size[1];
					}elseif($maxwidth > 0 && $maxheight > 0){
						if($rinfo['width'] > $maxwidth || $rinfo['height'] > $maxheight){
							$image = \think\Image::open(ROOT_PATH.$filepath);
							$filepath = substr($filepath,0,strlen($filepath) - strlen($rinfo['extension']) - 1).'_thumb.'.$rinfo['extension'];
							$image->thumb($maxwidth,$maxheight)->save(ROOT_PATH.$filepath,null,100);
							$size = getimagesize(ROOT_PATH.$filepath);
							$rinfo['width'] = $size[0];
							$rinfo['height'] = $size[1];
						}
					}

					if(input('param.xuanzhuan') == 1){
						$image = \think\Image::open(ROOT_PATH.$filepath);
						$image->rotate(270)->save(ROOT_PATH.$filepath,null,100);
					}
				}
				$rinfo['url'] = PRE_URL.'/'.$filepath;
		
				//如果为酒店签字 存到本地 不跟随系统
				if(input('param.hotelqz') == 1){
					$imgurl = \app\commons\Pic::tolocal($rinfo['url']);
				}else{
					$imgurl =  \app\commons\Pic::uploadoss($rinfo['url'],false,false);
				}
                if($imgurl === false){
                    return $this->json(['status'=>0,'msg'=>'附件设置未配置']);
                }
                $rinfo['url'] = $imgurl;
                if (input('param.isadmin')==1) {
                    $rinfo['url'] = $rinfo['url'];
                    $rinfo['id'] = Db::name('admin_upload')->insertGetId(array(
                        'aid' => aid,
                        'mid' => mid,
                        'name' => $rinfo['name'],
                        'dir' => date('Ymd'),
                        'url' => $rinfo['url'],
                        'type' => $rinfo['extension'],
                        'width' => $rinfo['width'],
                        'height' => $rinfo['height'],
                        'createtime' => time(),
                    ));
                
                }else {
                    if(in_array($rinfo['extension'],array('pem','xls','xlsx','pdf','doc','docs','docx'))){
                        $rinfo['url'] = $rinfo['url'];
                        $rinfo['id'] = Db::name('member_upload')->insertGetId(array(
                            'aid' => aid,
                            'mid' => mid,
                            'name' => $rinfo['name'],
                            'dir' => date('Ymd'),
                            'url' => $rinfo['url'],
                            'type' => $rinfo['extension'],
                            'width' => $rinfo['width'],
                            'height' => $rinfo['height'],
                            'createtime' => time(),
                        ));
                    }
                }
				
                if(false){}else{
                	return $this->json(['status'=>1,'msg'=>'上传成功','url'=>$rinfo['url'],'info'=>$rinfo,'sortnum'=>input('param.sortnum')]);
                }
			} catch (\think\exception\ValidateException $e) {
				return $this->json(['status'=>0,'msg'=>$e->getMessage()]);
			}
		}else{
			$errorNo = $_FILES['file']['error'];
			switch($errorNo) {
				case 1:
					$errmsg = '上传的文件超过了 upload_max_filesize 选项限制的值';break;
				case 2:
					$errmsg = '上传文件的大小超过了 HTML 表单中 MAX_FILE_SIZE 选项指定的值';break;
				case 3:
					$errmsg = '文件只有部分被上传';break;
				case 4:
					$errmsg = '没有文件被上传';break;
				case 6:
					$errmsg = '找不到临时文件夹';break;
				case 7:
					$errmsg= '文件写入失败';break;
				default:
					$errmsg = '未知上传错误！';
			}
			return $this->json(['status'=>0, 'msg'=>$errmsg]);
		}
	}
}