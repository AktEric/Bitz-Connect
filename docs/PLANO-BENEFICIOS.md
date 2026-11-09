# Plano — Benefícios do colaborador

> Só planejamento. Nada disto foi implementado; aguarda a decisão das perguntas no fim.

## Objetivo
- **Admins** (dono/RH) cadastram os benefícios de cada pessoa, na tela de Cadastro de pessoas.
- **Cada colaborador** vê, numa tela própria, **somente os seus** benefícios, puxados automaticamente pelo usuário logado.
- Cada benefício é um **quadrado de cor própria** (vale alimentação em verde, vale transporte em azul…) com nome, valor e **data de depósito**.

## Modelo de dados
| Tabela | Campos |
|---|---|
| `benefit_types` (catálogo da empresa) | uuid, hotel_id, name, color (`green`, `blue`, `amber`, `rose`, `purple`, `teal`, `orange`, `gray`), icon, active |
| `employee_benefits` | uuid, hotel_id, user_id, benefit_type_id, `amount` (criptografado, como o salário; nulo = benefício sem valor, ex.: plano de saúde), frequency (`monthly`, `one_time`), deposit_day (1–31) ou deposit_on (data, para pontual), starts_on, ends_on (nulo = vigente), note, created_by |

Catálogo inicial sugerido (editável): Vale alimentação (verde), Vale refeição (laranja), Vale transporte (azul), Plano de saúde (rosa), Plano odontológico (teal), Seguro de vida (roxo), Auxílio creche (âmbar), Gympass (cinza).

**Depósito:** mensal = "todo dia N" + **próximo depósito** calculado (dia 31 em mês curto cai no último dia; fim de semana pode ser opção futura). Pontual = data fixa.

## Quem vê o quê (segurança)
- `GET /me/benefits`: **só do usuário logado**, sem parâmetro de pessoa. Nunca devolve dados de outros.
- Admin: `benefits.manage` (dono/RH) lê e grava por pessoa em `/people/{uuid}/benefits`. Leitura de terceiros entra na auditoria (como a ficha). Mudanças registradas sem os valores no log.
- Isolamento por empresa em todas as consultas; valores criptografados em repouso e fora de listagens, perfil e relatórios.
- Testes obrigatórios: colaborador não lê o de ninguém; só admin grava; outra empresa não acessa; `role` legado não vira admin.
- Aviso ao colaborador quando um benefício é adicionado ou alterado.

## Telas
1. **Meus benefícios** (menu "Meu RH"): grade de quadrados coloridos — nome, valor, "Depósito: dia 5 · próximo 05/12", estado (vigente / encerrado). Total mensal no topo. Vazio: "Nenhum benefício cadastrado".
2. **Cadastro de pessoas → aba Benefícios**: tabela (Benefício | Valor | Depósito | Vigência | Ações) e botão "Adicionar benefício" (modal: tipo, valor, frequência, dia/data, vigência, observação).
3. **Configuração → Tipos de benefício** (dono/RH): criar, renomear, escolher cor, desativar.
4. **"Opção a mais no cadastrar/editar usuário"**: na criação de convite e no cadastro, escolher um **pacote de benefícios** (modelo, ex.: "Operacional CLT" = VA + VT) que é aplicado quando a pessoa entra e pode ser ajustado depois na aba Benefícios.

## Fases
1. Backend: catálogo, benefícios por pessoa, `/me/benefits`, permissões e testes.
2. Cadastro de pessoas: aba Benefícios (tabela + modal) e tela de tipos.
3. Tela "Meus benefícios" (quadrados coloridos) e item no menu.
4. Pacotes de benefícios no convite/cadastro.
5. (Opcional) Histórico de depósitos mês a mês.

## Decisões que preciso de você
1. **Quem administra**: só dono e RH (como salário), ou o financeiro também?
2. **Valor**: fixo por mês (versão 1) ou varia mês a mês (ex.: vale transporte por dias trabalhados)? Variável exige o histórico de depósitos (fase 5).
3. **Privacidade na tela do colaborador**: mostrar os valores direto, ou ocultos por padrão com o "olho", como o salário?
4. **Benefícios sem valor** (plano de saúde): mostrar só nome e cobertura/observação?
5. **Pacotes no convite**: entram já na primeira entrega ou depois?
