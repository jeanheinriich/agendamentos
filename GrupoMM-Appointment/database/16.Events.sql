-- =====================================================================
-- Eventos de alarme
-- =====================================================================
-- O armazenamento de eventos recebidos dos equipamentos e do tratamento
-- dado a cada um deles.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Tipos de evento armazenados
-- ---------------------------------------------------------------------
-- Notice: eventos de aviso não prioritários
-- Alarm: evento de alarme (bateria desconectada, SOS, etc)
-- Message: eventos de mensagem de teclado
-- System: eventos de sistema
-- ---------------------------------------------------------------------
CREATE TYPE EventType AS ENUM('Notice', 'Alarm', 'Message', 'System');

-- ---------------------------------------------------------------------
-- Os grupos de eventos de rastreamento
-- ---------------------------------------------------------------------
-- A tabela que armazena os grupos de eventos gerados pelo rastreador.
-- Permite agrupar os eventos de rastreador, de forma a tornar a
-- configuração dos mesmos mais lógica, tornando juntos eventos de uma
-- mesma classe
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS trackerEventGroups (
  trackerEventGroupID serial,      -- ID do grupo de evento de rastreamento
  name                varchar(50)  -- Nome do grupo
                      NOT NULL,
  PRIMARY KEY (trackerEventGroupID),
  UNIQUE (name)
);

-- Insere os grupos de eventos
INSERT INTO trackerEventGroups (trackerEventGroupID, name) VALUES
  ( 1, 'Básico'),
  ( 2, 'Antena e comunicação'),
  ( 3, 'Bateria'),
  ( 4, 'Telemetria e comportamentos anômalos'),
  ( 5, 'Cerca virtual e pontos de controle'),
  ( 6, 'Controle de jornada/motorista'),
  ( 7, 'Alarme'),
  ( 8, 'Detecção de jammer'),
  ( 9, 'Sensor de combustível'),
  (10, 'Sensor de temperatura e umidade'),
  (11, 'Sensor de umidade'),
  (12, 'Sensor de fadiga'),
  (13, 'Sensor de colisão');

ALTER SEQUENCE trackereventgroups_trackereventgroupid_seq RESTART WITH 14;

-- ---------------------------------------------------------------------
-- Os eventos de rastreador
-- ---------------------------------------------------------------------
-- A tabela que armazena os possíveis eventos gerados pelo rastreador.
-- Eventos podem ser gerados pelo próprio equipamento ou por acessórios
-- acoplados ao mesmo.
-- ----------'-----------------------------------------------------------
CREATE TABLE IF NOT EXISTS trackerEvents (
  trackerEventID      serial,      -- ID do tipo de evento de alarme
  name                varchar(50)  -- Nome do evento
                      NOT NULL,
  trackerEventGroupID integer      -- O ID do grupo de eventos
                      NOT NULL,
  defaultEventType    EventType    -- A classificação padrão do evento
                      NOT NULL
                      DEFAULT 'Notice',
  PRIMARY KEY (trackerEventID),
  UNIQUE (name),
  FOREIGN KEY (trackerEventGroupID)
    REFERENCES trackerEventGroups(trackerEventGroupID)
    ON DELETE CASCADE
);

-- Insere os tipos de eventos de rastreador
INSERT INTO trackerEvents (trackerEventID, name, trackerEventGroupID, defaultEventType) VALUES
  (  1, 'Falha', 1, 'Alarm'),
  (  2, 'Botão de pânico', 1, 'Alarm'),
  (  3, 'Ignição ligada', 1, 'Alarm'),
  (  4, 'Ignição desligada', 1, 'Notice'),
  (  5, 'Porta', 1, 'Notice'),
  (  6, 'Vibração', 1, 'Notice'),
  (  7, 'Antena GPS desconectada', 2, 'Alarm'),
  (  8, 'Antena GPS reconectada', 2, 'Notice'),
  (  9, 'Antena GPS em curto', 2, 'Alarm'),
  ( 10, 'Simcard removido', 2, 'Alarm'),
  ( 11, 'Perda de sinal GPRS', 2, 'Notice'),
  ( 12, 'Sem comunicação', 2, 'Notice'),
  ( 13, 'Bateria interna baixa', 3, 'Alarm'),
  ( 14, 'Bateria interna conectada', 3, 'Notice'),
  ( 15, 'Bateria interna desconectada', 3, 'Alarm'),
  ( 16, 'Bateria baixa', 3, 'Notice'),
  ( 17, 'Bateria conectada', 3, 'Notice'),
  ( 18, 'Bateria desconectada', 3, 'Alarm'),
  ( 19, 'Início de carga da bateria', 3, 'Notice'),
  ( 20, 'Fim de carga da bateria', 3, 'Notice'),
  ( 21, 'Sleep mode ativado', 3, 'Notice'),
  ( 22, 'Sleep mode desativado', 3, 'Notice'),
  ( 23, 'Aceleração brusca', 4, 'Notice'),
  ( 24, 'Frenagem brusca', 4, 'Notice'),
  ( 25, 'Curva acentuada', 4, 'Notice'),
  ( 26, 'Curva acentuada à direita', 4, 'Notice'),
  ( 27, 'Curva acentuada à esquerda', 4, 'Notice'),
  ( 28, 'Calibração DPA (Análise de motorista)', 4, 'Notice'),
  ( 29, 'Calibração odômetro', 4, 'Notice'),
  ( 30, 'Calibração RPM', 4, 'Notice'),
  ( 31, 'Alta rotação', 4, 'Notice'),
  ( 32, 'Pressão do óleo do motor excedida', 4, 'Notice'),
  ( 33, 'RPM excedido', 4, 'Notice'),
  ( 34, 'Parado', 4, 'Notice'),
  ( 35, 'Baixa velocidade', 4, 'Notice'),
  ( 36, 'Ultrapassou o limite de velocidade', 4, 'Notice'),
  ( 37, 'Ultrapassou o limite de velocidade com chuva', 4, 'Notice'),
  ( 38, 'Ultrapassou o limite de velocidade sem chuva', 4, 'Notice'),
  ( 39, 'Voltou ao limite de velocidade', 4, 'Notice'),
  ( 40, 'Voltou ao limite de velocidade com chuva', 4, 'Notice'),
  ( 41, 'Voltou ao limite de velocidade sem chuva', 4, 'Notice'),
  ( 42, 'Choque/Colisão', 4, 'Alarm'),
  ( 43, 'Ponto morto', 4, 'Notice'),
  ( 44, 'Entrou na cerca virtual', 5, 'Notice'),
  ( 45, 'Saiu da cerca virtual', 5, 'Alarm'),
  ( 46, 'Entrou no check-point', 5, 'Notice'),
  ( 47, 'Saiu do check-point', 5, 'Notice'),
  ( 48, 'Dentro da área de controle - Antecipado', 5, 'Notice'),
  ( 49, 'Dentro da área de controle - No prazo previsto', 5, 'Notice'),
  ( 50, 'Dentro da área de controle - Atrasado', 5, 'Notice'),
  ( 51, 'Fora da rota', 5, 'Alarm'),
  ( 52, 'Em movimento com ignição desligada', 5, 'Alarm'),
  ( 53, 'Ultrapassou o limite de velocidade na cerca', 5, 'Notice'),
  ( 54, 'Voltou ao limite de velocidade na cerca', 5, 'Notice'),
  ( 55, 'Entrada em zona morta de GPS', 5, 'Notice'),
  ( 56, 'Saída de zona morta de GPS', 5, 'Notice'),
  ( 57, 'Simcard trocado', 2, 'Notice'),
  ( 58, 'Equipamento violado', 7, 'Alarm'),
  ( 59, 'Alarme sonoro', 7, 'Notice'),
  ( 60, 'Pseudo estação base', 2, 'Notice'),
  ( 61, 'Desligamento do equipamento', 1, 'Alarm'),
  ( 62, 'Sleep mode', 3, 'Notice'),
  ( 63, 'Aviso de queda', 4, 'Notice'),
  ( 64, 'Risco de colisão', 13, 'Notice'),
  ( 65, 'Falha no GPS', 2, 'Alarm'),
  ( 66, 'Sensor de temperatura', 10, 'Notice'),
  ( 67, 'Frenagem de emergência', 4, 'Notice'),
  ( 68, 'Sensor de luz', 7, 'Notice'),
  ( 69, 'Anti-furto', 5, 'Alarm'),
  ( 70, 'Estacionamento', 5, 'Notice'),
  ( 71, 'Acidente', 13, 'Alarm'),
  ( 72, 'Bloqueador detectado (Jamming)', 8, 'Alarm'),
  ( 73, 'Entrada em modo de hibernação', 3, 'Notice'),
  ( 74, 'Saída do modo de hibernação', 3, 'Notice'),
  ( 75, 'Erro na bateria interna', 3, 'Alarm'),
  ( 76, 'Desvio da rota pré-definida', 5, 'Alarm'),
  ( 77, 'Entrada na rota pré-definida', 5, 'Notice'),
  ( 78, 'Identificação do motorista inserida', 6, 'Alarm'),
  ( 79, 'Identificação do motorista removida', 6, 'Alarm'),
  ( 80, 'Identificação do motorista falhou', 6, 'Alarm'),
  ( 81, 'Parado por mais tempo que o pré-definido', 4, 'Alarm'),
  ( 82, 'Porta do motorista aberta', 1, 'Alarm'),
  ( 83, 'Porta do motorista fechada', 1, 'Alarm'),
  ( 84, 'Porta do passageiro aberta', 1, 'Alarm'),
  ( 85, 'Porta do passageiro fechada', 1, 'Alarm'),
  ( 86, 'Engate do reboque', 1, 'Alarm'),
  ( 87, 'Desengate do reboque', 1, 'Alarm'),
  ( 88, 'Porta do baú aberta', 1, 'Alarm'),
  ( 89, 'Porta do baú fechada', 1, 'Alarm'),
  ( 90, 'Porta lateral aberta', 1, 'Alarm'),
  ( 91, 'Porta lateral fechada', 1, 'Alarm'),
  ( 92, 'Trava do quinto roda aberta', 1, 'Alarm'),
  ( 93, 'Trava do quinto roda fechada', 1, 'Alarm'),
  ( 94, 'Portas da cabine abertas', 1, 'Alarm'),
  ( 95, 'Portas da cabine fechadas', 1, 'Alarm'),
  ( 96, 'Equipamento reiniciado', 1, 'Alarm'),
  ( 97, 'Alvo executado', 6, 'Alarm'),
  ( 98, 'Bloqueio por anti-furto', 1, 'Alarm'),
  ( 99, 'Desbloqueio por anti-furto', 1, 'Alarm'),
  (100, 'Bloqueio por manobrista', 1, 'Alarm'),
  (101, 'Violação do manobrista', 1, 'Alarm'),
  (102, 'Operadora de telefonia móvel alterada', 1, 'Alarm'),
  (103, 'Sirene ligada por 1 minuto', 1, 'Alarm'),
  (104, 'Bloqueado pela central', 1, 'Alarm'),
  (105, 'Desbloqueado pela central', 1, 'Alarm'),
  (106, 'Bloqueado de maneira silenciosa pela central', 1, 'Alarm'),
  (107, 'Atingiu o limite de velocidade máxima com chuva', 4, 'Alarm'),
  (108, 'Atingiu o limite de velocidade máxima sem chuva', 4, 'Alarm'),
  (109, 'Sirene ligada', 1, 'Alarm'),
  (110, 'Sirene desligada', 1, 'Alarm'),
  (111, 'Identificador de motorista não cadastrado', 6, 'Alarm'),
  (112, 'Encaixado na base magnética', 4, 'Alarm'),
  (113, 'Removido da base magnética', 4, 'Alarm'),
  (114, 'Em movimento', 4, 'Alarm');

ALTER SEQUENCE trackerevents_trackereventid_seq RESTART WITH 115;

-- ---------------------------------------------------------------------
-- Tipos de ações a serem realizadas em um evento de rastreador
-- ---------------------------------------------------------------------
-- Discard: descartar evento
-- Send: enviar evento ao destinatário
-- Silent: registrar o evento silenciosamente
-- ---------------------------------------------------------------------
CREATE TYPE EventAction AS ENUM('Discard', 'Send', 'Silent');

-- ---------------------------------------------------------------------
-- Os possíveis tratadores de eventos
-- ---------------------------------------------------------------------
-- A tabela que armazena os possíveis tratadores de eventos recebidos de
-- rastreadores ao longo do tempo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS treaters (
  treaterID     serial,         -- ID do tratador
  name          varchar(50)     -- Nome do tratador de eventos
                NOT NULL,
  defaultAction EventAction     -- A ação padrão a ser aplicada aos
                NOT NULL        -- eventos deste tratador
                DEFAULT 'Discard',
  PRIMARY KEY (treaterID),
  UNIQUE (name)
);

-- Insere a relação de tratadores possíveis e as ações padrão
INSERT INTO treaters (treaterID, name, defaultAction) VALUES
  (1, 'Provedor', 'Discard'),
  (2, 'Callcenter', 'Send'),
  (3, 'Cliente', 'Send'),
  (4, 'Gerenciadora de risco', 'Discard'),
  (5, 'Aplicativo', 'Discard');

ALTER SEQUENCE treaters_treaterid_seq RESTART WITH 6;

-- ---------------------------------------------------------------------
-- As configurações do padrão de ações por evento para cada tratador
-- ---------------------------------------------------------------------
-- A tabela que armazena quais as ações devem ser adotadas à cada evento
-- gerado para cada possível tratador. Estas regras são definidas por
-- contratante, e são válidas exceto se definida uma regra específica
-- que altere o comportamento. Podemos ter inúmeros tratadores
-- (ex: vários cliente), então cada um deles poderá definir regras mais
-- especificas, modificando o comportamento do sistema. Todavia, se ele
-- não o fizer, prevalecerão as regras aqui definidas e/ou, em última
-- instância, a ação padrão a ser adotada (defaultAction) do tratador.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS treatmentRules (
  treatmentRuleID   serial,      -- ID da regra de tratamento
  contractorID      integer      -- O ID do contratante
                    NOT NULL,
  treaterID         integer      -- O ID do tratador de eventos
                    NOT NULL,
  trackerEventID    integer      -- O ID do evento de rastreador
                    NOT NULL,
  action            EventAction  -- A ação a ser aplicada aos eventos
                    NOT NULL     -- deste tratador
                    DEFAULT 'Discard',
  typeOfEvent       EventType    -- A classificação do evento (se alarme
                    NOT NULL     -- ou alerta)
                    DEFAULT 'Notice',
  PRIMARY KEY (treatmentRuleID),
  UNIQUE (contractorID, treaterID, trackerEventID),
  FOREIGN KEY (trackerEventID)
    REFERENCES trackerEvents(trackerEventID)
    ON DELETE CASCADE
);

INSERT INTO treatmentRules (contractorID, treaterID, trackerEventID,
  action, typeOfEvent) VALUES
  (1, 5,  1, 'Send', 'Alarm'),     -- Falha
  (1, 4,  2, 'Send', 'Alarm'),     -- Botão de pânico
  (1, 5,  2, 'Send', 'Alarm'),     -- Botão de pânico
  (1, 5,  3, 'Send', 'Alarm'),     -- Ignição ligada
  (1, 5,  4, 'Send', 'Notice'),    -- Ignição desligada
  (1, 5,  7, 'Send', 'Alarm'),     -- Antena GPS desconectada
  (1, 5,  9, 'Send', 'Alarm'),     -- Antena GPS em curto
  (1, 5, 10, 'Send', 'Alarm'),     -- Simcard removido
  (1, 5, 16, 'Send', 'Notice'),    -- Bateria baixa
  (1, 4, 18, 'Send', 'Alarm'),     -- Bateria desconectada
  (1, 5, 18, 'Send', 'Alarm'),     -- Bateria desconectada
  (1, 5, 36, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade
  (1, 5, 37, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade com chuva
  (1, 5, 38, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade sem chuva
  (1, 5, 44, 'Send', 'Notice'),    -- Entrou na cerca virtual
  (1, 5, 45, 'Send', 'Alarm'),     -- Saiu da cerca virtual
  (1, 5, 51, 'Send', 'Alarm'),     -- Fora da rota
  (1, 5, 52, 'Send', 'Alarm'),     -- Em movimento com ignição desligada
  (1, 5, 53, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade na cerca
  (1, 2, 73, 'Silent', 'Notice'),  -- Entrada em modo de hibernação (Callcenter)
  (1, 2, 74, 'Silent', 'Notice'),  -- Saída do modo de hibernação (Callcenter)
  (1, 3, 73, 'Silent', 'Notice'),  -- Entrada em modo de hibernação (Cliente)
  (1, 3, 74, 'Silent', 'Notice');  -- Saída do modo de hibernação (Cliente)

INSERT INTO treatmentRules (contractorID, treaterID, trackerEventID,
  action, typeOfEvent) VALUES
  (7, 5,  1, 'Send', 'Alarm'),   -- Falha
  (7, 4,  2, 'Send', 'Alarm'),   -- Botão de pânico
  (7, 5,  2, 'Send', 'Alarm'),   -- Botão de pânico
  (7, 5,  3, 'Send', 'Alarm'),   -- Ignição ligada
  (7, 5,  7, 'Send', 'Alarm'),   -- Antena GPS desconectada
  (7, 5,  9, 'Send', 'Alarm'),   -- Antena GPS em curto
  (7, 5, 10, 'Send', 'Alarm'),   -- Simcard removido
  (7, 5, 16, 'Send', 'Notice'),  -- Bateria baixa
  (7, 4, 18, 'Send', 'Alarm'),   -- Bateria desconectada
  (7, 5, 18, 'Send', 'Alarm'),   -- Bateria desconectada
  (7, 5, 36, 'Send', 'Notice'),  -- Ultrapassou o limite de velocidade
  (7, 5, 37, 'Send', 'Notice'),  -- Ultrapassou o limite de velocidade com chuva
  (7, 5, 38, 'Send', 'Notice'),  -- Ultrapassou o limite de velocidade sem chuva
  (7, 5, 44, 'Send', 'Notice'),  -- Entrou na cerca virtual
  (7, 5, 45, 'Send', 'Alarm'),   -- Saiu da cerca virtual
  (7, 5, 51, 'Send', 'Alarm'),   -- Fora da rota
  (7, 5, 52, 'Send', 'Alarm'),   -- Em movimento com ignição desligada
  (7, 5, 53, 'Send', 'Notice'),  -- Ultrapassou o limite de velocidade na cerca
  (7, 2, 73, 'Silent', 'Notice'),  -- Entrada em modo de hibernação (Callcenter)
  (7, 2, 74, 'Silent', 'Notice'),  -- Saída do modo de hibernação (Callcenter)
  (7, 3, 73, 'Silent', 'Notice'),  -- Entrada em modo de hibernação (Cliente)
  (7, 3, 74, 'Silent', 'Notice');  -- Saída do modo de hibernação (Cliente)

INSERT INTO treatmentRules (contractorID, treaterID, trackerEventID,
  action, typeOfEvent) VALUES
  (1813, 5,  1, 'Send', 'Alarm'),     -- Falha
  (1813, 4,  2, 'Send', 'Alarm'),     -- Botão de pânico
  (1813, 5,  2, 'Send', 'Alarm'),     -- Botão de pânico
  (1813, 5,  3, 'Send', 'Alarm'),     -- Ignição ligada
  (1813, 5,  7, 'Send', 'Alarm'),     -- Antena GPS desconectada
  (1813, 5,  9, 'Send', 'Alarm'),     -- Antena GPS em curto
  (1813, 5, 10, 'Send', 'Alarm'),     -- Simcard removido
  (1813, 5, 16, 'Send', 'Notice'),    -- Bateria baixa
  (1813, 4, 18, 'Send', 'Alarm'),     -- Bateria desconectada
  (1813, 5, 18, 'Send', 'Alarm'),     -- Bateria desconectada
  (1813, 5, 36, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade
  (1813, 5, 37, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade com chuva
  (1813, 5, 38, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade sem chuva
  (1813, 5, 44, 'Send', 'Notice'),    -- Entrou na cerca virtual
  (1813, 5, 45, 'Send', 'Alarm'),     -- Saiu da cerca virtual
  (1813, 5, 51, 'Send', 'Alarm'),     -- Fora da rota
  (1813, 5, 52, 'Send', 'Alarm'),     -- Em movimento com ignição desligada
  (1813, 5, 53, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade na cerca
  (1813, 2, 73, 'Silent', 'Notice'),  -- Entrada em modo de hibernação (Callcenter)
  (1813, 2, 74, 'Silent', 'Notice'),  -- Saída do modo de hibernação (Callcenter)
  (1813, 3, 73, 'Silent', 'Notice'),  -- Entrada em modo de hibernação (Cliente)
  (1813, 3, 74, 'Silent', 'Notice');  -- Saída do modo de hibernação (Cliente)

INSERT INTO treatmentRules (contractorID, treaterID, trackerEventID,
  action, typeOfEvent) VALUES
  (2530, 5,  1, 'Send', 'Alarm'),     -- Falha
  (2530, 4,  2, 'Send', 'Alarm'),     -- Botão de pânico
  (2530, 5,  2, 'Send', 'Alarm'),     -- Botão de pânico
  (2530, 5,  3, 'Send', 'Alarm'),     -- Ignição ligada
  (2530, 5,  4, 'Send', 'Notice'),    -- Ignição desligada
  (2530, 5,  7, 'Send', 'Alarm'),     -- Antena GPS desconectada
  (2530, 5,  9, 'Send', 'Alarm'),     -- Antena GPS em curto
  (2530, 5, 10, 'Send', 'Alarm'),     -- Simcard removido
  (2530, 5, 16, 'Send', 'Notice'),    -- Bateria baixa
  (2530, 4, 18, 'Send', 'Alarm'),     -- Bateria desconectada
  (2530, 5, 18, 'Send', 'Alarm'),     -- Bateria desconectada
  (2530, 5, 36, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade
  (2530, 5, 37, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade com chuva
  (2530, 5, 38, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade sem chuva
  (2530, 5, 44, 'Send', 'Notice'),    -- Entrou na cerca virtual
  (2530, 5, 45, 'Send', 'Alarm'),     -- Saiu da cerca virtual
  (2530, 5, 51, 'Send', 'Alarm'),     -- Fora da rota
  (2530, 5, 52, 'Send', 'Alarm'),     -- Em movimento com ignição desligada
  (2530, 5, 53, 'Send', 'Notice'),    -- Ultrapassou o limite de velocidade na cerca
  (2530, 2, 73, 'Silent', 'Notice'),  -- Entrada em modo de hibernação (Callcenter)
  (2530, 2, 74, 'Silent', 'Notice'),  -- Saída do modo de hibernação (Callcenter)
  (2530, 3, 73, 'Silent', 'Notice'),  -- Entrada em modo de hibernação (Cliente)
  (2530, 3, 74, 'Silent', 'Notice');  -- Saída do modo de hibernação (Cliente)

-- ---------------------------------------------------------------------
-- As configurações da ação por evento para um tratador específico
-- ---------------------------------------------------------------------
-- A tabela que armazena qual a ação deve ser adotada à um evento gerado
-- para um tratador específico. Estas regras são definidas por cliente
-- de um contratante e sobrescreve às regras anteriores.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customerRules (
  customerID        integer      -- O ID do cliente
                    NOT NULL,
  PRIMARY KEY (treatmentRuleID),
  UNIQUE (contractorID, treaterID, customerID, trackerEventID)
) INHERITS (treatmentRules);

-- ---------------------------------------------------------------------
-- Obtém quais os tratadores para um evento de um rastreador, bem como
-- a ação a ser aplicada.
-- ---------------------------------------------------------------------
-- Stored Procedure que recupera os tratadores para um evento em função
-- das regras, bem como a ação a ser aplicada à cada tratador.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION getTreatmentRules(FequipmentID integer,
  FtrackerEventID integer)
RETURNS jsonb AS
$$
DECLARE
  FcontractorID  integer;
  FcustomerID  integer;
  FenableAtMonitoring  boolean;
  treatmentActions  jsonb;
BEGIN
  SELECT INTO FcontractorID, FcustomerID, FenableAtMonitoring
         E.contractorID,
         V.customerID,
         C.enableAtMonitoring
    FROM erp.equipments AS E
    LEFT JOIN erp.vehicles AS V USING (vehicleID)
    LEFT JOIN erp.entities AS C ON (E.customerPayerID = C.entityID)
   WHERE E.equipmentID = FequipmentID;

  IF (FcustomerID IS NULL) THEN
    -- Obtemos para cada tratador qual a ação a ser realizada e como o
    -- evento deve ser tratado (se alarme ou alerta), porém ignorando as
    -- condições específicas de tratamento pelo cliente
    SELECT INTO treatmentActions
           jsonb_object_agg(treaterID, json_build_object('action', action, 'typeOfEvent', typeOfEvent))
      FROM (
        SELECT DISTINCT ON (T.treaterID) T.treaterID,
               CASE
                 WHEN R.action IS NOT NULL THEN R.action
                 ELSE T.defaultAction
               END AS action,
               CASE
                 WHEN R.typeOfEvent IS NOT NULL THEN R.typeOfEvent
                 ELSE (SELECT defaultEventType FROM trackerEvents WHERE trackerEventID = FtrackerEventID)
               END AS typeOfEvent
          FROM public.treaters AS T
          LEFT JOIN public.treatmentRules AS R ON (T.treaterID = R.treaterID AND R.contractorID = FcontractorID AND R.trackerEventID = FtrackerEventID)
         ORDER BY treaterID
      ) AS treatmentActions;

    -- Forçamos que tanto os eventos para o cliente e a gerenciadora de
    -- risco sejam descartados, pois eles ainda não existem
    treatmentActions := jsonb_set(
      treatmentActions,
      '{3,action}', '"Discard"'
    );
    treatmentActions := jsonb_set(
      treatmentActions,
      '{4,action}', '"Discard"'
    );
  ELSE
    -- Obtemos para cada tratador qual a ação a ser realizada e como o
    -- evento deve ser tratado (se alarme ou alerta)
    SELECT INTO treatmentActions
           jsonb_object_agg(treaterID, json_build_object('action', action, 'typeOfEvent', typeOfEvent))
      FROM (
        SELECT DISTINCT ON (T.treaterID) T.treaterID,
               CASE
                 WHEN C.action IS NOT NULL THEN C.action
                 WHEN R.action IS NOT NULL THEN R.action
                 ELSE T.defaultAction
               END AS action,
               CASE
                 WHEN C.typeOfEvent IS NOT NULL THEN C.typeOfEvent
                 WHEN R.typeOfEvent IS NOT NULL THEN R.typeOfEvent
                 ELSE (SELECT defaultEventType FROM trackerEvents WHERE trackerEventID = FtrackerEventID)
               END AS typeOfEvent,
               C.customerID
          FROM public.treaters AS T
          LEFT JOIN public.treatmentRules AS R ON (T.treaterID = R.treaterID AND R.contractorID = FcontractorID AND R.trackerEventID = FtrackerEventID)
          LEFT JOIN public.customerRules AS C ON (T.treaterID = C.treaterID AND C.contractorID = FcontractorID AND C.trackerEventID = FtrackerEventID AND C.customerID = FcustomerID)
         ORDER BY treaterID, C.customerID NULLS FIRST
      ) AS treatmentActions;

      -- Verificamos se o equipamento está espelhado para o tratador 4
    IF (FenableAtMonitoring IS NOT TRUE) THEN
      treatmentActions := jsonb_set(
        treatmentActions,
        '{4,action}', '"Discard"'
      );
    END IF;
  END IF;

  -- RAISE NOTICE 'treaters %', treaterAction;
  -- RAISE NOTICE 'secound treater %', treaterAction[1];
  -- RAISE NOTICE 'and this action is %', TreaterAction[1]->'action';

  -- Eliminamos todos os tratadores cuja ação seja descartar e
  -- acrescentamos a informação de tratamento, sendo que já trata todos
  -- àqueles que sejam apenas para registrar (Silent)
  SELECT INTO treatmentActions
         jsonb_object_agg(
           key,
           json_build_object(
             'action', value->>'action',
             'typeOfEvent', value->>'typeOfEvent',
             'treated', CASE WHEN value->>'action' = 'Silent' THEN true ELSE NULL END,
             'updatedAt', to_char(CURRENT_TIMESTAMP,'YYYY-MM-DD HH24:MI:SS'),
             'comments', '[]'
           )
         )
    FROM jsonb_each(treatmentActions)
   WHERE value->>'action' <> 'Discard';

  RETURN treatmentActions;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Os bloqueadores de notificações de eventos
-- ---------------------------------------------------------------------
-- A tabela que armazena os bloqueadores de notificações de eventos a
-- serem enviadas aos clientes através do serviço de push.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notificationBlockers (
  notificationBlockerID serial,      -- ID do bloqueador de notificação
  name                  varchar(50)  -- Nome do bloqueio sendo realizado
                        NOT NULL,
  aliasName             varchar(10)  -- Nome abreviado do bloqueio
                        NOT NULL,
  PRIMARY KEY (notificationBlockerID),
  UNIQUE (name)
);

-- Insere os bloqueadores de eventos
INSERT INTO notificationBlockers (notificationBlockerID, name) VALUES
  ( 1, 'Ignição ligada e desligada', 'ignition'),
  ( 2, 'Bateria conectada e desconectada', 'battery');

ALTER SEQUENCE notificationblockers_notificationblockerid_seq RESTART WITH 2;

-- ---------------------------------------------------------------------
-- Os eventos afetados por bloqueador
-- ---------------------------------------------------------------------
-- A tabela que armazena quais eventos os bloqueadores de notificação
-- irão atual.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notificationBlockerEvents (
  notificationBlockerEventID serial,      -- ID do evento bloqueado
  notificationBlockerID      integer      -- O ID do bloqueador de
                             NOT NULL,    -- eventos
  trackerEventID             integer      -- O ID do evento de rastreador
                             NOT NULL,    -- que deve ser bloqueado
  PRIMARY KEY (notificationBlockerEventID),
  UNIQUE (notificationBlockerID, trackerEventID),
  FOREIGN KEY (notificationBlockerID)
    REFERENCES notificationBlockers(notificationBlockerID)
    ON DELETE CASCADE,
  FOREIGN KEY (trackerEventID)
    REFERENCES trackerEvents(trackerEventID)
    ON DELETE CASCADE
);

-- Insere os eventos bloqueados por cada bloqueador de eventos
INSERT INTO notificationBlockerEvents (notificationBlockerID, trackerEventID) VALUES
  (1, 3), -- Ignição ligada
  (1, 4), -- Ignição desligada
  (2, 17), -- Bateria conectada
  (2, 18); -- Bateria desconectada

-- ---------------------------------------------------------------------
-- Os bloqueadores de notificações de eventos por token definidos
-- ---------------------------------------------------------------------
-- A tabela que armazena quais os bloqueadores de notificações de
-- eventos estão ativos para um token de notificação através do serviço
-- de push. Sempre que ativo, o bloqueador impede que notificações dos
-- eventos programados sejam enviados àquele token.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notificationBlockerTokens (
  notificationBlockerID      integer      -- O ID do bloqueador de
                             NOT NULL,    -- eventos
  entityID                   integer,     -- O ID da entidade (cliente)
  deviceTokenID              integer      -- O ID do token de dispositivo
                             NOT NULL,    -- no qual deve ser bloqueado
  equipmentID                integer      -- O ID do equipamento no qual
                             NOT NULL,    -- deve ser bloqueado
  PRIMARY KEY (notificationBlockerID, deviceTokenID),
  UNIQUE (notificationBlockerID, deviceTokenID),
  FOREIGN KEY (notificationBlockerID)
    REFERENCES notificationBlockers(notificationBlockerID)
    ON DELETE CASCADE,
  FOREIGN KEY (deviceTokenID)
    REFERENCES deviceTokens(deviceTokenID)
    ON DELETE CASCADE,
  FOREIGN KEY (equipmentID)
    REFERENCES erp.equipments(equipmentID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Bloqueadores de notificações de eventos por veículo e token
-- ---------------------------------------------------------------------
-- Obtém uma matriz dos bloqueadores de notificações de eventos para um
-- determinado veículo e para o token de um dispositivo específico.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.getNotificationsBlockersOnDevice(
  FdeviceTokenID integer, FequipmentID integer)
RETURNS jsonb AS $$
  SELECT jsonb_agg(                   
           jsonb_build_object(
             blockers.aliasName, blockers.isBlocked
           )
         )
    FROM (
      SELECT blocker.aliasName,
             (
               SELECT EXISTS (
                 SELECT 1
                   FROM public.notificationBlockerTokens AS tokens
                  WHERE tokens.notificationBlockerID = blocker.notificationBlockerID
                    AND tokens.deviceTokenID = FdeviceTokenID
                    AND tokens.equipmentID = FequipmentID
               )
             ) AS isBlocked
        FROM public.notificationBlockers AS blocker
    ) AS blockers;
$$
LANGUAGE 'sql' IMMUTABLE STRICT;


-- ---------------------------------------------------------------------
-- Propriedade do veículo
-- ---------------------------------------------------------------------
-- Obtém um texto que define a propriedade do veículo
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.getOwnershipOfVehicle(FvehicleID integer)
RETURNS varchar AS $$
  WITH model AS (
    SELECT CASE
      WHEN model.vehicleSubtypeID IS NULL THEN type.article
      ELSE subtype.article
    END AS article,
    CASE
      WHEN model.vehicleSubtypeID IS NULL THEN type.name
      ELSE subtype.name
    END AS name
  FROM erp.vehicles AS vehicle
 INNER JOIN erp.vehicleTypes AS type ON (vehicle.vehicleTypeID = type.vehicleTypeID)
 INNER JOIN erp.vehicleModels AS model ON (vehicle.vehicleModelID = model.vehicleModelID)
  LEFT JOIN erp.vehicleSubtypes AS subtype USING (vehicleSubtypeID)
  WHERE vehicle.vehicleID = FvehicleID)
 SELECT 'n' || article || ' '
          || CASE WHEN model.article = 'o' THEN 'seu' ELSE 'sua' END
          || ' ' || lower(model.name)
   FROM model
$$
LANGUAGE 'sql' IMMUTABLE STRICT;

-- ---------------------------------------------------------------------
-- Nome do evento
-- ---------------------------------------------------------------------
-- Obtém o nome de um evento
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.getTrackerEventName(FtrackerEventID integer)
RETURNS varchar AS $$
  SELECT name
    FROM public.trackerEvents
   WHERE trackerEventID = FtrackerEventID;
$$
LANGUAGE 'sql' IMMUTABLE STRICT;

-- ---------------------------------------------------------------------
-- Mensagem de notificação
-- ---------------------------------------------------------------------
-- Obtém o texto da mensagem de notificação.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.getNotificationMessage (
  FeventDate timestamp, FtypeOfEvent varchar,
  FtrackerEventID integer, FvehicleID integer)
RETURNS varchar AS $$
DECLARE
  dayOfEvent  varchar;
  hourOfEvent  varchar;
  vehicleType  varchar;
  eventType varchar;
  eventName  varchar;
  result  varchar;
BEGIN
  -- Convertemos as datas
  dayOfEvent  := to_char(FeventDate,'DD/MM/YYYY');
  hourOfEvent := to_char(FeventDate,'HH24:MI:SS');
  -- IF FtypeOfEvent = 'Alarm' THEN
  --   eventType := 'alarme';
  -- ELSE
  --   eventType := 'evento';
  -- END IF;
  SELECT getOwnershipOfVehicle(FvehicleID) INTO vehicleType;
  SELECT getTrackerEventName(FtrackerEventID) INTO eventName;

  --result := 'Olá, ocorreu um ' || eventType
  --  || ' de ' || lower(eventName) || ' ' || vehicleType || ' em '
  --  || dayOfEvent || ' às ' || hourOfEvent || '. Verifique!!!';
  result := eventName || ' ' || vehicleType || ' em '
    || dayOfEvent || ' às ' || hourOfEvent;
  
  RETURN result;
END;
$$ LANGUAGE plpgsql IMMUTABLE STRICT;

-- ---------------------------------------------------------------------
-- O registro de eventos
-- ---------------------------------------------------------------------
-- A tabela que armazena os eventos recebidos de rastreadores ao longo
-- do tempo.
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
  eventID                 serial,         -- ID do evento
  positionID              bigint,         -- O ID da posição do evento
  contractorID            integer,        -- O ID do contratante
  equipmentID             integer,        -- ID do equipamento
  terminalID              varchar         -- Número de série do dispositivo
                          NOT NULL,       -- de rastreamento (terminal)
  mainTracker             boolean         -- O indicador do rastreador
                          NOT NULL        -- principal ou reserva
                          DEFAULT true,
  FirmwareVersion         varchar,        -- Versão do firmware
  vehicleID               integer,        -- O ID do veículo se vinculado
  plate                   varchar(7),     -- A placa do veículo
  customerID              integer,        -- O ID do cliente se vinculado
  subsidiaryID            integer,        -- O ID da unidade/filial do cliente
  customerPayerID         integer         -- O ID do pagante se vinculado
                          DEFAULT NULL,   
  subsidiaryPayerID       integer         -- O ID da unidade/filial do
                          DEFAULT NULL,   -- pagante
  eventDate               timestamp       -- A data/hora do evento
                          NOT NULL,
  gpsDate                 timestamp       -- A data/hora do GPS
                          NOT NULL,
  systemDate              timestamp       -- A data/hora do registro no
                          NOT NULL        -- sistema
                          DEFAULT CURRENT_TIMESTAMP,
  latitude                numeric(9,6)    -- A latitude da posição
                          NOT NULL,
  longitude               numeric(9,6)    -- A longitude da posição
                          NOT NULL,
  withGPS                 boolean         -- O indicativo de que o
                          NOT NULL        -- equipamento está com GPS
                          DEFAULT FALSE,
  realTime                boolean         -- O indicativo de que o
                          NOT NULL        -- equipamento está em tempo
                          DEFAULT TRUE,   -- real
  address                 varchar,        -- O endereço relativo à posição geográfica
  satellites              integer,        -- A quantidade de satélites em uso
  mcc                     char(3),        -- O código do país
  mnc                     char(3),        -- O código da operadora
  course                  integer,        -- A direção atual (em graus)
  ignitionStatus          boolean,        -- O estado da ignição
  blockStatus             boolean,        -- O estado do bloqueio
  sirenStatus             boolean,        -- O estado da sirene
  emergencyStatus         boolean,        -- O estado do modo emergência
  speed                   integer,        -- A velocidade do veículo
  odometer                integer,        -- O valor do odômetro
  horimeter               integer,        -- O valor do horímetro
  rpm                     integer,        -- O valor de rotação do motor
  powerVoltage            numeric(4,2),   -- O valor de tensão da bateria principal
  charge                  boolean,        -- O indicativo de que a bateria interna está carregando
  batteryVoltage          numeric(4,2),   -- O valor de tensão da bateria interna
  gsmSignalStrength       integer,        -- O nível do sinal GSM
  inputs                  boolean[]       -- O estado das entradas
                          DEFAULT '{}',
  outputs                 boolean[]       -- O estado das saídas
                          DEFAULT '{}',
  driverIdentifierID      integer         -- O ID do identificador do
                          DEFAULT NULL,   -- motorista
  identifier              varchar(50)     -- O número do identificador
                          DEFAULT NULL,   -- do motorista
  driverID                integer,        -- ID do motorista
  driverRegistered        boolean,        -- O indicativo de que o motorista está registrado
  trackerEventID          integer         -- O evento ocorrido
                          NOT NULL,
  isReal                  boolean         -- A identificação de que é um
                          DEFAULT FALSE,  -- evento real
  treatmentActions        jsonb           -- As ações de tratamento
                          NOT NULL,       -- dadas ao evento
  port                    integer,        -- A porta de comunicação pela qual o evento foi recebido
  protocolID              integer,        -- O ID do protocolo de comunicação
  PRIMARY KEY (eventID),
  FOREIGN KEY (trackerEventID)
    REFERENCES trackerEvents(trackerEventID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Gatilho para processar inserções na tabela de eventos
-- ---------------------------------------------------------------------
-- Cria um gatilho que lida com as inserções de registros na tabela de
-- eventos, criando as partições se necessário.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION eventTransaction()
RETURNS trigger AS
$BODY$
  DECLARE
    yearOfEventDate  char(4);
    monthOfEventDate  char(2);
    startOfMonth date;
    endOfMonth date;
    partition  varchar;
    newEventID  integer;
    titleOfMessage  text;
    typeOfEvent  varchar;
    messageContent  text;
    pushSrv  record;
    treatmentRecord  record;
  BEGIN
    -- Faz a criação de uma nova partição, se necessário, nos processos
    -- em que se insere os dados de eventos obtidos. Faz uso da variável
    -- especial TG_OP para verificar a operação executada.
    IF (TG_OP = 'INSERT') THEN
      IF (TG_WHEN = 'BEFORE') THEN
        BEGIN
          yearOfEventDate := extract(YEAR FROM NEW.eventDate);
          monthOfEventDate := LPAD(extract(MONTH FROM NEW.eventDate)::varchar, 2, '0');
          partition := TG_RELNAME || '_' || yearOfEventDate || monthOfEventDate;
          startOfMonth := to_char(NEW.eventDate, 'YYYY-MM-01');
          endOfMonth := (date_trunc('MONTH', NEW.eventDate) + INTERVAL '1 MONTH - 1 second')::date;
          
          -- Verifica se a tabela existe
          IF NOT EXISTS(SELECT T.relname, N.nspname FROM pg_catalog.pg_class AS T JOIN pg_catalog.pg_namespace AS N ON T.relnamespace = N.oid WHERE T.relname = partition AND N.nspname = 'public') THEN
            -- RAISE NOTICE 'A partição %/% da tabela de % está sendo criada', monthOfEventDate, yearOfEventDate, TG_RELNAME;
            EXECUTE 'CREATE TABLE public.' || partition || ' ( CHECK ( eventDate::date >= DATE ''' || startOfMonth || '''  AND eventDate::date <=  DATE ''' ||  endOfMonth || ''' )) INHERITS (public.' || TG_RELNAME || ');';
            EXECUTE 'CREATE INDEX ' || partition || '_byevent ON public.'  || partition || '(eventDate)';
            EXECUTE 'CREATE INDEX ' || partition || '_byequipment ON public.'  || partition || '(equipmentID, eventDate)';
            EXECUTE 'CREATE INDEX ' || partition || '_byposition ON public.'  || partition || '(positionID)';

            EXECUTE 'ALTER TABLE public.' || partition || ' ADD primary key(eventID);';
            -- EXECUTE 'ALTER TABLE ' || partition || ' ADD CONSTRAINT ' || partition || '_unique UNIQUE (terminalID, eventDate, latitude, longitude);';
          END IF;

          -- Inserimos o registro
          EXECUTE 'INSERT INTO public.' || partition || ' SELECT(public.' || TG_RELNAME || ' ' || quote_literal(NEW) || ').* RETURNING eventID;'
            INTO newEventID;

          -- Se o veículo é conhecido e a data/hora do evento é recente
          -- então analizamos os envios de notificação ao cliente
          IF NEW.vehicleID IS NOT NULL AND NEW.eventDate >= NOW() - interval '15 minutes' THEN
            -- Obtemos as informações da notificação
            titleOfMessage := NEW.plate;

            -- Se existir notificações a serem enviadas ao celular do,
            -- cliente então insere
            IF NEW.treatmentactions->>'5' IS NOT NULL
              AND jsonb_extract_path_text(NEW.treatmentactions, '5', 'action') = 'Send' THEN
              RAISE NOTICE 'Inserindo nova notificação para o cliente';
              -- Obtemos o tipo do evento
              typeOfEvent := NEW.treatmentActions#>>'{5,typeOfEvent}';
              messageContent := public.getNotificationMessage(
                NEW.eventDate,
                typeOfEvent,
                NEW.trackerEventID,
                NEW.vehicleID)
              ;

              FOR pushSrv IN
                WITH SetOfEntities AS (
                  SELECT NEW.customerID AS entityID
                   UNION
                  SELECT DISTINCT U.entityID
                    FROM erp.authorizedequipments AS A
                   INNER JOIN erp.users AS U USING (userID)
                   INNER JOIN erp.vehicles AS V USING (vehicleID)
                   WHERE U.blocked = FALSE
                     AND V.customerID = NEW.customerID
                     AND A.equipmentID = NEW.equipmentID
                ),
                EnabledEntities AS (
                  SELECT entityID
                    FROM SetOfEntities
                   INNER JOIN erp.entities AS E USING (entityID)
                   WHERE E.blocked = FALSE
                     AND (
                           SELECT COUNT(*)
                             FROM erp.users AS U
                            WHERE U.entityID = E.entityID
                              AND blocked = FALSE
                         ) > 0
                     AND NOT EXISTS (
                           SELECT 1
                             FROM erp.affiliateBlocking AS B
                            WHERE B.associationID = NEW.customerPayerID
                              AND B.customerID = NEW.customerID
                              AND B.unblockedAt IS NULL
                         )
                ),
                UsersToRemove AS (
                  SELECT U.userID
                    FROM erp.users AS U
                   INNER JOIN EnabledEntities AS EE ON U.entityID = EE.entityID
                   WHERE U.seeallvehicles = FALSE
                     AND NOT EXISTS (
                           SELECT 1
                             FROM erp.visibleVehicles AS VV
                            WHERE VV.userID = U.userID
                              AND VV.vehicleID = NEW.vehicleID
                         )
                )
                SELECT pushservice AS name,
                       platform,
                       ARRAY_AGG(token) AS tokens
                  FROM public.deviceTokens
                 INNER JOIN EnabledEntities AS ValidEntity USING (entityID)
                  LEFT JOIN public.notificationBlockerTokens AS blocker ON (deviceTokens.deviceTokenID = blocker.deviceTokenID AND blocker.equipmentID = NEW.equipmentID)
                  LEFT JOIN public.notificationBlockerEvents AS eventBlocker ON (blocker.notificationBlockerID = eventBlocker.notificationBlockerID AND eventBlocker.trackerEventID = NEW.trackerEventID)
                 WHERE deviceTokens.contractorID = NEW.contractorID
                   AND deviceTokens.broken = FALSE
                   AND blocker.deviceTokenID IS NULL
                   AND deviceTokens.userID NOT IN (SELECT userID FROM UsersToRemove)
                 GROUP BY pushservice, platform
              LOOP
                -- Para cada serviço de push, insere uma notificação
                INSERT INTO public.notificationsQueue
                  (contractorID, pushService, tokens, title, message,
                  platform, channel) VALUES (NEW.contractorID,
                  pushSrv.name, pushSrv.tokens, titleOfMessage,
                  messageContent, pushSrv.platform, typeOfEvent);
              END LOOP;
            END IF;

            -- Se existir notificações a serem enviadas ao provedor,
            -- então insere. Aqui está temporário pois fiz para permitir
            -- o envio de mensagens na fase de testes
            IF NEW.treatmentactions->>'2' IS NOT NULL
              AND jsonb_extract_path_text(NEW.treatmentactions, '2', 'action') = 'Send' THEN
              RAISE NOTICE 'Inserindo nova notificação para provedor';
              RAISE NOTICE 'Contractor contém %', NEW.contractorID;
              -- Obtemos o tipo do evento
              typeOfEvent := NEW.treatmentActions#>>'{2,typeOfEvent}';
              messageContent := public.getNotificationMessage(
                NEW.eventDate,
                typeOfEvent,
                NEW.trackerEventID,
                NEW.vehicleID)
              ;

              FOR pushSrv IN 
                SELECT pushservice AS name,
                       platform,
                       ARRAY_AGG(token) AS tokens
                  FROM public.deviceTokens
                  LEFT JOIN public.notificationBlockerTokens AS blocker ON (deviceTokens.deviceTokenID = blocker.deviceTokenID AND blocker.equipmentID = NEW.equipmentID)
                  LEFT JOIN public.notificationBlockerEvents AS eventBlocker ON (blocker.notificationBlockerID = eventBlocker.notificationBlockerID AND eventBlocker.trackerEventID = NEW.trackerEventID)
                 WHERE deviceTokens.entityID = NEW.contractorID
                   AND deviceTokens.broken = FALSE
                   AND blocker.deviceTokenID IS NULL
                   GROUP BY pushservice, platform
              LOOP
                RAISE NOTICE 'Inserindo nova notificação do tipo % com mensagem %', typeOfEvent, titleOfMessage;
                -- Para cada serviço de push, insere uma notificação
                INSERT INTO public.notificationsQueue
                  (contractorID, pushService, tokens, title, message,
                  platform, channel) VALUES (NEW.contractorID,
                  pushSrv.name, pushSrv.tokens, titleOfMessage,
                  messageContent, pushSrv.platform, typeOfEvent);
              END LOOP;
            END IF;
          END IF;

          -- Agora inserimos as ações dos eventos apenas se a data/hora
          -- do evento for recente
          IF NEW.eventDate >= NOW() - interval '2 days' THEN
            -- Inserimos as ações dos eventos
            FOR treatmentRecord IN
              SELECT key,
                      value
                FROM jsonb_each(NEW.treatmentActions)
            LOOP
              INSERT INTO public.eventActions (contractorID, eventID,
                treaterID, customerID, subsidiaryID, customerPayerID,
                subsidiaryPayerID, eventAction, eventDate, typeOfEvent,
                updatedAt) VALUES (NEW.contractorID, newEventID,
                (treatmentRecord.key)::integer,
                NEW.customerID, NEW.subsidiaryID,
                NEW.customerPayerID, NEW.subsidiaryPayerID,
                (treatmentRecord.value->>'action')::EventAction,
                NEW.eventDate,
                (treatmentRecord.value->>'typeOfEvent')::EventType,
                (treatmentRecord.value->>'updatedAt')::timestamp
              );
            END LOOP;
          END IF;
          
          RETURN NULL;
        END;
      END IF;
    END IF;
  END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER eventTransactionTrigger
  BEFORE INSERT ON public.events
  FOR EACH ROW EXECUTE PROCEDURE eventTransaction();

-- ---------------------------------------------------------------------
-- O registro das ações dos eventos
-- ---------------------------------------------------------------------
-- A tabela que armazena as ações dadas a cada evento em função de quem
-- trata, incluindo o tratador, a ação realizada e o tipo de evento
-- considerado.
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS eventActions (
  eventActionID           serial,         -- ID da ação do evento
  contractorID            integer,        -- O ID do contratante
                          NOT NULL,
  eventID                 integer         -- O ID do evento
                          NOT NULL,
  customerID              integer         -- O ID do cliente se vinculado
                          DEFAULT NULL,   
  subsidiaryID            integer         -- O ID da unidade/filial do cliente
                          DEFAULT NULL,   
  customerPayerID         integer         -- O ID do pagante se vinculado
                          DEFAULT NULL,   
  subsidiaryPayerID       integer         -- O ID da unidade/filial do
                          DEFAULT NULL,   -- pagante
  treaterID               integer         -- O ID do tratador do evento
                          NOT NULL,
  eventDate               timestamp       -- A data/hora do evento
                          NOT NULL,
  eventAction             EventAction     -- A ação realizada no evento
                          NOT NULL,
  typeOfEvent             EventType       -- O tipo de evento
                          NOT NULL,
  visualized              boolean         -- O indicativo de que a
                          NOT NULL        -- notificação foi visualizada
                          DEFAULT FALSE,
  visualizedAt            timestamp       -- A data/hora em que o evento
                          DEFAULT NULL,   -- foi visualizado
  treated                 boolean         -- O indicativo de que o
                          NOT NULL        -- evento foi tratado
                          DEFAULT FALSE,
  treatedDueToExpiration  boolean         -- O indicativo de que o
                          NOT NULL        -- tratamento foi realizado
                          DEFAULT FALSE,  -- devido à expiração
  updatedAt               timestamp       -- A data/hora da última
                          NOT NULL        -- atualização
                          DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (eventActionID),
  FOREIGN KEY (eventID)
    REFERENCES events(eventID)
    ON DELETE CASCADE,
  FOREIGN KEY (treaterID)
    REFERENCES treaters(treaterID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Gatilho para processar inserções na tabela de ações dos eventos
-- ---------------------------------------------------------------------
-- Cria um gatilho que lida com as inserções de registros na tabela de
-- ações dos eventos, criando as partições se necessário.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION eventActionTransaction()
RETURNS trigger AS
$BODY$
  DECLARE
    yearOfEventActionDate  char(4);
    monthOfEventActionDate  char(2);
    startOfMonth date;
    endOfMonth date;
    partition  varchar;
  BEGIN
    -- Faz a criação de uma nova partição, se necessário, nos processos
    -- em que se insere os dados de eventos obtidos. Faz uso da variável
    -- especial TG_OP para verificar a operação executada.
    IF (TG_OP = 'INSERT') THEN
      IF (TG_WHEN = 'BEFORE') THEN
        BEGIN
          yearOfEventActionDate := extract(YEAR FROM NEW.eventDate);
          monthOfEventActionDate := LPAD(extract(MONTH FROM NEW.eventDate)::varchar, 2, '0');
          partition := TG_RELNAME || '_' || yearOfEventActionDate || monthOfEventActionDate;
          startOfMonth := to_char(NEW.eventDate, 'YYYY-MM-01');
          endOfMonth := (date_trunc('MONTH', NEW.eventDate) + INTERVAL '1 MONTH - 1 second')::date;
          
          -- Verifica se a tabela existe
          IF NOT EXISTS(SELECT T.relname, N.nspname FROM pg_catalog.pg_class AS T JOIN pg_catalog.pg_namespace AS N ON T.relnamespace = N.oid WHERE T.relname = partition AND N.nspname = 'public') THEN
            -- RAISE NOTICE 'A partição %/% da tabela de % está sendo criada', monthOfEventActionDate, yearOfEventActionDate, TG_RELNAME;
            EXECUTE 'CREATE TABLE public.' || partition || ' ( CHECK ( eventDate::date >= DATE ''' || startOfMonth || '''  AND eventDate::date <=  DATE ''' ||  endOfMonth || ''' )) INHERITS (public.' || TG_RELNAME || ');';
            EXECUTE 'CREATE INDEX ' || partition || '_byevent ON public.'  || partition || '(treaterID, eventDate)';
            EXECUTE 'CREATE INDEX ' || partition || '_bytreater ON public.'  || partition || '(contractorID, treaterID, treated, updatedAt)';
            EXECUTE 'CREATE INDEX ' || partition || '_bytreater_and_eventdate ON public.'  || partition || '(contractorID, treaterID, eventAction, eventDate DESC)';
            EXECUTE 'CREATE INDEX ' || partition || '_bytreater_and_eventdate_and_customer ON public.'  || partition || '(contractorID, treaterID, customerID, eventAction, eventDate DESC)';
            EXECUTE 'CREATE INDEX ' || partition || '_bytreater_and_eventdate_and_customerpayer ON public.'  || partition || '(contractorID, treaterID, customerPayerID, eventAction, eventDate DESC)';

            EXECUTE 'ALTER TABLE public.' || partition || ' ADD primary key(EventActionID);';
          END IF;

          -- Inserimos o registro
          EXECUTE 'INSERT INTO public.' || partition || ' SELECT(public.' || TG_RELNAME || ' ' || quote_literal(NEW) || ').*;';
          
          RETURN NULL;
        END;
      END IF;
    END IF;
  END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER eventActionTransactionTrigger
  BEFORE INSERT ON public.eventActions
  FOR EACH ROW EXECUTE PROCEDURE eventActionTransaction();

-- ---------------------------------------------------------------------
-- O registro dos tratamentos dados aos eventos
-- ---------------------------------------------------------------------
-- A tabela que armazena as ações dadas a cada evento em função de quem
-- trata, incluindo o tratador, a ação realizada e o tipo de evento
-- considerado.
-- ----------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS eventTreatments (
  eventTreatmentID    serial,         -- ID da ação do evento
  eventActionID       integer         -- O ID da ação do evento
                      NOT NULL,
  eventDate           timestamp       -- A data/hora do evento
                      NOT NULL,
  treatmentAt         timestamp       -- A data/hora do tratamento
                      NOT NULL
                      DEFAULT CURRENT_TIMESTAMP,
  treatmentByUserID   integer         -- O ID do usuário que tratou
                      NOT NULL,
  treatmentDetail     text            -- O detalhamento do tratamento
                      NOT NULL,
  PRIMARY KEY (eventTreatmentID),
  FOREIGN KEY (eventActionID)
    REFERENCES eventActions(eventActionID)
    ON DELETE CASCADE,
  FOREIGN KEY (treatmentByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Gatilho para processar inserções na tabela de tratamento dos eventos
-- ---------------------------------------------------------------------
-- Cria um gatilho que lida com as inserções de registros na tabela de
-- tratamento dos eventos, criando as partições se necessário.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION eventTreatmentTransaction()
RETURNS trigger AS
$BODY$
  DECLARE
    yearOfEventTreatmentDate  char(4);
    monthOfEventTreatmentDate  char(2);
    startOfMonth date;
    endOfMonth date;
    partition  varchar;
  BEGIN
    -- Faz a criação de uma nova partição, se necessário, nos processos
    -- em que se insere os dados de eventos obtidos. Faz uso da variável
    -- especial TG_OP para verificar a operação executada.
    IF (TG_OP = 'INSERT') THEN
      IF (TG_WHEN = 'BEFORE') THEN
        BEGIN
          yearOfEventTreatmentDate := extract(YEAR FROM NEW.eventDate);
          monthOfEventTreatmentDate := LPAD(extract(MONTH FROM NEW.eventDate)::varchar, 2, '0');
          partition := TG_RELNAME || '_' || yearOfEventTreatmentDate || monthOfEventTreatmentDate;
          startOfMonth := to_char(NEW.eventDate, 'YYYY-MM-01');
          endOfMonth := (date_trunc('MONTH', NEW.eventDate) + INTERVAL '1 MONTH - 1 second')::date;
          
          -- Verifica se a tabela existe
          IF NOT EXISTS(SELECT T.relname, N.nspname FROM pg_catalog.pg_class AS T JOIN pg_catalog.pg_namespace AS N ON T.relnamespace = N.oid WHERE T.relname = partition AND N.nspname = 'public') THEN
            -- RAISE NOTICE 'A partição %/% da tabela de % está sendo criada', monthOfEventTreatmentDate, yearOfEventTreatmentDate, TG_RELNAME;
            EXECUTE 'CREATE TABLE public.' || partition || ' ( CHECK ( eventDate::date >= DATE ''' || startOfMonth || '''  AND eventDate::date <=  DATE ''' ||  endOfMonth || ''' )) INHERITS (public.' || TG_RELNAME || ');';
            EXECUTE 'ALTER TABLE public.' || partition || ' ADD primary key(EventTreatmentID);';
          END IF;

          -- Inserimos o registro
          EXECUTE 'INSERT INTO public.' || partition || ' SELECT(public.' || TG_RELNAME || ' ' || quote_literal(NEW) || ').*;';
          
          RETURN NULL;
        END;
      END IF;
    END IF;
  END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER eventTreatmentTransactionTrigger
  BEFORE INSERT ON public.eventTreatments
  FOR EACH ROW EXECUTE PROCEDURE eventTreatmentTransaction();
