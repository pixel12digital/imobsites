<?php
// Configuração para usar um banco de dados remoto
// Use este arquivo quando quiser sempre usar o banco remoto

// Configurações do banco de dados REMOTO
define('DB_HOST', getenv('REMOTE_DB_HOST') ?: '');
define('DB_NAME', getenv('REMOTE_DB_NAME') ?: '');
define('DB_USER', getenv('REMOTE_DB_USER') ?: '');
define('DB_PASS', getenv('REMOTE_DB_PASS') ?: '');

if (!DB_HOST || !DB_NAME || !DB_USER || !DB_PASS) {
    die('Configure as variáveis REMOTE_DB_HOST, REMOTE_DB_NAME, REMOTE_DB_USER e REMOTE_DB_PASS antes de utilizar o banco remoto.');
}

// Log para debug
error_log('[imobsites] FORÇANDO uso do banco REMOTO - Host: ' . DB_HOST . ' - Database: ' . DB_NAME);

try {
    // Conexão com timeout aumentado para conexões remotas
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8;connect_timeout=30";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Configurações adicionais para conexões remotas
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 30);
    $pdo->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES utf8");
    
    error_log('[imobsites] Conexão com banco REMOTO estabelecida com sucesso');
    
} catch(PDOException $e) {
    $error_msg = "Erro na conexão com o banco REMOTO: " . $e->getMessage();
    error_log('[imobsites] ' . $error_msg);
    
    // Em caso de erro, mostrar mensagem detalhada para debug
    die("Erro crítico: " . $error_msg . "<br><br>
         <strong>Verifique:</strong><br>
         - Se o host <strong>" . DB_HOST . "</strong> está correto<br>
         - Se as credenciais estão corretas<br>
         - Se o servidor permite conexões remotas<br>
         - Se o servidor MySQL está rodando<br>
         <br>
         <a href='test_banco_remoto.php'>🧪 Testar Conexão</a>");
}

// Função para executar queries
function query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Função para buscar uma linha
function fetch($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetch();
}

// Função para buscar todas as linhas
function fetchAll($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt->fetchAll();
}

// Função para buscar um registro por ID
function fetchById($table, $id) {
    global $pdo;
    $sql = "SELECT * FROM {$table} WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    return $stmt->fetch();
}

// Função para buscar registros por condição
function fetchWhere($table, $where, $params = []) {
    global $pdo;
    $sql = "SELECT * FROM {$table} WHERE {$where}";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

// Função para inserir dados
function insert($table, $data) {
    global $pdo;
    
    try {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute($data);
        
        if ($result) {
            $last_id = $pdo->lastInsertId();
            return $last_id;
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log("Erro na função insert: " . $e->getMessage());
        return false;
    }
}

// Função para atualizar dados
function update($table, $data, $where, $params = []) {
    global $pdo;
    
    $set_clause = [];
    $set_params = [];
    
    foreach (array_keys($data) as $column) {
        $set_clause[] = "{$column} = ?";
        $set_params[] = $data[$column];
    }
    
    $sql = "UPDATE {$table} SET " . implode(', ', $set_clause) . " WHERE {$where}";
    $stmt = $pdo->prepare($sql);
    
    $all_params = array_merge($set_params, $params);
    return $stmt->execute($all_params);
}

// Função para deletar dados
function delete($table, $where, $params = []) {
    global $pdo;
    
    try {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute($params);
        
        if ($result && $stmt->rowCount() > 0) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log("Erro na função delete: " . $e->getMessage());
        return false;
    }
}
?>
