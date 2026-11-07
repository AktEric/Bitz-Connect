// Service worker mínimo: habilita a instalação como app e mostra aviso quando está sem internet.
// Não guarda respostas da API nem páginas (dados de RH não ficam em cache no aparelho).
self.addEventListener('install', () => self.skipWaiting())
self.addEventListener('activate', (e) => e.waitUntil(self.clients.claim()))
self.addEventListener('fetch', (e) => {
  if (e.request.mode !== 'navigate') return
  e.respondWith(
    fetch(e.request).catch(() => new Response(
      '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Sem conexão</title><body style="font-family:system-ui;display:grid;place-items:center;min-height:100vh;margin:0;color:#374151"><div style="text-align:center;padding:24px"><h1 style="font-size:20px">Sem conexão</h1><p>Conecte-se à internet para usar o Workspace e bater o ponto.</p><button onclick="location.reload()" style="padding:10px 18px;border-radius:12px;border:0;background:#6d28d9;color:#fff;font-size:14px">Tentar de novo</button></div></body>',
      { headers: { 'Content-Type': 'text/html; charset=utf-8' } },
    )),
  )
})
