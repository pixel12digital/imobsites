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

$success_message = '';
$error_message = '';

    // Verificar se as funções estão carregadas
    error_log("DEBUG LOAD: Verificando carregamento das funções...");
    error_log("DEBUG LOAD: fetchAll existe? " . (function_exists('fetchAll') ? 'SIM' : 'NÃO'));
    error_log("DEBUG LOAD: insert existe? " . (function_exists('insert') ? 'SIM' : 'NÃO'));
    error_log("DEBUG LOAD: cleanInput existe? " . (function_exists('cleanInput') ? 'SIM' : 'NÃO'));

// Buscar dados para os selects
$tipos_imovel = fetchAll("SELECT * FROM tipos_imovel WHERE tenant_id = ? ORDER BY nome", [TENANT_ID]);
$localizacoes = fetchAll("SELECT * FROM localizacoes WHERE tenant_id = ? ORDER BY estado, cidade, bairro", [TENANT_ID]);
$usuarios = fetchAll("SELECT * FROM usuarios WHERE ativo = 1 AND tenant_id = ? ORDER BY nome", [TENANT_ID]);
$caracteristicas = fetchAll("SELECT * FROM caracteristicas ORDER BY nome");

// Array de estados brasileiros para cadastro rápido
$estados_brasil = [
    'AC' => 'Acre', 'AL' => 'Alagoas', 'AP' => 'Amapá', 'AM' => 'Amazonas',
    'BA' => 'Bahia', 'CE' => 'Ceará', 'DF' => 'Distrito Federal', 'ES' => 'Espírito Santo',
    'GO' => 'Goiás', 'MA' => 'Maranhão', 'MT' => 'Mato Grosso', 'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais', 'PA' => 'Pará', 'PB' => 'Paraíba', 'PR' => 'Paraná',
    'PE' => 'Pernambuco', 'PI' => 'Piauí', 'RJ' => 'Rio de Janeiro', 'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul', 'RO' => 'Rondônia', 'RR' => 'Roraima', 'SC' => 'Santa Catarina',
    'SP' => 'São Paulo', 'SE' => 'Sergipe', 'TO' => 'Tocantins'
];

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("DEBUG FORM: ==========================================");
    error_log("DEBUG FORM: Formulário POST recebido");
    error_log("DEBUG FORM: POST data: " . print_r($_POST, true));
    error_log("DEBUG FORM: FILES data: " . print_r($_FILES, true));
    error_log("DEBUG FORM: ==========================================");
    
            // Debug adicional para verificar se os dados estão chegando
        error_log("DEBUG FORM: Verificando campos específicos:");
        error_log("DEBUG FORM: Título: " . ($_POST['titulo'] ?? 'NÃO DEFINIDO'));
        error_log("DEBUG FORM: Descrição: " . ($_POST['descricao'] ?? 'NÃO DEFINIDO'));
        error_log("DEBUG FORM: Preço: " . ($_POST['preco'] ?? 'NÃO DEFINIDO'));
        error_log("DEBUG FORM: Tipo ID: " . ($_POST['tipo_id'] ?? 'NÃO DEFINIDO'));
        error_log("DEBUG FORM: Localização ID: " . ($_POST['localizacao_id'] ?? 'NÃO DEFINIDO'));
        error_log("DEBUG FORM: ==========================================");
        
                // Verificar se chegou até aqui
        error_log("DEBUG FORM: Iniciando processamento dos dados...");
        
        // Verificar se as funções necessárias existem
        error_log("DEBUG FORM: Verificando funções...");
        error_log("DEBUG FORM: cleanInput existe? " . (function_exists('cleanInput') ? 'SIM' : 'NÃO'));
        error_log("DEBUG FORM: insert existe? " . (function_exists('insert') ? 'SIM' : 'NÃO'));
        
        try {
            error_log("DEBUG FORM: Iniciando try/catch...");
            
            // Validar dados obrigatórios
            error_log("DEBUG FORM: Validando dados obrigatórios...");
            $titulo = cleanInput($_POST['titulo']);
            $descricao = cleanInput($_POST['descricao']);
            
            // Converter preço do formato brasileiro para número
            $preco = convertBrazilianPriceToNumber($_POST['preco']);
            
            $tipo_id = (int)$_POST['tipo_id'];
            $localizacao_id = (int)$_POST['localizacao_id'];
            
            error_log("DEBUG FORM: Dados validados - Título: {$titulo}, Preço: {$preco}, Tipo: {$tipo_id}, Localização: {$localizacao_id}");
        
        if (empty($titulo) || empty($descricao) || $preco <= 0 || $tipo_id <= 0 || $localizacao_id <= 0) {
            error_log("DEBUG FORM: Campos obrigatórios não preenchidos");
            throw new Exception('Todos os campos obrigatórios devem ser preenchidos.');
        }
        
        // Debug: Verificar sessão
        error_log("DEBUG SESSION: admin_id = " . ($_SESSION['admin_id'] ?? 'NÃO DEFINIDO'));
        error_log("DEBUG SESSION: admin_nome = " . ($_SESSION['admin_nome'] ?? 'NÃO DEFINIDO'));
        
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
            'usuario_id' => $_SESSION['admin_id'] ?? 1, // Fallback para usuário 1 se não houver sessão
            'status' => cleanInput($_POST['status']),
            'destaque' => isset($_POST['destaque']) ? 1 : 0,
            'area_total' => !empty($_POST['area_total']) ? (float)$_POST['area_total'] : null,
            'area_construida' => !empty($_POST['area_construida']) ? (float)$_POST['area_construida'] : null,
            'quartos' => !empty($_POST['quartos']) ? (int)$_POST['quartos'] : null,
            'banheiros' => !empty($_POST['banheiros']) ? (int)$_POST['banheiros'] : null,
            'vagas' => !empty($_POST['vagas_garagem']) ? (int)$_POST['vagas_garagem'] : null,
            'endereco' => cleanInput($_POST['endereco']),
            'data_criacao' => date('Y-m-d H:i:s'),
            'tenant_id' => TENANT_ID
        ];
        
        // Inserir imóvel
        error_log("DEBUG FORM: Inserindo imóvel no banco...");
        error_log("DEBUG FORM: Dados para inserção: " . print_r($dados_imovel, true));
        
        error_log("DEBUG FORM: Chamando função insert...");
        error_log("DEBUG FORM: Tabela: imoveis");
        error_log("DEBUG FORM: Dados: " . print_r($dados_imovel, true));
        
        error_log("DEBUG FORM: Função insert encontrada, executando...");
        $imovel_id = insert("imoveis", $dados_imovel);
        error_log("DEBUG FORM: Função insert executada");
        error_log("DEBUG FORM: Resultado do insert: " . ($imovel_id ? "SUCESSO - ID: {$imovel_id}" : "FALHA - Retornou: " . var_export($imovel_id, true)));
        
        if ($imovel_id) {
            error_log("DEBUG FORM: Imóvel inserido com sucesso! ID: " . $imovel_id);
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
            
            // Processar upload de fotos
            error_log("DEBUG FORM: Verificando se há fotos para upload...");
            error_log("DEBUG FORM: isset(\$_FILES['fotos']): " . (isset($_FILES['fotos']) ? 'SIM' : 'NÃO'));
            error_log("DEBUG FORM: !empty(\$_FILES['fotos']['name'][0]): " . (!empty($_FILES['fotos']['name'][0]) ? 'SIM' : 'NÃO'));
            
            if (isset($_FILES['fotos']) && !empty($_FILES['fotos']['name'][0])) {
                
                // DEBUG: Log das informações de upload
                error_log("DEBUG UPLOAD: Iniciando processamento de fotos");
                error_log("DEBUG UPLOAD: FILES array: " . print_r($_FILES, true));
                
                $upload_dir = '../../uploads/imoveis/' . $imovel_id . '/';
                error_log("DEBUG UPLOAD: Diretório de upload: " . $upload_dir);
                
                // Criar diretório se não existir
                if (!is_dir($upload_dir)) {
                    $created = mkdir($upload_dir, 0755, true);
                    error_log("DEBUG UPLOAD: Diretório criado: " . ($created ? 'SIM' : 'NÃO'));
                } else {
                    error_log("DEBUG UPLOAD: Diretório já existe");
                }
                
                foreach ($_FILES['fotos']['tmp_name'] as $key => $tmp_name) {
                    error_log("DEBUG UPLOAD: Processando arquivo {$key}");
                    error_log("DEBUG UPLOAD: Nome: " . $_FILES['fotos']['name'][$key]);
                    error_log("DEBUG UPLOAD: Tamanho: " . $_FILES['fotos']['size'][$key]);
                    error_log("DEBUG UPLOAD: Erro: " . $_FILES['fotos']['error'][$key]);
                    error_log("DEBUG UPLOAD: Tipo: " . $_FILES['fotos']['type'][$key]);
                    error_log("DEBUG UPLOAD: Temp: " . $tmp_name);
                    
                    if ($_FILES['fotos']['error'][$key] === UPLOAD_ERR_OK) {
                        $filename = $_FILES['fotos']['name'][$key];
                        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                        
                        error_log("DEBUG UPLOAD: Extensão detectada: " . $ext);
                        
                        // Validar extensão do arquivo
                        $allowed_extensions = getAllowedExtensions();
                        error_log("DEBUG UPLOAD: Extensões permitidas: " . implode(', ', $allowed_extensions));
                        
                        if (!in_array($ext, $allowed_extensions)) {
                            error_log("DEBUG UPLOAD: Extensão inválida: " . $ext);
                            throw new Exception("Tipo de arquivo não suportado: {$ext}. Formatos aceitos: " . implode(', ', $allowed_extensions));
                        }
                        
                        // Validar tamanho do arquivo
                        error_log("DEBUG UPLOAD: Tamanho do arquivo: " . $_FILES['fotos']['size'][$key] . " bytes");
                        error_log("DEBUG UPLOAD: Tamanho máximo permitido: " . MAX_FILE_SIZE . " bytes");
                        
                        if ($_FILES['fotos']['size'][$key] > MAX_FILE_SIZE) {
                            $size_mb = round($_FILES['fotos']['size'][$key] / (1024 * 1024), 2);
                            $max_mb = round(MAX_FILE_SIZE / (1024 * 1024), 2);
                            error_log("DEBUG UPLOAD: Arquivo muito grande: {$size_mb}MB > {$max_mb}MB");
                            throw new Exception("Arquivo muito grande: {$size_mb}MB. Tamanho máximo permitido: {$max_mb}MB");
                        }
                        
                        error_log("DEBUG UPLOAD: Tamanho válido, prosseguindo...");
                        $new_filename = uniqid() . '.' . $ext;
                        error_log("DEBUG UPLOAD: Novo nome do arquivo: " . $new_filename);
                        error_log("DEBUG UPLOAD: Caminho completo: " . $upload_dir . $new_filename);
                        
                        if (move_uploaded_file($tmp_name, $upload_dir . $new_filename)) {
                            error_log("DEBUG UPLOAD: Arquivo movido com sucesso!");
                            
                            // Inserir no banco de dados
                            $foto_data = [
                                'imovel_id' => $imovel_id,
                                'arquivo' => $new_filename,
                                'legenda' => cleanInput($_POST['legendas'][$key] ?? ''),
                                'ordem' => $key + 1,
                                'tenant_id' => TENANT_ID
                            ];
                            error_log("DEBUG UPLOAD: Dados para inserção: " . print_r($foto_data, true));
                            
                            $foto_id = insert("fotos_imovel", $foto_data);
                            if ($foto_id) {
                                error_log("DEBUG UPLOAD: Foto inserida no banco com ID: " . $foto_id);
                            } else {
                                error_log("DEBUG UPLOAD: ERRO ao inserir foto no banco");
                            }
                        } else {
                            error_log("DEBUG UPLOAD: ERRO ao mover arquivo: " . error_get_last()['message'] ?? 'Erro desconhecido');
                            throw new Exception("Erro ao fazer upload do arquivo: {$filename}");
                        }
                    } else {
                        error_log("DEBUG UPLOAD: Erro detectado no arquivo {$key}: " . $_FILES['fotos']['error'][$key]);
                        
                        // Verificar erros específicos do upload
                        $error_msg = '';
                        switch ($_FILES['fotos']['error'][$key]) {
                            case UPLOAD_ERR_INI_SIZE:
                                $error_msg = "Arquivo excede upload_max_filesize";
                                break;
                            case UPLOAD_ERR_FORM_SIZE:
                                $error_msg = "Arquivo excede MAX_FILE_SIZE";
                                break;
                            case UPLOAD_ERR_PARTIAL:
                                $error_msg = "Upload parcial do arquivo";
                                break;
                            case UPLOAD_ERR_NO_FILE:
                                $error_msg = "Nenhum arquivo foi enviado";
                                break;
                            case UPLOAD_ERR_NO_TMP_DIR:
                                $error_msg = "Diretório temporário não encontrado";
                                break;
                            case UPLOAD_ERR_CANT_WRITE:
                                $error_msg = "Falha ao escrever no disco";
                                break;
                            case UPLOAD_ERR_EXTENSION:
                                $error_msg = "Upload parado por extensão";
                                break;
                            default:
                                $error_msg = "Erro no upload do arquivo (código: " . $_FILES['fotos']['error'][$key] . ")";
                        }
                        
                        error_log("DEBUG UPLOAD: Mensagem de erro: " . $error_msg);
                        throw new Exception("Erro no upload: {$error_msg} - {$filename}");
                    }
                }
            }
            
            $success_message = "Imóvel cadastrado com sucesso! ID: " . $imovel_id . " - Redirecionando para o dashboard...";
            
            // Limpar formulário
            $_POST = array();
            
            // Redirecionar para o dashboard usando header() - mais confiável
            ob_end_clean(); // Limpar buffer antes do redirecionamento
            header('Location: ../index.php?success=imovel_cadastrado&id=' . $imovel_id);
            exit();
        } else {
            throw new Exception('Erro ao inserir imóvel no banco de dados.');
        }
        
    } catch (Exception $e) {
        $error_message = "Erro ao cadastrar imóvel: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Imóvel - Painel Administrativo JTR Imóveis</title>
    
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
                <i class="fas fa-home me-2"></i>JTR Imóveis - Admin
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
                        <i class="fas fa-plus me-2"></i>Adicionar Novo Imóvel
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="index.php" class="btn btn-sm btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i>Voltar
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Mensagens -->
                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                        <div class="mt-2">
                            <a href="../index.php" class="btn btn-success btn-sm me-2">
                                <i class="fas fa-tachometer-alt me-1"></i>Ir para Dashboard
                            </a>
                            <a href="index.php" class="btn btn-primary btn-sm me-2">
                                <i class="fas fa-home me-1"></i>Ver Imóveis
                            </a>
                            <a href="adicionar.php" class="btn btn-info btn-sm">
                                <i class="fas fa-plus me-1"></i>Adicionar Outro
                            </a>
                        </div>
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
                            <i class="fas fa-home me-2"></i>Informações do Imóvel
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
                                           value="<?php echo htmlspecialchars($_POST['titulo'] ?? ''); ?>" required>
                                    <div class="invalid-feedback">Título é obrigatório</div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="preco" class="form-label">Preço *</label>
                                    <div class="input-group">
                                        <span class="input-group-text">R$</span>
                                        <input type="text" class="form-control" id="preco" name="preco" 
                                               placeholder="0,00" 
                                               value="<?php echo htmlspecialchars($_POST['preco'] ?? ''); ?>" required>
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
                                                    <?php echo (isset($_POST['tipo_id']) && $_POST['tipo_id'] == $tipo['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($tipo['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Tipo de imóvel é obrigatório</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="localizacao_id" class="form-label">Localização *</label>
                                    <div class="input-group">
                                        <select class="form-select" id="localizacao_id" name="localizacao_id" required>
                                            <option value="">Selecione...</option>
                                            <?php foreach ($localizacoes as $localizacao): ?>
                                                <option value="<?php echo $localizacao['id']; ?>" 
                                                        <?php echo (isset($_POST['localizacao_id']) && $_POST['localizacao_id'] == $localizacao['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($localizacao['cidade'] . ' - ' . ($localizacao['bairro'] ?: 'Centro') . ', ' . $localizacao['estado']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#novaLocalizacaoModal">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i> 
                                            Não encontrou a localização? 
                                            <a href="#" data-bs-toggle="modal" data-bs-target="#novaLocalizacaoModal">Clique aqui para cadastrar</a>
                                        </small>
                                    </div>
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
                                        <input class="form-check-input" type="checkbox" id="negocio_venda" name="negocio_venda" value="1" checked>
                                        <label class="form-check-label" for="negocio_venda">
                                            <i class="fas fa-tag text-success me-1"></i>Venda
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="negocio_locacao" name="negocio_locacao" value="1">
                                        <label class="form-check-label" for="negocio_locacao">
                                            <i class="fas fa-key text-primary me-1"></i>Locação
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div id="preco_locacao_container" style="display: none;">
                                        <label for="preco_locacao" class="form-label">Preço de Locação (Mensal)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">R$</span>
                                            <input type="text" class="form-control" id="preco_locacao" name="preco_locacao" 
                                                   placeholder="0,00">
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
                            <div class="row mb-4" id="condicoes_locacao_container" style="display: none;">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-key text-primary me-2"></i>Condições de Locação
                                    </h6>
                                </div>
                                
                                <div class="col-12">
                                    <label for="condicoes_locacao" class="form-label">Condições e Considerações para Locação</label>
                                    <textarea class="form-control" id="condicoes_locacao" name="condicoes_locacao" rows="3" 
                                              placeholder="Ex: Aceita pets, fiador, caução de 3 meses, IPTU incluído, condomínio incluído..."><?php echo htmlspecialchars($_POST['condicoes_locacao'] ?? ''); ?></textarea>
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
                                        <option value="disponivel" <?php echo (isset($_POST['status']) && $_POST['status'] == 'disponivel') ? 'selected' : ''; ?>>Disponível</option>
                                        <option value="vendido" <?php echo (isset($_POST['status']) && $_POST['status'] == 'vendido') ? 'selected' : ''; ?>>Vendido</option>
                                        <option value="alugado" <?php echo (isset($_POST['status']) && $_POST['status'] == 'alugado') ? 'selected' : ''; ?>>Alugado</option>
                                        <option value="reservado" <?php echo (isset($_POST['status']) && $_POST['status'] == 'reservado') ? 'selected' : ''; ?>>Reservado</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="destaque" name="destaque" 
                                               <?php echo (isset($_POST['destaque']) && $_POST['destaque']) ? 'checked' : ''; ?>>
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
                                           value="<?php echo htmlspecialchars($_POST['area_total'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="area_construida" class="form-label">Área Construída (m²)</label>
                                    <input type="number" class="form-control" id="area_construida" name="area_construida" 
                                           step="0.01" min="0" 
                                           value="<?php echo htmlspecialchars($_POST['area_construida'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="quartos" class="form-label">Quartos</label>
                                    <input type="number" class="form-control" id="quartos" name="quartos" 
                                           min="0" value="<?php echo htmlspecialchars($_POST['quartos'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="banheiros" class="form-label">Banheiros</label>
                                    <input type="number" class="form-control" id="banheiros" name="banheiros" 
                                           min="0" value="<?php echo htmlspecialchars($_POST['banheiros'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="vagas_garagem" class="form-label">Vagas</label>
                                    <input type="number" class="form-control" id="vagas_garagem" name="vagas_garagem" 
                                           min="0" value="<?php echo htmlspecialchars($_POST['vagas_garagem'] ?? ''); ?>">
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
                                           value="<?php echo htmlspecialchars($_POST['endereco'] ?? ''); ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="cep" class="form-label">CEP</label>
                                    <input type="text" class="form-control" id="cep" name="cep" 
                                           value="<?php echo htmlspecialchars($_POST['cep'] ?? ''); ?>">
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
                                                           <?php echo (isset($_POST['caracteristicas']) && in_array($caracteristica['id'], $_POST['caracteristicas'])) ? 'checked' : ''; ?>>
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
                                    <textarea class="form-control" id="descricao" name="descricao" rows="6" required><?php echo htmlspecialchars($_POST['descricao'] ?? ''); ?></textarea>
                                    <div class="invalid-feedback">Descrição é obrigatória</div>
                                </div>
                            </div>

                            <!-- Fotos -->
                            <div class="row mb-4">
                                <div class="col-12">
                                    <h6 class="text-primary mb-3">
                                        <i class="fas fa-images me-2"></i>Fotos do Imóvel
                                    </h6>
                                </div>
                                
                                <div class="col-12">
                                    <div class="drop-zone border-2 border-dashed border-secondary rounded p-4 text-center">
                                        <i class="fas fa-cloud-upload-alt fa-3x text-secondary mb-3"></i>
                                        <h5>Arraste e solte as fotos aqui</h5>
                                        <p class="text-muted">ou clique para selecionar</p>
                                        <input type="file" class="file-upload" name="fotos[]" multiple accept="image/*" style="display: none;">
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
                                    <button type="submit" class="btn btn-primary btn-lg me-3" id="btnSubmit">
                                        <i class="fas fa-save me-2"></i>Cadastrar Imóvel
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

    <!-- Modal Nova Localização -->
    <div class="modal fade" id="novaLocalizacaoModal" tabindex="-1" aria-labelledby="novaLocalizacaoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="novaLocalizacaoModalLabel">
                        <i class="fas fa-map-marker-alt text-primary"></i> Nova Localização
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="formNovaLocalizacao">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nova_cidade" class="form-label">
                                    <i class="fas fa-city"></i> Cidade *
                                </label>
                                <input type="text" class="form-control" id="nova_cidade" name="cidade" required placeholder="Ex: São Paulo">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="nova_estado" class="form-label">
                                    <i class="fas fa-flag"></i> Estado *
                                </label>
                                <select class="form-select" id="nova_estado" name="estado" required>
                                    <option value="">Selecione o estado...</option>
                                    <?php foreach ($estados_brasil as $sigla => $nome): ?>
                                        <option value="<?php echo $sigla; ?>"><?php echo $sigla; ?> - <?php echo $nome; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nova_bairro" class="form-label">
                                    <i class="fas fa-map"></i> Bairro
                                </label>
                                <input type="text" class="form-control" id="nova_bairro" name="bairro" placeholder="Ex: Jardins, Centro, Vila Madalena">
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="nova_cep" class="form-label">
                                    <i class="fas fa-mail-bulk"></i> CEP
                                </label>
                                <input type="text" class="form-control" id="nova_cep" name="cep" placeholder="00000-000" maxlength="9">
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb"></i> 
                            <strong>Dica:</strong> Cadastre apenas localizações que realmente serão utilizadas pela imobiliária.
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <button type="button" class="btn btn-primary" id="btnSalvarLocalizacao">
                        <i class="fas fa-save"></i> Salvar Localização
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Admin JS -->
    <script src="../assets/js/admin.js"></script>
    <!-- Dropzone JS - COMENTADO PARA TESTE -->
    <!-- <script src="https://unpkg.com/dropzone@5/dist/min/dropzone.min.js"></script> -->
    

    
    <script>
        // Configuração específica para esta página
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar se o admin.js foi carregado
            if (typeof isValidFileType === 'function') {
                // Configurar upload de arquivos
                if (typeof setupFileUploads === 'function') {
                    setupFileUploads();
                }
                
                // Configuração do formulário
                const form = document.querySelector('form');
                const btnSubmit = document.getElementById('btnSubmit');
                
                if (form && btnSubmit) {
                    // Validação do formulário
                    form.addEventListener('submit', function(e) {
                        // Verificar se todos os campos obrigatórios estão preenchidos
                        const requiredFields = form.querySelectorAll('[required]');
                        let allRequiredFilled = true;
                        
                        requiredFields.forEach(field => {
                            if (!field.value.trim()) {
                                console.error('Campo obrigatório vazio:', field.name);
                                allRequiredFilled = false;
                            }
                        });
                        
                        if (!allRequiredFilled) {
                            console.error('Formulário não pode ser enviado - campos obrigatórios vazios');
                            e.preventDefault();
                            return;
                        }
                    });
                }
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
                        if (value.length > 0) {
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

            // Máscara para CEP
            const cepInput = document.getElementById('cep');
            if (cepInput) {
                cepInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    value = value.replace(/^(\d{5})(\d)/, '$1-$2');
                    e.target.value = value;
                });
            }
            
            // Máscara para CEP da nova localização
            const novaCepInput = document.getElementById('nova_cep');
            if (novaCepInput) {
                novaCepInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 5) {
                        value = value.substring(0, 5) + '-' + value.substring(5, 8);
                    }
                    e.target.value = value;
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
                    }
                });
            }
            
            // Salvar nova localização
            document.getElementById('btnSalvarLocalizacao').addEventListener('click', function() {
                const form = document.getElementById('formNovaLocalizacao');
                const formData = new FormData(form);
                
                // Validar campos obrigatórios
                const cidade = formData.get('cidade').trim();
                const estado = formData.get('estado');
                
                if (!cidade || !estado) {
                    alert('Por favor, preencha cidade e estado.');
                    return;
                }
                
                // Enviar via AJAX
                fetch('salvar_localizacao_ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Adicionar nova opção ao select
                        const select = document.getElementById('localizacao_id');
                        const option = document.createElement('option');
                        option.value = data.localizacao_id;
                        option.text = `${cidade} - ${formData.get('bairro') || 'Centro'}, ${estado}`;
                        option.selected = true;
                        
                        select.appendChild(option);
                        
                        // Fechar modal e limpar formulário
                        const modal = bootstrap.Modal.getInstance(document.getElementById('novaLocalizacaoModal'));
                        modal.hide();
                        form.reset();
                        
                        // Mostrar mensagem de sucesso
                        alert('Localização cadastrada com sucesso!');
                    } else {
                        alert('Erro ao cadastrar localização: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    alert('Erro ao cadastrar localização. Tente novamente.');
                });
            });
        });
    </script>
</body>
</html>
