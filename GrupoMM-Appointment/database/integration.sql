-- ERP de Rastreamento
-- Versão 1.0
--

-- =====================================================================
-- Criação das tabelas de integração com o sistema STC
-- =====================================================================

CREATE SCHEMA stc AUTHORIZATION admin;

-- ---------------------------------------------------------------------
-- Cities
-- ---------------------------------------------------------------------
-- As cidades com o respectivo código IBGE
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.cities (
  contractorID  integer        -- ID do contratante
                NOT NULL,
  cityID        integer        -- A ID da cidade no sistema STC
                NOT NULL,
  name          varchar(50)    -- O nome da cidade
                NOT NULL,
  state         char(2)        -- O estado (UF) onde encontra-se a cidade
                NOT NULL,
  ibgeCode      integer        -- O código IBGE da cidade
                NOT NULL
                DEFAULT 0,
  PRIMARY KEY (contractorID, cityID)
);

-- Gatilho para lidar com o código do IBGE
CREATE OR REPLACE FUNCTION stc.cityTransaction()
RETURNS trigger AS $BODY$
DECLARE
  ibgeCode     integer;
BEGIN
  -- Faz a atualização do código IBGE na inserção de novos valores. Faz
  -- uso da variável especial TG_OP para verificar a operação executada
  -- e de TG_WHEN para determinar o instante em que isto ocorre.
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica o código IBGE da cidade e associa ao novo registro
      SELECT C.ibgecode INTO ibgecode
        FROM erp.cities AS C
       WHERE C.state = NEW.state
         AND upper(public.unaccented(C.name)) = upper(NEW.name);

      IF FOUND THEN
        NEW.ibgecode = ibgecode;
      END IF;
    END IF;

    -- Retornamos a nova cidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Retornamos a nova entidade
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER citiesTransactionTriggerBefore
  BEFORE INSERT ON stc.cities
  FOR EACH ROW EXECUTE PROCEDURE stc.cityTransaction();

-- ---------------------------------------------------------------------
-- Customers
-- ---------------------------------------------------------------------
-- Informações dos clientes cadastrados no sistema STC
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.customers (
  contractorID            integer        -- ID do contratante
                          NOT NULL,
  clientID                integer,       -- Número de identificação do
                                         -- cliente no sistema STC
  customerID              integer,       -- Número de identificação do
                                         -- cliente no sistema ERP
  subsidiaryID            integer,       -- Número de identificação da
                                         -- unidade/filial no sistema ERP
  nationalRegister        varchar(18)    -- CPF ou CNPJ
                          NOT NULL
                          DEFAULT '00.000.000/0000-00',
  name                    varchar(80)    -- Nome do cliente
                          NOT NULL,
  email                   varchar(40)    -- Email
                          NOT NULL
                          DEFAULT '',
  status                  boolean        -- O indicativo de que o cliente
                          DEFAULT true,  -- está ativo ou não
  postalCode              char(9)        -- O código postal (CEP)
                          NOT NULL,
  cityID                  integer,       -- Código da cidade
  entityTypeID            integer        -- O ID do tipo de entidade legal
                          NOT NULL,
  regionalDocumentNumber  varchar(20)    -- Número do documento
                          NOT NULL       -- (Ex: Inscrição estadual ou RG)
                          DEFAULT '',
  address                 varchar(70)    -- O endereço
                          NOT NULL
                          DEFAULT '',
  district                varchar(30)    -- O bairro
                          NOT NULL
                          DEFAULT '',
  complement              varchar(30)    -- O complemento do endereço
                          NOT NULL
                          DEFAULT '',
  info                    text           -- Descrição do cliente
                          NOT NULL
                          DEFAULT '',
  login                   varchar(50)    -- O login de acesso do usuário
                          NOT NULL,      -- no sistema STC
  password                varchar(50)    -- A senha de acesso do usuário
                          NOT NULL,      -- no sistema STC
  getPositions            boolean        -- O indicativo de que devemos
                          DEFAULT false, -- obter (ou não) os dados de
                                         -- posicionamento dos veículos
  createdAt               timestamp      -- A data de criação do cliente
                          NOT NULL
                          DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (contractorID, clientID),
  FOREIGN KEY (entityTypeID)
    REFERENCES erp.entitiesTypes(entityTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID, cityID)
    REFERENCES stc.cities(contractorID, cityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- Gatilho para lidar com a associação do cliente do sistema STC com o
-- cliente interno
CREATE OR REPLACE FUNCTION stc.customerTransaction()
RETURNS trigger AS $BODY$
DECLARE
  customerID  integer;
BEGIN
  -- Faz a atualização do código do cliente na inserção de novos
  -- valores caso o CNPJ ou CPF coincidam com o CNPJ/CPF de um cliente
  -- interno. Faz uso da variável especial TG_OP para verificar a
  -- operação executada e de TG_WHEN para determinar o instante em que
  -- isto ocorre.
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica o código IBGE da cidade e associa ao novo registro
      SELECT C.entityID INTO customerID
        FROM erp.entities AS C
       INNER JOIN erp.subsidiaries AS S USING (entityID)
       WHERE C.customer = true
         AND C.contractorID = NEW.contractorID
         AND trim(S.nationalRegister) = trim(NEW.nationalRegister)
       LIMIT 1;

      IF FOUND THEN
        NEW.customerID = customerID;
      END IF;
    END IF;

    -- Retornamos a nova cidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica o código IBGE da cidade e associa ao novo registro
      SELECT C.entityID INTO customerID
        FROM erp.entities AS C
       INNER JOIN erp.subsidiaries AS S USING (entityID)
       WHERE C.customer = true
         AND C.contractorID = OLD.contractorID
         AND trim(S.nationalRegister) = trim(OLD.nationalRegister)
       LIMIT 1;

      IF FOUND THEN
        NEW.customerID = customerID;
      END IF;
    END IF;

    -- Retornamos a nova cidade
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER customerTransactionTriggerBefore
  BEFORE INSERT OR UPDATE ON stc.customers
  FOR EACH ROW EXECUTE PROCEDURE stc.customerTransaction();


-- ---------------------------------------------------------------------
-- Tipos de Veículos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.vehicleTypes (
  contractorID    integer,      -- Número de identificação do contratante
                                -- no sistema ERP
  vehicleTypeID   integer,      -- ID do tipo de veículo
  name            varchar(50)   -- Nome do tipo de veículo
                  NOT NULL,
  PRIMARY KEY (contractorID, vehicleTypeID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Marcas de Veículos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.vehicleBrands (
  contractorID    integer,      -- Número de identificação do contratante
                                -- no sistema ERP
  vehicleBrandID  integer,      -- ID da marca de veículo
  name            varchar(50)   -- Nome da marca de veículo
                  NOT NULL,
  PRIMARY KEY (contractorID, vehicleBrandID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Modelos de Veículos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.vehicleModels (
  contractorID    integer,      -- Número de identificação do contratante
                                -- no sistema ERP
  vehicleBrandID  integer,      -- ID da marca de veículo
  vehicleModelID  integer,      -- ID do modelo de veículo
  name            varchar(50)   -- Nome do modelo de veículo
                  NOT NULL,
  PRIMARY KEY (contractorID, vehicleModelID),
  FOREIGN KEY (contractorID, vehicleBrandID)
    REFERENCES stc.vehicleBrands(contractorID, vehicleBrandID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Fabricantes de dispositivos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.manufactures (
  contractorID    integer,      -- Número de identificação do contratante
                                -- no sistema ERP
  manufactureID   char(2),      -- ID do fabricante
  name            varchar(50)   -- Nome do fabricante
                  NOT NULL,
  PRIMARY KEY (contractorID, manufactureID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Gerenciador de dispositivos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.managers (
  contractorID    integer,      -- Número de identificação do contratante
                                -- no sistema ERP
  managerID       integer,      -- ID do gerenciador
  name            varchar(50)   -- Nome do gerenciador
                  NOT NULL,
  PRIMARY KEY (contractorID, managerID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Dispositivos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.deviceModels (
  contractorID      integer,      -- Número de identificação do contratante
                                  -- no sistema ERP
  deviceModelID     serial,       -- ID do modelo de rastreador
  manufactureID     char(2),      -- ID do fabricante do rastreador
  name              varchar(100), -- Nome do modelo do rastreador
  ableToKeyboard    boolean       -- A flag indicativa de que o modelo é
                    DEFAULT false,-- capaz de usar teclados
  PRIMARY KEY (contractorID, deviceModelID),
  FOREIGN KEY (contractorID, manufactureID)
    REFERENCES stc.manufactures(contractorID, manufactureID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Sincronismo de modelos de rastreadores
-- ---------------------------------------------------------------------
-- Stored Procedure que atualiza o nome do modelo de um rastreador no
-- processo de importação de dados.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION stc.syncDeviceName(fContractorID integer,
  fDeviceID varchar, fDeviceModelName varchar)
RETURNS void AS
$$
DECLARE
  deviceData record;
  deviceModel record;
BEGIN
  -- Como temos o ID do equipamento, através dele fazemos a busca do seu
  -- respectivo fabricante
  SELECT manufactureID,
         devicemodelID
    INTO deviceData
    FROM stc.devices
   WHERE contractorID = fContractorID
     AND deviceid = fDeviceID::integer;
  IF FOUND THEN
    RAISE NOTICE 'DeviceID: % localizado', fDeviceID;
    -- Encontrou o equipamento, analisamos se o modelo foi identificado
    IF deviceData.devicemodelID IS NULL THEN
      RAISE NOTICE 'O dispositivo não possui modelo';
      -- Não foi identificado o modelo, vamos tentar identificar pelo
      -- nome do modelo e a informação do fabricante
      SELECT deviceModelID
        INTO deviceModel
        FROM stc.devicemodels
       WHERE contractorID = fContractorID
         AND manufactureID = deviceData.manufactureID
         AND public.unaccented(name) ILIKE public.unaccented(fDeviceModelName);
      IF FOUND THEN
        RAISE NOTICE 'Modelo: % localizado, apenas atualizando', deviceModel.deviceModelID;
        -- Encontrou o modelo, vamos atualizar o equipamento
        UPDATE stc.devices
           SET deviceModelID = deviceModel.deviceModelID
         WHERE deviceid = fDeviceID::integer;
      ELSE
        RAISE NOTICE 'Modelo não localizado, insere';
        -- Não foi localizado o modelo, vamos adicioná-lo
        INSERT INTO stc.devicemodels
                   (contractorID, manufactureID, name)
        VALUES (fContractorID, deviceData.manufactureid, fDeviceModelName)
        RETURNING devicemodelid INTO deviceData.devicemodelid;

        -- Agora que temos o modelo, vamos atualizar o equipamento
        UPDATE stc.devices
           SET deviceModelID = deviceData.devicemodelid
         WHERE deviceid = fDeviceID::integer;
      END IF;
    ELSE
      RAISE NOTICE 'Modelo já identificado, ignorando';
    END IF;
  END IF;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Dispositivos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.devices (
  contractorID      integer,      -- Número de identificação do contratante
                                  -- no sistema ERP
  deviceID          integer,      -- ID do dispositivo
  deviceModelID     integer,      -- ID do modelo do dispositivo
  manufactureID     char(2),      -- ID do fabricante do rastreador
  plate             varchar(10),  -- Placa do veículo ao qual está associado
  ownerName         varchar(100), -- Nome do proprietário do rastreador
  ownerType         varchar(100), -- vehicle = veículo no qual ele está instalado ou
                                  -- manager = gerenciado que está com o dispositivo
  createdAt         timestamp     -- A data de criação do cliente
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  lastCommunication timestamp     -- A data de última comunicação
                    DEFAULT NULL,
  PRIMARY KEY (contractorID, deviceID),
  FOREIGN KEY (contractorID, manufactureID)
    REFERENCES stc.manufactures(contractorID, manufactureID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);


-- ---------------------------------------------------------------------
-- Journeys
-- ---------------------------------------------------------------------
-- Armazena as informações das jornadas de trabalho a serem cumpridas
-- pelos motoristas.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.journeys (
  contractorID            integer,            -- Número de identificação do
                                              -- contratante no sistema ERP
  clientID                integer,            -- Número de identificação do
                                              -- cliente no sistema STC
  journeyID               serial,             -- ID da jornada
  startDayTime            time                -- O horário de início do
                          DEFAULT '05:00:00', -- período diurno
  startNightTime          time                -- O horário de início do
                          DEFAULT '22:00:00', -- período noturno
  name                    varchar(50),        -- Nome da jornada
  createdAt               timestamp           -- A data de criação da jornada
                          NOT NULL
                          DEFAULT CURRENT_TIMESTAMP,
  computeOvertime         boolean             -- Computa horas adicionais como
                          DEFAULT true,       -- horas extras (falso considera
                                              -- banco de horas)
  discountWorkedLessHours boolean             -- Desconta horas trabalhadas
                          DEFAULT false,      -- à menos do banco de horas
  createdByUserID         integer             -- O ID do usuário responsável
                          NOT NULL,           -- pelo cadastro desta jornada
  updatedAt               timestamp           -- A data de modificação da
                          NOT NULL            -- jornada
                          DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID         integer             -- O ID do usuário responsável
                          NOT NULL,           -- pela última modificação
  asDefault               boolean,            -- A flag indicativa de que esta
                                              -- jornada é o padrão para novos
                                              -- motoristas
  PRIMARY KEY (contractorID, clientID, journeyID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID, clientID)
    REFERENCES stc.customers(contractorID, clientID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

-- Gatilho para lidar com a jornada padrão
CREATE OR REPLACE FUNCTION stc.journeyTransaction()
RETURNS trigger AS $BODY$
DECLARE
  amount  integer;
BEGIN
  -- Faz a atualização da jornada padrão na inserção de novos valores.
  -- Faz uso da variável especial TG_OP para verificar a operação
  -- executada e de TG_WHEN para determinar o instante em que isto
  -- ocorre.
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica se a jornada foi definida como padrão
      IF (NEW.asDefault = TRUE) THEN
        -- Precisamos forçar para que todas as demais jornadas deste
        -- cliente não possuam mais o atributo de padrão
        UPDATE stc.journeys
           SET asDefault = FALSE
         WHERE contractorID = NEW.contractorID
           AND clientID = NEW.clientID;
      ELSE
        -- Precisamos verificar se temos alguma jornada definida como
        -- padrão para este cliente
        SELECT count(*) INTO amount
          FROM stc.journeys
         WHERE contractorID = NEW.contractorID
           AND clientID = NEW.clientID
           AND asDefault = TRUE;

        IF FOUND THEN
          IF (amount = 0) THEN
            -- Se não tivermos nenhuma jornada padrão, então definimos
            -- esta como a padrão para este cliente
            NEW.asDefault = TRUE;
          END IF;
        END IF;
      END IF;
    END IF;

    -- Retornamos a nova jornada
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica se a jornada padrão foi modificada
      IF (OLD.asDefault <> NEW.asDefault) THEN
        IF (NEW.asDefault = TRUE) THEN
          -- Precisamos forçar para que todas as demais jornadas deste
          -- cliente não possuam mais o atributo de padrão
          UPDATE stc.journeys
             SET asDefault = FALSE
           WHERE contractorID = NEW.contractorID
             AND clientID = NEW.clientID
             AND journeyID <> OLD.journeyID;
        END IF;
      END IF;
    END IF;

    -- Retornamos a nova jornada
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$BODY$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS journeysTransactionTriggerBefore
  ON stc.journeys;

CREATE TRIGGER journeysTransactionTriggerBefore
  BEFORE INSERT OR UPDATE ON stc.journeys
  FOR EACH ROW EXECUTE PROCEDURE stc.journeyTransaction();


-- ---------------------------------------------------------------------
-- JourneyPerDay
-- ---------------------------------------------------------------------
-- Armazena as informações da quantidade de segundos a serem cumpridos
-- por cada jornada de trabalho em cada dia da semana.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.journeyPerDay (
  contractorID    integer,      -- Número de identificação do
                                -- contratante no sistema ERP
  clientID        integer,      -- Número de identificação do cliente no
                                -- sistema STC
  journeyID       integer,      -- ID da jornada
  journeyPerDayID serial,       -- ID da jornada diária
  dayofweek       smallint      -- Dia da semana (0: Dom, 1: Seg, etc)
                  NOT NULL
                  CHECK (
                    (dayofweek >= 0) AND
                    (dayofweek < 7)
                  ),
  seconds         integer       -- Quantidade de segundos que determina
                  NOT NULL      -- a duração de uma jornada neste dia
                  CHECK (
                    (seconds >= 0) AND
                    (seconds <= 86400)
                  ),
  PRIMARY KEY (journeyPerDayID),
  UNIQUE (journeyID, dayofweek),
  FOREIGN KEY (contractorID, clientID, journeyID)
    REFERENCES stc.journeys(contractorID, clientID, journeyID)
    ON DELETE RESTRICT
);


-- ---------------------------------------------------------------------
-- Drivers
-- ---------------------------------------------------------------------
-- Armazena as informações de motoristas cadastrados no sistema STC
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.drivers (
  contractorID          integer,      -- Número de identificação do contratante
                                      -- no sistema ERP
  clientID              integer,      -- Número de identificação do
                                      -- cliente no sistema STC
  driverID              bigint,       -- Número de identificação do motorista
                                      -- no sistema STC
  name                  varchar(100), -- Nome do motorista
  occupation            varchar(100), -- Ocupação (cargo)
  customerIsMyEmployer  boolean       -- O indicativo de que o cliente
                        DEFAULT true, -- é o empregador deste motorista
  employerName          varchar(100), -- O nome do empregador
  PRIMARY KEY (contractorID, clientID, driverID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID, clientID)
    REFERENCES stc.customers(contractorID, clientID)
    ON DELETE RESTRICT
);


-- ---------------------------------------------------------------------
-- JourneyPerDriver
-- ---------------------------------------------------------------------
-- Armazena as informações de qual jornada de trabalho cada motorista
-- está cumprindo. Como o motorista pode mudar de jornada de trabalho ao
-- longo do tempo, então esta tabela armazena também a data à partir da
-- qual o motorista inicia na nova jornada.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.journeyPerDriver (
  journeyPerDriverID serial,       -- ID da jornada por motorista
  contractorID       integer,      -- Número de identificação do
                                   -- contratante no sistema ERP
  clientID           integer,      -- Número de identificação do cliente no
                                   -- sistema STC
  driverID           bigint,       -- ID do motorista
  journeyID          integer,      -- ID da jornada
  begginingAt        date          -- Data à partir da qual o motorista
                     NOT NULL      -- iniciou o cumprimento desta jornada
                     DEFAULT CURRENT_TIMESTAMP, 
  PRIMARY KEY (journeyPerDriverID),
  UNIQUE (contractorID, clientID, driverID, begginingAt),
  FOREIGN KEY (contractorID, clientID)
    REFERENCES stc.customers(contractorID, clientID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID, clientID, driverID)
    REFERENCES stc.drivers(contractorID, clientID, driverID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID, clientID, journeyID)
    REFERENCES stc.journeys(contractorID, clientID, journeyID)
    ON DELETE RESTRICT
);

CREATE TYPE stc.driverJourney AS
(
  contractorID            integer,      -- Número de identificação do
                                        -- contratante no sistema ERP
  clientID                integer,      -- Número de identificação do cliente
                                        -- no sistema STC
  driverID                bigint,       -- ID do motorista
  journeyID               integer,      -- ID da jornada
  name                    varchar(50),  -- Nome da jornada
  begginingAt             date,         -- Data de início do cumprimento
  startDayTime            time,         -- O horário de início do período diurno
  endDayTime              time,         -- O horário de término do período diurno
  startNightTime          time,         -- O horário de início do período noturno
  endNightTime            time,         -- O horário de término do período noturno
  computeOvertime         boolean,      -- A flag que indica que horas adicionais serão computadas como horas extras
  discountWorkedLessHours boolean       -- A flag que indica que horas trabalhadas à menos serão descontadas do banco de horas
);

-- Stored Procedure que recupera as jornadas a serem cumpridas durante o
-- período informado para um motorista. Nos permite calcular as horas
-- trabalhadas para um motorista
CREATE OR REPLACE FUNCTION stc.getJourneysForDriveOnPeriod(FcontractorID int,
  FclientID int, FdriverID int, FstartDate date, FendDate date)
RETURNS SETOF stc.driverJourney AS
$$
DECLARE
  driverJourney   stc.driverJourney%rowtype;
BEGIN
  -- Recuperamos, primeiramente, a informação de jornada cujo início de
  -- cumprimento ocorreu antes do início do período solicitado
  SELECT journeyPerDriver.contractorID,
         journeyPerDriver.clientID,
         journeyPerDriver.driverID,
         journeyPerDriver.journeyID,
         journeys.name,
         journeyPerDriver.begginingAt,
         journeys.startDayTime,
         (journeys.startNightTime::time - interval '1 second') AS endDayTime,
         journeys.startNightTime,
         (journeys.startDayTime::time - interval '1 second') AS endNightTime,
         journeys.computeOvertime,
         CASE
           WHEN journeys.computeOvertime THEN false
           ELSE journeys.discountWorkedLessHours
         END AS discountWorkedLessHours
    INTO driverJourney
    FROM stc.journeyPerDriver
   INNER JOIN stc.journeys USING (journeyID)
   WHERE journeyPerDriver.contractorID = FcontractorID
     AND journeyPerDriver.clientID = FclientID
     AND journeyPerDriver.driverID = FdriverID
     AND journeyPerDriver.begginingAt::date <= FstartDate::date
   ORDER BY journeyPerDriver.begginingAt DESC
   LIMIT 1;

  IF FOUND THEN
    RETURN NEXT driverJourney;
  ELSE
    -- Tentamos localizar a jornada padrão para o motorista
    SELECT journeys.contractorID,
           journeys.clientID,
           FdriverID AS driverID,
           journeys.journeyID,
           journeys.name,
           FstartDate::date AS begginingAt,
           journeys.startDayTime,
           (journeys.startNightTime::time - interval '1 second') AS endDayTime,
           journeys.startNightTime,
           (journeys.startDayTime::time - interval '1 second') AS endNightTime,
           journeys.computeOvertime,
           CASE
             WHEN journeys.computeOvertime THEN false
             ELSE journeys.discountWorkedLessHours
           END AS discountWorkedLessHours
      INTO driverJourney
      FROM stc.journeys
     WHERE journeys.contractorID = FcontractorID
       AND journeys.clientID = FclientID
       AND journeys.asDefault = true
     LIMIT 1;
    IF FOUND THEN
      RETURN NEXT driverJourney;
    END IF;
  END IF;

  -- Recuperamos a informação de jornadas cujo início de cumprimento
  -- ocorram dentro do período solicitado
  FOR driverJourney IN
    SELECT journeyPerDriver.contractorID,
           journeyPerDriver.clientID,
           journeyPerDriver.driverID,
           journeyPerDriver.journeyID,
           journeys.name,
           journeyPerDriver.begginingAt,
           journeys.startDayTime,
           (journeys.startNightTime::time - interval '1 second') AS endDayTime,
           journeys.startNightTime,
           (journeys.startDayTime::time - interval '1 second') AS endNightTime,
           journeys.computeOvertime,
           CASE
             WHEN journeys.computeOvertime THEN false
             ELSE journeys.discountWorkedLessHours
           END AS discountWorkedLessHours
      FROM stc.journeyPerDriver
     INNER JOIN stc.journeys USING (journeyID)
     WHERE journeyPerDriver.contractorID = FcontractorID
       AND journeyPerDriver.clientID = FclientID
       AND journeyPerDriver.driverID = FdriverID
       AND journeyPerDriver.begginingAt::date > FstartDate::date
       AND journeyPerDriver.begginingAt::date < FendDate::date
     ORDER BY journeyPerDriver.begginingAt ASC loop
    RETURN NEXT driverJourney;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT stc.getJourneysForDriveOnPeriod(1, 15, 46, '2020-07-01'::date, '2020-07-31'::date);


-- ---------------------------------------------------------------------
-- Vehicles
-- ---------------------------------------------------------------------
-- Armazena as informações de veículos cadastrados no sistema STC     
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.vehicles (
  contractorID    integer,      -- Número de identificação do contratante
                                -- no sistema ERP
  id              integer,      -- Número de identificação do
                                -- veículo no sistema STC
  clientID        integer,      -- Número de identificação do
                                -- cliente no sistema STC
  vehicleID       integer,      -- Número de identificação do
                                -- veículo no ERP
  customerID      integer,      -- Número de identificação do
                                -- cliente no ERP
  subsidiaryID    integer,      -- Número de identificação da
                                -- unidade/filial do cliente
                                -- no ERP
  plate           varchar(10),  -- Placa do veículo
  vehicleTypeID   integer,      -- ID do tipo do veículo
  vehicleModelID  integer,      -- ID do modelo do veículo
  manufactureID   char(2),      -- ID do fabricante do rastreador
                                -- associado ao veículo
  deviceID        integer,      -- ID do rastreador associado
                                -- ao veículo
  status          boolean       -- O indicativo de que o veículo
                  DEFAULT true, -- está ativo ou não
  yearFabr        char(4)       -- O ano de fabricação
                  NOT NULL,
  yearModel       char(4)       -- O ano do modelo
                  NOT NULL,
  renavam         varchar(20)   -- O número do RENAVAM
                  NOT NULL,
  vin             varchar(20),  -- O número do chassi
                                --   VIN: Vehicle Identification Number
  info            text,         -- Informações do veículo
                                -- (limitado à 500 caracteres)
  label           varchar(50),  -- Um rótulo para o veículo
  email           varchar(40),  -- Email associado ao veículo
  driver          varchar(100), -- Nome do motorista
  phoneNumber1    varchar(20),  -- Telefone 1
  phoneNumber2    varchar(20),  -- Telefone 12
  cpf             varchar(18),  -- CPF ou CNPJ *** (1)
  lastPositionID  integer       -- Último posicionamento recebido
                  DEFAULT 0,
  PRIMARY KEY (contractorID, id),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID, vehicleTypeID)
    REFERENCES stc.vehicleTypes(contractorID, vehicleTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID, manufactureID)
    REFERENCES stc.manufactures(contractorID, manufactureID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID, deviceID)
    REFERENCES stc.devices(contractorID, deviceID)
    ON DELETE RESTRICT
);

-- *** (1): Observado em 15/02/2022 que alguns registros estavam vindo
--          com o CNPJ neste campo, e por isto incrementamos o seu
--          tamanho para 18, de forma a comportar estes valores

-- ---------------------------------------------------------------------
-- Positions
-- ---------------------------------------------------------------------
-- Armazena as informações de posições obtidas através do rastreador
-- instalado no veículo pelo sistema STC
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.positions (
  contractorID    integer,      -- Número de identificação do contratante
                                -- no sistema ERP
  registreID      serial,       -- ID do registro
  positionID      integer,      -- Nº sequencial do posicionamento
  deviceID        integer,      -- ID do rastreador associado
                                -- ao veículo
  plate           varchar(10),  -- Placa do veículo
  eventDate       timestamp     -- A data do evento
                  NOT NULL
                  DEFAULT CURRENT_TIMESTAMP,
  ignitionStatus  boolean,      -- O estado da ignição (ligada/desligada)
  odometer        integer,      -- O valor do odômetro
  horimeter       integer,      -- O valor do "horimeter"
  address         text          -- O endereço da posição
                  NOT NULL
                  DEFAULT '',
  direction       numeric(5,2),
  speed           smallint,
  batteryVoltage  numeric(4,2),
  latitude        numeric(8,6),
  longitude       numeric(9,6),
  driverID        bigint,       -- ID do motorista
  driverName      varchar(100), -- Nome do motorista
  rs232           varchar(100), -- Dados obtidos da porta serial
  PRIMARY KEY (registreID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID, deviceID)
    REFERENCES stc.devices(contractorID, deviceID)
    ON DELETE RESTRICT
);

CREATE OR REPLACE FUNCTION stc.insertPositions()
  RETURNS TRIGGER AS $BODY$
DECLARE
  yearOfEventDate     char(4);
  monthOfEventDate    char(2);
  partitionPeriod     char(6);
  tableName           varchar;
  createNewPartition  text;
  createNewIndex      text;
  fClientID           integer;
BEGIN
  -- Faz a criação automática do particionamento, se necessário, antes
  -- da inserção de novos valores. Faz uso da variável especial TG_OP
  -- para verificar a operação executada.
  IF (TG_OP = 'INSERT') THEN
    -- Determinamos o ano e mês da ocorrência do evento para determinar
    -- o nome da tabela onde os dados precisam ser inseridos
    yearOfEventDate  := extract(YEAR FROM NEW.eventDate);
    monthOfEventDate := LPAD(extract(MONTH FROM NEW.eventDate)::varchar, 2, '0');
    partitionPeriod  := yearOfEventDate || monthOfEventDate;
    tableName         := 'positions_of_' || partitionPeriod;

    IF NOT EXISTS (SELECT relname FROM pg_class WHERE relname=tableName) THEN
      RAISE NOTICE 'Creating positions partition for month % of year %', monthOfEventDate, yearOfEventDate;

      -- Criamos a nova tabela
      createNewPartition := 'CREATE TABLE stc.' || tableName
        || ' ( PRIMARY KEY (registreID), '
        || ' CHECK ( eventDate >= timestamp ''' || yearOfEventDate || '-' || monthOfEventDate || '-01 00:00:00'''
        || '     AND eventDate <  timestamp ''' || yearOfEventDate || '-' || monthOfEventDate || '-01 00:00:00'' + INTERVAL ''1month'' )'
        || ' ) INHERITS (stc.positions);';
      EXECUTE createNewPartition;

      -- Criamos os novos índices
      createNewIndex := 'CREATE INDEX index_of_positions_on_' || partitionPeriod || '_per_plate ON stc.' || tableName || ' (contractorID, plate, eventDate);';
      EXECUTE createNewIndex;
      createNewIndex := 'CREATE INDEX index_of_positions_on_' || partitionPeriod || '_per_date ON stc.' || tableName || ' (contractorID, eventDate);';
      EXECUTE createNewIndex;
    END IF;

    IF ((NEW.positionID IS NULL) OR (NEW.positionID = 0)) THEN
      RAISE EXCEPTION 'ID da posição inválido ou inexistente';
    END IF;

    -- Verificamos se o registro já foi inserido
    IF count(1) > 0 FROM stc.positions WHERE contractorID = NEW.contractorID AND positionID = NEW.positionID THEN
      RAISE NOTICE 'Skipping insertion because duplicate key value violates unique constraint "%" ON "%". ', 
        TG_NAME, TG_TABLE_NAME 
      USING DETAIL = format('Key (positionID)=(%s) already exists when (contractorID)=(%s).', NEW.positionID, NEW.contractorID);

      -- Ignoramos a inserção
      RETURN NULL;
    END IF;

    -- Inserimos os dados na tabela correta
    EXECUTE format('INSERT INTO stc.%s VALUES ($1.*);', tableName)
      USING NEW;

    -- Removido em 11/08/2020 pelo fato de não podermos confiar na
    -- informação obtida da STC
    -- IF NEW.driverID > 0 THEN
    --  -- Recupera a informação de cliente
    --  SELECT clientID INTO fClientID
    --    FROM stc.vehicles
    --   WHERE contractorID = NEW.contractorID
    --     AND plate = NEW.plate;
    --
    --  -- Verificamos se o motorista já foi inserido
    --  IF count(1) = 0 FROM stc.drivers WHERE contractorID = NEW.contractorID AND clientID = fClientID AND driverID = NEW.driverID THEN
    --    RAISE NOTICE 'Insert driver information.'
    --    USING DETAIL = format('Driver [%s] %s.', NEW.driverID, NEW.driverName);
    --
    --    -- Inserimos o motorista
    --    INSERT INTO stc.drivers
    --           (contractorID, clientID, driverID, name, occupation)
    --    VALUES (NEW.contractorID, fClientID, NEW.driverID, NEW.driverName, 'Motorista');
    --  END IF;
    -- END IF;

    -- Retornamos nulo
    RETURN NULL;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Retornamos a nova entidade
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER inserPositionTrigger
  BEFORE INSERT ON stc.positions
  FOR EACH ROW
  EXECUTE PROCEDURE stc.insertPositions();

--INSERT INTO stc.positions (contractorID, positionID, deviceID,
--  plate, eventDate, ignitionStatus, odometer, horimeter,
--  address,
--  direction, speed, batteryVoltage, latitude, longitude, driverID, driverName, rs232)
--  VALUES (1, 1426, 4470,
--    'ABC1234', '2018-09-27 11:15:12', false, 61783255, 085120,
--    'ALAMEDA PRAÇA CAPITAL - LOT. CENTER SANTA GENEBRA, CAMPINAS - SP, 13080-650',
--    000.00, 0, 13.10, -22.847178, -47.08351, 0, 'x', 'x');
