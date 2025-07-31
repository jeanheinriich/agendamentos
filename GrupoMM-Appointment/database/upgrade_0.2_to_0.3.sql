ALTER TABLE erp.vehicles DROP COLUMN cityOfPlateID;

DROP FUNCTION erp.getVehiclesData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FcityOfPlateID integer,
  FvehicleID integer, FsearchValue varchar(100), FsearchField varchar(20),
  FOrder varchar, Skip integer, LimitOf integer);

DROP TYPE erp.vehicleData;

-- Cria uma stored procedure que recupera as informações de veículos
-- para o gerenciamento de veículos
CREATE TYPE erp.vehicleData AS
(
  vehicleID          integer,
  contractorID       integer,
  contractorName     varchar(100),
  contractorBlocked  boolean,
  customerID         integer,
  customerName       varchar(100),
  customerBlocked    boolean,
  customerTypeID     integer,
  customerTypeName   varchar(30),
  juridicalperson    boolean,
  subsidiaryID       integer,
  subsidiaryName     varchar(50),
  subsidiaryBlocked  boolean,
  plate              varchar(7),
  vehicleTypeID      integer,
  vehicleTypeName    varchar(30),
  vehicleBrandID     integer,
  vehicleBrandName   varchar(30),
  vehicleModelID     integer,
  vehicleModelName   varchar(50),
  vehicleColorID     integer,
  vehicleColorName   varchar(30),
  vehicleColor       varchar(30),
  carNumber          varchar(20),
  fuelType           char(1),
  fuelTypeName       varchar(30),
  vehicleBlocked     boolean,
  blockedLevel       smallint,
  createdAt          timestamp,
  updatedAt          timestamp,
  fullcount          integer
);

CREATE OR REPLACE FUNCTION erp.getVehiclesData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FvehicleID integer,
  FsearchValue varchar(100), FsearchField varchar(20),
  FOrder varchar, Skip integer, LimitOf integer)
RETURNS SETOF erp.vehicleData AS
$$
DECLARE
  vehicleData  erp.vehicleData%rowtype;
  row          record;
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
  IF (FvehicleID IS NULL) THEN
    FvehicleID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'customer.name ASC, subsidiary.subsidiaryid ASC, vehicles.plate ASC';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';
  
  IF (FvehicleID > 0) THEN
    filter := format(' AND vehicles.vehicleID = %s',
                    FvehicleID);
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

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- Determina o campo onde será realizada a pesquisa
      CASE (FsearchField)
        WHEN 'vehicleBrandName' THEN
          field := 'vehicleBrands.name';
        WHEN 'vehicleModelName' THEN
          field := 'vehicleModels.name';
        ELSE
          field := 'vehicles.' || FsearchField;
      END CASE;
      -- Monta o filtro
      filter := filter || format(' AND public.unaccented(%s) ILIKE public.unaccented(''%%%s%%'')',
                                  field, FsearchValue);
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  -- Monta a consulta
  query := format('SELECT vehicles.vehicleID,
                          vehicles.contractorID,
                          contractor.name AS contractorName,
                          contractor.blocked AS contractorBlocked,
                          vehicles.customerID,
                          customer.name AS customerName,
                          customer.blocked AS customerBlocked,
                          customer.entityTypeID AS customerTypeID,
                          customerType.name AS customerTypeName,
                          customerType.juridicalperson AS juridicalperson,
                          vehicles.subsidiaryID,
                          subsidiary.name AS subsidiaryName,
                          subsidiary.blocked AS subsidiaryBlocked,
                          vehicles.plate,
                          vehicles.vehicleTypeID,
                          vehicleTypes.name AS vehicleTypeName,
                          vehicles.vehicleBrandID,
                          vehicleBrands.name AS vehicleBrandName,
                          vehicles.vehicleModelID,
                          vehicleModels.name AS vehicleModelName,
                          vehicles.vehicleColorID,
                          vehicleColors.name AS vehicleColorName,
                          vehicleColors.color AS vehicleColor,
                          vehicles.carNumber,
                          vehicles.fuelType,
                          fuelTypes.name AS fuelTypeName,
                          vehicles.blocked AS vehicleBlocked,
                          vehicles.createdAt,
                          vehicles.updatedAt,
                          count(*) OVER() AS fullcount
                     FROM erp.vehicles
                    INNER JOIN erp.entities AS contractor ON (vehicles.contractorID = contractor.entityID)
                    INNER JOIN erp.entities AS customer ON (vehicles.customerID = customer.entityID)
                    INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
                    INNER JOIN erp.subsidiaries AS subsidiary ON (vehicles.subsidiaryID = subsidiary.subsidiaryID)
                    INNER JOIN erp.vehicleTypes ON (vehicles.vehicleTypeID = vehicleTypes.vehicleTypeID)
                    INNER JOIN erp.vehicleBrands ON (vehicles.vehicleBrandID = vehicleBrands.vehicleBrandID)
                    INNER JOIN erp.vehicleModels ON (vehicles.vehicleModelID = vehicleModels.vehicleModelID)
                    INNER JOIN erp.vehicleColors ON (vehicles.vehicleColorID = vehicleColors.vehicleColorID)
                    INNER JOIN erp.fuelTypes ON (vehicles.fuelType = fuelTypes.fuelType)
                    WHERE vehicles.contractorID = %s %s
                    ORDER BY %s %s',
                  fContractorID, filter, FOrder, limits);
  FOR row IN EXECUTE query
  LOOP
    vehicleData.vehicleID          := row.vehicleID;
    vehicleData.contractorID       := row.contractorID;
    vehicleData.contractorName     := row.contractorName;
    vehicleData.contractorBlocked  := row.contractorBlocked;
    vehicleData.customerID         := row.customerID;
    vehicleData.customerName       := row.customerName;
    vehicleData.customerBlocked    := row.customerBlocked;
    vehicleData.customerTypeID     := row.customerTypeID;
    vehicleData.customerTypeName   := row.customerTypeName;
    vehicleData.juridicalperson    := row.juridicalperson;
    vehicleData.subsidiaryID       := row.subsidiaryID;
    vehicleData.subsidiaryName     := row.subsidiaryName;
    vehicleData.subsidiaryBlocked  := row.subsidiaryBlocked;
    vehicleData.plate              := row.plate;
    vehicleData.vehicleTypeID      := row.vehicleTypeID;
    vehicleData.vehicleTypeName    := row.vehicleTypeName;
    vehicleData.vehicleBrandID     := row.vehicleBrandID;
    vehicleData.vehicleBrandName   := row.vehicleBrandName;
    vehicleData.vehicleModelID     := row.vehicleModelID;
    vehicleData.vehicleModelName   := row.vehicleModelName;
    vehicleData.vehicleColorID     := row.vehicleColorID;
    vehicleData.vehicleColorName   := row.vehicleColorName;
    vehicleData.vehicleColor       := row.vehicleColor;
    vehicleData.carNumber          := row.carNumber;
    vehicleData.fuelType           := row.fuelType;
    vehicleData.fuelTypeName       := row.fuelTypeName;
    vehicleData.vehicleBlocked     := row.vehicleBlocked;
    -- RAISE NOTICE 'Contractor %', row.contractorBlocked;
    -- RAISE NOTICE 'Vehicle %', row.vehicleBlocked;
    -- RAISE NOTICE 'Subsidiary %', row.subsidiaryBlocked;

    -- Determina o nível de bloqueio. O nível de bloqueio mais baixo é o
    -- do veículo, seguido da unidade/filial do cliente, da empresa e
    -- por último o contratante
    blockedLevel := 0;
    IF (row.vehicleBlocked) THEN
      blockedLevel := blockedLevel|1;
    END IF;
    IF (row.subsidiaryBlocked) THEN
      blockedLevel := blockedLevel|2;
    END IF;
    IF (row.customerBlocked) THEN
      blockedLevel := blockedLevel|4;
    END IF;
    IF (row.contractorBlocked) THEN
      blockedLevel := blockedLevel|8;
    END IF;
    vehicleData.blockedLevel       := blockedLevel;
    vehicleData.createdAt          := row.createdAt;
    vehicleData.updatedAt          := row.updatedAt;
    vehicleData.fullcount          := row.fullcount;

    RETURN NEXT vehicleData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- Alteramos os horários padrão de início das jornadas diúrnas e noturnas
ALTER TABLE stc.customers ALTER COLUMN startDayTime SET DEFAULT '05:00:00';
ALTER TABLE stc.customers ALTER COLUMN startNightTime SET DEFAULT '22:00:00';
