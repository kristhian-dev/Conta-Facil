<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

use finfo;
use RuntimeException;
use ZipArchive;

/**
 * Recebimento e armazenamento seguro de arquivos.
 *
 * Validação (nada do que o navegador informa é considerado confiável):
 *   1. código de erro do PHP e is_uploaded_file();
 *   2. tamanho real do arquivo (maior que zero e dentro do limite);
 *   3. extensão do nome original em lista fechada;
 *   4. MIME detectado pelo CONTEÚDO (finfo), compatível com a extensão;
 *   5. verificação estrutural do conteúdo (assinatura do PDF, imagem
 *      decodificável, XML bem formado e sem DOCTYPE, XLSX com estrutura válida).
 *
 * Armazenamento:
 *   - nome aleatório (32 hex + extensão normalizada), sem relação com o nome original;
 *   - somente no diretório configurado (storage/uploads), bloqueado para acesso web;
 *   - o caminho de leitura é montado apenas a partir de nomes que seguem o padrão
 *     aleatório, o que impede path traversal.
 */
final class Upload
{
    /** Extensão aceita => MIME types aceitos (detectados pelo conteúdo). */
    public const TIPOS = [
        'pdf'  => ['application/pdf'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
        'xml'  => ['text/xml', 'application/xml'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    ];

    /** MIME usado ao entregar o arquivo, pela extensão armazenada. */
    private const MIME_ENTREGA = [
        'pdf'  => 'application/pdf',
        'jpg'  => 'image/jpeg',
        'png'  => 'image/png',
        'xml'  => 'application/xml',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const PADRAO_NOME = '/^[a-f0-9]{32}\.(pdf|jpg|png|xml|xlsx)$/';

    public static function tamanhoMaximo(): int
    {
        return min(
            (int) config('uploads.tamanho_maximo'),
            self::iniParaBytes((string) ini_get('upload_max_filesize')),
            self::iniParaBytes((string) ini_get('post_max_size'))
        );
    }

    public static function extensoesAceitas(): string
    {
        return '.' . implode(',.', array_keys(self::TIPOS));
    }

    /**
     * Valida um item de $_FILES.
     *
     * @return array{erro: string}|array{erro: null, tmp: string, nome_original: string,
     *               extensao: string, mime: string, tamanho: int}
     */
    public static function validar(mixed $arquivo): array
    {
        if (!is_array($arquivo) || !isset($arquivo['error']) || is_array($arquivo['error'])) {
            return ['erro' => 'Selecione o arquivo do documento.'];
        }

        $limite = formatar_tamanho(self::tamanhoMaximo());

        $erroEnvio = match ((int) $arquivo['error']) {
            UPLOAD_ERR_OK                           => null,
            UPLOAD_ERR_NO_FILE                      => 'Selecione o arquivo do documento.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "O arquivo excede o tamanho máximo de {$limite}.",
            UPLOAD_ERR_PARTIAL                      => 'O envio do arquivo foi interrompido. Tente novamente.',
            default                                 => 'Não foi possível receber o arquivo. Tente novamente.',
        };
        if ($erroEnvio !== null) {
            return ['erro' => $erroEnvio];
        }

        $tmp = (string) $arquivo['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            return ['erro' => 'Arquivo inválido.'];
        }

        $tamanho = (int) filesize($tmp);
        if ($tamanho === 0) {
            return ['erro' => 'O arquivo está vazio.'];
        }
        if ($tamanho > self::tamanhoMaximo()) {
            return ['erro' => "O arquivo excede o tamanho máximo de {$limite}."];
        }

        $nomeOriginal = self::limparNome((string) ($arquivo['name'] ?? ''));
        $extensao     = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

        if (!isset(self::TIPOS[$extensao])) {
            return ['erro' => 'Tipo de arquivo não permitido. Envie PDF, JPG, PNG, XML ou XLSX.'];
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime, self::TIPOS[$extensao], true) || !self::conteudoValido($tmp, $extensao)) {
            return ['erro' => "O conteúdo do arquivo não corresponde a um arquivo .{$extensao} válido."];
        }

        return [
            'erro'          => null,
            'tmp'           => $tmp,
            'nome_original' => $nomeOriginal,
            'extensao'      => $extensao === 'jpeg' ? 'jpg' : $extensao,
            'mime'          => $mime,
            'tamanho'       => $tamanho,
        ];
    }

    /**
     * Move o arquivo validado para o diretório de uploads com nome aleatório.
     *
     * @throws RuntimeException se o diretório não aceitar gravação
     */
    public static function armazenar(array $validado): string
    {
        $diretorio = self::diretorio();
        if (!is_dir($diretorio) || !is_writable($diretorio)) {
            throw new RuntimeException("Diretório de uploads sem permissão de gravação: {$diretorio}");
        }

        $nome    = bin2hex(random_bytes(16)) . '.' . $validado['extensao'];
        $destino = $diretorio . '/' . $nome;

        if (!move_uploaded_file($validado['tmp'], $destino)) {
            throw new RuntimeException('Falha ao mover o arquivo enviado.');
        }
        @chmod($destino, 0640);

        return $nome;
    }

    /**
     * Caminho absoluto de um arquivo armazenado, ou null se o nome não seguir
     * o padrão gerado pelo sistema ou se o arquivo não existir.
     */
    public static function caminho(?string $nome): ?string
    {
        if ($nome === null || !preg_match(self::PADRAO_NOME, $nome)) {
            return null;
        }

        $caminho = self::diretorio() . '/' . $nome;

        return is_file($caminho) ? $caminho : null;
    }

    public static function mimeParaEntrega(string $nome): string
    {
        return self::MIME_ENTREGA[pathinfo($nome, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
    }

    /**
     * Remove um arquivo armazenado (usado na limpeza de arquivos substituídos
     * ou de documentos excluídos). Nomes fora do padrão são ignorados.
     */
    public static function remover(?string $nome): void
    {
        $caminho = self::caminho($nome);
        if ($caminho !== null) {
            @unlink($caminho);
        }
    }

    public static function iniParaBytes(string $valor): int
    {
        $valor = trim($valor);
        $numero = (int) $valor;

        return match (strtolower(substr($valor, -1))) {
            'g'     => $numero * 1024 ** 3,
            'm'     => $numero * 1024 ** 2,
            'k'     => $numero * 1024,
            default => $numero,
        };
    }

    private static function diretorio(): string
    {
        return rtrim((string) config('uploads.diretorio'), '/');
    }

    /**
     * Nome original usado só para exibição e para o nome sugerido no download.
     * Remove caminhos ("../", "C:\"), caracteres de controle e limita o tamanho.
     */
    private static function limparNome(string $nome): string
    {
        $nome = basename(str_replace('\\', '/', $nome));
        $nome = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $nome);
        $nome = trim(mb_substr($nome, 0, 200));

        return $nome === '' || $nome === '.' ? 'arquivo' : $nome;
    }

    private static function conteudoValido(string $caminho, string $extensao): bool
    {
        switch ($extensao) {
            case 'pdf':
                return file_get_contents($caminho, false, null, 0, 5) === '%PDF-';

            case 'jpg':
            case 'jpeg':
            case 'png':
                $info = @getimagesize($caminho);
                $esperado = $extensao === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG;
                return $info !== false && $info[2] === $esperado && $info[0] > 0 && $info[1] > 0;

            case 'xml':
                $conteudo = (string) file_get_contents($caminho);
                // DOCTYPE é recusado: evita entidades externas (XXE) e expansão maliciosa.
                if (stripos($conteudo, '<!DOCTYPE') !== false || stripos($conteudo, '<!ENTITY') !== false) {
                    return false;
                }
                $anterior = libxml_use_internal_errors(true);
                $xml = simplexml_load_string($conteudo, 'SimpleXMLElement', LIBXML_NONET);
                libxml_clear_errors();
                libxml_use_internal_errors($anterior);
                return $xml !== false;

            case 'xlsx':
                $zip = new ZipArchive();
                if ($zip->open($caminho, ZipArchive::RDONLY) !== true) {
                    return false;
                }
                $valido = $zip->locateName('[Content_Types].xml') !== false
                    && $zip->locateName('xl/workbook.xml') !== false;
                $zip->close();
                return $valido;
        }

        return false;
    }
}
