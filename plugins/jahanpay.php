<?php
$pluginData[jahanpay][type] = 'payment';
$pluginData[jahanpay][name] = 'جهان پي';
$pluginData[jahanpay][uniq] = 'jahanpay';
$pluginData[jahanpay][description] = 'مخصوص پرداخت با درواز<span lang="fa"> اختصاصی</span> پرداخت <a href="http://jahanpay.me">جهان پی</a>';
$pluginData[jahanpay][author][name] = 'JahanPay';
$pluginData[jahanpay][author][url] = 'http://jahanpay.me';
$pluginData[jahanpay][author][email] = 'support@jahanpay.com';


$pluginData[jahanpay][field][config][1][title] = 'API';
$pluginData[jahanpay][field][config][1][name] = 'merchant';

//-- تابع انتقال به دروازه پرداخت
function gateway__jahanpay($data)
{
global $config,$db,$smarty;
include_once('include/libs/nusoap.php');
$merchantID = trim($data[merchant]);
$amount = round($data[amount]/10);
$invoice_id = $data[invoice_id];
$callBackUrl = $data[callback];
$callBackUrl = $data[callback].'&oid='.$invoice_id;

$client = new SoapClient("http://jpws.me/directservice?wsdl");
$res = $client->requestpayment($merchantID , $amount , $callBackUrl , $invoice_id );

$query = 'SELECT * FROM `config` WHERE `config_id` = "1" LIMIT 1';
$conf = $db->fetch($query);

if($res['result'] == 1)
{
$update[payment_res_num] = $res['au'];
$sql = $db->queryUpdate('payment', $update, 'WHERE `payment_rand` = "'.$invoice_id.'" LIMIT 1;');
$db->execute($sql);

$_SESSION['jpay_uniq']=$res['au'];
$_SESSION['invoice_id']=$data[invoice_id];
echo "<div style='display:none'>{$res['form']}</div>Please wait ... <script language='javascript'>document.jahanpay.submit(); </script>";
exit;
}
else
{
$data[title] = 'خطای سیستمی';
$data[message] = '<font color="red">خطا در اتصال به جهان پی</font><a href="index.php" class="button">بازگشت</a>';
$smarty->assign('config', $conf);
$smarty->assign('data', $data);
$smarty->display('message.tpl');
exit;
}
}


function callback__jahanpay($data)
{
global $db;
$oid = preg_replace('/[^0-9]/','',$_GET['oid']);
$sql = "SELECT * FROM `payment` WHERE `payment_rand` = {$oid} LIMIT 1;";
$payment = $db->fetch($sql);
if ($payment[payment_status] == 1)
{
$merchantID = trim($data[merchant]);
$amount = round($payment['payment_amount']/10);
$client = new SoapClient("http://jpws.me/directservice?wsdl");
$res = $client->verification($merchantID , $amount , $payment['payment_res_num'] , $payment['payment_rand'], $_POST + $_GET );
if($res['result'] == 1)
{
//-- آماده کردن خروجی
$output['status'] = 1;
$output['res_num'] = $payment['payment_res_num'];
$output['ref_num'] = $res['bank_au'];
$output['payment_id'] = $payment['payment_id'];
}
else
{
$output[status] = 0;
$output[message]= 'پرداخت انجام نشده است .';
}
}
else
{
$output[status] = 0;
$output[message]= 'این سفارش قبلا پرداخت شده است.';
}
return $output;
}
