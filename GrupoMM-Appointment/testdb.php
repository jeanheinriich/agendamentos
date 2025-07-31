<?php

function toBoolean($value): int {
  return $value ? 1 : 0;
}

// Configurações do banco de dados
$host = 'localhost';
$dbname = 'erp';
$user = 'admin';
$password = '#d3vj34n#';
$schema = 'erp';

try {
  // Conexão PDO com PostgreSQL
  echo "Conectando ao banco de dados...\n";
  $pdo = new PDO("pgsql:host=$host;dbname=$dbname", $user, $password);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  $pdo->exec(
    sprintf('SET search_path TO %s;', $schema)
  );

  echo "Conexão estabelecida com sucesso!\n";
  
  // Seleciona todos os registros da tabela 'users'
  $stmt = $pdo->query('SELECT * FROM users');
  $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Exibe os registros
  echo "Usuários:\n";
  
  foreach ($users as $user) {
    echo "ID: {$user['userid']}\n";
    echo "Nome: {$user['name']}\n";
    echo "\n";
  }
} catch (Exception $e) {
  // Rollback em caso de erro
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo "Erro: " . $e->getMessage();
}