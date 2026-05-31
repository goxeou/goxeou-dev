<?php


namespace app\commons;
use think\facade\Db;
use app\commons\File;
class Pic
{
	//远程图片保存到本地
	public static function tolocal($picurl,$aid='',$store=false,$fixed_dir='',$filename=''){
        if($aid == '') $aid = aid ?? 0;
		if(strpos($picurl,PRE_URL) !== 0){ //非本地
			if(!$picurl) return '';
			if($store){
				$info = db('pictolocal')->where('pic',$picurl)->find();
				if($info){
					return $info['url'];
				}
			}
			$picurlArr = explode('.',$picurl);
			$ext = end($picurlArr);
			if(!$ext || strlen($ext) > 6) $ext = 'jpg';
			if(!in_array($ext,explode(',',config('app.upload_type')))){
				return '';
			}
			if($fixed_dir){
                $dir = $fixed_dir;
            }else{
                $dir = "upload/{$aid}/".date('Ym');
            }
			if(!is_dir(ROOT_PATH.$dir)) mk_dir(ROOT_PATH.$dir);
			if(!$filename){
				$filename = date('d_His').rand(1000,9999).'.'.$ext;
			}
			$mediapath = $dir.'/'.$filename;
			$piccontent = request_get($picurl);
			file_put_contents(ROOT_PATH.$mediapath,$piccontent);
			$url = PRE_URL.'/'.$mediapath;
			if($store){
				db('pictolocal')->insert(['pic'=>$picurl,'url'=>$url,'createtime'=>time()]);
			}
		}else{
		    if($fixed_dir){
		        $arr = parse_url($picurl);
		        $path = $arr['path'];
				if(!$filename){
				    $file_name = basename($picurl);
				}else{
					$file_name = $filename;
				}
                $mediapath = $fixed_dir.'/'.$file_name;
                File::all_copy(ROOT_PATH.$path,ROOT_PATH.$mediapath);
                $url = PRE_URL.'/'.$mediapath;
            }else{
                $url = $picurl;
            }
		}
		return $url;
	}
	//上传图片
	public static function uploadoss($picurl,$store=false,$transcode=true){
		$oldpicurl = $picurl;
		if(!$oldpicurl) return '';
		if($store){
			$info = db('pictolocal')->where('pic',$oldpicurl)->find();
			if($info){
				return $info['url'];
			}
		}
		if(defined('aid') && aid > 0){
			$remoteset = db('admin')->where('id',aid)->value('remote');
			$remoteset = json_decode($remoteset,true);
			if(!$remoteset || $remoteset['type']==0){
				$remoteset = db('sysset')->where('name','remote')->value('value');
				$remoteset = json_decode($remoteset,true);
			}
		}else{
			$remoteset = db('sysset')->where('name','remote')->value('value');
			$remoteset = json_decode($remoteset,true);
		}
		//dump($remoteset);
		if($remoteset['type']==2){ //阿里云
			$alyunossConf = $remoteset['alioss'];
            if($alyunossConf['key'] == '' || $alyunossConf['secret'] == '' || $alyunossConf['bucket'] == '' ||  $alyunossConf['ossurl'] == ''){
                return false;
            }
			if(strpos($picurl,$alyunossConf['url']) === 0) return $picurl;
			$picurl = \app\commons\Pic::tolocal($picurl);
			$accessKeyId = $alyunossConf['key'];
			$accessKeySecret = $alyunossConf['secret'];
			$endpoint = 'http://'.$alyunossConf['ossurl'];
			$bucket= $alyunossConf['bucket'];
			// 文件名称
			$object = ltrim(str_replace(PRE_URL,'',$picurl),'/');
			// <yourLocalFile>由本地文件路径加文件名包括后缀组成，例如/users/local/myfile.txt
			$filePath = ROOT_PATH.$object;
			if(!file_exists($filePath)) return '';
			try{
				$ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
				$ossClient->uploadFile($bucket, $object, $filePath);
				@unlink($filePath);
				$picurl = $alyunossConf['url'].'/'.$object;
				if($store){
					db('pictolocal')->insert(['pic'=>$oldpicurl,'url'=>$picurl,'createtime'=>time()]);
				}
				return $picurl;
			} catch(OssException $e) {
                @unlink($filePath);
				return $picurl;
				//return $e->getMessage();
			}
		}elseif($remoteset['type']==3){ //七牛云
			$qiniuConf = $remoteset['qiniu'];
            if($qiniuConf['accesskey'] == '' || $qiniuConf['secretkey'] == '' || $qiniuConf['bucket'] == '' || $qiniuConf['url'] == ''){
                return false;
            }
			if(strpos($picurl,$qiniuConf['url']) === 0) return $picurl;
			$picurl = \app\commons\Pic::tolocal($picurl);

            $object = ltrim(str_replace(PRE_URL,'',$picurl),'/');
            $filePath = ROOT_PATH.$object;
            if(!file_exists($filePath)) return '';

			$auth = new \Qiniu\Auth($qiniuConf['accesskey'], $qiniuConf['secretkey']);
            $policy = null;
            if($qiniuConf['transcode'] == 'webp' && $transcode){
                $picurlPath = pathinfo($object);
                $objectWebp = $picurlPath['dirname'].'/'.$picurlPath['filename'].'.webp';
                $policy = array(
                    'persistentOps' => "imageMogr2/format/webp|saveas/" . \Qiniu\base64_urlSafeEncode($qiniuConf['bucket'] .':'.$objectWebp)
                );
            }
			$token = $auth->uploadToken($qiniuConf['bucket'],null,3600,$policy);
			$uploadMgr = new \Qiniu\Storage\UploadManager();
			// 调用 UploadManager 的 putFile 方法进行文件的上传。
			list($ret, $err) = $uploadMgr->putFile($token, $object, $filePath);
			//echo "\n====> putFile result: \n";
			if ($err !== null) {
                @unlink($filePath);
				return $picurl;
				//var_dump($err);
			} else {
				@unlink($filePath);
				$picurl = $qiniuConf['url'].'/'.$object;

                if($qiniuConf['transcode'] == 'webp' && $transcode){
//                    $config = new \Qiniu\Config();
//                    $bucketManager = new \Qiniu\Storage\BucketManager($auth, $config);
//                    $err = $bucketManager->delete($qiniuConf['bucket'],$object);

                    $picurl = $qiniuConf['url'].'/'.$objectWebp;
                    request_get($picurl.'?1');
                }
				if($store){
					db('pictolocal')->insert(['pic'=>$oldpicurl,'url'=>$picurl,'createtime'=>time()]);
				}
				return $picurl;
				//var_dump($ret);
			}
		}elseif($remoteset['type']==4){ //腾讯云

			$cosConf = $remoteset['cos'];
            if($cosConf['appid'] =='' || $cosConf['secretid'] =='' || $cosConf['secretkey'] =='' || $cosConf['bucket'] =='' || $cosConf['url'] == ''){
                return false;
            }
			if(strpos($picurl,$cosConf['url']) === 0) return $picurl;
			$picurl = \app\commons\Pic::tolocal($picurl);
			$secretId = $cosConf['secretid']; //"云 API 密钥 SecretId";
			$secretKey = $cosConf['secretkey']; //"云 API 密钥 SecretKey";
			$region = $cosConf['local']; //设置一个默认的存储桶地域
			$bucket = str_replace("-".$cosConf['appid'],'',$cosConf['bucket'])."-".$cosConf['appid'];
			$object = ltrim(str_replace(PRE_URL,'',$picurl),'/');
			$filePath = ROOT_PATH.$object;
			if(!file_exists($filePath)) return '';
			try {
				$cosClient = new \Qcloud\Cos\Client(array(
					'region' => $region,
					//'schema' => 'https', //协议头部，默认为http
					'credentials'=> array('secretId'  => $secretId ,'secretKey' => $secretKey)
				));
				$result = $cosClient->upload($bucket,$object,fopen($filePath, "rb"));
				@unlink($filePath);
				$picurl = $cosConf['url'].'/'.$object;
				if($store){
					db('pictolocal')->insert(['pic'=>$oldpicurl,'url'=>$picurl,'createtime'=>time()]);
				}
				return $picurl;
			} catch (\Exception $e){
                @unlink($filePath);
				echojson(['status'=>0,'msg'=>$e->getMessage()]);
				return $picurl;
			}
		}else{
			$picurl = \app\commons\Pic::tolocal($picurl);
			if($store){
				db('pictolocal')->insert(['pic'=>$oldpicurl,'url'=>$picurl,'createtime'=>time()]);
			}
			return $picurl;
		}
	}
	//删除图片
	public static function deletepic($picurl){
        \think\facade\Log::write('deletepic:'.$picurl);
		if(defined('aid') && aid > 0){ 
			$remoteset = db('admin')->where('id',aid)->value('remote');
			$remoteset = json_decode($remoteset,true);
			if(!$remoteset || $remoteset['type']==0){
				$remoteset = db('sysset')->where('name','remote')->value('value');
				$remoteset = json_decode($remoteset,true);
			}
		}else{
			$remoteset = db('sysset')->where('name','remote')->value('value');
			$remoteset = json_decode($remoteset,true);
		}
		if(strpos($picurl,PRE_URL) !== 0 && !getcustom('retain_osspic')){ //非本地
			if($remoteset['type']==2){ //阿里云
				$alyunossConf = $remoteset['alioss'];
				if(strpos($picurl,$alyunossConf['url']) !== 0) return ['status'=>0,'msg'=>''];
				$accessKeyId = $alyunossConf['key'];
				$accessKeySecret = $alyunossConf['secret'];
				$endpoint = 'http://'.$alyunossConf['ossurl'];
				$bucket= $alyunossConf['bucket'];
				// 文件名称
				$object = ltrim(str_replace($alyunossConf['url'],'',$picurl),'/');
				try{
					$ossClient = new \OSS\OssClient($accessKeyId, $accessKeySecret, $endpoint);
					$ossClient->deleteObject($bucket, $object);
					return ['status'=>1,'msg'=>''];
				} catch(OssException $e) {
					return ['status'=>0,'msg'=>''];
				}
			}elseif($remoteset['type']==3){ //七牛云
				$qiniuConf = $remoteset['qiniu'];
				if(strpos($picurl,$qiniuConf['url']) !== 0) return ['status'=>0,'msg'=>''];
				$auth = new \Qiniu\Auth($qiniuConf['accesskey'], $qiniuConf['secretkey']);
				$object = ltrim(str_replace($qiniuConf['url'],'',$picurl),'/');
				$config = new \Qiniu\Config();
				$bucketManager = new \Qiniu\Storage\BucketManager($auth, $config);
				$err = $bucketManager->delete($qiniuConf['bucket'],$object);
				return ['status'=>1,'msg'=>''];
			}elseif($remoteset['type']==4){ //腾讯云
				$cosConf = $remoteset['cos'];
				if(strpos($picurl,$cosConf['url']) !== 0) return ['status'=>0,'msg'=>''];
				$secretId = $cosConf['secretid'];
				$secretKey = $cosConf['secretkey'];
				$region = $cosConf['local'];
				$bucket = str_replace("-".$cosConf['appid'],'',$cosConf['bucket'])."-".$cosConf['appid'];
				$object = ltrim(str_replace($cosConf['url'],'',$picurl),'/');
				try {
					$cosClient = new \Qcloud\Cos\Client(array(
						'region' => $region,
						//'schema' => 'https', //协议头部，默认为http
						'credentials'=> array('secretId'  => $secretId ,'secretKey' => $secretKey)
					));
					$result = $cosClient->deleteObject(['Bucket'=>$bucket,'Key'=>$object]);
					return ['status'=>1,'msg'=>''];
				} catch (\Exception $e){
					echojson(['status'=>0,'msg'=>$e->getMessage()]);
					return $picurl;
				}
			}
		}else{
			$filePath = ROOT_PATH.ltrim(str_replace(PRE_URL,'',$picurl),'/');
			@unlink($filePath);
			return ['status'=>1,'msg'=>''];
		}
	}
}