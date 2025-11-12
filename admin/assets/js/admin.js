/**
 * Imob ImÃ³veis - Painel Administrativo
 * JavaScript principal para funcionalidades do admin
 */

document.addEventListener('DOMContentLoaded', function() {
    
    // ===== INICIALIZAÃ‡ÃƒO =====
    initializeAdmin();
    
    // ===== EVENT LISTENERS =====
    setupEventListeners();
    
    // ===== FUNCIONALIDADES RESPONSIVAS =====
    setupResponsiveFeatures();
    
    // ===== CONFIRMAÃ‡Ã•ES E ALERTAS =====
    setupConfirmations();
    
    // ===== TOOLTIPS E POPOVERS =====
    setupTooltips();
    
    // ===== VALIDAÃ‡Ã•ES DE FORMULÃRIO =====
setupFormValidations();

// ===== FORMATAÃ‡ÃƒO DE PREÃ‡OS =====
setupPriceFormatting();
    
    // ===== UPLOAD DE ARQUIVOS =====
    setupFileUploads();
    
    // ===== NOTIFICAÃ‡Ã•ES =====
    setupNotifications();
    
    // ===== DASHBOARD CHARTS =====
    setupDashboardCharts();
});

/**
 * InicializaÃ§Ã£o principal do painel admin
 */
function initializeAdmin() {
    console.log('Imob ImÃ³veis Admin - Inicializando...');
    
    // Verificar se hÃ¡ mensagens de sucesso/erro para mostrar
    showStoredMessages();
    
    // Inicializar componentes Bootstrap
    initializeBootstrapComponents();
    
    // Configurar tema escuro/claro se disponÃ­vel
    setupThemeToggle();
    
    // Inicializar sidebar mobile
    initializeMobileSidebar();
}

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    
    // Toggle sidebar mobile
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleMobileSidebar);
    }
    
    // Fechar sidebar ao clicar fora (mobile)
    document.addEventListener('click', function(e) {
        const sidebar = document.querySelector('.sidebar');
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        
        if (window.innerWidth <= 768 && 
            sidebar && 
            !sidebar.contains(e.target) && 
            !sidebarToggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });
    
    // Filtros de busca em tempo real
    const searchInputs = document.querySelectorAll('.search-input');
    searchInputs.forEach(input => {
        input.addEventListener('input', debounce(handleSearch, 300));
    });
    
    // Filtros de status
    const statusFilters = document.querySelectorAll('.status-filter');
    statusFilters.forEach(filter => {
        filter.addEventListener('change', handleStatusFilter);
    });
    
    // PaginaÃ§Ã£o
    const paginationLinks = document.querySelectorAll('.pagination .page-link');
    paginationLinks.forEach(link => {
        link.addEventListener('click', handlePagination);
    });
    
    // BotÃµes de aÃ§Ã£o
    const actionButtons = document.querySelectorAll('.action-btn');
    actionButtons.forEach(btn => {
        btn.addEventListener('click', handleActionButton);
    });
}

/**
 * Funcionalidades responsivas
 */
function setupResponsiveFeatures() {
    
    // Ajustar layout baseado no tamanho da tela
    function adjustLayout() {
        const sidebar = document.querySelector('.sidebar');
        const main = document.querySelector('main');
        
        if (window.innerWidth <= 768) {
            if (sidebar) sidebar.classList.remove('show');
            if (main) main.style.marginLeft = '0';
        } else {
            if (sidebar) sidebar.classList.remove('show');
            if (main) main.style.marginLeft = '';
        }
    }
    
    // Executar no carregamento e no redimensionamento
    adjustLayout();
    window.addEventListener('resize', debounce(adjustLayout, 250));
    
    // Sidebar mobile
    initializeMobileSidebar();
}

/**
 * Inicializar sidebar mobile
 */
function initializeMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    
    if (!sidebar || !sidebarToggle) return;
    
    // Criar botÃ£o toggle se nÃ£o existir
    if (!document.querySelector('.sidebar-toggle')) {
        const toggleBtn = document.createElement('button');
        toggleBtn.className = 'btn btn-primary d-md-none sidebar-toggle';
        toggleBtn.innerHTML = '<i class="fas fa-bars"></i>';
        toggleBtn.style.position = 'fixed';
        toggleBtn.style.top = '70px';
        toggleBtn.style.left = '10px';
        toggleBtn.style.zIndex = '1001';
        document.body.appendChild(toggleBtn);
    }
}

/**
 * Toggle sidebar mobile
 */
function toggleMobileSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (sidebar) {
        sidebar.classList.toggle('show');
    }
}

/**
 * Configurar confirmaÃ§Ãµes
 */
function setupConfirmations() {
    
    // ConfirmaÃ§Ã£o para exclusÃµes
    const deleteButtons = document.querySelectorAll('.btn-delete, .btn-excluir');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Tem certeza que deseja excluir este item? Esta aÃ§Ã£o nÃ£o pode ser desfeita.')) {
                e.preventDefault();
                return false;
            }
            
            // Mostrar loading
            showLoading(this);
        });
    });
    
    // ConfirmaÃ§Ã£o para alteraÃ§Ãµes de status
    const statusButtons = document.querySelectorAll('.btn-status');
    statusButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            const action = this.dataset.action;
            const itemName = this.dataset.itemName || 'item';
            
            if (!confirm(`Tem certeza que deseja ${action} este ${itemName}?`)) {
                e.preventDefault();
                return false;
            }
        });
    });
}

/**
 * Configurar tooltips
 */
function setupTooltips() {
    // Inicializar tooltips Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Inicializar popovers Bootstrap
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Configurar validaÃ§Ãµes de formulÃ¡rio
 */
function setupFormValidations() {
    
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            form.classList.add('was-validated');
        });
    });
    
    // ValidaÃ§Ã£o de campos especÃ­ficos
    const requiredFields = document.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        field.addEventListener('blur', validateField);
        field.addEventListener('input', clearFieldError);
    });
}

/**
 * Validar campo individual
 */
function validateField(e) {
    const field = e.target;
    const value = field.value.trim();
    
    if (field.hasAttribute('required') && !value) {
        showFieldError(field, 'Este campo Ã© obrigatÃ³rio');
    } else if (field.type === 'email' && value && !isValidEmail(value)) {
        showFieldError(field, 'Email invÃ¡lido');
    } else if (field.type === 'tel' && value && !isValidPhone(value)) {
        showFieldError(field, 'Telefone invÃ¡lido');
    } else {
        clearFieldError(field);
    }
}

/**
 * Mostrar erro no campo
 */
function showFieldError(field, message) {
    clearFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'invalid-feedback';
    errorDiv.textContent = message;
    
    field.classList.add('is-invalid');
    field.parentNode.appendChild(errorDiv);
}

/**
 * Limpar erro do campo
 */
function clearFieldError(field) {
    field.classList.remove('is-invalid');
    const errorDiv = field.parentNode.querySelector('.invalid-feedback');
    if (errorDiv) {
        errorDiv.remove();
    }
}

/**
 * Configurar upload de arquivos
 */
function setupFileUploads() {
    console.log('DEBUG: setupFileUploads chamada');
    
    const fileInputs = document.querySelectorAll('.file-upload');
    console.log('DEBUG: File inputs encontrados:', fileInputs.length);
    
    fileInputs.forEach((input, index) => {
        console.log('DEBUG: Configurando input', index, ':', input);
        input.addEventListener('change', handleFileUpload);
    });
    
    // Drag and drop para upload
    const dropZones = document.querySelectorAll('.drop-zone');
    console.log('DEBUG: Drop zones encontradas:', dropZones.length);
    
    dropZones.forEach((zone, index) => {
        console.log('DEBUG: Configurando drop zone', index, ':', zone);
        zone.addEventListener('dragover', handleDragOver);
        zone.addEventListener('drop', handleDrop);
        zone.addEventListener('dragleave', handleDragLeave);
    });
    
    console.log('DEBUG: setupFileUploads concluÃ­do');
}

/**
 * Manipular upload de arquivo
 */
function handleFileUpload(e) {
    console.log('DEBUG: handleFileUpload chamada');
    
    const input = e.target;
    const files = input.files;
    
    console.log('DEBUG: Arquivos selecionados:', files.length);
    console.log('DEBUG: Input:', input);
    console.log('DEBUG: Parent node:', input.parentNode);
    
    // Buscar o preview de forma mais robusta
    let preview = input.parentNode.querySelector('.file-preview');
    
    // Se nÃ£o encontrar no parent, buscar em todo o documento
    if (!preview) {
        preview = document.querySelector('.file-preview');
        console.log('DEBUG: Preview encontrado no documento:', preview);
    }
    
    // Se ainda nÃ£o encontrar, criar o preview
    if (!preview) {
        console.log('DEBUG: Criando preview dinamicamente');
        preview = document.createElement('div');
        preview.className = 'file-preview mt-3';
        input.parentNode.appendChild(preview);
    }
    
    console.log('DEBUG: Preview final:', preview);
    
    if (files.length > 0) {
        // Limpar preview anterior
        if (preview) {
            preview.innerHTML = '';
            console.log('DEBUG: Preview limpo');
        }
        
        // Processar cada arquivo
        Array.from(files).forEach((file, index) => {
            console.log('DEBUG: Processando arquivo:', file.name, 'Tipo:', file.type, 'Tamanho:', file.size);
            
            // Validar tipo de arquivo
            if (!isValidFileType(file)) {
                console.log('DEBUG: Arquivo invÃ¡lido:', file.name);
                showNotification(`Tipo de arquivo nÃ£o suportado: ${file.name}`, 'error');
                return;
            }
            
            // Validar tamanho
            if (file.size > 5 * 1024 * 1024) { // 5MB
                console.log('DEBUG: Arquivo muito grande:', file.name);
                showNotification(`Arquivo muito grande: ${file.name}. MÃ¡ximo 5MB`, 'error');
                return;
            }
            
            // Mostrar preview para imagens
            if (preview && file.type.startsWith('image/')) {
                console.log('DEBUG: Criando preview para imagem:', file.name);
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    console.log('DEBUG: FileReader carregado para:', file.name);
                    
                    const previewItem = document.createElement('div');
                    previewItem.className = 'preview-item d-inline-block me-3 mb-3';
                    previewItem.style.cssText = 'position: relative;';
                    
                    previewItem.innerHTML = `
                        <img src="${e.target.result}" class="img-thumbnail" style="max-width: 150px; max-height: 150px; object-fit: cover;">
                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0" 
                                style="transform: translate(50%, -50%);" 
                                onclick="removeFile(this, ${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    
                    preview.appendChild(previewItem);
                    console.log('DEBUG: Preview item adicionado para:', file.name);
                };
                
                reader.onerror = function() {
                    console.error('DEBUG: Erro no FileReader para:', file.name);
                };
                
                reader.readAsDataURL(file);
            } else {
                console.log('DEBUG: NÃ£o Ã© imagem ou preview nÃ£o encontrado:', file.type, preview);
            }
        });
        
        showNotification(`${files.length} arquivo(s) selecionado(s) com sucesso`, 'success');
    }
}

/**
 * Configurar notificaÃ§Ãµes
 */
function setupNotifications() {
    
    // Criar container de notificaÃ§Ãµes se nÃ£o existir
    if (!document.querySelector('.notifications-container')) {
        const container = document.createElement('div');
        container.className = 'notifications-container';
        container.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
        `;
        document.body.appendChild(container);
    }
}

/**
 * Mostrar notificaÃ§Ã£o
 */
function showNotification(message, type = 'info', duration = 5000) {
    const container = document.querySelector('.notifications-container');
    if (!container) return;
    
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show`;
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    container.appendChild(notification);
    
    // Auto-remover apÃ³s duraÃ§Ã£o
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, duration);
    
    // Remover ao fechar
    notification.querySelector('.btn-close').addEventListener('click', () => {
        notification.remove();
    });
}

/**
 * Configurar grÃ¡ficos do dashboard
 */
function setupDashboardCharts() {
    
    // Verificar se Chart.js estÃ¡ disponÃ­vel
    if (typeof Chart !== 'undefined') {
        setupImoveisChart();
        setupContatosChart();
    }
}

/**
 * Configurar grÃ¡fico de imÃ³veis
 */
function setupImoveisChart() {
    const ctx = document.getElementById('imoveisChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['DisponÃ­vel', 'Vendido', 'Alugado', 'Reservado'],
            datasets: [{
                data: [12, 19, 3, 5],
                backgroundColor: [
                    '#1cc88a',
                    '#e74a3b',
                    '#f6c23e',
                    '#36b9cc'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

/**
 * Configurar grÃ¡fico de contatos
 */
function setupContatosChart() {
    const ctx = document.getElementById('contatosChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
            datasets: [{
                label: 'Contatos',
                data: [65, 59, 80, 81, 56, 55],
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

/**
 * Mostrar mensagens armazenadas
 */
function showStoredMessages() {
    // Verificar mensagens na sessÃ£o (PHP)
    const successMessage = document.querySelector('.alert-success');
    const errorMessage = document.querySelector('.alert-danger');
    
    if (successMessage) {
        showNotification(successMessage.textContent, 'success');
        successMessage.remove();
    }
    
    if (errorMessage) {
        showNotification(errorMessage.textContent, 'error');
        errorMessage.remove();
    }
}

/**
 * Inicializar componentes Bootstrap
 */
function initializeBootstrapComponents() {
    // Inicializar dropdowns
    const dropdowns = document.querySelectorAll('.dropdown-toggle');
    dropdowns.forEach(dropdown => {
        new bootstrap.Dropdown(dropdown);
    });
    
    // Inicializar modais
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        new bootstrap.Modal(modal);
    });
    
    // Inicializar tabs
    const tabs = document.querySelectorAll('[data-bs-toggle="tab"]');
    tabs.forEach(tab => {
        new bootstrap.Tab(tab);
    });
}

/**
 * Configurar toggle de tema
 */
function setupThemeToggle() {
    const themeToggle = document.querySelector('.theme-toggle');
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
        
        // Carregar tema salvo
        const savedTheme = localStorage.getItem('admin-theme');
        if (savedTheme) {
            document.body.setAttribute('data-theme', savedTheme);
        }
    }
}

/**
 * Toggle tema escuro/claro
 */
function toggleTheme() {
    const body = document.body;
    const currentTheme = body.getAttribute('data-theme');
    const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
    
    body.setAttribute('data-theme', newTheme);
    localStorage.setItem('admin-theme', newTheme);
    
    showNotification(`Tema alterado para ${newTheme === 'dark' ? 'escuro' : 'claro'}`, 'info');
}

/**
 * Mostrar loading
 */
function showLoading(element) {
    const originalText = element.innerHTML;
    element.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Carregando...';
    element.disabled = true;
    
    // Restaurar apÃ³s 3 segundos (ou quando a operaÃ§Ã£o terminar)
    setTimeout(() => {
        element.innerHTML = originalText;
        element.disabled = false;
    }, 3000);
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Handlers para eventos especÃ­ficos
 */
function handleSearch(e) {
    const searchTerm = e.target.value;
    const searchResults = document.querySelector('.search-results');
    
    if (searchResults) {
        // Implementar busca em tempo real
        console.log('Buscando por:', searchTerm);
    }
}

function handleStatusFilter(e) {
    const status = e.target.value;
    const form = e.target.closest('form');
    
    if (form) {
        form.submit();
    }
}

function handlePagination(e) {
    e.preventDefault();
    const href = e.target.href;
    
    if (href) {
        window.location.href = href;
    }
}

function handleActionButton(e) {
    const action = e.target.dataset.action;
    const itemId = e.target.dataset.itemId;
    
    console.log('AÃ§Ã£o:', action, 'Item:', itemId);
}

/**
 * FunÃ§Ãµes utilitÃ¡rias
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

function isValidPhone(phone) {
    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
    return phoneRegex.test(phone.replace(/\D/g, ''));
}

function isValidFileType(file) {
    // Verificar MIME type
    const allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (allowedMimeTypes.includes(file.type)) {
        return true;
    }
    
    // Verificar extensÃ£o como fallback
    const allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const fileName = file.name.toLowerCase();
    const extension = fileName.split('.').pop();
    
    return allowedExtensions.includes(extension);
}

function handleDragOver(e) {
    e.preventDefault();
    e.currentTarget.classList.add('drag-over');
}

function handleDrop(e) {
    e.preventDefault();
    e.currentTarget.classList.remove('drag-over');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const input = e.currentTarget.querySelector('input[type="file"]');
        if (input) {
            // Atualizar o input com os arquivos
            input.files = files;
            
            // Disparar evento change para processar os arquivos
            const changeEvent = new Event('change', { bubbles: true });
            input.dispatchEvent(changeEvent);
            
            // Adicionar classe visual para feedback
            e.currentTarget.classList.add('drop-success');
            setTimeout(() => {
                e.currentTarget.classList.remove('drop-success');
            }, 1000);
        }
    }
}

function handleDragLeave(e) {
    e.currentTarget.classList.remove('drag-over');
}

/**
 * Remover arquivo do preview
 */
function removeFile(button, index) {
    const previewItem = button.closest('.preview-item');
    if (previewItem) {
        previewItem.remove();
        
        // Atualizar o input de arquivo
        const fileInput = document.querySelector('.file-upload');
        if (fileInput && fileInput.files.length > 0) {
            const dt = new DataTransfer();
            Array.from(fileInput.files).forEach((file, i) => {
                if (i !== index) {
                    dt.items.add(file);
                }
            });
            fileInput.files = dt.files;
        }
        
        showNotification('Arquivo removido', 'info');
    }
}

/**
 * Configurar formataÃ§Ã£o de preÃ§os no padrÃ£o brasileiro
 */
function setupPriceFormatting() {
    const priceInputs = document.querySelectorAll('input[name="preco"], input[id="preco"]');
    
    priceInputs.forEach(input => {
        // Formatar valor inicial se existir
        if (input.value) {
            input.value = formatPriceForInput(input.value);
        }
        
        // Formatar ao perder o foco
        input.addEventListener('blur', function() {
            if (this.value) {
                this.value = formatPriceForInput(this.value);
            }
        });
        
        // Formatar ao digitar em tempo real (mais suave)
        input.addEventListener('input', function() {
            // Obter o valor atual e a posiÃ§Ã£o do cursor
            let currentValue = this.value;
            let cursorPosition = this.selectionStart;
            
            // Se o usuÃ¡rio estÃ¡ digitando no meio do campo, nÃ£o formatar
            if (cursorPosition < currentValue.length) {
                return;
            }
            
            // Remover tudo exceto nÃºmeros
            let cleanValue = currentValue.replace(/[^\d]/g, '');
            
            // Se nÃ£o hÃ¡ valor, nÃ£o fazer nada
            if (!cleanValue) {
                return;
            }
            
            // Formatar em tempo real apenas se o usuÃ¡rio estÃ¡ no final
            let formattedValue = formatPriceRealTime(cleanValue);
            
            // Atualizar o campo apenas se o valor mudou e o cursor estÃ¡ no final
            if (formattedValue !== currentValue && cursorPosition === currentValue.length) {
                this.value = formattedValue;
                
                // Manter o cursor no final apÃ³s formataÃ§Ã£o
                this.setSelectionRange(formattedValue.length, formattedValue.length);
            }
        });
        
        // Formatar ao ganhar o foco (remover formataÃ§Ã£o para ediÃ§Ã£o)
        input.addEventListener('focus', function() {
            if (this.value) {
                this.value = this.value.replace(/\./g, '').replace(',', '.');
            }
        });
    });
}

/**
 * Formatar preÃ§o em tempo real durante a digitaÃ§Ã£o (versÃ£o suave)
 * @param {string} value - Valor numÃ©rico limpo
 * @returns {string} - Valor formatado em tempo real
 */
function formatPriceRealTime(value) {
    // Se nÃ£o hÃ¡ valor, retornar vazio
    if (!value) {
        return '';
    }
    
    // Converter para string e garantir que seja apenas nÃºmeros
    let cleanValue = String(value).replace(/[^\d]/g, '');
    
    // Se nÃ£o hÃ¡ nÃºmeros, retornar vazio
    if (!cleanValue) {
        return '';
    }
    
    // Formatar em tempo real (versÃ£o mais suave)
    let formattedValue = '';
    
    // Para valores pequenos (1-2 dÃ­gitos), nÃ£o adicionar formataÃ§Ã£o
    if (cleanValue.length <= 2) {
        return cleanValue;
    }
    
    // Para valores mÃ©dios (3-5 dÃ­gitos), adicionar apenas vÃ­rgula
    if (cleanValue.length <= 5) {
        formattedValue = cleanValue.slice(0, -2) + ',' + cleanValue.slice(-2);
        return formattedValue;
    }
    
    // Para valores grandes (6+ dÃ­gitos), adicionar pontos e vÃ­rgula
    let tempValue = cleanValue;
    
    // Adicionar vÃ­rgula para decimais
    if (tempValue.length > 2) {
        tempValue = tempValue.slice(0, -2) + ',' + tempValue.slice(-2);
    }
    
    // Adicionar pontos para milhares (apenas se necessÃ¡rio)
    if (tempValue.length > 6) { // Mais de 999,99
        let parts = tempValue.split(',');
        let integerPart = parts[0];
        let decimalPart = parts[1] || '';
        
        // Adicionar pontos para milhares
        let formattedInteger = '';
        for (let i = 0; i < integerPart.length; i++) {
            if (i > 0 && (integerPart.length - i) % 3 === 0) {
                formattedInteger += '.';
            }
            formattedInteger += integerPart[i];
        }
        
        formattedValue = formattedInteger + (decimalPart ? ',' + decimalPart : '');
    } else {
        formattedValue = tempValue;
    }
    
    return formattedValue;
}

/**
 * Formatar preÃ§o para exibiÃ§Ã£o no input (padrÃ£o brasileiro)
 * @param {string|number} value - Valor a ser formatado
 * @returns {string} - Valor formatado
 */
function formatPriceForInput(value) {
    // Converter para string e remover formataÃ§Ã£o existente
    let cleanValue = String(value).replace(/[^\d,]/g, '');
    
    // Substituir vÃ­rgula por ponto para cÃ¡lculos
    cleanValue = cleanValue.replace(',', '.');
    
    // Converter para nÃºmero
    let number = parseFloat(cleanValue);
    
    if (isNaN(number)) {
        return '';
    }
    
    // Formatar para o padrÃ£o brasileiro (pontos para milhares, vÃ­rgula para decimais)
    return number.toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Converter preÃ§o formatado para nÃºmero (para envio do formulÃ¡rio)
 * @param {string} formattedPrice - PreÃ§o formatado
 * @returns {number} - NÃºmero para envio
 */
function convertFormattedPriceToNumber(formattedPrice) {
    // Remover pontos e substituir vÃ­rgula por ponto
    const cleanValue = formattedPrice.replace(/\./g, '').replace(',', '.');
    return parseFloat(cleanValue) || 0;
}

// ===== EXPORTAR FUNÃ‡Ã•ES PARA USO GLOBAL =====
window.AdminPanel = {
    showNotification,
    showLoading,
    toggleTheme,
    validateField,
    clearFieldError,
    formatPriceForInput,
    formatPriceRealTime,
    convertFormattedPriceToNumber
};
