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

// Verificar se foi passado um ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$localizacao_id = (int)$_GET['id'];

// Array de estados brasileiros
$estados_brasil = [
    'AC' => 'Acre',
    'AL' => 'Alagoas',
    'AP' => 'Amapá',
    'AM' => 'Amazonas',
    'BA' => 'Bahia',
    'CE' => 'Ceará',
    'DF' => 'Distrito Federal',
    'ES' => 'Espírito Santo',
    'GO' => 'Goiás',
    'MA' => 'Maranhão',
    'MT' => 'Mato Grosso',
    'MS' => 'Mato Grosso do Sul',
    'MG' => 'Minas Gerais',
    'PA' => 'Pará',
    'PB' => 'Paraíba',
    'PR' => 'Paraná',
    'PE' => 'Pernambuco',
    'PI' => 'Piauí',
    'RJ' => 'Rio de Janeiro',
    'RN' => 'Rio Grande do Norte',
    'RS' => 'Rio Grande do Sul',
    'RO' => 'Rondônia',
    'RR' => 'Roraima',
    'SC' => 'Santa Catarina',
    'SP' => 'São Paulo',
    'SE' => 'Sergipe',
    'TO' => 'Tocantins'
];

// Buscar dados da localização
$localizacao = fetch("SELECT * FROM localizacoes WHERE id = ? AND tenant_id = ?", [$localizacao_id, TENANT_ID]);

if (!$localizacao) {
    header('Location: index.php');
    exit;
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar dados obrigatórios
        $cidade = cleanInput($_POST['cidade']);
        $estado = cleanInput($_POST['estado']);
        $bairro = !empty($_POST['bairro']) ? cleanInput($_POST['bairro']) : null;
        $cep = !empty($_POST['cep']) ? cleanInput($_POST['cep']) : null;
        
        // Validações
        if (empty($cidade)) {
            throw new Exception('Cidade é obrigatória.');
        }
        
        if (empty($estado) || !array_key_exists($estado, $estados_brasil)) {
            throw new Exception('Estado é obrigatório e deve ser válido.');
        }
        
        // Validar CEP se fornecido
        if ($cep && !preg_match('/^\d{5}-?\d{3}$/', $cep)) {
            throw new Exception('CEP deve estar no formato 00000-000 ou 00000000.');
        }
        
        // Verificar se já existe uma localização com cidade + bairro + estado (excluindo a atual)
        $existing_location = fetch(
            "SELECT id FROM localizacoes WHERE cidade = ? AND bairro = ? AND estado = ? AND id != ? AND tenant_id = ?", 
            [$cidade, $bairro, $estado, $localizacao_id, TENANT_ID]
        );
        
        if ($existing_location) {
            throw new Exception('Já existe uma localização com esta cidade, bairro e estado.');
        }
        
        // Preparar dados para atualização
        $dados_localizacao = [
            'cidade' => $cidade,
            'bairro' => $bairro,
            'estado' => $estado,
            'cep' => $cep
        ];
        
        // Atualizar localização
        $updated = update("localizacoes", $dados_localizacao, "id = ? AND tenant_id = ?", [$localizacao_id, TENANT_ID]);
        
        if ($updated) {
            $success_message = "Localização atualizada com sucesso!";
            
            // Atualizar dados da variável para exibição
            $localizacao = array_merge($localizacao, $dados_localizacao);
        } else {
            throw new Exception('Erro ao atualizar localização.');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Buscar estatísticas da localização
$imoveis_count = fetch("SELECT COUNT(*) as total FROM imoveis WHERE localizacao_id = ? AND tenant_id = ?", [$localizacao_id, TENANT_ID]);
$imoveis_list = fetchAll("SELECT id, titulo, preco, status FROM imoveis WHERE localizacao_id = ? AND tenant_id = ? ORDER BY data_criacao DESC LIMIT 5", [$localizacao_id, TENANT_ID]);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Localização - Painel Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <?php include '../includes/subheader.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-edit text-warning"></i>
                        Editar Localização
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>

                <?php if ($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-map-marker-alt"></i> Dados da Localização
                                    <span class="badge bg-secondary ms-2">ID: <?php echo $localizacao_id; ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" id="formLocalizacao">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="cidade" class="form-label">
                                                <i class="fas fa-city"></i> Cidade *
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="cidade" 
                                                   name="cidade" 
                                                   value="<?php echo htmlspecialchars($localizacao['cidade']); ?>"
                                                   required 
                                                   placeholder="Ex: São Paulo">
                                            <div class="form-text">Nome da cidade</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="estado" class="form-label">
                                                <i class="fas fa-flag"></i> Estado *
                                            </label>
                                            <select class="form-select" id="estado" name="estado" required>
                                                <option value="">Selecione o estado...</option>
                                                <?php foreach ($estados_brasil as $sigla => $nome): ?>
                                                    <option value="<?php echo $sigla; ?>" 
                                                            <?php echo ($localizacao['estado'] === $sigla) ? 'selected' : ''; ?>>
                                                        <?php echo $sigla; ?> - <?php echo $nome; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Estado da localização</div>
                                        </div>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="bairro" class="form-label">
                                                <i class="fas fa-map"></i> Bairro
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="bairro" 
                                                   name="bairro" 
                                                   value="<?php echo htmlspecialchars($localizacao['bairro'] ?? ''); ?>"
                                                   placeholder="Ex: Jardins, Centro, Vila Madalena">
                                            <div class="form-text">Bairro específico (opcional)</div>
                                        </div>
                                        
                                        <div class="col-md-6 mb-3">
                                            <label for="cep" class="form-label">
                                                <i class="fas fa-mail-bulk"></i> CEP
                                            </label>
                                            <input type="text" 
                                                   class="form-control" 
                                                   id="cep" 
                                                   name="cep" 
                                                   value="<?php echo htmlspecialchars($localizacao['cep'] ?? ''); ?>"
                                                   placeholder="00000-000"
                                                   maxlength="9">
                                            <div class="form-text">CEP da região (opcional)</div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="index.php" class="btn btn-secondary me-md-2">
                                            <i class="fas fa-times"></i> Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-warning">
                                            <i class="fas fa-save"></i> Atualizar Localização
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle"></i> Informações
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-lightbulb"></i> Dicas:</h6>
                                    <ul class="mb-0">
                                        <li><strong>Cidade e Estado</strong> são obrigatórios</li>
                                        <li><strong>Bairro</strong> é opcional mas recomendado</li>
                                        <li><strong>CEP</strong> ajuda na precisão da localização</li>
                                        <li>Evite duplicatas de cidade+bairro+estado</li>
                                    </ul>
                                </div>
                                
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Importante:</h6>
                                    <p class="mb-0">Alterações afetarão todos os imóveis cadastrados nesta localização.</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estatísticas da Localização -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-bar"></i> Estatísticas
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <h4 class="text-primary"><?php echo $imoveis_count['total']; ?></h4>
                                        <small class="text-muted">Imóveis</small>
                                    </div>
                                    <div class="col-6">
                                        <h4 class="text-success"><?php echo $localizacao['estado']; ?></h4>
                                        <small class="text-muted">Estado</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Imóveis nesta Localização -->
                        <?php if (!empty($imoveis_list)): ?>
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-home"></i> Imóveis Recentes
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($imoveis_list as $imovel): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($imovel['titulo']); ?></strong>
                                                    <br><small class="text-muted">R$ <?php echo number_format($imovel['preco'], 2, ',', '.'); ?></small>
                                                </div>
                                                <span class="badge bg-<?php echo $imovel['status'] === 'disponivel' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($imovel['status']); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if ($imoveis_count['total'] > 5): ?>
                                        <div class="text-center mt-2">
                                            <small class="text-muted">
                                                E mais <?php echo ($imoveis_count['total'] - 5); ?> imóvel(is)...
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    
    <script>
        // Máscara para CEP
        document.getElementById('cep').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 5) {
                value = value.substring(0, 5) + '-' + value.substring(5, 8);
            }
            e.target.value = value;
        });
        
        // Validação do formulário
        document.getElementById('formLocalizacao').addEventListener('submit', function(e) {
            const cidade = document.getElementById('cidade').value.trim();
            const estado = document.getElementById('estado').value;
            
            if (!cidade) {
                e.preventDefault();
                alert('Por favor, preencha a cidade.');
                document.getElementById('cidade').focus();
                return false;
            }
            
            if (!estado) {
                e.preventDefault();
                alert('Por favor, selecione o estado.');
                document.getElementById('estado').focus();
                return false;
            }
        });
    </script>
</body>
</html>
