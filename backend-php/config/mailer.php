<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../lib/phpmailer/Exception.php';
require_once __DIR__ . '/../lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../lib/phpmailer/SMTP.php';

function getMailSettings(): array {
    $file = __DIR__ . '/settings.json';
    if (file_exists($file)) {
        $json = json_decode(file_get_contents($file), true);
        return $json ?? [];
    }
    return [];
}

function enviarBienvenida(string $email, string $nombre, string $codigo): bool {
    $cfg  = getMailSettings();
    $smtp = $cfg['smtp'] ?? [];
    $tpl  = $cfg['email_bienvenida'] ?? [];

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = $smtp['host']      ?? 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtp['username']  ?? '';
        $mail->Password   = $smtp['password']  ?? '';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = (int)($smtp['port'] ?? 587);
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom($smtp['from'] ?? $smtp['username'] ?? '', $smtp['from_name'] ?? APP_NAME);
        $mail->addAddress($email, $nombre);

        $mail->isHTML(true);
        $mail->Subject = str_replace(['{nombre}','{codigo}'], [$nombre,$codigo], $tpl['asunto'] ?? 'Bienvenido/a');
        $mail->Body    = plantillaEmail($nombre, $codigo, $tpl);
        $mail->AltBody = "{$nombre},\n\nTu código de acceso: {$codigo}\n\n" . APP_NAME;;

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}

function plantillaEmail(string $nombre, string $codigo, array $tpl = []): string {
    $app_name_html = htmlspecialchars(APP_NAME);
    $saludo     = htmlspecialchars(str_replace(['{nombre}','{codigo}'], [$nombre,$codigo], $tpl['saludo']          ?? "¡Bienvenido/a, {$nombre}!"));
    $intro      = htmlspecialchars(str_replace(['{nombre}','{codigo}'], [$nombre,$codigo], $tpl['intro']           ?? ''));
    $etiqueta   = htmlspecialchars($tpl['etiqueta_codigo'] ?? 'Tu código de acceso');
    $instruc    = htmlspecialchars(str_replace(['{nombre}','{codigo}'], [$nombre,$codigo], $tpl['instrucciones']   ?? ''));
    $pie        = htmlspecialchars($tpl['pie']             ?? '');

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f5f5f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f7;padding:40px 20px">
    <tr><td align="center">
      <table width="100%" style="max-width:520px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08)">
        <tr>
          <td style="background:linear-gradient(135deg,#1c1c1e 0%,#2c2c2e 100%);padding:32px 40px;text-align:center">
            <div style="font-size:22px;font-weight:800;color:#ffffff;letter-spacing:3px">{$app_name_html}</div>
            <div style="font-size:13px;color:rgba(255,255,255,.6);margin-top:4px;letter-spacing:1px">NUTRICIÓN · BIENESTAR</div>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 40px">
            <p style="font-size:18px;font-weight:700;color:#1d1d1f;margin:0 0 8px">{$saludo}</p>
            <p style="font-size:15px;color:#444;line-height:1.6;margin:0 0 28px">{$intro}</p>
            <div style="background:#f5f5f7;border-radius:12px;padding:24px;text-align:center;margin-bottom:28px">
              <div style="font-size:12px;font-weight:600;color:#6e6e73;letter-spacing:1px;text-transform:uppercase;margin-bottom:10px">{$etiqueta}</div>
              <div style="font-size:34px;font-weight:800;color:#1d1d1f;letter-spacing:6px;font-family:monospace">{$codigo}</div>
            </div>
            <p style="font-size:14px;color:#6e6e73;line-height:1.6;margin:0">{$instruc}</p>
          </td>
        </tr>
        <tr>
          <td style="background:#fafafa;border-top:1px solid #e0e0e5;padding:20px 40px;text-align:center">
            <p style="font-size:12px;color:#aaa;margin:0">{$pie}</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}
