<?php

/**
 * Modelos de estrutura por segmento: setores e cargos sugeridos.
 * Usados para dar um ponto de partida a empresas de qualquer ramo.
 */
return [
    'hotelaria' => [
        'label' => 'Hotelaria',
        'departments' => ['Recepção', 'Governança', 'Alimentos e Bebidas', 'Manutenção', 'Reservas', 'Administrativo'],
        'job_titles'  => ['Recepcionista', 'Camareira', 'Garçom', 'Gerente de operações', 'Analista de reservas'],
    ],
    'restaurante' => [
        'label' => 'Restaurante e bares',
        'departments' => ['Cozinha', 'Salão', 'Bar', 'Caixa', 'Estoque e compras', 'Administrativo'],
        'job_titles'  => ['Cozinheiro', 'Auxiliar de cozinha', 'Garçom', 'Barman', 'Gerente de salão'],
    ],
    'arena-esportiva' => [
        'label' => 'Arena e eventos esportivos',
        'departments' => ['Operações de eventos', 'Segurança', 'Bilheteria', 'Manutenção do gramado', 'Marketing', 'Administrativo'],
        'job_titles'  => ['Coordenador de eventos', 'Segurança', 'Atendente de bilheteria', 'Gestor de arena'],
    ],
    'clube-futebol' => [
        'label' => 'Clube de futebol',
        'departments' => ['Futebol profissional', 'Categorias de base', 'Departamento médico', 'Comissão técnica', 'Marketing e sócios', 'Administrativo e financeiro'],
        'job_titles'  => ['Treinador', 'Preparador físico', 'Fisioterapeuta', 'Analista de desempenho', 'Gerente de futebol'],
    ],
    'geral' => [
        'label' => 'Outro segmento',
        'departments' => ['Administrativo', 'Financeiro', 'Recursos Humanos', 'Comercial', 'Operações'],
        'job_titles'  => ['Analista', 'Coordenador', 'Gerente', 'Assistente'],
    ],
];
