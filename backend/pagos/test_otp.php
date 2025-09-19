<?php
require '../vendor/autoload.php';

use OTPHP\TOTP;

// Usar el secreto generado
$secret = 'TWB4Q62NL6QF34SCEMLDXMFCGJPA52S6IYSFLJXCCJN3N7S7JOQLMVE3C2TGIQ7JIYRDTD7LXNFIAOMA3GCPLECPXM7IH22BCNEQYCI';
$totp = TOTP::create($secret);
$totp->setLabel('Luis Master');
$totp->setIssuer('Sistema Empresarial');

// Generar URI OTP
$uri = $totp->getProvisioningUri();

// Mostrar QR usando servicio externo
echo '<h3>Escanea este código QR con Google Authenticator</h3>';
echo '<img src="https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($uri) . '&size=200x200" />';
echo '<p>O usa este secreto manualmente: <strong>' . $secret . '</strong></p>';