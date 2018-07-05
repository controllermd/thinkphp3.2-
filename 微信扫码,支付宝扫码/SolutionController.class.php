<?php
// +----------------------------------------------------------------------
// | OneThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2013 http://www.onethink.cn All rights reserved.
// +----------------------------------------------------------------------
// | Author: 麦当苗儿 <zuojiazi@vip.qq.com> <http://www.zjzit.cn>
// +----------------------------------------------------------------------


namespace Home\Controller;

use OT\DataDictionary;
use Think\Controller;

/**
 * 前台首页控制器
 * 主要获取首页聚合数据
 */
class SolutionController extends Controller {
   //支付
   public function solution_a(){
       $student = session('nowStudent');
       $data = M('xuej')->where(['id'=>$student['xueji']])->find();
       $student['cal'] = (date('Y',time())-$data['time']).'年级'.$data['number'].'班';
       $student['order'] = date('YmdHis',time());
       $package = M('package')->where(['school_id'=>$student['sid']])->select();
       $student['flow_management'] = M('student')->where(['id'=>$student['id']])->getField('flow_management');
        $this->assign('student',$student);
        $this->assign('package',$package);
   	    $this->display();
   }
   public function solution_aa(){
       if(IS_POST){
           //使用时间
           $xn_dd = trim(I('post.xn_dd'));
           $total_amount = trim(I('post.total_amount'));
           $total_amount = M('package')->where(['cycle'=>$total_amount])->getField('price');
           if(I('post.type') == '1'){
               //商户订单号，商户网站订单系统中唯一订单号，必填
               $out_trade_no = trim(I('post.out_trade_no'));
               //订单名称，必填
               $proName = '学生缴费'.$xn_dd;
               //付款金额，必填
               //$total_amount = 0.01;//trim((integer)I('post.total_amount')).".00";
               //商品描述，可空
               $body = '学生缴费';//trim($_POST['WIDbody']);
               Vendor('Alipay.aop.AopClient');
               Vendor('Alipay.aop.request.AlipayTradePagePayRequest');
               //请求
               $c = new \AopClient();
               $config = C('alipay');
               $c->gatewayUrl = "https://openapi.alipay.com/gateway.do";
               $c->appId = $config['app_id'];
               $c->rsaPrivateKey = $config['merchant_private_key'];
               $c->format = "json";
               $c->charset= "UTF-8";
               $c->signType= "RSA2";
               $c->alipayrsaPublicKey = $config['alipay_public_key'];
               $request = new \AlipayTradePagePayRequest();
               $request->setReturnUrl($config['return_url']);
               $request->setNotifyUrl($config['notify_url']);
               $request->setBizContent("{" .
                   "    \"product_code\":\"FAST_INSTANT_TRADE_PAY\"," .
                   "    \"subject\":\"$proName\"," .
                   "    \"out_trade_no\":\"$out_trade_no\"," .
                   "    \"total_amount\":$total_amount," .
                   "    \"body\":\"$body\"" .
                   "  }");
               $result = $c->pageExecute ($request);
               //这个地方将数据全部写入
               $model['order'] = trim(I('post.out_trade_no'));
               $model['price'] = trim((integer)I('post.total_amount'));
               $model['student_id'] = trim(I('post.student_id'));
               $model['type'] = trim(I('post.type'));
               $model['xn_dd'] = $xn_dd;
               $model['created_at'] = time();
               session('model',$model);
               //输出
               echo $result;
           }elseif(I('post.type') == '2'){
               $student = session('nowStudent');
               vendor('Wx.WxPayNativePay');
               $notify = new \NativePay();
               $url1 = $notify->GetPrePayUrl("123456789");
               //$total_amount = trim((integer)I('post.total_amount')).'.00';
               $premission_name = '学生缴费';
               $input = new \WxPayUnifiedOrder();
               $attach = json_encode(array('student_id'=>$student['id'],'xn_dd'=>$xn_dd));
               $input->SetBody($premission_name);//商品描述
               $input->SetAttach($attach);//附加数据可以不填
               $input->SetOut_trade_no(\WxPayConfig::MCHID.date('YmdHis'));//商户订单号必须32位
               $input->SetTotal_fee($total_amount*100);//金额,以分为单位
               $input->SetTime_start ( date ( "YmdHis" ) );//交易起始时间	time_start
               //$input->SetTime_expire ( date ( "YmdHis", time () + 600 ) );//可以不填
               // $input->SetGoods_tag ( "xxx-tag" );商品优惠标签可以不填
               $input->SetNotify_url ( "http://www.xryxy.com/api.php/Recharge/solution_w" );//通知地址
               $input->SetTrade_type ( "NATIVE" );//交易类型
               $input->SetProduct_id ( rand ( 4, 8 ) );//商品id为扫码交易的时候必填
               $result = $notify->GetPayUrl ( $input );
               $url2 = $result ["code_url"];
               /*Vendor('phpqrcode.phpqrcode');
               \QRcode::png($url2,false,'M',5,1,false);*/
               $this->assign('url',$url2);
               $this->display('Solution/Solution_b');
           }
       }
   }
   //支付情况
    public function solution_q(){
        $model = session('model');
       if(!empty($_GET['total_amount'])){
           M('recharge')->add($model);
           unset($_SESSION['model']);
           $this->redirect("Solution/solution_c");
       }
    }
   //充值记录
    public function solution_c(){
        $student = session('nowStudent');
        $list = M("recharge")->where(['student_id'=>$student['id']])->select();
        $this->assign("list",$list);
   	    $this->display();
   }
   //我的银行卡
   public function solut(){
   	$this->display();
   }
// 微信支付页面
   public function solution_b(){
   	$this->display();
   }
  
}