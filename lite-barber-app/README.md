# Lite Barber App

Frontend Vue 3 usado pelo sistema Lite Barber.

## Requisitos

- Node.js 20+
- API Laravel disponível em `http://127.0.0.1:8000`

## Desenvolvimento

```bash
npm install
cp .env.example .env
npm run dev
```

Por padrão, o Vite encaminha `/api` e `/storage` para `http://127.0.0.1:8000`.
O endereço pode ser alterado pela variável `VITE_API_PROXY_TARGET`.

Para evitar o proxy do Vite e apontar diretamente para outra API:

```dotenv
VITE_API_BASE_URL=https://api.seudominio.com.br
VITE_API_PROXY_TARGET=https://api.seudominio.com.br
```

## Build

```bash
npm run build
npm run preview
```

## Qualidade contínua

```bash
npm run check
```

Este comando executa o build de produção. O workflow `.github/workflows/ci.yml` executa o mesmo check em pull requests e pushes para `main`.

## Integração HTTP

Todas as chamadas HTTP devem usar `src/services/api.js`.
O cliente:

- usa `VITE_API_BASE_URL` quando configurada;
- usa URLs relativas em desenvolvimento;
- envia o token Sanctum armazenado em `localStorage`;
- remove o token quando a API responde `401`.
