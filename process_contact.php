<?php
// Processamento de formulário de contato - imobsites
require_once 'config/paths.php';
require_once 'config/database.php';
require_once 'config/config.php';

// Função para identificar tipo de operação baseado no assunto e mensagem
function identificarTipoOperacao($assunto, $mensagem) {
    $assunto_lower = strtolower($assunto);
    $mensagem_lower = strtolower($mensagem);
    
    // Palavras-chave para venda
    $palavras_venda = [
        'compra', 'comprar', 'adquirir', 'adquirindo', 'comprador', 'compradores',
        'financiamento', 'financiar', 'hipoteca', 'entrada', 'parcelas',
        'valor total', 'preço total', 'custo total', 'investimento'
    ];
    
    // Palavras-chave para locação
    $palavras_locacao = [
        'aluguel', 'alugar', 'locação', 'locar', 'locatário', 'locatários',
        'mensalidade', 'caução', 'fiador', 'contrato de aluguel',
        'valor mensal', 'preço mensal', 'mensal', 'temporário'
    ];
    
    // Verificar no assunto
    foreach ($palavras_venda as $palavra) {
        if (strpos($assunto_lower, $palavra) !== false) {
            return 'venda';
        }
    }
    
    foreach ($palavras_locacao as $palavra) {
        if (strpos($assunto_lower, $palavra) !== false) {
            return 'locacao';
        }
    }
    
    // Verificar na mensagem
    foreach ($palavras_venda as $palavra) {
        if (strpos($mensagem_lower, $palavra) !== false) {
            return 'venda';
        }
    }
    
    foreach ($palavras_locacao as $palavra) {
        if (strpos($mensagem_lower, $palavra) !== false) {
            return 'locacao';
        }
    }
    
    // Se não conseguir identificar, usar padrão baseado no assunto
    if (strpos($assunto_lower, 'compra') !== false || strpos($assunto_lower, 'venda') !== false) {
        return 'venda';
    } elseif (strpos($assunto_lower, 'aluguel') !== false) {
        return 'locacao';
    }
    
    // Padrão: venda (mais comum)
    return 'venda';
}

// Função para obter número de telefone baseado no tipo de operação
function obterTelefoneOperacao($tipo_operacao) {
    switch ($tipo_operacao) {
        case 'venda':
            return PHONE_VENDA;
        case 'locacao':
            return PHONE_LOCACAO;
        default:
            return PHONE_VENDA; // Usar número de vendas como padrão
    }
}

// Função para obter número do WhatsApp baseado no tipo de operação
function obterWhatsAppOperacao($tipo_operacao) {
    switch ($tipo_operacao) {
        case 'venda':
            return PHONE_WHATSAPP_VENDA;
        case 'locacao':
            return PHONE_WHATSAPP_LOCACAO;
        default:
            return '5511999999999'; // WhatsApp padrão
    }
}

// Função para salvar contato no banco de dados
function salvarContato($nome, $email, $telefone, $assunto, $mensagem, $tipo_operacao) {
    global $pdo;
    
    try {
        $sql = "INSERT INTO contatos (nome, email, telefone, assunto, mensagem, tipo_operacao, status, data_envio) 
                VALUES (?, ?, ?, ?, ?, ?, 'nao_lido', NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nome, $email, $telefone, $assunto, $mensagem, $tipo_operacao]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Erro ao salvar contato: " . $e->getMessage());
        return false;
    }
}

// Função para enviar e-mail de notificação
function enviarEmailNotificacao($nome, $email, $telefone, $assunto, $mensagem, $tipo_operacao) {
    $telefone_operacao = obterTelefoneOperacao($tipo_operacao);
    
    $para = SITE_EMAIL;
    $assunto_email = "Novo contato via site - " . ucfirst($tipo_operacao);
    
    $corpo = "
    <html>
    <head>
        <title>Novo Contato - imobsites</title>
    </head>
    <body>
        <h2>Novo contato recebido via site</h2>
        <p><strong>Tipo de Operação:</strong> " . ucfirst($tipo_operacao) . "</p>
        <p><strong>Nome:</strong> {$nome}</p>
        <p><strong>E-mail:</strong> {$email}</p>
        <p><strong>Telefone:</strong> {$telefone}</p>
        <p><strong>Assunto:</strong> {$assunto}</p>
        <p><strong>Mensagem:</strong></p>
        <p>{$mensagem}</p>
        <hr>
        <p><strong>Contato específico para {$tipo_operacao}:</strong> {$telefone_operacao}</p>
        <p><em>Este e-mail foi enviado automaticamente pelo sistema de contatos do site.</em></p>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_EMAIL . "\r\n";
    $headers .= "Reply-To: {$email}\r\n";
    
    return mail($para, $assunto_email, $corpo, $headers);
}

// Processar formulário se foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_contato'])) {
    $nome = cleanInput($_POST['nome']);
    $email = cleanInput($_POST['email']);
    $telefone = cleanInput($_POST['telefone']);
    $assunto = cleanInput($_POST['assunto']);
    $mensagem = cleanInput($_POST['mensagem']);
    
    // Validações básicas
    $erros = [];
    
    if (empty($nome)) {
        $erros[] = "Nome é obrigatório";
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros[] = "E-mail válido é obrigatório";
    }
    
    if (empty($mensagem)) {
        $erros[] = "Mensagem é obrigatória";
    }
    
    if (empty($erros)) {
        // Identificar tipo de operação
        $tipo_operacao = identificarTipoOperacao($assunto, $mensagem);
        
        // Salvar no banco de dados
        $contato_id = salvarContato($nome, $email, $telefone, $assunto, $mensagem, $tipo_operacao);
        
        if ($contato_id) {
            // Enviar e-mail de notificação
            enviarEmailNotificacao($nome, $email, $telefone, $assunto, $mensagem, $tipo_operacao);
            
            // Retornar sucesso
            $resultado = [
                'sucesso' => true,
                'mensagem' => 'Mensagem enviada com sucesso! Entraremos em contato em breve.',
                'tipo_operacao' => $tipo_operacao,
                'telefone_operacao' => obterTelefoneOperacao($tipo_operacao),
                'whatsapp_operacao' => obterWhatsAppOperacao($tipo_operacao)
            ];
        } else {
            $resultado = [
                'sucesso' => false,
                'mensagem' => 'Erro ao enviar mensagem. Tente novamente.'
            ];
        }
    } else {
        $resultado = [
            'sucesso' => false,
            'mensagem' => 'Por favor, corrija os seguintes erros: ' . implode(', ', $erros)
        ];
    }
    
    // Se for requisição AJAX, retornar JSON
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
        header('Content-Type: application/json');
        echo json_encode($resultado);
        exit;
    }
    
    // Se não for AJAX, redirecionar com mensagem
    if (isset($resultado['sucesso']) && $resultado['sucesso']) {
        header('Location: ' . getPagePath('contato') . '?success=1&tipo=' . $resultado['tipo_operacao']);
    } else {
        header('Location: ' . getPagePath('contato') . '?error=' . urlencode($resultado['mensagem']));
    }
    exit;
}

// Se acessado diretamente sem POST, redirecionar
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . getPagePath('contato'));
    exit;
}
?>
