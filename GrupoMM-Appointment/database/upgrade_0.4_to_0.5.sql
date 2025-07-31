-- Acrescentamos as colunas para indicar o empregador do motorista
ALTER TABLE stc.drivers ADD COLUMN customerIsMyEmployer  boolean
                        DEFAULT true;
ALTER TABLE stc.drivers ADD COLUMN employerName varchar(100);

-- Acrescentamos a coluna para determinar o comportamento do cálculo de
-- horas adicionais, se são calculadas como horas extras ou computadas
-- como banco de horas
ALTER TABLE stc.journeys ADD COLUMN computeOvertime  boolean
                         DEFAULT true;

DROP FUNCTION stc.getJourneysForDriveOnPeriod(FcontractorID int,
  FclientID int, FdriverID int, FstartDate date, FendDate date);
DROP TYPE stc.driverJourney;

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
  endNightTime      time,         -- O horário de término do período noturno
  computeOvertime   boolean       -- A flag que indica que horas adicionais serão computadas como horas extras
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
         journeys.computeOvertime
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
           journeys.computeOvertime
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
           journeys.computeOvertime
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
