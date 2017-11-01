<?php
namespace Home\Controller;
use Think\Controller;

//引入七牛云sdk，我使用的TP框架你可以根据自己的环境自行引入
vendor('Qiniu.autoload');
use Qiniu\Auth;  
use Qiniu\Storage\UploadManager;
use Qiniu\Storage\BucketManager;

class IndexController extends Controller {
    //下载录音
    public function downloadRecord(){
    	//参数
        $media_id = '前端jssdk上传录音到微信服务器后返回的serverid';
    	$token = '你的公众号全局access_token';
        
        //下载微信媒体文件的接口
        $url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token={$token}&media_id={$media_id}";
        
        $filename = time().rand(11111,99999); //录音下载到本地的文件名
        $filePath = "./Public/record/".$filename.'.amr'; //录音的全路径

        $download = $this->downAndSaveFile($url,$filePath); //下载录音

        if ($download) {
        	$response = array('status'=>1,'info'=>'从微信服务器下载文件成功');
        	//上传七牛并转换格式
        	$transcode = $this->transcode($filePath,$filename);
        	if ($transcode) {
        		$response = array('status'=>1,'info'=>'上传并转码成功');
        		//写入数据库
        		//这里执行你自己的业务逻辑
        	}else{
        		$response = array('status'=>0,'info'=>'上传并转码失败');
        	}
        }else{
        	$response = array('status'=>0,'info'=>'从微信服务器下载录音失败');
        }
        $this->ajaxReturn($response,'json');
    }

    //从微信服务器下载文件
    private function downAndSaveFile($url,$savePath){
        ob_start();
        readfile($url);
        $file  = ob_get_contents();
        ob_end_clean();
        // $size = strlen($file);
        $fp = fopen($savePath, 'a');
        fwrite($fp, $file);
        fclose($fp);
        if (file_exists($savePath)) {
        	return true;
        }else{
        	return false;
        }
    }

    //上传到七牛云并转码
    private function transcode($filePath,$filename){     
	$accessKey = '';  //你的七牛云ak,七牛云后台获取
	$secretKey = '';  //你的七牛云sk

        $auth = new Auth($accessKey, $secretKey); //七牛云权限验证

        $reply = array(
        	'bucket'=>'',//你的七牛云空间
        	'pipeline'=>'',//你的七牛云队列,如果不适用私有队列，可以不设置
        );
        $bucket = trim($reply['bucket']);
        //数据处理队列名称,不设置代表不使用私有队列，使用公有队列。    
        $pipeline = trim($reply['pipeline']);    
        //通过添加'|saveas'参数，指定处理后的文件保存的bucket和key    
        //不指定默认保存在当前空间，bucket为目标空间，后一个参数为转码之后文件名     
        $savekey = \Qiniu\base64_urlSafeEncode($bucket.':'.$filename.'.mp3');    
        //设置转码参数    
        $fops = "avthumb/mp3/ab/64k/ar/44100/acodec/libmp3lame";  
        $fops = $fops.'|saveas/'.$savekey;    
        if(!empty($pipeline)){
            $policy = array(
                'persistentOps' => $fops,
                'persistentPipeline' => $pipeline    
            );    
        }else{
            $policy = array(
                'persistentOps' => $fops    
            );    
        }    
                
        //指定上传转码命令    
        $uptoken = $auth->uploadToken($bucket, null, 3600, $policy);    
        $key = $filename.'.amr'; //七牛云中保存的amr文件名    
        $uploadMgr = new UploadManager();    
                
        //上传文件并转码$filePath为本地文件路径  
        list($ret, $err) = $uploadMgr->putFile($uptoken, $key, $filePath);    
        if ($err !== null) {
		return false;   
        }else {
        	//此时七牛云中同一段音频文件有amr和MP3两个格式的两个文件同时存在    
	        $bucketMgr = new BucketManager($auth);   
	        //为节省空间,删除amr格式文件    
	        $bucketMgr->delete($bucket, $key);
         	return true;
        }    
    }
}
