<?php
// ── Configuração ─────────────────────────────────────────────
define('SENHA',       'Mvb@120189');
define('SESSION_KEY', 'aeron_admin');
define('INDEX_PATH',  __DIR__ . '/../index.html');
define('MARKER',      '<!-- PORTFOLIO_END -->'); // marcador no index.html

session_start();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

// ── Login ────────────────────────────────────────────────────
if ($action === 'login') {
    if ($_POST['senha'] === SENHA) {
        $_SESSION[SESSION_KEY] = true;
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Senha incorreta.']);
    }
    exit;
}

// ── Logout ───────────────────────────────────────────────────
if ($action === 'logout') {
    session_destroy();
    echo json_encode(['ok' => true]);
    exit;
}

// ── Verificar sessão para as demais ações ────────────────────
if (empty($_SESSION[SESSION_KEY])) {
    echo json_encode(['ok' => false, 'msg' => 'Não autorizado.']);
    exit;
}

// ── Checar auth ──────────────────────────────────────────────
if ($action === 'check') {
    echo json_encode(['ok' => true]);
    exit;
}

// ── Listar portfólios atuais ─────────────────────────────────
if ($action === 'list') {
    if (!file_exists(INDEX_PATH)) {
        echo json_encode(['ok' => false, 'msg' => 'index.html não encontrado.']);
        exit;
    }
    $html = file_get_contents(INDEX_PATH);
    // Extrair itens do grid de portfólio
    preg_match_all('/<div class="animate-fade-up"[^>]*>.*?<\/div>\s*<\/div>\s*<\/a>\s*<\/div>/s', $html, $matches);
    echo json_encode(['ok' => true, 'count' => count($matches[0])]);
    exit;
}

// ── Adicionar novo item ──────────────────────────────────────
if ($action === 'add') {
    $title = trim($_POST['title'] ?? '');
    $url   = trim($_POST['url']   ?? '');
    $img   = trim($_POST['img']   ?? '');
    $delay = trim($_POST['delay'] ?? '0');

    if (!$title || !$url || !$img) {
        echo json_encode(['ok' => false, 'msg' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }

    if (!file_exists(INDEX_PATH)) {
        echo json_encode(['ok' => false, 'msg' => 'index.html não encontrado no caminho: ' . INDEX_PATH]);
        exit;
    }

    $html = file_get_contents(INDEX_PATH);

    if (strpos($html, MARKER) === false) {
        echo json_encode(['ok' => false, 'msg' => 'Marcador ' . MARKER . ' não encontrado no index.html. Adicione-o antes de fechar o grid de portfólio.']);
        exit;
    }

    $delayAttr = $delay !== '0' ? " style=\"transition-delay:{$delay}s\"" : '';
    $titleEsc  = htmlspecialchars($title, ENT_QUOTES);
    $urlEsc    = htmlspecialchars($url,   ENT_QUOTES);
    $imgEsc    = htmlspecialchars($img,   ENT_QUOTES);

    $newItem = <<<HTML

        <div class="animate-fade-up"{$delayAttr}><a href="{$urlEsc}" target="_blank" rel="noopener noreferrer" class="group block rounded-2xl overflow-hidden border border-[#0A66F7]/20 hover:border-[#0A66F7]/60 hover:shadow-[0_0_35px_rgba(10,102,247,0.3)] transition-all duration-300 bg-[#0D1526]"><div class="relative overflow-hidden aspect-video"><img src="{$imgEsc}" alt="{$titleEsc}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"><div class="absolute inset-0 bg-[#0A66F7]/0 group-hover:bg-[#0A66F7]/50 transition-all duration-300 flex items-center justify-center"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white opacity-0 group-hover:opacity-100 group-hover:scale-125 transition-all duration-300"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg></div></div><div class="px-5 py-4"><p class="font-semibold text-gray-200 group-hover:text-[#0A66F7] transition-all duration-300">{$titleEsc}</p></div></a></div>
HTML;

    $newHtml = str_replace(MARKER, $newItem . "\n        " . MARKER, $html);

    // Backup antes de salvar
    file_put_contents(INDEX_PATH . '.bak', $html);

    if (file_put_contents(INDEX_PATH, $newHtml) === false) {
        echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar index.html. Verifique as permissões da pasta.']);
        exit;
    }

    echo json_encode(['ok' => true, 'msg' => "\"$title\" adicionado ao portfólio com sucesso!"]);
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação desconhecida.']);
