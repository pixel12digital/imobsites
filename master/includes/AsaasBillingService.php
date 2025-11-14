<?php
/**
 * AsaasBillingService
 *
 * Serviço centralizado para gerenciar billing no Asaas:
 * - Garantir existência de customer
 * - Criar cobranças pré-pagas (prepaid_parceled)
 * - Criar assinaturas recorrentes (recurring_monthly)
 *
 * Suporta:
 * - Cartão de crédito (com parcelamento)
 * - Pix
 * - Boleto
 */

declare(strict_types=1);

require_once __DIR__ . '/AsaasClient.php';

if (!function_exists('buildAsaasExternalReference')) {
    /**
     * Monta a referência externa usada para amarrar pedido ↔ cobrança no Asaas.
     *
     * @param int $orderId
     * @return string
     */
    function buildAsaasExternalReference(int $orderId): string
    {
        return 'order:' . $orderId;
    }
}

if (!function_exists('formatPhoneForAsaas')) {
    /**
     * Formata telefone brasileiro para o formato internacional esperado pelo Asaas.
     * Formato esperado: +55 + DDD + número (ex: +5547999999999)
     *
     * @param string|null $phone
     * @return string|null
     */
    function formatPhoneForAsaas(?string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }

        $phoneDigits = preg_replace('/\D+/', '', (string)$phone);
        error_log('[asaas.phone.format] Telefone original: ' . $phone . ' | Dígitos: ' . $phoneDigits);
        
        // Remove código do país se já estiver presente (55)
        if (substr($phoneDigits, 0, 2) === '55' && strlen($phoneDigits) > 11) {
            $phoneDigits = substr($phoneDigits, 2);
            error_log('[asaas.phone.format] Removido código 55, dígitos restantes: ' . $phoneDigits);
        }
        
        // Valida se tem DDD + número (10 ou 11 dígitos)
        if (strlen($phoneDigits) >= 10 && strlen($phoneDigits) <= 11) {
            // Formato: +55 + DDD + número
            $formatted = '+55' . $phoneDigits;
            error_log('[asaas.phone.format] Telefone formatado: ' . $formatted);
            return $formatted;
        }
        
        error_log('[asaas.phone.format] Telefone inválido (deve ter 10 ou 11 dígitos): ' . $phoneDigits . ' (tamanho: ' . strlen($phoneDigits) . ')');
        return null;
    }
}

if (!function_exists('ensureCustomerForOrder')) {
    /**
     * Garante que existe um customer no Asaas para o pedido.
     * Busca por e-mail/CPF ou cria um novo.
     *
     * @param array<string,mixed> $orderData
     * @return string ID do customer no Asaas
     */
    function ensureCustomerForOrder(array $orderData): string
    {
        global $pdo;

        $customerEmail = (string)($orderData['customer_email'] ?? '');
        $customerCpfCnpj = isset($orderData['customer_cpf_cnpj']) ? preg_replace('/\D+/', '', (string)$orderData['customer_cpf_cnpj']) : null;

        if ($customerEmail === '') {
            throw new InvalidArgumentException('E-mail do cliente é obrigatório para criar customer no Asaas.');
        }

        // Verifica se já existe asaas_customer_id no pedido
        if (!empty($orderData['asaas_customer_id'])) {
            return (string)$orderData['asaas_customer_id'];
        }

        // Tenta buscar customer existente no Asaas por e-mail
        $existingCustomer = asaasFindCustomerByEmail($customerEmail);

        if (is_array($existingCustomer) && isset($existingCustomer['id'])) {
            $customerId = (string)$existingCustomer['id'];

            // Verifica se o customer precisa ser atualizado com CPF/CNPJ
            $existingCpfCnpj = $existingCustomer['cpfCnpj'] ?? null;
            $needsUpdate = false;
            $updatePayload = [];

            // Verifica se precisa atualizar CPF/CNPJ
            if (($existingCpfCnpj === null || $existingCpfCnpj === '') && 
                $customerCpfCnpj !== null && $customerCpfCnpj !== '') {
                $updatePayload['cpfCnpj'] = $customerCpfCnpj;
                $needsUpdate = true;
            }

            // Garante que notificationDisabled está habilitado
            $existingNotificationsDisabled = $existingCustomer['notificationsDisabled'] ?? false;
            if ($existingNotificationsDisabled !== true) {
                $updatePayload['notificationsDisabled'] = true;
                $needsUpdate = true;
            }

            // Atualiza o customer se necessário
            if ($needsUpdate) {
                try {
                    asaasUpdateCustomer($customerId, $updatePayload);
                    if (isset($updatePayload['cpfCnpj'])) {
                        error_log('[asaas.customer.update] CPF/CNPJ adicionado ao customer existente: ' . substr($customerCpfCnpj, 0, 3) . '***');
                    }
                    if (isset($updatePayload['notificationsDisabled'])) {
                        error_log('[asaas.customer] notificationDisabled=true aplicado para customer existente ' . substr($customerId, 0, 20));
                    }
                } catch (Throwable $e) {
                    error_log('[asaas.customer.update.error] Falha ao atualizar customer: ' . $e->getMessage());
                    // Continua mesmo se a atualização falhar
                }
            }

            // Atualiza o pedido com o customer_id encontrado
            if (isset($orderData['id'])) {
                update('orders', [
                    'asaas_customer_id' => $customerId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [(int)$orderData['id']]);
            }

            return $customerId;
        }

        // Cria novo customer no Asaas
        $orderId = (int)($orderData['id'] ?? 0);
        $externalReference = $orderId > 0 ? buildAsaasExternalReference($orderId) : null;

        // Formata telefone para formato internacional do Asaas (+55 + DDD + número)
        $mobilePhone = formatPhoneForAsaas($orderData['customer_whatsapp'] ?? null);
        
        error_log('[asaas.customer.create] Dados do payload: name=' . ($orderData['customer_name'] ?? 'NULL') . ' | email=' . $customerEmail . ' | mobilePhone=' . ($mobilePhone ?? 'NULL'));

        $customerPayload = [
            'name' => (string)($orderData['customer_name'] ?? ''),
            'email' => $customerEmail,
            'mobilePhone' => $mobilePhone,
            // Desabilita notificações padrão do Asaas para este cliente
            // Todas as notificações relacionadas a cobrança serão enviadas pelo sistema próprio (NotificationService)
            'notificationsDisabled' => true,
        ];
        
        // Log do payload completo antes de enviar
        error_log('[asaas.customer.create] Payload completo: ' . json_encode($customerPayload, JSON_UNESCAPED_UNICODE));

        if ($customerCpfCnpj !== null && $customerCpfCnpj !== '') {
            $customerPayload['cpfCnpj'] = $customerCpfCnpj;
        }

        if ($externalReference !== null) {
            $customerPayload['externalReference'] = $externalReference;
        }

        try {
            $customerResponse = asaasCreateCustomer($customerPayload);
            $customerId = (string)($customerResponse['id'] ?? '');

            if ($customerId === '') {
                throw new RuntimeException('Não foi possível criar o cliente no Asaas.');
            }

            // Atualiza o pedido com o customer_id criado
            if ($orderId > 0) {
                update('orders', [
                    'asaas_customer_id' => $customerId,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$orderId]);
            }

            error_log('[asaas.billing] Customer criado no Asaas: ' . $customerId . ' para orderId=' . $orderId);
            error_log('[asaas.customer] notificationDisabled=true aplicado para customer criado ' . substr($customerId, 0, 20));

            return $customerId;
        } catch (Throwable $e) {
            error_log('[asaas.billing.error] Falha ao criar customer: ' . $e->getMessage());
            throw new RuntimeException('Não foi possível criar o cliente no Asaas: ' . $e->getMessage(), 0, $e);
        }
    }
}

if (!function_exists('createPrepaidPayment')) {
    /**
     * Cria uma cobrança pré-paga no Asaas (modelo B: planos parcelados).
     *
     * @param array<string,mixed> $orderData
     * @param array<string,mixed> $planData
     * @param string $customerId ID do customer no Asaas
     * @param string $paymentMethod 'credit_card', 'pix' ou 'boleto'
     * @param array<string,mixed>|null $cardData Dados do cartão (quando paymentMethod = 'credit_card')
     * @return array<string,mixed>
     */
    function createPrepaidPayment(
        array $orderData,
        array $planData,
        string $customerId,
        string $paymentMethod,
        ?array $cardData = null
    ): array {
        $orderId = (int)($orderData['id'] ?? 0);
        $externalReference = buildAsaasExternalReference($orderId);
        $planName = (string)($planData['name'] ?? $orderData['plan_code'] ?? 'Plano ImobSites');
        $totalAmount = (float)($orderData['total_amount'] ?? 0.0);
        
        // Log para debug do orderData recebido (sem CPF completo por segurança)
        $maskedCpf = 'NULL';
        if (isset($orderData['customer_cpf_cnpj']) && $orderData['customer_cpf_cnpj'] !== null) {
            $cpf = preg_replace('/\D+/', '', (string)$orderData['customer_cpf_cnpj']);
            $maskedCpf = substr($cpf, 0, 3) . '***' . substr($cpf, -2);
        }
        error_log('[asaas.payment.create] orderData recebido - customer_cpf_cnpj: ' . $maskedCpf);

        if ($totalAmount <= 0) {
            throw new InvalidArgumentException('Valor do pedido inválido para cobrança Asaas.');
        }

        // Determina billingType conforme método de pagamento
        $billingTypeMap = [
            'credit_card' => 'CREDIT_CARD',
            'pix' => 'PIX',
            'boleto' => 'BOLETO',
        ];

        $billingType = $billingTypeMap[$paymentMethod] ?? 'UNDEFINED';

        if ($billingType === 'UNDEFINED') {
            throw new InvalidArgumentException('Método de pagamento inválido: ' . $paymentMethod);
        }

        // Calcula data de vencimento (padrão: 3 dias a partir de hoje)
        $dueDate = date('Y-m-d', strtotime('+3 days'));
        
        // Monta payload base
        $paymentPayload = [
            'customer' => $customerId,
            'billingType' => $billingType,
            'value' => $totalAmount,
            'description' => sprintf('ImobSites - %s - Pedido #%d', $planName, $orderId),
            'externalReference' => $externalReference,
            'dueDate' => $dueDate, // Obrigatório para PIX e Boleto
        ];

        // Para PIX e Boleto, o Asaas pode exigir CPF/CNPJ do cliente
        // Adiciona CPF/CNPJ se disponível no orderData
        if (in_array($paymentMethod, ['pix', 'boleto'], true)) {
            $customerCpfCnpj = isset($orderData['customer_cpf_cnpj']) ? preg_replace('/\D+/', '', (string)$orderData['customer_cpf_cnpj']) : null;
            
            // Log para debug
            error_log(sprintf(
                '[asaas.payment.create] Verificando CPF/CNPJ para %s: orderData[customer_cpf_cnpj]=%s | resultado=%s',
                $paymentMethod,
                $orderData['customer_cpf_cnpj'] ?? 'NULL',
                $customerCpfCnpj ?? 'NULL'
            ));
            
            if ($customerCpfCnpj !== null && $customerCpfCnpj !== '') {
                $paymentPayload['cpfCnpj'] = $customerCpfCnpj;
                error_log('[asaas.payment.create] CPF/CNPJ adicionado ao payload PIX/Boleto: ' . substr($customerCpfCnpj, 0, 3) . '***');
            } else {
                error_log('[asaas.payment.create] AVISO: CPF/CNPJ não fornecido para ' . $paymentMethod . ' - Asaas pode rejeitar');
                error_log('[asaas.payment.create] DEBUG orderData keys: ' . implode(', ', array_keys($orderData)));
            }
        }

        // Para cartão de crédito, adiciona dados do cartão e parcelamento
        if ($paymentMethod === 'credit_card' && is_array($cardData)) {
            $installments = isset($orderData['payment_installments']) ? (int)$orderData['payment_installments'] : 1;
            if ($installments > 1) {
                $paymentPayload['installmentCount'] = $installments;
                $paymentPayload['installmentValue'] = round($totalAmount / $installments, 2);
            }

            // Normaliza campo do número do cartão (aceita 'number' ou 'cardNumber')
            $cardNumber = $cardData['cardNumber'] ?? $cardData['number'] ?? null;

            // Dados do cartão
            $creditCard = [];
            if ($cardNumber !== null) {
                $creditCard['number'] = preg_replace('/\D+/', '', (string)$cardNumber);
            }
            if (isset($cardData['expiryMonth'])) {
                $creditCard['expiryMonth'] = str_pad((string)$cardData['expiryMonth'], 2, '0', STR_PAD_LEFT);
            }
            if (isset($cardData['expiryYear'])) {
                $creditCard['expiryYear'] = (string)$cardData['expiryYear'];
            }
            if (isset($cardData['ccv'])) {
                $creditCard['ccv'] = (string)$cardData['ccv'];
            }
            
            // O Asaas pode exigir holderName também no objeto creditCard
            $holderName = $cardData['holderName'] ?? $orderData['customer_name'] ?? null;
            if ($holderName !== null) {
                $creditCard['holderName'] = (string)$holderName;
            }

            // Dados do titular (obrigatórios para cartão de crédito)
            $holderInfo = [];
            
            // Nome do titular (obrigatório)
            $holderName = $cardData['holderName'] ?? $orderData['customer_name'] ?? null;
            if ($holderName !== null) {
                $holderInfo['name'] = (string)$holderName;
            }
            
            // Email do titular (obrigatório)
            if (isset($orderData['customer_email'])) {
                $holderInfo['email'] = (string)$orderData['customer_email'];
            }
            
            // CPF/CNPJ do titular (obrigatório)
            if (isset($cardData['cpfCnpj']) || isset($orderData['customer_cpf_cnpj'])) {
                $holderInfo['cpfCnpj'] = preg_replace('/\D+/', '', (string)($cardData['cpfCnpj'] ?? $orderData['customer_cpf_cnpj'] ?? ''));
            }
            
            // Telefone do titular (formata para formato internacional)
            if (isset($cardData['phone'])) {
                $holderInfo['phone'] = formatPhoneForAsaas($cardData['phone']);
            } elseif (isset($orderData['customer_whatsapp'])) {
                $holderInfo['phone'] = formatPhoneForAsaas($orderData['customer_whatsapp']);
            }
            
            // Celular do titular
            if (isset($orderData['customer_whatsapp'])) {
                $holderInfo['mobilePhone'] = formatPhoneForAsaas($orderData['customer_whatsapp']);
            }
            if (isset($cardData['postalCode'])) {
                $holderInfo['postalCode'] = preg_replace('/\D+/', '', (string)$cardData['postalCode']);
            }
            if (isset($cardData['addressNumber'])) {
                $holderInfo['addressNumber'] = (string)$cardData['addressNumber'];
            }
            if (isset($cardData['address']) || isset($cardData['street'])) {
                $holderInfo['address'] = (string)($cardData['address'] ?? $cardData['street'] ?? '');
            }
            if (isset($cardData['addressComplement'])) {
                $holderInfo['addressComplement'] = (string)$cardData['addressComplement'];
            }
            if (isset($cardData['province']) || isset($cardData['neighborhood'])) {
                $holderInfo['province'] = (string)($cardData['province'] ?? $cardData['neighborhood'] ?? '');
            }
            if (isset($cardData['city'])) {
                $holderInfo['city'] = (string)$cardData['city'];
            }
            if (isset($cardData['state'])) {
                $holderInfo['state'] = strtoupper(substr((string)$cardData['state'], 0, 2));
            }
            if (isset($cardData['phone'])) {
                $holderInfo['phone'] = preg_replace('/\D+/', '', (string)$cardData['phone']);
            }

            if (!empty($creditCard)) {
                $paymentPayload['creditCard'] = $creditCard;
            }
            
            // Log dos dados do cartão e titular antes de enviar (sem dados sensíveis)
            // Não logar número completo do cartão, CVV ou CPF completo
            $logCard = $creditCard;
            if (isset($logCard['number'])) {
                $logCard['number'] = substr($logCard['number'], 0, 4) . '****' . substr($logCard['number'], -4);
            }
            if (isset($logCard['ccv'])) {
                $logCard['ccv'] = '***';
            }
            $logHolder = $holderInfo;
            if (isset($logHolder['cpfCnpj'])) {
                $cpf = $logHolder['cpfCnpj'];
                $logHolder['cpfCnpj'] = substr($cpf, 0, 3) . '***' . substr($cpf, -2);
            }
            error_log('[asaas.billing] Dados do cartão (mascarado): ' . json_encode($logCard, JSON_UNESCAPED_UNICODE));
            error_log('[asaas.billing] Dados do titular (mascarado): ' . json_encode($logHolder, JSON_UNESCAPED_UNICODE));
            
            // O Asaas exige creditCardHolderInfo quando usa cartão de crédito
            // Garante que pelo menos name, email e cpfCnpj estão presentes
            if (empty($holderInfo['name']) || empty($holderInfo['email']) || empty($holderInfo['cpfCnpj'])) {
                $maskedCpf = 'NULL';
                if (isset($holderInfo['cpfCnpj']) && $holderInfo['cpfCnpj'] !== '') {
                    $cpf = (string)$holderInfo['cpfCnpj'];
                    $maskedCpf = substr($cpf, 0, 3) . '***' . substr($cpf, -2);
                }
                error_log('[asaas.billing] AVISO: Dados do titular incompletos. name=' . ($holderInfo['name'] ?? 'NULL') . ' email=' . ($holderInfo['email'] ?? 'NULL') . ' cpfCnpj=' . $maskedCpf);
            }
            
            if (!empty($holderInfo)) {
                $paymentPayload['creditCardHolderInfo'] = $holderInfo;
            }
        }

        error_log(sprintf(
            '[asaas.billing] Criando cobrança pré-paga: orderId=%d method=%s amount=%.2f installments=%d',
            $orderId,
            $paymentMethod,
            $totalAmount,
            $orderData['payment_installments'] ?? 1
        ));
        
        // Log do payload completo antes de enviar
        error_log('[asaas.billing] Payload completo da cobrança: ' . json_encode($paymentPayload, JSON_UNESCAPED_UNICODE));

        try {
            $paymentResponse = asaasCreatePayment($paymentPayload);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[asaas.billing.error] Falha ao criar cobrança orderId=%d: %s',
                $orderId,
                $e->getMessage()
            ));
            throw $e;
        }

        $providerPaymentId = (string)($paymentResponse['id'] ?? '');
        if ($providerPaymentId === '') {
            throw new RuntimeException('Cobrança criada no Asaas sem identificador.');
        }

        // Para PIX, busca dados do QR Code via endpoint específico
        if ($paymentMethod === 'pix') {
            // Aguarda um momento para o Asaas processar o PIX
            sleep(1);
            
            // Tenta obter dados do QR Code PIX via endpoint específico
            try {
                $pixQrCodeResponse = asaasGetPixQrCode($providerPaymentId);
                
                if (is_array($pixQrCodeResponse) && !empty($pixQrCodeResponse)) {
                    // Atualiza paymentResponse com dados do QR Code
                    $paymentResponse['pixTransaction'] = $pixQrCodeResponse;
                    error_log('[asaas.pix.qr] Dados do QR Code PIX obtidos com sucesso para payment ' . substr($providerPaymentId, 0, 20));
                }
            } catch (Throwable $e) {
                // Se falhar, tenta obter da resposta original ou busca o payment novamente
                error_log('[asaas.pix.qr.error] Falha ao obter Pix QR para payment ' . substr($providerPaymentId, 0, 20) . ': ' . $e->getMessage());
                
                // Fallback: tenta buscar da resposta original
                $pixTransaction = $paymentResponse['pixTransaction'] ?? null;
                if ($pixTransaction === null) {
                    // Última tentativa: busca o payment completo (até 3 tentativas)
                    $maxAttempts = 3;
                    $attempt = 0;
                    
                    while ($attempt < $maxAttempts) {
                        try {
                            sleep(2);
                            $attempt++;
                            error_log("[asaas.pix.qr] Tentativa $attempt de $maxAttempts para buscar dados do PIX via GET /payments...");
                            
                            $paymentResponse = asaasGetPayment($providerPaymentId);
                            $pixTransaction = $paymentResponse['pixTransaction'] ?? null;
                            
                            if ($pixTransaction !== null && is_array($pixTransaction) && !empty($pixTransaction)) {
                                error_log('[asaas.pix.qr] Dados do PIX encontrados na tentativa ' . $attempt);
                                break;
                            }
                        } catch (Throwable $retryError) {
                            error_log('[asaas.pix.qr] Erro ao buscar dados do PIX (tentativa ' . $attempt . '): ' . $retryError->getMessage());
                            if ($attempt >= $maxAttempts) {
                                break;
                            }
                        }
                    }
                }
            }
        }

        // Para Boleto, busca linha digitável via endpoint específico
        if ($paymentMethod === 'boleto') {
            // Aguarda um momento para o Asaas processar o boleto
            sleep(1);
            
            try {
                $boletoLineResponse = asaasGetBoletoIdentificationField($providerPaymentId);
                
                if (is_array($boletoLineResponse) && !empty($boletoLineResponse)) {
                    $identificationField = $boletoLineResponse['identificationField'] ?? null;
                    
                    if ($identificationField !== null && $identificationField !== '') {
                        // Adiciona linha digitável ao paymentResponse para ser incluída no resultado
                        $paymentResponse['identificationField'] = $identificationField;
                        error_log('[asaas.boleto.line] Linha digitável obtida com sucesso para payment ' . substr($providerPaymentId, 0, 20));
                    }
                }
            } catch (Throwable $e) {
                // Se falhar, loga mas não impede a criação do pedido
                error_log('[asaas.boleto.line.error] Falha ao obter linha digitável para payment ' . substr($providerPaymentId, 0, 20) . ': ' . $e->getMessage());
            }
        }

        // Extrai dados de resposta conforme método de pagamento
        $result = [
            'provider' => 'asaas',
            'provider_payment_id' => $providerPaymentId,
            'payment_method' => $paymentMethod,
            'status' => strtolower((string)($paymentResponse['status'] ?? 'PENDING')),
            'raw_response' => $paymentResponse,
        ];

        // URL de pagamento (boleto)
        $paymentUrl = $paymentResponse['invoiceUrl'] ?? $paymentResponse['paymentUrl'] ?? $paymentResponse['bankSlipUrl'] ?? $paymentResponse['boletoUrl'] ?? null;
        if ($paymentUrl) {
            $result['payment_url'] = $paymentUrl;
        }

        // Dados do Pix
        if ($paymentMethod === 'pix') {
            // O Asaas retorna os dados do PIX em pixTransaction quando disponível
            $pixTransaction = $paymentResponse['pixTransaction'] ?? null;
            
            if (is_array($pixTransaction) && !empty($pixTransaction)) {
                // Dados do PIX estão disponíveis (obtidos via endpoint específico ou resposta original)
                $result['pix_payload'] = $pixTransaction['payload'] 
                    ?? $pixTransaction['pixCopiaECola']
                    ?? $pixTransaction['pixCopyPaste']
                    ?? null;
                
                // O encodedImage pode vir em base64 ou como URL
                $result['pix_qr_code_image'] = $pixTransaction['encodedImage'] 
                    ?? $pixTransaction['qrCodeImage']
                    ?? $pixTransaction['qrCode']
                    ?? null;
                
                // Se o encodedImage não incluir prefixo data:, adiciona (se for base64)
                if ($result['pix_qr_code_image'] !== null && strpos($result['pix_qr_code_image'], 'data:') !== 0) {
                    // Verifica se parece ser base64 (sem prefixo)
                    if (preg_match('/^[A-Za-z0-9+\/]+=*$/', $result['pix_qr_code_image'])) {
                        $result['pix_qr_code_image'] = 'data:image/png;base64,' . $result['pix_qr_code_image'];
                    }
                }
            } else {
                // Fallback: tenta campos diretos na resposta (formato alternativo)
                $result['pix_payload'] = $paymentResponse['pixPayload'] 
                    ?? $paymentResponse['pixCopiaECola'] 
                    ?? $paymentResponse['pixCopyPaste']
                    ?? null;
                
                $result['pix_qr_code_image'] = $paymentResponse['pixQrCodeImage'] 
                    ?? $paymentResponse['pixQrCode']
                    ?? null;
            }
            
            // URL do PIX (sempre disponível, mesmo quando pixTransaction é null)
            if (isset($paymentResponse['invoiceUrl'])) {
                $result['payment_url'] = $paymentResponse['invoiceUrl'];
            } elseif (isset($paymentResponse['paymentLink'])) {
                $result['payment_url'] = $paymentResponse['paymentLink'];
            }
            
            // Log de sucesso ou aviso
            if ($result['pix_payload'] !== null) {
                error_log('[asaas.payment.details.info] Dados do PIX obtidos com sucesso para payment ' . substr($providerPaymentId, 0, 20));
            } else {
                error_log('[asaas.payment.details.info] Dados do PIX ainda não disponíveis. O cliente pode usar a payment_url para acessar a página de pagamento.');
            }
        }

        // Dados do boleto
        if ($paymentMethod === 'boleto') {
            $result['boleto_url'] = $paymentUrl;
            $result['boleto_barcode'] = $paymentResponse['barcode'] ?? $paymentResponse['nossoNumero'] ?? null;
            
            // Linha digitável (obtida via endpoint específico)
            $result['boleto_line'] = $paymentResponse['identificationField'] ?? null;
            
            // Log de sucesso ou aviso
            if ($result['boleto_line'] !== null) {
                error_log('[asaas.payment.details.info] Linha digitável do boleto obtida com sucesso para payment ' . substr($providerPaymentId, 0, 20));
            } else {
                error_log('[asaas.payment.details.info] Linha digitável do boleto não disponível. O cliente pode usar a payment_url para acessar o boleto.');
            }
        }

        error_log('[asaas.billing] Cobrança pré-paga criada: paymentId=' . $providerPaymentId . ' orderId=' . $orderId);

        return $result;
    }
}

if (!function_exists('createRecurringSubscription')) {
    /**
     * Cria uma assinatura recorrente no Asaas (modelo A: plano mensal).
     *
     * @param array<string,mixed> $orderData
     * @param array<string,mixed> $planData
     * @param string $customerId ID do customer no Asaas
     * @param string $paymentMethod 'credit_card' (outros métodos podem ser adicionados depois)
     * @param array<string,mixed>|null $cardData Dados do cartão
     * @return array<string,mixed>
     */
    function createRecurringSubscription(
        array $orderData,
        array $planData,
        string $customerId,
        string $paymentMethod,
        ?array $cardData = null
    ): array {
        $orderId = (int)($orderData['id'] ?? 0);
        $externalReference = buildAsaasExternalReference($orderId);
        $planName = (string)($planData['name'] ?? $orderData['plan_code'] ?? 'Plano ImobSites');
        $monthlyValue = (float)($planData['price_per_month'] ?? $orderData['total_amount'] ?? 0.0);

        if ($monthlyValue <= 0) {
            throw new InvalidArgumentException('Valor mensal do plano inválido para assinatura Asaas.');
        }

        if ($paymentMethod !== 'credit_card') {
            throw new InvalidArgumentException('Assinaturas recorrentes atualmente suportam apenas cartão de crédito.');
        }

        if (!is_array($cardData) || empty($cardData)) {
            throw new InvalidArgumentException('Dados do cartão são obrigatórios para assinatura recorrente.');
        }

        // Calcula próxima data de vencimento (hoje + 1 dia)
        $nextDueDate = date('Y-m-d', strtotime('+1 day'));

        // Monta payload da assinatura
        $subscriptionPayload = [
            'customer' => $customerId,
            'billingType' => 'CREDIT_CARD',
            'value' => $monthlyValue,
            'nextDueDate' => $nextDueDate,
            'cycle' => 'MONTHLY',
            'description' => sprintf('ImobSites - %s - Assinatura Mensal', $planName),
            'externalReference' => $externalReference,
        ];

        // Normaliza campo do número do cartão (aceita 'number' ou 'cardNumber')
        $cardNumber = $cardData['cardNumber'] ?? $cardData['number'] ?? null;

        // Dados do cartão
        $creditCard = [];
        if ($cardNumber !== null) {
            $creditCard['number'] = preg_replace('/\D+/', '', (string)$cardNumber);
        }
        if (isset($cardData['expiryMonth'])) {
            $creditCard['expiryMonth'] = str_pad((string)$cardData['expiryMonth'], 2, '0', STR_PAD_LEFT);
        }
        if (isset($cardData['expiryYear'])) {
            $creditCard['expiryYear'] = (string)$cardData['expiryYear'];
        }
        if (isset($cardData['ccv'])) {
            $creditCard['ccv'] = (string)$cardData['ccv'];
        }

        // Dados do titular
        $holderInfo = [];
        if (isset($cardData['holderName'])) {
            $holderInfo['name'] = (string)$cardData['holderName'];
        }
        if (isset($cardData['cpfCnpj']) || isset($orderData['customer_cpf_cnpj'])) {
            $holderInfo['cpfCnpj'] = preg_replace('/\D+/', '', (string)($cardData['cpfCnpj'] ?? $orderData['customer_cpf_cnpj'] ?? ''));
        }
        if (isset($cardData['postalCode'])) {
            $holderInfo['postalCode'] = preg_replace('/\D+/', '', (string)$cardData['postalCode']);
        }
        if (isset($cardData['addressNumber'])) {
            $holderInfo['addressNumber'] = (string)$cardData['addressNumber'];
        }
        if (isset($cardData['address']) || isset($cardData['street'])) {
            $holderInfo['address'] = (string)($cardData['address'] ?? $cardData['street'] ?? '');
        }
        if (isset($cardData['addressComplement'])) {
            $holderInfo['addressComplement'] = (string)$cardData['addressComplement'];
        }
        if (isset($cardData['province']) || isset($cardData['neighborhood'])) {
            $holderInfo['province'] = (string)($cardData['province'] ?? $cardData['neighborhood'] ?? '');
        }
        if (isset($cardData['city'])) {
            $holderInfo['city'] = (string)$cardData['city'];
        }
        if (isset($cardData['state'])) {
            $holderInfo['state'] = strtoupper(substr((string)$cardData['state'], 0, 2));
        }
        if (isset($cardData['phone'])) {
            $holderInfo['phone'] = preg_replace('/\D+/', '', (string)$cardData['phone']);
        }

        if (!empty($creditCard)) {
            $subscriptionPayload['creditCard'] = $creditCard;
        }
        if (!empty($holderInfo)) {
            $subscriptionPayload['creditCardHolderInfo'] = $holderInfo;
        }

        error_log(sprintf(
            '[asaas.billing] Criando assinatura recorrente: orderId=%d value=%.2f nextDueDate=%s',
            $orderId,
            $monthlyValue,
            $nextDueDate
        ));

        try {
            $subscriptionResponse = asaasCreateSubscription($subscriptionPayload);
        } catch (Throwable $e) {
            error_log(sprintf(
                '[asaas.billing.error] Falha ao criar assinatura orderId=%d: %s',
                $orderId,
                $e->getMessage()
            ));
            throw $e;
        }

        $providerSubscriptionId = (string)($subscriptionResponse['id'] ?? '');
        if ($providerSubscriptionId === '') {
            throw new RuntimeException('Assinatura criada no Asaas sem identificador.');
        }

        $result = [
            'provider' => 'asaas',
            'provider_subscription_id' => $providerSubscriptionId,
            'payment_method' => $paymentMethod,
            'subscription_status' => strtolower((string)($subscriptionResponse['status'] ?? 'ACTIVE')),
            'next_due_date' => $subscriptionResponse['nextDueDate'] ?? $nextDueDate,
            'raw_response' => $subscriptionResponse,
        ];

        error_log('[asaas.billing] Assinatura recorrente criada: subscriptionId=' . $providerSubscriptionId . ' orderId=' . $orderId);

        return $result;
    }
}

