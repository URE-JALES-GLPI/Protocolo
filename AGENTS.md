# Instruções do projeto (Protocolo)

## Fluxo de trabalho obrigatório

- **NÃO executar testes** (unitários, integração, e2e) após mudanças. O usuário testa no servidor.
- **NÃO pedir confirmação** para commitar ou dar push. Fazer as mudanças, commitar com mensagem descritiva e dar `git push` direto na `main`.
- Depois do push, apenas informar o que foi alterado e o hash do commit, para o usuário dar `pull` no servidor e testar.

## Contexto

- Repositório: `git@github.com:URE-JALES-GLPI/Protocolo.git` (branch `main`).
- Deploy/teste é feito pelo usuário em outro servidor via `git pull`.
