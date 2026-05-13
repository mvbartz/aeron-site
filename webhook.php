<?php
$token = "aeron_deploy_2026";
if (!isset($_GET['token']) || $_GET['token'] !== $token) {
    http_response_code(403);
    die("Acesso negado");
}

function run($cmd) {
    $descriptors = [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']];
    $p = proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($p)) return "FALHOU";
    fclose($pipes[0]);
    $out = stream_get_contents($pipes[1]);
    $err = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    proc_close($p);
    return trim($out . $err);
}

echo "<pre>";
echo "whoami: " . run('whoami') . "\n";
echo "HOME: " . run('echo $HOME') . "\n";
echo "pwd: " . run('pwd') . "\n";
echo "\nProcurando .git:\n";
echo run('find /home/u469388378 -name ".git" -type d 2>/dev/null') . "\n";
echo "\nConteudo de HOME:\n";
echo run('ls -la /home/u469388378/') . "\n";
echo "</pre>";
