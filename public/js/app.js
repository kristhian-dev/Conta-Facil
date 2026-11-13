/* =====================================================================
   ContaFácil — comportamento da interface (sem bibliotecas).
   Tudo aqui é melhoria de uso: as regras e validações reais ficam no servidor.
   ===================================================================== */
(function () {
    'use strict';

    var somenteDigitos = function (valor) {
        return valor.replace(/\D/g, '');
    };

    /* Confirmação antes de ações destrutivas: <form data-confirmar="Mensagem"> */
    document.addEventListener('submit', function (evento) {
        var mensagem = evento.target.getAttribute('data-confirmar');
        if (mensagem && !window.confirm(mensagem)) {
            evento.preventDefault();
        }
    });

    /* Máscaras de CPF/CNPJ e telefone */
    var mascaras = {
        'cpf-cnpj': function (valor) {
            var d = somenteDigitos(valor).slice(0, 14);
            if (d.length <= 11) {
                return d.replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d)/, '$1.$2')
                        .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            }
            return d.replace(/^(\d{2})(\d)/, '$1.$2')
                    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
                    .replace(/\.(\d{3})(\d)/, '.$1/$2')
                    .replace(/(\d{4})(\d)/, '$1-$2');
        },
        'telefone': function (valor) {
            var d = somenteDigitos(valor).slice(0, 11);
            if (d.length <= 2) { return d.length ? '(' + d : d; }
            var meio = d.length === 11 ? 5 : 4;
            return '(' + d.slice(0, 2) + ') ' + d.slice(2, 2 + meio) +
                   (d.length > 2 + meio ? '-' + d.slice(2 + meio) : '');
        }
    };

    document.querySelectorAll('[data-mascara]').forEach(function (campo) {
        var aplicar = mascaras[campo.getAttribute('data-mascara')];
        if (!aplicar) { return; }
        campo.addEventListener('input', function () {
            campo.value = aplicar(campo.value);
        });
    });

    /* Campos condicionais: <div data-visivel-se="status=concluida"> só aparece
       quando o campo "status" do mesmo formulário tem o valor "concluida". */
    document.querySelectorAll('[data-visivel-se]').forEach(function (bloco) {
        var partes = bloco.getAttribute('data-visivel-se').split('=');
        var form = bloco.closest('form');
        var controle = form && form.elements[partes[0]];
        if (!controle) { return; }

        var atualizar = function (houveMudanca) {
            // Um campo com erro continua visível para que a mensagem seja lida.
            bloco.hidden = controle.value !== partes[1] && !bloco.classList.contains('campo-invalido');
            // Ao ocultar por escolha do usuário, limpa o valor para não enviá-lo sem querer.
            if (houveMudanca && controle.value !== partes[1]) {
                bloco.querySelectorAll('input').forEach(function (campo) { campo.value = ''; });
            }
        };
        controle.addEventListener('change', function () { atualizar(true); });
        atualizar(false);
    });

    /* Opções dependentes: <select data-filtrar-por="cliente_id"> mostra só as
       <option data-cliente="N"> do cliente escolhido no campo indicado. */
    document.querySelectorAll('select[data-filtrar-por]').forEach(function (dependente) {
        var controle = dependente.form && dependente.form.elements[dependente.getAttribute('data-filtrar-por')];
        if (!controle) { return; }

        var filtrar = function () {
            Array.prototype.forEach.call(dependente.options, function (opcao) {
                var dono = opcao.getAttribute('data-cliente');
                var visivel = dono === null || dono === controle.value;
                opcao.hidden = !visivel;
                opcao.disabled = !visivel;
                if (!visivel && opcao.selected) { dependente.value = ''; }
            });
        };
        controle.addEventListener('change', filtrar);
        filtrar();
    });

    /* Aviso imediato de arquivo acima do limite (o servidor valida de qualquer forma). */
    document.querySelectorAll('input[type="file"][data-limite]').forEach(function (campo) {
        campo.addEventListener('change', function () {
            var limite = parseInt(campo.getAttribute('data-limite'), 10);
            var arquivo = campo.files && campo.files[0];
            campo.setCustomValidity(arquivo && arquivo.size > limite
                ? 'O arquivo excede o tamanho máximo de ' + (limite / 1048576).toFixed(1).replace('.', ',') + ' MB.'
                : '');
            campo.reportValidity();
        });
    });

    /* Formulário de cliente: mostra os campos conforme o tipo de pessoa */
    var formCliente = document.querySelector('[data-form-cliente]');
    if (formCliente) {
        var atualizarTipo = function () {
            var marcado = formCliente.querySelector('input[name="tipo_pessoa"]:checked');
            var tipo = marcado ? marcado.value : 'PJ';

            formCliente.querySelectorAll('[data-somente]').forEach(function (bloco) {
                bloco.hidden = bloco.getAttribute('data-somente') !== tipo;
            });
            formCliente.querySelectorAll('[data-rotulo-pf]').forEach(function (rotulo) {
                rotulo.textContent = rotulo.getAttribute(tipo === 'PF' ? 'data-rotulo-pf' : 'data-rotulo-pj');
            });
        };

        formCliente.querySelectorAll('input[name="tipo_pessoa"]').forEach(function (opcao) {
            opcao.addEventListener('change', atualizarTipo);
        });
        atualizarTipo();
    }
})();
