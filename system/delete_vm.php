<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
if(!empty($_SESSION['username']) && !empty($_P['ref'])){
    $cloud=[];
    $ip = get_client_ip();
    $ip_data= unserialize(_req('http://ip-api.com/php/'.$ip));
    $country=($ip_data['status']=='success')?$ip_data['country']:'Unknown';
    $rg=($ip_data['status']=='success')?$ip_data['regionName']:'Unknown';
    $city=($ip_data['status']=='success')?$ip_data['city']:'Unknown';
    _que('INSERT INTO log (data) VALUES (?)',["DELETE VM '{$_P['ref']}' BY {$ip} {$country} {$rg} {$city}"]);

    $query = _que('SELECT * FROM vm 
    INNER JOIN customer ON vm.cusid = customer.id
    INNER JOIN hosts ON vm.host = hosts.host WHERE vm.cusid=? AND vm.ref=?',[$_SESSION['username'],$_P['ref']]);
    if(!is_array($query) || @!isset($query['failed'])){
        $cloud = $query->fetch(PDO::FETCH_ASSOC);
    }
    if(empty($cloud)){
        $data=['msg'=>'No cloud in system'];
    } elseif($cloud['unlimited'] == 1) {
        $data=['msg'=>'ลบไม่ได้'];
    } elseif($cloud['manitance']==1) {
        $data=['msg'=>'เครื่องอยู่ในช่วงปรับปรุง'];
    } elseif(empty($cloud['2fa']) || (!empty($cloud['2fa']) && !empty($_SESSION['2fa']))) {

        $query = _que('DELETE FROM vm WHERE ref=?;UPDATE ip_address SET available = 0 , uuid="" WHERE uuid=?;',[$_P['ref'],$_P['ref']]);
        if(!is_array($query) || @!isset($query['failed'])){
            require_once'../API/Xen.php';
            $xen = new PsXenAPI($cloud['host'],$cloud['username'],$cloud['password']);
            $key = PsXenAPI::apref($_P['ref']);
            $data=$xen->rq('VM.get_record', [$key])['Value'];
            if($data["power_state"] == 'Running'){
                $xen->rq('VM.hard_shutdown', [$key]);
            }
            foreach ($data['VBDs'] as $vbd) {
                $vbdx=$xen->rq('VBD.get_record', [$vbd])['Value'];
                if($vbdx['type']=='Disk'){
                    $xen->rq('VDI.destroy', [$vbdx['VDI']]);
                }
            }
            $xen->rq('VM.destroy', [$key]);
            http_response_code(200);
            require_once '../API/PHPMailer.php';
            require_once '../API/SMTP.php';
            require_once '../API/Exception.php';
            require_once '../API/OAuth.php';
            // $setting=[];
            // $query = _que('SELECT * FROM setting');
            // if(!is_array($query) || @!isset($query['failed'])){
            //     $setting = $query->fetchAll(PDO::FETCH_ASSOC);
            // }
            // $stmp = json_decode($setting[0]['stmp']);
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            // $mail->Host       = $stmp->host;
            // $mail->CharSet = "UTF-8";   
            // $mail->SMTPAuth   = true;
            // $mail->Username   = $stmp->username;
            // $mail->Password   = $stmp->password;
            // $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            // $mail->Port       = 587;
            // $mail->setFrom($stmp->mail, $stmp->title);
            $mail->Host       = 'craft.in.th';
            $mail->CharSet = "UTF-8";   
            $mail->SMTPAuth   = true;
            $mail->Username   = 'no-reply@drite.in.th';
            $mail->Password   = '$66tujL6';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->setFrom('no-reply@drite.in.th', 'No-reply');
            $mail->isHTML(true);
            $mail->Subject = '[Craft.in.th] Delete Cloud';
            /*
            $res = '<html><center style="@import url(https://fonts.googleapis.com/css2?family=Kanit&display=swap);font-family: \'Kanit\', sans-serif;">
            <div style="text-align:center;width:100%;max-width:500px; background: rgb(12,112,222);
            background: linear-gradient(90deg, rgba(12,112,222,1) 0%, rgba(47,77,189,1) 100%);
            padding:4rem;">
            <center>
                <div style="color:#111;padding:2rem;background-color:#fff;width:100%;max-width:350px;">
                    <p>สวัสดี</p>
                    <p>เราได้ส่ง Email นี้มา เพราะ มี คนลบ Clpud ของคุณ โดย</p>
                    <p style="margin-bottom:0px;">IP '.$ip.'</p>
                    <p style="margin-bottom:0px;">Country '.$country.'</p>
                    <p style="margin-bottom:0px;">Region '.$rg.'</p>
                    <p style="margin-bottom:12px;">City '.$city.'</p>
                    <p>หากไม่ กรุณาติดต่อเราโดยทันที เพื่อป้องกันมิจฉาชีพที่จะมาสวมรอยเป็นคุณ</p>
                </div>
                <div style="color:#fff;padding:2rem;background-color:#111;width:100%;max-width:350px;">'.$end_body.'</div>
                </center>
            </div>
            </center></html>';
            */
            $email_text = ['cloud/destroy.png', $user['fname'].' '.$user['lname'], 'Delete Cloud', 'เราได้ส่ง Email นี้มาเนื่องจาก Cloud ของคุณถูกลบโดย', 'IP '.$ip.'<br>Country '.$country.'<br>Region '.$rg.'<br>City '.$city.'<br>หากไม่ใช่คุณ กรุณาติดต่อเราโดยทันที เพื่อป้องกันมิจฉาชีพที่จะมาสวมรอยเป็นคุณ', 'ติดต่อเรา', 'https://www.facebook.com/craftstudioofficial'];
            $res = str_replace(['{{image}}', '{{customer_full_name}}', '{{title}}', '{{subtitle}}', '{{description}}', '{{button_text}}', '{{button_url}}'], $email_text, file_get_contents(__DIR__.'/../components/email_template.html'));

            $res_alt = 'สวัสดี เราได้ส่ง Email นี้มา เพราะ มี คนลบ Clpud ของคุณ โดย IP '.$ip.' Country '.$country.' Region '.$rg.' City '.$city.' หากไม่ กรุณาติดต่อเราโดยทันที เพื่อป้องกันมิจฉาชีพที่จะมาสวมรอยเป็นคุณ '.$end_body;
            
            $mail->addAddress($cloud['email'],$cloud['fname']);
            $mail->Body    = $res;
            $mail->AltBody = $res_alt;
            
            $mail->Priority = 1;
            $mail->AddCustomHeader("X-MSMail-Priority: High");
            $mail->send();

            $data = ['msg' => 'สำเร็จ','eval'=>'window.location.replace("?page=home")'];
        } else {
            $data = ['msg'=>$query['msg']];
        }
        
    }
} else{
    $data=['msg'=>'Data incorrect!'];
}
