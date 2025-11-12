<?php
// Iniciar output buffering para evitar problemas com headers
ob_start();

// Carregar configurações ANTES de iniciar a sessão
$config_path = dirname(__DIR__) . '/../config/';
require_once $config_path . 'paths.php';
require_once $config_path . 'database.php';
require_once $config_path . 'tenant.php';
require_once $config_path . 'config.php';

// Agora iniciar a sessão
session_start();

// Verificar se o usuário está logado
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true || !isset($_SESSION['tenant_id']) || (int)$_SESSION['tenant_id'] !== TENANT_ID) {
    header('Location: ../login.php');
    exit;
}

/**
 * FUNÇÃO GLOBAL DE RENUMERAÇÃO AUTOMÁTICA
 * Esta função funciona para QUALQUER imóvel, garantindo que as fotos
 * sejam sempre numeradas sequencialmente de 1 a N
 */
function renumerarFotosAutomaticamente($imovel_id) {
    // Buscar todas as fotos do imóvel ordenadas pela ordem atual
    $fotos_restantes = fetchAll("SELECT id FROM fotos_imovel WHERE imovel_id = ? AND tenant_id = ? ORDER BY ordem", [$imovel_id, TENANT_ID]);
    
    // Renumerar sequencialmente de 1 a N
    foreach ($fotos_restantes as $index => $foto) {
        $nova_ordem = $index + 1;
        query("UPDATE fotos_imovel SET ordem = ? WHERE id = ? AND tenant_id = ?", [$nova_ordem, $foto['id'], TENANT_ID]);
    }
    
    // Log para auditoria
    error_log("AUTO-RENUMERAÇÃO: Imóvel ID {$imovel_id} - Fotos reordenadas de 1 a " . count($fotos_restantes));
    
    return count($fotos_restantes);
}

$success_message = '';
$error_message = '';

// Verificar se foi passado um ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$imovel_id = (int)$_GET['id'];

// Buscar dados do imóvel
$imovel = fetch("
    SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro, u.nome as corretor_nome 
    FROM imoveis i 
    LEFT JOIN tipos_imovel t ON i.tipo_id = t.id AND t.tenant_id = i.tenant_id
    LEFT JOIN localizacoes l ON i.localizacao_id = l.id AND l.tenant_id = i.tenant_id
    LEFT JOIN usuarios u ON i.usuario_id = u.id AND u.tenant_id = i.tenant_id
    WHERE i.id = ? AND i.tenant_id = ?
", [$imovel_id, TENANT_ID]);

if (!$imovel) {
    header('Location: index.php');
    exit;
}

// Buscar dados para os selects
$tipos_imovel = fetchAll("SELECT * FROM tipos_imovel WHERE tenant_id = ? ORDER BY nome", [TENANT_ID]);
$localizacoes = fetchAll("SELECT * FROM localizacoes WHERE tenant_id = ? ORDER BY cidade, bairro", [TENANT_ID]);
$usuarios = fetchAll("SELECT * FROM usuarios WHERE ativo = 1 AND tenant_id = ? ORDER BY nome", [TENANT_ID]);
$caracteristicas = fetchAll("SELECT * FROM caracteristicas ORDER BY nome");

// Buscar características do imóvel
$imovel_caracteristicas = fetchAll("
    SELECT caracteristica_id FROM imovel_caracteristicas WHERE imovel_id = ? AND tenant_id = ?
", [$imovel_id, TENANT_ID]);

$caracteristicas_selecionadas = array_column($imovel_caracteristicas, 'caracteristica_id');

// Buscar fotos do imóvel
$fotos_imovel = fetchAll("
    SELECT * FROM fotos_imovel WHERE imovel_id = ? AND tenant_id = ? ORDER BY ordem
", [$imovel_id, TENANT_ID]);

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Log para debug
        error_log("DEBUG: Iniciando processamento do formulário POST");
        error_log("DEBUG: Dados POST recebidos: " . print_r($_POST, true));
        
        // Validar dados obrigatórios
        $titulo = cleanInput($_POST['titulo']);
        $descricao = cleanInput($_POST['descricao']);
        
        // Converter preço do formato brasileiro para número
        $preco = convertBrazilianPriceToNumber($_POST['preco']);
        
        error_log("DEBUG: Preço original: " . $_POST['preco']);
        error_log("DEBUG: Preço convertido: " . $preco);
        
        $tipo_id = (int)$_POST['tipo_id'];
        $localizacao_id = (int)$_POST['localizacao_id'];
        
        if (empty($titulo) || empty($descricao) || $preco <= 0 || $tipo_id <= 0 || $localizacao_id <= 0) {
            throw new Exception('Todos os campos obrigatórios devem ser preenchidos.');
        }
        
        // Determinar tipo de negócio baseado nas seleções
        $tipo_negocio = 'venda'; // padrão
        if (isset($_POST['negocio_venda']) && isset($_POST['negocio_locacao'])) {
            $tipo_negocio = 'venda_locacao';
        } elseif (isset($_POST['negocio_locacao'])) {
            $tipo_negocio = 'locacao';
        }

        // Preço de locação (se aplicável)
        $preco_locacao = null;
        if (isset($_POST['negocio_locacao']) && !empty($_POST['preco_locacao'])) {
            $preco_locacao = convertBrazilianPriceToNumber($_POST['preco_locacao']);
            error_log("DEBUG: Preço de locação original: " . $_POST['preco_locacao']);
            error_log("DEBUG: Preço de locação convertido: " . $preco_locacao);
        }

        // Condições de locação (se aplicável)
        $condicoes_locacao = null;
        if (isset($_POST['negocio_locacao']) && !empty($_POST['condicoes_locacao'])) {
            $condicoes_locacao = cleanInput($_POST['condicoes_locacao']);
        }

        // Preparar dados do imóvel
        $dados_imovel = [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'preco' => $preco,
            'preco_locacao' => $preco_locacao,
            'condicoes_locacao' => $condicoes_locacao,
            'tipo_negocio' => $tipo_negocio,
            'tipo_id' => $tipo_id,
            'localizacao_id' => $localizacao_id,
            'status' => cleanInput($_POST['status']),
            'destaque' => isset($_POST['destaque']) ? 1 : 0,
            'area_total' => !empty($_POST['area_total']) ? (float)$_POST['area_total'] : null,
            'area_construida' => !empty($_POST['area_construida']) ? (float)$_POST['area_construida'] : null,
            'quartos' => !empty($_POST['quartos']) ? (int)$_POST['quartos'] : null,
            'banheiros' => !empty($_POST['banheiros']) ? (int)$_POST['banheiros'] : null,
            'vagas_garagem' => !empty($_POST['vagas_garagem']) ? (int)$_POST['vagas_garagem'] : null,
            'endereco' => cleanInput($_POST['endereco']),
            'cep' => cleanInput($_POST['cep']),
            'data_atualizacao' => date('Y-m-d H:i:s')
        ];
        
        error_log("DEBUG: Dados do imóvel preparados: " . print_r($dados_imovel, true));
        
        // Atualizar imóvel
        error_log("DEBUG: Executando UPDATE na tabela imoveis");
        error_log("DEBUG: WHERE id = " . $imovel_id);
        
        $resultado = update("imoveis", $dados_imovel, "id = ? AND tenant_id = ?", [$imovel_id, TENANT_ID]);
        
        error_log("DEBUG: Resultado do UPDATE: " . ($resultado ? 'SUCESSO' : 'FALHA'));
        
        // Verificar se realmente foi atualizado
        if ($resultado) {
            $stmt = $pdo->prepare("SELECT preco FROM imoveis WHERE id = ? AND tenant_id = ?");
            $stmt->execute([$imovel_id, TENANT_ID]);
            $preco_verificacao = $stmt->fetchColumn();
            error_log("DEBUG: Preço após UPDATE no banco: " . $preco_verificacao);
            
            if ($preco_verificacao == $preco) {
                error_log("DEBUG: ✅ SUCESSO - Preço foi atualizado corretamente no banco");
            } else {
                error_log("DEBUG: ❌ ERRO - Preço não foi atualizado no banco. Esperado: $preco, Encontrado: $preco_verificacao");
            }
        }
        
        if ($resultado) {
            // Atualizar características
            // Primeiro, remover todas as características existentes
            query("DELETE FROM imovel_caracteristicas WHERE imovel_id = ? AND tenant_id = ?", [$imovel_id, TENANT_ID]);
            
            // Inserir características selecionadas
            if (isset($_POST['caracteristicas']) && is_array($_POST['caracteristicas'])) {
                foreach ($_POST['caracteristicas'] as $caracteristica_id) {
                    insert("imovel_caracteristicas", [
                        'imovel_id' => $imovel_id,
                        'caracteristica_id' => (int)$caracteristica_id,
                        'tenant_id' => TENANT_ID
                    ]);
                }
            }
            
            // Processar exclusão de fotos
            if (isset($_POST['excluir_fotos']) && is_array($_POST['excluir_fotos'])) {
                foreach ($_POST['excluir_fotos'] as $foto_id) {
                    $foto = fetch("SELECT arquivo FROM fotos_imovel WHERE id = ? AND imovel_id = ? AND tenant_id = ?", [$foto_id, $imovel_id, TENANT_ID]);
                    if ($foto) {
                        // Excluir arquivo físico
                        $arquivo_path = "../../uploads/imoveis/{$imovel_id}/{$foto['arquivo']}";
                        if (file_exists($arquivo_path)) {
                            unlink($arquivo_path);
                        }
                        
                        // Excluir registro do banco
                        query("DELETE FROM fotos_imovel WHERE id = ? AND tenant_id = ?", [$foto_id, TENANT_ID]);
                    }
                }
                
                // SOLUÇÃO DEFINITIVA: Reordenar as fotos restantes após a exclusão
                // Esta função funciona para QUALQUER imóvel, não apenas o ID 6
                renumerarFotosAutomaticamente($imovel_id);
            }
            
            // Processar reordenação de fotos existentes
            if (isset($_POST['ordem_fotos']) && is_array($_POST['ordem_fotos'])) {
                foreach ($_POST['ordem_fotos'] as $ordem_foto) {
                    if (strpos($ordem_foto, ':') !== false) {
                        list($foto_id, $nova_ordem) = explode(':', $ordem_foto);
                        if (is_numeric($foto_id) && is_numeric($nova_ordem)) {
                            query("UPDATE fotos_imovel SET ordem = ? WHERE id = ? AND imovel_id = ? AND tenant_id = ?", 
                                  [(int)$nova_ordem, (int)$foto_id, $imovel_id, TENANT_ID]);
                        }
                    }
                }
            }
            
            // Processar upload de novas fotos
            if (isset($_FILES['novas_fotos']) && !empty($_FILES['novas_fotos']['name'][0])) {
                $upload_dir = "../../uploads/imoveis/{$imovel_id}/";
                
                // Criar diretório se não existir
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Buscar última ordem
                $ultima_ordem = fetch("SELECT MAX(ordem) as max_ordem FROM fotos_imovel WHERE imovel_id = ? AND tenant_id = ?", [$imovel_id, TENANT_ID]);
                $ordem_atual = ($ultima_ordem['max_ordem'] ?? 0) + 1;
                
                foreach ($_FILES['novas_fotos']['tmp_name'] as $key => $tmp_name) {
                    if ($_FILES['novas_fotos']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = $_FILES['novas_fotos']['name'][$key];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        // Validar extensão do arquivo
                        $allowed_extensions = getAllowedExtensions();
                        if (!in_array($ext, $allowed_extensions)) {
                            throw new Exception("Tipo de arquivo não suportado: {$ext}. Formatos aceitos: " . implode(', ', $allowed_extensions));
                        }
                        
                        // Validar tamanho do arquivo
                        if ($_FILES['novas_fotos']['size'][$key] > MAX_FILE_SIZE) {
                            $size_mb = round($_FILES['novas_fotos']['size'][$key] / (1024 * 1024), 2);
                            $max_mb = round(MAX_FILE_SIZE / (1024 * 1024), 2);
                            throw new Exception("Arquivo muito grande: {$size_mb}MB. Tamanho máximo permitido: {$max_mb}MB");
                        }
                        
                        $new_filename = uniqid() . '.' . $ext;
                        
                        if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                            insert("fotos_imovel", [
                                'imovel_id' => $imovel_id,
                                'arquivo' => $new_filename,
                                'legenda' => cleanInput($_POST['legendas_novas'][$key] ?? ''),
                                'ordem' => $ordem_atual++,
                                'tenant_id' => TENANT_ID
                            ]);
                        } else {
                            throw new Exception("Erro ao fazer upload do arquivo: {$filename}");
                        }
                    } else {
                        // Verificar erros específicos do upload
                        $error_msg = '';
                        switch ($_FILES['novas_fotos']['error'][$key]) {
                            case UPLOAD_ERR_INI_SIZE:
                            case UPLOAD_ERR_FORM_SIZE:
                                $error_msg = "Arquivo muito grande";
                                break;
                            case UPLOAD_ERR_PARTIAL:
                                $error_msg = "Upload parcial do arquivo";
                                break;
                            case UPLOAD_ERR_NO_FILE:
                                $error_msg = "Nenhum arquivo foi enviado";
                                break;
                            default:
                                $error_msg = "Erro no upload do arquivo";
                        }
                        throw new Exception("Erro no upload: {$error_msg} - {$filename}");
                    }
                }
            }
            
            // IMPORTANTE: Recarregar dados do banco para garantir sincronização
            $imovel_atualizado = fetch("
                SELECT i.*, t.nome as tipo_nome, l.cidade, l.bairro, u.nome as corretor_nome 
                FROM imoveis i 
                LEFT JOIN tipos_imovel t ON i.tipo_id = t.id AND t.tenant_id = i.tenant_id
                LEFT JOIN localizacoes l ON i.localizacao_id = l.id AND l.tenant_id = i.tenant_id
                LEFT JOIN usuarios u ON i.usuario_id = u.id AND u.tenant_id = i.tenant_id
                WHERE i.id = ? AND i.tenant_id = ?
            ", [$imovel_id, TENANT_ID]);
            
            if ($imovel_atualizado) {
                $imovel = $imovel_atualizado;
                error_log("DEBUG: Dados recarregados do banco: " . print_r($imovel, true));
            } else {
                error_log("DEBUG: ERRO ao recarregar dados do banco");
            }
            
            $caracteristicas_selecionadas = isset($_POST['caracteristicas']) ? $_POST['caracteristicas'] : [];
            
            // Buscar fotos atualizadas
            $fotos_imovel = fetchAll("
                SELECT * FROM fotos_imovel WHERE imovel_id = ? AND tenant_id = ? ORDER BY ordem
            ", [$imovel_id, TENANT_ID]);
            
            // Verificar se é apenas uma atualização de ordem
            if (isset($_POST['apenas_ordem']) && $_POST['apenas_ordem'] == '1') {
                // Apenas recarregar a página com as ordens atualizadas
                header("Location: editar.php?id={$imovel_id}&success=ordem_atualizada");
                exit;
            }
            
            // Redirecionar para o dashboard após salvar com sucesso
            header('Location: ../index.php?success=imovel_atualizado');
            exit;
        } else {
            throw new Exception('Erro ao atualizar imóvel no banco de dados.');
        }
        
    } catch (Exception $e) {
        $error_message = "Erro ao atualizar imóvel: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Imóvel - Painel Administrativo imobsites</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Admin CSS -->
    <link href="../assets/css/admin.css" rel="stylesheet">
    <!-- Dropzone CSS -->
    <link href="https://unpkg.com/dropzone@5/dist/min/dropzone.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-home me-2"></i>imobsites - Admin
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['admin_nome']); ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="../perfil.php"><i class="fas fa-user-cog me-2"></i>Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <div class="position-sticky pt-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="../index.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="index.php">
                                <i class="fas fa-home me-2"></i>Imóveis
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../usuarios/">
                                <i class="fas fa-users me-2"></i>Usuários
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../contatos/">
                                <i class="fas fa-envelope me-2"></i>Contatos
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../configuracoes/">
                                <i class="fas fa-cog me-2"></i>Configurações
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="../../" target="_blank">
                                <i class="fas fa-external-link-alt me-2"></i>Ver Site
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Conteúdo Principal -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-edit me-2"></i>Editar Imóvel
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="index.php" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Voltar
                            </a>
                            <a href="<?php echo getPagePath('imovel', ['id' => $imovel_id]); ?>" 
                               class="btn btn-sm btn-info" target="_blank">
                                <i class="fas fa-eye me-1"></i>Ver no Site
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Informações do Imóvel -->
                <div class="card shadow mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Informações do Imóvel
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>ID:</strong> <?php echo $imovel['id']; ?></p>
                                <p><strong>Título:</strong> <?php echo htmlspecialchars($imovel['titulo']); ?></p>
                                <p><strong>Tipo:</strong> <?php echo htmlspecialchars($imovel['tipo_nome']); ?></p>
                                <p><strong>Localização:</strong> <?php echo htmlspecialchars($imovel['bairro'] . ', ' . $imovel['cidade']); ?></p>
                            </div>
                            <div class="col-md-6">
                                <p><strong>Status:</strong> 
                                    <span class="badge bg-<?php echo $imovel['status'] === 'disponivel' ? 'success' : 
                                        ($imovel['status'] === 'vendido' ? 'danger' : 
                                        ($imovel['status'] === 'alugado' ? 'warning' : 'info')); ?>">
                                        <?php echo ucfirst($imovel['status']); ?>
                                    </span>
                                </p>
                                <p><strong>Destaque:</strong> 
                                    <?php echo $imovel['destaque'] ? '<i class="fas fa-star text-warning"></i> Sim' : '<i class="fas fa-star text-muted"></i> Não'; ?>
                                </p>
                                <p><strong>Corretor:</strong> <?php echo htmlspecialchars($imovel['corretor_nome']); ?></p>
                                <p><strong>Data de Criação:</strong> <?php echo date('d/m/Y H:i', strtotime($imovel['data_criacao'])); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mensagens -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_GET['success']) && $_GET['success'] === 'ordem_atualizada'): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="fas fa-sort me-2"></i>Ordem das fotos atualizada com sucesso!
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Formulário -->
                <div class="card shadow">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-edit me-2"></i>Editar Informações
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                            
                            <!-- Informações Básicas -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-info-circle me-2"></i>Informações Básicas
                                    </h6>
                                </div>
                                
                                <div class="col-md-8">
                                    <label for="titulo" class="form-label">Título *</label>
                                    <input type="text" class="form-control" id="titulo" name="titulo" 
                                           value="<?php echo htmlspecialchars($imovel['titulo']); ?>" required>
                                    <div class="invalid-feedback">Título é obrigatório</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="preco" class="form-label">Preço *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="text" class="form-control" id="preco" name="preco" 
                                               placeholder="0,00" 
                                               value="<?php echo htmlspecialchars(formatPrice($imovel['preco'])); ?>" required>
                                    </div>
                                    <div class="invalid-feedback">Preço é obrigatório</div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="tipo_id" class="form-label">Tipo de Imóvel *</label>
                                    <select class="form-select" id="tipo_id" name="tipo_id" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($tipos_imovel as $tipo): ?>
                                            <option value="<?php echo $tipo['id']; ?>" 
                                                    <?php echo $imovel['tipo_id'] == $tipo['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tipo['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Tipo de imóvel é obrigatório</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="localizacao_id" class="form-label">Localização *</label>
                                    <select class="form-select" id="localizacao_id" name="localizacao_id" required>
                                        <option value="">Selecione...</option>
                                        <?php foreach ($localizacoes as $localizacao): ?>
                                            <option value="<?php echo $localizacao['id']; ?>" 
                                                    <?php echo $imovel['localizacao_id'] == $localizacao['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($localizacao['bairro'] . ', ' . $localizacao['cidade']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Localização é obrigatória</div>
                                </div>
                            </div>

                            <!-- Tipo de Negócio -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-handshake me-2"></i>Tipo de Negócio
                                    </h6>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Disponível para:</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="negocio_venda" name="negocio_venda" value="1" 
                                               <?php echo ($imovel['tipo_negocio'] == 'venda' || $imovel['tipo_negocio'] == 'venda_locacao') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="negocio_venda">
                                            <i class="fas fa-tag text-success me-1"></i>Venda
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="negocio_locacao" name="negocio_locacao" value="1"
                                               <?php echo ($imovel['tipo_negocio'] == 'locacao' || $imovel['tipo_negocio'] == 'venda_locacao') ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="negocio_locacao">
                                            <i class="fas fa-key text-primary me-1"></i>Locação
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div id="preco_locacao_container" style="display: <?php echo ($imovel['tipo_negocio'] == 'locacao' || $imovel['tipo_negocio'] == 'venda_locacao') ? 'block' : 'none'; ?>;">
                                        <label for="preco_locacao" class="form-label">Preço de Locação (Mensal)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" class="form-control" id="preco_locacao" name="preco_locacao" 
                                                   placeholder="0,00" value="<?php echo $imovel['preco_locacao'] ? formatPrice($imovel['preco_locacao']) : ''; ?>">
                                        </div>
                                        <div class="form-text">
                                            <small class="text-muted">
                                                <i class="fas fa-info-circle"></i> 
                                                Preço mensal para locação
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Condições de Locação -->
                            <div class="row mb-4" id="condicoes_locacao_container" style="display: <?php echo ($imovel['tipo_negocio'] == 'locacao' || $imovel['tipo_negocio'] == 'venda_locacao') ? 'block' : 'none'; ?>;">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-key text-primary me-2"></i>Condições de Locação
                                    </h6>
                                </div>
                                
                                <div class="col-12">
                                    <label for="condicoes_locacao" class="form-label">Condições e Considerações para Locação</label>
                                    <textarea class="form-control" id="condicoes_locacao" name="condicoes_locacao" rows="3" 
                                              placeholder="Ex: Aceita pets, fiador, caução de 3 meses, IPTU incluído, condomínio incluído..."><?php echo htmlspecialchars($imovel['condicoes_locacao'] ?? ''); ?></textarea>
                                    <div class="form-text">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i> 
                                            Descreva condições especiais, aceitação de pets, fiador, caução, etc.
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label for="status" class="form-label">Status</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="disponivel" <?php echo $imovel['status'] == 'disponivel' ? 'selected' : ''; ?>>Disponível</option>
                                        <option value="vendido" <?php echo $imovel['status'] == 'vendido' ? 'selected' : ''; ?>>Vendido</option>
                                        <option value="alugado" <?php echo $imovel['status'] == 'alugado' ? 'selected' : ''; ?>>Alugado</option>
                                        <option value="reservado" <?php echo $imovel['status'] == 'reservado' ? 'selected' : ''; ?>>Reservado</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="destaque" name="destaque" 
                                               <?php echo $imovel['destaque'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="destaque">
                                            <i class="fas fa-star me-1"></i>Imóvel em Destaque
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Características -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-list me-2"></i>Características
                                    </h6>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="area_total" class="form-label">Área Total (m²)</label>
                                    <input type="number" class="form-control" id="area_total" name="area_total" 
                                           step="0.01" min="0" 
                                           value="<?php echo htmlspecialchars($imovel['area_total'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="area_construida" class="form-label">Área Construída (m²)</label>
                                    <input type="number" class="form-control" id="area_construida" name="area_construida" 
                                           step="0.01" min="0" 
                                           value="<?php echo htmlspecialchars($imovel['area_construida'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="quartos" class="form-label">Quartos</label>
                                    <input type="number" class="form-control" id="quartos" name="quartos" 
                                           min="0" value="<?php echo htmlspecialchars($imovel['quartos'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="banheiros" class="form-label">Banheiros</label>
                                    <input type="number" class="form-control" id="banheiros" name="banheiros" 
                                           min="0" value="<?php echo htmlspecialchars($imovel['banheiros'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="vagas_garagem" class="form-label">Vagas</label>
                                    <input type="number" class="form-control" id="vagas_garagem" name="vagas_garagem" 
                                           min="0" value="<?php echo htmlspecialchars($imovel['vagas_garagem'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Endereço -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-map-marker-alt me-2"></i>Endereço
                                    </h6>
                                </div>
                                
                                <div class="col-md-8">
                                    <label for="endereco" class="form-label">Endereço Completo</label>
                                    <input type="text" class="form-control" id="endereco" name="endereco" 
                                           value="<?php echo htmlspecialchars($imovel['endereco'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="cep" class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="cep" name="cep" 
                                           value="<?php echo htmlspecialchars($imovel['cep'] ?? ''); ?>">
                                </div>
                            </div>

                            <!-- Características Adicionais -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-tags me-2"></i>Características Adicionais
                                    </h6>
                                </div>
                                
                                <div class="col-12">
                                    <div class="row">
                                        <?php foreach ($caracteristicas as $caracteristica): ?>
                                            <div class="col-md-3 mb-2">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                           name="caracteristicas[]" 
                                                           value="<?php echo $caracteristica['id']; ?>" 
                                                           id="carac_<?php echo $caracteristica['id']; ?>"
                                                           <?php echo in_array($caracteristica['id'], $caracteristicas_selecionadas) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="carac_<?php echo $caracteristica['id']; ?>">
                                                        <?php echo htmlspecialchars($caracteristica['nome']); ?>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Descrição -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-align-left me-2"></i>Descrição
                                    </h6>
                                </div>
                                
                                <div class="col-12">
                                    <label for="descricao" class="form-label">Descrição Detalhada *</label>
                                    <textarea class="form-control" id="descricao" name="descricao" rows="6" required><?php echo htmlspecialchars($imovel['descricao']); ?></textarea>
                                    <div class="invalid-feedback">Descrição é obrigatória</div>
                                </div>
                            </div>

                            <!-- Fotos Existentes -->
                            <?php if ($fotos_imovel): ?>
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-images me-2"></i>Fotos Existentes
                                        <small class="text-muted ms-2">(Arraste para reordenar)</small>
                                    </h6>
                                </div>
                                
                                <div class="col-12">
                                    <div class="fotos-grid" id="fotosGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 15px; margin-top: 15px;">
                                        <?php foreach ($fotos_imovel as $foto): ?>
                                            <div class="foto-item" data-foto-id="<?php echo $foto['id']; ?>" data-ordem="<?php echo $foto['ordem']; ?>" style="cursor: move;">
                                                <div class="foto-card" style="border: 1px solid #ddd; border-radius: 8px; overflow: hidden; background: white;">
                                                    <div class="foto-header" style="background: #f8f9fa; padding: 8px; display: flex; justify-content: space-between; align-items: center;">
                                                        <div class="foto-actions">
                                                            <button type="button" class="btn btn-sm btn-outline-primary foto-principal" 
                                                                    data-foto-id="<?php echo $foto['id']; ?>" 
                                                                    title="Definir como foto principal"
                                                                    <?php echo ($foto['ordem'] == 1) ? 'disabled' : ''; ?>>
                                                                <i class="fas fa-star"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-outline-danger excluir-foto" 
                                                                    data-foto-id="<?php echo $foto['id']; ?>" 
                                                                    title="Excluir foto">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="foto-image" style="height: 150px; overflow: hidden;">
                                                        <img src="../../uploads/imoveis/<?php echo $imovel_id; ?>/<?php echo $foto['arquivo']; ?>" 
                                                             alt="Foto do imóvel" 
                                                             class="img-fluid" style="width: 100%; height: 100%; object-fit: cover;">
                                                    </div>
                                                    <div class="foto-footer" style="padding: 8px; background: #f8f9fa;">
                                                        <small class="text-muted"><?php echo $foto['arquivo']; ?></small>
                                                        <input type="hidden" name="ordem_fotos[]" value="<?php echo $foto['id']; ?>:<?php echo $foto['ordem']; ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <button type="button" class="btn btn-outline-success btn-sm" id="salvarOrdem">
                                            <i class="fas fa-save me-2"></i>Salvar Nova Ordem
                                        </button>
                                        <small class="text-muted ms-3">
                                            <i class="fas fa-info-circle me-1"></i>
                                            A primeira foto será exibida como foto principal
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Novas Fotos -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-plus me-2"></i>Adicionar Novas Fotos
                                    </h6>
                                </div>
                                
                                <div class="col-12">
                                    <div class="drop-zone border-2 border-dashed border-secondary rounded p-4 text-center">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-secondary mb-3"></i>
                                        <h5>Arraste e solte as novas fotos aqui</h5>
                                        <p class="text-muted">ou clique para selecionar</p>
                                        <input type="file" class="file-upload" name="novas_fotos[]" multiple accept="image/*" style="display: none;">
                                        <button type="button" class="btn btn-primary" onclick="document.querySelector('.file-upload').click()">
                                            <i class="fas fa-folder-open me-2"></i>Selecionar Fotos
                                        </button>
                                    </div>
                                    
                                    <div class="file-preview mt-3"></div>
                                    
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Formatos aceitos: JPG, PNG, GIF, WebP. Máximo 5MB por foto.
                                    </small>
                                </div>
                            </div>

                            <!-- Botões -->
                            <div class="row">
                                <div class="col-12 text-center">
                                    <button type="submit" class="btn btn-primary btn-lg me-3">
                                        <i class="fas fa-save me-2"></i>Salvar Alterações
                                    </button>
                                    <a href="index.php" class="btn btn-secondary btn-lg">
                                        <i class="fas fa-times me-2"></i>Cancelar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Admin JS -->
    <script src="../assets/js/admin.js"></script>
    <!-- Dropzone JS -->
    <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script>
    
    <script>
        // Sistema de Drag & Drop para Fotos
        class FotoManager {
            constructor() {
                this.fotosGrid = document.getElementById('fotosGrid');
                this.draggedElement = null;
                this.init();
            }
            
            init() {
                if (this.fotosGrid) {
                    this.setupDragAndDrop();
                    this.setupEventListeners();
                }
            }
            
            setupDragAndDrop() {
                const fotoItems = this.fotosGrid.querySelectorAll('.foto-item');
                
                fotoItems.forEach(item => {
                    item.setAttribute('draggable', true);
                    
                    item.addEventListener('dragstart', (e) => this.handleDragStart(e));
                    item.addEventListener('dragend', (e) => this.handleDragEnd(e));
                    item.addEventListener('dragover', (e) => this.handleDragOver(e));
                    item.addEventListener('drop', (e) => this.handleDrop(e));
                    item.addEventListener('dragenter', (e) => this.handleDragEnter(e));
                    item.addEventListener('dragleave', (e) => this.handleDragLeave(e));
                });
            }
            
            setupEventListeners() {
                // Botão salvar ordem
                const salvarOrdemBtn = document.getElementById('salvarOrdem');
                if (salvarOrdemBtn) {
                    salvarOrdemBtn.addEventListener('click', () => this.salvarNovaOrdem());
                }
                
                // Botões de ação das fotos
                this.setupFotoActions();
            }
            
            setupFotoActions() {
                // Botões de foto principal
                document.querySelectorAll('.foto-principal').forEach(btn => {
                    btn.addEventListener('click', (e) => this.definirFotoPrincipal(e));
                });
                
                // Botões de exclusão
                document.querySelectorAll('.excluir-foto').forEach(btn => {
                    btn.addEventListener('click', (e) => this.excluirFoto(e));
                });
            }
            
            handleDragStart(e) {
                this.draggedElement = e.target.closest('.foto-item');
                this.draggedElement.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/html', this.draggedElement.outerHTML);
            }
            
            handleDragEnd(e) {
                this.draggedElement.classList.remove('dragging');
                this.draggedElement = null;
                
                // Remover classes de drag over de todos os itens
                document.querySelectorAll('.foto-item').forEach(item => {
                    item.classList.remove('drag-over');
                });
            }
            
            handleDragOver(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
            }
            
            handleDragEnter(e) {
                e.preventDefault();
                const targetItem = e.target.closest('.foto-item');
                if (targetItem && targetItem !== this.draggedElement) {
                    targetItem.classList.add('drag-over');
                }
            }
            
            handleDragLeave(e) {
                const targetItem = e.target.closest('.foto-item');
                if (targetItem) {
                    targetItem.classList.remove('drag-over');
                }
            }
            
            handleDrop(e) {
                e.preventDefault();
                const targetItem = e.target.closest('.foto-item');
                
                if (targetItem && this.draggedElement && targetItem !== this.draggedElement) {
                    // Trocar posições
                    this.swapItems(this.draggedElement, targetItem);
                    this.updateOrdens();
                }
                
                // Remover classes de drag over
                document.querySelectorAll('.foto-item').forEach(item => {
                    item.classList.remove('drag-over');
                });
            }
            
            swapItems(item1, item2) {
                const parent = item1.parentNode;
                const next1 = item1.nextSibling;
                const next2 = item2.nextSibling;
                
                if (next1 === item2) {
                    parent.insertBefore(item2, item1);
                } else if (next2 === item1) {
                    parent.insertBefore(item1, item2);
                } else {
                    parent.insertBefore(item1, next2);
                    parent.insertBefore(item2, next1);
                }
            }
            
            updateOrdens() {
                const fotoItems = this.fotosGrid.querySelectorAll('.foto-item');
                fotoItems.forEach((item, index) => {
                    const ordem = index + 1;
                    item.setAttribute('data-ordem', ordem);
                    
                    // Atualizar input hidden
                    const input = item.querySelector('input[name="ordem_fotos[]"]');
                    if (input) {
                        const fotoId = item.getAttribute('data-foto-id');
                        input.value = `${fotoId}:${ordem}`;
                    }
                    
                    // Atualizar botão de foto principal
                    const btnPrincipal = item.querySelector('.foto-principal');
                    if (btnPrincipal) {
                        if (ordem === 1) {
                            btnPrincipal.disabled = true;
                            btnPrincipal.classList.add('btn-success');
                            btnPrincipal.classList.remove('btn-outline-primary');
                            btnPrincipal.title = 'Esta é a foto principal';
                        } else {
                            btnPrincipal.disabled = false;
                            btnPrincipal.classList.remove('btn-success');
                            btnPrincipal.classList.add('btn-outline-primary');
                            btnPrincipal.title = 'Definir como foto principal';
                        }
                    }
                    
                    // Adicionar classe de reordenação
                    item.classList.add('reordering');
                    setTimeout(() => item.classList.remove('reordering'), 1000);
                });
            }
            
            definirFotoPrincipal(e) {
                const btn = e.target.closest('.foto-principal');
                const fotoItem = btn.closest('.foto-item');
                const fotoId = fotoItem.getAttribute('data-foto-id');
                
                // Mover para primeira posição
                const firstItem = this.fotosGrid.querySelector('.foto-item');
                if (firstItem !== fotoItem) {
                    this.fotosGrid.insertBefore(fotoItem, firstItem);
                    this.updateOrdens();
                }
                
                // Mostrar feedback
                this.showNotification('Foto definida como foto principal!', 'success');
            }
            
            excluirFoto(e) {
                const btn = e.target.closest('.excluir-foto');
                const fotoItem = btn.closest('.foto-item');
                const fotoId = fotoItem.getAttribute('data-foto-id');
                
                if (confirm('Tem certeza que deseja excluir esta foto?')) {
                    // Adicionar input hidden para exclusão
                    const form = document.querySelector('form');
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'excluir_fotos[]';
                    input.value = fotoId;
                    form.appendChild(input);
                    
                    // Remover visualmente
                    fotoItem.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => {
                        fotoItem.remove();
                        this.updateOrdens();
                    }, 300);
                    
                    this.showNotification('Foto removida!', 'success');
                }
            }
            
            salvarNovaOrdem() {
                const fotoItems = this.fotosGrid.querySelectorAll('.foto-item');
                const novaOrdem = [];
                
                fotoItems.forEach((item, index) => {
                    const fotoId = item.getAttribute('data-foto-id');
                    const ordem = index + 1;
                    novaOrdem.push({ id: fotoId, ordem: ordem });
                });
                
                // Atualizar inputs hidden do formulário
                this.updateFormInputs(novaOrdem);
                
                // Mostrar notificação
                this.showNotification('Ordem salva! As alterações serão aplicadas quando você salvar o imóvel.', 'success');
                
                // Opcional: Submeter o formulário automaticamente após um pequeno delay
                setTimeout(() => {
                    const form = document.querySelector('form');
                    if (form) {
                        // Criar um input hidden temporário para indicar que é apenas uma atualização de ordem
                        const inputTemp = document.createElement('input');
                        inputTemp.type = 'hidden';
                        inputTemp.name = 'apenas_ordem';
                        inputTemp.value = '1';
                        form.appendChild(inputTemp);
                        
                        // Submeter o formulário
                        form.submit();
                    }
                }, 1500);
            }
            
            updateFormInputs(novaOrdem) {
                // Remover inputs antigos
                const inputsAntigos = document.querySelectorAll('input[name="ordem_fotos[]"]');
                inputsAntigos.forEach(input => input.remove());
                
                // Adicionar novos inputs
                const form = document.querySelector('form');
                novaOrdem.forEach(({ id, ordem }) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ordem_fotos[]';
                    input.value = `${id}:${ordem}`;
                    form.appendChild(input);
                });
            }
            
            showNotification(message, type = 'info') {
                // Criar notificação simples
                const notification = document.createElement('div');
                notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
                notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
                notification.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.body.appendChild(notification);
                
                // Auto-remover após 3 segundos
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 3000);
            }
        }
        
        // Configuração específica para esta página
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar gerenciador de fotos
            if (document.getElementById('fotosGrid')) {
                new FotoManager();
            }
            
            // Máscara para CEP
            const cepInput = document.getElementById('cep');
            if (cepInput) {
                cepInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    value = value.replace(/^(\d{5})(\d)/, '$1-$2');
                    e.target.value = value;
                });
            }
            
            // Controle dos campos de locação
            const negocioLocacaoCheckbox = document.getElementById('negocio_locacao');
            const precoLocacaoContainer = document.getElementById('preco_locacao_container');
            const condicoesLocacaoContainer = document.getElementById('condicoes_locacao_container');
            const precoLocacaoInput = document.getElementById('preco_locacao');
            const condicoesLocacaoInput = document.getElementById('condicoes_locacao');

            if (negocioLocacaoCheckbox && precoLocacaoContainer && condicoesLocacaoContainer) {
                negocioLocacaoCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        precoLocacaoContainer.style.display = 'block';
                        condicoesLocacaoContainer.style.display = 'block';
                        precoLocacaoInput.required = true;
                    } else {
                        precoLocacaoContainer.style.display = 'none';
                        condicoesLocacaoContainer.style.display = 'none';
                        precoLocacaoInput.required = false;
                        precoLocacaoInput.value = '';
                        condicoesLocacaoInput.value = '';
                    }
                });
            }

            // Máscara e formatação para preço de locação (mesma lógica do preço de venda)
            if (precoLocacaoInput) {
                // Aplicar máscara de formatação brasileira
                precoLocacaoInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    
                    if (value.length > 0) {
                        // Converter para número e formatar
                        const numericValue = parseInt(value);
                        const formattedValue = (numericValue / 100).toFixed(2);
                        const formattedPrice = formattedValue.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                        e.target.value = formattedPrice;
                    }
                });

                // Formatar ao perder o foco (blur)
                precoLocacaoInput.addEventListener('blur', function() {
                    if (this.value) {
                        let value = this.value.replace(/\D/g, '');
                        if (value) {
                            const numericValue = parseInt(value);
                            const formattedValue = (numericValue / 100).toFixed(2);
                            const formattedPrice = formattedValue.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                            this.value = formattedPrice;
                        }
                    }
                });

                // Formatar ao ganhar o foco (focus)
                precoLocacaoInput.addEventListener('focus', function() {
                    if (this.value) {
                        // Converter formato brasileiro para número simples para edição
                        let value = this.value.replace(/\./g, '').replace(',', '.');
                        this.value = value;
                    }
                });
            }

            // Validação de preço
            const precoInput = document.getElementById('preco');
            if (precoInput) {
                precoInput.addEventListener('blur', function() {
                    if (this.value && parseFloat(this.value) <= 0) {
                        this.setCustomValidity('Preço deve ser maior que zero');
                    } else {
                        this.setCustomValidity('');
                    }
                });
                
                // Converter preço formatado antes de enviar o formulário
                const form = document.querySelector('form');
                form.addEventListener('submit', function(e) {
                    if (precoInput.value) {
                        // Converter o preço formatado para número antes de enviar
                        const numericValue = window.AdminPanel.convertFormattedPriceToNumber(precoInput.value);
                        precoInput.value = numericValue;
                        
                        // Log para debug
                        console.log('DEBUG: Preço original:', precoInput.value);
                        console.log('DEBUG: Preço convertido:', numericValue);
                    }
                });
            }
        });
    </script>
</body>
</html>
