-- =====================================================================
-- Lançamentos de valores cobrados
-- =====================================================================
-- Armazena as informações de serviços executados e/ou valores cobrados
-- em uma instalação. Um lançamento pode ser:
--  - Abonado: quando o mesmo deixa de ser cobrado. Define-se um motivo
--    para o abono;
--  - Ajustado: quando o valor cobrado é modificado. Define-se um motivo
--    para o ajuste e o novo valor no mesmo lançamento;
--  - Renegociado: quando o lançamento é renegociado em parcelas, neste
--    caso deixando de ser cobrado e novos lançamentos são informados,
--    mantendo relação entre eles. Uma renegociação é gerada.
--  - Faturado: quando o valor é definitivamente transposto para uma
--    fatura.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Valores cobrados por instalação
-- ---------------------------------------------------------------------
-- Armazena as informações de serviços executados e/ou valores cobrados
-- em uma instalação.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.billings (
  billingID             serial,       -- ID da cobrança
  contractorID          integer       -- ID do contratante
                        NOT NULL,
  contractID            integer       -- ID do contrato
                        NOT NULL,
  installationID        integer       -- ID da instalação
                        DEFAULT NULL,
  contractChargeID      integer       -- ID da tarifa cobrada ou nulo
                        DEFAULT NULL, -- para outros valores (ex: renegociação)
  billingDate           date          -- A data da cobrança
                        NOT NULL,
  name                  varchar(60)   -- Nome do que se está cobrando
                        NOT NULL,
  value                 numeric(12,2) -- O valor cobrado
                        NOT NULL
                        DEFAULT 0.00,
  installmentNumber     smallint      -- O número da parcela ("0" indica
                        NOT NULL      -- que não é um valor parcelado)
                        DEFAULT 0,
  numberOfinstallments  smallint      -- A quantidade de parcelas ("0"
                        NOT NULL      -- para valores não parcelados)
                        DEFAULT 0,
  granted               boolean       -- Valor abonado (relavado)
                        NOT NULL
                        DEFAULT FALSE,
  reasonForGranting     varchar(100), -- O motivo pelo qual foi concedido
  renegotiated          boolean       -- Valor renegociado
                        NOT NULL
                        DEFAULT FALSE,
  renegotiationID       integer       -- O ID da renegociação (incluído
                        DEFAULT NULL, -- apenas nos registros gerados)
  ascertainedPeriodID   integer       -- O ID do período apurado cujo
                        DEFAULT NULL, -- valor esta cobrança representa
  billedPeriodID        integer       -- O ID do período cobrado cujo
                        DEFAULT NULL, -- valor esta cobrança representa
  addMonthlyAutomatic   boolean       -- Registro adicionado durante o
                        NOT NULL      -- fechamento mensal automaticamente
                        DEFAULT FALSE,
  isMonthlyPayment      boolean       -- Registro adicionado durante o
                        NOT NULL      -- fechamento mensal indicando que
                        DEFAULT FALSE,-- é uma cobrança de mensalidade
  invoiced              boolean       -- Valor faturado (foi gerada uma
                        NOT NULL      -- fatura para cobrança)
                        DEFAULT FALSE,
  invoiceID             integer       -- O ID da fatura onde foi cobrado
                        DEFAULT NULL,
  createdAt             timestamp     -- A data de inclusão da cobrança
                        NOT NULL
                        DEFAULT CURRENT_TIMESTAMP,
  createdByUserID       integer       -- O ID do usuário responsável
                        NOT NULL,
  updatedAt             timestamp     -- A data de modificação
                        NOT NULL
                        DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID       integer       -- O ID do usuário responsável pela
                        NOT NULL,     -- última modificação
  CHECK ( ( (numberOfinstallments > 0) AND
            (installmentNumber BETWEEN 1 AND numberOfinstallments) ) OR
          ( (numberOfinstallments = 0) AND (installmentNumber = 0) ) ),
  CHECK ( (granted AND (reasonForGranting IS NOT NULL)) OR
          (NOT granted AND (reasonForGranting IS NULL)) ),
  PRIMARY KEY (billingID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractID)
    REFERENCES erp.contracts(contractID)
    ON DELETE CASCADE,
  FOREIGN KEY (installationID)
    REFERENCES erp.installations(installationID)
    ON DELETE CASCADE,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

CREATE INDEX idx_billings_invoiced ON erp.billings(invoiced);
CREATE INDEX idx_billings_contractid ON erp.billings(contractid);
CREATE INDEX idx_billings_installationid ON erp.billings(installationid);
CREATE INDEX idx_billings_billingdate_installmentnumber ON erp.billings(billingdate, installmentnumber);

-- ---------------------------------------------------------------------
-- Transações em cobranças
-- ---------------------------------------------------------------------
-- Lida com transações ocorridas na tabela de valores cobrados.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.billingTransaction()
RETURNS trigger AS $$
DECLARE
  billingName varchar(30);
  counter smallint;
BEGIN
  -- Lida com os lançamentos, alterando o valor da fatura sempre que
  -- ocorrer modificação de um valor de um lançamento. Faz uso da
  -- variável especial TG_OP para verificar a operação executada e de
  -- TG_WHEN para determinar o instante em que isto ocorre
  IF (TG_OP = 'INSERT') THEN
    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Verifica se o lançamento está em uma fatura
      IF (OLD.invoiceID IS NOT NULL) THEN
        -- Verifica se o valor já foi cobrado
        IF (OLD.invoiced = FALSE) THEN
          -- O valor ainda não foi fechado, então precisamos recalcular
          -- os valores da respectiva fatura
          UPDATE erp.invoices
             SET invoiceValue = ROUND(billingsCharged.total, 2)
            FROM (
              SELECT invoiceID,
                     SUM(value) as total
                FROM erp.billings
               WHERE granted = false
                 AND renegotiated = false
                 AND invoiced = false
                 AND invoiceID = OLD.invoiceID
              GROUP BY invoiceID
             ) AS billingsCharged
           WHERE invoices.invoiceID = billingsCharged.invoiceID;
        END IF;
      END IF;
    END IF;

    -- Retornamos a entidade modificada
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER billingTransactionTriggerAfter
  AFTER UPDATE ON erp.billings
  FOR EACH ROW EXECUTE PROCEDURE erp.billingTransaction();

-- ---------------------------------------------------------------------
-- Dados de valores cobrados
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de lançamentos
-- (valores cobrados)
-- ---------------------------------------------------------------------
CREATE TYPE erp.billingData AS
(
  billingID            integer,
  customerID           integer,
  customerName         varchar(100),
  cooperative          boolean,
  juridicalperson      boolean,
  subsidiaryID         integer,
  subsidiaryName       varchar(100),
  contractID           integer,
  contractNumber       varchar(16),
  planID               integer,
  planName             varchar(50),
  dueDay               smallint,
  installationID       integer,
  installationNumber   char(12),
  vehicleID            integer,
  plate                varchar(7),
  billingDate          date,
  name                 varchar(100),
  billingValue         numeric(12,2),
  installmentNumber    smallint,
  numberOfInstallments smallint,
  granted              boolean,
  reasonforgranting    text,
  renegotiated         boolean,
  renegotiationID      integer,
  inMonthlyCalculation boolean,
  ascertainedPeriodID  integer,
  invoiceID            integer,
  fullcount            integer
);

CREATE OR REPLACE FUNCTION erp.getBillingsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FinvoiceID integer,
  FsearchValue varchar(100), FsearchField varchar(20),
  FinMonthlyCalculation boolean, FOrder varchar, Skip integer,
  LimitOf integer)
RETURNS SETOF erp.billingData AS
$$
DECLARE
  billingData  erp.billingData%rowtype;
  row          record;
  vehicleData  record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  blockedLevel integer;
  Finvoiced    varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FcustomerID IS NULL) THEN
    FcustomerID = 0;
  END IF;
  IF (FinvoiceID IS NULL) THEN
    FinvoiceID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FinMonthlyCalculation IS NULL) THEN
    FinMonthlyCalculation = FALSE;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'customers.name, installations.installationID, billings.billingDate, billings.installmentNumber';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';
  
  -- Realiza a filtragem por cliente
  IF (FcustomerID > 0) THEN
    filter := format(' AND customers.entityID = %s',
                    FcustomerID);
    IF (FsubsidiaryID > 0) THEN
      filter := filter || format(' AND subsidiaries.subsidiaryID = %s',
                                 FsubsidiaryID);
    END IF;
  END IF;

  IF (FinvoiceID > 0) THEN
    -- Visualizamos apenas os valores que estão em uma fatura
    filter := filter || format(' AND billings.invoiceID = %s',
                               FinvoiceID);
    Finvoiced := 'true';
  ELSE
    Finvoiced := 'false';
  END IF;

  IF (FinMonthlyCalculation) THEN
    -- Visualizamos apenas os valores que estão no processo de análise
    -- para o faturamento
    filter := filter || format(' AND billings.invoiceID IS NOT NULL');
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- Determina o campo onde será realizada a pesquisa
      CASE (FsearchField)
        WHEN 'plate' THEN
          -- Localizamos instalações em que este veículo esteve associado
          FsearchValue := UPPER(FsearchValue);
          filter := filter || 
            format(' AND installations.installationID IN ('
              || 'SELECT I.installationID'
              || '  FROM erp.vehicles AS V '
              || ' INNER JOIN erp.installationRecords AS I USING (vehicleID)'
              || ' WHERE V.plate ILIKE ''%%%s%%'''
              || ' GROUP BY I.installationID)',
            FsearchValue);
        WHEN 'installationid' THEN
          -- Localizamos pelo ID da instalação
          filter := filter ||
            format(' AND installations.installationid = %s', FsearchValue);
        WHEN 'contractNumber' THEN
          -- Localizamos pelo número do contrato
          field := 'erp.getContractNumber(contracts.createdat)';
          filter := filter ||
            format(' AND %s ILIKE ''%%%s%%''', field, FsearchValue);
        ELSE
          -- Localizamos pelo número da instalação
          field := 'installations.installationNumber';
          filter := filter ||
            format(' AND %s ILIKE ''%%%s%%''', field, FsearchValue);
      END CASE;
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  -- Monta a consulta
  query := format('
    SELECT billings.billingID,
           contracts.customerID,
           customers.name AS customerName,
           entitiesTypes.cooperative,
           entitiesTypes.juridicalperson,
           contracts.subsidiaryID,
           subsidiaries.name AS subsidiaryName,
           billings.contractID,
           erp.getContractNumber(contracts.createdat) AS contractNumber,
           contracts.planid,
           plans.name AS planName,
           dueDays.day AS dueDay,
           billings.installationID,
           installations.installationNumber,
           billings.billingDate,
           CASE
             WHEN billings.renegotiationid IS NOT NULL AND billings.numberofinstallments > 0 THEN ''Renegociação de '' || billings.name || '' (Parcela '' || billings.installmentNumber || '' de '' || billings.numberofinstallments || '')''
             WHEN billings.renegotiationid IS NOT NULL AND billings.numberofinstallments = 0 THEN ''Renegociação de '' || billings.name
             WHEN billings.numberofinstallments > 0 THEN billings.name || '' (Parcela '' || billings.installmentNumber || '' de '' || billings.numberofinstallments || '')''
             ELSE billings.name
           END AS name,
           billings.value AS billingValue,
           billings.installmentNumber,
           billings.numberOfInstallments,
           billings.granted,
           billings.reasonforgranting,
           billings.renegotiated,
           billings.renegotiationid,
           CASE
             WHEN billings.invoiceID IS NULL THEN FALSE
             ELSE TRUE
           END AS inMonthlyCalculation,
           billings.ascertainedPeriodID,
           billings.invoiceID,
           count(*) OVER() AS fullcount
      FROM erp.billings
     INNER JOIN erp.contracts ON (billings.contractID = contracts.contractID)
     INNER JOIN erp.plans ON (contracts.planID = plans.planID)
     INNER JOIN erp.dueDays ON (contracts.dueDayID = dueDays.dueDayID)
     INNER JOIN erp.entities AS customers ON (contracts.customerID = customers.entityID)
     INNER JOIN erp.entitiesTypes ON (customers.entityTypeID = entitiesTypes.entityTypeID)
     INNER JOIN erp.subsidiaries ON (contracts.subsidiaryID = subsidiaries.subsidiaryID)
      LEFT JOIN erp.installations ON (billings.installationID = installations.installationID)
     WHERE contracts.contractorID = %s
       AND billings.invoiced = %s
       AND contracts.deleted = false
       AND customers.deleted = false
       AND subsidiaries.deleted = false %s
     ORDER BY %s %s;',
    fContractorID, Finvoiced, filter, FOrder, limits);
  -- RAISE NOTICE 'Query IS %', query;
  FOR row IN EXECUTE query
  LOOP
    billingData.billingID            := row.billingID;
    billingData.customerID           := row.customerID;
    billingData.customerName         := row.customerName;
    billingData.cooperative          := row.cooperative;
    billingData.juridicalperson      := row.juridicalperson;
    billingData.subsidiaryID         := row.subsidiaryID;
    billingData.subsidiaryName       := row.subsidiaryName;
    billingData.contractID           := row.contractID;
    billingData.contractNumber       := row.contractNumber;
    billingData.planID               := row.planID;
    billingData.planName             := row.planName;
    billingData.dueDay               := row.dueDay;
    billingData.installationID       := row.installationID;
    billingData.installationNumber   := row.installationNumber;
    billingData.billingDate          := row.billingDate;
    billingData.name                 := row.name;
    billingData.billingValue         := row.billingValue;
    billingData.installmentNumber    := row.installmentNumber;
    billingData.numberOfInstallments := row.numberOfInstallments;
    billingData.granted              := row.granted;
    billingData.reasonforgranting    := row.reasonforgranting;
    billingData.renegotiated         := row.renegotiated;
    billingData.renegotiationID      := row.renegotiationID;
    billingData.inMonthlyCalculation := row.inMonthlyCalculation;
    billingData.ascertainedPeriodID  := row.ascertainedPeriodID;
    billingData.invoiceID            := row.invoiceID;
    billingData.fullcount            := row.fullcount;

    -- Localizamos o veículo
    SELECT DISTINCT ON (I.installationID)
           R.vehicleID,
           V.plate
      INTO vehicleData
      FROM erp.installations AS I
     INNER JOIN erp.installationRecords AS R USING (installationID)
     INNER JOIN erp.vehicles AS V USING (vehicleID)
     WHERE I.installationID = row.installationID
     ORDER BY I.installationID, R.uninstalledAt DESC NULLS FIRST, R.installedAt DESC;
    IF NOT FOUND THEN
      billingData.vehicleID = NULL;
      billingData.plate     = NULL;
    ELSE
      billingData.vehicleID = vehicleData.vehicleID;
      billingData.plate     = vehicleData.plate;
    END IF;

    RETURN NEXT billingData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getBillingsData(1, 0, 0, 0, 'EYG5072', 'plate', false, NULL, 0, 100);

-- ---------------------------------------------------------------------
-- As renegociações de valores cobrados em uma instalação.
-- ---------------------------------------------------------------------
-- Armazena as informações de renegociações de serviços executados e que
-- ainda não tenham sido faturados. Marca o serviço renegociado e inclui
-- um ou mais registros conforme a negociação realizada.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.renegotiatedBillings (
  renegotiationID       serial,       -- ID da renegociação
  contractorID          integer       -- ID do contratante
                        NOT NULL,
  contractID            integer       -- ID do contrato
                        NOT NULL,
  installationID        integer       -- ID da instalação
                        NOT NULL,
  billingID             integer       -- ID da cobrança renegociada
                        NOT NULL,
  renegotiationDate     date          -- A data da renegociação
                        NOT NULL,
  renegotiationValue    numeric(12,2) -- O valor renegociado
                        NOT NULL
                        DEFAULT 0.00,
  notes                 text          -- Observações com relação ao que
                        NOT NULL,     -- foi acertado
  numberOfinstallments  smallint      -- A quantidade de parcelas ("0"
                        NOT NULL      -- para valores não parcelados)
                        DEFAULT 0,
  renegotiatedByUserID  integer       -- O ID do usuário responsável
                        NOT NULL,     -- pela renegociação
  PRIMARY KEY (renegotiationID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractID)
    REFERENCES erp.contracts(contractID)
    ON DELETE CASCADE,
  FOREIGN KEY (installationID)
    REFERENCES erp.installations(installationID)
    ON DELETE CASCADE,
  FOREIGN KEY (billingID)
    REFERENCES erp.billings(billingID)
    ON DELETE CASCADE
);

-- Adicionamos a chave extrangeira para as renegociações na tabela de
-- cobranças
ALTER TABLE erp.billings
  ADD CONSTRAINT billings_renegotiationid_fkey
      FOREIGN KEY (renegotiationID)
        REFERENCES erp.renegotiatedBillings(renegotiationID)
        ON DELETE CASCADE;

-- ---------------------------------------------------------------------
-- Transações em renegociações
-- ---------------------------------------------------------------------
-- Gatilho para lidar com as operações nas renegociações
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.renegotiatedBillingTransaction()
RETURNS trigger AS $$
DECLARE
  billingName varchar(30);
  counter smallint;
BEGIN
  -- Lida com as renegociações, incluíndo os valores renegociados
  -- automaticamente ao inserirmos a renegociação. Faz uso da variável
  -- especial TG_OP para verificar a operação executada e de TG_WHEN
  -- para determinar o instante em que isto ocorre
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Obtemos o nome do valor renegociado
      SELECT INTO billingName
             name
        FROM erp.billings
       WHERE billingID = NEW.billingID;

      -- Alteramos o valor cobrado, indicando que ele foi renegociado
      UPDATE erp.billings
         SET renegotiated = TRUE,
             updatedAt = CURRENT_TIMESTAMP,
             updatedByUserID = NEW.renegotiatedByUserID
       WHERE billingID = NEW.billingID;

      -- Inserimos o(s) registro(s) do(s) valor(es) renegociado(s)
      IF (NEW.numberOfinstallments > 0) THEN
        -- Precisamos inserir o valor na quantidade de parcelas indicada
        counter := 0;
        LOOP
          EXIT WHEN counter = NEW.numberOfinstallments;
          counter := counter + 1 ;

          INSERT INTO erp.billings (contractorID, contractID, installationID,
                      billingDate, name, value, installmentNumber,
                      numberOfinstallments, renegotiationID,
                      createdByUserID, updatedByUserID) VALUES
                     (NEW.contractorID, NEW.contractID, NEW.installationID,
                      NEW.renegotiationDate, billingName,
                      NEW.renegotiationValue, counter,
                      NEW.numberOfinstallments ,NEW.renegotiationID,
                      NEW.renegotiatedByUserID, NEW.renegotiatedByUserID);
        END LOOP;
      ELSE
        -- Incluímos apenas uma parcela única
        INSERT INTO erp.billings (contractorID, contractID, installationID,
                    billingDate, name, value, renegotiationID,
                    createdByUserID, updatedByUserID) VALUES
                   (NEW.contractorID, NEW.contractID, NEW.installationID,
                    NEW.renegotiationDate, billingName,
                    NEW.renegotiationValue, NEW.renegotiationID,
                    NEW.renegotiatedByUserID, NEW.renegotiatedByUserID);
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Retornamos a entidade modificada
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER renegotiatedBillingTransactionTriggerAfter
  AFTER INSERT ON erp.renegotiatedBillings
  FOR EACH ROW EXECUTE PROCEDURE erp.renegotiatedBillingTransaction();
