<?php
/**
 * AERON · Painel de portfólio — API JSON
 * Fonte de dados: ../portfolio.json  (fonte única da verdade)
 * O site novo lê esse arquivo e monta o portfólio. Nenhum HTML é editado aqui.
 *
 * Segurança: a senha NÃO fica neste arquivo. Crie painel/config.php (fora do Git):
 *   <?php return ['senha_hash' => 'HASH', 'gh_token_file' => __DIR__.'/../.gh_config'];
 * Gere o hash uma vez com: echo password_hash('SUA_SENHA', PASSWORD_DEFAULT);
 */

session_start();
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

define('SESSION_KEY', 'aeron_painel');
define('DATA_PATH',   __DIR__ . '/../portfolio.json');
define('CATS',        ['tour', 'google', 'landing']);

// ── config (senha fora do código versionado) ─────────────────
$cfg = @include __DIR__ . '/config.php';
if (!is_array($cfg) || empty($cfg['senha_hash'])) {
    // Sem config: bloqueia escrita, mas explica. (não expõe segredo)
    $cfg = ['senha_hash' => null, 'gh_token_file' => __DIR__ . '/../.gh_config'];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

function out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

function readData(){
    if (!file_exists(DATA_PATH)) return ['categories'=>['tour'=>'Tour Virtual','google'=>'Google','landing'=>'Landing Pages'],'items'=>[]];
    $j = json_decode(file_get_contents(DATA_PATH), true);
    if (!is_array($j) || !isset($j['items'])) $j = ['categories'=>[],'items'=>[]];
    return $j;
}
function writeData($data){
    $data['items'] = array_values($data['items']);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @copy(DATA_PATH, DATA_PATH . '.bak');
    return file_put_contents(DATA_PATH, $json) !== false ? $json : false;
}
function slugId($title){
    $s = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
    $s = trim($s, '-');
    return ($s ?: 'item') . '-' . substr(md5($title . microtime()), 0, 4);
}

// ── login / logout / check ───────────────────────────────────
if ($action === 'login') {
    if (!$cfg['senha_hash']) out(['ok'=>false,'msg'=>'Painel sem config.php no servidor. Crie o arquivo com o hash da senha.']);
    if (password_verify($_POST['senha'] ?? '', $cfg['senha_hash'])) {
        session_regenerate_id(true);
        $_SESSION[SESSION_KEY] = true;
        out(['ok'=>true]);
    }
    out(['ok'=>false,'msg'=>'Senha incorreta.']);
}
if ($action === 'logout') { session_destroy(); out(['ok'=>true]); }

// leitura pública (o site também lê portfolio.json direto)
if ($action === 'list') { out(['ok'=>true, 'data'=>readData()]); }

// ── guarda de autenticação para escrita ──────────────────────
if (empty($_SESSION[SESSION_KEY])) out(['ok'=>false,'msg'=>'Não autorizado.','auth'=>false]);
if ($action === 'check') out(['ok'=>true]);

// ── salvar (criar/editar) ────────────────────────────────────
if ($action === 'save') {
    $id    = trim($_POST['id'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $cat   = trim($_POST['category'] ?? '');
    $link  = trim($_POST['link'] ?? '');
    $img   = trim($_POST['image'] ?? '');

    if ($title==='' || $link==='' || $img==='') out(['ok'=>false,'msg'=>'Preencha título, link e imagem.']);
    if (!in_array($cat, CATS, true)) out(['ok'=>false,'msg'=>'Categoria inválida.']);
    if (!filter_var($link, FILTER_VALIDATE_URL) || !filter_var($img, FILTER_VALIDATE_URL)) out(['ok'=>false,'msg'=>'Link ou imagem com URL inválida.']);

    $data = readData();
    if ($id !== '') {
        $found = false;
        foreach ($data['items'] as &$it) {
            if ($it['id'] === $id) { $it['title']=$title;$it['category']=$cat;$it['link']=$link;$it['image']=$img; $found=true; break; }
        }
        unset($it);
        if (!$found) out(['ok'=>false,'msg'=>'Item não encontrado.']);
        $msg = 'Trabalho atualizado.';
    } else {
        $maxOrder = 0; foreach ($data['items'] as $it) $maxOrder = max($maxOrder, ($it['order'] ?? 0));
        $id = slugId($title);
        array_unshift($data['items'], ['id'=>$id,'title'=>$title,'category'=>$cat,'image'=>$img,'link'=>$link,'active'=>true,'order'=>-1]);
        // renumbera ordem
        foreach ($data['items'] as $i=>&$it) $it['order']=$i; unset($it);
        $msg = 'Trabalho adicionado.';
    }
    if (writeData($data)===false) out(['ok'=>false,'msg'=>'Erro ao salvar. Verifique permissões do portfolio.json.']);
    syncToGitHub($cfg, $msg);
    out(['ok'=>true,'msg'=>$msg,'id'=>$id]);
}

// ── ativar/ocultar ───────────────────────────────────────────
if ($action === 'toggle') {
    $id = trim($_POST['id'] ?? '');
    $data = readData(); $ok=false;
    foreach ($data['items'] as &$it) if ($it['id']===$id) { $it['active']=!($it['active']??true); $ok=true; break; }
    unset($it);
    if (!$ok) out(['ok'=>false,'msg'=>'Item não encontrado.']);
    if (writeData($data)===false) out(['ok'=>false,'msg'=>'Erro ao salvar.']);
    syncToGitHub($cfg, 'Painel: alternar visibilidade');
    out(['ok'=>true]);
}

// ── excluir ──────────────────────────────────────────────────
if ($action === 'delete') {
    $id = trim($_POST['id'] ?? '');
    $data = readData();
    $before = count($data['items']);
    $data['items'] = array_values(array_filter($data['items'], fn($it)=>$it['id']!==$id));
    if (count($data['items'])===$before) out(['ok'=>false,'msg'=>'Item não encontrado.']);
    if (writeData($data)===false) out(['ok'=>false,'msg'=>'Erro ao salvar.']);
    syncToGitHub($cfg, 'Painel: remover trabalho');
    out(['ok'=>true,'msg'=>'Removido.']);
}

// ── reordenar ────────────────────────────────────────────────
if ($action === 'reorder') {
    $order = json_decode($_POST['order'] ?? '[]', true);
    if (!is_array($order) || !$order) out(['ok'=>false,'msg'=>'Ordem inválida.']);
    $data = readData();
    $map = []; foreach ($data['items'] as $it) $map[$it['id']] = $it;
    $new = [];
    foreach ($order as $i=>$id) if (isset($map[$id])) { $map[$id]['order']=$i; $new[]=$map[$id]; }
    // acrescenta os que não vieram na ordem (segurança)
    foreach ($data['items'] as $it) if (!in_array($it['id'], $order, true)) $new[]=$it;
    $data['items'] = $new;
    if (writeData($data)===false) out(['ok'=>false,'msg'=>'Erro ao salvar.']);
    syncToGitHub($cfg, 'Painel: reordenar');
    out(['ok'=>true]);
}

out(['ok'=>false,'msg'=>'Ação desconhecida.']);

// ── sync GitHub (dispara redeploy via webhook do Actions) ─────
function syncToGitHub($cfg, $msg){
    $tokenFile = $cfg['gh_token_file'] ?? (__DIR__ . '/../.gh_config');
    if (!file_exists($tokenFile)) return false;
    $token = trim(file_get_contents($tokenFile));
    $api = 'https://api.github.com/repos/mvbartz/aeron-site/contents/portfolio.json';
    $hdr = "Authorization: token $token\r\nUser-Agent: AERON-Painel\r\nAccept: application/vnd.github+json\r\n";
    $get = @file_get_contents($api, false, stream_context_create(['http'=>['method'=>'GET','header'=>$hdr]]));
    if (!$get) return false;
    $sha = json_decode($get, true)['sha'] ?? null; if (!$sha) return false;
    $body = json_encode(['message'=>$msg,'content'=>base64_encode(file_get_contents(DATA_PATH)),'sha'=>$sha,'branch'=>'main']);
    $put = @file_get_contents($api, false, stream_context_create(['http'=>['method'=>'PUT','header'=>$hdr."Content-Type: application/json\r\n",'content'=>$body]]));
    return $put !== false;
}
