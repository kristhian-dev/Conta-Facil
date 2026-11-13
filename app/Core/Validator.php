<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

/**
 * Validação de dados no servidor com mensagens por campo.
 *
 * Uso:
 *   $v = new Validator($_POST);
 *   if (!$v->validar(['email' => ['obrigatorio', 'email', 'max:160']])) {
 *       $erros = $v->erros();   // ['email' => 'Informe um e-mail válido.']
 *   }
 *
 * Regras disponíveis:
 *   obrigatorio, email, min:N, max:N (caracteres), in:a,b,c (valor permitido),
 *   cpf, cnpj (somente dígitos, com dígitos verificadores), telefone (10 ou 11 dígitos),
 *   inteiro (inteiro positivo), data (AAAA-MM-DD), mes (AAAA-MM), datahora (AAAA-MM-DDTHH:MM),
 *   dinheiro (ex.: 1.234,56 | 1234,56 | 1234.56).
 * Cada campo recebe apenas a primeira mensagem de erro encontrada.
 * Erros que dependem do banco (ex.: duplicidade) são incluídos com adicionarErro().
 */
final class Validator
{
    /** @var array<string, string> */
    private array $erros = [];

    public function __construct(private readonly array $dados)
    {
    }

    /**
     * @param array<string, string[]> $regrasPorCampo
     */
    public function validar(array $regrasPorCampo): bool
    {
        $this->erros = [];

        foreach ($regrasPorCampo as $campo => $regras) {
            $valor = $this->texto($campo);

            foreach ($regras as $regra) {
                [$nome, $parametro] = array_pad(explode(':', $regra, 2), 2, null);
                $erro = $this->aplicar($nome, $valor, $parametro);

                if ($erro !== null) {
                    $this->erros[$campo] = $erro;
                    break;
                }
            }
        }

        return $this->erros === [];
    }

    /**
     * @return array<string, string>
     */
    public function erros(): array
    {
        return $this->erros;
    }

    public function temErro(string $campo): bool
    {
        return isset($this->erros[$campo]);
    }

    /**
     * Registra um erro verificado fora das regras (ex.: CPF/CNPJ já cadastrado).
     */
    public function adicionarErro(string $campo, string $mensagem): void
    {
        $this->erros[$campo] ??= $mensagem;
    }

    /**
     * Valor do campo como texto, sem espaços nas pontas.
     * Valores que não são texto (ex.: arrays enviados de propósito) viram ''.
     */
    public function texto(string $campo): string
    {
        $valor = $this->dados[$campo] ?? '';

        return is_string($valor) ? trim($valor) : '';
    }

    private function aplicar(string $regra, string $valor, ?string $parametro): ?string
    {
        // Campo vazio e não obrigatório: as demais regras não se aplicam.
        if ($valor === '' && $regra !== 'obrigatorio') {
            return null;
        }

        return match ($regra) {
            'obrigatorio' => $valor === '' ? 'Este campo é obrigatório.' : null,
            'email'       => filter_var($valor, FILTER_VALIDATE_EMAIL) === false ? 'Informe um e-mail válido.' : null,
            'min'         => mb_strlen($valor) < (int) $parametro ? "Informe pelo menos {$parametro} caracteres." : null,
            'max'         => mb_strlen($valor) > (int) $parametro ? "Informe no máximo {$parametro} caracteres." : null,
            'in'          => in_array($valor, explode(',', (string) $parametro), true) ? null : 'Selecione uma opção válida.',
            'cpf'         => self::cpfValido($valor) ? null : 'CPF inválido. Confira os 11 dígitos.',
            'cnpj'        => self::cnpjValido($valor) ? null : 'CNPJ inválido. Confira os 14 dígitos.',
            'telefone'    => preg_match('/^\d{10,11}$/', (string) preg_replace('/[\s().-]/', '', $valor))
                                 ? null : 'Informe o telefone com DDD (10 ou 11 dígitos).',
            'inteiro'     => preg_match('/^[1-9]\d{0,9}$/', $valor) ? null : 'Selecione uma opção válida.',
            'data'        => self::dataValida($valor, 'Y-m-d') ? null : 'Informe uma data válida.',
            'mes'         => self::dataValida($valor, 'Y-m') ? null : 'Informe um mês válido (AAAA-MM).',
            'datahora'    => self::dataValida($valor, 'Y-m-d\TH:i') ? null : 'Informe uma data e hora válidas.',
            'dinheiro'    => preg_match('/^(\d{1,3}(\.\d{3})+|\d+)(,\d{1,2})?$|^\d+(\.\d{1,2})?$/', $valor)
                                 ? null : 'Informe um valor válido. Exemplo: 1.234,56',
            default       => throw new \InvalidArgumentException("Regra de validação desconhecida: {$regra}"),
        };
    }

    /**
     * Data existente no calendário, no formato exato informado (ex.: 2026-02-30 é inválida).
     * Aceita apenas anos entre 2000 e 2100, faixa adequada ao sistema.
     */
    public static function dataValida(string $valor, string $formato): bool
    {
        $data = \DateTimeImmutable::createFromFormat('!' . $formato, $valor);

        return $data !== false
            && $data->format($formato) === $valor
            && (int) $data->format('Y') >= 2000
            && (int) $data->format('Y') <= 2100;
    }

    /**
     * CPF: 11 dígitos, não repetidos, com os dois dígitos verificadores corretos.
     */
    public static function cpfValido(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($posicao = 9; $posicao < 11; $posicao++) {
            $soma = 0;
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cpf[$i] * ($posicao + 1 - $i);
            }
            $digito = ($soma * 10) % 11 % 10;

            if ((int) $cpf[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }

    /**
     * CNPJ: 14 dígitos, não repetidos, com os dois dígitos verificadores corretos.
     */
    public static function cnpjValido(string $cnpj): bool
    {
        if (!preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) {
            return false;
        }

        $pesos = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        for ($posicao = 12; $posicao < 14; $posicao++) {
            $soma = 0;
            // 1º dígito usa os pesos a partir de 5; o 2º, a partir de 6.
            $pesosUsados = array_slice($pesos, 13 - $posicao);
            for ($i = 0; $i < $posicao; $i++) {
                $soma += (int) $cnpj[$i] * $pesosUsados[$i];
            }
            $resto  = $soma % 11;
            $digito = $resto < 2 ? 0 : 11 - $resto;

            if ((int) $cnpj[$posicao] !== $digito) {
                return false;
            }
        }

        return true;
    }
}
