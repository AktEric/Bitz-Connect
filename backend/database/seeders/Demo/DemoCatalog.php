<?php

namespace Database\Seeders\Demo;

/**
 * Catálogo estático do cenário de demonstração: hotéis, profissionais, vagas,
 * cursos, mentorias e textos de feed. Tudo escrito à mão para fazer sentido
 * dentro do universo de hotelaria — nada de lorem ipsum.
 */
class DemoCatalog
{
    /**
     * Hotéis. `pro` define assinatura Bitz Connect PRO - Empresa.
     * `media` escolhe o pool de fotos coerente com o tipo de propriedade.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function hotels(): array
    {
        return [
            [
                'name' => 'Costa Azul Resort & Spa', 'segment' => 'Resort',
                'city' => 'Florianópolis', 'state' => 'SC', 'pro' => true, 'media' => 'resort',
                'founded' => 2004,
                'description' => 'Resort pé na areia no norte da Ilha com 184 apartamentos, spa completo, três restaurantes e centro de convenções para até 400 pessoas. Somos casa para 210 colaboradores e formamos gente para a hotelaria catarinense desde 2004.',
            ],
            [
                'name' => 'Serra Verde Hotel Boutique', 'segment' => 'Hotel',
                'city' => 'Gramado', 'state' => 'RS', 'pro' => true, 'media' => 'facade',
                'founded' => 2011,
                'description' => 'Hotel boutique de 42 suítes no centro de Gramado, com restaurante autoral premiado e adega com mais de 300 rótulos. Alta temporada de inverno com ocupação média de 94%.',
            ],
            [
                'name' => 'Pousada Vento Norte', 'segment' => 'Pousada',
                'city' => 'Jericoacoara', 'state' => 'CE', 'pro' => false, 'media' => 'destination',
                'founded' => 2015,
                'description' => 'Pousada de 18 quartos a duas ruas da Duna do Pôr do Sol. Operação enxuta, atendimento familiar e nota 9,4 nas principais OTAs.',
            ],
            [
                'name' => 'Rede Horizonte Hotéis', 'segment' => 'Rede',
                'city' => 'São Paulo', 'state' => 'SP', 'pro' => true, 'media' => 'facade',
                'founded' => 1998,
                'description' => 'Rede nacional com 22 unidades em 11 estados, focada em hotelaria corporativa e long stay. Mais de 1.800 colaboradores e programa próprio de trainee em gestão hoteleira.',
            ],
            [
                'name' => 'Ipanema Palace Hotel', 'segment' => 'Hotel',
                'city' => 'Rio de Janeiro', 'state' => 'RJ', 'pro' => true, 'media' => 'room',
                'founded' => 1979,
                'description' => 'Clássico de frente para a praia de Ipanema, 126 apartamentos, rooftop bar e serviço de concierge reconhecido pelas Chaves de Ouro. Tradição carioca desde 1979.',
            ],
            [
                'name' => 'Chalés da Montanha', 'segment' => 'Pousada',
                'city' => 'Campos do Jordão', 'state' => 'SP', 'pro' => false, 'media' => 'destination',
                'founded' => 2009,
                'description' => 'Doze chalés independentes com lareira, café da manhã servido no quarto e vista para o vale. Público de casais e viagens de inverno.',
            ],
            [
                'name' => 'Business Center Hotel Batel', 'segment' => 'Hotel',
                'city' => 'Curitiba', 'state' => 'PR', 'pro' => false, 'media' => 'facade',
                'founded' => 2013,
                'description' => 'Hotel corporativo no Batel com 98 apartamentos, cinco salas de reunião e check-in expresso. Ocupação de segunda a quinta acima de 85%.',
            ],
            [
                'name' => 'Amazônia Eco Lodge', 'segment' => 'Resort',
                'city' => 'Manaus', 'state' => 'AM', 'pro' => false, 'media' => 'destination',
                'founded' => 2017,
                'description' => 'Lodge de selva a 80 km de Manaus, 24 bangalôs suspensos, energia solar e operação com guias das comunidades ribeirinhas locais.',
            ],
            [
                'name' => 'Hostel Trilha Urbana', 'segment' => 'Hostel',
                'city' => 'Belo Horizonte', 'state' => 'MG', 'pro' => false, 'media' => 'facade',
                'founded' => 2016,
                'description' => 'Hostel na Savassi com 64 camas, quartos privativos e programação cultural semanal. Público jovem, mochileiro e intercambista.',
            ],
            [
                'name' => 'Grand Nordeste Hotéis', 'segment' => 'Rede',
                'city' => 'Recife', 'state' => 'PE', 'pro' => true, 'media' => 'resort',
                'founded' => 2006,
                'description' => 'Rede com sete resorts entre Pernambuco, Alagoas e Bahia, especializada em all inclusive e turismo familiar. Cerca de 1.200 colaboradores na alta temporada.',
            ],
            [
                'name' => 'Vila Colonial Hotel Fazenda', 'segment' => 'Hotel',
                'city' => 'Tiradentes', 'state' => 'MG', 'pro' => false, 'media' => 'destination',
                'founded' => 2012,
                'description' => 'Hotel fazenda em casarão restaurado do século XIX, 31 acomodações, cozinha mineira de raiz e programação de gastronomia o ano todo.',
            ],
            [
                'name' => 'Praia dos Coqueiros Resort', 'segment' => 'Resort',
                'city' => 'Porto Seguro', 'state' => 'BA', 'pro' => true, 'media' => 'resort',
                'founded' => 2008,
                'description' => 'Resort all inclusive com 240 apartamentos, quatro piscinas, kids club e teatro próprio. Alta temporada com mais de 90 mil diárias vendidas por ano.',
            ],
        ];
    }

    /**
     * Profissionais. `creator` = assinante Bitz Connect PRO - Consultor
     * (publica vídeos, cursos, mentorias e stories). `hotel` é o índice no
     * catálogo de hotéis; null para consultores independentes.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function people(): array
    {
        return [
            // ── Consultores PRO ──────────────────────────────────
            [
                'name' => 'Marina Cavalcanti', 'creator' => true, 'hotel' => null,
                'title' => 'Consultora de Revenue Management', 'specialty' => 'Revenue Management',
                'city' => 'São Paulo', 'state' => 'SP', 'seniority' => 14,
                'bio' => 'Trabalho com precificação e distribuição hoteleira há 14 anos. Já estruturei o RM de 60+ propriedades entre resorts e hotelaria urbana. Falo de tarifa, mix de canais e forecast sem enrolação.',
            ],
            [
                'name' => 'Rodrigo Antunes', 'creator' => true, 'hotel' => null,
                'title' => 'Consultor de Operações Hoteleiras', 'specialty' => 'Gestão Hoteleira',
                'city' => 'Rio de Janeiro', 'state' => 'RJ', 'seniority' => 18,
                'bio' => 'Ex-gerente geral de bandeira internacional. Hoje ajudo hotéis independentes a organizar a operação, o custo por apartamento ocupado e a rotina de liderança.',
            ],
            [
                'name' => 'Patrícia Bulhões', 'creator' => true, 'hotel' => null,
                'title' => 'Especialista em Experiência do Hóspede', 'specialty' => 'Hospitalidade',
                'city' => 'Florianópolis', 'state' => 'SC', 'seniority' => 11,
                'bio' => 'Desenho jornadas do hóspede do primeiro e-mail ao pós-checkout. Obcecada por NPS, recuperação de falhas e detalhes que ninguém mede — mas todo mundo sente.',
            ],
            [
                'name' => 'Anderson Prates', 'creator' => true, 'hotel' => null,
                'title' => 'Chef Executivo e Consultor de A&B', 'specialty' => 'F&B',
                'city' => 'São Paulo', 'state' => 'SP', 'seniority' => 20,
                'bio' => 'Vinte anos de cozinha de hotel. Consultoria em ficha técnica, CMV, cardápio de café da manhã e operação de banquetes. Cozinha boa é cozinha que fecha a conta.',
            ],
            [
                'name' => 'Juliana Mesquita', 'creator' => true, 'hotel' => null,
                'title' => 'Consultora de Governança e Housekeeping', 'specialty' => 'Governança',
                'city' => 'Gramado', 'state' => 'RS', 'seniority' => 16,
                'bio' => 'Governanta executiva de formação. Trabalho produtividade de camareira, enxoval, química de limpeza e escala — a área que mais pesa no custo e mais aparece na avaliação.',
            ],
            [
                'name' => 'Tiago Bernardes', 'creator' => true, 'hotel' => null,
                'title' => 'Consultor de Marketing e Distribuição Hoteleira', 'specialty' => 'Marketing Hoteleiro',
                'city' => 'Curitiba', 'state' => 'PR', 'seniority' => 9,
                'bio' => 'Venda direta, metabusca, motor de reservas e CRM. Meu trabalho é diminuir a dependência de OTA sem perder volume.',
            ],
            [
                'name' => 'Fernanda Rolim', 'creator' => true, 'hotel' => null,
                'title' => 'Headhunter e Consultora de RH para Hotelaria', 'specialty' => 'Recursos Humanos',
                'city' => 'Recife', 'state' => 'PE', 'seniority' => 12,
                'bio' => 'Recruto liderança para hotéis e resorts do Nordeste. Também estruturo programas de retenção — porque contratar bem sem segurar o time é enxugar gelo.',
            ],
            [
                'name' => 'Otávio Lacerda', 'creator' => true, 'hotel' => null,
                'title' => 'Especialista em Eventos e MICE', 'specialty' => 'Eventos',
                'city' => 'São Paulo', 'state' => 'SP', 'seniority' => 13,
                'bio' => 'Congressos, casamentos e convenções corporativas. Trabalho o comercial de eventos, o memorando de serviços e a margem que quase todo hotel deixa na mesa.',
            ],
            [
                'name' => 'Camila Nakamura', 'creator' => true, 'hotel' => null,
                'title' => 'Consultora de Sustentabilidade Hoteleira', 'specialty' => 'Sustentabilidade',
                'city' => 'Manaus', 'state' => 'AM', 'seniority' => 8,
                'bio' => 'Certificações, gestão de resíduos, eficiência energética e compras responsáveis. Sustentabilidade que reduz conta de luz também é sustentabilidade.',
            ],
            [
                'name' => 'Sérgio Vilanova', 'creator' => true, 'hotel' => null,
                'title' => 'Mentor de Carreira em Hotelaria', 'specialty' => 'Carreira',
                'city' => 'Belo Horizonte', 'state' => 'MG', 'seniority' => 22,
                'bio' => 'Comecei como mensageiro em 2003 e cheguei a diretor de operações. Hoje mentoro quem quer sair da recepção e chegar à gerência sem levar dez anos.',
            ],

            // ── Profissionais em hotéis ──────────────────────────
            [
                'name' => 'Ana Beatriz Rocha', 'creator' => false, 'hotel' => 0, 'admin' => true,
                'title' => 'Gerente Geral', 'specialty' => 'Gestão Hoteleira', 'seniority' => 15,
                'bio' => 'Gerente geral do Costa Azul. Time de 210 pessoas, meta de RevPAR e a certeza de que operação boa se constrói no corredor, não na sala.',
            ],
            [
                'name' => 'Bruno Sampaio', 'creator' => false, 'hotel' => 3, 'admin' => true,
                'title' => 'Diretor de Operações', 'specialty' => 'Gestão Hoteleira', 'seniority' => 19,
                'bio' => 'Respondo pelas 22 unidades da Rede Horizonte. Padronização, indicador na mão e gente boa liderando cada praça.',
            ],
            [
                'name' => 'Carla Menezes', 'creator' => false, 'hotel' => 4,
                'title' => 'Governanta Executiva', 'specialty' => 'Governança', 'seniority' => 17,
                'bio' => 'Governança do Ipanema Palace. 126 apartamentos, 34 camareiras e enxoval que roda todo dia sem falhar.',
            ],
            [
                'name' => 'Diego Ferrarini', 'creator' => false, 'hotel' => 1, 'admin' => true,
                'title' => 'Chef Executivo', 'specialty' => 'F&B', 'seniority' => 12,
                'bio' => 'Cozinha do Serra Verde. Menu sazonal com produtor da região, brigada de 14 pessoas e um café da manhã que é o motivo de metade das nossas avaliações.',
            ],
            [
                'name' => 'Eliane Portela', 'creator' => false, 'hotel' => 9, 'admin' => true,
                'title' => 'Gerente de Reservas', 'specialty' => 'Revenue Management', 'seniority' => 10,
                'bio' => 'Central de reservas da Grand Nordeste. Sete resorts, alta temporada inteira no forecast e telefone que não para.',
            ],
            [
                'name' => 'Fábio Quintela', 'creator' => false, 'hotel' => 4, 'admin' => true,
                'title' => 'Concierge Sênior', 'specialty' => 'Hospitalidade', 'seniority' => 21,
                'bio' => 'Concierge no Ipanema Palace há 12 anos, membro das Chaves de Ouro. Se o hóspede pedir, eu consigo — só não prometo chuva em dia de sol.',
            ],
            [
                'name' => 'Gabriela Assunção', 'creator' => false, 'hotel' => 11, 'admin' => true,
                'title' => 'Coordenadora de A&B', 'specialty' => 'F&B', 'seniority' => 9,
                'bio' => 'All inclusive é logística. Coordeno quatro pontos de consumo, buffet para 600 pessoas e o bar da piscina que nunca fecha.',
            ],
            [
                'name' => 'Henrique Vasques', 'creator' => false, 'hotel' => 6, 'admin' => true,
                'title' => 'Gerente de Recepção', 'specialty' => 'Hospitalidade', 'seniority' => 8,
                'bio' => 'Recepção do Business Center Batel. Check-in de executivo tem que ser rápido, correto e simpático — nessa ordem, mas sem abrir mão de nenhum.',
            ],
            [
                'name' => 'Isabela Tavares', 'creator' => false, 'hotel' => 3,
                'title' => 'Analista de Revenue Management', 'specialty' => 'Revenue Management', 'seniority' => 5,
                'bio' => 'Analista de RM na Rede Horizonte. Vivo no relatório de pick-up e no comparativo de tarifa da concorrência.',
            ],
            [
                'name' => 'João Petronilho', 'creator' => false, 'hotel' => 7, 'admin' => true,
                'title' => 'Gerente de Manutenção Predial', 'specialty' => 'Manutenção', 'seniority' => 14,
                'bio' => 'Manutenção no meio da selva é outro esporte. Energia solar, tratamento de água e preventiva em dia — porque peça de reposição aqui demora dois dias de barco.',
            ],
            [
                'name' => 'Karina Duarte', 'creator' => false, 'hotel' => 5, 'admin' => true,
                'title' => 'Supervisora de Housekeeping', 'specialty' => 'Governança', 'seniority' => 7,
                'bio' => 'Doze chalés, equipe pequena e padrão alto. Aqui cada detalhe do quarto aparece na avaliação no dia seguinte.',
            ],
            [
                'name' => 'Lucas Andrade', 'creator' => false, 'hotel' => 8, 'admin' => true,
                'title' => 'Recepcionista Sênior', 'specialty' => 'Hospitalidade', 'seniority' => 4,
                'bio' => 'Recepção do Trilha Urbana. Falo inglês e espanhol, indico bar, mostro mapa e faço check-in de mochileiro às três da manhã de bom humor.',
            ],
            [
                'name' => 'Mariana Belchior', 'creator' => false, 'hotel' => 1,
                'title' => 'Gerente de Eventos', 'specialty' => 'Eventos', 'seniority' => 11,
                'bio' => 'Casamento em Gramado é um universo próprio. Coordeno 60 eventos por ano entre casamentos, corporativos e famílias inteiras em fim de ano.',
            ],
            [
                'name' => 'Nicolas Perrone', 'creator' => false, 'hotel' => 0,
                'title' => 'Maître', 'specialty' => 'F&B', 'seniority' => 13,
                'bio' => 'Salão do restaurante principal do Costa Azul. Serviço à francesa, brigada de 18 garçons e mapa de mesas na cabeça.',
            ],
            [
                'name' => 'Olívia Ferraz', 'creator' => false, 'hotel' => 9,
                'title' => 'Gerente de Marketing Hoteleiro', 'specialty' => 'Marketing Hoteleiro', 'seniority' => 9,
                'bio' => 'Marketing dos sete resorts da Grand Nordeste. Venda direta crescendo, OTA no lugar certo e conteúdo que mostra o produto de verdade.',
            ],
            [
                'name' => 'Paulo Rezende', 'creator' => false, 'hotel' => 10, 'admin' => true,
                'title' => 'Gerente Geral', 'specialty' => 'Gestão Hoteleira', 'seniority' => 16,
                'bio' => 'Toco a Vila Colonial com a lógica de hotel fazenda: hospitalidade mineira, comida de verdade e hóspede que volta todo ano.',
            ],
            [
                'name' => 'Renata Coelho', 'creator' => false, 'hotel' => 3,
                'title' => 'Coordenadora de RH', 'specialty' => 'Recursos Humanos', 'seniority' => 10,
                'bio' => 'RH da Rede Horizonte. Recrutamento em 11 estados, programa de trainee e a briga diária contra o turnover da linha de frente.',
            ],
            [
                'name' => 'Sabrina Klein', 'creator' => false, 'hotel' => 1,
                'title' => 'Sommelière', 'specialty' => 'F&B', 'seniority' => 6,
                'bio' => 'Sommelière do Serra Verde. Adega com 300 rótulos, harmonização do menu degustação e paixão declarada por vinho da Serra Gaúcha.',
            ],
            [
                'name' => 'Thiago Mourão', 'creator' => false, 'hotel' => 2, 'admin' => true,
                'title' => 'Cozinheiro', 'specialty' => 'F&B', 'seniority' => 3,
                'bio' => 'Cozinha da Pousada Vento Norte. Café da manhã com tapioca, peixe fresco no almoço e vontade de virar chef.',
            ],
            [
                'name' => 'Vanessa Lira', 'creator' => false, 'hotel' => 11,
                'title' => 'Recepcionista', 'specialty' => 'Hospitalidade', 'seniority' => 2,
                'bio' => 'Primeira experiência em resort e já apaixonada. Estudando gestão hoteleira à noite e aprendendo muito no balcão.',
            ],
        ];
    }
}
