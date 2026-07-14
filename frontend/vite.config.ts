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
    proxy: {
      '/api': {
        target: 'http://php:8000',
        changeOrigin: true,
      },
    },
  },
})
