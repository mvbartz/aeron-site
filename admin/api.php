<?php
define('SENHA',      'Mvb@120189');
define('SESSION_KEY','aeron_admin');
define('INDEX_PATH', __DIR__ . '/../index.html');
define('MARKER',     '<!-- PORTFOLIO_END -->');

session_start();
header('Content-Type: application/json; charset=utf-8');

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

// ── Auth guard ───────────────────────────────────────────────
if (empty($_SESSION[SESSION_KEY])) {
    echo json_encode(['ok' => false, 'msg' => 'Não autorizado.']);
    exit;
}

if ($action === 'check') { echo json_encode(['ok' => true]); exit; }

// ── Helpers ──────────────────────────────────────────────────
function readIndex() {
    if (!file_exists(INDEX_PATH)) return null;
    return file_get_contents(INDEX_PATH);
}

function saveIndex($html) {
    file_put_contents(INDEX_PATH . '.bak', file_get_contents(INDEX_PATH));
    return file_put_contents(INDEX_PATH, $html) !== false;
}

/**
 * Extrai todos os itens de portfólio do HTML.
 * Cada item é uma linha com: data-pid="XXXX" ... </p></div></a></div>
 */
function parseItems($html) {
    // Captura cada div.animate-fade-up[data-pid] completo (tudo em uma linha)
    $pattern = '/<div class="animate-fade-up"([^>]*data-pid="([a-f0-9]+)"[^>]*)>(.*?<\/p><\/div><\/a><\/div>)/';
    preg_match_all($pattern, $html, $matches, PREG_SET_ORDER);

    $items = [];
    foreach ($matches as $m) {
        $attrs = $m[1];  // tudo entre animate-fade-up" e >
        $pid   = $m[2];
        $inner = $m[0];  // bloco completo

        // Título: último <p class="font-semibold...">...</p>
        preg_match('/font-semibold[^"]*"[^>]*>([^<]+)<\/p>/', $inner, $tm);
        $title = html_entity_decode(trim($tm[1] ?? ''), ENT_QUOTES, 'UTF-8');

        // href do <a>
        preg_match('/href="([^"]+)"/', $inner, $hm);
        $url = $hm[1] ?? '';

        // src da imagem
        preg_match('/src="([^"]+)"/', $inner, $im);
        $img = $im[1] ?? '';

        // Ativo: ausente de data-hidden="1"
        $active = strpos($attrs, 'data-hidden="1"') === false;

        $items[] = compact('pid', 'title', 'url', 'img', 'active', 'inner');
    }
    return $items;
}

// ── Listar ───────────────────────────────────────────────────
if ($action === 'list') {
    $html = readIndex();
    if (!$html) { echo json_encode(['ok' => false, 'msg' => 'index.html não encontrado.']); exit; }

    $items = parseItems($html);
    $out = array_map(fn($i) => [
        'pid'    => $i['pid'],
        'title'  => $i['title'],
        'url'    => $i['url'],
        'img'    => $i['img'],
        'active' => $i['active'],
    ], $items);

    echo json_encode(['ok' => true, 'items' => $out]);
    exit;
}

// ── Adicionar ────────────────────────────────────────────────
if ($action === 'add') {
    $title = trim($_POST['title'] ?? '');
    $url   = trim($_POST['url']   ?? '');
    $img   = trim($_POST['img']   ?? '');

    if (!$title || !$url || !$img) {
        echo json_encode(['ok' => false, 'msg' => 'Preencha todos os campos.']); exit;
    }

    $html = readIndex();
    if (!$html) { echo json_encode(['ok' => false, 'msg' => 'index.html não encontrado.']); exit; }
    if (strpos($html, MARKER) === false) {
        echo json_encode(['ok' => false, 'msg' => 'Marcador PORTFOLIO_END não encontrado no index.html.']); exit;
    }

    $pid = substr(md5($url . microtime()), 0, 8);
    $te  = htmlspecialchars($title, ENT_QUOTES);
    $ue  = htmlspecialchars($url,   ENT_QUOTES);
    $ie  = htmlspecialchars($img,   ENT_QUOTES);

    // Novo item vai para o TOPO (antes do primeiro item existente)
    // Se não houver itens, vai antes do MARKER
    $newItem = '<div class="animate-fade-up" data-pid="'.$pid.'">'
        . '<a href="'.$ue.'" target="_blank" rel="noopener noreferrer" class="group block rounded-2xl overflow-hidden border border-[#0A66F7]/20 hover:border-[#0A66F7]/60 hover:shadow-[0_0_35px_rgba(10,102,247,0.3)] transition-all duration-300 bg-[#0D1526]">'
        . '<div class="relative overflow-hidden aspect-video">'
        . '<img src="'.$ie.'" alt="'.$te.'" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500">'
        . '<div class="absolute inset-0 bg-[#0A66F7]/0 group-hover:bg-[#0A66F7]/50 transition-all duration-300 flex items-center justify-center">'
        . '<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="text-white opacity-0 group-hover:opacity-100 group-hover:scale-125 transition-all duration-300"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>'
        . '</div></div>'
        . '<div class="px-5 py-4"><p class="font-semibold text-gray-200 group-hover:text-[#0A66F7] transition-all duration-300">'.$te.'</p></div>'
        . '</a></div>';

    // Inserir no topo: antes do primeiro <div class="animate-fade-up" data-pid=
    $firstItem = strpos($html, '<div class="animate-fade-up" data-pid=');
    if ($firstItem !== false) {
        $newHtml = substr($html, 0, $firstItem) . $newItem . "\n        " . substr($html, $firstItem);
    } else {
        // Sem itens ainda — inserir antes do MARKER
        $newHtml = str_replace(MARKER, $newItem . "\n        " . MARKER, $html);
    }

    if (!saveIndex($newHtml)) {
        echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar. Verifique permissões.']); exit;
    }
    echo json_encode(['ok' => true, 'msg' => "\"$title\" adicionado com sucesso!", 'pid' => $pid]);
    syncToGitHub($newHtml, "Admin: adicionar portfólio \"$title\"");
    exit;
}

// ── Toggle ativo/inativo ─────────────────────────────────────
if ($action === 'toggle') {
    $pid    = trim($_POST['pid']    ?? '');
    $active = trim($_POST['active'] ?? '1');
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => 'PID inválido.']); exit; }

    $html = readIndex();
    if (!$html) { echo json_encode(['ok' => false, 'msg' => 'index.html não encontrado.']); exit; }

    $escaped = preg_quote($pid, '/');

    if ($active === '0') {
        // Adiciona data-hidden="1" e display:none
        $html = preg_replace(
            '/<div class="animate-fade-up"( data-pid="'.$escaped.'"[^>]*)>/',
            '<div class="animate-fade-up"$1 data-hidden="1" style="display:none">',
            $html
        );
    } else {
        // Remove data-hidden e display:none
        $html = preg_replace(
            '/<div class="animate-fade-up"( data-pid="'.$escaped.'"[^>]*?) data-hidden="1" style="display:none">/',
            '<div class="animate-fade-up"$1>',
            $html
        );
    }

    if (!saveIndex($html)) {
        echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar.']); exit;
    }
    echo json_encode(['ok' => true, 'msg' => $active === '1' ? 'Item ativado.' : 'Item ocultado.']);
    exit;
}

// ── Excluir ──────────────────────────────────────────────────
if ($action === 'delete') {
    $pid = trim($_POST['pid'] ?? '');
    if (!$pid) { echo json_encode(['ok' => false, 'msg' => 'PID inválido.']); exit; }

    $html = readIndex();
    if (!$html) { echo json_encode(['ok' => false, 'msg' => 'index.html não encontrado.']); exit; }

    $escaped = preg_quote($pid, '/');
    // Remove o bloco inteiro (termina sempre com </p></div></a></div>)
    $new = preg_replace(
        '/\s*<div class="animate-fade-up"[^>]*data-pid="'.$escaped.'"[^>]*>.*?<\/p><\/div><\/a><\/div>/',
        '',
        $html
    );

    if ($new === null || $new === $html) {
        echo json_encode(['ok' => false, 'msg' => 'Item não encontrado (PID: '.$pid.').']); exit;
    }

    if (!saveIndex($new)) {
        echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar.']); exit;
    }
    echo json_encode(['ok' => true, 'msg' => 'Item removido.']);
    syncToGitHub($new, 'Admin: remover portfólio');
    exit;
}

// ── Reordenar ────────────────────────────────────────────────
if ($action === 'reorder') {
    $order = json_decode($_POST['order'] ?? '[]', true);
    if (!is_array($order) || empty($order)) {
        echo json_encode(['ok' => false, 'msg' => 'Ordem inválida.']); exit;
    }

    $html = readIndex();
    if (!$html) { echo json_encode(['ok' => false, 'msg' => 'index.html não encontrado.']); exit; }

    // Extrair todos os itens
    $items = parseItems($html);
    if (empty($items)) {
        echo json_encode(['ok' => false, 'msg' => 'Nenhum item encontrado.']); exit;
    }

    // Mapear pid => bloco HTML
    $map = [];
    foreach ($items as $item) {
        $map[$item['pid']] = $item['inner'];
    }

    // Montar novo bloco na ordem recebida
    $newBlock = '';
    foreach ($order as $pid) {
        if (isset($map[$pid])) {
            $newBlock .= "\n        " . $map[$pid];
        }
    }

    // Remover todos os itens existentes e inserir novo bloco antes do MARKER
    $pattern = '/<div class="animate-fade-up" data-pid="[a-f0-9]+"[^>]*>.*?<\/p><\/div><\/a><\/div>\s*/';
    $htmlClean = preg_replace($pattern, '', $html);
    $newHtml   = str_replace(MARKER, $newBlock . "\n        " . MARKER, $htmlClean);

    if (!saveIndex($newHtml)) {
        echo json_encode(['ok' => false, 'msg' => 'Erro ao salvar.']); exit;
    }
    echo json_encode(['ok' => true, 'msg' => 'Ordem salva com sucesso.']);
    syncToGitHub($newHtml, 'Admin: reordenar portfólios');
    exit;
}

// ── Sincronizar index.html com GitHub ────────────────────────
// ── Função de sync com GitHub ─────────────────────────────────
function syncToGitHub($html, $msg = 'Admin: sincronizar index.html') {
    $config_file = __DIR__ . '/../.gh_config';
    if (!file_exists($config_file)) return false;
    $token = trim(file_get_contents($config_file));

    $api = 'https://api.github.com/repos/mvbartz/aeron-site/contents/index.html';

    // Buscar SHA atual
    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'header' => "Authorization: token $token\r\nUser-Agent: AERON-Admin\r\nAccept: application/vnd.github+json\r\n"
    ]]);
    $res = @file_get_contents($api, false, $ctx);
    if (!$res) return false;
    $sha = json_decode($res, true)['sha'] ?? null;
    if (!$sha) return false;

    // Enviar atualizado
    $ctx2 = stream_context_create(['http' => [
        'method'  => 'PUT',
        'header'  => "Authorization: token $token\r\nUser-Agent: AERON-Admin\r\nContent-Type: application/json\r\nAccept: application/vnd.github+json\r\n",
        'content' => json_encode(['message' => $msg, 'content' => base64_encode($html), 'sha' => $sha, 'branch' => 'main'])
    ]]);
    $res2 = @file_get_contents($api, false, $ctx2);
    return $res2 !== false;
}

if ($action === 'sync_github') {
    $html = readIndex();
    if (!$html) { echo json_encode(['ok' => false, 'msg' => 'index.html não encontrado.']); exit; }
    $ok = syncToGitHub($html, 'Admin: sync manual do index.html');
    echo json_encode($ok
        ? ['ok' => true,  'msg' => 'GitHub sincronizado com sucesso!']
        : ['ok' => false, 'msg' => 'Erro ao sincronizar. Verifique o arquivo .gh_config no servidor.']
    );
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Ação desconhecida.']);
