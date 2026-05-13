<?php
// ── AERON Solutions — Formulário de Contato v2 ─────────────────
// Envia e-mail completo + notificação Telegram

// ── Rota: download do VCF ─────────────────────────────────────
if (isset($_GET['vcf'])) {
    $file = __DIR__ . '/ContatoAERON.vcf';
    if (file_exists($file)) {
        header('Content-Type: text/vcard; charset=utf-8');
        header('Content-Disposition: attachment; filename="ContatoAERON.vcf"');
        header('Content-Length: ' . filesize($file));
        header('Cache-Control: no-cache, must-revalidate');
        readfile($file);
    } else {
        http_response_code(404);
        echo 'Arquivo não encontrado.';
    }
    exit;
}

header('Content-Type: application/json; charset=utf-8');

// Bloquear acesso direto sem POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'Método não permitido.']);
    exit;
}

// ── Configurações ─────────────────────────────────────────────
define('EMAIL_DESTINO', 'contato@aeronsolutions.com.br');
define('TG_TOKEN',      '8736158370:AAFEOFGGcSmgRJCsheL_n39b4bBmswUwBwk');
define('TG_CHAT_ID',    '762092421');

// ── Receber e sanitizar dados ─────────────────────────────────
function clean($val) {
    return htmlspecialchars(strip_tags(trim($val ?? '')), ENT_QUOTES, 'UTF-8');
}

$nome     = clean($_POST['nome']     ?? '');
$whatsapp = clean($_POST['whatsapp'] ?? '');
$email    = clean($_POST['email']    ?? '');
$servico  = clean($_POST['servico']  ?? '');
$mensagem = clean($_POST['mensagem'] ?? '');

// ── Validação básica ──────────────────────────────────────────
if (!$nome || !$whatsapp || !$email || !$servico) {
    echo json_encode(['ok' => false, 'msg' => 'Preencha todos os campos obrigatórios.']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['ok' => false, 'msg' => 'E-mail inválido.']);
    exit;
}

// ── Montar e-mail HTML ────────────────────────────────────────
$data_hora = date('d/m/Y H:i:s');

$corpo_html = "
<!DOCTYPE html>
<html lang='pt-BR'>
<head><meta charset='UTF-8'><style>
  body { font-family: Arial, sans-serif; background:#f4f6f9; margin:0; padding:20px; }
  .card { background:#fff; border-radius:12px; padding:32px; max-width:560px; margin:0 auto; box-shadow:0 2px 12px rgba(0,0,0,0.08); }
  .badge { display:inline-block; background:#0A66F7; color:#fff; padding:4px 12px; border-radius:20px; font-size:12px; font-weight:bold; margin-bottom:20px; }
  h2 { color:#0A66F7; margin:0 0 24px; font-size:20px; }
  .field { margin-bottom:16px; padding:14px 16px; background:#f8fafc; border-left:3px solid #0A66F7; border-radius:0 8px 8px 0; }
  .label { font-size:11px; color:#64748b; text-transform:uppercase; letter-spacing:.5px; font-weight:600; margin-bottom:4px; }
  .value { color:#1e293b; font-size:15px; font-weight:500; }
  .msg-box { background:#f0f9ff; border:1px solid #bae6fd; border-radius:8px; padding:16px; margin-top:20px; }
  .footer { text-align:center; margin-top:24px; color:#94a3b8; font-size:12px; }
</style></head>
<body>
<div class='card'>
  <span class='badge'>🔔 Novo Lead</span>
  <h2>Novo contato via site</h2>
  <div class='field'><div class='label'>Nome</div><div class='value'>{$nome}</div></div>
  <div class='field'><div class='label'>WhatsApp</div><div class='value'>{$whatsapp}</div></div>
  <div class='field'><div class='label'>E-mail</div><div class='value'>{$email}</div></div>
  <div class='field'><div class='label'>Serviço de interesse</div><div class='value'>{$servico}</div></div>
  " . ($mensagem ? "<div class='msg-box'><div class='label' style='margin-bottom:8px'>Mensagem</div><div class='value' style='white-space:pre-wrap'>{$mensagem}</div></div>" : "") . "
  <div class='footer'>Recebido em {$data_hora} · AERON Solutions</div>
</div>
</body></html>";

// ── Enviar e-mail via mail() da Hostinger ─────────────────────
$assunto = "🔔 Novo Lead AERON – {$nome} ({$servico})";
$headers  = "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: AERON Solutions <no-reply@aeronsolutions.com.br>\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$email_ok = mail(EMAIL_DESTINO, $assunto, $corpo_html, $headers);

// ── Notificação Telegram ──────────────────────────────────────
$msg_tg = "🔔 *Novo Lead AERON*\n\n"
    . "👤 *Nome:* {$nome}\n"
    . "📱 *WhatsApp:* {$whatsapp}\n"
    . "📧 *E-mail:* {$email}\n"
    . "🎯 *Serviço:* {$servico}\n"
    . ($mensagem ? "💬 *Mensagem:* {$mensagem}\n" : "")
    . "\n⏰ {$data_hora}";

$tg_url  = "https://api.telegram.org/bot" . TG_TOKEN . "/sendMessage";
$tg_data = http_build_query([
    'chat_id'    => TG_CHAT_ID,
    'text'       => $msg_tg,
    'parse_mode' => 'Markdown',
]);

$ch = curl_init($tg_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST,           true);
curl_setopt($ch, CURLOPT_POSTFIELDS,     $tg_data);
curl_setopt($ch, CURLOPT_TIMEOUT,        10);
curl_exec($ch);
curl_close($ch);

// ── Resposta ──────────────────────────────────────────────────
if ($email_ok) {
    echo json_encode(['ok' => true, 'msg' => 'Mensagem enviada com sucesso!']);
} else {
    // Fallback: mesmo se o mail() falhar, retorna ok para o usuário
    // mas loga o erro no servidor
    error_log("AERON Contact Form - Falha ao enviar email para: " . EMAIL_DESTINO);
    echo json_encode(['ok' => true, 'msg' => 'Mensagem enviada com sucesso!']);
}
