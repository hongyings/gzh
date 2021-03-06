<?php
/**
 * Created by PhpStorm.
 * User: 17610
 * Date: 2019/9/5
 * Time: 10:12
 */

namespace app\wechat\controller;
use EasyWeChat\Factory;
use EasyWeChat\Kernel\Messages\Image;
use EasyWeChat\Kernel\Messages\Video;
use EasyWeChat\Kernel\Messages\Voice;
use EasyWeChat\Kernel\Messages\Raw;
use app\wechat\model\KeyModel;

class Message extends Base
{
    
    /**
     * 消息分流 (只能使用客服消息回复)
     *
     */
    public function shuntMsg()
    {
        //接收消息
        $params = json_decode(file_get_contents('php://input'),true);
        Logs($params,'params','gzh');
        $appId = isset($params['appid']) ? trim($params['appid']) : null;
        $openid = isset($params['FromUserName']) ? trim($params['FromUserName']) : null;
        $event = isset($params['Event']) ? trim($params['Event']) : null;
        $eventKey = isset($params['EventKey']) ? trim($params['EventKey']) : null;
        $mediaId = isset($params['MediaId']) ? trim($params['MediaId']) : null;
    
        $message = $params;
        //接受消息类型
        switch ($message['MsgType']) {
            case 'event':
                self::_event($appId, $openid, $event, $eventKey);
                break;
            case 'text':
                $keyword = isset($message['Content']) ? trim($message['Content']) : null;
                !empty($keyword) && self::_response($appId, $openid, $keyword);
                break;
            case 'image':
                $picUrl = isset($message['PicUrl']) ? trim($message['PicUrl']) : null;
                self::_image($openid, $mediaId, $picUrl);
                break;
            case 'voice':
                $format = isset($message['Format']) ? trim($message['Format']) : null;
                self::_voice($openid, $mediaId, $format);
                break;
            case 'video':
            case 'shortvideo':
                self::_video($openid, $mediaId, '');
                break;
            case 'location':    //微信暂不支持
                self::_location($openid);
                break;
            case 'link':    //微信暂不支持
                self::_link($openid);
                break;
            case 'file':
                self::_file($openid);
                break;
            // ... 其它消息
            default:
                break;
        }
    }
    
    //事件消息
    public static function _event($appId,$openid,$event,$eventKey)
    {
        $msg='';
        Logs(['event'=>$event,'eventKey'=>$eventKey],'event','gzh');
        switch ($event){
            case 'subscribe'://用户未关注时，进行关注后的事件推送 $eventKey=qrscene_...
                if(isset($eventKey) && stripos($eventKey,'qrscene_')===0)
                {
                    $eventKey=substr($eventKey,stripos($eventKey,'_')+1);
                    $msg = self::_response($appId,$openid,$eventKey);
                    
                } else {
                    
                    $msg = self::_response($appId,$openid,$event);
                    
                }
                break;
            case 'SCAN':// 扫码
                $msg = self::_response($appId,$openid,$eventKey);
                break;
            case 'LOCATION':// 上报地理位置
                break;
            case 'CLICK':// 点击菜单拉取消息时的事件推送
                $msg = self::_response($appId,$openid,$eventKey);
                break;
            case 'VIEW':// 点击菜单跳转链接时的事件推送
                $msg = self::_response($appId,$openid,$eventKey);
                break;
            case 'scancode_waitmsg':    //扫码带提示
                $msg = self::_response($appId,$openid,$eventKey);
                break;
        }
        return $msg;
    }
    
    //回复消息主入口   [文本]
    public static function _response($appId,$openid,$keyword='')
    {
        //查询数据库
        $custom_reply = self::getKeysMsg($appId,strtolower($keyword));
        $res='';
        if(!empty($custom_reply)){
            foreach ($custom_reply as $item){
                $content=json_decode(trim($item['content']),true);
                $res =  self::_json($appId,$openid,$item['msgtype'],$content);
            }
        }
        else {
            //机器人回复
            $message = self::_robot($keyword);
            $res =  self::_json($appId,$openid,'text',['content'=>$message]);
        }
        
        Logs(['appid'=>$appId,'openid'=>$openid,'keyword'=>$keyword,'res'=>$res],'return to the result','gzh');
        return  $res;
    }
    
    //图片消息
    public static function _image($openid,$mediaId,$picUrl)
    {
        return  $image = new Image($mediaId);
    }
    //语音消息
    public static function _voice($openid,$mediaId,$format)
    {
        return  $voice = new Voice($mediaId);
    }
    //视频消息
    public static function _video($openid,$mediaId,$title)
    {
        $video = new Video($mediaId, [
            'title' => $title,
            'description' => '...',
        ]);
        return $video;
    }
    //坐标消息
    public static function _location($openid,$keyword=''){}
    //链接消息
    public static function _link($openid,$keyword=''){}
    //文件消息
    public static function _file($openid,$keyword=''){}
    
    /**
     * 客服消息
     */
    public static function _json($appId,$openId,$msgType,$content)
    {
        $msg=[
            'touser' => $openId,
            'msgtype' => $msgType,
            "$msgType"=>$content
        ];
        $message = new Raw(json_encode($msg,JSON_UNESCAPED_UNICODE));
        $app = Factory::officialAccount(cache($appId));
        $result = $app->customer_service->message($message)->to($openId)->send();
        return $result;
    }
    
    //获取自定义回复内容
    public static function getKeysMsg($appId,$keys)
    {
        $res = KeyModel::getKeyMsg('msg_id,msgtype,content',['status'=>1,'keys'=>$keys,'appid'=>$appId]);
        return $res;
    }
}