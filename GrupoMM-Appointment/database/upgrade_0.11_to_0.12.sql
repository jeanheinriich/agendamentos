-- ---------------------------------------------------------------------
-- As ações possíveis para serem aplicadas à um registro de boleto.
-- ---------------------------------------------------------------------
-- Armazena as informações das ações possíveis que podemos executar
-- sobre um boleto.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.billetInstructions (
  instructionID   serial,       -- ID da instrução
  name            varchar(50)   -- Descrição do instrução
                  NOT NULL,
  instructionCode integer       -- Código da instrução a ser executada,
                  NOT NULL,     -- que está definido na classe de boletos
  PRIMARY KEY (instructionID)
);

-- Insere as ações possíveis
INSERT INTO erp.billetInstructions (instructionID, name, instructionCode) VALUES
  ( 1, 'Pedido de registro', 1),
  ( 2, 'Pedido de baixa', 2),
  ( 3, 'Concessão de abatimento', 3),
  ( 4, 'Cancelamento de abatimento', 4),
  ( 5, 'Alteração do vencimento', 5),
  ( 6, 'Alteração de outros dados', 6),
  ( 7, 'Pedido de protesto', 7),
  ( 8, 'Sustar protesto e baixar o título', 8),
  ( 9, 'Sustar protesto e manter pendente em carteira', 9),
  ( 10, 'Pedido de negativação', 10),
  ( 11, 'Sustar negativação e baixar o título', 11),
  ( 12, 'Sustar negativação e manter pendente em carteira', 12);

ALTER SEQUENCE erp.billetinstructions_instructionid_seq RESTART WITH 13;


-- ---------------------------------------------------------------------
-- A fila de despacho de boletos bancários
-- ---------------------------------------------------------------------
-- Armazena as informações a serem enviadas ao banco nos arquivos de
-- remessa.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.billetDispatching (
  dispatchingID     serial,       -- ID do evento de despacho
  contractorID      integer       -- ID do contratante
                    NOT NULL,
  paymentID         integer       -- ID do pagamento atrelado ao evento
                    NOT NULL,
  instructionID     integer       -- ID da instrução
                    NOT NULL,
  requestDate       date          -- Data da requisição
                    NOT NULL
                    DEFAULT CURRENT_DATE,
  dispatchDate      date          -- Data do despacho
                    DEFAULT NULL,
  shippingFileID    integer       -- O ID do arquivo de remessa
                    DEFAULT NULL,
  PRIMARY KEY (dispatchingID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (instructionID)
    REFERENCES erp.billetInstructions(instructionID)
    ON DELETE RESTRICT,
  FOREIGN KEY (shippingFileID)
    REFERENCES erp.bankingTransmissionFiles(transmissionFileID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Gatilho para lidar com novos boletos
-- ---------------------------------------------------------------------
-- Função que insere o boleto para registro na fila de despacho de
-- boletos bancários
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.sendBilletToRegistration()
RETURNS trigger AS $$
DECLARE
  query varchar;
BEGIN
  -- Lida com o registro do boleto. Faz uso da variável especial TG_OP
  -- para verificar a operação executada e de TG_WHEN para determinar o
  -- instante em que isto ocorre
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Inserimos o boleto na fila de despacho de boletos bancários
      INSERT INTO erp.billetDispatching (contractorID, paymentID,
        instructionID) VALUES (NEW.contractorID, NEW.paymentID, 1);
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Não faz nada

    -- Retornamos a entidade modificada
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Não faz nada

    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER bankingBilletPaymentTransactionTriggerAfter
  AFTER INSERT ON erp.bankingBilletPayments
  FOR EACH ROW EXECUTE PROCEDURE erp.sendBilletToRegistration();

-- ---------------------------------------------------------------------
-- Modifica características obsoletas para registro de e-mails
-- ---------------------------------------------------------------------

-- Retira as colunas desnecessárias
ALTER TABLE erp.bankingBilletPayments
  DROP COLUMN shippingFileID;
ALTER TABLE erp.bankingBilletPayments
  DROP COLUMN returnFileID;
ALTER TABLE erp.bankingBilletPayments
  DROP COLUMN sendByEmail;

-- Retira funções de e-mail obsoletas
DROP FUNCTION erp.registreSentMailStatus(FcontractorID integer,
  FpaymentID int, FsentSuccessfully boolean);
DROP FUNCTION erp.registreSentCarnetByMailStatus(FcontractorID integer,
  FcarnetID int, FsentSuccessfully boolean);
DROP TRIGGER sentEmailRecordTransactionTriggerBefore
  ON erp.sentEmailRecords;
DROP TRIGGER sentEmailRecordTransactionTriggerAfter
  ON erp.sentEmailRecords;
DROP FUNCTION erp.sentEmailRecordTransaction();
DROP TABLE erp.sentEmailRecords;


-- ---------------------------------------------------------------------
-- Dados do contrato
-- ---------------------------------------------------------------------
-- Altera a stored procedure que recupera as informações de contratos e
-- instalações para o gerenciamento de contratos, incluindo as colunas
-- para indicar a quantidade de parcelas e a data do próximo vencimento.
-- ---------------------------------------------------------------------
DROP FUNCTION erp.getContractsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FcontractID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Factive boolean,
  FtoCarnet boolean, FOrder varchar, Skip integer, LimitOf integer);
DROP TYPE erp.contractData;

CREATE TYPE erp.contractData AS
(
  contractID                integer,
  contractorID              integer,
  contractorName            varchar(100),
  contractorBlocked         boolean,
  customerID                integer,
  customerName              varchar(100),
  customerBlocked           boolean,
  customerTypeID            integer,
  customerTypeName          varchar(30),
  cooperative               boolean,
  juridicalperson           boolean,
  subsidiaryID              integer,
  subsidiaryName            varchar(100),
  subsidiaryBlocked         boolean,
  affiliated                boolean,
  contractNumber            varchar(16),
  planID                    integer,
  planName                  varchar(50),
  dueDay                    smallint,
  signatureDate             date,
  contractendDate           date,
  paymentConditionID        integer,
  paymentConditionName      varchar(50),
  numberOfParcels           integer,
  contractPrice             numeric(12,2),
  contractActive            boolean,
  installationID            integer,
  installationNumber        char(12),
  noTracker                 boolean,
  containsTrackingData      boolean,
  monthPrice                numeric(12,2),
  startDate                 date,
  endDate                   date,
  dateOfNextReadjustment    date,
  lastDayOfCalculatedPeriod date,
  lastDayOfBillingPeriod    date,
  firstDueDate              date,
  blockedLevel              smallint,
  vehicleID                 integer,
  plate                     varchar(7),
  vehicleTypeID             integer,
  vehicleTypeName           varchar(30),
  vehicleBrandID            integer,
  vehicleBrandName          varchar(30),
  vehicleModelID            integer,
  vehicleModelName          varchar(50),
  vehicleColorID            integer,
  vehicleColorName          varchar(30),
  vehicleColor              varchar(30),
  vehicleBlocked            boolean,
  fullcount                 integer
);

CREATE OR REPLACE FUNCTION erp.getContractsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FcontractID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Factive boolean,
  FtoCarnet boolean, FOrder varchar, Skip integer, LimitOf integer)
RETURNS SETOF erp.contractData AS
$$
DECLARE
  contractData erp.contractData%rowtype;
  row          record;
  vehicleData  record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  blockedLevel integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FcustomerID IS NULL) THEN
    FcustomerID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FcontractID IS NULL) THEN
    FcontractID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'customer.name ASC, subsidiary.subsidiaryid ASC, contracts.signaturedate ASC';
  END IF;
  IF (FtoCarnet IS NULL) THEN
    FtoCarnet = FALSE;
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';
  
  IF (FcontractID > 0) THEN
    filter := format(' AND contracts.contractID = %s',
                    FcontractID);
  ELSE
    -- Realiza a filtragem por cliente
    IF (FcustomerID > 0) THEN
      filter := format(' AND customer.entityID = %s',
                      FcustomerID);
      IF (FsubsidiaryID > 0) THEN
        filter := filter || format(' AND subsidiary.subsidiaryID = %s',
                                  FsubsidiaryID);
      END IF;
    END IF;
  END IF;

  IF (FtoCarnet) THEN
    -- Incluímos apenas contratos cuja forma de pagamento seja em carnê
    filter := filter || ' AND contracts.paymentConditionID IN '
      || '('
      ||   'SELECT Carnets.paymentConditionID FROM '
      ||   '('
      ||     'SELECT COND.paymentconditionid, '
      ||            'string_to_array(COND.paymentinterval, ''/'') AS parcels '
      ||       'FROM erp.paymentconditions AS COND '
      ||      'WHERE COND.paymentmethodid = 5 '
      ||        'AND COND.paymentformid = 2 '
      ||        'AND COND.timeunit = ''MONTH'''
      ||   ') AS Carnets '
      ||   'WHERE array_length(Carnets.parcels, 1) > 1 '
      ||     'AND (Carnets.parcels::INT[])[1] > 0'
      || ')'
    ;
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      IF (FsearchField = 'plate') THEN
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
      ELSE
        -- Determina o campo onde será realizada a pesquisa
        CASE (FsearchField)
          WHEN 'contractNumber' THEN
            field := 'erp.getContractNumber(contracts.createdat)';
          ELSE
            field := 'installations.installationNumber';
        END CASE;
        -- Monta o filtro
        filter := filter || format(' AND %s ILIKE ''%%%s%%''',
                                    field, FsearchValue);
      END IF;
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  IF (Factive IS NOT NULL) THEN
    IF (Factive = TRUE) THEN
      -- Adiciona a opção de filtragem de contratos ativos
      filter := filter || ' AND contracts.active = true';
    ELSE
      -- Adiciona a opção de filtragem de contratos inativos
      filter := filter || ' AND contracts.active = false';
    END IF;
  END IF;

  -- Monta a consulta
  query := format('SELECT contracts.contractID,
                          contracts.contractorID,
                          contractor.name AS contractorName,
                          contractor.blocked AS contractorBlocked,
                          contracts.customerID,
                          customer.name AS customerName,
                          customer.blocked AS customerBlocked,
                          customer.entityTypeID AS customerTypeID,
                          customerType.name AS customerTypeName,
                          customerType.cooperative,
                          customerType.juridicalperson,
                          contracts.subsidiaryID,
                          subsidiary.name AS subsidiaryName,
                          subsidiary.blocked AS subsidiaryBlocked,
                          subsidiary.affiliated,
                          erp.getContractNumber(contracts.createdat) AS contractNumber,
                          contracts.planID,
                          plans.name AS planName,
                          dueDays.day AS dueDay,
                          contracts.signaturedate,
                          contracts.enddate AS contractenddate,
                          contracts.paymentConditionID,
                          paymentConditions.name AS paymentConditionName,
                          CASE
                            WHEN paymentConditions.timeunit = ''MONTH'' AND paymentConditions.paymentformid = 2 AND paymentConditions.paymentmethodid = 5 THEN array_upper(string_to_array(paymentConditions.paymentinterval, ''/'')::int[], 1)
                            ELSE 0
                          END AS numberOfParcels,
                          contracts.monthprice AS contractPrice,
                          contracts.active AS contractActive,
                          installations.installationID,
                          installations.installationNumber,
                          installations.monthprice,
                          installations.startDate,
                          installations.endDate,
                          installations.dateOfNextReadjustment,
                          installations.lastDayOfCalculatedPeriod,
                          installations.lastDayOfBillingPeriod,
                          CASE
                            WHEN installations.lastDayOfBillingPeriod IS NULL THEN ((date_trunc(''month'', (CURRENT_DATE + interval ''1 day'')) + interval ''1 month'') + (dueDays.day - 1) * interval ''1 day'')::Date
                            ELSE ((date_trunc(''month'', (installations.lastDayOfBillingPeriod + interval ''1 day'')) + interval ''1 month'') + (dueDays.day - 1) * interval ''1 day'')::Date
                          END AS firstDueDate,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID
                                     AND uninstalledAt IS NULL) AS tracked,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID) AS containsTrackingData,
                          count(*) OVER() AS fullcount
                     FROM erp.contracts
                    INNER JOIN erp.entities AS contractor ON (contracts.contractorID = contractor.entityID)
                    INNER JOIN erp.entities AS customer ON (contracts.customerID = customer.entityID)
                    INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
                    INNER JOIN erp.subsidiaries AS subsidiary ON (contracts.subsidiaryID = subsidiary.subsidiaryID)
                    INNER JOIN erp.dueDays ON (contracts.dueDayID = dueDays.dueDayID)
                    INNER JOIN erp.paymentConditions ON (contracts.paymentConditionID = paymentConditions.paymentConditionID)
                    INNER JOIN erp.plans ON (contracts.planID = plans.planID)
                    INNER JOIN erp.installations ON (contracts.contractID = installations.contractID)
                    WHERE contracts.contractorID = %s
                      AND contracts.deleted = false
                      AND customer.deleted = false
                      AND subsidiary.deleted = false %s
                    ORDER BY %s %s',
                  fContractorID, filter, FOrder, limits);
  FOR row IN EXECUTE query
  LOOP
    contractData.contractID                 := row.contractID;
    contractData.contractorID               := row.contractorID;
    contractData.contractorName             := row.contractorName;
    contractData.contractorBlocked          := row.contractorBlocked;
    contractData.customerID                 := row.customerID;
    contractData.customerName               := row.customerName;
    contractData.customerBlocked            := row.customerBlocked;
    contractData.customerTypeID             := row.customerTypeID;
    contractData.customerTypeName           := row.customerTypeName;
    contractData.juridicalperson            := row.juridicalperson;
    contractData.cooperative                := row.cooperative;
    contractData.subsidiaryID               := row.subsidiaryID;
    contractData.subsidiaryName             := row.subsidiaryName;
    contractData.subsidiaryBlocked          := row.subsidiaryBlocked;
    contractData.affiliated                 := row.affiliated;
    contractData.contractNumber             := row.contractNumber;
    contractData.planID                     := row.planID;
    contractData.planName                   := row.planName;
    contractData.dueDay                     := row.dueDay;
    contractData.signatureDate              := row.signatureDate;
    contractData.contractEndDate            := row.contractEndDate;
    contractData.paymentConditionID         := row.paymentConditionID;
    contractData.paymentConditionName       := row.paymentConditionName;
    contractData.numberOfParcels            := row.numberOfParcels;
    contractData.contractPrice              := row.contractPrice;
    contractData.contractActive             := row.contractActive;
    contractData.installationID             := row.installationID;
    contractData.installationNumber         := row.installationNumber;
    contractData.noTracker                  := NOT row.tracked;
    contractData.containsTrackingData       := row.containsTrackingData;
    contractData.monthPrice                 := row.monthPrice;
    contractData.startDate                  := row.startDate;
    contractData.endDate                    := row.endDate;
    contractData.dateOfNextReadjustment     := row.dateOfNextReadjustment;
    contractData.lastDayOfCalculatedPeriod  := row.lastDayOfCalculatedPeriod;
    contractData.lastDayOfBillingPeriod     := row.lastDayOfBillingPeriod;
    contractData.firstDueDate               := row.firstDueDate;
    -- RAISE NOTICE 'Contractor %', row.contractorBlocked;
    -- RAISE NOTICE 'Vehicle %', row.vehicleBlocked;
    -- RAISE NOTICE 'Subsidiary %', row.subsidiaryBlocked;

    -- Determina o nível de bloqueio. O nível de bloqueio mais baixo é o
    -- da instalação, seguido do contrato, da unidade/filial do cliente,
    -- da empresa e por último o do contratante
    blockedLevel := 0;
    IF (row.endDate IS NOT NULL) THEN
      -- A instalação foi encerrada
      blockedLevel := blockedLevel|1;
    END IF;
    IF ( (row.contractEndDate IS NOT NULL) OR
         (row.contractActive = FALSE) ) THEN
      -- O contrato está encerrado ou foi inativado
      blockedLevel := blockedLevel|2;
    END IF;
    IF (row.subsidiaryBlocked) THEN
      -- A unidade/filial do cliente foi inativada
      blockedLevel := blockedLevel|4;
    END IF;
    IF (row.customerBlocked) THEN
      -- O cliente foi bloqueado
      blockedLevel := blockedLevel|8;
    END IF;
    IF (row.contractorBlocked) THEN
      -- O contratante foi bloqueado
      blockedLevel := blockedLevel|16;
    END IF;
    contractData.blockedLevel := blockedLevel;
    contractData.fullcount    := row.fullcount;

    -- Localizamos o veículo, se necessário
    IF (row.containsTrackingData) THEN
      SELECT DISTINCT ON (I.installationID)
             R.vehicleID,
             V.plate,
             V.vehicleTypeID,
             T.name AS vehicleTypeName,
             V.vehicleBrandID,
             B.name AS vehicleBrandName,
             V.vehicleModelID,
             M.name AS vehicleModelName,
             V.vehicleColorID,
             C.name AS vehicleColorName,
             V.blocked
        INTO vehicleData
        FROM erp.installations AS I
       INNER JOIN erp.installationRecords AS R USING (installationID)
       INNER JOIN erp.vehicles AS V USING (vehicleID)
       INNER JOIN erp.vehicleTypes AS T ON (V.vehicleTypeID = T.vehicleTypeID)
       INNER JOIN erp.vehicleBrands AS B ON (V.vehicleBrandID = B.vehicleBrandID)
       INNER JOIN erp.vehicleModels AS M ON (V.vehicleModelID = M.vehicleModelID)
       INNER JOIN erp.vehicleColors AS C ON (V.vehicleColorID = C.vehicleColorID)
       WHERE I.installationID = row.installationID
       ORDER BY I.installationID, R.uninstalledAt NULLS FIRST, R.installedAt DESC;
      IF NOT FOUND THEN
        contractData.vehicleID        = NULL;
        contractData.plate            = NULL;
        contractData.vehicleTypeID    = NULL;
        contractData.vehicleTypeName  = NULL;
        contractData.vehicleBrandID   = NULL;
        contractData.vehicleBrandName = NULL;
        contractData.vehicleModelID   = NULL;
        contractData.vehicleModelName = NULL;
        contractData.vehicleColorID   = NULL;
        contractData.vehicleColorName = NULL;
        contractData.vehicleBlocked   = FALSE;
      ELSE
        contractData.vehicleID        = vehicleData.vehicleID;
        contractData.plate            = vehicleData.plate;
        contractData.vehicleTypeID    = vehicleData.vehicleTypeID;
        contractData.vehicleTypeName  = vehicleData.vehicleTypeName;
        contractData.vehicleBrandID   = vehicleData.vehicleBrandID;
        contractData.vehicleBrandName = vehicleData.vehicleBrandName;
        contractData.vehicleModelID   = vehicleData.vehicleModelID;
        contractData.vehicleModelName = vehicleData.vehicleModelName;
        contractData.vehicleColorID   = vehicleData.vehicleColorID;
        contractData.vehicleColorName = vehicleData.vehicleColorName;
        contractData.vehicleBlocked   = vehicleData.blocked;
      END IF;
    ELSE
      contractData.vehicleID        = NULL;
      contractData.plate            = NULL;
      contractData.vehicleTypeID    = NULL;
      contractData.vehicleTypeName  = NULL;
      contractData.vehicleBrandID   = NULL;
      contractData.vehicleBrandName = NULL;
      contractData.vehicleModelID   = NULL;
      contractData.vehicleModelName = NULL;
      contractData.vehicleColorID   = NULL;
      contractData.vehicleColorName = NULL;
    END IF;

    RETURN NEXT contractData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Permissões
-- ---------------------------------------------------------------------
-- As informações de permissões aqui estão com o índice na sequência
-- exatamente superior ao último inserido. Na definição, ele entra em
-- sequência para facilitar a amplicação.

-- Incluída nova permissão para obtenção de dados de e-mails
INSERT INTO erp.permissions (permissionID, name, description) VALUES
  ( 406, 'ERP\Financial\Payments\Get\MailData',
    'Recupera as informações de e-mails enviados');
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (406, 'PATCH')) y(permissionID, method));

-- Incluída nova permissão para obtenção de dados de taxas cobradas
INSERT INTO erp.permissions (permissionID, name, description) VALUES
  ( 407, 'ERP\Financial\Payments\Get\TariffData',
    'Recupera as informações de tarifas cobradas');
INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM (VALUES (1),(2)) x(groupID),
          (VALUES (407, 'PATCH')) y(permissionID, method));
