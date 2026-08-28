<?php
declare(strict_types=1);
class EmailTemplates {
    private static function base(string $title,string $content,array $s):string{
        $name=h($s['admin_brand_name']??"Castro's Ready");$phone=h($s['phone']??'');$email=h($s['email']??'');$site=h($s['website']??'castrosready.us');$year=date('Y');
        return '<!doctype html><html><body style="margin:0;background:#f3f5f3;font-family:Arial,Helvetica,sans-serif;color:#20302f"><div style="max-width:640px;margin:24px auto;background:#fff;border:1px solid #dfe5e1;border-radius:18px;overflow:hidden"><div style="padding:26px 28px;background:#103c39;color:#fff;border-bottom:5px solid #f2d45c"><div style="font-size:13px;letter-spacing:.12em;text-transform:uppercase;opacity:.8">'.$name.'</div><h1 style="font-size:24px;margin:7px 0 0">'.h($title).'</h1></div><div style="padding:28px">'.$content.'</div><div style="padding:20px 28px;background:#f8faf9;border-top:1px solid #e4e9e6;font-size:13px;color:#667170"><strong>'.$name.'</strong><br>'.$phone.' · '.$email.'<br>'.$site.'<div style="margin-top:12px">© '.$year.' '.$name.'</div></div></div></body></html>';
    }
    public static function estimateAdmin(array $r,array $s):string{
        $rows=['Name'=>$r['full_name']??'','Phone'=>$r['phone']??'','Email'=>$r['email']??'','Address'=>$r['address']??'','Service'=>$r['service_needed']??'','Desired date'=>$r['desired_date']??''];$html='<p>A new free estimate request was submitted from the website.</p><div style="border:1px solid #e1e6e3;border-radius:12px;padding:16px">';foreach($rows as $k=>$v)$html.='<p style="margin:7px 0"><strong>'.h($k).':</strong> '.h((string)$v).'</p>';$html.='</div><p><strong>Project details</strong><br>'.nl2br(h($r['message']??'')) .'</p>';return self::base('New estimate request',$html,$s);
    }
    public static function estimateCustomer(array $r,array $s):string{ $name=h($r['full_name']??'there');return self::base('We received your request','<p>Hello '.$name.',</p><p>Thank you for contacting Castro’s Ready. We successfully received your free estimate request and our team will review it as soon as possible.</p><p>If you have additional information, you can reply through the contact methods shown on our website.</p>',$s); }
    public static function adminReset(string $name,array $s):string{return self::base('Administrator access reset','<p>Hello '.h($name).',</p><p>Administrator access for the Castro’s Ready CMS was reset for client handoff. Website content and customer requests were not deleted.</p><p>If you did not perform this action, review the hosting account and database immediately.</p>',$s);}
    public static function passwordReset(string $name,string $url,array $s):string{
        $safeUrl=h($url);
        return self::base('Reset administrator password','<p>Hello '.h($name).',</p><p>A password reset was requested for your Castro’s Ready administrator account.</p><p style="margin:24px 0"><a href="'.$safeUrl.'" style="display:inline-block;background:#0f7777;color:#fff;text-decoration:none;font-weight:700;padding:13px 18px;border-radius:10px">Create new password</a></p><p>This secure link expires in 60 minutes and can only be used once. Your password is never sent by email.</p><p style="font-size:12px;color:#687574;word-break:break-all">'.$safeUrl.'</p>',$s);
    }
    public static function test(string $method,array $s):string{return self::base('Email configuration test','<p>Your <strong>'.h($method).'</strong> email configuration is working correctly.</p><p>This message was generated from the Castro’s Ready administrator.</p>',$s);}
}
