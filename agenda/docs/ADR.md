# Architectural Decision Records (ADR) - Elite Barber

Este documento registra as decisões arquiteturais tomadas durante o desenvolvimento do sistema.

## ADR 01: Estética "Luxury Modern Minimalist"
- **Status**: Aceito
- **Contexto**: O usuário desejava um sistema que não parecesse "amador", exigindo um visual premium.
- **Decisão**: Utilizar uma paleta monocromática (Preto #121212, Branco, Cinza #333) com tipografia contrastante (Playfair Display para títulos).
- **Consequência**: Interface altamente sofisticada, porém exige cuidado contínuo com espaçamentos e contraste de texto.

## ADR 02: Arquitetura Single Page Application (SPA) Vanilla
- **Status**: Aceito
- **Contexto**: Necessidade de rapidez e simplicidade sem dependências pesadas como React ou Vue para um MVP.
- **Decisão**: Usar HTML e JS Vanilla com um sistema de "views" controladas por classes `.active` e `.hidden`.
- **Consequência**: Carregamento instantâneo e facilidade de manutenção sem necessidade de ferramentas de build complexas.

## ADR 03: Persistência via localStorage
- **Status**: Aceito
- **Contexto**: O sistema precisava ser funcional imediatamente sem custo de banco de dados.
- **Decisão**: Salvar todos os estados de agendamentos, configuração e serviços no `localStorage`.
- **Consequência**: Funciona offline e mantém dados localmente, mas os dados não são sincronizados entre diferentes dispositivos no momento.

## ADR 04: Mobile-First e Touch-Friendly
- **Status**: Aceito
- **Contexto**: O sistema será operado majoritariamente em smartphones por clientes e pelo barbeiro.
- **Decisão**: Botões largos, áreas de clique generosas (ex: barra de data inteira clicável) e inputs sem bordas.
- **Consequência**: Excelente usabilidade em telas pequenas.

## ADR 05: Segurança Simple-Hash
- **Status**: Aceito
- **Contexto**: Necessidade básica de separar área de cliente da área admin.
- **Decisão**: Uso de código fixo (`1234`) para acesso ao dashboard.
- **Consequência**: Suficiente para uso privado, mas inseguro para uso público vultoso sem migração para Firebase Auth.

---
*Ultima atualização: 11 de Abril de 2026*
