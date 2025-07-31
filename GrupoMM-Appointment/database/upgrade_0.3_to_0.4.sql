-- Removemos as colunas da tabela de clientes (STC) referentes à
-- configuração do horário de trabalho
ALTER TABLE stc.customers DROP COLUMN startDayTime;
ALTER TABLE stc.customers DROP COLUMN startNightTime;

-- ---------------------------------------------------------------------
-- Journeys
-- ---------------------------------------------------------------------
-- Armazena as informações das jornadas de trabalho a serem cumpridas
-- pelos motoristas.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stc.journeys (
  contractorID      integer,            -- Número de identificação do
                                        -- contratante no sistema ERP
  clientID          integer,            -- Número de identificação do
                                        -- cliente no sistema STC
  journeyID         serial,             -- ID da jornada
  startDayTime      time                -- O horário de início do
                    DEFAULT '05:00:00', -- período diurno
  startNightTime    time                -- O horário de início do
                    DEFAULT '22:00:00', -- período noturno
  name              varchar(50),        -- Nome da jornada
  createdAt         timestamp           -- A data de criação da jornada
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  createdByUserID   integer             -- O ID do usuário responsável
                    NOT NULL,           -- pelo cadastro desta jornada
  updatedAt         timestamp           -- A data de modificação da
                    NOT NULL            -- jornada
                    DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID   integer             -- O ID do usuário responsável
                    NOT NULL,           -- pela última modificação
  asDefault         boolean,            -- A flag indicativa de que esta
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
  driverID           integer,      -- ID do motorista
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
  contractorID      integer,      -- Número de identificação do
                                  -- contratante no sistema ERP
  clientID          integer,      -- Número de identificação do cliente
                                  -- no sistema STC
  driverID          integer,      -- ID do motorista
  journeyID         integer,      -- ID da jornada
  name              varchar(50),  -- Nome da jornada
  begginingAt       date,         -- Data de início do cumprimento
  startDayTime      time,         -- O horário de início do período diurno
  endDayTime        time,         -- O horário de término do período diurno
  startNightTime    time,         -- O horário de início do período noturno
  endNightTime      time          -- O horário de término do período noturno
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
         (journeys.startDayTime::time - interval '1 second') AS endNightTime
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
           (journeys.startDayTime::time - interval '1 second') AS endNightTime
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
           (journeys.startDayTime::time - interval '1 second') AS endNightTime
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
