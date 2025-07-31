-- =====================================================================
-- Dados de contratos
-- =====================================================================
-- Funções para exibição dos dados dos contratos.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Dados do veículo mais recente em uma instalação
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações do veículo mais
-- recente em uma instalação para indicação nos dados do contrato.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getMostRecentVehicleOnInstallation(
  FcontractorID integer, FinstallationID integer
) RETURNS TABLE (
  installationID  int,
  vehicleID  int,
  plate  varchar(7),
  vehicleTypeID  integer,
  vehicleTypeName  varchar(30),
  vehicleBrandID  integer,
  vehicleBrandName  varchar(30),
  vehicleModelID  integer,
  vehicleModelName  varchar(50),
  vehicleColorID  integer,
  vehicleColorName  varchar(30),
  blocked  boolean
) AS
$$
BEGIN
  -- Selecionamos o registro mais recente levando em consideração o
  -- carro que possua um rastreador em operação (vinculado) e, se não
  -- existir, àquele que foi desinstalado por último
  RETURN QUERY
    SELECT DISTINCT ON (installationID)
           vehicle.installationID,
           vehicle.vehicleID,
           vehicle.plate,
           vehicle.vehicleTypeID,
           vehicle.vehicleTypeName,
           vehicle.vehicleBrandID,
           vehicle.vehicleBrandName,
           vehicle.vehicleModelID,
           vehicle.vehicleModelName,
           vehicle.vehicleColorID,
           vehicle.vehicleColorName,
           vehicle.blocked
      FROM (
      (SELECT R.installationID,
              1 AS tag,
              R.vehicleID,
              V.plate,
              R.installedAt,
              R.uninstalledAt,
              V.vehicleTypeID,
              T.name AS vehicleTypeName,
              V.vehicleBrandID,
              B.name AS vehicleBrandName,
              V.vehicleModelID,
              M.name AS vehicleModelName,
              V.vehicleColorID,
              C.name AS vehicleColorName,
              V.blocked
         FROM erp.installationRecords AS R
        INNER JOIN erp.vehicles AS V USING (vehicleID)
        INNER JOIN erp.vehicleTypes AS T ON (V.vehicleTypeID = T.vehicleTypeID)
        INNER JOIN erp.vehicleBrands AS B ON (V.vehicleBrandID = B.vehicleBrandID)
        INNER JOIN erp.vehicleModels AS M ON (V.vehicleModelID = M.vehicleModelID)
        INNER JOIN erp.vehicleColors AS C ON (V.vehicleColorID = C.vehicleColorID)
        WHERE R.contractorID = FcontractorID
          AND R.installationID = FinstallationID
        ORDER BY R.uninstalledAt DESC)
        UNION
      (SELECT FinstallationID AS installationID,
              2 AS tag,
              0 AS vehicleID,
              NULL AS plate,
              NULL AS installedAt,
              NULL AS uninstalledAt,
              0 AS vehicleTypeID,
              NULL AS vehicleTypeName,
              0 AS vehicleBrandID,
              NULL AS vehicleBrandName,
              0 AS vehicleModelID,
              NULL AS vehicleModelName,
              0 AS vehicleColorID,
              NULL AS vehicleColorName,
              FALSE AS blocked)
        ORDER BY tag, uninstalledAt DESC
        ) AS vehicle;
END;
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Dados do contrato
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de contratos e
-- instalações para o gerenciamento de contratos
-- ---------------------------------------------------------------------
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
  FtoCarnet boolean, FonlyActive boolean, FOrder varchar, Skip integer,
  LimitOf integer)
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
  IF (FonlyActive IS NULL) THEN
    FonlyActive = FALSE;
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
      filter := format(' AND contracts.customerID = %s',
                      FcustomerID);
      IF (FsubsidiaryID > 0) THEN
        filter := filter || format(' AND contracts.subsidiaryID = %s',
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

  IF (FonlyActive) THEN
    -- Incluímos apenas contratos que não estejam encerrados
    filter := filter || ' AND contracts.endDate IS NULL AND installations.endDate IS NULL';
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
      ELSIF (FsearchField = 'associate') THEN
        filter := filter || 
          format(' AND installations.installationID IN ('
            || 'SELECT E.installationID'
            || '  FROM erp.equipments AS E '
            || ' INNER JOIN erp.vehicles AS V USING (vehicleID)'
            || ' WHERE E.storageLocation = ''Installed'''
            || '   AND E.customerPayerID = %s'
            || '   AND E.subsidiaryPayerID = %s'
            || '   AND V.customerID = %s'
            || ' GROUP BY E.installationID)',
          FcustomerID, FsubsidiaryID, FsearchValue);
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
                          erp.getContractNumber(contracts.createdat) AS contractNumber,
                          contracts.planID,
                          plans.name AS planName,
                          contracts.subscriptionPlanID,
                          dueDays.day AS dueDay,
                          contracts.signaturedate,
                          contracts.enddate AS contractenddate,
                          contracts.paymentConditionID,
                          paymentConditions.name AS paymentConditionName,
                          CASE
                            WHEN paymentConditions.timeunit = ''MONTH'' AND paymentConditions.paymentformid = 2 AND paymentConditions.paymentmethodid = 5 THEN array_upper(string_to_array(paymentConditions.paymentinterval, ''/'')::int[], 1)
                            ELSE subscriptionPlans.numberOfMonths
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
                          vehicle.vehicleID,
                          vehicle.plate,
                          vehicle.vehicleTypeID,
                          vehicle.vehicleTypeName,
                          vehicle.vehicleBrandID,
                          vehicle.vehicleBrandName,
                          vehicle.vehicleModelID,
                          vehicle.vehicleModelName,
                          vehicle.vehicleColorID,
                          vehicle.vehicleColorName,
                          vehicle.blocked AS vehicleBlocked,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID
                                     AND uninstalledAt IS NULL) AS tracked,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID) AS containsTrackingData,
                          count(*) OVER() AS fullcount
                     FROM erp.contracts
                    INNER JOIN erp.subscriptionPlans USING (subscriptionPlanID)
                    INNER JOIN erp.entities AS contractor ON (contracts.contractorID = contractor.entityID)
                    INNER JOIN erp.entities AS customer ON (contracts.customerID = customer.entityID)
                    INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
                    INNER JOIN erp.subsidiaries AS subsidiary ON (contracts.subsidiaryID = subsidiary.subsidiaryID)
                    INNER JOIN erp.dueDays ON (contracts.dueDayID = dueDays.dueDayID)
                    INNER JOIN erp.paymentConditions ON (contracts.paymentConditionID = paymentConditions.paymentConditionID)
                    INNER JOIN erp.plans ON (contracts.planID = plans.planID)
                    INNER JOIN erp.installations ON (contracts.contractID = installations.contractID)
                    INNER JOIN erp.getMostRecentVehicleOnInstallation(contracts.contractorID, installations.installationid) AS vehicle ON (installations.installationID = vehicle.installationID)
                    WHERE contracts.contractorID = %s
                      AND contracts.deleted = false
                      AND customer.deleted = false
                      AND subsidiary.deleted = false %s
                    ORDER BY %s %s',
                  fContractorID, filter, FOrder, limits);
  -- RAISE NOTICE 'Query: %', query;
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
    contractData.vehicleID                  := row.vehicleID;
    contractData.plate                      := row.plate;
    contractData.vehicleTypeID              := row.vehicleTypeID;
    contractData.vehicleTypeName            := row.vehicleTypeName;
    contractData.vehicleBrandID             := row.vehicleBrandID;
    contractData.vehicleBrandName           := row.vehicleBrandName;
    contractData.vehicleModelID             := row.vehicleModelID;
    contractData.vehicleModelName           := row.vehicleModelName;
    contractData.vehicleColorID             := row.vehicleColorID;
    contractData.vehicleColorName           := row.vehicleColorName;
    contractData.vehicleBlocked             := row.vehicleBlocked;

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

    RETURN NEXT contractData;
  END loop;
END
$$
LANGUAGE 'plpgsql';


-- ---------------------------------------------------------------------
-- Dados da instalação
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de uma instalação
-- ---------------------------------------------------------------------
CREATE TYPE erp.installationData AS
(
  contractID           integer,
  installationID       integer,
  installationNumber   varchar(12),
  vehicleID            integer,
  plate                varchar(7),
  startDate            date,
  endDate              date,
  noTracker            boolean,
  containsTrackingData boolean,
  suspended            boolean,
  finish               boolean
);

CREATE OR REPLACE FUNCTION erp.getInstallationsData(FcontractID integer,
  FincludeSuspended boolean, FincludeFinish boolean,
  FincludeInstallationID integer)
RETURNS SETOF erp.installationData AS
$$
DECLARE
  installationData  erp.installationData%rowtype;
  row  record;
  query  varchar;
  filter varchar;
  vehicleData  record;
BEGIN
  IF (FcontractID IS NULL) THEN
    FcontractID = 0;
  END IF;
  IF (FincludeSuspended IS NULL) THEN
    FincludeSuspended = FALSE;
  END IF;
  IF (FincludeFinish IS NULL) THEN
    FincludeFinish = FALSE;
  END IF;
  IF (FincludeInstallationID IS NULL) THEN
    FincludeInstallationID = 0;
  END IF;

  filter := '';
  IF (NOT FincludeSuspended) THEN
    filter := filter || ' AND contracts.active = true';
  END IF;
  IF (NOT FincludeFinish) THEN
    filter := filter || ' AND installations.endDate IS NULL';
  END IF;
  IF (FincludeInstallationID > 0) THEN
    filter := filter || ' OR installations.installationID = ' || FincludeInstallationID;
  END IF;

  -- Monta a consulta
  query := format('SELECT contracts.contractID,
                          contractor.blocked AS contractorBlocked,
                          contracts.signaturedate,
                          contracts.startTermAfterInstallation,
                          contracts.active AS contractActive,
                          installations.installationID,
                          installations.installationNumber,
                          installations.startDate,
                          installations.endDate,
                          CASE
                            WHEN installations.endDate IS NULL THEN FALSE
                            ELSE TRUE
                          END AS finish,
                          vehicle.vehicleID,
                          vehicle.plate,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID
                                     AND uninstalledAt IS NULL) AS tracked,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID) AS containsTrackingData
                     FROM erp.contracts
                    INNER JOIN erp.entities AS contractor ON (contracts.contractorID = contractor.entityID)
                    INNER JOIN erp.entities AS customer ON (contracts.customerID = customer.entityID)
                    INNER JOIN erp.subsidiaries AS subsidiary ON (contracts.subsidiaryID = subsidiary.subsidiaryID)
                    INNER JOIN erp.installations ON (contracts.contractID = installations.contractID)
                    INNER JOIN erp.getMostRecentVehicleOnInstallation(contracts.contractorID, installations.installationid) AS vehicle ON (installations.installationID = vehicle.installationID)
                    WHERE contracts.contractID = %s
                      AND contracts.deleted = false
                      AND customer.deleted = false
                      AND subsidiary.deleted = false %s
                    ORDER BY 8 NULLS FIRST, 9 ASC, 7',
                  FContractID, filter);
  FOR row IN EXECUTE query
  LOOP
    installationData.contractID           := row.contractID;
    installationData.installationID       := row.installationID;
    installationData.installationNumber   := row.installationNumber;
    installationData.startDate            := row.startDate;
    installationData.endDate              := row.endDate;
    installationData.noTracker            := NOT row.tracked;
    installationData.containsTrackingData := row.containsTrackingData;
    installationData.suspended            := NOT row.contractActive;
    installationData.finish               := row.finish;
    installationData.vehicleID            := row.vehicleID;
    installationData.plate                := row.plate;

    RETURN NEXT installationData;
  END loop;
END
$$
LANGUAGE 'plpgsql';
