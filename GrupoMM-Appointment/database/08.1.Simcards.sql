-- =====================================================================
-- Simcards
-- =====================================================================
-- Tabelas utilizada no controle de simcards
-- =====================================================================

-- ---------------------------------------------------------------------
-- SIM Cards
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.simcards (
  simcardID         serial,         -- ID do SIM Card
  contractorID      integer         -- ID do contratante
                    NOT NULL,
  assignedToID      integer         -- ID do contratante para quem o
                    DEFAULT NULL,   -- simcard foi comodatado
  iccID             varchar(20)     -- ICCID (Integrated Circuit Card ID)
                    NOT NULL,
  imsi              varchar(15),    -- IMSI (International Mobile Suscriber
                                    -- Identity)
  phoneNumber       varchar(20),    -- Número do telefone associado
  mobileOperatorID  integer         -- Número de identificação da operadora
                    DEFAULT NULL,   -- de telefonia móvel a qual pertence
                                    -- o SIM Card
  simcardTypeID     integer         -- O tipo (modelo) do SIM Card
                    NOT NULL,
  pinCode           char(4),        -- Código PIN (Personal Identification
                                    -- Number)
  pukCode           char(8),        -- Código PUK (PIN Unblocking Key)
  ownershipTypeID   integer,        -- O tipo de propriedade do SIM Card
  supplierID        integer,        -- ID do fornecedor do SIM Card
  subsidiaryID      integer,        -- ID da unidade/filial do fornecedor do SIM Card
  assetNumber       varchar(20),    -- Número de patrimônio do fornecedor
  storageLocation   StorageType     -- O local onde encontra-se armazenado
                    NOT NULL,
  technicianID      integer         -- O ID do técnico que está com a
                    DEFAULT NULL,   -- posse
  serviceProviderID integer         -- O ID do prestador de serviços que
                    DEFAULT NULL,   -- está com a posse
  depositID         integer         -- O ID do depósito onde está
                    DEFAULT NULL,   -- armazenado
  equipmentID       integer         -- O ID do equipamento no qual está
                    DEFAULT NULL,   -- instalado
  slotNumber        smallint        -- O número do slot no equipamento
                    NOT NULL        -- em que está instalado
                    DEFAULT 0
                    CHECK (slotNumber BETWEEN 0 AND 9),
  blocked           boolean         -- O indicativo de que o SIM Card está
                    DEFAULT false,  -- bloqueado para uso (ele não pode
                                    -- ser usado em outro equipamento)
  createdAt         timestamp       -- A data de criação do SIM Card
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  createdByUserID   integer         -- O ID do usuário responsável pelo
                    NOT NULL,       -- cadastro deste SIM Card
  updatedAt         timestamp       -- A data de modificação do SIM Card
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID   integer         -- O ID do usuário responsável pela
                    NOT NULL,       -- última modificação deste SIM Card
  UNIQUE (contractorID, iccid),
  PRIMARY KEY (simcardID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (mobileOperatorID)
    REFERENCES erp.mobileOperators(mobileOperatorID)
    ON DELETE RESTRICT,
  FOREIGN KEY (simcardTypeID)
    REFERENCES erp.simcardTypes(simcardTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (ownershipTypeID)
    REFERENCES erp.ownershipTypes(ownershipTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (supplierID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE RESTRICT,
  FOREIGN KEY (technicianID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (depositID)
    REFERENCES erp.deposits(depositID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Simcards em comodato
-- ---------------------------------------------------------------------
-- Armazena as informações de simcards em comodato. Os simcards
-- podem estar vinculados a um veículo ou não.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.leasedSimcards (
  leasedSimcardID serial,          -- Número do ID da locação
  simcardID       integer          -- ID do simcard
                  NOT NULL,
  contractorID    integer          -- ID do contratante
                  NOT NULL,
  assignedTo      integer          -- ID do contratante para quem o
                  NOT NULL,        -- simcard foi comodatado
  startDate       date             -- A data de início da locação
                  NOT NULL,
  gracePeriod     integer          -- O período de carência
                  DEFAULT 0,
  endDate         date,            -- A data de término da locação
  PRIMARY KEY (leasedSimcardID),    -- O indice primário
  FOREIGN KEY (simcardID)
    REFERENCES erp.simcards(simcardID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (assignedTo)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- Índice para buscar rapidamente SIM Card por contratante (proprietário)
CREATE INDEX idx_leasedsimcards_contractorid
  ON erp.leasedSimcards(contractorID);

-- Índice para buscar rapidamente SIM Cards comodatados para um
-- contratante específico
CREATE INDEX idx_leasedsimcards_assignedto
  ON erp.leasedSimcards(assignedTo);

-- Índice para filtros por período ativo (usado em consultas que
-- verificam comodatos ativos)
CREATE INDEX idx_leasedsimcards_dates
  ON erp.leasedSimcards(startDate, endDate);

-- Índice composto para buscar rapidamente SIM Cards comodatados de/para
-- um contratante específico
CREATE INDEX idx_leasedsimcards_contractor_assignedto
  ON erp.leasedSimcards(contractorID, assignedTo);

-- Índice para junções com a tabela de SIM Cards
CREATE INDEX idx_leasedsimcards_simcardid
  ON erp.leasedSimcards(simcardID);

-- ---------------------------------------------------------------------
-- Transações nos SIM Cards em comodato
-- ---------------------------------------------------------------------
-- Gatilho para lidar com os SIM Cards em comodato, fazendo a inclusão
-- do ID para quem o SIM Card foi comodatado quando ocorrer a inclusão
-- de um novo SIM Card em comodato.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.leasedSimcardTransaction()
RETURNS trigger AS $$
BEGIN
  -- Faz uso da variável especial TG_OP para verificar a operação
  -- executada.
  IF (TG_OP = 'INSERT') THEN
    -- Atualiza o campo assignedTo do equipamento
    UPDATE erp.simcards
       SET assignedToID = NEW.assignedTo
     WHERE simcardID = NEW.simcardID;
    
    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Atualiza o campo assignedTo do equipamento conforme o estado
    -- atualizado

    -- Verifica se o empréstimo foi encerrado
    IF (NEW.endDate IS NOT NULL) THEN
      UPDATE erp.simcards
         SET assignedToID = NULL
       WHERE simcardID = NEW.simcardID;
    ELSE
      IF (NEW.assignedTo <> OLD.assignedTo) THEN
        -- Atualiza o campo assignedTo do equipamento, pois houve
        -- alteração no contratante
        UPDATE erp.simcards
          SET assignedToID = NEW.assignedTo
        WHERE simcardID = NEW.simcardID;
      END IF;
    END IF;
    
    -- Retornamos a nova entidade
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER leasedSimcardTransactionTriggerAfter
 AFTER INSERT OR UPDATE ON erp.leasedSimcards
   FOR EACH ROW EXECUTE FUNCTION erp.leasedSimcardTransaction();

-- ---------------------------------------------------------------------
-- Dados de Simcards
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de SIM Cards
-- para o gerenciamento de SIM Cards
-- ---------------------------------------------------------------------
CREATE TYPE erp.simCardData AS
(
  simcardID              integer,
  contractorID           integer,
  contractorName         varchar(100),
  assignedToID           integer,
  assignedToName         varchar(100),
  leasedSimcard          boolean,
  leasedingSimcard       boolean,
  supplierID             integer,
  supplierName           varchar(100),
  supplierBlocked        boolean,
  juridicalperson        boolean,
  subsidiaryID           integer,
  subsidiaryName         varchar(50),
  subsidiaryBlocked      boolean,
  assetNumber            varchar(20),
  iccID                  varchar(20),
  imsi                   varchar(15),
  phoneNumber            varchar(20),
  mobileOperatorID       integer,
  mobileOperatorName     varchar(20),
  mobileOperatorLogo     text,
  simcardTypeID          integer,
  simcardTypeName        varchar(20),
  attached               boolean,
  equipmentID            integer,
  serialnumber           char(30),
  imei                   char(18),
  equipmentModelID       integer,
  equipmentModelName     varchar(50),
  slotNumber             smallint,
  slotTypeID             integer,
  slotTypeName           varchar(20),
  simCardBlocked         boolean,
  blockedLevel           smallint,
  createdAt              timestamp,
  updatedAt              timestamp,
  fullcount              integer
);

CREATE OR REPLACE FUNCTION erp.getSimCardsData(FcontractorID integer,
  FsupplierID integer, FsubsidiaryID integer, FsimcardID integer,
  FsearchValue varchar(100), FsearchField varchar(20), FtypeID integer,
  FmobileOperatorID integer, FstorageLocation varchar(30),
  FstorageID integer, FOrder varchar, Skip integer, LimitOf integer)
RETURNS SETOF erp.simCardData AS
$$
DECLARE
  simCardData  erp.simCardData%rowtype;
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
  IF (FsupplierID IS NULL) THEN
    FsupplierID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FsimcardID IS NULL) THEN
    FsimcardID = 0;
  END IF;
  IF (FtypeID IS NULL) THEN
    FtypeID = 0;
  END IF;
  IF (FmobileOperatorID IS NULL) THEN
    FmobileOperatorID = 0;
  END IF;
  IF (FstorageLocation IS NULL) THEN
    FstorageLocation = 'Any';
  END IF;
  IF (FstorageID IS NULL) THEN
    FstorageID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'supplier.name ASC, subsidiary.subsidiaryid ASC, simcards.iccID ASC';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  
  IF (FsimcardID > 0) THEN
    filter := format(' AND simcards.simcardID = %s',
                    FsimcardID);
  ELSE
    -- Realiza a filtragem por cliente
    IF (FsupplierID > 0) THEN
      filter := format(' AND supplier.entityID = %s',
                      FsupplierID);
      IF (FsubsidiaryID > 0) THEN
        filter := filter || format(' AND subsidiary.subsidiaryID = %s',
                                  FsubsidiaryID);
      END IF;
    ELSE
      filter := '';
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
        WHEN 'simCardBrandName' THEN
          field := 'simCardBrands.name';
        WHEN 'simCardModelName' THEN
          field := 'simCardModels.name';
        ELSE
          field := 'simCards.' || FsearchField;
      END CASE;
      -- Monta o filtro
      filter := filter || format(' AND public.unaccented(%s) ILIKE public.unaccented(''%%%s%%'')',
                                  field, FsearchValue);
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  IF (FtypeID > 0) THEN
    -- Monta o filtro
    filter := filter || format(' AND simcards.simcardTypeID = %s', FtypeID);
  END IF;

  IF (FmobileOperatorID > 0) THEN
    -- Monta o filtro
    filter := filter || format(' AND simcards.mobileOperatorID = %s', FmobileOperatorID);
  END IF;

  CASE (FstorageLocation)
    WHEN 'Installed' THEN
      -- Monta o filtro
      filter := filter || format(' AND simcards.storageLocation = ''%s''', FstorageLocation);
    WHEN 'StoredOnDeposit' THEN
      -- Monta o filtro
      IF FstorageID > 0 THEN
        filter := filter || format(' AND simcards.storageLocation = ''%s'' AND simcards.depositID = %s', FstorageLocation, FstorageID);
      ELSE
        filter := filter || format(' AND simcards.storageLocation = ''%s''', FstorageLocation);
      END IF;
    WHEN 'StoredWithTechnician' THEN
      -- Monta o filtro
      IF FstorageID > 0 THEN
        filter := filter || format(' AND simcards.storageLocation = ''%s'' AND simcards.technicianID = %s', FstorageLocation, FstorageID);
      ELSE
        filter := filter || format(' AND simcards.storageLocation = ''%s''', FstorageLocation);
      END IF;
    WHEN 'StoredWithServiceProvider' THEN
      -- Monta o filtro
      IF FstorageID > 0 THEN
        filter := filter || format(' AND simcards.storageLocation = ''%s'' AND simcards.serviceProviderID = %s', FstorageLocation, FstorageID);
      ELSE
        filter := filter || format(' AND simcards.storageLocation = ''%s''', FstorageLocation);
      END IF;
    ELSE
      -- Não faz nada
  END CASE;

  -- Monta a consulta
  query := format('SELECT simcards.simcardID,
                          simcards.contractorID,
                          contractor.name AS contractorName,
                          contractor.blocked AS contractorBlocked,
                          simcards.assignedToID,
                          assignedTo.name AS assignedToName,
                          CASE
                            WHEN simcards.assignedToID = %s THEN TRUE
                            ELSE FALSE
                          END AS leasedSimcard,
                          CASE
                            WHEN simcards.contractorID = %s AND simcards.assignedToID IS NOT NULL THEN TRUE
                            ELSE FALSE
                          END AS leasedingSimcard,
                          simcards.supplierID,
                          CASE
                            WHEN simcards.assignedToID = %s THEN contractor.name
                            WHEN simcards.supplierID IS NULL THEN ''Outros fornecedores''
                            ELSE supplier.name
                          END AS supplierName,
                          CASE 
                            WHEN simcards.assignedToID = %s THEN contractor.blocked
                            WHEN simcards.supplierID IS NULL THEN FALSE
                            ELSE supplier.blocked
                          END AS supplierBlocked,
                          CASE 
                            WHEN simcards.assignedToID = %s THEN assignedToType.juridicalperson
                            WHEN simcards.supplierID IS NULL THEN TRUE
                            ELSE supplierType.juridicalperson
                          END AS juridicalperson,
                          CASE 
                            WHEN simcards.subsidiaryID IS NULL THEN 0
                            ELSE simcards.subsidiaryID
                          END AS subsidiaryID,
                          CASE 
                            WHEN simcards.subsidiaryID IS NULL THEN ''Não informado''
                            ELSE subsidiary.name
                          END AS subsidiaryName,
                          CASE 
                            WHEN simcards.subsidiaryID IS NULL THEN FALSE
                            ELSE subsidiary.blocked
                          END AS subsidiaryBlocked,
                          simcards.iccID,
                          simcards.imsi,
                          simcards.phoneNumber,
                          simcards.mobileOperatorID,
                          mobileOperators.name AS mobileOperatorName,
                          mobileOperators.logo AS mobileOperatorLogo,
                          simcards.simcardTypeID,
                          simcardTypes.name AS simcardTypeName,
                          simcards.assetNumber,
                          simcards.blocked AS simCardBlocked,
                          simcards.createdAt,
                          simcards.updatedAt,
                          simcards.storageLocation = ''Installed'' AS attached,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN simcards.equipmentID
                            ELSE 0
                          END AS equipmentID,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN equipments.serialnumber
                            ELSE ''''
                          END AS serialnumber,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN equipments.imei
                            ELSE ''''
                          END AS imei,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN equipments.equipmentModelID
                            ELSE 0
                          END AS equipmentModelID,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN equipmentmodels.name
                            ELSE ''''
                          END AS equipmentModelName,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN simcards.slotNumber
                            ELSE 0
                          END AS slotNumber,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN equipmentmodels.simcardTypeID
                            ELSE 0
                          END AS slotTypeID,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN slotType.name
                            ELSE ''''
                          END AS slotTypeName,
                          count(*) OVER() AS fullcount
                     FROM erp.simcards
                    INNER JOIN erp.entities AS contractor ON (simcards.contractorID = contractor.entityID)
                    INNER JOIN erp.mobileOperators USING (mobileOperatorID)
                    INNER JOIN erp.simcardTypes ON (simcards.simcardTypeID = simcardTypes.simcardTypeID)
                     LEFT JOIN erp.entities AS supplier ON (simcards.supplierID = supplier.entityID)
                     LEFT JOIN erp.entitiesTypes AS supplierType ON (supplier.entityTypeID = supplierType.entityTypeID)
                     LEFT JOIN erp.subsidiaries AS subsidiary ON (simcards.subsidiaryID = subsidiary.subsidiaryID)
                     LEFT JOIN erp.entities AS assignedTo ON (simcards.assignedToID = assignedTo.entityID)
                     LEFT JOIN erp.entitiesTypes AS assignedToType ON (assignedTo.entityTypeID = assignedToType.entityTypeID)
                     LEFT JOIN erp.equipments USING (equipmentID)
                     LEFT JOIN erp.equipmentmodels USING (equipmentModelID)
                     LEFT JOIN erp.simcardTypes AS slotType ON (equipmentmodels.simcardTypeID = slotType.simcardTypeID)
                    WHERE (simcards.contractorID = %s OR simcards.assignedToID = %s) %s
                    ORDER BY %s %s',
                  fContractorID, fContractorID, fContractorID, 
                  fContractorID, fContractorID, fContractorID,
                  fContractorID, filter, FOrder, limits);
  -- RAISE NOTICE 'SQL: %', query;
  FOR row IN EXECUTE query
  LOOP
    simCardData.simcardID              := row.simcardID;
    simCardData.contractorID           := row.contractorID;
    simCardData.contractorName         := row.contractorName;
    simCardData.assignedToID           := row.assignedToID;
    simCardData.assignedToName         := row.assignedToName;
    simCardData.leasedSimcard          := row.leasedSimcard;
    simCardData.leasedingSimcard       := row.leasedingSimcard;
    simCardData.supplierID             := row.supplierID;
    simCardData.supplierName           := row.supplierName;
    simCardData.supplierBlocked        := row.supplierBlocked;
    simCardData.juridicalperson        := row.juridicalperson;
    simCardData.subsidiaryID           := row.subsidiaryID;
    simCardData.subsidiaryName         := row.subsidiaryName;
    simCardData.subsidiaryBlocked      := row.subsidiaryBlocked;
    simCardData.iccID                  := row.iccID;
    simCardData.imsi                   := row.imsi;
    simCardData.phoneNumber            := row.phoneNumber;
    simCardData.mobileOperatorID       := row.mobileOperatorID;
    simCardData.mobileOperatorName     := row.mobileOperatorName;
    simCardData.mobileOperatorLogo     := row.mobileOperatorLogo;
    simCardData.simcardTypeID          := row.simcardTypeID;
    simCardData.simcardTypeName        := row.simcardTypeName;
    simCardData.attached               := row.attached;
    simCardData.equipmentID            := row.equipmentID;
    simCardData.serialnumber           := row.serialnumber;
    simCardData.imei                   := row.imei;
    simCardData.equipmentModelID       := row.equipmentModelID;
    simCardData.equipmentModelName     := row.equipmentModelName;
    simCardData.slotNumber             := row.slotNumber;
    simCardData.slotTypeID             := row.slotTypeID;
    simCardData.slotTypeName           := row.slotTypeName;
    simCardData.assetNumber            := row.assetNumber;
    simCardData.simCardBlocked         := row.simCardBlocked;
    -- RAISE NOTICE 'Contractor %', row.contractorBlocked;
    -- RAISE NOTICE 'SIM Card %', row.simCardBlocked;
    -- RAISE NOTICE 'Subsidiary %', row.subsidiaryBlocked;

    -- Determina o nível de bloqueio. O nível de bloqueio mais baixo é o
    -- do SIM Card, depois a unidade/filial do fornecedor, o próprio
    -- fornecedor e por último o contratante
    blockedLevel := 0;
    IF (row.simCardBlocked) THEN
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
    simCardData.blockedLevel       := blockedLevel;
    simCardData.createdAt          := row.createdAt;
    simCardData.updatedAt          := row.updatedAt;
    simCardData.fullcount          := row.fullcount;

    RETURN NEXT simCardData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getSimCardsData(1, 0, 0, 0, '', 'name', 0, 0, 'Any', 0, 'supplier.name ASC, subsidiary.subsidiaryid ASC, simCards.iccID ASC', 0, 10);

-- ---------------------------------------------------------------------
-- ICCID's de SIM Cards
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.iccids (
  id                serial,         -- ID do SIM Card
  iccid             varchar(20)     -- ICCID (Integrated Circuit Card ID)
                    NOT NULL,
  imsi              varchar(15),    -- IMSI (International Mobile Suscriber
                                    -- Identity)
  equipmentID       integer         -- O ID do equipamento no qual está
                    DEFAULT NULL,   -- instalado
  createdAt         timestamp       -- A data de criação do SIM Card
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (iccid),
  PRIMARY KEY (id)
);
