<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Preferências de chat de cada pessoa (valem em qualquer empresa). */
class ChatSettingsController extends Controller
{
    use ApiResponseTrait;

    /** GET /me/chat-settings */
    public function show(Request $request): JsonResponse
    {
        return $this->success('Preferências carregadas.', ['read_receipts' => (bool) $request->user()->chat_read_receipts]);
    }

    /**
     * PUT /me/chat-settings { read_receipts }
     * Desligada: ninguém vê quando você leu (e você também não vê quando os outros leram), como no WhatsApp.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate(['read_receipts' => ['required', 'boolean']]);
        $request->user()->forceFill(['chat_read_receipts' => $data['read_receipts']])->save();

        return $this->success('Preferência salva.', ['read_receipts' => (bool) $data['read_receipts']]);
    }
}
