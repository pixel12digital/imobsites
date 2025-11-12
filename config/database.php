<?php
// Configuração para ambiente local (XAMPP/WAMP)
// Ajuste as credenciais abaixo conforme o seu MySQL local
define('DB_HOST', 'localhost');
define('DB_NAME', 'imobsites');
define('DB_USER', 'root');
define('DB_PASS', '');

// Log para debug
error_log('[JTR Imóveis] Usando banco LOCAL - Host: ' . DB_HOST . ' - Database: ' . DB_NAME);

try {
    // Conexão com timeout otimizado para conexões remotas
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8;connect_timeout=30";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Configurações otimizadas para conexões remotas
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 30);
    $pdo->setAttribute(PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES utf8");
    
    // Tornar a variável $pdo global
    global $pdo;
    
    error_log('[JTR Imóveis] Conexão com banco LOCAL estabelecida com sucesso');

} catch(PDOException $e) {
    $error_msg = "Erro na conexão com o banco LOCAL: " . $e->getMessage();
    error_log('[JTR Imóveis] ' . $error_msg);

    // Em caso de erro, mostrar mensagem detalhada para debug
    die("Erro crítico: " . $error_msg . "<br><br>
         <strong>Verifique:</strong><br>
         - Se o servidor MySQL local está rodando<br>
         - Se o host <strong>" . DB_HOST . "</strong> está acessível<br>
         - Se as credenciais estão corretas<br>");
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
    
    try {
        $set_clause = [];
        $set_params = [];
        
        foreach (array_keys($data) as $column) {
            $set_clause[] = "{$column} = ?";
            $set_params[] = $data[$column];
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $set_clause) . " WHERE {$where}";
        error_log("DEBUG: SQL UPDATE: " . $sql);
        error_log("DEBUG: Parâmetros: " . print_r(array_merge($set_params, $params), true));
        
        $stmt = $pdo->prepare($sql);
        $all_params = array_merge($set_params, $params);
        $result = $stmt->execute($all_params);
        
        if ($result) {
            $rows_affected = $stmt->rowCount();
            error_log("DEBUG: Linhas afetadas: " . $rows_affected);
            return $rows_affected > 0; // Retorna true apenas se houve alteração
        } else {
            error_log("DEBUG: Erro na execução do UPDATE");
            return false;
        }
    } catch (Exception $e) {
        error_log("Erro na função update: " . $e->getMessage());
        return false;
    }
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
