# Elite Barber - Sistema de Agendamento Boutique
## Visão Geral do Projeto

Este é um sistema de agendamento front-end de alta performance e design de luxo, desenvolvido para barbeiros independentes ou barbearias boutique. O foco principal é a experiência do usuário (UX) e uma estética minimalista de alto nível.

## 🚀 Tecnologias Utilizadas
- **HTML5 Semântico**
- **CSS3 Moderno**: Variáveis, Flexbox, Grid, Animações customizadas.
- **JavaScript Vanilla (ES6+)**: Lógica de estado e persistência local.
- **Fontes Premium**: Playfair Display (Serif) & Outfit (Sans).
- **Iconografia**: FontAwesome 6.0.

## ✨ Funcionalidades Implementadas

### 1. Home (Landing Page)
- Design "Ultra-Luxury" com estatísticas de prestígio.
- Ações rápidas: Agendar, Fila Digital, Meus Horários e Acesso Restrito.

### 2. Agendamento (Fluxo do Cliente)
- Seleção de data dinâmica com calendário nativo (clicável em toda a barra).
- Grade de horários inteligente (bloqueia slots ocupados na data selecionada).
- Seleção de serviços com preços dinâmicos.
- Máscara de telefone (Brasil) automática.
- Feedback visual de "Checked" com mudança de cor para preto no conteúdo selecionado.

### 3. Fila de Espera Digital (Waitlist)
- Sistema para clientes "Walk-in" (sem agendamento).
- Visualização de posição na fila e tempo estimado.
- Lista em tempo real de quem está no aguardo.
- Animação de "Pulse" para indicar status ativo.

### 4. Painel Administrativo (Barbeiro)
- **Segurança**: Proteção via código de acesso (padrão: `1234`).
- **Resumo Executivo**: Cards com Total de Agendamentos, Receita do Dia (calculada) e Fila atual.
- **Agenda**: Visualização por data dos compromissos, com links diretos para WhatsApp.
- **Gestão de Serviços**: Adição e remoção de serviços e preços.
- **Configuração**: Definição de horários de abertura/fechamento e dias de funcionamento via "Day Chips".

## 💾 Persistência de Dados
O sistema utiliza o `localStorage` do navegador para manter os dados:
- `b_appointments`: Lista de agendamentos.
- `b_queue`: Pessoas na fila.
- `b_services`: Catálogo de serviços.
- `b_config`: Horários e dias de funcionamento.

## 🛠 Como Continuar o Desenvolvimento (Instruções para a próxima IA)
1. **Contexto**: O projeto está em um estágio de front-end funcional completo.
2. **Estética**: Seguir rigorosamente o padrão "Luxury Minimalist" (Preto, Branco, Cinza Profundo, Tipografia Serifada para Títulos).
3. **Persistência**: Se migrar para Backend, utilizar Supabase ou Firebase para substituir o `localStorage`.
4. **PWA**: O próximo passo lógico é adicionar um `manifest.json` para instalação como App.

---
**Status Atual**: Estável, sem bugs conhecidos, layout refinado e traduzido 100% para Português.
