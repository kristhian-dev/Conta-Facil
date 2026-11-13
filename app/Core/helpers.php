<?php

declare(strict_types=1);

/**
 * Funções auxiliares globais, usadas principalmente nas Views.
 */

defined('APP_ROOT') || exit;

use App\Core\Csrf;

/**
 * Lê um valor da configuração central usando notação de ponto.
 * Ex.: config('db.host'), config('auth.perfis_ativos', []).
 */
function config(string $chave, mixed $padrao = null): mixed
{
    static $config = null;
    $config ??= require APP_ROOT . '/config/config.php';

    $valor = $config;
    foreach (explode('.', $chave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $padrao;
        }
        $valor = $valor[$parte];
    }

    return $valor;
}

/**
 * Escapa texto para saída segura em HTML (proteção contra XSS).
 * Toda informação dinâmica exibida nas Views deve passar por aqui.
 */
function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Caminho base da aplicação na URL (ex.: "/conta_facil/").
 * Funciona tanto na raiz do servidor quanto em subpastas.
 */
function base_path(): string
{
    static $base = null;
    $base ??= rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/') . '/';

    return $base;
}

/**
 * Monta a URL de uma rota. Ex.: url('clientes/editar', ['id' => 5])
 * -> /conta_facil/index.php?r=clientes/editar&id=5
 */
function url(string $rota, array $parametros = []): string
{
    // A barra é válida em query strings; mantê-la deixa a URL legível.
    $query = str_replace('%2F', '/', http_build_query(['r' => $rota] + $parametros));

    return base_path() . 'index.php?' . $query;
}

/**
 * Redireciona para uma rota e encerra a requisição.
 */
function redirecionar(string $rota, array $parametros = []): never
{
    header('Location: ' . url($rota, $parametros));
    exit;
}

/**
 * URL de um arquivo estático em public/. Ex.: asset('css/app.css')
 * Inclui ?v=<data de modificação> para que o navegador busque a versão nova
 * sempre que o arquivo mudar (evita CSS/JS antigos em cache).
 */
function asset(string $caminho): string
{
    $caminho = ltrim($caminho, '/');
    $versao  = @filemtime(APP_ROOT . '/public/' . $caminho);

    return base_path() . 'public/' . $caminho . ($versao !== false ? '?v=' . $versao : '');
}

/**
 * Campo oculto com o token CSRF, para uso em todo formulário POST.
 */
function csrf_campo(): string
{
    return Csrf::campo();
}

/**
 * Rota da requisição atual (ex.: "clientes/editar"). Útil para o menu.
 */
function rota_atual(): string
{
    $rota = $_GET['r'] ?? '';

    return is_string($rota) && $rota !== '' ? trim($rota, '/') : config('app.rota_inicial');
}

// ---------------------------------------------------------------------
// Formatação
// ---------------------------------------------------------------------

/**
 * 11 dígitos -> 000.000.000-00 | 14 dígitos -> 00.000.000/0000-00.
 * Outros valores são devolvidos sem alteração.
 */
function formatar_cpf_cnpj(?string $valor): string
{
    $valor = (string) $valor;

    return match (true) {
        (bool) preg_match('/^\d{11}$/', $valor) => (string) preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $valor),
        (bool) preg_match('/^\d{14}$/', $valor) => (string) preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $valor),
        default => $valor,
    };
}

/**
 * Telefone com DDD: (86) 3222-1111 ou (86) 99999-1111.
 * Valores fora do padrão são devolvidos sem alteração.
 */
function formatar_telefone(?string $valor): string
{
    $digitos = (string) preg_replace('/\D/', '', (string) $valor);

    return match (strlen($digitos)) {
        10 => (string) preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digitos),
        11 => (string) preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digitos),
        default => (string) $valor,
    };
}

/**
 * Data/hora do banco (Y-m-d ou Y-m-d H:i:s) no formato brasileiro.
 */
function formatar_data(?string $valor, bool $comHora = false): string
{
    if ($valor === null || $valor === '') {
        return '—';
    }

    $timestamp = strtotime($valor);

    return $timestamp === false ? $valor : date($comHora ? 'd/m/Y H:i' : 'd/m/Y', $timestamp);
}

/**
 * Competência (AAAA-MM-01 ou AAAA-MM) no formato MM/AAAA.
 */
function formatar_competencia(?string $valor): string
{
    return preg_match('/^(\d{4})-(\d{2})/', (string) $valor, $m) ? "{$m[2]}/{$m[1]}" : '—';
}

/**
 * Valor decimal do banco (1234.50) como moeda: R$ 1.234,50.
 */
function formatar_moeda(string|float|null $valor): string
{
    return $valor === null || $valor === '' ? '—' : 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

/**
 * Tamanho em bytes legível: 950 B, 12,4 KB, 1,8 MB.
 */
function formatar_tamanho(int|string|null $bytes): string
{
    if ($bytes === null || $bytes === '') {
        return '—';
    }

    $bytes = (int) $bytes;

    return match (true) {
        $bytes >= 1024 ** 2 => number_format($bytes / 1024 ** 2, 1, ',', '.') . ' MB',
        $bytes >= 1024      => number_format($bytes / 1024, 1, ',', '.') . ' KB',
        default             => $bytes . ' B',
    };
}

/**
 * "1 obrigação", "3 obrigações"; quantidade zero devolve ''.
 */
function plural(int $quantidade, string $singular, string $pluralTexto): string
{
    if ($quantidade === 0) {
        return '';
    }

    return $quantidade . ' ' . ($quantidade === 1 ? $singular : $pluralTexto);
}

// ---------------------------------------------------------------------
// Formulários
// ---------------------------------------------------------------------

/**
 * Lista de <option> já escapada.
 *
 * @param array<string|int, string> $opcoes valor => rótulo
 */
function opcoes(array $opcoes, string|int|null $selecionado): string
{
    $html = '';
    foreach ($opcoes as $valor => $rotulo) {
        $marcado = (string) $valor === (string) $selecionado ? ' selected' : '';
        $html .= '<option value="' . e($valor) . '"' . $marcado . '>' . e($rotulo) . '</option>';
    }

    return $html;
}

/**
 * Mensagem de erro de um campo (vazio quando não há erro).
 *
 * @param array<string, string> $erros
 */
function erro_campo(array $erros, string $campo): string
{
    return isset($erros[$campo])
        ? '<p class="campo-erro" id="' . e($campo) . '-erro">' . e($erros[$campo]) . '</p>'
        : '';
}

/**
 * Atributos de acessibilidade para um campo com erro.
 *
 * @param array<string, string> $erros
 */
function atributos_erro(array $erros, string $campo): string
{
    return isset($erros[$campo])
        ? ' aria-invalid="true" aria-describedby="' . e($campo) . '-erro"'
        : '';
}
