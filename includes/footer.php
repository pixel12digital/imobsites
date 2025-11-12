    </main>

    <!-- Footer -->
    <footer class="footer py-5">
        <div class="container">
            <div class="row">
                <!-- Informações da Empresa -->
                <div class="col-lg-4 mb-4">
                    <h5><?php echo SITE_NAME; ?></h5>
                    <p>Realizamos sonhos com paixão, dedicação e recursos para ajudar nossos clientes a atingir seus objetivos de compra e venda.</p>
                    <div class="social-links mt-3">
                        <a href="#" aria-label="Facebook da imobsites"><i class="fab fa-facebook-f fa-lg"></i></a>
                        <a href="#" aria-label="Instagram da imobsites"><i class="fab fa-instagram fa-lg"></i></a>
                        <a href="#" aria-label="WhatsApp da imobsites"><i class="fab fa-whatsapp fa-lg"></i></a>
                    </div>
                </div>

                <!-- Links Rápidos -->
                <div class="col-lg-2 mb-4">
                    <h6>Links Rápidos</h6>
                    <ul>
                        <li><a href="<?php echo getPagePath('home'); ?>">Início</a></li>
                        <li><a href="<?php echo getPagePath('imoveis'); ?>">Imóveis</a></li>
                        <li><a href="<?php echo getPagePath('sobre'); ?>">Sobre</a></li>
                        <li><a href="<?php echo getPagePath('contato'); ?>">Contato</a></li>
                    </ul>
                </div>

                <!-- Tipos de Imóveis -->
                <div class="col-lg-2 mb-4">
                    <h6>Imóveis</h6>
                    <ul>
                        <li><a href="<?php echo getPagePath('imoveis', ['tipo' => 'casa']); ?>">Casas</a></li>
                        <li><a href="<?php echo getPagePath('imoveis', ['tipo' => 'apartamento']); ?>">Apartamentos</a></li>
                        <li><a href="<?php echo getPagePath('imoveis', ['tipo' => 'chacara']); ?>">Chácaras</a></li>
                        <li><a href="<?php echo getPagePath('imoveis', ['tipo' => 'terreno']); ?>">Terrenos</a></li>
                    </ul>
                </div>

                <!-- Contato -->
                <div class="col-lg-4 mb-4">
                    <h6>Contato</h6>
                    <div class="contact-info">

                        <p class="mb-2">
                            <i class="fas fa-home text-success" aria-hidden="true"></i>
                            <strong>Vendas:</strong> 
                            <a href="tel:<?php echo str_replace(['+', ' ', '-'], '', PHONE_VENDA); ?>" 
                               aria-label="Ligar para vendas: <?php echo PHONE_VENDA; ?>"
                               title="Ligar para vendas">
                                <?php echo PHONE_VENDA; ?>
                            </a>
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-key text-info" aria-hidden="true"></i>
                            <strong>Locação:</strong> 
                            <a href="tel:<?php echo str_replace(['+', ' ', '-'], '', PHONE_LOCACAO); ?>" 
                               aria-label="Ligar para locação: <?php echo PHONE_LOCACAO; ?>"
                               title="Ligar para locação">
                                <?php echo PHONE_LOCACAO; ?>
                            </a>
                        </p>
                        <p class="mb-2">
                            <i class="fas fa-envelope"></i>
                            <a href="mailto:<?php echo SITE_EMAIL; ?>"><?php echo SITE_EMAIL; ?></a>
                        </p>

                    </div>
                </div>
            </div>

            <!-- Linha Divisória -->
            <div class="divider"></div>

            <!-- Copyright -->
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="mb-0 copyright">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. Todos os direitos reservados.
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-0 developer-credit">
                        Desenvolvido por <a href="https://pixel12digital.com.br" target="_blank" rel="noopener noreferrer">Pixel12Digital</a>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="<?php echo getAssetPath('js/main.js'); ?>"></script>

    <!-- WhatsApp Float Buttons -->
    <div class="whatsapp-float" role="complementary" aria-label="Botões de contato rápido">
        <!-- Botão principal de vendas -->
        <a href="https://wa.me/<?php echo PHONE_WHATSAPP_VENDA; ?>?text=Olá! Gostaria de saber mais sobre imóveis para compra." 
           target="_blank" 
           class="whatsapp-btn whatsapp-venda" 
           title="WhatsApp Vendas - Abrir conversa para compra de imóveis"
           aria-label="Abrir WhatsApp para vendas de imóveis. Número: <?php echo PHONE_VENDA; ?>"
           role="button">
            <i class="fab fa-whatsapp" aria-hidden="true"></i>
            <span class="whatsapp-label" aria-label="Vendas">Vendas</span>
        </a>
        
        <!-- Botão de locação -->
        <a href="https://wa.me/<?php echo PHONE_WHATSAPP_LOCACAO; ?>?text=Olá! Gostaria de saber mais sobre imóveis para aluguel." 
           target="_blank" 
           class="whatsapp-btn whatsapp-locacao" 
           title="WhatsApp Locação - Abrir conversa para aluguel de imóveis"
           aria-label="Abrir WhatsApp para locação de imóveis. Número: <?php echo PHONE_LOCACAO; ?>"
           role="button">
            <i class="fas fa-key" aria-hidden="true"></i>
            <span class="whatsapp-label" aria-label="Locação">Locação</span>
        </a>
    </div>
</body>
</html>
