// @vitest-environment jsdom
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, waitFor, fireEvent, cleanup } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import BiancaChat from './BiancaChat'

// Implementação trocável por teste (função simples: evita o vi.fn registrar a rejeição como "não tratada").
let impl: (...args: unknown[]) => Promise<string> = async () => ''
const calls: unknown[][] = []
vi.mock('@/services/help.service', () => ({
  askBianca: (...a: unknown[]) => { calls.push(a); return impl(...a) },
}))

function setup() {
  window.HTMLElement.prototype.scrollIntoView = vi.fn()
  return render(<MemoryRouter initialEntries={['/w/inovacao/mural']}><BiancaChat workspace="inovacao" /></MemoryRouter>)
}

describe('BiancaChat', () => {
  beforeEach(() => { calls.length = 0 })
  afterEach(() => cleanup())

  it('abre, cumprimenta e responde uma conversa simples', async () => {
    impl = async () => 'Oi! Tudo bem sim, e você?'
    setup()

    fireEvent.click(screen.getByLabelText(/abrir a central de ajuda/i))
    expect(screen.getByText(/Eu sou a Bianca/i)).toBeTruthy()

    fireEvent.change(screen.getByLabelText(/mensagem para a bianca/i), { target: { value: 'oi, tudo bem?' } })
    fireEvent.click(screen.getByLabelText('Enviar'))

    await waitFor(() => expect(screen.getByText('Oi! Tudo bem sim, e você?')).toBeTruthy())
    expect(calls[0]).toEqual([
      [{ role: 'user', content: 'oi, tudo bem?' }],
      { workspace: 'inovacao', page: '/w/inovacao/mural' },
    ])
  })

  it('envia a pergunta sugerida ao clicar', async () => {
    impl = async () => 'Abra Pessoas e defina o gestor direto.'
    setup()
    fireEvent.click(screen.getByLabelText(/abrir a central de ajuda/i))
    fireEvent.click(screen.getByText('Como monto o organograma?'))
    await waitFor(() => expect(screen.getByText(/gestor direto/)).toBeTruthy())
  })

  it('mostra erro amigável quando a resposta falha', async () => {
    impl = () => new Promise<string>((_, reject) => {
      setTimeout(() => reject({ response: { data: { message: 'A Bianca está indisponível agora.' } } }), 0)
    })
    setup()
    fireEvent.click(screen.getByLabelText(/abrir a central de ajuda/i))
    fireEvent.change(screen.getByLabelText(/mensagem para a bianca/i), { target: { value: 'oi' } })
    fireEvent.click(screen.getByLabelText('Enviar'))
    await waitFor(() => expect(screen.getByRole('alert').textContent).toContain('indisponível'))
  })

  it('não envia mensagem vazia', () => {
    setup()
    fireEvent.click(screen.getByLabelText(/abrir a central de ajuda/i))
    expect((screen.getByLabelText('Enviar') as HTMLButtonElement).disabled).toBe(true)
  })
})
