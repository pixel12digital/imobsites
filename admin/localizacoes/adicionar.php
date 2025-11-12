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
        
        // Verificar se já existe uma localização com cidade + bairro + estado
        $existing_location = fetch(
            "SELECT id FROM localizacoes WHERE cidade = ? AND bairro = ? AND estado = ? AND tenant_id = ?", 
            [$cidade, $bairro, $estado, TENANT_ID]
        );
        
        if ($existing_location) {
            throw new Exception('Já existe uma localização com esta cidade, bairro e estado.');
        }
        
        // Preparar dados para inserção
        $dados_localizacao = [
            'cidade' => $cidade,
            'bairro' => $bairro,
            'estado' => $estado,
            'cep' => $cep,
            'tenant_id' => TENANT_ID
        ];
        
        // Inserir localização
        $localizacao_id = insert("localizacoes", $dados_localizacao);
        
        if ($localizacao_id) {
            $success_message = "Localização cadastrada com sucesso! ID: {$localizacao_id}";
            
            // Limpar formulário após sucesso
            $_POST = [];
        } else {
            throw new Exception('Erro ao cadastrar localização.');
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adicionar Localização - Painel Admin</title>
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
                        <i class="fas fa-plus text-success"></i>
                        Adicionar Nova Localização
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
                                                   value="<?php echo isset($_POST['cidade']) ? htmlspecialchars($_POST['cidade']) : ''; ?>"
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
                                                            <?php echo (isset($_POST['estado']) && $_POST['estado'] === $sigla) ? 'selected' : ''; ?>>
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
                                                   value="<?php echo isset($_POST['bairro']) ? htmlspecialchars($_POST['bairro']) : ''; ?>"
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
                                                   value="<?php echo isset($_POST['cep']) ? htmlspecialchars($_POST['cep']) : ''; ?>"
                                                   placeholder="00000-000"
                                                   maxlength="9">
                                            <div class="form-text">CEP da região (opcional)</div>
                                        </div>
                                    </div>
                                    
                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="index.php" class="btn btn-secondary me-md-2">
                                            <i class="fas fa-times"></i> Cancelar
                                        </a>
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-save"></i> Salvar Localização
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
                                    <p class="mb-0">Após cadastrar, a localização estará disponível para seleção no cadastro de imóveis.</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-list"></i> Localizações Recentes
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php
                                $recentes = fetchAll("SELECT * FROM localizacoes ORDER BY id DESC LIMIT 5");
                                if (!empty($recentes)):
                                ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($recentes as $recente): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($recente['cidade']); ?></strong>
                                                    <?php if ($recente['bairro']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($recente['bairro']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="badge bg-primary"><?php echo $recente['estado']; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center mb-0">Nenhuma localização cadastrada ainda.</p>
                                <?php endif; ?>
                            </div>
                        </div>
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
