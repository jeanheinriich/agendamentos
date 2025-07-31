-- =====================================================================
-- Equipamentos de Rastreamento
-- =====================================================================
-- Tabelas utilizada no controle de equipamentos de rastreamento
-- =====================================================================

-- ---------------------------------------------------------------------
-- Equipamentos
-- ---------------------------------------------------------------------
-- Armazena as informações de equipamentos. Os equipamentos podem estar
-- vinculados a um veículo ou não.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.equipments (
  equipmentID         serial,         -- Número de identificação do equipamento
  contractorID        integer         -- ID do contratante
                      NOT NULL,
  assignedToID        integer         -- ID do contratante para quem o
                      DEFAULT NULL,   -- equipamento foi comodatado
  equipmentModelID    integer         -- ID do modelo do equipamento
                      NOT NULL,
  IMEI                char(18),       -- Número do IMEI (International
                                      -- Mobile Equipment Identity)
  serialNumber        varchar(30),    -- Número de série do equipamento
  ownershipTypeID     integer         -- O tipo de propriedade do
                      NOT NULL,       -- equipamento
  supplierID          integer,        -- Número de identificação do
                                      -- fornecedor do equipamento
  subsidiaryID        integer,        -- Número de identificação da
                                      -- unidade/filial do fornecedor
  assetNumber         varchar(20),    -- Número de patrimônio do fornecedor
  equipmentStateID    integer         -- ID da situação em que se encontra
                      DEFAULT 1,      -- o equipamento
  storageLocation     StorageType     -- O local onde encontra-se
                      NOT NULL,       -- armazenado
  technicianID        integer         -- O ID do técnico que está com a
                      DEFAULT NULL,   -- posse
  serviceProviderID   integer         -- O ID do prestador de serviços
                      DEFAULT NULL,   -- que está com a posse
  depositID           integer         -- O ID do depósito onde está
                      DEFAULT NULL,   -- armazenado
  vehicleID           integer         -- O ID do veículo no qual está
                      DEFAULT NULL,   -- instalado
  installationID      integer         -- ID do item de contrato, que
                      DEFAULT NULL,   -- permite identificar o pagante
  installedAt         date            -- A data da instalação
                      DEFAULT NULL,
  main                boolean         -- O identificador de equipamento
                      DEFAULT false,  -- principal (demais são contingência)
  hiddenFromCustomer  boolean         -- O identificador de equipamento
                      DEFAULT false,  -- oculto do cliente
  installationSite    varchar(100)    -- O local físico de instalação
                      DEFAULT NULL,   -- deste equipamento no veículo
  hasBlocking         boolean         -- O indicativo de que o equipamento
                      DEFAULT false,  -- possui bloqueio
  blockingSite        varchar(100)    -- O local físico onde foi instalado
                      DEFAULT NULL,   -- o bloqueio
  hasSiren            boolean         -- O indicativo de que o equipamento
                      DEFAULT false,  -- possui sirene
  sirenSite           varchar(100)    -- O local físico onde foi instalado
                      DEFAULT NULL,   -- a sirene
  panicButtonSite     varchar(100)    -- O local físico onde foi instalado
                      DEFAULT NULL,   -- o botão de pânico
  hasiButton          boolean         -- O indicativo de que o equipamento
                      DEFAULT false,  -- possui iButton
  iButtonsMemSize     integer         -- A quantidade de iButtons que
                      NOT NULL        -- podem ser armazenados
                      DEFAULT 0,
  iButtonsMemUsed     integer         -- A quantidade de iButtons que
                      NOT NULL        -- estão armazenados
                      DEFAULT 0,
  iButtonSite         varchar(100)    -- O local físico onde foi instalado
                      DEFAULT NULL,   -- o iButton
  iButtonActive       boolean         -- O indicativo de que a leitora
                      DEFAULT false,  -- de iButton está ativa
  iButtonNewState     boolean         -- O indicativo do estado que a
                      DEFAULT NULL,   -- leitora deve estar
  customerPayerID     integer         -- Número de identificação do cliente
                      DEFAULT NULL,   -- responsável pelo pagamento
  subsidiaryPayerID   integer         -- Número de identificação da unidade
                      DEFAULT NULL,   -- do cliente pagante
  lastCommunication   timestamp       -- A data de última comunicação
                      DEFAULT NULL,
  lastConfigSync      timestamp       -- A data de última sincronização
                      DEFAULT NULL,   -- de configuração
  onLine              boolean         -- O indicativo de que o equipamento
                      DEFAULT false,  -- está online
  blocked             boolean         -- O indicativo de que o equipamento
                      DEFAULT false,  -- está bloqueado para uso (ele não
                                      -- pode ser instalado em um veículo)
  createdAt           timestamp       -- A data de criação do equipamento
                      NOT NULL
                      DEFAULT CURRENT_TIMESTAMP,
  createdByUserID     integer         -- O ID do usuário responsável pelo
                      NOT NULL,       -- cadastro deste equipamento
  updatedAt           timestamp       -- A data de modificação do
                      NOT NULL        -- equipamento
                      DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID     integer         -- O ID do usuário responsável pela
                      NOT NULL,       -- última modificação
  systemDate          timestamp       -- A data de sistema, utilizado para
                      NOT NULL        -- determinar qual o último cadastro
                      DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (equipmentID),        -- O indice primário
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (assignedToID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (equipmentModelID)
    REFERENCES erp.equipmentModels(equipmentModelID)
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
  FOREIGN KEY (equipmentStateID)
    REFERENCES erp.equipmentStates(equipmentStateID)
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
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

-- NOTE: A data do systema (systemDate) é utilizada para determinar qual
--       o cadastro é mais recente. Isso é necessário para que o sistema
--       possa identificar corretamente o contratante em qual o
--       equipamento foi cadastrado pela última vez, caso ele venha a
--       ser cadastrado mais de uma vez. É utilizado unicamente pela
--       plataforma de rastreamento no processo de inserção de registros
--       de posição.

-- Adiciona a chave extrangeira para unir a tabela de equipamentos com a
-- tabela de SIM Cards
ALTER TABLE erp.simcards
  ADD CONSTRAINT simcards_equipmentid_fkey
    FOREIGN KEY (equipmentID)
    REFERENCES erp.equipments(equipmentID)
    ON DELETE CASCADE;

-- Cria um índice para os veículos por fornecedor
CREATE INDEX idx_equipments_supplierid ON erp.equipments(supplierID);
CREATE INDEX idx_equipments_subsidiaryid ON erp.equipments(subsidiaryID);

-- Cria um índice para os veículos por equipamento
CREATE INDEX idx_equipments_vehicleID ON erp.equipments(vehicleID);

-- Cria um índice para os equipamentos por contratante
CREATE INDEX idx_equipments_contractorid ON erp.equipments(contractorID);

-- Cria um índice para os equipamentos por modelo
CREATE INDEX idx_equipments_equipmentmodelid ON erp.equipments(equipmentModelID);

-- Cria um índice para os equipamentos em comodato
CREATE INDEX idx_equipments_assignedtoid ON erp.equipments(assignedToID);

-- Índice para busca por serialNumber (considere adicionar partial indexes se aplicável)
CREATE INDEX idx_equipment_serialnumber ON erp.equipments(serialNumber);

-- Índice para a ordenação por systemDate
CREATE INDEX idx_equipment_systemdate ON erp.equipments(systemDate DESC);

-- Índice composto se você frequentemente filtra por serialNumber e ordena por systemDate
CREATE INDEX idx_equipment_serialnumber_systemdate ON erp.equipments(serialNumber, systemDate DESC);

-- ---------------------------------------------------------------------
-- Obtém o número de série de um equipamento formatado
-- ---------------------------------------------------------------------
-- Obtém o número de série de um equipamento acrescido dos respectivos
-- zeros à esquerda, de forma a ter o tamanho exigido pelo fabricante.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getFormattedSerialNumber(integer)
RETURNS text AS
$BODY$
  SELECT LPAD(equipment.serialNumber, model.serialNumberSize, '0')
    FROM erp.equipments AS equipment
   INNER JOIN erp.equipmentModels AS model USING (equipmentModelID)
   WHERE equipment.equipmentID = $1;
$BODY$
LANGUAGE 'sql' IMMUTABLE STRICT;

-- ---------------------------------------------------------------------
-- Equipamentos em comodato
-- ---------------------------------------------------------------------
-- Armazena as informações de equipamentos em comodato. Os equipamentos
-- podem estar vinculados a um veículo ou não.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.leasedEquipments (
  leasedEquipmentID serial,          -- Número do ID da locação
  equipmentID       integer          -- ID do equipamento
                    NOT NULL,
  contractorID      integer          -- ID do contratante
                    NOT NULL,
  assignedTo        integer          -- ID do contratante para quem o
                    NOT NULL,        -- equipamento foi comodatado
  startDate         date             -- A data de início da locação
                    NOT NULL,
  gracePeriod       integer          -- O período de carência
                    DEFAULT 0,
  endDate           date,            -- A data de término da locação
  PRIMARY KEY (leasedEquipmentID),   -- O indice primário
  FOREIGN KEY (equipmentID)
    REFERENCES erp.equipments(equipmentID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (assignedTo)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

-- Índice para buscar rapidamente equipamentos por contratante
-- (proprietário)
CREATE INDEX idx_leasedequipments_contractorid 
    ON erp.leasedEquipments(contractorID);

-- Índice para buscar rapidamente equipamentos comodatados para um
-- contratante específico
CREATE INDEX idx_leasedequipments_assignedto 
    ON erp.leasedEquipments(assignedTo);

-- Índice para filtros por período ativo (usado em consultas que
-- verificam comodatos ativos)
CREATE INDEX idx_leasedequipments_dates 
    ON erp.leasedEquipments(startDate, endDate);

-- Índice composto para buscar rapidamente equipamentos comodatados
-- de/para um contratante específico
CREATE INDEX idx_leasedequipments_contractor_assigned 
    ON erp.leasedEquipments(contractorID, assignedTo);

-- Índice para junções com a tabela de equipamentos
CREATE INDEX idx_leasedequipments_equipmentid 
    ON erp.leasedEquipments(equipmentID);

-- ---------------------------------------------------------------------
-- Transações nos equipamentos em comodato
-- ---------------------------------------------------------------------
-- Gatilho para lidar com os equipamentos em comodato, fazendo a
-- inclusão do ID para quem o equipamento foi comodatado quando ocorrer
-- a inclusão de um novo equipamento em comodato.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.leasedEquipmentTransaction()
RETURNS trigger AS $$
BEGIN
  -- Faz uso da variável especial TG_OP para verificar a operação
  -- executada.
  IF (TG_OP = 'INSERT') THEN
    -- Atualiza o campo assignedTo do equipamento
    UPDATE erp.equipments
       SET assignedToID = NEW.assignedTo
     WHERE equipmentID = NEW.equipmentID;
    
    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Atualiza o campo assignedTo do equipamento conforme o estado
    -- atualizado

    -- Verifica se o empréstimo foi encerrado
    IF (NEW.endDate IS NOT NULL) THEN
      UPDATE erp.equipments
         SET assignedToID = NULL
       WHERE equipmentID = NEW.equipmentID;
    ELSE
      IF (NEW.assignedTo <> OLD.assignedTo) THEN
        -- Atualiza o campo assignedTo do equipamento, pois houve
        -- alteração no contratante
        UPDATE erp.equipments
          SET assignedToID = NEW.assignedTo
        WHERE equipmentID = NEW.equipmentID;
      END IF;
    END IF;
    
    -- Retornamos a nova entidade
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER leasedEquipmentTransactionTriggerAfter
 AFTER INSERT OR UPDATE ON erp.leasedEquipments
   FOR EACH ROW EXECUTE FUNCTION erp.leasedEquipmentTransaction();

-- ---------------------------------------------------------------------
-- Dados dos slots de Simcard do equipamento
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações dos SIM Cards
-- vinculados nos slots de um equipamento
-- ---------------------------------------------------------------------
CREATE TYPE erp.slotData AS
(
  equipmentID            integer,
  contractorID           integer,
  assignedToID           integer,
  slotNumber             smallint,
  simcardID              integer,
  iccID                  varchar(20),
  phoneNumber            varchar(20),
  mobileOperatorID       integer,
  mobileOperatorName     varchar(20)
);

CREATE OR REPLACE FUNCTION erp.getSlotData(FcontractorID integer,
  FequipmentID integer)
RETURNS SETOF erp.slotData AS
$$
DECLARE
  slotData       erp.slotData%rowtype;
  row            record;
  query          varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FequipmentID IS NULL) THEN
    FequipmentID = 0;
  END IF;

  -- Monta a consulta
  query := 'SELECT EQPTO.equipmentID,
                   EQPTO.slotnumber,
                   SIM.simcardID,
                   SIM.contractorID,
                   SIM.assignedToID,
                   SIM.iccid,
                   CASE
                     WHEN SIM.phoneNumber IS NOT NULL THEN SIM.phoneNumber
                     ELSE ''Não disponível''
                   END AS phoneNumber,
                   SIM.mobileOperatorID,
                   MOBI.name AS mobileOperatorName
              FROM (SELECT equipments.equipmentID,
                           generate_series(1, equipmentmodels.maxsimcards) AS slotNumber
                      FROM erp.equipments
                     INNER JOIN erp.equipmentmodels USING (equipmentModelID)
                     WHERE (equipments.contractorID = $1 OR equipments.assignedToID = $2)
                       AND equipments.equipmentID = $3) AS EQPTO
              LEFT JOIN erp.simcards AS SIM
                     ON EQPTO.equipmentID = SIM.equipmentID
                    AND EQPTO.slotNumber = SIM.slotNumber
              LEFT JOIN erp.mobileoperators AS MOBI USING (mobileoperatorid)';
  FOR row IN EXECUTE query USING fContractorID, fContractorID, fEquipmentID
  LOOP
    slotData.equipmentID            := row.equipmentID;
    slotData.contractorID           := row.contractorID;
    slotData.assignedToID           := row.assignedToID;
    slotData.slotNumber             := row.slotNumber;
    slotData.simcardID              := row.simcardID;
    slotData.iccid                  := row.iccid;
    slotData.phoneNumber            := row.phoneNumber;
    slotData.mobileOperatorID       := row.mobileOperatorID;
    slotData.mobileOperatorName     := row.mobileOperatorName;

    RETURN NEXT slotData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getSlotData(1, 1);
