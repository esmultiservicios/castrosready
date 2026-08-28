<?php
declare(strict_types=1);require __DIR__.'/config/bootstrap.php';header('Content-Type: application/json; charset=utf-8');
try{
 if($_SERVER['REQUEST_METHOD']!=='POST') throw new RuntimeException('Invalid request.');
 $name=trim((string)($_POST['name']??''));$phone=trim((string)($_POST['phone']??''));$email=trim((string)($_POST['email']??''));$address=trim((string)($_POST['address']??''));$service=trim((string)($_POST['service']??''));$date=trim((string)($_POST['date']??''));$message=trim((string)($_POST['message']??''));
 if($name===''&&$phone===''&&$email===''&&$message==='') throw new RuntimeException('Please provide at least your name or contact information.');
 $photo=null;if(!empty($_FILES['photos']['name'][0])){$f=['name'=>$_FILES['photos']['name'][0],'type'=>$_FILES['photos']['type'][0],'tmp_name'=>$_FILES['photos']['tmp_name'][0],'error'=>$_FILES['photos']['error'][0],'size'=>$_FILES['photos']['size'][0]];$photo=upload_image($f,'estimates','estimate',8);}
 $st=db()->prepare('INSERT INTO estimate_requests(full_name,phone,email,address,service_needed,desired_date,message,photo_path) VALUES(?,?,?,?,?,?,?,?)');$st->execute([$name,$phone,$email,$address,$service,$date!==''?$date:null,$message,$photo]);
 $settings=settings();$to=$settings['email']??'';if(filter_var($to,FILTER_VALIDATE_EMAIL)){$subject="New Castro's Ready estimate request";$body="Name: $name\nPhone: $phone\nEmail: $email\nAddress: $address\nService: $service\nDesired date: $date\n\n$message";$headers='From: website@'.($_SERVER['HTTP_HOST']??'localhost');@mail($to,$subject,$body,$headers);}
 echo json_encode(['ok'=>true,'message'=>'Thank you. Your free estimate request has been received.']);
}catch(Throwable $e){http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()]);}
