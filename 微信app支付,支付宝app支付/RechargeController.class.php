<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/3/30 0030
 * Time: 下午 1:43
 */
namespace Api\Controller;

class RechargeController extends ApiController{
    public function key(){
        if(IS_POST){
            $type = trim(I('post.type'));
            $xn_dd = trim(I('post.xn_dd'));
            $student_id = I('post.student_id');
            if($type == "1"){
                $config = C("ALIPAY");
                F('stu', $student_id, $path=DATA_PATH);
                //商户订单号，商户网站订单系统中唯一订单号，必填
                $out_trade_no = (String)date('YmdHis',time());
                //订单名称，必填
                $proName = '学生缴费'.$xn_dd;
                //付款金额，必填
                $total_amount = trim((String)I('post.total_amount'));
                //商品描述，可空这里用于判断使用时间
                $body = $xn_dd;
                Vendor('AlipayApp.aop.AopClient');
                Vendor('AlipayApp.aop.request.AlipayTradeAppPayRequest');
                $aop = new \AopClient();
                $aop->gatewayUrl = "https://openapi.alipay.com/gateway.do";
                $aop->appId = $config['app_id'];
                $aop->rsaPrivateKey = $config['merchant_private_key'];
                $aop->format = "json";
                $aop->charset = "UTF-8";
                $aop->signType = "RSA2";
                $aop->alipayrsaPublicKey = $config['alipay_public_key'];
//实例化具体API对应的request类,类名称和接口名称对应,当前调用接口名称：alipay.trade.app.pay
                $request = new \AlipayTradeAppPayRequest();
//SDK已经封装掉了公共参数，这里只需要传入业务参数
                $bizcontent = "{\"body\":\"$body\","
                    . "\"subject\": \"$proName\","
                    . "\"out_trade_no\": \"$out_trade_no\","
                    . "\"total_amount\": \"$total_amount\","
                    . "\"product_code\":\"QUICK_MSECURITY_PAY\""
                    . "}";
                $request->setNotifyUrl($config['notify_url']);
                $request->setBizContent($bizcontent);
//这里和普通的接口调用不同，使用的是sdkExecute
                $response = $aop->sdkExecute($request);
//htmlspecialchars是为了输出到页面时防止被浏览器将关键参数html转义，实际打印到日志以及http传输不会有这个问题
                $this->ajaxReturn(array('status'=>'success','data'=>$response,'msg'=>'成功'));//就是orderString 可以直接给客户端请求，无需再做处理。
            }elseif ($type == '2'){
                vendor('Wx.WxPayNativePay');
                $body  = '学生流量卡缴费';  // 描述
                $order = \WxPayConfig::MCHID.date('YmdHis'); // 订单号
                $price = trim(I('post.total_amount')); // 价格
                $d['appid'] = 'wx9c8da742ee1c48e6';
                $d['mch_id']= '1501971781';
                $d['nonce_str']= \Org\Util\String::randString(20);
                $d['body']     = $body;
                $d['attach'] = json_encode(array('student_id'=>$student_id,'xn_dd'=>$xn_dd));
                $d['out_trade_no']= $order;
                $d['total_fee']= $price*100;
                $d['spbill_create_ip']= gethostbyname($_ENV['COMPUTERNAME']);
                $d['notify_url']= 'http://www.xryxy.com/api.php/Recharge/solution_w.html';
                $d['trade_type']= 'APP';
                // 获取签名
                $d['sign'] = $this->_getwxsign($d);
                // 拼装数据
                $xml = $this->_setxmldata($d);
                // 发送请求
                $res = $this->_sendprePaycurl($xml);
                if($res['return_code'] == 'SUCCESS'){
                    // 二次签名
                    $t['appid'] = 'wx9c8da742ee1c48e6';
                    $t['noncestr'] = \Org\Util\String::randString(20);
                    $t['package'] = "Sign=WXPay";
                    $t['prepayid'] = $res['prepay_id'];
                    $t['partnerid'] = '1501971781';
                    $t['timestamp'] = time();
                    $t['sign'] = $this->_getsecondsign($t);
                    $this->ajaxReturn(array('status'=>'success','data'=>$t,'msg'=>'成功'));
                }else{
                    $this->ajaxReturn(array('status'=>'error','data'=>'','msg'=>'失败'));
                }
            }else{

            }
        }
    }
    //支付回调
    public function r_success(){
        $student_id = F('stu');
        Vendor('AlipayApp.aop.AopClient');
        $config = C("ALIPAY");
        $aop = new \AopClient;
        $aop->alipayrsaPublicKey = $config['alipay_public_key'];
        $flag = $aop->rsaCheckV1($_POST, NULL, "RSA2");
        if ($flag) {
            if ($_POST['trade_status'] == 'TRADE_SUCCESS'
                || $_POST['trade_status'] == 'TRADE_FINISHED') {//处理交易完成或者支付成功的通知
                //var_dump($_POST);exit;
                //获取订单号
                $data['order'] = $_POST['out_trade_no'];
                //交易号
                $data['price'] = $_POST['total_amount'];
                //订单支付时间
                $gmt_payment = $_POST['gmt_payment'];
                //转换为时间戳
                $data['created_at'] = strtotime($gmt_payment);
                $data['student_id'] = $student_id;
                $data['xn_dd'] = $_POST['body'];
                $data['type'] = 1;
                M("recharge")->add($data);
                F("stu",null);
            }
        }
    }
    //微信支付成功回调
    public function solution_w(){
        vendor('Wx.WxPayNativePay');
        Vendor('Wx.WxPay#Notify');
        $file_in = file_get_contents("php://input");
        $request=simplexml_load_string($file_in,'SimpleXMLElement', LIBXML_NOCDATA);
        $xmljson= json_encode($request);
        $xml=json_decode($xmljson,true);
        $attach = json_decode($xml['attach'],true);
        $data['order'] = $xml['transaction_id'];//transaction_id
        $data['student_id'] = $attach['student_id'];
        $data['xn_dd'] = $attach['xn_dd'];
        $data['price'] = $xml['total_fee']/100;
        $data['created_at'] = strtotime($xml['time_end']);
        $data['type'] = 2;
        M('recharge')->add($data);
        $notify = new \WxPayNotify();
        $notify->Handle(false); //调用方法*/
    }
    //充值记录
    public function record(){
        if(IS_POST){
            $student_id = I('post.student_id');
            $list = M("recharge")->where(['student_id'=>$student_id])->select();
            if($list){
                $return = array('status'=>'success','data'=>$list,'msg'=>'请求成功');
            }else{
                $return = array('status'=>'error','data'=>'','msg'=>'请求失败');
            }
            $this->ajaxReturn($return);
        }
    }
    public function solution_s(){
        $re = F('re');
        $data = F('data');
        var_dump($re,$data);
        //M("recharge")->add($data);
        F('re',null);
        F('data',null);
    }
    private function _sendprePaycurl($xmlData) {
        $url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
        $header[] = "Content-type: text/xml";
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,FALSE);//后续添加的,不然验证不过ssl
        curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,FALSE);//严格校验2
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $xmlData);
        $data = curl_exec($curl);
        if (curl_errno($curl)) {
            print curl_error($curl);
        }
        curl_close($curl);
        return $this->_xmldataparse($data);
    }
    //xml格式数据解析函数
    private function _xmldataparse($data){
        $msg = array();
        $msg = (array)simplexml_load_string($data, 'SimpleXMLElement', LIBXML_NOCDATA);
        return $msg;
    }
    /* 拼装请求的数据xml 生成xml数据格式 by gm 2017-11-2*/
    private function _setxmldata($data) {
        $xml = "<xml>
                    <appid>%s</appid>
                    <attach>%s</attach>
                    <body>%s</body>
                    <mch_id>%s</mch_id>
                    <nonce_str>%s</nonce_str>
                    <notify_url>%s</notify_url>
                    <out_trade_no>%s</out_trade_no>
                    <spbill_create_ip>%s</spbill_create_ip>
                    <total_fee>%d</total_fee>
                    <trade_type>%s</trade_type>
                    <sign>%s</sign>
                </xml>";
        $data = sprintf($xml, $data['appid'], $data['attach'], $data['body'], $data['mch_id'], $data['nonce_str'], $data['notify_url'], $data['out_trade_no'], $data['spbill_create_ip'], $data['total_fee'], $data['trade_type'], $data['sign']);
        return $data;
    }
    /*生成微信签名 by gm 2017-11-02*/
    private function _getwxsign($data){
        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $str .= !$str ? $key . '=' . $value : '&' . $key . '=' . $value;
        }
        $str.='&key=gX6YMzD4I7ckTHwhDNyLKQNlii54HG4M';
        //$str  = mb_convert_encoding($str,'utf-8',mb_detect_encoding($str));
        $sign = strtoupper(MD5($str));
        return $sign;
    }
    /*获取二次签名 by gm 2017-11-02*/
    private function _getsecondsign($data){
        $sign = array(
            "appid"=>$data['appid'],
            "noncestr"=>$data['noncestr'],
            "package"=>$data['package'],
            "prepayid"=>$data['prepayid'],
            "partnerid"=>$data['partnerid'],
            "timestamp"=>$data['timestamp'],
        );
        return $this->_getwxsign($sign);
    }
    public function package(){
        if(IS_POST){
            $id = I('post.sid');
            $re = M('package')->where(['school_id'=>$id])->field('cycle,price')->select();
            if($re){
                $this->ajaxReturn(array('status'=>'success','data'=>$re,'msg'=>'请求成功'));
            }else{
                $this->ajaxReturn(array('status'=>'error','data'=>'','msg'=>'请求失败'));
            }
        }
    }
    public function flow(){
        $data['flow_management'] = I('post.flow');
        $id = I('post.id');
        $re = M('student')->where(['id'=>$id])->save($data);
        if($re){
            $this->ajaxReturn(array('status'=>'success','data'=>'','msg'=>'请求成功'));
        }else{
            $this->ajaxReturn(array('status'=>'error','data'=>'','msg'=>'请求失败'));
        }
    }
}