-- =====================================================================
-- Posições
-- =====================================================================
-- O armazenamento do histórico de posições dos rastreadores.
-- =====================================================================
-- 
-- Aspectos gerais
-- 
-- As posições são gravadas na forma de latitude e longitude, acrescido
-- de um ângulo que nos indica o sentido para o qual o veículo está se
-- movimentando.
-- 
-- Para armazenarmos as coordenadas de longitude e latitude, precisamos
-- entender o quão “precisas” são as coordenadas correspondentes quando
-- convertidas em coordenadas projetadas (a serem exibidas num mapa)
-- Para isto, precisamos quantizar como as coordenadas com vários dígitos
-- decimais e o que eles representam em termos de precisão. Devemos
-- também considerar que a Terra é um elipsóide e não uma esfera. Então,
-- assim se modelarmos a forma da terra como um elipsóide (elipsóide de
-- dois eixos), não podemos mapear decimais de grau para a distância do
-- solo com uma única tabela, porque essa relação muda (para medições de
-- distância E/W) com a mudança de latitude. Observe esta tabela para
-- mostrar as variações e precisão envolvida:
-- 
-- Casas             Norte/Sul ou  Leste/Oeste  Leste/Oeste  Leste/Oeste
-- decim  Graus      Leste/Oeste   na latitude  na latitude  na latitude
--                   no equador    23 N/S       45 N/S       67 N/S
-- -----  ---------- ------------  -----------  -----------  -----------
--   0    1            111.32 km    102.47 km     78.71 km    43.496 km
--   1    0.1          11.132 km    10.247 km     7.871 km    4.3496 km
--   2    0.01         1.1132 km    1.0247 km     787.1 m     434.96 m
--   3    0.001        111.32 m     102.47 m      78.71 m     43.496 m
--   4    0.0001       11.132 m     10.247 m      7.871 m     4.3496 m
--   5    0.00001      1.1132 m     1.0247 m      787.1 mm    434.96 mm
--   6    0.000001     11.132 cm    102.47 mm     78.71 mm    43.496 mm
--   7    0.0000001    1.1132 cm    10.247 mm     7.871 mm    4.3496 mm
--   8    0.00000001   1.1132 mm    1.0247 mm     0.7871mm    0.43496mm
-- 
-- Para efeito de precisão, adotaremos 6 casas decimais, o que nos dá
-- um valor bom para posicionar algo no mapa com certa precisão.
-- 
-- Com relação à direção que o veículo está seguindo em função do seu
-- movimento, precisamos entender como os ângulos são relacionados aos
-- respectivos pontos cardeais. Para isto, veja o gráfico abaixo uma
-- reprodução de uma rosa dos ventos com o respectivo ângulo associado:
--            
--                               0º
--                315º                       45º   
--                     NW        N        NE       
--                      `.       |       .`        
--                        `.     |     .`          
--                          `.   |   .`            
--                            `. | .`              
--               270º W---------`.`---------E 90º  
--                             .`|`.               
--                           .`  |  `.             
--                         .`    |    `.           
--                       .`      |      `.         
--                     SW        S        SE       
--                225º                       135º  
--                              180º
-- 
-- Quando não existir possibilidade de mostrar o ângulo de direção
-- exato do veículo (com uma seta apontando a direção), pode-se adotar
-- um modo de informar o ângulo por aproximação usando caracteres de
-- seta presentes no sistema Unicode, dividindo os ângulos em trechos
-- conforme a tabela abaixo:
-- 
-- Ângulos de   0,00º <=  22,50º considerar Norte - N (🡱)
-- Ângulos de  22,50º <=  67,50º considerar Nordeste - NE (🡵)
-- Ângulos de  67,50º <= 112,50º considerar Leste - E (🡲)
-- Ângulos de 112,50º <= 157,50º considerar Sudeste - SE (🡶)
-- Ângulos de 157,50º <= 202,50º considerar Sul - S (🡳)
-- Ângulos de 202,50º <= 247,50º considerar Suldoeste - SW (🡷)
-- Ângulos de 247,50º <= 292,50º considerar Oeste - W (🡰)
-- Ângulos de 292,50º <= 337,50º considerar Noroeste - NW (🡴)
-- Ângulos acima de 337,50º considerar Norte - N (🡱)
-- 
-- =====================================================================

-- ---------------------------------------------------------------------
-- Tipos de evento de posição
-- ---------------------------------------------------------------------
-- Tracking: rastreamento normal,
-- Static: acompanhamento quando ignição desligada
-- Alarm: eventos de alarme ou outros eventos
-- ---------------------------------------------------------------------
CREATE TYPE PositionType AS ENUM('Tracking', 'Static', 'Alarm');

-- ---------------------------------------------------------------------
-- O registro de posições (histórico)
-- ---------------------------------------------------------------------
-- A tabela (particionada) que armazena os registros de posições de cada
-- equipamento ao longo do tempo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS positions (
  positionID              serial,         -- ID da posição
  type                    PositionType    -- O tipo de evento de posição
                          DEFAULT 'Static',
  contractorID            integer,        -- O ID do contratante
  equipmentID             integer,        -- ID do equipamento
  terminalID              varchar         -- Número de série do dispositivo
                          NOT NULL,       -- de rastreamento (terminal)
  mainTracker             boolean         -- O indicador do rastreador
                          NOT NULL        -- principal ou reserva
                          DEFAULT true,
  firmwareVersion         varchar,        -- Versão do firmware
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
  treated                 boolean         -- O indicativo de registro
                          DEFAULT FALSE,  -- tratado
  madeRequest             boolean         -- O indicativo de que uma
                          DEFAULT FALSE,  -- requisição foi feita à API
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
  alarms                  integer[]       -- Os alarmes ocorridos
                          DEFAULT '{}',
  driverIdentifierID      integer         -- O ID do identificador do
                          DEFAULT NULL,   -- motorista
  identifier              varchar(50)     -- O número do identificador
                          DEFAULT NULL,   -- do motorista
  driverID                integer,        -- ID do motorista
  driverRegistered        boolean,        -- O indicativo de que o motorista está registrado
  rs232Data               varchar,        -- Os dados da porta serial
  port                    integer,        -- A porta de comunicação pela qual o evento foi recebido
  protocolID              integer,        -- O ID do protocolo de comunicação
  PRIMARY KEY (positionID)
);

-- OLD INSERT INTO positions_202212 SELECT(positions '(24000,Alarm,3250,359510081341955,,3392,AAA2222,1646,1569,"2022-12-31 13:13:44","2022-12-31 13:13:48.959298",-23.559756,-46.806438,11,196,f,0,7070,15022,12.79,t,4.00,57,{},{},f,"Rua Padre Manoel da Nóbrega, Vila Jardim Veloso, Padroeira, Osasco, Osasco, São Paulo, BR-SP, 06154-000",,,t,{4})').* RETURNING positionID;
-- NEW INSERT INTO positions SELECT(positions '(24000,Alarm,3250,359510081341955,,3392,AAA2222,1646,1569,"2022-12-31 13:13:44","2022-12-31 13:13:48.959298",-23.559756,-46.806438,"Rua Padre Manoel da Nóbrega, Vila Jardim Veloso, Padroeira, Osasco, Osasco, São Paulo, BR-SP, 06154-000",t,11,196,f,0,7070,15022,12.79,t,4.00,57,{},{},{4},,)').* RETURNING positionID;

-- ---------------------------------------------------------------------
-- O registro da última posição de cada equipamento
-- ---------------------------------------------------------------------
-- A tabela que armazena o registro da última posição de cada
-- equipamento ao longo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS lastPositions (
  positionID              integer,        -- ID da última posição
  type                    PositionType    -- O tipo de evento de posição
                          DEFAULT 'Static',
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
  alarms                  integer[]       -- Os alarmes ocorridos
                          DEFAULT '{}',
  driverIdentifierID      integer         -- O ID do identificador do
                          DEFAULT NULL,   -- motorista
  identifier              varchar(50)     -- O número do identificador
                          DEFAULT NULL,   -- do motorista
  driverID                integer,        -- ID do motorista
  driverRegistered        boolean,        -- O indicativo de que o motorista está registrado
  port                    integer,        -- A porta de comunicação pela qual o evento foi recebido
  protocolID              integer,        -- O ID do protocolo de comunicação
  PRIMARY KEY (equipmentID)
);

-- Cria um índice para obtenção de posições mais rapidamente
CREATE INDEX idx_lastpositions_equipment_port ON public.lastPositions (equipmentID, port);
CREATE INDEX idx_lastpositions_equipment_plate_maintracker_terminalid ON public.lastPositions (equipmentID, plate, mainTracker, terminalID);
CREATE INDEX idx_lastpositions_contractor_customer ON public.lastPositions (contractorID, plate, customerID);
CREATE INDEX idx_lastpositions_contractor_customerPayer ON public.lastPositions (contractorID, plate, customerPayerID);
CREATE INDEX idx_lastpositions_equipmentid ON public.lastPositions(equipmentID);
CREATE INDEX idx_lastpositions_vehicleid ON public.lastPositions(vehicleID);

-- ---------------------------------------------------------------------
-- A fila de registros que precisam ter o endereço atualizado
-- ---------------------------------------------------------------------
-- A tabela que armazena os registros que precisam ter o endereço obtido
-- através da API e atualizado na tabela de posições.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pendingAddressToUpdateQueue (
  positionID  integer         -- ID da posição
              NOT NULL,
  systemDate  timestamp       -- A data/hora do GPS
              NOT NULL,
  latitude    numeric(9,6)    -- A latitude da posição
              NOT NULL,
  longitude   numeric(9,6)    -- A longitude da posição
              NOT NULL,
  queueNumber integer         -- O número da fila
              NOT NULL
              DEFAULT 0,
  done        boolean         -- O indicativo de que a tarefa foi
              NOT NULL        -- realizada
              DEFAULT FALSE,
  PRIMARY KEY (positionID)
);
ALTER TABLE pendingAddressToUpdateQueue
  ADD COLUMN done boolean NOT NULL DEFAULT FALSE;

CREATE INDEX pendingAddressToUpdateQueue_bySystemDate
  ON pendingAddressToUpdateQueue(systemDate);

CREATE INDEX pendingAddressToUpdateQueue_byQueueNumberDone
    ON pendingAddressToUpdateQueue (queueNumber, done);

-- Teste de inserção de registros na fila de atualização de endereços
-- INSERT INTO pendingAddressToUpdateQueue (positionID, systemDate, latitude, longitude) VALUES
--   (51370113, '2024-01-12 14:33:06.719992', -23.503123, -46.778314),
--   (51370121, '2024-01-12 14:33:07.546085', -23.553735, -46.787328),
--   (51369951, '2024-01-12 14:32:48.607567', -18.851450, -41.983703),
--   (51369996, '2024-01-12 14:32:53.724252', -23.494914, -47.433953),
--   (51370102, '2024-01-12 14:33:05.757058', -23.604687, -46.605982),
--   (51370165, '2024-01-12 14:33:11.093813', -22.504341, -47.399696),
--   (51369958, '2024-01-12 14:32:49.621266', -23.353056, -46.053109),
--   (51369987, '2024-01-12 14:32:52.60182 ', -29.182021, -51.574178),
--   (51370033, '2024-01-12 14:32:57.272446', -29.182031, -51.574084),
--   (51370080, '2024-01-12 14:33:03.295788', -29.182152, -51.573570),
--   (51370092, '2024-01-12 14:33:05.311457', -23.649910, -46.454262),
--   (51369964, '2024-01-12 14:32:50.242457', -22.924345, -47.087920),
--   (51370085, '2024-01-12 14:33:04.325053', -22.924277, -47.087249),
--   (51370029, '2024-01-12 14:32:57.036743', -23.494703, -47.434059),
--   (51370056, '2024-01-12 14:33:00.099199', -23.496966, -46.779409),
--   (51370106, '2024-01-12 14:33:06.134676', -23.543951, -46.478166),
--   (51370164, '2024-01-12 14:33:11.055323', -23.792482, -46.660868);

-- ---------------------------------------------------------------------
-- Gatilho para processar atualizações na tabela de posições
-- ---------------------------------------------------------------------
-- Cria um gatilho que lida com as atualizações de registros na tabela
-- de posições, atualizando a última posição, se necessário.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION positionTransactionUpdate()
RETURNS trigger AS
$BODY$
  BEGIN
    IF (TG_OP = 'UPDATE') THEN
      IF (TG_WHEN = 'AFTER') THEN
        IF OLD.address <> NEW.address THEN
          -- Ocorreu uma mudança de endereço, então precisamos atualizar
          -- esta informação nas tabelas que armazenam esta informação

          -- Atualizamos a tabela de últimas posições
          -- RAISE NOTICE 'UPDATE address in last position of Device: %', OLD.terminalID;
          UPDATE public.lastPositions
             SET address = NEW.address
           WHERE positionID = OLD.positionID;
          
          -- Verificamos se este é um evento de alarme
          IF (OLD.type = 'Alarm') THEN
            -- Atualizamos a tabela de eventos
            -- RAISE NOTICE 'UPDATE address in event position of Device: %', OLD.terminalID;
            UPDATE public.events
              SET address = NEW.address
            WHERE positionID = OLD.positionID;
          END IF;
        END IF;
      END IF;

      -- Retornamos a nova entidade
      RETURN NEW;
    END IF;

    -- Qualquer outro resultado é ignorado após o processamento anterior
    RETURN NULL;
  END;
$BODY$ LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Gatilho para processar inserções na tabela de posições
-- ---------------------------------------------------------------------
-- Cria um gatilho que lida com as inserções de registros na tabela de
-- posições, criando as partições se necessário, identificando o
-- equipamento e o veículo, se possível, e atualizando as últimas
-- posições de cada equipamento de rastreamento.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION positionTransaction()
RETURNS trigger AS
$BODY$
  DECLARE
    yearOfPositionDate  char(4);
    monthOfPositionDate  char(2);
    startOfMonth date;
    endOfMonth date;
    partition  varchar;
    newPositionID  integer;
    newEventID  integer;
    newTrackerEventID  integer;
    treatment  jsonb;
    needUpdate  boolean;
    needToUpdateEquipment  boolean;
    hasBlocking  boolean;
    hasSiren  boolean;
    hasiButton  boolean;
    iButton record;
    iButtonStore record;
    currentDriverIdentifierID  integer;
    fenceEvents  int[];
    fenceCustomerRow record;
    iButtonMemSize integer;
    iButtonMemUsed integer;
    lastPosition integer;
    newPosition integer;
    curMemPosition integer;
  BEGIN
    -- Faz a criação de uma nova partição, se necessário, nos processos
    -- em que se insere os dados de posicionamento obtidos. Faz uso da
    -- variável especial TG_OP para verificar a operação executada.
    IF (TG_OP = 'INSERT') THEN
      IF (TG_WHEN = 'BEFORE') THEN
        BEGIN
          yearOfPositionDate := extract(YEAR FROM NEW.eventDate);
          monthOfPositionDate := LPAD(extract(MONTH FROM NEW.eventDate)::varchar, 2, '0');
          partition := TG_RELNAME || '_' || yearOfPositionDate || monthOfPositionDate;
          startOfMonth := to_char(NEW.eventDate, 'YYYY-MM-01');
          endOfMonth := (date_trunc('MONTH', NEW.eventDate) + INTERVAL '1 month - 1 day')::date;
          
          -- Verifica se a tabela existe
          IF NOT EXISTS(SELECT T.relname, N.nspname FROM pg_catalog.pg_class AS T JOIN pg_catalog.pg_namespace AS N ON T.relnamespace = N.oid WHERE T.relname = partition AND N.nspname = 'public') THEN
            -- RAISE NOTICE 'A partição %/% da tabela de % está sendo criada', monthOfPositionDate, yearOfPositionDate, TG_RELNAME;
            EXECUTE 'CREATE TABLE public.' || partition || ' ( CHECK ( eventDate::date >= DATE ''' || startOfMonth || '''  AND eventDate::date <=  DATE ''' ||  endOfMonth || ''' )) INHERITS (public.' || TG_RELNAME || ');';
            EXECUTE 'CREATE INDEX ' || partition || '_byevent ON public.' || partition || '(eventDate)';
            EXECUTE 'CREATE INDEX ' || partition || '_byequipment ON public.' || partition || '(equipmentID, eventDate)';
            EXECUTE 'CREATE INDEX ' || partition || '_byvehicle ON public.' || partition || '(vehicleID, eventDate)';
            EXECUTE 'CREATE INDEX ' || partition || '_byfirmware ON public.' || partition || '(terminalID, firmwareVersion)';
            EXECUTE 'CREATE INDEX ' || partition || '_byaddress ON public.' || partition || '(address)';
            EXECUTE 'CREATE INDEX ' || partition || '_toroute ON public.' || partition || '(contractorid, vehicleid, maintracker, eventdate)';
            EXECUTE 'CREATE INDEX ' || partition || '_covering ON public.' || partition || '(terminalID, eventDate DESC) INCLUDE (ignitionStatus, blockStatus, sirenStatus, emergencyStatus, charge, powerVoltage, batteryVoltage, gsmSignalStrength, inputs, outputs)';
            EXECUTE 'ALTER TABLE public.' || partition || ' ADD primary key(positionID);';
            -- Retirada esta constraint pois alguns rastreadores podem
            -- enviar mais de uma posição no mesmo segundo, o que impede
            -- a inserção de novos registros
            -- EXECUTE 'ALTER TABLE public.' || partition || ' ADD CONSTRAINT ' || partition || '_unique UNIQUE (terminalID, eventDate, latitude, longitude);';

            -- Criamos o gatilho para lidar com modificações da tabela
            -- para lidar efeciêntemente com as atualizações de
            -- endereços (quando sua obtenção falhou)
            EXECUTE 'CREATE TRIGGER positionTransactionTriggerUpdate_' || partition || ' AFTER UPDATE ON public.' || partition || ' FOR EACH ROW EXECUTE PROCEDURE positionTransactionUpdate();';
          ELSE
            -- RAISE NOTICE 'A partição %/% da tabela de % já existe', monthOfPositionDate, yearOfPositionDate, TG_RELNAME;
          END IF;

          -- Acrescenta a informação do ID do rastreador, do veículo e o
          -- proprietário do veículo, se disponível, e do respectivo
          -- pagante do rastreador
          SELECT E.equipmentID,
                 E.main,
                 E.contractorID,
                 CASE
                   WHEN M.analogoutput > 0 OR M.digitaloutput > 0 THEN
                     CASE
                       WHEN V.vehicleID IS NULL THEN TRUE
                       ELSE E.hasBlocking
                     END
                   ELSE FALSE
                 END AS hasBlocking,
                 CASE
                   WHEN M.analogoutput > 1 OR M.digitaloutput > 1 THEN
                     CASE
                       WHEN V.vehicleID IS NULL THEN TRUE
                       ELSE E.hasSiren
                     END
                   ELSE FALSE
                 END AS hasSiren,
                 CASE
                   WHEN M.hasIbuttonInput THEN E.hasiButton
                   ELSE FALSE
                 END AS hasiButton,
                 (Length(E.serialNumber) < M.serialNumberSize AND Length(NEW.terminalID) = M.serialNumberSize) OR
                 (Length(E.serialNumber) < M.reducedNumberSize AND Length(NEW.terminalID) = M.reducedNumberSize),
                 E.iButtonsMemSize,
                 E.iButtonsMemUsed,
                 V.plate,
                 V.vehicleID,
                 V.customerID,
                 V.subsidiaryID,
                 E.customerPayerID,
                 E.subsidiaryPayerID
            INTO NEW.equipmentID,
                 NEW.mainTracker,
                 NEW.contractorID,
                 hasBlocking,
                 hasSiren,
                 hasiButton,
                 needToUpdateEquipment,
                 iButtonMemSize,
                 iButtonMemUsed,
                 NEW.plate,
                 NEW.vehicleID,
                 NEW.customerID,
                 NEW.subsidiaryID,
                 NEW.customerPayerID,
                 NEW.subsidiaryPayerID
            FROM erp.equipments AS E
           INNER JOIN erp.equipmentModels AS M USING (equipmentModelID)
            LEFT JOIN erp.vehicles AS V USING (vehicleID)
           WHERE LPAD(E.serialNumber, M.serialNumberSize, '0') = LPAD(NEW.terminalID, M.serialNumberSize, '0')
              OR (
                   (M.reducedNumberSize > 0) AND (CASE WHEN NEW.protocolID IS NULL THEN true ELSE M.protocolID = NEW.protocolID END) AND
                   (
                     (LENGTH(NEW.terminalID) = M.reducedNumberSize) AND
                     (E.serialNumber ILIKE '%' || NEW.terminalID)
                   ) OR (
                     (LENGTH(NEW.terminalID) = M.serialNumberSize) AND
                     (NEW.terminalID ILIKE '%' || E.serialNumber)
                   )
                 )
           ORDER BY E.systemDate DESC LIMIT 1;
          IF FOUND THEN
            -- RAISE NOTICE 'Identificado equipamento ID %', NEW.equipmentID;
            -- IF NEW.vehicleID IS NOT NULL THEN
            --   RAISE NOTICE 'Identificado veículo ID % %', NEW.vehicleID, NEW.plate;
            --   RAISE NOTICE 'Cliente ID % %', NEW.customerID, NEW.subsidiaryID;
            -- END IF;

            -- Verificar se a data/hora está adiantada em relação ao
            -- servidor
            IF NEW.eventDate > NOW() THEN
              -- Subtrair 3 segundos da data/hora atual e atribuir ao
              -- campo NEW.eventDate para simular uma data/hora válida
              -- vindo do equipamento
              NEW.eventDate := DATE_TRUNC('second', NOW() - INTERVAL '3 seconds');
            END IF;

            IF needToUpdateEquipment THEN
              -- Atualiza o número de série do equipamento no cadastro
              -- RAISE NOTICE 'Atualizando o número de série do equipamento %', NEW.equipmentID;
              UPDATE erp.equipments
                 SET serialNumber = NEW.terminalID
               WHERE equipmentID = NEW.equipmentID;
            END IF;

            IF hasiButton THEN
              -- Lógica principal para processar eventos de rastreadores
              -- que possuem leitor de iButton (identificador de
              -- motorista).

              -- Este bloco de código é executado apenas para eventos
              -- originados de equipamentos rastreadores que possuem a
              -- capacidade de identificar motoristas através de
              -- iButtons. A partir daqui, a trigger processa a
              -- identificação do motorista, o registro de condução e a
              -- gestão da memória do equipamento.

              IF NEW.identifier IS NOT NULL THEN
                -- ## Caso 1: Identificador de iButton Fornecido ##
                -- O equipamento rastreador enviou um evento com um
                -- identificador de iButton. Precisamos processar este
                -- identificador para associá-lo a um motorista e
                -- gerenciar o registro de condução deste motorista
                -- no veículo.

                -- Apaga registros anteriores de motoristas dirigindo
                -- este veículo. Garante que, ao identificar um novo
                -- motorista (através do iButton), não haja conflitos
                -- com registros de motoristas anteriores, assegurando
                -- que apenas o motorista atual seja registrado como
                -- estando dirigindo este veículo.
                -- RAISE NOTICE 'Apaga qualquer registro de motorista que esteja dirigindo um veículo em que esteja instalado este equipamento ID %', NEW.equipmentID;
                DELETE FROM public.driversInVehicles AS driver
                  WHERE driver.equipmentID = NEW.equipmentID;

                -- Foi passado um identificador do motorista, então faz
                -- a busca do ID dele e de seus atributos
                -- RAISE NOTICE 'Buscando o ID do identificador %', NEW.identifier;
                SELECT driverIdentifierID AS ID,
                       driverID,
                       active,
                       updatedByUserID AS userID
                  INTO iButton
                  FROM erp.driveridentifiers AS identifier
                 WHERE contractorID = NEW.contractorID
                   AND identifierTechnologyID = 1
                   AND identifier ILIKE NEW.identifier
                   AND customerID = NEW.customerID
                   AND deleted = false;
                IF FOUND THEN
                  -- ## iButton encontrado em erp.driveridentifiers ##
                  -- O identificador do motorista foi encontrado na
                  -- tabela erp.driveridentifiers. Prosseguimos com a
                  -- lógica para gerenciar o registro de condução e o
                  -- armazenamento do iButton.

                  -- Associamos o ID do identificador do motorista
                  NEW.driverIdentifierID := iButton.ID;

                  -- Buscamos informações do registro de armazenamento
                  -- do iButton neste veículo
                  SELECT driverIdentifierStoreID AS id,
                         stored,
                         toRemove,
                         removedAt,
                         registeredByUserID AS userID
                    INTO iButtonStore
                    FROM erp.driverIdentifierStore
                   WHERE contractorID = NEW.contractorID
                     AND customerID = NEW.customerID
                     AND equipmentID = NEW.equipmentID
                     AND vehicleID = NEW.vehicleID
                     AND driverIdentifierID = iButton.ID
                     AND deleted = FALSE
                   ORDER BY registeredAt DESC
                   LIMIT 1;
                  IF FOUND THEN
                    -- ## Registro de iButtonStore Encontrado ##
                    -- Encontramos um registro existente em
                    -- erp.driverIdentifierStore para este iButton,
                    -- veículo e equipamento. Agora, verificamos
                    -- diferentes cenários baseados em
                    -- NEW.driverRegistered, iButtonStore.stored,
                    -- iButtonStore.toRemove, etc.

                    IF NEW.driverRegistered THEN
                      -- ### Caso 1.1: iButton Registrado no Equipamento (NEW.driverRegistered = TRUE) ###
                      -- O evento indica que o iButton está registrado
                      -- na memória do equipamento. Verificamos o status
                      -- em iButtonStore.

                      IF iButtonStore.stored = FALSE THEN
                        -- #### Sub-caso 1.1.1: iButtonStore.stored = FALSE ####
                        -- No banco, consta como não armazenado, mas o
                        -- equipamento diz que está. Precisamos
                        -- sincronizar.

                        IF iButtonStore.toRemove THEN
                          -- ##### Sub-caso 1.1.1.1: iButtonStore.toRemove = TRUE ####
                          -- Marcado para remoção. Verificamos removedAt.

                          IF iButtonStore.removedAt IS NOT NULL THEN
                            -- ###### Sub-caso 1.1.1.1.a: iButtonStore.removedAt IS NOT NULL ######
                            -- Já tem data de remoção. Verificamos se a
                            -- remoção ocorreu antes do evento atual.

                            IF iButtonStore.removedAt < NEW.eventDate THEN
                              -- ###### Sub-caso 1.1.1.1.a.(i): removedAt < eventDate ######
                              -- Removido antes do evento, reseta o
                              -- status para 'stored'.
                              UPDATE erp.driverIdentifierStore
                                SET stored = TRUE,
                                    storedAt = NEW.eventDate,
                                    removedAt = NULL
                              WHERE driverIdentifierStoreID = iButtonStore.id;
                            END IF; -- Fim Sub-caso 1.1.1.1.a.(i)
                          END IF; -- Fim Sub-caso 1.1.1.1.a
                        ELSE
                          -- ##### Sub-caso 1.1.1.2: iButtonStore.toRemove = FALSE ####
                          -- Não está marcado para remoção. Força
                          -- 'stored = TRUE'.
                          UPDATE erp.driverIdentifierStore
                            SET stored = TRUE,
                                storedAt = NEW.eventDate
                          WHERE driverIdentifierStoreID = iButtonStore.id;
                        END IF; -- Fim Sub-caso 1.1.1
                      END IF; -- Fim Sub-caso 1.1

                    ELSE
                      -- ### Caso 1.2: iButton NÃO Registrado no Equipamento (NEW.driverRegistered = FALSE) ###
                      -- O evento indica que o iButton NÃO está
                      -- registrado na memória do equipamento.
                      -- Verificamos o status em iButtonStore.

                      IF iButtonStore.stored = TRUE THEN
                        -- #### Sub-caso 1.2.1: iButtonStore.stored = TRUE ####
                        -- No banco, consta como armazenado, mas o
                        -- equipamento diz que não está. Precisamos
                        -- corrigir o banco.
                        UPDATE erp.driverIdentifierStore
                          SET stored = FALSE,
                              storedAt = NULL
                        WHERE driverIdentifierStoreID = iButtonStore.id;
                      END IF; -- Fim Sub-caso 1.2
                    END IF; -- Fim Caso 1.1 e 1.2

                  ELSE
                    -- ## Registro de iButtonStore NÃO Encontrado ##
                    -- Não existe registro em erp.driverIdentifierStore
                    -- para este iButton, veículo e equipamento.
                    -- Precisamos criar um novo registro se o iButton
                    -- estiver registrado no equipamento e tiver um
                    -- driverID associado.

                    IF NEW.driverRegistered AND iButton.driverID IS NOT NULL THEN
                      -- ### Caso 1.3: Novo Registro iButtonStore (iButtonStore NOT FOUND) ###
                      -- iButton registrado no equipamento e driverID
                      -- válido. Verificamos a memória do equipamento e,
                      -- se necessário, inserimos um novo registro em
                      -- erp.driverIdentifierStore.

                      -- Verifica se a memória do equipamento está cheia
                      IF iButtonMemUsed >= iButtonMemSize THEN
                        -- #### Sub-caso 1.3.1: Memória Cheia ####
                        -- Memória cheia, gera alarme.

                        -- A memória esta cheia e, por algum motivo,
                        -- temos rastreadores que estão na memória do
                        -- equipamento e não estão registrados no
                        -- sistema. Então, vamos gerar um alerta para o
                        -- cliente
                        NEW.type := 'Alarm';
                        -- RAISE NOTICE 'Evento de identificador de motorista não cadastrado';
                        newTrackerEventID := 111;
                        -- RAISE NOTICE 'Adicionando evento %', newTrackerEventID;
                        IF NOT (newTrackerEventID = ANY(NEW.alarms)) THEN
                          NEW.alarms := NEW.alarms || newTrackerEventID;
                        END IF;
                      ELSE
                        -- #### Sub-caso 1.3.2: Memória Livre ####
                        -- Memória livre, encontra posição e insere novo
                        -- registro.
                        lastPosition := 0;
                        newPosition := 0;
                        FOR curMemPosition IN
                          SELECT memPosition
                            FROM erp.driverIdentifierStore
                          WHERE equipmentID = NEW.equipmentID
                            AND deleted = FALSE
                          ORDER BY memPosition
                        LOOP
                          -- RAISE NOTICE 'Position: %', curMemPosition;
                          IF curMemPosition - lastPosition > 1 THEN
                            -- Encontramos uma posição livre
                            -- RAISE NOTICE 'Position free: %', lastPosition + 1;
                            newPosition := lastPosition + 1;
                            EXIT;
                          END IF;
                          lastPosition := curMemPosition;
                        END LOOP;

                        -- Se não encontramos uma posição livre, então a
                        -- próxima posição é a última ocupada + 1
                        -- RAISE NOTICE 'New position: %', newPosition;
                        -- RAISE NOTICE 'Last position: %', lastPosition;
                        IF newPosition = 0 THEN
                          newPosition := lastPosition + 1;
                        END IF;

                        -- Insere novo registro em erp.driverIdentifierStore
                        INSERT INTO erp.driverIdentifierStore (
                          contractorID, customerID, vehicleID,
                          equipmentID, driverIdentifierID, memPosition,
                          stored, storedAt, registeredByUserID
                        ) VALUES (
                          NEW.contractorID, NEW.customerID, NEW.vehicleID,
                          NEW.equipmentID, iButton.ID, newPosition,
                          TRUE, NEW.eventDate, iButton.userID
                        );
                      END IF; -- Fim Sub-caso 1.3
                    END IF; -- Fim Caso 1.3
                  END IF; -- Fim IF FOUND (iButtonStore)

                  IF NEW.driverRegistered THEN
                    -- ## Registro em driversInVehicles ##
                    -- Insere registro em public.driversInVehicles
                    -- indicando que o motorista está dirigindo o
                    -- veículo.

                    -- RAISE NOTICE 'Identificado que o motorista % está dirigindo o veículo %', NEW.driverID, NEW.vehicleID;
                    INSERT INTO public.driversInVehicles (vehicleID, equipmentID, driverIdentifierID, driverID)
                    VALUES (NEW.vehicleID, NEW.equipmentID, iButton.ID, iButton.driverID);

                    IF NEW.ignitionStatus = TRUE THEN
                      -- Define driverID em NEW se ignição ligada
                      NEW.driverID := iButton.driverID;
                    END IF;
                  END IF;
                ELSE
                  -- ## iButton NÃO Encontrado em erp.driveridentifiers ##
                  -- O identificador do motorista NÃO foi encontrado na
                  -- tabela erp.driveridentifiers. Geração de alarme
                  -- (se driverRegistered).

                  NEW.driverIdentifierID := NULL;

                  IF NEW.driverRegistered THEN
                    -- ### Caso 1.4: iButton Não Encontrado, mas driverRegistered = TRUE ###
                    -- Alarme: iButton na memória do rastreador, mas não
                    -- cadastrado.
        
                    -- Por algum motivo este identificador está na
                    -- memória do rastreador (que liberou o motorista)
                    -- mas não está cadastrado no sistema. Então, vamos
                    -- gerar um alerta para o cliente
                    NEW.type := 'Alarm';
                    -- RAISE NOTICE 'Evento de identificador de motorista não cadastrado';
                    newTrackerEventID := 111;
                    -- RAISE NOTICE 'Adicionando evento %', newTrackerEventID;
                    IF NOT (newTrackerEventID = ANY(NEW.alarms)) THEN
                      NEW.alarms := NEW.alarms || newTrackerEventID;
                    END IF; 
                  END IF; -- Fim Caso 1.4
                END IF; -- Fim IF FOUND (iButton em erp.driveridentifiers)
              ELSE
                -- ## Caso 2: Nenhum identificador fornecido ##
                -- O equipamento rastreador NÃO enviou um identificador
                -- de iButton neste evento. Isso pode ocorrer em 
                -- eventos de ignição ligada/desligada ou outros eventos
                -- em que o iButton não é lido. Tratamos este caso
                -- de forma diferente dependendo do status da ignição.

                IF NEW.ignitionStatus = TRUE THEN
                  -- ### Sub-caso 2a: Ignicao Ligada ###
                  -- Com a ignição ligada, verificamos se já existe
                  -- um motorista associado a este veículo e equipamento
                  -- (possivelmente identificado em um evento anterior
                  -- com iButton). Se existir, mantemos a associação
                  -- e atualizamos o registro.

                  -- Verifica se temos um motorista dirigindo o veículo
                  -- RAISE NOTICE 'Identificando o motorista no veículo %', NEW.vehicleID;
                  SELECT driver.driverIdentifierID,
                         driver.driverID,
                         identifier.identifier
                    INTO iButton
                    FROM public.driversInVehicles AS driver
                   INNER JOIN erp.driveridentifiers AS identifier USING (driverIdentifierID)
                   WHERE driver.equipmentID = NEW.equipmentID
                     AND driver.vehicleID = NEW.vehicleID;
                  IF FOUND THEN
                    NEW.driverIdentifierID := iButton.driverIdentifierID;
                    NEW.identifier := iButton.identifier;
                    NEW.driverID := iButton.driverID;
                    NEW.driverRegistered := TRUE;
                  END IF;

                  -- Sempre atualiza o registro de motorista que está
                  -- dirigindo o veículo para lidarmos com a situação
                  -- em que o motorista desliga a ignição e liga
                  -- novamente antes do dispositivo desativar o
                  -- motorista
                  -- RAISE NOTICE 'Atualizando o motorista no veículo %', NEW.vehicleID;
                  UPDATE public.driversInVehicles
                     SET lastPositionAt = CURRENT_TIMESTAMP
                   WHERE equipmentID = NEW.equipmentID
                     AND vehicleID = NEW.vehicleID;
                ELSE
                  -- ### Sub-caso 2b: Ignicao Desligada ###
                  -- Com a ignição desligada, removemos registros de
                  -- motoristas associados a este veículo e equipamento
                  -- que estejam "antigos" (última posição há mais de
                  -- 20s).

                  -- Apaga qualquer registro de motorista dirigindo este
                  -- veículo que seja superior a 20 segundos
                  DELETE FROM public.driversInVehicles AS driver
                    WHERE driver.equipmentID = NEW.equipmentID
                      AND driver.vehicleID = NEW.vehicleID
                      AND driver.lastPositionAt < CURRENT_TIMESTAMP - INTERVAL '20 seconds';
                END IF; -- Fim Sub-caso 2
              END IF; -- Fim análise de caso
            END IF; -- Fim hasiButton

            -- Verifica se este equipamento já está na lista de
            -- integração com outra plataforma
            IF (SELECT COUNT(*)
                FROM erp.equipmentstogethistory
                WHERE equipmentID = NEW.equipmentID
                AND contractorID = NEW.contractorID) = 1 THEN
              -- RAISE NOTICE 'Equipamento na lista de integração';
              -- Neste caso, precisamos verificar se não existe um outro
              -- registro com a mesma ID do registro a ser inserido (o
              -- ID está no campo firmwareVersion)
              IF EXISTS(SELECT 1 FROM public.positions WHERE terminalID = NEW.terminalID AND firmwareVersion = NEW.firmwareVersion) THEN
                -- O evento já foi inserido, então não faz nada
                -- RAISE NOTICE 'Evento já inserido';
                RETURN NULL;
              END IF;
            END IF;

            -- Verifica se é o rastreador do veículo FOZ3899
            IF NEW.terminalID = '357789644126580' THEN
              -- Analisamos se o evento é estático
              IF NEW.type = 'Static' THEN
                -- Verificamos se a ignição está desligada
                IF NEW.ignitionStatus = FALSE THEN
                  -- Verificamos se a posição é próximo à cada do
                  -- cliente (num raio de 600 metros)
                  IF (public.distance(-23.637958, -46.566146, NEW.latitude, NEW.longitude) < 600) THEN
                    -- A posição é próxima à casa do cliente, então
                    -- consideramos como se tivesse na casa do cliente
                    NEW.latitude := -23.637958;
                    NEW.longitude := -46.566146;
                    NEW.address := 'Rua Lourdes, Nova Gerty, São Caetano do Sul, São Paulo, BR-SP, 09571-470';
                  END IF;
                END IF;
              END IF;
            END IF;

            -- Força o estado do bloqueio e da sirene em função de
            -- existir um bloqueador e uma sirene instalada no veículo
            NEW.blockStatus := CASE
              WHEN hasBlocking THEN NEW.blockStatus
              ELSE NULL
            END;
            NEW.sirenStatus := CASE
              WHEN hasSiren THEN NEW.sirenStatus
              ELSE NULL
            END;

            -- Obtém o evento de cerca, se for o caso
            IF NEW.mainTracker = TRUE AND NEW.vehicleID > 0 THEN
              -- RAISE NOTICE 'Obtendo eventos de cerca para todos os usuários autorizados';
              FOR fenceCustomerRow IN
                SELECT DISTINCT customerID AS id
                  FROM (
                    SELECT customerUser.entityID AS customerID
                      FROM erp.authorizedequipments AS authorized
                    INNER JOIN erp.users AS customerUser USING (userID)
                    WHERE authorized.vehicleID = NEW.vehicleID
                      AND authorized.contractorID = NEW.contractorID
                    UNION
                    SELECT customerID
                      FROM erp.vehicles
                    WHERE vehicleID = NEW.vehicleID
                      AND contractorID = NEW.contractorID
                    UNION
                    SELECT entityID AS customerID
                      FROM erp.entities
                    WHERE contractor = TRUE
                      AND contractorID = NEW.contractorID
                    ) AS customerRoll
              LOOP
                SELECT *
                  INTO fenceEvents
                  FROM public.getFencesAction(
                        fenceCustomerRow.id,
                        NEW.latitude,
                        NEW.longitude,
                        NEW.vehicleID
                      );
                IF fenceEvents IS NOT NULL THEN
                  -- Indicamos que é um alarme sempre
                  NEW.type := 'Alarm';

                  -- Adicionamos os eventos de cerca ao registro
                  -- RAISE NOTICE 'Eventos de cerca obtidos';
                  FOREACH newTrackerEventID IN ARRAY fenceEvents
                  LOOP
                    -- RAISE NOTICE 'Adicionando evento de cerca %', newTrackerEventID;
                    IF NOT (newTrackerEventID = ANY(NEW.alarms)) THEN
                      NEW.alarms := NEW.alarms || newTrackerEventID;
                    END IF;
                  END LOOP;
                END IF;
              END LOOP;
            END IF;

            -- Inserimos o registro
            -- RAISE NOTICE 'Inserimos o registro em %', partition;
            EXECUTE 'INSERT INTO public.' || partition || ' SELECT(public.' || TG_RELNAME || ' ' || quote_literal(NEW) || ').* RETURNING positionID;'
              INTO newPositionID;
            
            -- Se o registro inserido não contiver um endereço, então
            -- inserimos na fila de atualização de endereços
            IF NEW.address = 'ND' THEN
              -- RAISE NOTICE 'Inserimos na fila de atualização de endereços';
              INSERT INTO public.pendingAddressToUpdateQueue (positionID, systemDate, latitude, longitude)
                VALUES (newPositionID, NEW.systemDate, NEW.latitude, NEW.longitude);
            END IF;

            -- Atualizamos a data de última comunicação do equipamento
            -- RAISE NOTICE 'Atualizando a data de última comunicação do equipamento %', NEW.equipmentID;
            UPDATE erp.equipments
               SET lastCommunication = date_trunc('seconds', NEW.systemDate),
                   online = TRUE
             WHERE equipmentID = NEW.equipmentID;
            
            -- Lidamos com a informação do registro de última posição
            SELECT eventDate <= NEW.eventDate
              INTO needUpdate
              FROM public.lastPositions
             WHERE equipmentID = NEW.equipmentID;
            IF FOUND THEN
              IF needUpdate THEN
                -- Atualize o registro de última posição
                -- RAISE NOTICE 'UPDATE last position of Device: %', NEW.terminalID;
                UPDATE public.lastPositions
                  SET positionID = newPositionID,
                      type = NEW.type,
                      terminalID = NEW.terminalID,
                      contractorID = NEW.contractorID,
                      equipmentID = NEW.equipmentID,
                      mainTracker = NEW.mainTracker,
                      FirmwareVersion = NEW.FirmwareVersion,
                      vehicleID = NEW.vehicleID,
                      plate = NEW.plate,
                      customerID = NEW.customerID,
                      subsidiaryID = NEW.subsidiaryID,
                      customerPayerID = NEW.customerPayerID,
                      subsidiaryPayerID = NEW.subsidiaryPayerID,
                      eventDate = NEW.eventDate,
                      gpsDate = NEW.gpsDate,
                      systemDate = NEW.systemDate,
                      latitude = NEW.latitude,
                      longitude = NEW.longitude,
                      withGPS = NEW.withGPS,
                      realTime = NEW.realTime,
                      address = NEW.address,
                      satellites = NEW.satellites,
                      mcc = NEW.mcc,
                      mnc = NEW.mnc,
                      course = NEW.course,
                      ignitionStatus = NEW.ignitionStatus,
                      blockStatus = NEW.blockStatus,
                      sirenStatus = NEW.sirenStatus,
                      emergencyStatus = NEW.emergencyStatus,
                      speed = NEW.speed,
                      odometer = NEW.odometer,
                      horimeter = NEW.horimeter,
                      rpm = NEW.rpm,
                      powerVoltage = NEW.powerVoltage,
                      charge = NEW.charge,
                      batteryVoltage = NEW.batteryVoltage,
                      gsmSignalStrength = NEW.gsmSignalStrength,
                      inputs = NEW.inputs,
                      outputs = NEW.outputs,
                      alarms = NEW.alarms,
                      driverIdentifierID = NEW.driverIdentifierID,
                      identifier = NEW.identifier,
                      driverID = NEW.driverID,
                      driverRegistered = NEW.driverRegistered,
                      port = NEW.port,
                      protocolID = NEW.protocolID
                WHERE equipmentID = NEW.equipmentID;
              ELSE
                -- Não faça nada, pois a data do último evento já é
                -- posterior ou igual à data do evento sendo inserido
                -- RAISE NOTICE 'KEEP last position of Device: %', NEW.terminalID;
              END IF;
            ELSE
              -- Insira um novo registro na tabela
              -- RAISE NOTICE 'INSERT last position of Device: %', NEW.terminalID;
              INSERT INTO public.lastPositions (positionID, type,
                      contractorID, equipmentID, terminalID,
                      mainTracker, FirmwareVersion, vehicleID, plate,
                      customerID, subsidiaryID, customerPayerID,
                      subsidiaryPayerID, eventDate, gpsDate, systemDate,
                      latitude, longitude, withGPS, realTime, address,
                      satellites, mcc, mnc, course, ignitionStatus,
                      blockStatus, sirenStatus, emergencyStatus, speed,
                      odometer, horimeter, rpm, powerVoltage, charge,
                      batteryVoltage, gsmSignalStrength, inputs,
                      outputs, alarms, driverIdentifierID, identifier,
                      driverID, driverRegistered, port, protocolID)
              VALUES (newPositionID, NEW.type, NEW.contractorID,
                      NEW.equipmentID, NEW.terminalID, NEW.mainTracker,
                      NEW.FirmwareVersion, NEW.vehicleID, NEW.plate,
                      NEW.customerID, NEW.subsidiaryID,
                      NEW.customerPayerID, NEW.subsidiaryPayerID,
                      NEW.eventDate, NEW.gpsDate, NEW.systemDate,
                      NEW.latitude, NEW.longitude, NEW.withGPS,
                      NEW.realTime, NEW.address, NEW.satellites,
                      NEW.mcc, NEW.mnc, NEW.course, NEW.ignitionStatus,
                      NEW.blockStatus, NEW.sirenStatus,
                      NEW.emergencyStatus, NEW.speed, NEW.odometer,
                      NEW.horimeter, NEW.rpm, NEW.powerVoltage,
                      NEW.charge, NEW.batteryVoltage,
                      NEW.gsmSignalStrength, NEW.inputs, NEW.outputs,
                      NEW.alarms, NEW.driverIdentifierID, NEW.identifier,
                      NEW.driverID, NEW.driverRegistered,
                      NEW.port, NEW.protocolID);
            END IF;

            -- Insere o evento de alarme, se for o caso
            IF (NEW.type = 'Alarm' AND array_length(NEW.alarms, 1) > 0) THEN
              -- Percorremos os eventos, adicionando-os individualmente
              -- RAISE NOTICE 'Inserimos os alarmes';
              FOREACH newTrackerEventID IN ARRAY NEW.alarms
              LOOP
                -- Obtemos o tratamento a ser dado à este evento
                treatment := getTreatmentRules(
                  NEW.equipmentID, newTrackerEventID
                );

                IF (treatment::text <> '{}'::text) THEN
                  -- O evento precisa ser tratado, então adiciona
                  -- RAISE NOTICE 'INSERT alarm event on Device: %', NEW.terminalID;
                  INSERT INTO public.events (contractorID, equipmentID,
                          terminalID, mainTracker, FirmwareVersion,
                          vehicleID, plate, customerID, subsidiaryID,
                          customerPayerID, subsidiaryPayerID, eventDate,
                          gpsDate, systemDate, latitude, longitude,
                          withGPS, realTime, address, satellites, mcc,
                          mnc, course, ignitionStatus, blockStatus,
                          sirenStatus, emergencyStatus, speed, odometer,
                          horimeter, rpm, powerVoltage, charge,
                          batteryVoltage, gsmSignalStrength, inputs,
                          outputs, driverIdentifierID, identifier,
                          driverID, driverRegistered, trackerEventID,
                          isReal, treatmentActions, port, protocolID,
                          positionID)
                  VALUES (NEW.contractorID, NEW.equipmentID,
                          NEW.terminalID, NEW.mainTracker,
                          NEW.FirmwareVersion, NEW.vehicleID, NEW.plate,
                          NEW.customerID, NEW.subsidiaryID,
                          NEW.customerPayerID, NEW.subsidiaryPayerID,
                          NEW.eventDate, NEW.gpsDate, NEW.systemDate,
                          NEW.latitude, NEW.longitude, NEW.withGPS,
                          NEW.realTime, NEW.address, NEW.satellites,
                          NEW.mcc, NEW.mnc, NEW.course,
                          NEW.ignitionStatus, NEW.blockStatus,
                          NEW.sirenStatus, NEW.emergencyStatus,
                          NEW.speed, NEW.odometer, NEW.horimeter,
                          NEW.rpm, NEW.powerVoltage, NEW.charge,
                          NEW.batteryVoltage, NEW.gsmSignalStrength,
                          NEW.inputs, NEW.outputs,
                          NEW.driverIdentifierID, NEW.identifier,
                          NEW.driverID, NEW.driverRegistered,
                          newTrackerEventID, 'yes', treatment, NEW.port,
                          NEW.protocolID, NEW.positionID);
                END IF;
              END LOOP;
            END IF;
          ELSE
            RAISE WARNING 'Equipamento não cadastrado';
          END IF;
          
          RETURN NULL;

        EXCEPTION WHEN unique_violation THEN  
            RAISE WARNING 'O valor de chave duplicado viola a restrição de exclusividade "%" em "%"', 
              TG_NAME, TG_TABLE_NAME 
              USING DETAIL = format('Chave (terminalID)=(%s) (eventDate)=(%s) (latitude)=(%s) (longitude)=(%s) já existe.', NEW.terminalID, NEW.eventDate, NEW.latitude, NEW.longitude);
            RETURN NULL;
        END;
      END IF;
    ELSIF (TG_OP = 'UPDATE') THEN
      -- Atualizamos o endereço do registro de alarme se o evento foi
      -- registrado como tal
      IF ((OLD.type = 'Alarm') AND (OLD.address <> NEW.address)) THEN
        -- RAISE NOTICE 'Atualizando endereço no evento de alarme do dispositivo: %', OLD.terminalID;
        UPDATE public.events
           SET address = NEW.address
         WHERE positionID = OLD.positionID;
      END IF;

      -- Retornamos a nova entidade
      RETURN NEW;
    END IF;
  END;
$BODY$ LANGUAGE plpgsql;

-- DROP TRIGGER IF EXISTS positionTransactionTrigger ON positions;
CREATE TRIGGER positionTransactionTrigger
  BEFORE INSERT OR UPDATE ON positions
  FOR EACH ROW EXECUTE PROCEDURE positionTransaction();

-- ---------------------------------------------------------------------
-- Encontrar depósito principal do contratante
-- ---------------------------------------------------------------------
-- Identifica qual o depósito principal do contratante
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.findDeposit(
  FcontractorID integer, Ftype DeviceType
) RETURNS integer AS
$BODY$
  DECLARE
    FdepositID integer;
  BEGIN
    SELECT depositID
      INTO FdepositID
      FROM erp.deposits
    WHERE contractorID = FcontractorID
      AND devicetype IN (Ftype, 'Both')
    ORDER BY master DESC
    LIMIT 1;
    IF NOT FOUND THEN
      -- Disparamos uma exceção
      RAISE EXCEPTION 'Não temos depósitos válidos para o contratante %.', FcontractorID
      USING HINT = 'Por favor, verifique os depósitos cadastrados.';
    END IF;

    RETURN FdepositID;
  END;
$BODY$ LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Função para atualizar as informações de ICCID, IMSI e IMEI através do
-- número de série do terminal.
-- ---------------------------------------------------------------------
-- Cria uma função que lida com as inserções de terminal móvel,
-- atualizando as informações do equipamento de rastreamento.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION updateMobileInformationOfEquipment(
  FterminalID varchar, FprotocolID integer, Fimei varchar,
  Fimsi varchar, Ficcid varchar)
RETURNS void AS
$$
DECLARE
  FequipmentID integer;
  FcurrentIMEI varchar;
  Fid integer;
  FequipmentICCID integer;
BEGIN
  -- Recupera a informação do ID do equipamento e das informações do
  -- IMEI
  SELECT E.equipmentID,
         E.imei
    INTO FequipmentID, FcurrentIMEI
    FROM erp.equipments AS E
   INNER JOIN erp.equipmentModels AS M USING (equipmentModelID)
   WHERE LPAD(E.serialNumber, M.serialNumberSize, '0') = LPAD(FterminalID, M.serialNumberSize, '0')
      OR (
           (M.reducedNumberSize > 0) AND (CASE WHEN FprotocolID IS NULL THEN true ELSE M.protocolID = FprotocolID END) AND
           (
             (LENGTH(FterminalID) = M.reducedNumberSize) AND
             (E.serialNumber ILIKE '%' || FterminalID)
           ) OR (
             (LENGTH(FterminalID) = M.serialNumberSize) AND
             (FterminalID ILIKE '%' || E.serialNumber)
           )
         )
   ORDER BY E.systemDate DESC LIMIT 1;
  IF FOUND THEN
    IF Fimei IS NOT NULL THEN
      IF (FcurrentIMEI IS NULL OR FcurrentIMEI <> Fimei) THEN
        -- RAISE NOTICE 'UPDATE IMEI to: %', Fimei;
        -- Atualiza o valor do IMEI deste equipamento
        UPDATE erp.equipments
          SET imei = Fimei
        WHERE equipmentID = FequipmentID;
      END IF;
    END IF;

    IF Ficcid IS NOT NULL THEN
      -- Atualizar a informação de IMSI e ICCID

      -- Verifica se o ICCID já está cadastrado
      SELECT id,
             equipmentID
        INTO Fid, FequipmentICCID
        FROM erp.iccids
       WHERE iccid = Ficcid;
      IF NOT FOUND THEN
        -- RAISE NOTICE 'INSERT ICCID: %', Ficcid;
        -- Insere o ICCID
        INSERT INTO erp.iccids (iccid, imsi, equipmentID)
        VALUES (Ficcid, Fimsi, FequipmentID);
      ELSE
        -- Verifica se o equipamento mudou
        IF FequipmentICCID <> FequipmentID THEN
          -- RAISE NOTICE 'UPDATE equipment FOR ICCID: %', Ficcid;
          UPDATE erp.iccids
            SET equipmentID = FequipmentID
          WHERE id = Fid;
        END IF;
      END IF;

      -- Verifica se o equipamento já tem um ICCID cadastrado e se tiver
      -- mantém apenas o último
      UPDATE erp.iccids
         SET equipmentID = NULL
       WHERE equipmentID = FequipmentID
         AND iccid <> Ficcid;
    END IF;
  END IF;
END
$$
LANGUAGE 'plpgsql';

-- SELECT updateMobileInformationOfEquipment('357789642345067', 1, '357789642345067', '724051231764943', '89550532120038629599');

-- ---------------------------------------------------------------------
-- Função para atualizar a informação do estado do bloqueio através do
-- número de série do terminal.
-- ---------------------------------------------------------------------
-- Cria uma função que lida com o estado do bloqueio de um equipamento,
-- gerando uma nova posição que força a atualização no cliente.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION updateBlockingState(FterminalID varchar,
  FprotocolID integer, Fblocking boolean, FeventDate timestamp)
RETURNS void AS
$$
DECLARE
  FequipmentID  integer;
  FmainTracker  boolean;
  FcontractorID integer;
  lastPosition  record;
BEGIN
  -- Obtém a informação do ID do rastreador
  SELECT E.equipmentID,
         E.main,
         COALESCE(E.assignedToID, E.contractorID) AS contractorID
    INTO FequipmentID,
         FmainTracker,
         FcontractorID
    FROM erp.equipments AS E
   INNER JOIN erp.equipmentModels AS M USING (equipmentModelID)
   WHERE LPAD(E.serialNumber, M.serialNumberSize, '0') = LPAD(FterminalID, M.serialNumberSize, '0')
      OR (
           (M.reducedNumberSize > 0) AND (CASE WHEN FprotocolID IS NULL THEN true ELSE M.protocolID = FprotocolID END) AND
           (
             (LENGTH(FterminalID) = M.reducedNumberSize) AND
             (E.serialNumber ILIKE '%' || FterminalID)
           ) OR (
             (LENGTH(FterminalID) = M.serialNumberSize) AND
             (FterminalID ILIKE '%' || E.serialNumber)
           )
         )
    ORDER BY E.systemDate DESC LIMIT 1;
  IF FOUND THEN
    -- RAISE NOTICE 'Identificado equipamento ID %', NEW.equipmentID;
    -- Copia o último registro de posição este equipamento
    SELECT *
      INTO lastPosition
      FROM public.lastPositions
      WHERE equipmentID = FequipmentID;

      -- Insere um novo registro de posição para forçar a atualização
      INSERT INTO public.positions (type, terminalID, firmwareVersion,
        eventDate, gpsDate, latitude, longitude, withGPS, realTime,
        address, madeRequest, satellites, mcc, mnc, course,
        ignitionStatus, blockstatus, sirenstatus, emergencystatus,
        speed, odometer, horimeter, rpm, powerVoltage, charge,
        batteryVoltage, gsmSignalStrength, alarms, inputs, outputs,
        driverID, driverRegistered, port, protocolID) VALUES (
          CASE
            WHEN lastPosition.ignitionStatus = TRUE THEN 'Tracking'::PositionType
            ELSE 'Static'::PositionType
          END,
          lastPosition.terminalID,
          lastPosition.firmwareVersion,
          FeventDate,
          FeventDate,
          lastPosition.latitude,
          lastPosition.longitude,
          lastPosition.withGPS,
          lastPosition.realTime,
          lastPosition.address,
          false,
          lastPosition.satellites,
          lastPosition.mcc,
          lastPosition.mnc,
          lastPosition.course,
          lastPosition.ignitionStatus,
          Fblocking,
          lastPosition.sirenstatus,
          lastPosition.emergencystatus,
          lastPosition.speed,
          lastPosition.odometer,
          lastPosition.horimeter,
          lastPosition.rpm,
          lastPosition.powerVoltage,
          lastPosition.charge,
          lastPosition.batteryVoltage,
          lastPosition.gsmSignalStrength,
          '{}'::int[],
          lastPosition.inputs,
          lastPosition.outputs,
          lastPosition.driverID,
          lastPosition.driverRegistered,
          lastPosition.port,
          lastPosition.protocolID
        );
  END IF;
END
$$
LANGUAGE 'plpgsql';

-- SELECT updateBlockingState('357789642345067', 3, true, '2019-01-01 00:00:00');

-- ---------------------------------------------------------------------
-- Função para obter a distância entre duas coordenadas geográficas
-- através da fórmula de Haversine
-- ---------------------------------------------------------------------
-- Cria uma função que obtém a distância entre duas coordenadas de forma
-- menos precisa, utilizando a fórmula de Haversine. Fazemos isto pois
-- as distâncias que queremos calcular são pequenas, e esta fórmula é
-- mais rápida.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.distanceInMeters(lat1 double precision,
  lng1 double precision, lat2 double precision, lng2 double precision)
RETURNS double precision AS
$BODY$
  SELECT atan2(
    sqrt(
      sin(radians($3-$1)/2)^2 +
      sin(radians($4-$2)/2)^2 *
      cos(radians($1)) *
      cos(radians($3))
    ),
    1 - sqrt(
      sin(radians($3-$1)/2)^2 +
      sin(radians($4-$2)/2)^2 *
      cos(radians($1)) *
      cos(radians($3))
    )
  ) * 2 * 6371 * 1000 AS distance;
$BODY$
LANGUAGE sql IMMUTABLE COST 100;

-- ---------------------------------------------------------------------
-- Função para obter o traçado de uma rota
-- ---------------------------------------------------------------------
-- Cria uma função que obtém o traçado de uma rota, obtendo os pontos
-- de uma rota e determinando informações complementares de paradas.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION getTripSegments(FcontractorID integer,
  FuserID integer, FisVehicle boolean, Fid integer,
  FmainTracker boolean, FstartDate timestamp with time zone,
  FendDate timestamp with time zone, filterByEntityID integer,
  belongToAnAssociation boolean)
RETURNS jsonb AS
$$
DECLARE
  segments jsonb := '[]';

  -- Variáveis para controle do loop
  latitude  float8;
  longitude  float8;
  speed  integer;
  ignitionState  boolean;
  eventDate  timestamp;

  -- Variáveis para controle da última posição
  previousLatitude  float8;
  previousLongitude  float8;
  previousIgnitionState  boolean;
  previousEventDate  timestamp;

  -- Variáveis auxiliares de totalização
  startDate  timestamp := NULL;
  totalTime  integer;
  distance  float8;
  typeOfPoint  text;
  lastSegment  integer;
  ignitionLabel text;
BEGIN
  -- RAISE NOTICE 'Obtendo o traçado da rota do equipamento %', Fid;
  -- Loop pelas coordenadas recuperadas
  FOR latitude, longitude, ignitionState, speed, eventDate IN
    SELECT position.latitude,
           position.longitude,
           position.ignitionStatus,
           position.speed,
           position.eventDate
      FROM public.positions AS position
     WHERE position.contractorID = FcontractorID
       AND CASE
             WHEN FisVehicle
              THEN position.vehicleID = Fid
              ELSE position.equipmentID = Fid
           END
       AND position.eventDate BETWEEN FstartDate AND FendDate
       AND position.mainTracker = FmainTracker
       AND (
             CASE
               WHEN belongToAnAssociation AND filterByEntityID > 0
                 THEN position.customerPayerID = filterByEntityID
               WHEN NOT belongToAnAssociation AND filterByEntityID > 0
                 THEN position.customerID = filterByEntityID
               ELSE TRUE
             END
             OR position.equipmentID IN (
              SELECT equipmentID
                FROM erp.authorizedEquipments
               WHERE userID = FuserID
             )
           )
     ORDER BY position.eventDate ASC
  LOOP
    -- RAISE NOTICE 'Coordenada: % %, ign: %, data: %',
    --   latitude, longitude, ignitionState, eventDate;
    
    -- Verificar se é a primeira coordenada
    IF previousLatitude IS NULL THEN
      -- Inicializa as informações da coordenada anterior
      -- RAISE NOTICE 'Primeira coordenada: % %, ign: %',
      --   eventDate, ARRAY[latitude, longitude], ignitionState
      -- ;
      
      previousLatitude := latitude;
      previousLongitude := longitude;
      previousIgnitionState := ignitionState;
      previousEventDate := eventDate;

      IF ignitionState THEN
        -- Ignição já está ligada, então precisa registrar o ponto
        segments := segments || jsonb_build_object(
          'type', 'start',
          'position', jsonb_build_array(latitude, longitude),
          'eventDate', to_char(eventDate,'DD/MM/YYYY HH24:MI:SS'),
          'ignition', ignitionState,
          'speed', speed,
          'info', NULL
        );
      END IF;
      startDate := eventDate;

      -- Passar para a próxima iteração do loop
      CONTINUE;
    END IF;

    -- Calcula a distância entre as coordenadas utilizando a fórmula de
    -- Haversine
    distance := public.distanceInMeters(
      previousLatitude, previousLongitude,
      latitude, longitude
    );
    -- RAISE NOTICE 'Distância em relação ao último ponto: %', distance;

    -- Conforme o estado da ignição e a distância entre o último ponto,
    -- determina se a coordenada deve ser ignorada (considerar veículo
    -- parado no mesmo lugar) ou se deve ser adicionada ao segmento
    IF ( (previousIgnitionState = ignitionState) AND (
         (ignitionState = FALSE AND distance <= 30)
         -- OR (distance <= 10)
         )
       ) THEN
      -- RAISE NOTICE '[%] Ignorando coordenada muito próxima: %',
      --   CASE ignitionState WHEN TRUE THEN 'Andando' ELSE 'Parado' END,
      --   ARRAY[latitude, longitude]
      -- ;

      CONTINUE;
    END IF;

    IF ignitionState = previousIgnitionState THEN
      -- RAISE NOTICE 'Veículo se deslocou: %', ARRAY[latitude, longitude];

      -- Veículo continua no mesmo estado (andando ou parado)
      IF ignitionState THEN
        -- Veículo continua andando, então registra o próximo ponto
        segments := segments || jsonb_build_object(
          'type', 'point',
          'position', jsonb_build_array(latitude, longitude),
          'eventDate', to_char(eventDate,'DD/MM/YYYY HH24:MI:SS'),
          'ignition', ignitionState,
          'speed', speed,
          'info', NULL
        );
      ELSE
        -- Veículo apesar de parado, deslocou-se, então registra
        IF jsonb_array_length(segments) = 0 THEN
          typeOfPoint := 'start';
        ELSE
          typeOfPoint := 'stop';
        END IF;

        -- Calcula o tempo total do período parado
        totalTime := EXTRACT(
          EPOCH FROM (eventDate - startDate)
        ) / 60;

        -- Registra o ponto de parada com a duração
        segments := segments || jsonb_build_array(
          jsonb_build_object(
            'type', typeOfPoint,
            'position', jsonb_build_array(latitude, longitude),
            'eventDate', to_char(eventDate,'DD/MM/YYYY HH24:MI:SS'),
            'ignition', FALSE,
            'speed', 0,
            'info', json_build_object(
              'startDate', to_char(startDate,'DD/MM/YYYY'),
              'startTime', to_char(startDate,'HH24:MI'),
              'endDate', to_char(eventDate,'DD/MM/YYYY'),
              'endTime', to_char(eventDate,'HH24:MI'),
              'total', totalTime
            )
          )
        );

        startDate := eventDate;
        totalTime := 0;
      END IF;
    ELSE
      -- Mudou o estado da ignição (parado para andando ou vice-versa)
      IF previousIgnitionState THEN
        -- Veículo estava andando e parou, então registramos o horário
        -- de início do período parado
        -- RAISE NOTICE 'Veículo parou: %', ARRAY[latitude, longitude];
        startDate := eventDate;
        totalTime := 0;
      ELSE
        -- Veículo estava parado e começou a se deslocar
        -- RAISE NOTICE 'Veículo começou a se deslocar: %',
        --   ARRAY[latitude, longitude]
        -- ;
        IF jsonb_array_length(segments) = 0 THEN
          typeOfPoint := 'start';
        ELSE
          typeOfPoint := 'stop';
        END IF;

        -- Calcula o tempo total do período parado
        totalTime := EXTRACT(
          EPOCH FROM (eventDate - startDate)
        ) / 60;

        -- Registra o ponto de parada com a duração
        segments := segments || jsonb_build_array(
          jsonb_build_object(
            'type', typeOfPoint,
            'position', jsonb_build_array(latitude, longitude),
            'eventDate', to_char(eventDate,'DD/MM/YYYY HH24:MI:SS'),
            'ignition', FALSE,
            'speed', 0,
            'info', json_build_object(
              'startDate', to_char(startDate,'DD/MM/YYYY'),
              'startTime', to_char(startDate,'HH24:MI'),
              'endDate', to_char(eventDate,'DD/MM/YYYY'),
              'endTime', to_char(eventDate,'HH24:MI'),
              'total', totalTime
            )
          )
        );
      END IF;
    END IF;

    -- Atualiza as informações da coordenada anterior
    previousLatitude := latitude;
    previousLongitude := longitude;
    previousIgnitionState := ignitionState;
  END LOOP;

  -- Adiciona o último ponto ao resultado
  -- RAISE NOTICE 'Fim do trajeto: %', ARRAY[previousLatitude, previousLongitude];
  IF previousLatitude IS NOT NULL THEN
    IF previousIgnitionState THEN
      -- Veículo estava se deslocando, então o último ponto é o fim do
      -- trajeto, alteramos ele para o tipo 'end'
      lastSegment := jsonb_array_length(segments) - 1;
      segments := jsonb_set(
        segments,
        ARRAY[lastSegment::text, 'type'],
        '"end"'
      );
    ELSE
      -- Calcula o tempo total do período parado
      totalTime := EXTRACT(
        EPOCH FROM (eventDate - startDate)
      ) / 60;

      -- Registra o ponto de parada com a duração
      segments := segments || jsonb_build_array(
        jsonb_build_object(
          'type', 'end',
          'position', jsonb_build_array(previousLatitude, previousLongitude),
          'eventDate', to_char(eventDate,'DD/MM/YYYY HH24:MI:SS'),
          'ignition', FALSE,
          'speed', 0,
          'info', json_build_object(
            'startDate', to_char(startDate,'DD/MM/YYYY'),
            'startTime', to_char(startDate,'HH24:MI'),
            'endDate', to_char(eventDate,'DD/MM/YYYY'),
            'endTime', to_char(eventDate,'HH24:MI'),
            'total', totalTime
          )
        )
      );
    END IF;
  END IF;

  -- Retorna o resultado
  RETURN segments;
END;
$$ LANGUAGE plpgsql;

-- SELECT getTripSegments(1, TRUE, 3363, TRUE, '2023-05-29 00:00:00', '2023-05-29 15:42:58', 0, FALSE);
-- SELECT getTripSegments(1, TRUE, 3363, TRUE, CURRENT_TIMESTAMP - interval '2 hours', CURRENT_TIMESTAMP, 0, FALSE);
-- \copy (SELECT getTripSegments(1, 3363, TRUE, '2023-05-29 00:00:00', '2023-05-29 15:42:58', 0, FALSE))
--   TO 'query.csv' WITH DELIMITER ',' CSV QUOTE '"' FORCE QUOTE *;

-- ---------------------------------------------------------------------
-- Função para obter a totalização de horas de uso em um dia
-- ---------------------------------------------------------------------
-- Cria uma função que obtém o acumulado de tempo de uso de um veículo
-- em um dia usando a informação proveniente do horímetro
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.calculateUsageTimePerHour(FcontractorID integer,
  FuserID integer, FisVehicle boolean, Fid integer, FmainTracker boolean,
  FeventDate date, filterByEntityID integer, belongToAnAssociation boolean)
RETURNS TABLE (hourOfDay INT, usageTimeInHour int)
AS $$
DECLARE
  ignitionStatus  boolean;
  eventDate  timestamp;
  horimeter  integer;

  previousIgnitionStatus  boolean;
  previousEventDate  timestamp;
  previousHorimeter  integer;
  lastHorimeter  integer;

  usageTimePerHour  integer[];
  usageTime  integer;

  currentTime  time;
  startOfNextHour  time;
  endTime  time;
  hourTotalized  integer;
  minutesToNextHour  integer;
BEGIN
  -- RAISE NOTICE 'Obtendo a informação de horímetro do % ID %',
  --   CASE WHEN FisVehicle THEN 'veículo' ELSE 'equipamento' END, Fid;

  -- Inicializa a matriz de totalizadores por hora
  FOR hour IN 0..23 LOOP
    usageTimePerHour[hour] := 0;
  END LOOP;

  -- Loop pelas informações recuperadas
  FOR eventDate, ignitionStatus, horimeter IN
    SELECT position.eventDate,
           position.ignitionStatus,
           position.horimeter
      FROM public.positions AS position
     WHERE position.contractorID = FcontractorID
       AND CASE
             WHEN FisVehicle
               THEN position.vehicleID = Fid
               ELSE position.equipmentID = Fid
           END
       AND DATE(position.eventDate) = FeventDate
       AND position.mainTracker = FmainTracker
       AND (
             CASE
               WHEN belongToAnAssociation AND filterByEntityID > 0
                 THEN position.customerPayerID = filterByEntityID
               WHEN NOT belongToAnAssociation AND filterByEntityID > 0
                 THEN position.customerID = filterByEntityID
               ELSE TRUE
             END
             OR position.equipmentID IN (
               SELECT equipmentID
                 FROM erp.authorizedEquipments
               WHERE userID = FuserID
             )
           )
     ORDER BY position.eventDate ASC
  LOOP
    --RAISE NOTICE 'Dado obtido às %, ign: %, horímetro: %',
    --  eventDate, ignitionStatus, horimeter;
    
    -- Verificar se é a primeira informação
    IF previousHorimeter IS NULL THEN
      -- Considera esta a primeira informação do dia
      previousEventDate := eventDate;
      previousIgnitionStatus := ignitionStatus;
      previousHorimeter := horimeter;
      lastHorimeter := horimeter;

      -- RAISE NOTICE 'Primeira informação do dia';
    ELSE
      -- Verificar se o estado de ignição mudou
      -- RAISE NOTICE 'Verificando se o estado de ignição mudou';
      IF previousIgnitionStatus <> ignitionStatus THEN
        -- Conforme o estado da ignição, determinamos 
        -- RAISE NOTICE 'Ignição mudou de estado';
        IF ignitionStatus THEN
          -- Ignição ligada, então começamos a contar o tempo a partir
          -- deste instante
          -- RAISE NOTICE 'Ignição ligou, às %: contando o tempo de uso', eventDate;
          previousEventDate := eventDate;
        ELSE
          -- Ignição desligada, então calculamos o tempo de uso até o
          -- momento
          -- RAISE NOTICE 'Ignição desligou, às %, calculando o tempo de uso', eventDate;
          usageTime := 0;
          IF horimeter > previousHorimeter THEN
            usageTime := horimeter - previousHorimeter;
          END IF;

          -- RAISE NOTICE 'Tempo de uso: % - % = %',
          --   horimeter, previousHorimeter, usageTime;

          -- Determinamos a hora de início e de fim
          currentTime := previousEventDate::time;
          endTime := eventDate::time;

          -- Como o tempo de uso está em minutos, distribuímos o tempo
          -- nos totalizadores por hora
          LOOP
            -- Obtemos a hora sendo totalizada
            hourTotalized := EXTRACT(HOUR FROM currentTime);
            startOfNextHour := DATE_TRUNC('hour', currentTime  + interval '1 hour')::time;

            -- Determinamos quantos minutos faltam para o final da hora
            minutesToNextHour := 60 - EXTRACT(MINUTE FROM currentTime);

            -- RAISE NOTICE 'Hora %, Próx: %, Faltam: %',
            --   hourTotalized, startOfNextHour, minutesToNextHour;

            IF usageTime < minutesToNextHour THEN
              -- Se o tempo de uso for menor que os minutos que faltam
              -- para o final da hora, então o tempo de uso é armazenado
              -- integralmente na hora atual
              usageTimePerHour[hourTotalized] :=
                usageTimePerHour[hourTotalized] + usageTime
              ;
              -- RAISE NOTICE 'Incrementada hora % em %',
              --   hourTotalized, usageTime
              -- ;

              usageTime := 0;
            ELSE
              -- Armazenamos apenas o tempo de uso da hora atual
              usageTimePerHour[hourTotalized] := usageTimePerHour[hourTotalized] +
                minutesToNextHour;
              -- RAISE NOTICE 'Incrementada hora % em %',
              --   hourTotalized, minutesToNextHour
              -- ;
              
              -- Deduzimos o tempo de uso da hora atual
              usageTime := usageTime - minutesToNextHour;
            END IF;

            currentTime := startOfNextHour;

            EXIT WHEN usageTime <= 0;
            CONTINUE WHEN usageTime > 0;
          END LOOP;

          previousHorimeter := horimeter;
        END IF;

        -- Atualizar as informações anteriores
        previousEventDate := eventDate;
        previousIgnitionStatus := ignitionStatus;
      END IF;

      lastHorimeter := horimeter;
    END IF;
  END LOOP;

  IF previousIgnitionStatus THEN
    -- RAISE NOTICE 'Ignição ligada no final do dia, contando o tempo de uso';
    -- Ignição ligada, então calculamos o tempo de uso até o final do
    -- dia, pois não temos mais informações
    usageTime := 0;
    IF lastHorimeter > previousHorimeter THEN
      usageTime := lastHorimeter - previousHorimeter;
    END IF;

    --RAISE NOTICE 'Tempo de uso: % - % = %',
    --  horimeter, previousHorimeter, usageTime;
    -- Determinamos a hora de início e de fim
    currentTime := previousEventDate::time;
    endTime := '23:59:59'::time;
    -- RAISE NOTICE 'Hora de início %, Fim do dia: %', currentTime, endTime;

    -- Como o tempo de uso está em minutos, distribuímos o tempo
    -- nos totalizadores por hora
    LOOP
      -- Obtemos a hora sendo totalizada
      hourTotalized := EXTRACT(HOUR FROM currentTime);
      startOfNextHour := DATE_TRUNC('hour', currentTime  + interval '1 hour')::time;

      -- Determinamos quantos minutos faltam para o final da hora
      minutesToNextHour := 60 - EXTRACT(MINUTE FROM currentTime);

      -- RAISE NOTICE 'Hora %, Próx: %, Faltam: %',
      --   hourTotalized, startOfNextHour, minutesToNextHour;

      IF usageTime < minutesToNextHour THEN
        -- Se o tempo de uso for menor que os minutos que faltam
        -- para o final da hora, então o tempo de uso é armazenado
        -- integralmente na hora atual
        usageTimePerHour[hourTotalized] :=
          usageTimePerHour[hourTotalized] + usageTime
        ;
        -- RAISE NOTICE 'Incrementada hora % em %',
        --   hourTotalized, usageTime
        -- ;

        usageTime := 0;
      ELSE
        -- Armazenamos apenas o tempo de uso da hora atual
        usageTimePerHour[hourTotalized] := usageTimePerHour[hourTotalized] +
          minutesToNextHour;
        -- RAISE NOTICE 'Incrementada hora % em %',
        --   hourTotalized, minutesToNextHour
        -- ;
        
        -- Deduzimos o tempo de uso da hora atual
        usageTime := usageTime - minutesToNextHour;
      END IF;

      currentTime := startOfNextHour;

      EXIT WHEN usageTime <= 0;
      CONTINUE WHEN usageTime > 0;
    END LOOP;
  END IF;

  -- Retornar os totalizadores por hora contidos na matriz
  FOR hourTotalized IN 0..23 LOOP
    hourOfDay := hourTotalized;
    usageTimeInHour := usageTimePerHour[hourTotalized];
    -- RAISE NOTICE 'Hora %: %', hourOfDay, usageTimeInHour;

    RETURN NEXT;
  END LOOP;
  
  RETURN;
END;
$$ LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Função para obter a totalização de horas de uso em um dia
-- ---------------------------------------------------------------------
-- Cria uma função que obtém o acumulado de tempo de uso de um veículo
-- em um dia usando a informação proveniente do horímetro e/ou, quando o
-- horímetro não está disponível, a informação de data/hora dos eventos
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.calculateUsageTimePerHour(FcontractorID integer,
  FuserID integer, FisVehicle boolean, Fid integer, FmainTracker boolean,
  FeventDate date, filterByEntityID integer, belongToAnAssociation boolean,
  FuseHorimeter boolean)
RETURNS TABLE (hourOfDay INT, usageTimeInHour int)
AS $$
DECLARE
  nextEvent  record;
  previousIgnition  boolean;
  begginingAt  timestamp;
  startHorimeter  integer;

  usageTimePerHour  integer[];
  usageTime  integer;

  currentTime  time;
  endTime  time;
  startOfNextHour  time;
  endOfCurrentState  time;
  hourTotalized  integer;
  minutesToNextHour  integer;
BEGIN
  -- RAISE NOTICE 'Obtendo a informação de horímetro do % ID %',
  --   CASE WHEN FisVehicle THEN 'veículo' ELSE 'equipamento' END, Fid;

  -- Inicializa a matriz de totalizadores por hora
  FOR hour IN 0..23 LOOP
    usageTimePerHour[hour] := 0;
  END LOOP;
  previousIgnition := NULL;

  -- Loop pelas informações recuperadas
  FOR nextEvent IN
    WITH stateChanges AS (
      SELECT eventDate,
             horimeter,
             ignitionStatus AS ignition,
             LAG(ignitionStatus) OVER (ORDER BY eventDate) AS prev_ignition_status
        FROM public.positions
       WHERE contractorID = FcontractorID
         AND CASE
               WHEN FisVehicle
                 THEN vehicleID = Fid
                 ELSE equipmentID = Fid
             END
         AND (
               CASE
                 WHEN belongToAnAssociation AND filterByEntityID > 0
                   THEN customerPayerID = filterByEntityID
                 WHEN NOT belongToAnAssociation AND filterByEntityID > 0
                   THEN customerID = filterByEntityID
                 ELSE TRUE
               END
               OR equipmentID IN (
                 SELECT equipmentID
                   FROM erp.authorizedEquipments
                 WHERE userID = FuserID
               )
             )
         AND DATE(eventDate) = FeventDate
       ORDER BY eventDate
    )
    SELECT eventDate,
           horimeter,
           ignition
      FROM (
        SELECT eventDate,
               horimeter,
               ignition,
               CASE
                 WHEN prev_ignition_status IS NULL OR prev_ignition_status != ignition THEN 1
                 ELSE 0
               END AS change
          FROM stateChanges
      ) AS period
    WHERE change = 1
  LOOP
    -- RAISE NOTICE 'Dado obtido às %, ign: %, horímetro: %',
    --   nextEvent.eventDate, nextEvent.ignition, nextEvent.horimeter;

    -- Computamos a duração do estado anterior até o evento atual sempre
    -- que a ignição muda de estado
    IF previousIgnition IS NULL OR previousIgnition <> nextEvent.ignition THEN
      IF nextEvent.ignition THEN
        -- Ignição ligada, então começamos a contar o tempo a partir
        -- deste instante
        -- RAISE NOTICE 'Ignição ligou, às %: contando o tempo de uso', nextEvent.eventDate;
        begginingAt := nextEvent.eventDate;
        startHorimeter := nextEvent.horimeter;
        previousIgnition := nextEvent.ignition;

        CONTINUE;
      ELSE
        -- Ignição desligada, então calculamos o tempo de uso até o
        -- momento
        -- RAISE NOTICE 'Ignição desligou, às %: calculando o tempo de uso', nextEvent.eventDate;
        previousIgnition := nextEvent.ignition;
        IF begginingAt IS NULL THEN
          -- Ignição desligada, mas não temos informação anterior, então
          -- ignoramos
          -- RAISE NOTICE 'Primeira iteração, sem informação anterior';
          CONTINUE;
        END IF;

        currentTime := begginingAt::time;
        endTime := nextEvent.eventDate::time;
        IF FuseHorimeter THEN
          -- Usamos a informação de horímetro para calcular
          -- RAISE NOTICE 'Usando horímetro para calcular o tempo de uso';
          usageTime := 0;
          IF nextEvent.horimeter > startHorimeter THEN
            usageTime := nextEvent.horimeter - startHorimeter;
          END IF;

          LOOP
            -- Obtemos a hora sendo totalizada
            hourTotalized := EXTRACT(HOUR FROM currentTime);
            startOfNextHour := DATE_TRUNC('hour', currentTime  + interval '1 hour')::time;

            -- Determinamos quantos minutos faltam para o final da hora
            minutesToNextHour := 60 - EXTRACT(MINUTE FROM currentTime);

            IF usageTime < minutesToNextHour THEN
              -- Se o tempo de uso for menor que os minutos que faltam
              -- para o final da hora, então o tempo de uso é armazenado
              -- integralmente na hora atual
              usageTimePerHour[hourTotalized] :=
                usageTimePerHour[hourTotalized] + usageTime
              ;
              usageTime := 0;
            ELSE
              -- Armazenamos apenas o tempo de uso da hora atual
              usageTimePerHour[hourTotalized] := usageTimePerHour[hourTotalized] +
                minutesToNextHour;
              
              -- Deduzimos o tempo de uso da hora atual
              usageTime := usageTime - minutesToNextHour;
            END IF;

            currentTime := startOfNextHour;

            EXIT WHEN usageTime <= 0;
            CONTINUE WHEN usageTime > 0;
          END LOOP;

          startHorimeter := nextEvent.horimeter;
        ELSE
          -- Usamos a informação de data/hora dos eventos para calcular
          -- RAISE NOTICE 'Usando a informação de data/hora dos eventos para calcular o tempo de uso';
          endOfCurrentState := nextEvent.eventDate::time;

          LOOP
            -- Obtemos a hora sendo totalizada
            hourTotalized := EXTRACT(HOUR FROM currentTime);
            IF currentTime >= '23:00:00'::time THEN
              startOfNextHour := '23:59:59'::time;
            ELSE
              startOfNextHour := DATE_TRUNC('hour', currentTime  + interval '1 hour')::time;
            END IF;
            -- RAISE NOTICE 'Hora sendo totalizada: %', hourTotalized;

            -- Determinamos quantos minutos faltam para o final da hora
            minutesToNextHour := 60 - EXTRACT(MINUTE FROM currentTime);
            -- RAISE NOTICE 'Minutos até a próxima hora: %', minutesToNextHour;

            -- Determinamos se o final do estado atual ocorre antes do
            -- final da hora que estamos analisando
            IF endOfCurrentState < startOfNextHour OR
              (endOfCurrentState = '23:59:59'::time AND endOfCurrentState <= startOfNextHour) THEN
              -- Se o final do estado atual encerra-se nesta hora, então
              -- o tempo de uso é armazenado integralmente na hora atual
              -- RAISE NOTICE 'O final do estado atual encerra-se nesta hora às %', endOfCurrentState;
              usageTime := EXTRACT(minute FROM endOfCurrentState) - EXTRACT(minute FROM currentTime);
              usageTimePerHour[hourTotalized] :=
                usageTimePerHour[hourTotalized] + usageTime
              ;
              -- RAISE NOTICE 'Incrementada hora % em %',
              --   hourTotalized, usageTime
              -- ;
            ELSE
              -- Armazenamos apenas o tempo de uso que estejam inseridos
              -- na hora atual
              -- RAISE NOTICE 'O final do estado atual encerra-se numa hora posterior às %', startOfNextHour;
              usageTimePerHour[hourTotalized] :=
                usageTimePerHour[hourTotalized] +
                minutesToNextHour
              ;
              -- RAISE NOTICE 'Incrementada hora % em %',
              --   hourTotalized, minutesToNextHour
              -- ;
            END IF;

            currentTime := startOfNextHour;

            EXIT WHEN endOfCurrentState < startOfNextHour OR (endOfCurrentState = '23:59:59'::time AND endOfCurrentState <= startOfNextHour);
            CONTINUE WHEN endOfCurrentState >= startOfNextHour;
          END LOOP;
        END IF;
      END IF;
    END IF;
  END LOOP;

  IF previousIgnition THEN
    -- Ignição permanceu ligada ao final, então calculamos o tempo de
    -- uso até o final do período solicitado usando a data/hora do
    -- evento para calcular
    -- RAISE NOTICE 'Usando a informação de data/hora dos eventos para calcular o tempo de uso';
    endOfCurrentState := '23:59:59'::time;
    currentTime := begginingAt::time;

    LOOP
      -- Obtemos a hora sendo totalizada
      hourTotalized := EXTRACT(HOUR FROM currentTime);
      IF currentTime >= '23:00:00'::time THEN
        startOfNextHour := '23:59:59'::time;
      ELSE
        startOfNextHour := DATE_TRUNC('hour', currentTime  + interval '1 hour')::time;
      END IF;
      -- RAISE NOTICE 'Hora sendo totalizada: %', hourTotalized;

      -- Determinamos quantos minutos faltam para o final da hora
      minutesToNextHour := 60 - EXTRACT(MINUTE FROM currentTime);
      -- RAISE NOTICE 'Minutos até a próxima hora: %', minutesToNextHour;

      -- Determinamos se o final do estado atual ocorre antes do
      -- final da hora que estamos analisando
      IF endOfCurrentState < startOfNextHour OR
         (endOfCurrentState = '23:59:59'::time AND endOfCurrentState <= startOfNextHour) THEN
        -- Se o final do estado atual encerra-se nesta hora, então
        -- o tempo de uso é armazenado integralmente na hora atual
        usageTime := EXTRACT(minute FROM endOfCurrentState) - EXTRACT(minute FROM currentTime);
        usageTimePerHour[hourTotalized] :=
          usageTimePerHour[hourTotalized] + usageTime
        ;
        -- RAISE NOTICE 'Incrementada hora % em %',
        --   hourTotalized, usageTime
        -- ;
      ELSE
        -- Armazenamos apenas o tempo de uso que estejam inseridos
        -- na hora atual
        usageTimePerHour[hourTotalized] :=
          usageTimePerHour[hourTotalized] +
          minutesToNextHour
        ;
        -- RAISE NOTICE 'Incrementada hora % em %',
        --   hourTotalized, minutesToNextHour
        -- ;
      END IF;

      currentTime := startOfNextHour;

      EXIT WHEN endOfCurrentState < startOfNextHour OR (endOfCurrentState = '23:59:59'::time AND endOfCurrentState <= startOfNextHour);
      CONTINUE WHEN endOfCurrentState >= startOfNextHour;
    END LOOP;
  END IF;

  -- Retornar os totalizadores por hora contidos na matriz
  FOR hourTotalized IN 0..23 LOOP
    hourOfDay := hourTotalized;
    usageTimeInHour := usageTimePerHour[hourTotalized];

    RETURN NEXT;
  END LOOP;
  
  RETURN;
END;
$$ LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Função para obter a totalização de horas de uso por dia
-- ---------------------------------------------------------------------
-- Cria uma função que obtém o acumulado de tempo de uso de um veículo
-- por dia usando a informação proveniente do horímetro e/ou, quando o
-- horímetro não está disponível, a informação de data/hora dos eventos
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.calculateUsageTimePerDay(FcontractorID integer,
  FuserID integer, FisVehicle boolean, Fid integer, FmainTracker boolean,
  FstartDate timestamp, FendDate timestamp, filterByEntityID integer,
  belongToAnAssociation boolean, FuseHorimeter boolean)
RETURNS TABLE (dateOfDay date, usageTimeInHour int)
AS $$
DECLARE
  nextEvent  record;
  previousIgnition  boolean;
  begginingAt  timestamp;
  startHorimeter  integer;

  dates date[];

  usageTimePerDay  integer[];
  usageTime  integer;

  currentTime  timestamp;
  lastDay  date;
  startOfNextDay  timestamp;
  endOfCurrentState  timestamp;
  dayTotalized  date;
  minutesToNextDay  integer;
BEGIN
  -- RAISE NOTICE 'Obtendo a informação de horímetro do % ID %',
  --   CASE WHEN FisVehicle THEN 'veículo' ELSE 'equipamento' END, Fid;

  -- Inicializa os totalizadores por dia
  previousIgnition := NULL;
  lastDay := FstartDate::date;
  SELECT ARRAY(
    SELECT generate_series(FstartDate::date, FendDate::Date, interval '1 day')::date
  ) INTO dates;
  FOR i IN 1..array_length(dates, 1) LOOP
    usageTimePerDay[i] := 0;
  END LOOP;

  -- Loop pelas informações recuperadas
  FOR nextEvent IN
    WITH stateChanges AS (
      SELECT eventDate,
             horimeter,
             ignitionStatus AS ignition,
             LAG(ignitionStatus) OVER (ORDER BY eventDate) AS prev_ignition_status
        FROM public.positions
       WHERE contractorID = FcontractorID
         AND CASE
               WHEN FisVehicle
                 THEN vehicleID = Fid
                 ELSE equipmentID = Fid
             END
         AND (
               CASE
                 WHEN belongToAnAssociation AND filterByEntityID > 0
                   THEN customerPayerID = filterByEntityID
                 WHEN NOT belongToAnAssociation AND filterByEntityID > 0
                   THEN customerID = filterByEntityID
                 ELSE TRUE
               END
               OR equipmentID IN (
                 SELECT equipmentID
                   FROM erp.authorizedEquipments
                 WHERE userID = FuserID
               )
             )
         AND eventDate BETWEEN FstartDate AND FendDate
       ORDER BY eventDate
    )
    SELECT eventDate,
           horimeter,
           ignition
      FROM (
        SELECT eventDate,
               horimeter,
               ignition,
               CASE
                 WHEN prev_ignition_status IS NULL OR prev_ignition_status != ignition THEN 1
                 ELSE 0
               END AS change
          FROM stateChanges
      ) AS period
    WHERE change = 1
  LOOP
    -- RAISE NOTICE 'Dado obtido às %, ign: %, horímetro: %',
    --   nextEvent.eventDate, nextEvent.ignition, nextEvent.horimeter;

    -- Computamos a duração do estado anterior até o evento atual sempre
    -- que a ignição muda de estado
    IF previousIgnition IS NULL OR previousIgnition <> nextEvent.ignition THEN
      IF nextEvent.ignition THEN
        -- Ignição ligada, então começamos a contar o tempo a partir
        -- deste instante
        -- RAISE NOTICE 'Ignição ligou, às %: contando o tempo de uso', nextEvent.eventDate;
        begginingAt := nextEvent.eventDate;
        startHorimeter := nextEvent.horimeter;
        previousIgnition := nextEvent.ignition;

        CONTINUE;
      ELSE
        -- Ignição desligada, então calculamos o tempo de uso até o
        -- momento
        -- RAISE NOTICE 'Ignição desligou, às %: calculando o tempo de uso', nextEvent.eventDate;
        previousIgnition := nextEvent.ignition;
        IF begginingAt IS NULL THEN
          -- Ignição desligada, mas não temos informação anterior, então
          -- ignoramos
          -- RAISE NOTICE 'Primeira iteração, sem informação anterior';
          CONTINUE;
        END IF;

        endOfCurrentState := nextEvent.eventDate;
        currentTime := begginingAt;
        -- RAISE NOTICE 'Periodo sendo totalizado: % até %', begginingAt, endOfCurrentState;

        IF FuseHorimeter THEN
          -- Usamos a informação de horímetro para calcular
          -- RAISE NOTICE 'Usando horímetro para calcular o tempo de uso';
          usageTime := 0;
          IF nextEvent.horimeter > startHorimeter THEN
            usageTime := nextEvent.horimeter - startHorimeter;
          END IF;

          LOOP
            -- Obtemos o dia sendo totalizado
            dayTotalized := currentTime::date;
            startOfNextDay := (currentTime::date + interval '1 day')::timestamp;
            -- RAISE NOTICE 'Dia sendo totalizado: %', dayTotalized;

            -- Determinamos quantos minutos faltam para o final do dia
            IF FendDate::date = dayTotalized THEN
              -- Se o dia sendo totalizado é o último dia do período
              -- solicitado, então o tempo de uso é limitado até o final
              -- do período
              minutesToNextDay := (EXTRACT(EPOCH FROM (date_trunc('minute', FendDate) - date_trunc('minute', currentTime))) / 60)::integer;
            ELSE
              minutesToNextDay := (EXTRACT(EPOCH FROM (date_trunc('day', currentTime) + INTERVAL '1 day' - date_trunc('minute', currentTime))) / 60)::integer;
            END IF;
            -- RAISE NOTICE 'Minutos até o próximo dia: %', minutesToNextDay;

            -- RAISE NOTICE 'Início do próximo dia às %', startOfNextDay;
            -- RAISE NOTICE 'Fim do estado às %', endOfCurrentState;
            IF endOfCurrentState < startOfNextDay THEN
              -- Se o final do estado atual encerra-se neste dia, então
              -- o tempo de uso é armazenado integralmente no dia atual
              -- RAISE NOTICE 'O final do estado atual encerra-se neste dia às %', endOfCurrentState;
              usageTime := (EXTRACT(EPOCH FROM date_trunc('minute', endOfCurrentState) - date_trunc('minute', currentTime)) / 60)::integer;
              FOR i IN 1..array_length(dates, 1) LOOP
                IF dates[i] = dayTotalized THEN
                  usageTimePerDay[i] := usageTimePerDay[i] + usageTime;
                  -- RAISE NOTICE 'Armazenanamos integralmente % no dia atual', usageTime;
                END IF;
              END LOOP;
              
              -- Zeramos o tempo de uso do dia atual
              usageTime := 0;
            ELSE
              -- Armazenamos apenas o tempo de uso do dia atual
              -- RAISE NOTICE 'Armazenamos apenas o tempo de uso do dia atual';
              FOR i IN 1..array_length(dates, 1) LOOP
                IF dates[i] = dayTotalized THEN
                  usageTimePerDay[i] := usageTimePerDay[i] + minutesToNextDay;
                  -- RAISE NOTICE 'Armazenanamos apenas % no dia atual', usageTime;
                END IF;
              END LOOP;
              
              -- Deduzimos o tempo de uso do dia atual
              usageTime := usageTime - minutesToNextDay;
            END IF;

            -- RAISE NOTICE 'Início do próximo dia às %', startOfNextDay;
            currentTime := startOfNextDay;

            EXIT WHEN usageTime <= 0;
            CONTINUE WHEN usageTime > 0;
          END LOOP;

          startHorimeter := nextEvent.horimeter;
        ELSE
          -- Usamos a informação de data/hora dos eventos para calcular
          -- RAISE NOTICE 'Usando a informação de data/hora dos eventos para calcular o tempo de uso';
          LOOP
            -- Obtemos o dia sendo totalizado
            dayTotalized := currentTime::date;
            startOfNextDay := (currentTime::date + interval '1 day')::timestamp;
            -- RAISE NOTICE 'Dia sendo totalizado: %', dayTotalized;
            -- RAISE NOTICE 'Início do próximo dia às %', startOfNextDay;

            -- Determinamos quantos minutos faltam para o final do dia
            IF FendDate::date = dayTotalized THEN
              -- Se o dia sendo totalizado é o último dia do período
              -- solicitado, então o tempo de uso é limitado até o final
              -- do período
              minutesToNextDay := (EXTRACT(EPOCH FROM (date_trunc('minute', FendDate) - date_trunc('minute', currentTime))) / 60)::integer;
            ELSE
              minutesToNextDay := (EXTRACT(EPOCH FROM (date_trunc('day', currentTime) + INTERVAL '1 day' - date_trunc('minute', currentTime))) / 60)::integer;
            END IF;
            -- RAISE NOTICE 'Minutos até o próximo dia: %', minutesToNextDay;

            -- Determinamos se o final do estado atual ocorre antes do
            -- final do dia que estamos analisando
            IF endOfCurrentState < startOfNextDay THEN
              -- Se o final do estado atual encerra-se neste dia, então
              -- o tempo de uso é armazenado integralmente no dia atual
              -- RAISE NOTICE 'O final do estado atual encerra-se neste dia às %', endOfCurrentState;
              usageTime := (EXTRACT(EPOCH FROM date_trunc('minute', endOfCurrentState) - date_trunc('minute', currentTime)) / 60)::integer;
              FOR i IN 1..array_length(dates, 1) LOOP
                IF dates[i] = dayTotalized THEN
                  usageTimePerDay[i] := usageTimePerDay[i] + usageTime;
                  -- RAISE NOTICE 'Armazenanamos integralmente % no dia atual', usageTime;
                END IF;
              END LOOP;
            ELSE
              -- Armazenamos apenas o tempo de uso que estejam inseridos
              -- no dia atual
              -- RAISE NOTICE 'O final do estado atual encerra-se num dia posterior às %', startOfNextDay;
              FOR i IN 1..array_length(dates, 1) LOOP
                IF dates[i] = dayTotalized THEN
                  usageTimePerDay[i] := usageTimePerDay[i] + minutesToNextDay;
                  -- RAISE NOTICE 'Armazenanamos apenas % no dia atual', minutesToNextDay;
                END IF;
              END LOOP;
            END IF;

            currentTime := startOfNextDay;

            EXIT WHEN endOfCurrentState < startOfNextDay;
            CONTINUE WHEN endOfCurrentState >= startOfNextDay;
          END LOOP;
        END IF;
      END IF;
    END IF;
  END LOOP;

  IF previousIgnition THEN
    -- Ignição permanceu ligada ao final, então calculamos o tempo de
    -- uso até o final do período solicitado usando a data/hora do
    -- evento para calcular
    -- RAISE NOTICE 'Usando a informação de data/hora dos eventos para calcular o tempo de uso';
    endOfCurrentState := FendDate;
    currentTime := nextEvent.eventDate;
    -- RAISE NOTICE 'Ignição permaneceu ligada ao final do período solicitado';
    -- RAISE NOTICE 'Calculando o tempo de uso de % até %', currentTime, endOfCurrentState;

    LOOP
      -- Obtemos o dia sendo totalizado
      dayTotalized := currentTime::date;
      startOfNextDay := (currentTime::date + interval '1 day')::timestamp;
      -- RAISE NOTICE 'Dia sendo totalizado: %', dayTotalized;
      -- RAISE NOTICE 'Início do próximo dia às %', startOfNextDay;

      -- Determinamos quantos minutos faltam para o final da hora
      IF FendDate::date = dayTotalized THEN
        -- Se o dia sendo totalizado é o último dia do período
        -- solicitado, então o tempo de uso é limitado até o final
        -- do período
        minutesToNextDay := (EXTRACT(EPOCH FROM (date_trunc('minute', FendDate) - date_trunc('minute', currentTime))) / 60)::integer;
      ELSE
        minutesToNextDay := (EXTRACT(EPOCH FROM (date_trunc('day', currentTime) + INTERVAL '1 day' - date_trunc('minute', currentTime))) / 60)::integer;
      END IF;
      -- RAISE NOTICE 'Minutos até o próximo dia: %', minutesToNextDay;

      -- Determinamos se o final do estado atual ocorre antes do
      -- final do dia que estamos analisando
      IF endOfCurrentState < startOfNextDay THEN
        -- Se o final do estado atual encerra-se neste dia, então
        -- o tempo de uso é armazenado integralmente no dia atual
        -- RAISE NOTICE 'O final do estado atual encerra-se neste dia às %', endOfCurrentState;
        usageTime := (EXTRACT(EPOCH FROM date_trunc('minute', endOfCurrentState) - date_trunc('minute', currentTime)) / 60)::integer;
        FOR i IN 1..array_length(dates, 1) LOOP
          IF dates[i] = dayTotalized THEN
            usageTimePerDay[i] := usageTimePerDay[i] + usageTime;
            -- RAISE NOTICE 'Armazenanamos integralmente % no dia atual', usageTime;
          END IF;
        END LOOP;
      ELSE
        -- Armazenamos apenas o tempo de uso que estejam inseridos
        -- no dia atual
        -- RAISE NOTICE 'O final do estado atual encerra-se numa hora posterior às %', startOfNextDay;
        FOR i IN 1..array_length(dates, 1) LOOP
          IF dates[i] = dayTotalized THEN
            usageTimePerDay[i] := usageTimePerDay[i] + minutesToNextDay;
            -- RAISE NOTICE 'Armazenanamos apenas % no dia atual', minutesToNextDay;
          END IF;
        END LOOP;
      END IF;

      currentTime := startOfNextDay;

      EXIT WHEN endOfCurrentState < startOfNextDay;
      CONTINUE WHEN endOfCurrentState >= startOfNextDay;
    END LOOP;
  END IF;

  -- Retornamos o totalizador acumulado por dia
  FOR i IN 1..array_length(dates, 1) LOOP
    dateOfDay := dates[i];
    usageTimeInHour := usageTimePerDay[i];

    RETURN NEXT;
  END LOOP;
  
  RETURN;
END;
$$ LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Função para obter o período em que um veículo permaneceu com a
-- ignição ligada e desligada
-- ---------------------------------------------------------------------
-- Cria uma função que obtém o acumulado de tempo com a ignição ligada e
-- desligada de um veículo em um período.
-- ---------------------------------------------------------------------
CREATE TYPE ignitionTotalization AS
(
  begginingAt       timestamp,  -- Data de início do período computado
  finishingAt       timestamp,  -- Data de término do período computado
  ignition          boolean,    -- O estado da ignição
  startOdometer     integer,    -- O odômetro no início do período
  endOdometer       integer,    -- O odômetro no final do período
  duration          interval,   -- A duração do estado
  travelledDistance integer,    -- A distância percorrida em km
  startAddress      text,       -- O endereço do terminal no momento do evento
  endAddress        text        -- O endereço do terminal no momento do evento
);

CREATE OR REPLACE FUNCTION totalizeIgnitionTime (FcontractorID  integer,
  FuserID integer, FisVehicle boolean, Fid integer, FmainTracker boolean,
  FstartDate timestamp, FendDate timestamp, filterByEntityID integer,
  belongToAnAssociation boolean)
RETURNS SETOF public.ignitionTotalization AS
$$
DECLARE
  totalization  public.ignitionTotalization;
  nextEvent  record;
  duration  interval;
  valueInDays  integer;
  valueInHours  integer;
BEGIN
  FOR nextEvent IN
    WITH stateChanges AS (
      SELECT eventDate,
             ignitionStatus AS ignition,
             odometer,
             address,
             LAG(ignitionStatus) OVER (ORDER BY eventDate) AS prev_ignition_status
        FROM public.positions
       WHERE contractorID = FcontractorID
         AND CASE
               WHEN FisVehicle
                 THEN vehicleID = Fid
                 ELSE equipmentID = Fid
             END
         AND (
               CASE
                 WHEN belongToAnAssociation AND filterByEntityID > 0
                   THEN customerPayerID = filterByEntityID
                 WHEN NOT belongToAnAssociation AND filterByEntityID > 0
                   THEN customerID = filterByEntityID
                 ELSE TRUE
               END
               OR equipmentID IN (
                 SELECT equipmentID
                   FROM erp.authorizedEquipments
                 WHERE userID = FuserID
               )
             )
         AND eventDate BETWEEN FstartDate AND FendDate
       ORDER BY eventDate
    )
    SELECT eventDate,
           ignition,
           odometer,
           address
      FROM (
        SELECT eventDate,
               ignition,
               odometer,
               address,
               CASE
                 WHEN prev_ignition_status IS NULL OR prev_ignition_status != ignition THEN 1
                 ELSE 0
               END AS change
          FROM stateChanges
      ) AS period
    WHERE change = 1
  LOOP
    IF totalization.begginingAt IS NULL THEN
      -- Iniciamos um novo registro com o estado atual
      totalization.begginingAt := nextEvent.eventDate;
      totalization.ignition := nextEvent.ignition;
      totalization.startAddress := nextEvent.address;
      totalization.startOdometer := nextEvent.odometer;
    ELSE
      -- Computamos a duração do estado anterior até o evento atual
      totalization.finishingAt := nextEvent.eventDate;
      totalization.endAddress := nextEvent.address;
      totalization.endOdometer := nextEvent.odometer;

      -- Calculamos a duração do estado em horas, minutos e segundos
      duration := totalization.finishingAt - totalization.begginingAt;
      valueInDays := EXTRACT(DAY FROM duration);
      valueInHours := EXTRACT(HOUR FROM duration);
      totalization.duration := ((valueInDays * 24) + valueInHours) || ':' || to_char(duration, 'MI:SS');
      totalization.travelledDistance := ABS(totalization.endOdometer - totalization.startOdometer);

      RETURN NEXT totalization;

      -- Iniciamos um novo registro com o estado atual
      totalization.begginingAt := nextEvent.eventDate;
      totalization.ignition := nextEvent.ignition;
      totalization.startAddress := nextEvent.address;
      totalization.startOdometer := nextEvent.odometer;
    END IF;
  END LOOP;
END;
$$ LANGUAGE plpgsql;

-- SELECT begginingat, finishingat, CASE WHEN ignition THEN 'Ligada' ELSE 'Desligada' END AS ignition, duration, startAddress, endAddress, startOdometer, endOdometer, travelledDistance FROM totalizeIgnitionTime(1, 6, true, 24, true, '2024-03-30 08:00:00', '2024-03-30 10:59:59', NULL, false);

-- ---------------------------------------------------------------------
-- Função para obter a totalização de trecho percorrido em um dia
-- ---------------------------------------------------------------------
-- Cria uma função que obtém o acumulado de trecho percorrido de um
-- veículo em um dia usando a informação proveniente do odômetro
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.calculateTravelledDistancePerHour(
  FcontractorID integer, FuserID integer, FisVehicle boolean,
  Fid integer, FmainTracker boolean, FeventDate date,
  filterByEntityID integer, belongToAnAssociation boolean)
RETURNS TABLE (hourOfDay INT, travelledDistanceInHour int)
AS $$
DECLARE
  eventDate  timestamp;
  lastOdometer  integer;
  odometer  integer;
  travelledDistance  integer;

  eventTime  time;
  travelledDistancePerHour  integer[];
  hourTotalized  integer;
BEGIN
  -- RAISE NOTICE 'Obtendo a informação de horímetro do % ID %',
  --   CASE WHEN FisVehicle THEN 'veículo' ELSE 'equipamento' END, Fid;

  -- Inicializa a matriz de totalizadores por hora
  FOR hour IN 0..23 LOOP
    travelledDistancePerHour[hour] := 0;
  END LOOP;

  -- Loop pelas informações recuperadas
  FOR eventDate, lastOdometer, odometer, travelledDistance IN
    WITH stateChanges AS (
      SELECT position.eventDate,
             position.odometer,
             LAG(position.odometer) OVER (ORDER BY position.eventDate) AS prev_odometer
        FROM public.positions AS position
       WHERE position.contractorID = FcontractorID
         AND CASE
               WHEN FisVehicle
                 THEN position.vehicleID = Fid
                 ELSE position.equipmentID = Fid
             END
         AND (
               CASE
                 WHEN belongToAnAssociation AND filterByEntityID > 0
                   THEN position.customerPayerID = filterByEntityID
                 WHEN NOT belongToAnAssociation AND filterByEntityID > 0
                   THEN position.customerID = filterByEntityID
                 ELSE TRUE
               END
               OR equipmentID IN (
                 SELECT equipmentID
                   FROM erp.authorizedEquipments
                 WHERE userID = FuserID
               )
             )
         AND DATE(position.eventDate) = FeventDate
       ORDER BY position.eventDate
    )
    SELECT period.eventDate,
           period.lastOdometer,
           period.odometer,
           period.travelledDistance
      FROM (
        SELECT stateChanges.eventDate,
               stateChanges.prev_odometer AS lastOdometer,
               stateChanges.odometer,
               CASE
                 WHEN stateChanges.prev_odometer IS NULL THEN 0
                 ELSE stateChanges.odometer - stateChanges.prev_odometer
               END AS travelledDistance,
               CASE
                 WHEN stateChanges.prev_odometer IS NULL OR stateChanges.prev_odometer != stateChanges.odometer THEN 1
                 ELSE 0
               END AS change
          FROM stateChanges
      ) AS period
    WHERE change = 1
  LOOP
    -- RAISE NOTICE 'Dado obtido às %, odômetro inicial: %, final: %, percorrido: %',
    --   eventDate, lastOdometer, odometer, travelledDistance;

    -- Estamos totalizando as distâncias percorridas por hora, então
    -- usamos a data do evento para determinar a hora
    eventTime := eventDate::time;
    hourTotalized := EXTRACT(HOUR FROM eventTime);
    -- RAISE NOTICE 'Hora totalizada %', hourTotalized;
    travelledDistancePerHour[hourTotalized] :=
      travelledDistancePerHour[hourTotalized] + travelledDistance
    ;
  END LOOP;

  -- Retornar os totalizadores por hora contidos na matriz
  FOR hourTotalized IN 0..23 LOOP
    hourOfDay := hourTotalized;
    travelledDistanceInHour := travelledDistancePerHour[hourTotalized];
    -- RAISE NOTICE 'Hora %: %', hourOfDay, travelledDistanceInHour;

    RETURN NEXT;
  END LOOP;
  
  RETURN;
END;
$$ LANGUAGE plpgsql;

-- SELECT * FROM calculateTravelledDistancePerHour(1, 6, true, 24, true, '2024-03-30 08:00:00', '2024-03-30 10:59:59', NULL, false);

-- ---------------------------------------------------------------------
-- Função para obter o estado do terminal através do número de série do
-- terminal, do protocolo e da data do último evento (se disponível).
-- ---------------------------------------------------------------------
-- Cria uma função que obtém o estado do equipamento no instante
-- imediatamente anterior ao informado e/ou o último estado registrado
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.getLastDeviceState(
  FterminalID varchar(20),
  FprotocolID int,
  FeventTime timestamp
) RETURNS TABLE(
  eventdate timestamp,
  ignition boolean,
  blocking boolean,
  siren boolean,
  emergency boolean,
  charging boolean,
  power numeric(4,2),
  battery numeric(4,2),
  gsmSignalStrength int,
  inputs boolean[],
  outputs boolean[]
) AS $$
DECLARE
  FequipmentID int;
BEGIN
  SELECT E.equipmentID
    INTO FequipmentID
    FROM erp.equipments AS E
  INNER JOIN erp.equipmentModels AS M USING (equipmentModelID)
  WHERE LPAD(E.serialNumber, M.serialNumberSize, '0') = LPAD(FterminalID, M.serialNumberSize, '0')
      OR (
          (M.reducedNumberSize > 0) AND (CASE WHEN FprotocolID IS NULL THEN true ELSE M.protocolID = FprotocolID END) AND
          (
            (LENGTH(FterminalID) = M.reducedNumberSize) AND
            (E.serialNumber ILIKE '%' || FterminalID)
          ) OR (
            (LENGTH(FterminalID) = M.serialNumberSize) AND
            (FterminalID ILIKE '%' || E.serialNumber)
          )
        )
  ORDER BY E.systemDate DESC LIMIT 1;
  IF FOUND THEN
    IF FeventTime IS NULL THEN
      FeventTime := '9999-12-31 23:59:59'::TIMESTAMP;
    END IF;
    
    RETURN QUERY
      SELECT P.eventDate,
             P.ignitionStatus AS ignition,
             P.blockStatus AS blocking,
             P.sirenStatus AS siren,
             P.emergencyStatus AS emergency,
             P.charge AS charging,
             P.powerVoltage AS power,
             P.batteryVoltage AS battery,
             P.gsmSignalStrength,
             P.inputs,
             P.outputs
        FROM public.positions AS P
       WHERE P.equipmentID = FequipmentID
         AND P.eventDate < FeventTime
       ORDER BY P.eventDate DESC
      LIMIT 1;
  END IF;
END;
$$ LANGUAGE plpgsql;

-- SELECT * FROM public.getLastDeviceState('354522183823645', 2, '2019-12-31 23:59:59');

-- ---------------------------------------------------------------------
-- Função para atualizar se o equipamento está online ou offline
-- ---------------------------------------------------------------------
-- Cria uma função que atualiza o estado de online/offline de um
-- equipamento baseado no número de série do terminal, do protocolo e
-- do estado de online, bem como a data do último evento.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.updateEquipmentOnlineState(
  FterminalID varchar(20),
  FprotocolID int,
  Fonline boolean,
  FlastCommunication timestamp
) RETURNS void AS $$
DECLARE
  FequipmentID int;
BEGIN
  SELECT E.equipmentID
    INTO FequipmentID
    FROM erp.equipments AS E
  INNER JOIN erp.equipmentModels AS M USING (equipmentModelID)
  WHERE LPAD(E.serialNumber, M.serialNumberSize, '0') = LPAD(FterminalID, M.serialNumberSize, '0')
      OR (
          (M.reducedNumberSize > 0) AND (CASE WHEN FprotocolID IS NULL THEN true ELSE M.protocolID = FprotocolID END) AND
          (
            (LENGTH(FterminalID) = M.reducedNumberSize) AND
            (E.serialNumber ILIKE '%' || FterminalID)
          ) OR (
            (LENGTH(FterminalID) = M.serialNumberSize) AND
            (FterminalID ILIKE '%' || E.serialNumber)
          )
        )
  ORDER BY E.systemDate DESC LIMIT 1;
  IF FOUND THEN
    IF Fonline THEN
      UPDATE erp.equipments
         SET online = true,
             lastCommunication = FlastCommunication
       WHERE equipmentID = FequipmentID;
    ELSE
      UPDATE erp.equipments
         SET online = false
       WHERE equipmentID = FequipmentID;
    END IF;
  END IF;
END;
$$ LANGUAGE plpgsql;
