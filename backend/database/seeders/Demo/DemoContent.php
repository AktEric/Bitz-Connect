<?php

namespace Database\Seeders\Demo;

/**
 * Biblioteca de textos do cenário de demonstração, organizada por
 * especialidade — cada profissional publica sobre o que realmente faz, e cada
 * post recebe uma foto do pool coerente com o assunto.
 */
class DemoContent
{
    /**
     * Posts por especialidade: ['text' => ..., 'media' => pool|null].
     *
     * @return array<string, array<int, array{text: string, media: ?string}>>
     */
    public static function postsBySpecialty(): array
    {
        return [
            'Revenue Management' => [
                ['text' => "Fechamos setembro com RevPAR 12% acima do mesmo mês do ano passado — e o mais importante: 71% disso veio de tarifa média, não de ocupação. Vender mais barato para encher o hotel é a saída fácil e quase sempre a errada.", 'media' => 'analytics'],
                ['text' => "Uma coisa que ainda vejo muito: hotel com o mesmo preço de terça e de sábado. Se a sua demanda muda ao longo da semana e o seu preço não muda, você está deixando dinheiro na mesa nos dias fortes e ficando caro nos dias fracos.", 'media' => null],
                ['text' => "Relatório de pick-up dos próximos 90 dias na mão toda segunda de manhã. É a reunião mais barata e mais rentável que existe em um hotel.", 'media' => 'analytics'],
                ['text' => "Pergunta honesta para quem trabalha com distribuição: qual a participação da venda direta no seu hotel hoje? Aqui levamos três anos para sair de 14% para 31% e cada ponto foi suado.", 'media' => null],
            ],
            'Gestão Hoteleira' => [
                ['text' => "Reunião de liderança das 9h com as sete chefias. Quinze minutos, em pé, com os números do dia anterior na parede: ocupação, no-show, chamados de manutenção e as duas piores avaliações. Simples assim, todo dia, há quatro anos.", 'media' => 'team'],
                ['text' => "Custo por apartamento ocupado é o indicador que mais me diz sobre a saúde de uma operação. Ocupação alta com CPO descontrolado é volume sem resultado.", 'media' => null],
                ['text' => "Passei a manhã inteira no corredor com a governança e a manutenção, apartamento por apartamento. Achei coisa que não aparece em relatório nenhum: tomada solta, ralo com cheiro, cortina blackout que não fecha direito.", 'media' => 'room'],
                ['text' => "Fechamos a temporada com 210 pessoas no time e zero acidente com afastamento. Esse é o número que eu levo para casa com orgulho.", 'media' => 'team'],
            ],
            'Hospitalidade' => [
                ['text' => "Hóspede chegou às 23h com voo atrasado, criança dormindo no colo e reserva feita no nome errado pela OTA. A recepcionista resolveu tudo em quatro minutos, mandou lanche para o quarto por conta da casa e ainda deixou um bilhete. Isso não está em nenhum manual — está na cultura.", 'media' => null],
                ['text' => "NPS não é enquete de satisfação. É diagnóstico. Se você não lê os comentários abertos um por um, está só colecionando nota.", 'media' => 'analytics'],
                ['text' => "A falha vai acontecer. O que separa um hotel bom de um hotel excelente é o tempo entre a falha e a recuperação. Meta aqui: 20 minutos, sem escalar para a gerência.", 'media' => null],
                ['text' => "Detalhe barato que mais gera comentário positivo na nossa operação: bilhete escrito à mão com o nome do hóspede. Custa uma caneta e trinta segundos.", 'media' => 'room'],
            ],
            'F&B' => [
                ['text' => "Reformulamos o café da manhã inteiro: tiramos 22 itens que quase ninguém consumia e colocamos seis estações de preparo na hora. Desperdício caiu 31% e a nota do café subiu de 8,1 para 9,3.", 'media' => 'food'],
                ['text' => "Ficha técnica não é burocracia de chef chato. É o que te permite saber se aquele prato lindo do cardápio está dando lucro ou pagando para o hóspede comer.", 'media' => 'food'],
                ['text' => "Menu novo entrando com produtor da região: queijo da serra, trutas de um sítio a 20 minutos daqui e cogumelo de uma família que fornece para a gente há seis anos.", 'media' => 'restaurant'],
                ['text' => "Banquete de 400 pessoas ontem. Serviço saiu em 38 minutos do primeiro ao último prato. Brigada afiada é treino, não é sorte.", 'media' => 'restaurant'],
            ],
            'Governança' => [
                ['text' => "Produtividade de camareira aqui é de 14 apartamentos por turno em arrumação e 9 em saída. Antes de aumentar meta, arrume o carrinho, o percurso e o abastecimento do andar. Quase sempre o problema é logística, não é gente.", 'media' => 'room'],
                ['text' => "Enxoval é ativo, não é despesa. Controle de giro, marcação por lote e inventário mensal — sem isso você compra lençol duas vezes por ano e nunca entende o motivo.", 'media' => null],
                ['text' => "A governança é a área que mais aparece nas avaliações e a que menos aparece nas reuniões de diretoria. Isso precisa mudar.", 'media' => 'room'],
            ],
            'Eventos' => [
                ['text' => "Casamento para 180 convidados sábado, congresso médico com 320 pessoas na terça e uma convenção de vendas fechando a semana. Memorando de serviços bem escrito é o que impede que isso vire caos.", 'media' => 'restaurant'],
                ['text' => "A maior margem perdida em evento de hotel não está no salão: está no que não foi ofertado. Coffee break premium, bebida, hospedagem do staff, sala de apoio. Comercial de eventos precisa ser treinado para enxergar isso.", 'media' => null],
                ['text' => "Visita técnica com o cliente 15 dias antes do evento vale mais do que dez e-mails. Todo mundo sai da sala com a mesma imagem na cabeça.", 'media' => 'team'],
            ],
            'Recursos Humanos' => [
                ['text' => "Turnover de linha de frente na hotelaria brasileira ronda os 40% ao ano. A maior parte das saídas acontece nos primeiros 90 dias — ou seja, o problema raramente é o salário, é a integração.", 'media' => 'team'],
                ['text' => "Vaga de gerente geral que abri semana passada teve 190 currículos em cinco dias. Dos 190, oito tinham experiência real em P&L de hotel. O funil na hotelaria é largo em cima e finíssimo embaixo.", 'media' => null],
                ['text' => "Plano de carreira em hotel não precisa ser sofisticado. Precisa existir e ser dito em voz alta: mensageiro vira recepcionista, recepcionista vira supervisor, supervisor vira gerente de hospedagem.", 'media' => 'team'],
            ],
            'Marketing Hoteleiro' => [
                ['text' => "Trocamos o motor de reservas e ganhamos 22% de conversão só arrumando o fluxo de três telas para uma. Antes de investir em mídia, arrume o que acontece depois do clique.", 'media' => 'analytics'],
                ['text' => "Foto de hotel com grande angular exagerada é armadilha. O hóspede chega, o quarto parece menor do que na foto e você ganhou uma avaliação ruim antes mesmo do check-in terminar.", 'media' => 'room'],
                ['text' => "Metabusca bem configurado é a forma mais barata de brigar de igual para igual com OTA na sua própria marca.", 'media' => null],
            ],
            'Sustentabilidade' => [
                ['text' => "Trocamos a iluminação de 240 apartamentos por LED e instalamos sensor de presença nas áreas de serviço. Payback em 11 meses e 18% a menos na conta de energia.", 'media' => 'resort'],
                ['text' => "Sustentabilidade em hotel de selva não é discurso: 100% da nossa energia é solar, a água passa por tratamento próprio e 60% da equipe é das comunidades vizinhas.", 'media' => 'destination'],
                ['text' => "Programa de reuso de enxoval funciona quando o hóspede entende o porquê — e quando a camareira também. Treinar a equipe é metade do resultado.", 'media' => null],
            ],
            'Carreira' => [
                ['text' => "Comecei como mensageiro em 2003 carregando mala em hotel de estrada. Levei nove anos para chegar à gerência. Hoje vejo gente fazendo esse caminho em quatro — porque estuda, mede resultado e não tem medo de pedir o próximo passo.", 'media' => null],
                ['text' => "Conselho para quem está na recepção e quer crescer: aprenda a ler um P&L. É o idioma que separa quem opera de quem decide.", 'media' => 'analytics'],
                ['text' => "Currículo de hotelaria com 'proativo e dinâmico' no topo e nenhum número embaixo não me diz nada. Escreva quantos apartamentos, qual ocupação, qual time, qual resultado.", 'media' => null],
            ],
            'Manutenção' => [
                ['text' => "Preventiva bem feita é a diferença entre trocar um rolamento na baixa temporada e ficar sem ar-condicionado no réveillon. Aqui rodamos 100% do plano nos meses de menor ocupação.", 'media' => null],
                ['text' => "Manutenção em lodge de selva: peça de reposição leva dois dias de barco. Nosso estoque crítico é um projeto de gestão em si.", 'media' => 'destination'],
            ],
        ];
    }

    /** Posts institucionais publicados pela página do hotel. */
    public static function hotelPosts(): array
    {
        return [
            ['text' => "Estamos com vagas abertas para a alta temporada! Se você tem paixão por hospitalidade e quer fazer parte de um time que se importa de verdade com o hóspede, dá uma olhada nas nossas oportunidades.", 'media' => 'team'],
            ['text' => "Nossa equipe de governança fechou o mês com 100% dos apartamentos entregues dentro do padrão de vistoria. Orgulho enorme desse time.", 'media' => 'room'],
            ['text' => "Novidade na operação: o restaurante principal passa a servir jantar sete dias por semana, com menu assinado pela nossa cozinha e produtos da região.", 'media' => 'restaurant'],
            ['text' => "Recebemos o selo de excelência mais uma vez este ano. Obrigado a cada hóspede que reservou um minuto para avaliar a estadia — e a cada colaborador que fez por merecer.", 'media' => 'resort'],
            ['text' => "Semana de treinamento interno concluída: 84 colaboradores passaram pelo módulo de atendimento e recuperação de falhas.", 'media' => 'team'],
        ];
    }

    /**
     * Comentários por tema — respondem ao assunto do post, não são genéricos.
     *
     * @return array<string, array<int, string>>
     */
    public static function comments(): array
    {
        return [
            'Revenue Management' => [
                'Isso aqui é exatamente o que tento explicar para a diretoria toda reunião. Tarifa média puxa resultado, ocupação sozinha puxa custo.',
                'Comecei a rodar pick-up semanal há dois meses e já mudou a forma como fecho a tarifa do fim de semana.',
                'Qual ferramenta vocês usam para acompanhar tarifa da concorrência? Estou avaliando trocar a nossa.',
                'Aqui a venda direta está em 26% e subindo. Devagar, mas subindo.',
            ],
            'Gestão Hoteleira' => [
                'A reunião de 15 minutos em pé mudou o jogo na minha unidade também. Recomendo demais.',
                'Gerente que não anda no corredor não conhece o próprio hotel. Assino embaixo.',
                'Zero acidente com afastamento em uma operação desse tamanho é resultado de gente, não de sorte. Parabéns ao time.',
            ],
            'Hospitalidade' => [
                'Foi assim que eu me apaixonei por essa profissão: pelo que a gente resolve quando nada estava previsto.',
                'Vou levar esse exemplo para o treinamento da minha equipe de recepção amanhã.',
                'Comentário aberto vale mais que a nota. A nota diz que tem problema, o comentário diz qual é.',
                'Trinta segundos e uma caneta. É impressionante como o básico ainda funciona.',
            ],
            'F&B' => [
                'Cortar itens do buffet dá um medo danado no começo, mas o desperdício some e a percepção melhora. Passamos por isso ano passado.',
                'CMV sem ficha técnica é chute com nome bonito.',
                '38 minutos para 400 pessoas é serviço de gente treinada. Meus parabéns à brigada.',
                'Trabalhar com produtor local muda a comida e muda a história que você conta para o hóspede.',
            ],
            'Governança' => [
                'A parte da logística do carrinho e do abastecimento do andar é subestimadíssima. Resolvemos isso e a produtividade subiu sem cobrar mais ninguém.',
                'Enxoval como ativo: vou usar essa frase na próxima reunião de custos.',
                'Governança devia ter cadeira fixa na reunião de diretoria. Sempre.',
            ],
            'Eventos' => [
                'Memorando bem escrito salva evento. Já vi casamento inteiro girar em torno de um documento de duas páginas.',
                'A margem perdida no que não foi ofertado é real. Estamos treinando o comercial exatamente nisso agora.',
            ],
            'Recursos Humanos' => [
                'Os primeiros 90 dias são tudo. Criamos um padrinho para cada novato e o turnover de entrada caiu pela metade.',
                'O funil largo em cima e fino embaixo descreve exatamente o que vivo em toda seleção de liderança.',
                'Plano de carreira dito em voz alta. Simples e quase ninguém faz.',
            ],
            'Marketing Hoteleiro' => [
                'Reduzir o checkout de três telas para uma é o tipo de ganho que ninguém posta no LinkedIn mas paga a conta.',
                'Foto que engana gera avaliação ruim garantida. O hóspede compara antes mesmo de desfazer a mala.',
            ],
            'Sustentabilidade' => [
                'Payback de 11 meses convence qualquer diretoria. É assim que se vende sustentabilidade internamente.',
                'Contratar da comunidade local é o ponto que mais respeito no trabalho de vocês.',
            ],
            'Carreira' => [
                'Precisava ler isso hoje. Estou há três anos na recepção e travado sobre qual próximo passo dar.',
                'Ler P&L foi o divisor de águas na minha carreira também.',
                'Currículo com número é outro nível. Vou reescrever o meu esta semana.',
            ],
            'Manutenção' => [
                'Rodar 100% da preventiva na baixa é o que separa operação madura de operação apagando incêndio.',
                'Estoque crítico em operação remota merece um curso inteiro só sobre isso.',
            ],
            'geral' => [
                'Excelente ponto. Salvando aqui para revisitar depois.',
                'Conteúdo bom demais, obrigado por compartilhar.',
                'Concordo integralmente — e é raro ver alguém falando disso publicamente.',
                'Compartilhando com o meu time agora.',
            ],
        ];
    }

    /**
     * Vagas por título com requisitos e benefícios coerentes.
     *
     * @return array<int, array<string, string>>
     */
    public static function jobs(): array
    {
        return [
            [
                'title' => 'Recepcionista (12x36)',
                'contract_type' => 'clt', 'modality' => 'on_site', 'salary' => 'R$ 2.100 - R$ 2.600',
                'description' => 'Atendimento de check-in e check-out, controle de reservas no PMS, atendimento telefônico e apoio ao hóspede durante toda a estadia. Escala 12x36 com revezamento de finais de semana.',
                'requirements' => "Ensino médio completo (superior em Hotelaria ou Turismo é diferencial)\nExperiência mínima de 1 ano em recepção de hotel\nInglês intermediário\nConhecimento em PMS (Desbravador, CM Soluções ou similar)",
                'benefits' => "Vale-transporte, refeição no local, plano de saúde após 90 dias, day off no aniversário e desconto em diárias da rede.",
            ],
            [
                'title' => 'Camareira / Camareiro',
                'contract_type' => 'clt', 'modality' => 'on_site', 'salary' => 'R$ 1.700 - R$ 2.000',
                'description' => 'Arrumação e limpeza de apartamentos em ocupação e saída, reposição de enxoval e amenities, comunicação de defeitos para a manutenção e cumprimento do padrão de vistoria da governança.',
                'requirements' => "Ensino fundamental completo\nExperiência em limpeza de apartamentos (hotel ou flat)\nAtenção a detalhes e organização\nDisponibilidade para escala de finais de semana",
                'benefits' => "Vale-transporte, refeição no local, uniforme, cesta básica e plano odontológico.",
            ],
            [
                'title' => 'Analista de Reservas',
                'contract_type' => 'clt', 'modality' => 'hybrid', 'salary' => 'R$ 3.200 - R$ 4.000',
                'description' => 'Gestão de reservas individuais e de grupos, atualização de tarifas e disponibilidade nos canais, controle de no-show e overbooking e apoio ao time de revenue no acompanhamento de pick-up.',
                'requirements' => "Superior cursando ou completo em Hotelaria, Turismo ou Administração\nExperiência com channel manager e extranets de OTA\nExcel intermediário (tabela dinâmica e PROCV)\nInglês intermediário",
                'benefits' => "Plano de saúde e odontológico, vale-refeição, home office 2x por semana e bônus semestral por meta.",
            ],
            [
                'title' => 'Gerente de Hospedagem',
                'contract_type' => 'clt', 'modality' => 'on_site', 'salary' => 'R$ 7.500 - R$ 9.500',
                'description' => 'Liderança das áreas de recepção, governança e mensageria. Responsável pelos indicadores de satisfação do hóspede, escala das equipes, controle de custos da hospedagem e substituição do gerente geral nas ausências.',
                'requirements' => "Superior completo em Hotelaria ou Administração\nMínimo de 4 anos em liderança de hospedagem\nVivência com indicadores (NPS, RevPAR, custo por apartamento ocupado)\nInglês avançado",
                'benefits' => "Plano de saúde extensivo a dependentes, PLR, celular corporativo, moradia no primeiro mês e programa de desenvolvimento de liderança.",
            ],
            [
                'title' => 'Chef de Partida',
                'contract_type' => 'clt', 'modality' => 'on_site', 'salary' => 'R$ 3.000 - R$ 3.800',
                'description' => 'Condução de uma praça da cozinha quente, mise en place, execução dentro da ficha técnica, controle de perdas e apoio ao chef executivo na produção de eventos e banquetes.',
                'requirements' => "Formação técnica em Gastronomia\nExperiência mínima de 2 anos em cozinha de hotel ou restaurante de alto volume\nConhecimento de boas práticas de manipulação (RDC 216)\nDisponibilidade para trabalhar em turnos",
                'benefits' => "Refeição no local, vale-transporte, plano de saúde, uniforme completo e curso de aperfeiçoamento custeado pela empresa.",
            ],
            [
                'title' => 'Barman / Bartender de Piscina',
                'contract_type' => 'clt', 'modality' => 'on_site', 'salary' => 'R$ 2.200 - R$ 2.800',
                'description' => 'Preparo de drinks clássicos e autorais no bar da piscina, controle de estoque do ponto de consumo, atendimento direto ao hóspede em regime all inclusive e organização do mise en place do bar.',
                'requirements' => "Curso de bartender ou experiência comprovada de 2 anos\nConhecimento de coquetelaria clássica\nInglês ou espanhol básico\nBoa comunicação e presença de salão",
                'benefits' => "Gorjeta rateada, refeição no local, transporte fretado, uniforme e plano de saúde.",
            ],
            [
                'title' => 'Analista de Revenue Management',
                'contract_type' => 'clt', 'modality' => 'hybrid', 'salary' => 'R$ 5.500 - R$ 7.000',
                'description' => 'Definição e acompanhamento da estratégia tarifária, análise de pick-up e forecast, monitoramento de concorrência, gestão do mix de canais e construção de relatórios para a diretoria.',
                'requirements' => "Superior completo em Hotelaria, Economia, Administração ou Estatística\nExperiência com RMS e ferramentas de rate shopping\nExcel avançado e leitura de indicadores de mercado (STR ou similar)\nInglês avançado",
                'benefits' => "Plano de saúde, PLR anual, home office 3x por semana, auxílio educação e ambiente com autonomia real de decisão.",
            ],
            [
                'title' => 'Assistente de Eventos',
                'contract_type' => 'clt', 'modality' => 'on_site', 'salary' => 'R$ 2.600 - R$ 3.200',
                'description' => 'Apoio ao gerente de eventos na elaboração de propostas, montagem de salas, acompanhamento de cerimonial, elaboração do memorando de serviços e atendimento ao cliente durante o evento.',
                'requirements' => "Superior cursando em Hotelaria, Turismo, Eventos ou Publicidade\nOrganização e capacidade de lidar com múltiplas demandas simultâneas\nDisponibilidade para finais de semana e período noturno\nPacote Office",
                'benefits' => "Vale-refeição, vale-transporte, plano de saúde, folga compensatória e participação em eventos de grandes marcas.",
            ],
            [
                'title' => 'Auxiliar de Manutenção Predial',
                'contract_type' => 'clt', 'modality' => 'on_site', 'salary' => 'R$ 2.300 - R$ 2.900',
                'description' => 'Execução do plano de manutenção preventiva e corretiva em apartamentos e áreas comuns, pequenos reparos hidráulicos, elétricos e de alvenaria e atendimento de chamados abertos pela recepção.',
                'requirements' => "Curso técnico em Elétrica, Mecânica ou Edificações\nExperiência em manutenção predial (hotelaria é diferencial)\nNR-10 e NR-35 desejáveis\nDisponibilidade para regime de plantão",
                'benefits' => "Adicional de periculosidade quando aplicável, refeição no local, plano de saúde, uniforme e EPIs completos.",
            ],
            [
                'title' => 'Estágio em Hotelaria (Programa Trainee)',
                'contract_type' => 'internship', 'modality' => 'on_site', 'salary' => 'R$ 1.600 (bolsa) + benefícios',
                'description' => 'Programa de 12 meses com rodízio pelas áreas de recepção, governança, A&B e reservas, com acompanhamento de um gestor padrinho e projeto final apresentado à diretoria.',
                'requirements' => "Cursando a partir do 3º semestre de Hotelaria, Turismo ou Administração\nDisponibilidade de 6 horas diárias\nInglês intermediário\nVontade real de construir carreira em hotelaria",
                'benefits' => "Bolsa-auxílio, vale-transporte, refeição no local, seguro de vida e efetivação para quem se destacar no programa.",
            ],
            [
                'title' => 'Coordenador(a) de Marketing Hoteleiro',
                'contract_type' => 'pj', 'modality' => 'remote', 'salary' => 'R$ 8.000 - R$ 11.000',
                'description' => 'Gestão da venda direta, campanhas de mídia paga, produção de conteúdo para redes sociais, relacionamento com OTAs e acompanhamento do funil do motor de reservas.',
                'requirements' => "Experiência comprovada em marketing para hotelaria ou turismo\nDomínio de Google Ads, Meta Ads e metabusca\nVivência com motor de reservas e CRM hoteleiro\nPortfólio com resultados mensuráveis",
                'benefits' => "Contrato PJ com 100% remoto, orçamento de mídia próprio, bônus por crescimento de venda direta e diárias de cortesia na rede.",
            ],
            [
                'title' => 'Supervisor(a) de Governança',
                'contract_type' => 'clt', 'modality' => 'on_site', 'salary' => 'R$ 3.500 - R$ 4.400',
                'description' => 'Supervisão da equipe de camareiras, vistoria de apartamentos, controle de enxoval e produtos de limpeza, elaboração de escalas e interface direta com recepção e manutenção.',
                'requirements' => "Ensino médio completo (superior em Hotelaria é diferencial)\nExperiência mínima de 3 anos em governança\nLiderança de equipes de pelo menos 10 pessoas\nConhecimento de controle de enxoval e inventário",
                'benefits' => "Plano de saúde, refeição no local, vale-transporte, uniforme e programa de desenvolvimento de liderança.",
            ],
        ];
    }

    /**
     * Cursos com ementa real por aula.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function courses(): array
    {
        return [
            [
                'title' => 'Revenue Management na Prática: da tarifa ao forecast',
                'level' => 'intermediate', 'price' => 39700, 'specialty' => 'Revenue Management', 'media' => 'analytics',
                'description' => 'Curso prático para quem precisa definir tarifa com critério. Você vai montar a estratégia de preço, ler pick-up, construir forecast e organizar o mix de canais da sua propriedade — com planilhas e exemplos reais de hotéis brasileiros.',
                'lessons' => [
                    ['Os quatro indicadores que sustentam a decisão de preço', 'Ocupação, diária média, RevPAR e TrevPAR: o que cada um esconde e por que olhar só um deles leva à decisão errada.'],
                    ['Segmentação de demanda e calendário de demanda', 'Como separar lazer, corporativo, grupos e OTA e montar o calendário que antecipa os dias fortes e fracos do seu hotel.'],
                    ['Construindo a grade tarifária', 'BAR, tarifas fechadas, restrições de estadia mínima e as regras que evitam que a sua tarifa vire bagunça.'],
                    ['Lendo o relatório de pick-up', 'A rotina semanal de 30 minutos que muda a sua decisão de preço para os próximos 90 dias.'],
                    ['Forecast de 12 meses sem adivinhação', 'Montando o forecast rolante com base histórica, ritmo de reserva e eventos da praça.'],
                    ['Mix de canais e custo real de distribuição', 'Quanto custa cada canal de verdade e como aumentar a venda direta sem perder volume.'],
                ],
            ],
            [
                'title' => 'Gestão de Governança e Housekeeping',
                'level' => 'intermediate', 'price' => 29700, 'specialty' => 'Governança', 'media' => 'room',
                'description' => 'A área que mais pesa no custo e mais aparece na avaliação do hóspede. Do padrão de arrumação ao controle de enxoval, com foco em produtividade sem sobrecarregar a equipe.',
                'lessons' => [
                    ['Padrão de arrumação: escrevendo o seu manual', 'Como transformar o jeito da sua casa em um padrão auditável, com checklist de vistoria.'],
                    ['Produtividade de camareira sem sobrecarga', 'Cálculo de UH por turno, percurso do andar, montagem do carrinho e abastecimento.'],
                    ['Gestão de enxoval como ativo', 'Giro, marcação por lote, inventário e o cálculo de quanto enxoval a sua operação realmente precisa.'],
                    ['Química de limpeza e segurança do trabalho', 'Diluição correta, EPIs e o que a fiscalização olha primeiro.'],
                    ['Escalas, absenteísmo e alta temporada', 'Montando escala que aguenta pico de ocupação sem estourar hora extra.'],
                ],
            ],
            [
                'title' => 'Experiência do Hóspede: da reserva ao pós-checkout',
                'level' => 'beginner', 'price' => 0, 'specialty' => 'Hospitalidade', 'media' => 'room',
                'description' => 'Curso gratuito e introdutório sobre jornada do hóspede. Ideal para quem está começando na linha de frente ou quer estruturar a cultura de atendimento da equipe.',
                'lessons' => [
                    ['Mapeando a jornada do hóspede', 'Os 11 pontos de contato entre a busca no Google e a avaliação pós-estadia.'],
                    ['O check-in que define a estadia', 'Os primeiros três minutos e o que fazer quando tudo dá errado neles.'],
                    ['Recuperação de falhas', 'O protocolo LATE: ouvir, assumir, resolver e acompanhar — com autonomia para a linha de frente.'],
                    ['Lendo avaliações como diagnóstico', 'Como transformar comentário aberto de OTA em plano de ação da semana.'],
                ],
            ],
            [
                'title' => 'Gestão de A&B em Hotelaria: cardápio, CMV e operação',
                'level' => 'advanced', 'price' => 49700, 'specialty' => 'F&B', 'media' => 'food',
                'description' => 'Para chefs, coordenadores e gerentes de A&B que precisam entregar comida boa com margem saudável. Ficha técnica, engenharia de cardápio, café da manhã e operação de banquetes.',
                'lessons' => [
                    ['Ficha técnica de verdade', 'Rendimento, fator de correção, custo por porção e por que a sua ficha atual provavelmente está errada.'],
                    ['Engenharia de cardápio', 'Classificando os pratos entre estrela, burro de carga, quebra-cabeça e abacaxi — e o que fazer com cada um.'],
                    ['Café da manhã que vende o hotel', 'Curadoria de itens, estações de preparo na hora e controle de desperdício.'],
                    ['Compras, fornecedores e produto local', 'Negociação, sazonalidade e como trabalhar com produtor da região sem quebrar a operação.'],
                    ['Banquetes e eventos', 'Do memorando ao serviço: dimensionamento de brigada e tempo de saída de praça.'],
                    ['Fechamento de CMV mensal', 'Inventário, transferências entre pontos de consumo e a conversa com a controladoria.'],
                ],
            ],
            [
                'title' => 'Liderança na Operação Hoteleira',
                'level' => 'intermediate', 'price' => 34700, 'specialty' => 'Gestão Hoteleira', 'media' => 'team',
                'description' => 'Como liderar times de linha de frente em uma operação que não para. Rotina de liderança, feedback, escala, indicadores e a conversa difícil que todo gestor adia.',
                'lessons' => [
                    ['A rotina de liderança de 15 minutos', 'A reunião diária que alinha sete áreas sem tomar a manhã de ninguém.'],
                    ['Indicadores que a sua equipe entende', 'Traduzindo RevPAR e NPS para a linguagem da recepção e da governança.'],
                    ['Feedback e a conversa difícil', 'Estrutura para dar retorno duro sem quebrar a relação.'],
                    ['Escala, folga e clima', 'Como a escala mal feita destrói o clima antes de qualquer outra coisa.'],
                    ['Formando o seu sucessor', 'Plano de desenvolvimento individual aplicado à realidade de hotel.'],
                ],
            ],
            [
                'title' => 'Marketing e Venda Direta para Hotéis',
                'level' => 'intermediate', 'price' => 27700, 'specialty' => 'Marketing Hoteleiro', 'media' => 'analytics',
                'description' => 'Reduza a dependência de OTA sem perder volume. Motor de reservas, metabusca, mídia paga, conteúdo e CRM aplicados à realidade de hotéis independentes e pequenas redes.',
                'lessons' => [
                    ['Diagnóstico da sua distribuição', 'Quanto cada canal custa de verdade e onde está a sua margem.'],
                    ['Motor de reservas que converte', 'Fluxo, paridade, prova social e os erros que derrubam a conversão pela metade.'],
                    ['Metabusca na prática', 'Google Hotel Ads e Trivago: quando vale, quanto investir e como medir.'],
                    ['Conteúdo que mostra o produto', 'Fotografia, vídeo e a honestidade que evita avaliação ruim.'],
                    ['CRM e recompra', 'E-mail que o hóspede abre e a base que ninguém do seu mercado está usando.'],
                ],
            ],
            [
                'title' => 'Eventos e MICE: do orçamento ao pós-evento',
                'level' => 'advanced', 'price' => 44700, 'specialty' => 'Eventos', 'media' => 'restaurant',
                'description' => 'Estruture o departamento de eventos do seu hotel: proposta comercial, memorando de serviços, precificação de espaço e A&B, operação no dia e fechamento com margem.',
                'lessons' => [
                    ['Precificação de espaço e pacotes', 'Aluguel de salão, mínimo de consumo e o cálculo que sustenta o desconto.'],
                    ['Proposta comercial que fecha', 'Estrutura, prazos, condições e as perguntas certas na visita técnica.'],
                    ['Memorando de serviços', 'O documento que alinha cozinha, salão, manutenção e recepção em uma página.'],
                    ['Operação no dia do evento', 'Escala, briefing, plano B e comunicação com o cliente.'],
                    ['Fechamento e margem real', 'Extras, retrabalho e o pós-evento que gera o próximo contrato.'],
                ],
            ],
            [
                'title' => 'Carreira em Hotelaria: da recepção à gerência',
                'level' => 'beginner', 'price' => 0, 'specialty' => 'Carreira', 'media' => 'team',
                'description' => 'Curso gratuito para quem está na linha de frente e quer crescer. Trilhas de carreira reais, o que cada degrau exige e como se preparar para o próximo.',
                'lessons' => [
                    ['O mapa da carreira hoteleira', 'As trilhas de hospedagem, A&B, comercial e RM — e como se movimentar entre elas.'],
                    ['O que muda de operador para líder', 'As competências que ninguém ensina no treinamento operacional.'],
                    ['Aprendendo a ler um P&L', 'Receita, custo direto, GOP: o idioma de quem decide.'],
                    ['Currículo e entrevista em hotelaria', 'Como falar de resultado quando o seu trabalho é atendimento.'],
                ],
            ],
            [
                'title' => 'Sustentabilidade Aplicada à Hotelaria',
                'level' => 'intermediate', 'price' => 24700, 'specialty' => 'Sustentabilidade', 'media' => 'destination',
                'description' => 'Práticas que reduzem impacto e conta de luz ao mesmo tempo: eficiência energética, água, resíduos, compras responsáveis e certificações reconhecidas pelo mercado.',
                'lessons' => [
                    ['Diagnóstico de consumo', 'Medindo energia, água e resíduos por apartamento ocupado.'],
                    ['Eficiência energética com payback curto', 'LED, sensores, ar-condicionado e as intervenções que se pagam em menos de dois anos.'],
                    ['Água e resíduos', 'Reuso de enxoval, tratamento e coleta seletiva que funciona na prática.'],
                    ['Compras responsáveis e cadeia local', 'Fornecedor da região, contratação da comunidade e o efeito na experiência do hóspede.'],
                    ['Certificações e comunicação', 'Quais selos valem a pena e como comunicar sem cair em greenwashing.'],
                ],
            ],
            [
                'title' => 'Recrutamento e Retenção na Hotelaria',
                'level' => 'intermediate', 'price' => 31700, 'specialty' => 'Recursos Humanos', 'media' => 'team',
                'description' => 'Como atrair, selecionar e principalmente segurar gente boa em um setor com turnover alto e escala difícil. Da descrição da vaga ao programa de integração de 90 dias.',
                'lessons' => [
                    ['Descrição de vaga que atrai o perfil certo', 'O que escrever e o que parar de escrever nos seus anúncios.'],
                    ['Seleção para linha de frente', 'Entrevista por competência adaptada à realidade operacional.'],
                    ['Os primeiros 90 dias', 'Integração, padrinho e os marcos que reduzem o turnover de entrada.'],
                    ['Plano de carreira e sucessão', 'Trilhas simples que funcionam em hotel de qualquer porte.'],
                    ['Clima, escala e reconhecimento', 'O que realmente segura gente quando não dá para pagar mais.'],
                ],
            ],
        ];
    }

    /**
     * Mentorias por especialidade.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function mentorships(): array
    {
        return [
            [
                'title' => 'Diagnóstico de Revenue Management do seu hotel',
                'specialty' => 'Revenue Management', 'duration' => 60, 'type' => 'online', 'price' => 45000,
                'description' => 'Sessão individual em que analisamos juntos a sua grade tarifária, o mix de canais e o pick-up dos próximos 90 dias. Você sai com três ações concretas de preço para aplicar na mesma semana. Traga seu relatório de ocupação dos últimos 12 meses.',
            ],
            [
                'title' => 'Mentoria de carreira: qual o seu próximo passo na hotelaria',
                'specialty' => 'Carreira', 'duration' => 45, 'type' => 'online', 'price' => 0,
                'description' => 'Conversa gratuita para quem está na linha de frente e não sabe qual caminho seguir. Mapeamos onde você está, onde quer chegar e o que falta — sem papo motivacional vazio.',
            ],
            [
                'title' => 'Revisão de cardápio e CMV com chef executivo',
                'specialty' => 'F&B', 'duration' => 60, 'type' => 'online', 'price' => 39000,
                'description' => 'Trago o olhar de vinte anos de cozinha de hotel para o seu cardápio: ficha técnica, engenharia de menu e onde está escapando a sua margem. Envie o cardápio e a planilha de custos antes da sessão.',
            ],
            [
                'title' => 'Estruturando a governança da sua propriedade',
                'specialty' => 'Governança', 'duration' => 60, 'type' => 'online', 'price' => 32000,
                'description' => 'Para governantas e supervisoras que precisam organizar produtividade, enxoval e escala. Analisamos o seu padrão atual e montamos um plano de ajuste de 60 dias.',
            ],
            [
                'title' => 'Consultoria express de operação hoteleira',
                'specialty' => 'Gestão Hoteleira', 'duration' => 90, 'type' => 'presential', 'price' => 95000,
                'description' => 'Visita presencial de meio período à propriedade, com caminhada pela operação, conversa com as chefias e devolutiva escrita em até cinco dias. Disponível para hotéis no eixo Rio-São Paulo.',
            ],
            [
                'title' => 'Plano de venda direta para hotel independente',
                'specialty' => 'Marketing Hoteleiro', 'duration' => 60, 'type' => 'online', 'price' => 38000,
                'description' => 'Sessão focada em reduzir sua dependência de OTA: diagnóstico do motor de reservas, metabusca e um plano de mídia compatível com o seu orçamento real.',
            ],
            [
                'title' => 'Preparação para entrevista de liderança em hotelaria',
                'specialty' => 'Recursos Humanos', 'duration' => 45, 'type' => 'online', 'price' => 28000,
                'description' => 'Simulação de entrevista com quem recruta liderança hoteleira todos os dias. Feedback direto sobre discurso, números e postura — inclusive sobre o que não dizer.',
            ],
            [
                'title' => 'Montando o departamento de eventos do seu hotel',
                'specialty' => 'Eventos', 'duration' => 60, 'type' => 'online', 'price' => 42000,
                'description' => 'Da precificação do salão ao memorando de serviços. Ideal para hotéis que já vendem eventos, mas ainda no improviso.',
            ],
            [
                'title' => 'Jornada do hóspede: onde a sua operação perde nota',
                'specialty' => 'Hospitalidade', 'duration' => 60, 'type' => 'online', 'price' => 35000,
                'description' => 'Mapeamos os pontos de contato do seu hotel e identificamos onde a experiência quebra. Levo suas avaliações públicas analisadas para a sessão.',
            ],
            [
                'title' => 'Primeiros passos em sustentabilidade hoteleira',
                'specialty' => 'Sustentabilidade', 'duration' => 45, 'type' => 'online', 'price' => 0,
                'description' => 'Sessão gratuita para propriedades que querem começar. Diagnóstico rápido de consumo e as três intervenções de payback mais curto para o seu caso.',
            ],
        ];
    }

    /**
     * Vídeos por especialidade: título, descrição, categoria e pools de cena.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function videos(): array
    {
        return [
            ['title' => 'Como definir a tarifa do próximo fim de semana em 10 minutos', 'specialty' => 'Revenue Management', 'category' => 'gestao', 'scenes' => ['analytics', 'facade', 'room'], 'description' => 'A rotina rápida que uso para fechar preço de sexta a domingo: pick-up, concorrência e evento na praça.'],
            ['title' => 'Os 3 erros de precificação que vejo em quase todo hotel independente', 'specialty' => 'Revenue Management', 'category' => 'gestao', 'scenes' => ['analytics', 'resort'], 'description' => 'Tarifa plana, desconto reativo e paridade quebrada: como cada um deles come a sua margem.'],
            ['title' => 'A reunião de 15 minutos que organiza o dia inteiro da operação', 'specialty' => 'Gestão Hoteleira', 'category' => 'operacoes', 'scenes' => ['team', 'facade', 'room'], 'description' => 'Formato, participantes e pauta da reunião diária de liderança que uso desde 2019.'],
            ['title' => 'Custo por apartamento ocupado: como calcular e o que fazer com ele', 'specialty' => 'Gestão Hoteleira', 'category' => 'gestao', 'scenes' => ['analytics', 'room'], 'description' => 'O indicador mais honesto da sua operação, explicado com números reais.'],
            ['title' => 'Os primeiros 3 minutos do check-in', 'specialty' => 'Hospitalidade', 'category' => 'atendimento', 'scenes' => ['facade', 'room', 'resort'], 'description' => 'O que acontece nesses três minutos define a avaliação da estadia inteira. Vamos falar de roteiro, postura e autonomia.'],
            ['title' => 'Recuperação de falha: o protocolo que dou para toda linha de frente', 'specialty' => 'Hospitalidade', 'category' => 'atendimento', 'scenes' => ['room', 'facade'], 'description' => 'Ouvir, assumir, resolver e acompanhar — com exemplos reais de situações difíceis.'],
            ['title' => 'Café da manhã: cortando itens sem perder percepção de valor', 'specialty' => 'F&B', 'category' => 'operacoes', 'scenes' => ['food', 'restaurant'], 'description' => 'Como reduzimos 22 itens do buffet e ainda assim subimos a nota do café.'],
            ['title' => 'Ficha técnica na prática: montando a sua em 20 minutos', 'specialty' => 'F&B', 'category' => 'gestao', 'scenes' => ['food', 'restaurant'], 'description' => 'Rendimento, fator de correção e custo por porção com um prato do começo ao fim.'],
            ['title' => 'Produtividade de camareira sem sobrecarregar ninguém', 'specialty' => 'Governança', 'category' => 'operacoes', 'scenes' => ['room', 'facade'], 'description' => 'Carrinho, percurso e abastecimento do andar: onde está o ganho real.'],
            ['title' => 'Controle de enxoval: parando de comprar lençol duas vezes por ano', 'specialty' => 'Governança', 'category' => 'gestao', 'scenes' => ['room', 'analytics'], 'description' => 'Giro, marcação por lote e inventário mensal explicados de forma simples.'],
            ['title' => 'Como sair da recepção e chegar à gerência', 'specialty' => 'Carreira', 'category' => 'carreira', 'scenes' => ['facade', 'team', 'room'], 'description' => 'O caminho real, com os degraus que quase ninguém conta para quem está começando.'],
            ['title' => 'Currículo de hotelaria: o que tirar e o que colocar', 'specialty' => 'Carreira', 'category' => 'carreira', 'scenes' => ['team', 'analytics'], 'description' => 'Transformando atendimento em resultado mensurável no papel.'],
            ['title' => 'Reduzindo dependência de OTA sem perder volume', 'specialty' => 'Marketing Hoteleiro', 'category' => 'gestao', 'scenes' => ['analytics', 'resort'], 'description' => 'O plano de venda direta que aplicamos em hotéis de 30 a 200 apartamentos.'],
            ['title' => 'Fotografia de hotel: o que a foto boa e honesta tem em comum', 'specialty' => 'Marketing Hoteleiro', 'category' => 'operacoes', 'scenes' => ['room', 'resort', 'restaurant'], 'description' => 'Enquadramento, luz e a armadilha da grande angular que gera avaliação ruim.'],
            ['title' => 'Turnover na linha de frente: atacando os primeiros 90 dias', 'specialty' => 'Recursos Humanos', 'category' => 'carreira', 'scenes' => ['team', 'facade'], 'description' => 'Programa de integração e padrinho: o que funcionou nos hotéis que acompanho.'],
            ['title' => 'Memorando de serviços: uma página que salva o evento', 'specialty' => 'Eventos', 'category' => 'operacoes', 'scenes' => ['restaurant', 'team'], 'description' => 'Estrutura, quem recebe e quando enviar. Com um modelo pronto para copiar.'],
            ['title' => 'Eficiência energética com payback abaixo de 24 meses', 'specialty' => 'Sustentabilidade', 'category' => 'gestao', 'scenes' => ['resort', 'analytics'], 'description' => 'As intervenções que se pagam rápido e convencem qualquer diretoria.'],
            ['title' => 'Hotelaria de selva: operar longe de tudo', 'specialty' => 'Sustentabilidade', 'category' => 'operacoes', 'scenes' => ['destination', 'resort'], 'description' => 'Energia solar, água e estoque crítico em uma operação a 80 km da cidade.'],
        ];
    }

    /** Comentários de vídeo. */
    public static function videoComments(): array
    {
        return [
            'Assisti duas vezes e anotei tudo. Muito direto ao ponto.',
            'Finalmente alguém explicando isso com número na tela em vez de teoria.',
            'Levei esse vídeo para a reunião de liderança de segunda. Rendeu uma hora de discussão boa.',
            'Faz um aprofundando essa parte final? Ficou faltando pouco para eu conseguir aplicar aqui.',
            'Trabalho em hotel de 40 apartamentos e mesmo assim deu para adaptar. Obrigada!',
            'Isso resolve uma discussão que tenho com a minha diretoria há meses.',
            'Conteúdo de graça melhor que curso pago que eu já fiz.',
            'Salvando na playlist para mostrar para a equipe nova.',
        ];
    }

    /** Avaliações de consultores e cursos. */
    public static function reviews(): array
    {
        return [
            5 => [
                'Sessão objetiva, com material preparado e devolutiva por escrito depois. Saí com plano de ação pronto.',
                'Melhor investimento que fiz na minha formação este ano. Conteúdo aplicável no dia seguinte.',
                'Conhece a operação de verdade — não é teoria de sala de aula. Recomendo de olhos fechados.',
                'Aplicamos as três recomendações e o resultado apareceu no fechamento do mês seguinte.',
                'Didática excelente e paciência para explicar de novo quando eu não entendi de primeira.',
            ],
            4 => [
                'Conteúdo muito bom. Só senti falta de mais exemplos de hotéis pequenos, mas nada que comprometa.',
                'Gostei bastante. O material de apoio poderia ser um pouco mais completo.',
                'Sessão produtiva e honesta. Uma hora passou rápido demais — talvez valesse 90 minutos.',
                'Aprendi bastante. Alguns módulos ficaram densos para quem está começando.',
            ],
            3 => [
                'Bom conteúdo, mas esperava algo mais avançado para o meu nível de experiência.',
                'Cumpre o que promete. Para quem já trabalha com o tema há anos, boa parte é revisão.',
            ],
        ];
    }

    /** Mensagens de conversa (candidato/recrutador/mentoria). */
    public static function conversations(): array
    {
        return [
            [
                'Boa tarde! Vi a vaga de Analista de Reservas e me candidatei. Trabalho há três anos com channel manager e tenho disponibilidade imediata. Fico à disposição para conversar.',
                'Boa tarde! Recebemos sua candidatura, obrigada. Seu perfil está aderente — consegue uma conversa por vídeo na quinta às 15h?',
                'Consigo sim, quinta às 15h está ótimo. Já bloqueei na agenda.',
                'Perfeito, envio o link por e-mail hoje ainda. Até quinta!',
            ],
            [
                'Oi! Assisti seu vídeo sobre custo por apartamento ocupado e queria entender melhor como aplicar em uma pousada de 18 quartos. Faz sentido nessa escala?',
                'Oi! Faz total sentido, sim — em operação pequena o CPO é ainda mais revelador porque cada custo fixo pesa mais por UH. Se quiser, dá para destrinchar isso numa mentoria.',
                'Ótimo, vou reservar um horário. Obrigada pela resposta rápida!',
            ],
            [
                'Bom dia! Somos um hotel de 98 apartamentos em Curitiba e estamos estruturando o RM do zero. Vocês atendem esse porte?',
                'Bom dia! Atendo sim, tenho vários casos exatamente nesse perfil. Costumo começar com um diagnóstico de 60 minutos antes de propor qualquer coisa.',
                'Combinado. Vou alinhar com a diretoria e volto até sexta com as datas.',
            ],
            [
                'Oi Fernanda, tudo bem? Vi que você abriu uma vaga de Gerente de Hospedagem. Estou há quatro anos como supervisora e queria entender se meu perfil se encaixa.',
                'Oi! Tudo ótimo. Encaixa sim, principalmente se você já responde por indicador de satisfação e escala. Me manda seu currículo por aqui mesmo?',
                'Acabei de enviar por e-mail. Obrigada pela atenção!',
            ],
            [
                'Boa noite! O curso de Governança tem certificado? Preciso comprovar carga horária na empresa.',
                'Boa noite! Tem sim, certificado com carga horária discriminada por módulo, liberado ao concluir a última aula.',
                'Perfeito, vou me inscrever ainda hoje então.',
            ],
        ];
    }

    /** Legendas de story (vaga). */
    public static function jobStoryCaptions(): array
    {
        return [
            'Vaga aberta! Vem fazer parte do nosso time 🏨',
            'Estamos contratando para a alta temporada. Candidate-se!',
            'Oportunidade nova na nossa operação — corre que é rápido!',
            'Nosso time está crescendo. Essa vaga pode ser sua.',
            'Última semana de inscrições para essa vaga!',
        ];
    }

    /** Legendas de story (mentoria). */
    public static function mentorshipStoryCaptions(): array
    {
        return [
            'Abri 5 horários novos de mentoria para esta semana 📅',
            'Agenda de setembro aberta. Vamos conversar sobre o seu caso?',
            'Sessão individual com devolutiva por escrito. Últimas vagas.',
            'Recebi muitos pedidos e abri mais horários. Aproveita!',
        ];
    }
}
