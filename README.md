# Protocolo de Pastas — URE

> Sistema de protocolo e rastreabilidade de pastas entre unidades — agora como plugin GLPI.

## Sobre

O **Protocolo de Pastas** controla o fluxo de pastas físicas que transitam entre o setor central e as unidades vinculadas. Cada pasta recebe um código sequencial, gera termos de recebimento e entrega e mantém o comprovante assinado arquivado — garantindo comprovação, padronização e auditoria.

Evoluiu de sistema standalone em PHP para plugin nativo do GLPI 11.

## Para que serve

- **Protocolar entradas e retiradas** — Registro com código único, escola vinculada e itens da pasta.
- **Comprovar movimentações** — Geração de termos em PDF com código de verificação e upload do documento assinado.
- **Rastrear status** — Acompanhamento de pendências, retiradas e cancelamentos com histórico completo.
- **Gerenciar cadastros** — Escolas, tipos de arquivo e usuários com perfis e permissões.
- **Centralizar a operação** — Dashboard com alertas de pendências e visão por período/escola.

## Destaques

- Fluxo completo: entrada → termo de recebimento → retirada → termo de entrega
- Arquivamento digital do termo assinado
- Histórico e logs por pasta
- Integração com perfis e entidades do GLPI
- Migração assistida do sistema legado para plugin

## Tecnologias

GLPI 11 · PHP 8 · MySQL · GLPI Plugin API

## Licença

GPL v2+

---
*Plugin mantido pela equipe de TI.*
