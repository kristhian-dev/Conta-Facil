<?php

declare(strict_types=1);

namespace App\Core;

defined('APP_ROOT') || exit;

/**
 * Paginação no servidor (LIMIT/OFFSET).
 *
 * Uso:
 *   $p = new Paginator($totalDeRegistros, Paginator::paginaDaRequisicao(), 10);
 *   $model->listar($filtros, $p->porPagina, $p->offset());
 *
 * A página solicitada é ajustada para o intervalo válido
 * (ex.: ?pagina=999 mostra a última página).
 */
final class Paginator
{
    public readonly int $pagina;
    public readonly int $totalPaginas;

    public function __construct(
        public readonly int $total,
        int $paginaSolicitada,
        public readonly int $porPagina = 10
    ) {
        $this->totalPaginas = max(1, (int) ceil($total / $porPagina));
        $this->pagina       = min(max(1, $paginaSolicitada), $this->totalPaginas);
    }

    /**
     * Lê ?pagina= da URL (inteiro positivo; qualquer outro valor vira 1).
     */
    public static function paginaDaRequisicao(): int
    {
        $pagina = filter_var($_GET['pagina'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $pagina === false ? 1 : $pagina;
    }

    public function offset(): int
    {
        return ($this->pagina - 1) * $this->porPagina;
    }

    public function primeiroItem(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function ultimoItem(): int
    {
        return min($this->offset() + $this->porPagina, $this->total);
    }

    /**
     * Números de página a exibir; null representa reticências.
     * Ex.: [1, null, 4, 5, 6, null, 12]
     *
     * @return array<int|null>
     */
    public function paginasVisiveis(int $vizinhas = 2): array
    {
        $inicio  = max(1, $this->pagina - $vizinhas);
        $fim     = min($this->totalPaginas, $this->pagina + $vizinhas);
        $paginas = [];

        if ($inicio > 1) {
            $paginas[] = 1;
            if ($inicio > 2) {
                $paginas[] = null;
            }
        }

        for ($i = $inicio; $i <= $fim; $i++) {
            $paginas[] = $i;
        }

        if ($fim < $this->totalPaginas) {
            if ($fim < $this->totalPaginas - 1) {
                $paginas[] = null;
            }
            $paginas[] = $this->totalPaginas;
        }

        return $paginas;
    }
}
