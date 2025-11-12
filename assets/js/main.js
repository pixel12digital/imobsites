// imobsites - Main JavaScript File

document.addEventListener('DOMContentLoaded', function() {
    
    // Inicializar componentes
    initTooltips();
    initPropertyFilters();
    initContactForm();
    initScrollEffects();
    initWhatsAppButton();
    initHomeFilters(); // Inicializar filtros da home
    
    // Animações de entrada
    animateOnScroll();
});

// Inicializar tooltips do Bootstrap
function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

// Sistema de filtros de imóveis
function initPropertyFilters() {
    const filterForm = document.getElementById('property-filters');
    if (!filterForm) return;
    
    // Filtros de preço
    const priceRange = document.getElementById('price-range');
    const priceValue = document.getElementById('price-value');
    
    if (priceRange && priceValue) {
        priceRange.addEventListener('input', function() {
            priceValue.textContent = formatPrice(this.value);
        });
    }
    
    // Filtros de área
    const areaRange = document.getElementById('area-range');
    const areaValue = document.getElementById('area-value');
    
    if (areaRange && areaValue) {
        areaRange.addEventListener('input', function() {
            areaValue.textContent = this.value + 'm²';
        });
    }
    
    // Aplicar filtros
    filterForm.addEventListener('submit', function(e) {
        e.preventDefault();
        applyFilters();
    });
}

// Aplicar filtros e atualizar resultados
function applyFilters() {
    const formData = new FormData(document.getElementById('property-filters'));
    const params = new URLSearchParams();
    
    for (let [key, value] of formData.entries()) {
        if (value) params.append(key, value);
    }
    
    // Redirecionar para página de imóveis com filtros
    const baseUrl = window.location.pathname.replace(/\/[^\/]*$/, '') || '';
    window.location.href = baseUrl + '/index.php?page=imoveis&' + params.toString();
}

// Formulário de contato
function initContactForm() {
    const contactForm = document.getElementById('contact-form');
    if (!contactForm) return;
    
    contactForm.addEventListener('submit', function(e) {
        e.preventDefault();
        submitContactForm(this);
    });
}

// Enviar formulário de contato
function submitContactForm(form) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    // Mostrar loading
    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando...';
    submitBtn.classList.add('loading');
    
    // Simular envio (substituir por AJAX real)
    setTimeout(() => {
        // Sucesso
        showNotification('Mensagem enviada com sucesso! Entraremos em contato em breve.', 'success');
        form.reset();
        
        // Restaurar botão
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
        submitBtn.classList.remove('loading');
    }, 2000);
}

// Efeitos de scroll
function initScrollEffects() {
    // Header transparente no scroll
    const header = document.querySelector('.header');
    if (header) {
        window.addEventListener('scroll', function() {
            if (window.scrollY > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
    }
    
    // Animações de entrada
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
            }
        });
    }, observerOptions);
    
    // Observar elementos para animação
    document.querySelectorAll('.property-card, .service-card, .stat-item').forEach(el => {
        observer.observe(el);
    });
}

// Animações ao scroll
function animateOnScroll() {
    const elements = document.querySelectorAll('.animate-on-scroll');
    
    elements.forEach(element => {
        element.classList.add('fade-in-up');
    });
}

// Botão do WhatsApp
function initWhatsAppButton() {
    const whatsappBtn = document.querySelector('.whatsapp-btn');
    if (whatsappBtn) {
        whatsappBtn.addEventListener('click', function(e) {
            // Adicionar delay para melhor UX
            setTimeout(() => {
                // O link do WhatsApp já está configurado no HTML
                // Esta função pode ser usada para analytics ou outras funcionalidades
            }, 100);
        });
    }
}

// Sistema de notificações
function showNotification(message, type = 'info') {
    // Criar elemento de notificação
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show notification-toast`;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        min-width: 300px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;
    
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Adicionar ao DOM
    document.body.appendChild(notification);
    
    // Auto-remover após 5 segundos
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Formatação de preços
function formatPrice(price) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(price);
}

// Lazy loading de imagens
function initLazyLoading() {
    const images = document.querySelectorAll('img[data-src]');
    
    const imageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.classList.remove('lazy');
                imageObserver.unobserve(img);
            }
        });
    });
    
    images.forEach(img => imageObserver.observe(img));
}

// Sistema de busca em tempo real
function initSearchSuggestions() {
    const searchInput = document.getElementById('search-input');
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        
        searchTimeout = setTimeout(() => {
            const query = this.value.trim();
            if (query.length >= 3) {
                searchProperties(query);
            }
        }, 300);
    });
}

// Buscar propriedades
function searchProperties(query) {
    // Aqui você implementaria a busca AJAX real
    console.log('Buscando por:', query);
    
    // Implementação da busca será adicionada posteriormente se necessário
}

// Atualizar resultados da busca
function updateSearchResults(results) {
    const resultsContainer = document.getElementById('search-results');
    if (!resultsContainer) return;
    
    // Implementar atualização dos resultados
    console.log('Resultados:', results);
}

// Sistema de favoritos
function toggleFavorite(propertyId) {
    const favoriteBtn = document.querySelector(`[data-property-id="${propertyId}"]`);
    const icon = favoriteBtn.querySelector('i');
    
    if (favoriteBtn.classList.contains('favorited')) {
        // Remover dos favoritos
        favoriteBtn.classList.remove('favorited');
        icon.classList.remove('fas');
        icon.classList.add('far');
        showNotification('Imóvel removido dos favoritos', 'info');
    } else {
        // Adicionar aos favoritos
        favoriteBtn.classList.add('favorited');
        icon.classList.remove('far');
        icon.classList.add('fas');
        showNotification('Imóvel adicionado aos favoritos', 'success');
    }
    
    // Salvar no localStorage
    saveFavorites();
}

// Salvar favoritos no localStorage
function saveFavorites() {
    const favorites = Array.from(document.querySelectorAll('.favorite-btn.favorited'))
        .map(btn => btn.dataset.propertyId);
    
    localStorage.setItem('jtr_favorites', JSON.stringify(favorites));
}

// Carregar favoritos do localStorage
function loadFavorites() {
    const favorites = JSON.parse(localStorage.getItem('jtr_favorites') || '[]');
    
    favorites.forEach(propertyId => {
        const btn = document.querySelector(`[data-property-id="${propertyId}"]`);
        if (btn) {
            btn.classList.add('favorited');
            const icon = btn.querySelector('i');
            icon.classList.remove('far');
            icon.classList.add('fas');
        }
    });
}

// Compartilhar imóvel
function shareProperty(propertyId, platform) {
    const baseUrl = window.location.pathname.replace(/\/[^\/]*$/, '') || '';
    const url = `${window.location.origin}${baseUrl}/index.php?page=imovel&id=${propertyId}`;
    const title = document.title;
    
    let shareUrl;
    
    switch(platform) {
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${encodeURIComponent(title + ' ' + url)}`;
            break;
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}`;
            break;
        default:
            return;
    }
    
    window.open(shareUrl, '_blank', 'width=600,height=400');
}

// Inicializar quando a página carregar
window.addEventListener('load', function() {
    loadFavorites();
    initLazyLoading();

});

// ===== FUNÇÕES PARA FILTROS RÁPIDOS DA HOME =====

// Aplicar filtro rápido
function aplicarFiltroRapido(campo, valor) {
    const form = document.getElementById('quickSearchForm');
    if (!form) return;
    
    // Limpar todos os filtros primeiro
    limparFiltros();
    
    // Aplicar o filtro específico
    const elemento = form.querySelector(`[name="${campo}"]`);
    if (elemento) {
        elemento.value = valor;
        
        // Destacar o botão clicado
        const botaoClicado = event.target.closest('button');
        if (botaoClicado) {
            // Remover destaque de todos os botões
            document.querySelectorAll('.quick-filters-section .btn-outline-primary').forEach(btn => {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            });
            
            // Destacar o botão clicado
            botaoClicado.classList.remove('btn-outline-primary');
            botaoClicado.classList.add('btn-primary');
        }
        
        // Mostrar notificação
        showNotification(`Filtro aplicado: ${getNomeFiltro(campo, valor)}`, 'success');
        
        // Buscar imóveis via AJAX
        buscarImoveisAjax();
    }
}

// Limpar todos os filtros
function limparFiltros() {
    const form = document.getElementById('quickSearchForm');
    if (!form) return;
    
    // Limpar todos os campos
    form.querySelectorAll('select').forEach(select => {
        select.value = '';
    });
    
    // Limpar campos hidden
    form.querySelectorAll('input[type="hidden"]').forEach(input => {
        input.value = '';
    });
    
    // Resetar botões de filtros rápidos
    document.querySelectorAll('.quick-filters-section .btn-outline-primary, .quick-filters-section .btn-primary').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-primary');
    });
    
    showNotification('Filtros limpos com sucesso', 'info');
    
    // Buscar imóveis via AJAX
    buscarImoveisAjax();
}

// Buscar imóveis via AJAX
function buscarImoveisAjax() {
    const form = document.getElementById('quickSearchForm');
    if (!form) return;
    
    // Mostrar loading
    const resultsContainer = document.getElementById('imoveisFiltrados');
    const countBadge = document.querySelector('.filter-results .badge');
    const titleElement = document.querySelector('.filter-results h2');
    
    if (resultsContainer) {
        resultsContainer.innerHTML = '<div class="col-12 text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-primary"></i><p class="mt-2">Buscando imóveis...</p></div>';
    }
    
    // Coletar dados do formulário
    const formData = new FormData(form);
    const params = new URLSearchParams();
    
    for (let [key, value] of formData.entries()) {
        if (value) {
            params.append(key, value);
        }
    }
    
    // Fazer requisição AJAX
    fetch('ajax_buscar_imoveis.php?' + params.toString())
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Atualizar contador
                if (countBadge) {
                    countBadge.textContent = data.count + ' imóveis encontrados';
                }
                
                // Atualizar título
                if (titleElement) {
                    titleElement.textContent = data.count > 0 ? 'Resultados da Busca' : 'Nenhum Resultado';
                }
                
                // Atualizar HTML dos resultados
                if (resultsContainer) {
                    resultsContainer.innerHTML = data.html;
                }
                
                // Scroll suave para os resultados
                document.querySelector('.filter-results').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'start' 
                });
                
            } else {
                console.error('Erro na busca:', data.error);
                showNotification('Erro ao buscar imóveis', 'error');
            }
        })
        .catch(error => {
            console.error('Erro na requisição:', error);
            showNotification('Erro ao buscar imóveis', 'error');
        });
}

// Obter nome amigável do filtro
function getNomeFiltro(campo, valor) {
    const nomes = {
        'tipo_negocio': {
            'venda': 'Para Venda',
            'locacao': 'Para Alugar',
            'venda_locacao': 'Venda + Locação'
        },
        'preco_max': {
            '100000': 'Até R$ 100.000',
            '200000': 'Até R$ 200.000',
            '300000': 'Até R$ 300.000',
            '500000': 'Até R$ 500.000',
            '750000': 'Até R$ 750.000',
            '1000000': 'Até R$ 1.000.000',
            '1500000': 'Até R$ 1.500.000',
            '2000000': 'Até R$ 2.000.000',
            '5000000': 'Até R$ 5.000.000'
        },
        'quartos': {
            '1': '1+ Quartos',
            '2': '2+ Quartos',
            '3': '3+ Quartos',
            '4': '4+ Quartos',
            '5': '5+ Quartos'
        },
        'vagas': {
            '1': '1+ Vagas',
            '2': '2+ Vagas',
            '3': '3+ Vagas',
            '4': '4+ Vagas',
            '5': '5+ Vagas'
        },
        'destaque': {
            '1': 'Em Destaque'
        }
    };
    
    return nomes[campo]?.[valor] || valor;
}

// Salvar filtros no localStorage
function salvarFiltrosHome() {
    const form = document.getElementById('quickSearchForm');
    if (!form) return;
    
    const filtros = {};
    form.querySelectorAll('select').forEach(select => {
        if (select.value) {
            filtros[select.name] = select.value;
        }
    });
    
    localStorage.setItem('jtr_filtros_home', JSON.stringify(filtros));
    showNotification('Filtros salvos com sucesso', 'success');
}

// Carregar filtros do localStorage
function carregarFiltrosHome() {
    const form = document.getElementById('quickSearchForm');
    if (!form) return;
    
    const filtros = JSON.parse(localStorage.getItem('jtr_filtros_home') || '{}');
    
    Object.entries(filtros).forEach(([campo, valor]) => {
        const elemento = form.querySelector(`[name="${campo}"]`);
        if (elemento) {
            elemento.value = valor;
        }
    });
    
    if (Object.keys(filtros).length > 0) {
        showNotification('Filtros anteriores carregados', 'info');
    }
}

// Inicializar filtros da home
function initHomeFilters() {
    const form = document.getElementById('quickSearchForm');
    if (!form) return;
    
    // Carregar filtros salvos
    carregarFiltrosHome();
    
    // Adicionar listener para salvar filtros automaticamente
    form.querySelectorAll('select').forEach(select => {
        select.addEventListener('change', function() {
            setTimeout(salvarFiltrosHome, 500); // Salvar após 500ms
        });
    });
    
    // Adicionar listener para envio do formulário
    form.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevenir submit tradicional
        
        // Salvar filtros antes de buscar
        salvarFiltrosHome();
        
        // Buscar imóveis via AJAX
        buscarImoveisAjax();
    });
}

// Exportar funções para uso global
window.JTRImoveis = {
    showNotification,
    formatPrice,
    toggleFavorite,
    shareProperty,
    searchProperties,
    aplicarFiltroRapido,
    limparFiltros,
    salvarFiltrosHome,
    carregarFiltrosHome,
    initHomeFilters
};



// Obter caminho do asset
function getAssetPath(path) {
    const baseUrl = window.location.pathname.replace(/\/[^\/]*$/, '') || '';
    return `${baseUrl}/assets/${path}`;
}

// Exportar resultados
function exportarResultados() {
    // Obter dados dos imóveis exibidos
    const imoveis = [];
    document.querySelectorAll('#imoveisGrid .card').forEach(card => {
        const imovel = {
            titulo: card.querySelector('.card-title')?.textContent?.trim() || '',
            preco: card.querySelector('.text-primary')?.textContent?.trim() || '',
            localizacao: card.querySelector('.fa-map-marker-alt')?.parentElement?.textContent?.trim() || '',
            quartos: card.querySelectorAll('.row.text-center .col-4 .fw-bold')[0]?.textContent?.trim() || '',
            banheiros: card.querySelectorAll('.row.text-center .col-4 .fw-bold')[1]?.textContent?.trim() || '',
            vagas: card.querySelectorAll('.row.text-center .col-4 .fw-bold')[2]?.textContent?.trim() || '',
            area_total: card.querySelectorAll('.row.text-center .col-6 .fw-bold')[0]?.textContent?.trim() || '',
            area_construida: card.querySelectorAll('.row.text-center .col-6 .fw-bold')[1]?.textContent?.trim() || '',
            data: card.querySelector('.text-muted')?.textContent?.trim() || ''
        };
        imoveis.push(imovel);
    });
    
    if (imoveis.length === 0) {
        showNotification('Nenhum imóvel para exportar', 'warning');
        return;
    }
    
    // Criar CSV
    const csvContent = createCSV(imoveis);
    
    // Download do arquivo
    downloadCSV(csvContent, 'imoveis-jtr.csv');
    
    showNotification('Exportação realizada com sucesso!', 'success');
}

// Criar conteúdo CSV
function createCSV(data) {
    const headers = [
        'Título',
        'Preço',
        'Localização',
        'Quartos',
        'Banheiros',
        'Vagas',
        'Área Total',
        'Área Construída',
        'Data'
    ];
    
    const csvRows = [headers.join(',')];
    
    data.forEach(row => {
        const values = [
            `"${row.titulo}"`,
            `"${row.preco}"`,
            `"${row.localizacao}"`,
            row.quartos,
            row.banheiros,
            row.vagas,
            row.area_total,
            row.area_construida,
            `"${row.data}"`
        ];
        csvRows.push(values.join(','));
    });
    
    return csvRows.join('\n');
}

// Download do arquivo CSV
function downloadCSV(content, filename) {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}
