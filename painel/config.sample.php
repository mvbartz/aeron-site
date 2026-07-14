<?php
/**
 * Renomeie este arquivo para config.php no servidor (NÃO versionar no Git).
 * Gere o hash da sua senha uma vez, rodando no servidor ou em qualquer PHP:
 *   echo password_hash('SUA_SENHA_FORTE', PASSWORD_DEFAULT);
 * Cole o resultado abaixo.
 */
return [
    // Exemplo de hash (troque pelo seu). Este é o hash de "troque-esta-senha".
    'senha_hash'    => '$2y$10$exampleexampleexampleexampleexampleexampleexampleexampl',
    // Caminho do arquivo com o token do GitHub (também fora do Git).
    'gh_token_file' => __DIR__ . '/../.gh_config',
];
