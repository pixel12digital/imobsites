<?php
if (!function_exists('cleanInput')) {
    function cleanInput($data)
    {
        $data = trim($data);
        $data = stripslashes($data);
        return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    }
}

function generateRandomPassword($length = 12): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

function formatDateTime($datetime): string
{
    return date('d/m/Y H:i', strtotime($datetime));
}

function generateSlug($string): string
{
    $string = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
    $string = preg_replace('/[^a-zA-Z0-9\s-]/', '', $string);
    $string = strtolower(trim($string));
    $string = preg_replace('/[\s-]+/', '-', $string);
    return $string;
}

if (!function_exists('validateAndFormatPhone')) {
    /**
     * Valida um telefone brasileiro garantindo DDD + número (10 ou 11 dígitos)
     * e retorna no formato (DD) XXXXX-XXXX ou (DD) XXXX-XXXX.
     *
     * @param string|null $phone
     * @param string $label
     * @return string
     * @throws Exception
     */
    function validateAndFormatPhone(?string $phone, string $label = 'Telefone'): string
    {
        $phone = trim((string)$phone);

        if ($phone === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            throw new Exception($label . ' deve conter dígitos, incluindo o DDD.');
        }

        $length = strlen($digits);

        if ($length === 10) {
            $ddd = substr($digits, 0, 2);
            $first = substr($digits, 2, 4);
            $second = substr($digits, 6, 4);

            return sprintf('(%s) %s-%s', $ddd, $first, $second);
        }

        if ($length === 11) {
            $ddd = substr($digits, 0, 2);
            $first = substr($digits, 2, 5);
            $second = substr($digits, 7, 4);

            return sprintf('(%s) %s-%s', $ddd, $first, $second);
        }

        throw new Exception($label . ' deve estar no formato DDD + número (10 ou 11 dígitos).');
    }
}

if (!function_exists('formatPhoneIfPossible')) {
    /**
     * Tenta formatar um telefone reutilizando a validação; mantém original se inválido.
     *
     * @param string|null $phone
     * @return string
     */
    function formatPhoneIfPossible(?string $phone): string
    {
        $phone = trim((string)$phone);
        if ($phone === '') {
            return '';
        }

        try {
            return validateAndFormatPhone($phone);
        } catch (Exception $e) {
            return $phone;
        }
    }
}

