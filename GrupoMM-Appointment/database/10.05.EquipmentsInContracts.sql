-- =====================================================================
-- Equipamentos em contratos
-- =====================================================================
-- Funções para manipulação de equipamentos que podem estar relacionados
-- com os contratos e e/ou os registros das instalações nos contratos de
-- clientes.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Dados de equipamentos
-- ---------------------------------------------------------------------
-- Obtém os dados do equipamento de rastreamento, incluindo a informação
-- de onde ele está instalado.
-- ---------------------------------------------------------------------
CREATE TYPE erp.equipmentData AS
(
  equipmentID        integer,
  contractorID       integer,
  contractorName     varchar(100),
  assignedToID       integer,
  assignedToName     varchar(100),
  leasedEquipment    boolean,
  leasedingEquipment boolean,
  supplierID         integer,
  supplierName       varchar(100),
  supplierBlocked    boolean,
  juridicalperson    boolean,
  subsidiaryID       integer,
  subsidiaryName     varchar(50),
  subsidiaryBlocked  boolean,
  imei               varchar(18),
  serialNumber       varchar(30),
  equipmentModelID   integer,
  equipmentModelName varchar(50),
  equipmentBrandID   integer,
  equipmentBrandName varchar(30),
  maxSimCards        smallint,
  assetNumber        varchar(20),
  attached           boolean,
  vehicleID          integer,
  plate              varchar(7),
  installationID     integer,
  installationNumber varchar(12),
  equipmentBlocked   boolean,
  stateID            integer,
  stateName          varchar(30),
  blockedLevel       smallint,
  createdAt          timestamp,
  updatedAt          timestamp,
  fullcount          integer
);

CREATE OR REPLACE FUNCTION erp.getEquipmentsData(FcontractorID integer,
  FsupplierID integer, FsubsidiaryID integer, FequipmentID integer,
  FsearchValue varchar(100), FsearchField varchar(20), FequipmentModelID integer,
  FstorageLocation varchar(30), FstorageID integer, FOrder varchar,
  Skip integer, LimitOf integer)
RETURNS SETOF erp.equipmentData AS
$$
DECLARE
  equipmentData  erp.equipmentData%rowtype;
  row            record;
  query          varchar;
  field          varchar;
  filter         varchar;
  limits         varchar;
  blockedLevel   integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FsupplierID IS NULL) THEN
    FsupplierID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FequipmentID IS NULL) THEN
    FequipmentID = 0;
  END IF;
  IF (FequipmentModelID IS NULL) THEN
    FequipmentModelID = 0;
  END IF;
  IF (FstorageLocation IS NULL) THEN
    FstorageLocation = 'Any';
  END IF;
  IF (FstorageID IS NULL) THEN
    FstorageID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'suppliername ASC, subsidiaryname ASC, serialnumber ASC';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  
  filter := '';
  IF (FequipmentID > 0) THEN
    filter := format(' AND equipments.equipmentID = %s',
                    FequipmentID);
  ELSE
    -- Realiza a filtragem por cliente
    IF (FsupplierID > 0) THEN
      filter := format(' AND supplier.entityID = %s',
                      FsupplierID);
      IF (FsubsidiaryID > 0) THEN
        filter := filter || format(' AND subsidiary.subsidiaryID = %s',
                                  FsubsidiaryID);
      END IF;
    END IF;
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      IF (FsearchField = 'plate') THEN
        -- Localiza por placa
        filter := filter || format(' AND vehicles.plate ILIKE ''%%%s%%''',
                                   FsearchValue);
      ELSE
        -- Determina o campo onde será realizada a pesquisa
        field := 'equipments.' || FsearchField;
        -- Monta o filtro
        filter := filter || format(' AND public.unaccented(%s) ILIKE public.unaccented(''%%%s%%'')',
                                    field, FsearchValue);
      END IF;
    END IF;
  END IF;

  IF (FequipmentModelID > 0) THEN
    filter := filter || format(' AND equipments.equipmentModelID = %s',
                    FequipmentModelID);
  END IF;

  CASE (FstorageLocation)
    WHEN 'Installed' THEN
      -- Monta o filtro
      filter := filter || format(' AND equipments.storageLocation = ''%s''', FstorageLocation);
    WHEN 'StoredOnDeposit' THEN
      -- Monta o filtro
      filter := filter || format(' AND equipments.storageLocation = ''%s'' AND equipments.depositID = %s', FstorageLocation, FstorageID);
    WHEN 'StoredWithTechnician' THEN
      -- Monta o filtro
      filter := filter || format(' AND equipments.storageLocation = ''%s'' AND equipments.technicianID = %s', FstorageLocation, FstorageID);
    WHEN 'StoredWithServiceProvider' THEN
      -- Monta o filtro
      filter := filter || format(' AND equipments.storageLocation = ''%s'' AND equipments.serviceProviderID = %s', FstorageLocation, FstorageID);
    ELSE
      -- Não faz nada
  END CASE;
  -- RAISE NOTICE 'filter IS %', filter;

  -- Monta a consulta
  query := format('SELECT * FROM (
                     SELECT equipments.equipmentID,
                            equipments.assignedToID,
                            equipments.contractorID,
                            contractor.name AS contractorName,
                            contractor.blocked AS contractorBlocked,
                            equipments.assignedToID,
                            assignedTo.name AS assignedToName,
                            CASE
                              WHEN equipments.assignedToID = %s THEN TRUE
                              ELSE FALSE
                            END AS leasedEquipment,
                            CASE
                              WHEN equipments.contractorID = %s AND equipments.assignedToID IS NOT NULL THEN TRUE
                              ELSE FALSE
                            END AS leasedingEquipment,
                            equipments.supplierID,
                            CASE
                              WHEN equipments.assignedToID = %s THEN contractor.name
                              ELSE supplier.name
                            END AS supplierName,
                            CASE 
                              WHEN equipments.assignedToID = %s THEN contractor.blocked
                              ELSE supplier.blocked
                            END AS supplierBlocked,   
                            CASE 
                              WHEN equipments.assignedToID = %s THEN assignedToType.juridicalperson
                              ELSE supplierType.juridicalperson
                            END AS juridicalperson,
                            equipments.subsidiaryID,
                            subsidiary.name AS subsidiaryName,
                            subsidiary.blocked AS subsidiaryBlocked,
                            equipments.imei,
                            equipments.serialNumber,
                            equipments.equipmentModelID,
                            equipmentModels.name AS equipmentModelName,
                            equipmentModels.equipmentBrandID,
                            equipmentBrands.name AS equipmentBrandName,
                            equipmentModels.maxSimCards,
                            equipments.assetNumber,
                            equipments.storageLocation = ''Installed'' AS attached,
                            equipments.vehicleID,
                            CASE
                              WHEN equipments.storageLocation = ''Installed'' THEN vehicles.plate
                              ELSE ''''
                            END AS plate,
                            equipments.installationID,
                            CASE
                              WHEN equipments.storageLocation = ''Installed'' THEN installations.installationNumber
                              ELSE ''''
                            END AS installationNumber,
                            equipments.blocked AS equipmentBlocked,
                            equipments.equipmentStateID,
                            equipmentStates.name AS equipmentStateName,
                            equipments.createdAt,
                            equipments.updatedAt,
                            count(*) OVER() AS fullcount
                       FROM erp.equipments
                      INNER JOIN erp.entities AS contractor ON (equipments.contractorID = contractor.entityID)
                      INNER JOIN erp.entities AS supplier ON (equipments.supplierID = supplier.entityID)
                      INNER JOIN erp.entitiesTypes AS supplierType ON (supplier.entityTypeID = supplierType.entityTypeID)
                      INNER JOIN erp.subsidiaries AS subsidiary ON (equipments.subsidiaryID = subsidiary.subsidiaryID)
                      INNER JOIN erp.equipmentModels USING (equipmentModelID)
                      INNER JOIN erp.equipmentBrands ON (equipmentModels.equipmentBrandID = equipmentBrands.equipmentBrandID)
                      INNER JOIN erp.equipmentStates USING (equipmentStateID)
                       LEFT JOIN erp.entities AS assignedTo ON (equipments.assignedToID = assignedTo.entityID)
                       LEFT JOIN erp.entitiesTypes AS assignedToType ON (assignedTo.entityTypeID = assignedToType.entityTypeID)
                       LEFT JOIN erp.vehicles USING (vehicleID)
                       LEFT JOIN erp.installations USING (installationID)
                      WHERE (equipments.contractorID = %s OR equipments.assignedToID = %s) %s
                    ) AS equipment
                    ORDER BY %s %s',
                  fContractorID, fContractorID, fContractorID,
                  fContractorID, fContractorID, fContractorID,
                  fContractorID, filter, FOrder, limits);
  FOR row IN EXECUTE query
  LOOP
    equipmentData.equipmentID        := row.equipmentID;
    equipmentData.contractorID       := row.contractorID;
    equipmentData.contractorName     := row.contractorName;
    equipmentData.assignedToID       := row.assignedToID;
    equipmentData.assignedToName     := row.assignedToName;
    equipmentData.leasedEquipment    := row.leasedEquipment;
    equipmentData.leasedingEquipment := row.leasedingEquipment;
    equipmentData.supplierID         := row.supplierID;
    equipmentData.supplierName       := row.supplierName;
    equipmentData.supplierBlocked    := row.supplierBlocked;
    equipmentData.juridicalperson    := row.juridicalperson;
    equipmentData.subsidiaryID       := row.subsidiaryID;
    equipmentData.subsidiaryName     := row.subsidiaryName;
    equipmentData.subsidiaryBlocked  := row.subsidiaryBlocked;
    equipmentData.imei               := row.imei;
    equipmentData.serialNumber       := row.serialNumber;
    equipmentData.equipmentModelID   := row.equipmentModelID;
    equipmentData.equipmentModelName := row.equipmentModelName;
    equipmentData.equipmentBrandID   := row.equipmentBrandID;
    equipmentData.equipmentBrandName := row.equipmentBrandName;
    equipmentData.maxSimCards        := row.maxSimCards;
    equipmentData.assetNumber        := row.assetNumber;
    equipmentData.attached           := row.attached;
    equipmentData.vehicleID          := row.vehicleID;
    equipmentData.plate              := row.plate;
    equipmentData.installationID     := row.installationID;
    equipmentData.installationNumber := row.installationNumber;
    equipmentData.equipmentBlocked   := row.equipmentBlocked;
    -- RAISE NOTICE 'Contractor %';
    -- RAISE NOTICE 'SIM Card %', row.equipmentBlocked;
    -- RAISE NOTICE 'Subsidiary %', row.subsidiaryBlocked;

    -- Determina o nível de bloqueio. O nível de bloqueio mais baixo é o
    -- do equipamento, seguido da unidade/filial do fornecedor, da
    -- empresa (fornecedor) e por último o contratante
    blockedLevel := 0;
    IF (row.equipmentBlocked) THEN
      blockedLevel := blockedLevel|1;
    END IF;
    IF (row.subsidiaryBlocked) THEN
      blockedLevel := blockedLevel|2;
    END IF;
    IF (row.supplierBlocked) THEN
      blockedLevel := blockedLevel|4;
    END IF;
    IF (row.contractorBlocked) THEN
      blockedLevel := blockedLevel|8;
    END IF;
    equipmentData.blockedLevel       := blockedLevel;
    equipmentData.stateID            := row.equipmentStateID;
    equipmentData.stateName          := row.equipmentStateName;
    equipmentData.createdAt          := row.createdAt;
    equipmentData.updatedAt          := row.updatedAt;
    equipmentData.fullcount          := row.fullcount;

    RETURN NEXT equipmentData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Equipamentos instalados por veículo
-- ---------------------------------------------------------------------
-- Obtém os dados dos equipamentos instalados em um veículo, incluindo
-- as informações do registro da instalação no contrato.
-- ---------------------------------------------------------------------
CREATE TYPE erp.equipmentPerVehicleData AS
(
  vehicleID              integer,
  plate                  varchar(7),
  equipmentID            integer,
  brandName              char(30),
  modelName              char(50),
  imei                   char(18),
  serialNumber           varchar(30),
  customerPayerID        integer,
  customerPayerName      varchar(100),
  subsidiaryPayerID      integer,
  subsidiaryPayerName    varchar(50),
  nationalRegister       varchar(18),
  installedAt            date,
  installationID         integer,
  installationNumber     varchar(12),
  main                   boolean,
  installationSite       varchar(100),
  hasBlocking            boolean,
  blockingSite           varchar(100),
  hasIButton             boolean,
  iButtonSite            varchar(100),
  hasSiren               boolean,
  sirenSite              varchar(100),
  panicButtonSite        varchar(100)
);

CREATE OR REPLACE FUNCTION erp.getEquipmentsPerVehicleData(FcontractorID integer,
  FvehicleID integer)
RETURNS SETOF erp.equipmentPerVehicleData AS
$$
DECLARE
  equipmentPerVehicleData  erp.equipmentPerVehicleData%rowtype;
  row                      record;
  query                    varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FvehicleID IS NULL) THEN
    FvehicleID = 0;
  END IF;

  -- Monta a consulta
  query := 'SELECT VEHICLE.vehicleID,
                   VEHICLE.plate,
                   EQPTO.equipmentID,
                   MODEL.name AS modelName,
                   BRAND.name AS brandName,
                   EQPTO.imei,
                   EQPTO.serialNumber,
                   EQPTO.installationID,
                   CASE
                     WHEN EQPTO.installationID > 0 THEN I.installationNumber
                     ELSE ''''
                   END AS installationNumber,
                   EQPTO.customerPayerID,
                   CUSTOMER.name AS customerPayerName,
                   EQPTO.subsidiaryPayerID,
                   SUBSIDIARY.name AS subsidiaryPayerName,
                   SUBSIDIARY.nationalregister,
                   R.installedAt,
                   EQPTO.main,
                   EQPTO.installationSite,
                   EQPTO.hasBlocking,
                   EQPTO.blockingSite,
                   EQPTO.hasIButton,
                   EQPTO.iButtonSite,
                   EQPTO.hasSiren,
                   EQPTO.sirenSite,
                   EQPTO.panicButtonSite
              FROM erp.vehicles AS VEHICLE
              LEFT JOIN erp.equipments AS EQPTO
                     ON VEHICLE.vehicleid = EQPTO.vehicleid
              LEFT JOIN erp.equipmentmodels AS MODEL
                  USING (equipmentModelID)
              LEFT JOIN erp.installationRecords AS R
                     ON (VEHICLE.vehicleid = R.vehicleid AND
                         EQPTO.equipmentID = R.equipmentID AND
                         R.uninstalledAt IS NULL)
              LEFT JOIN erp.installations AS I
                     ON EQPTO.installationID = I.installationID
              LEFT JOIN erp.entities AS CUSTOMER
                     ON EQPTO.customerPayerID = CUSTOMER.entityid
              LEFT JOIN erp.subsidiaries AS SUBSIDIARY
                     ON EQPTO.subsidiaryPayerID = SUBSIDIARY.subsidiaryID
             INNER JOIN erp.equipmentbrands AS BRAND
                  USING (equipmentBrandID)
             WHERE VEHICLE.contractorID = $1
               AND VEHICLE.vehicleID = $2
             ORDER BY EQPTO.main DESC NULLS LAST, EQPTO.installedAt'
  ;

  FOR row IN EXECUTE query USING FcontractorID, FvehicleID
  LOOP
    equipmentPerVehicleData.vehicleID           := row.vehicleID;
    equipmentPerVehicleData.plate               := row.plate;
    equipmentPerVehicleData.equipmentID         := row.equipmentID;
    equipmentPerVehicleData.brandName           := row.brandName;
    equipmentPerVehicleData.modelName           := row.modelName;
    equipmentPerVehicleData.imei                := row.imei;
    equipmentPerVehicleData.serialNumber        := row.serialNumber;
    equipmentPerVehicleData.installationID      := row.installationID;
    equipmentPerVehicleData.installationNumber  := row.installationNumber;
    equipmentPerVehicleData.customerPayerID     := row.customerPayerID;
    equipmentPerVehicleData.customerPayerName   := row.customerPayerName;
    equipmentPerVehicleData.subsidiaryPayerID   := row.subsidiaryPayerID;
    equipmentPerVehicleData.subsidiaryPayerName := row.subsidiaryPayerName;
    equipmentPerVehicleData.nationalRegister    := row.nationalRegister;
    equipmentPerVehicleData.installedAt         := row.installedAt;
    equipmentPerVehicleData.installationSite    := row.installationSite;
    equipmentPerVehicleData.main                := row.main;
    equipmentPerVehicleData.hasBlocking         := row.hasBlocking;
    equipmentPerVehicleData.blockingSite        := row.blockingSite;
    equipmentPerVehicleData.hasIButton          := row.hasIButton;
    equipmentPerVehicleData.iButtonSite         := row.iButtonSite;
    equipmentPerVehicleData.hasSiren            := row.hasSiren;
    equipmentPerVehicleData.sirenSite           := row.sirenSite;
    equipmentPerVehicleData.panicButtonSite     := row.panicButtonSite;

    RETURN NEXT equipmentPerVehicleData;
  END loop;
END
$$
LANGUAGE 'plpgsql';
