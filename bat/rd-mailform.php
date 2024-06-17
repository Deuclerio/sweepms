<?php

$formConfigFile = file_get_contents("rd-mailform.config.json");
$formConfig = json_decode($formConfigFile, true);

date_default_timezone_set('Etc/UTC');

try {
    require './phpmailer/PHPMailerAutoload.php';

    $recipients = $formConfig['recipientEmail'];

    preg_match_all("/([\w-]+(?:\.[\w-]+)*)@((?:[\w-]+\.)*\w[\w-]{0,66})\.([a-z]{2,6}(?:\.[a-z]{2})?)/", $recipients, $addresses, PREG_OFFSET_CAPTURE);

    if (!count($addresses[0])) {
        die('MF001');
    }

    function getRemoteIPAddress() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];

        } else if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        return $_SERVER['REMOTE_ADDR'];
    }

    if (preg_match('/^(127\.|192\.168\.)/', getRemoteIPAddress())) {
        die('MF002');
    }

    $template = file_get_contents('rd-mailform.tpl');

    if (isset($_POST['form-type'])) {
        $nomeVisitante = isset($_POST['nome']) ? $_POST['nome'] : "Visitante do Site";
        switch ($_POST['form-type']){
            case 'contact':
                $subject = 'O visitante  do site ' . strtoupper($nomeVisitante) . ' enviou uma mensagem.';
                break;
            case 'subscribe':
                $subject = 'Solicitação de inscrição';
                break;
            case 'order':
                $subject = 'Solicitação de pedido';
                break;
            default:
                $subject = 'O visitante  do site ' . strtoupper($nomeVisitante) . ' enviou uma mensagem.';
                break;
        }
    }else{
        die('MF004');
    }

    if (isset($_POST['email'])) {
        $template = str_replace(
            array("<!-- #{FromState} -->", "<!-- #{FromEmail} -->"),
            array("Email:", $_POST['email']),
            $template);
    }

    if (isset($_POST['mensagem'])) {
        $template = str_replace(
            array("<!-- #{MessageState} -->", "<!-- #{MessageDescription} -->"),
            array("Mensagem:", $_POST['mensagem']),
            $template);
    }
	
    if (isset($_POST['nome'])) {
        $template = str_replace(
            array("<!-- #{InfoName} -->", "<!-- #{InfoNameDescription} -->"),
            array("Nome:", $_POST['nome']),
            $template);
    }	
	
    if (isset($_POST['telefone'])) {
        $template = str_replace(
            array("<!-- #{InfoPhone} -->", "<!-- #{InfoPhoneDescription} -->"),
            array("Telefone:", $_POST['telefone']),
            $template);
    }	

    preg_match("/(<!-- #\{BeginInfo\} -->)(.|\s)*?(<!-- #\{EndInfo\} -->)/", $template, $tmp, PREG_OFFSET_CAPTURE);
    foreach ($_POST as $key => $value) {
        if ($key != "counter" && $key != "email" && $key != "telefone" && $key != "nome" && $key != "mensagem" && $key != "form-type" && $key != "g-recaptcha-response" && !empty($value)){
            $info = str_replace(
                array("<!-- #{BeginInfo} -->", "<!-- #{InfoState} -->", "<!-- #{InfoDescription} -->"),
                array("", ucfirst($key) . ':', $value),
                $tmp[0][0]);

            $template = str_replace("<!-- #{EndInfo} -->", $info, $template);
        }
    }

    $template = str_replace(
        array("<!-- #{Subject} -->", "<!-- #{SiteName} -->"),
        array($subject, $_SERVER['SERVER_NAME']),
        $template);

    $mail = new PHPMailer();


    if ($formConfig['useSmtp']) {
        //Tell PHPMailer to use SMTP
        $mail->isSMTP();

        //Enable SMTP debugging
        // 0 = off (for production use)
        // 1 = client messages
        // 2 = client and server messages
        $mail->SMTPDebug = 0;

        $mail->Debugoutput = 'html';

        // Set the hostname of the mail server
        $mail->Host = $formConfig['host'];

        // Set the SMTP port number - likely to be 25, 465 or 587
        $mail->Port = $formConfig['port'];

        // Whether to use SMTP authentication
        $mail->SMTPAuth = true;
        $mail->SMTPSecure = "ssl";

        // Username to use for SMTP authentication
        $mail->Username = $formConfig['username'];

        // Password to use for SMTP authentication
        $mail->Password = $formConfig['password'];
    }

    $mail->From = $addresses[0][0][0];

    # Attach file
    if (isset($_FILES['file']) &&
        $_FILES['file']['error'] == UPLOAD_ERR_OK) {
        $mail->AddAttachment($_FILES['file']['tmp_name'],
            $_FILES['file']['name']);
    }

    if (isset($_POST['nome'])){
        $mail->FromName = strtoupper($_POST['nome']);
    }else{
        $mail->FromName = "Visitante do Site";
    }

    foreach ($addresses[0] as $key => $value) {
        $mail->addAddress($value[0]);
    }

    $mail->CharSet = 'utf-8';
    $mail->Subject = $subject;
    $mail->MsgHTML($template);
    $mail->send();

    die('MF000');
} catch (phpmailerException $e) {
    die('MF254');
} catch (Exception $e) {
    die('MF255');
}