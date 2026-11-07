/// <reference types="vitest" />
import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

export default defineConfig({
  plugins: [react()],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
    },
  },
  server: {
    port: 3000,
    host: '0.0.0.0',
    // Permite acessar via domínio/túnel externo (Vite bloqueia Host headers
    // desconhecidos por padrão) — ok manter em true enquanto for só teste interno.
    allowedHosts: true,
    // Docker no Windows/OneDrive não repassa eventos de arquivo ao container: sem polling o Vite
    // serve versões antigas dos arquivos editados.
    watch: { usePolling: true, interval: 300 },
    proxy: {
      '/api': {
        // O nginx do compose é quem fala com o php-fpm; ele escuta 8000 na rede
        // interna (o container php só expõe 9000/fpm, que não atende HTTP).
        // Rodando o Vite fora do compose, aponte para a porta publicada do nginx
        // com VITE_API_PROXY_TARGET=http://localhost:9595.
        target: process.env.VITE_API_PROXY_TARGET ?? 'http://nginx:8000',
        changeOrigin: true,
      },
    },
  },
})
