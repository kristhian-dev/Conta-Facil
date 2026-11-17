# Roteiro da apresentação — ContaFácil (15 minutos)

Sugestão de divisão entre os integrantes. As anotações do apresentador em cada slide de `Apresentacao_ContaFacil.pptx` repetem estas indicações.

| Slides | Conteúdo | Quem | Tempo |
|---|---|---|---|
| 1–3 | Capa, problema e objetivo | Yasmin | 3 min |
| 4–5 | Solução e funcionalidades | Yasmin | 2 min |
| 6–9 | Radar, clientes/obrigações, contato pelo WhatsApp e documentos — **com demonstração ao vivo** | Kristhian | 5,5 min |
| 10–12 | Arquitetura MVC, banco de dados (DER), segurança e testes | Yasmin | 2,5 min |
| 13–15 | Limitações, próximos passos e conclusão | Kristhian | 2 min |
| | **Total** | | **15 min** |

## Demonstração ao vivo (slides 6–9, cerca de 5,5 minutos)

1. Entrar como contador (`contador@contafacil.test`; senha no README).
2. Painel: clicar no indicador "Atrasadas" e mostrar a lista já filtrada.
3. Radar: filtrar por "Alto risco" e abrir a pendência de um cliente.
4. Clientes: tentar cadastrar um CNPJ inválido e mostrar o erro no campo.
5. WhatsApp: abrir a ficha do **cliente de teste com o seu próprio celular** (veja abaixo), escolher o modelo "Documentos pendentes", editar o texto e clicar em "Abrir no WhatsApp". Explicar que o sistema só abre a conversa: o envio é feito pelo contador, nada é gravado e o recurso não veio de feedback de usuário.
6. Documentos: solicitar um documento, registrar o recebimento com um arquivo próprio (PDF ou imagem), visualizar e aprovar.

## Antes da apresentação

- Conferir que o Apache e o MySQL estão em execução e que `http://localhost/conta_facil/` abre a tela de login.
- **Cadastrar um cliente de teste com o seu próprio celular** e solicitar a ele um documento, para que o modelo "Documentos pendentes" apareça. **Nunca abrir o WhatsApp com os telefones dos clientes de demonstração**: os números do seed são fictícios e podem pertencer a pessoas reais. Excluir o cliente de teste depois da apresentação.
- Ter à mão um PDF ou uma imagem pequena (até 5 MB) para o passo 6. Os documentos de demonstração não têm arquivo físico.
- Decidir se o `database/seed.sql` será executado de novo antes da apresentação para atualizar as datas dos dados de demonstração. Isso apaga todas as obrigações e documentos dos clientes de demonstração, inclusive os criados durante testes.
- Ser transparente sobre o teste com usuário real: ele **não foi realizado** (slides 12 a 14).
