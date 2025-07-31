-- =====================================================================
-- Identificadores de motoristas
-- =====================================================================
-- Tabelas utilizada no controle de identificação de motoristas
-- =====================================================================

-- ---------------------------------------------------------------------
-- Motoristas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.drivers (
  driverID                serial,        -- ID do motorista
  contractorID            integer        -- ID do contratante
                          NOT NULL,
  customerID              integer        -- ID do cliente
                          NOT NULL,
  name                    varchar(100),  -- O nome do motorista
  nickname                varchar(50),   -- O apelido do motorista
  occupation              varchar(100),  -- A ocupação do motorista
  birthDate               date,          -- A data de nascimento
  genderID                integer,       -- O ID do gênero
  cnh                     varchar(20),   -- O número da CNH do motorista
  cnhCategory             varchar(5),    -- A categoria da CNH
  cnhExpirationDate       date,          -- A data de vencimento da CNH
  cpf                     varchar(14),   -- O número do CPF do motorista
  address                 varchar(100)   -- O endereço
                          NOT NULL,
  streetNumber            varchar(10),   -- O número da casa
  complement              varchar(30),   -- O complemento do endereço
  district                varchar(50),   -- O bairro
  cityID                  integer        -- O ID da cidade
                          DEFAULT NULL,
  postalCode              char(9),       -- O CEP
  active                  boolean        -- Ativo
                          NOT NULL
                          DEFAULT TRUE,
  createdAt               timestamp      -- A data de criação do 
                          NOT NULL       -- motorista
                          DEFAULT CURRENT_TIMESTAMP,
  createdByUserID         integer        -- O ID do usuário responsável
                          NOT NULL,      -- pelo cadastro deste motorista
  updatedAt               timestamp      -- A data de modificação do
                          NOT NULL       -- motorista
                          DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID         integer        -- O ID do usuário responsável
                          NOT NULL,      -- pela última modificação
  deletedAt               timestamp     -- A data de exclusão do
                          DEFAULT NULL, -- identificador
  deletedByUserID         integer       -- O ID do usuário responsável
                          DEFAULT NULL, -- pela exclusão
  PRIMARY KEY (driverID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (genderID)
    REFERENCES erp.genders(genderID)
    ON DELETE RESTRICT,
  FOREIGN KEY (cityID)
    REFERENCES erp.cities(cityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (deletedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

-- INSERT INTO erp.drivers (contractorID, customerID, name, nickname,
--   occupation, birthDate, cnh, cnhCategory, cnhExpirationDate, cpf,
--   address, streetNumber, complement, district, cityID, postalCode,
--   createdByUserID, updatedByUserID) VALUES
--   (1, 42, 'João da Silva', '', 'Motorista', '1980-01-01', '12345678901',
--   'A', '2024-01-01', '123.456.789-01', 'Rua das Flores', '123',
--   'Apto 101', 'Centro', 3287, '12345-678', 6, 6),
--   (1, 42, 'Maria da Silva', '', 'Motorista', '1985-02-01', '12345678902',
--   'B', '2025-02-17', '123.456.789-02', 'Rua das Flores', '123',
--   'Apto 102', 'Centro', 3287, '12345-678', 6, 6),
--   (1, 42, 'Carlos Apolinaro', '', 'Motorista', '1990-10-27', '12345678903',
--   'B', '2030-06-12', '123.456.789-03', 'Rua das Flores', '123',
--   'Apto 103', 'Centro', 3287, '12345-678', 6, 6);

-- ---------------------------------------------------------------------
-- Telefones por motorista
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones por motorista.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.driverPhones (
  driverPhoneID serial,        -- O ID do telefone do motorista
  driverID      integer        -- O ID do motorista à qual pertence este
                NOT NULL,      -- telefone
  phoneTypeID   integer        -- O ID do tipo de telefone
                NOT NULL,
  phoneNumber   varchar(20)    -- O número do telefone
                NOT NULL,
  PRIMARY KEY (driverPhoneID),
  FOREIGN KEY (driverID)
    REFERENCES erp.drivers(driverID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Identificadores de motoristas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.driverIdentifiers (
  driverIdentifierID      serial,       -- ID do identificador
  contractorID            integer       -- ID do contratante
                          NOT NULL,
  customerID              integer       -- ID do cliente
                          NOT NULL,
  identifierTechnologyID  integer,      -- ID da tecnologia de identificação
  identifier              varchar(50),  -- O identificador (número de série)
  driverID                integer,      -- ID do motorista
  isUniversal             boolean       -- Se é universal, ou seja, será
                          NOT NULL      -- cadastrado sempre em todos os
                          DEFAULT FALSE,-- veículos da frota
  active                  boolean       -- Ativo
                          NOT NULL
                          DEFAULT TRUE,
  createdAt               timestamp     -- A data de criação do 
                          NOT NULL      -- identificador
                          DEFAULT CURRENT_TIMESTAMP,
  createdByUserID         integer       -- O ID do usuário responsável
                          NOT NULL,     -- pelo cadastro
  updatedAt               timestamp     -- A data de modificação do
                          NOT NULL      -- identificador
                          DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID         integer       -- O ID do usuário responsável
                          NOT NULL,     -- pela última modificação
  deleted                 boolean       -- Flag indicadora que o
                          NOT NULL      -- identificador foi excluído
                          DEFAULT FALSE,
  deletedAt               timestamp     -- A data de exclusão do
                          DEFAULT NULL, -- identificador
  deletedByUserID         integer       -- O ID do usuário responsável
                          DEFAULT NULL, -- pela exclusão
  PRIMARY KEY (driverIdentifierID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (identifierTechnologyID)
    REFERENCES erp.identifierTechnologies(identifierTechnologyID)
    ON DELETE RESTRICT,
  FOREIGN KEY (driverID)
    REFERENCES erp.drivers(driverID)
    ON DELETE RESTRICT,  
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (deletedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

CREATE INDEX idx_driveridentifiers_search ON erp.driveridentifiers (
    contractorID,
    customerID,
    identifierTechnologyID,
    identifier,
    deleted
);

-- INSERT INTO erp.driverIdentifiers (contractorID, customerID,
--   identifierTechnologyID, identifier, driverID, createdByUserID,
--   updatedByUserID) VALUES
--   (1, 42, 1, '01ADA126000000', 1, 6, 6),
--   (1, 42, 1, '01ADA126000001', NULL, 6, 6),
--   (1, 42, 1, '01ADA126000002', NULL, 6, 6),
--   (1, 42, 1, '01ADA126000003', NULL, 6, 6),
--   (1, 42, 1, '01ADA126000004', NULL, 6, 6),
--   (1, 42, 1, '01ADA126000005', NULL, 6, 6);

-- ---------------------------------------------------------------------
-- O controle do armazenamento de identificadores de motoristas nos
-- equipamentos de rastreamento
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.driverIdentifierStore (
  driverIdentifierStoreID serial,         -- ID do armazenamento
  contractorID            integer         -- ID do contratante
                          NOT NULL,
  customerID              integer         -- ID do cliente
                          NOT NULL,
  vehicleID               integer         -- ID do veículo
                          NOT NULL,
  equipmentID             integer         -- ID do equipamento
                          NOT NULL,
  driverIdentifierID      integer         -- ID do identificador do
                          NOT NULL,       -- motorista
  memPosition             smallint        -- Posição da memória no 
                          NOT NULL,       -- equipamento
  stored                  boolean         -- Se está armazenado na
                          NOT NULL        -- memória do equipamento
                          DEFAULT FALSE,
  storedAt                timestamp       -- Data/hora em que foi
                          DEFAULT NULL,   -- armazenado
  toRemove                boolean         -- Flag indicadora que devemos
                          NOT NULL        -- retirar este identificador
                          DEFAULT FALSE,  -- da memória do equipamento
  removedAt               timestamp       -- Data/hora em que foi 
                          DEFAULT NULL,   -- retirado
  registeredAt            timestamp       -- A data de registro do 
                          NOT NULL        -- identificador no equipamento
                          DEFAULT CURRENT_TIMESTAMP,
  registeredByUserID      integer         -- O ID do usuário responsável
                          NOT NULL,       -- pelo registro
  deleted                 boolean         -- Flag indicadora que o
                          NOT NULL        -- identificador foi excluído
                          DEFAULT FALSE,
  deletedAt               timestamp       -- A data de exclusão do 
                          DEFAULT NULL,    -- identificador
  deletedByUserID         integer          -- O ID do usuário responsável
                          DEFAULT NULL,    -- pela exclusão
  PRIMARY KEY (driverIdentifierStoreID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE RESTRICT,
  FOREIGN KEY (equipmentID)
    REFERENCES erp.equipments(equipmentID)
    ON DELETE RESTRICT,
  FOREIGN KEY (driverIdentifierID)
    REFERENCES erp.driverIdentifiers(driverIdentifierID)
    ON DELETE RESTRICT,
  FOREIGN KEY (registeredByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (deletedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Os motoristas que estão dirigindo os veículos
-- ---------------------------------------------------------------------
-- Armazena os motoristas que estão dirigindo os veículos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS driversInVehicles (
  vehicleID           integer,        -- ID do veículo
  equipmentID         integer         -- ID do equipamento
                      NOT NULL,
  driverIdentifierID  integer         -- ID do identificador do
                      NOT NULL,       -- motorista
  driverID            integer         -- ID do motorista que está
                      DEFAULT NULL,   -- dirigindo o veículo
  insertedAt          timestamp       -- A data de inserção do motorista
                      NOT NULL        -- no veículo
                      DEFAULT CURRENT_TIMESTAMP,
  lastPositionAt      timestamp       -- A data da última posição
                      DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (equipmentID, driverIdentifierID),
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles (vehicleID)
    ON DELETE CASCADE,
  FOREIGN KEY (equipmentID)
    REFERENCES erp.equipments (equipmentID)
    ON DELETE CASCADE,
  FOREIGN KEY (driverIdentifierID)
    REFERENCES erp.driverIdentifiers (driverIdentifierID)
    ON DELETE CASCADE,
  FOREIGN KEY (driverID)
    REFERENCES erp.drivers (driverID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Insere um identificador de motorista no equipamento
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que insere um identificador de motorista,
-- no equipamento, analisando a posição na memória do equipamento que
-- esteja livre para armazenar o novo identificador.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.insertDriverIdentifierInEquipment(
  FcontractorID integer, FcustomerID integer, FvehicleID integer,
  FequipmentID integer, FdriverIdentifierID integer, FuserID integer)
RETURNS integer AS
$$
DECLARE
  position integer;
  lastPosition integer;
  newPosition integer;
  memSize integer;
  memUsed integer;
  storeID integer;
BEGIN
  -- Seleciona a quantidade de posições de memória disponíveis no
  -- equipamento
  SELECT iButtonsMemSize,
         iButtonsMemUsed
    INTO memSize,
         memUsed
    FROM erp.equipments
   WHERE equipmentID = FequipmentID;
  
  -- Verifica se a memória do equipamento está cheia
  IF memUsed >= memSize THEN
    RAISE EXCEPTION 'A memória do equipamento está cheia';
  END IF;

  -- Verifica se o identificador de motorista já está armazenado no
  -- equipamento
  SELECT driverIdentifierStoreID
    INTO storeID
    FROM erp.driverIdentifierStore
   WHERE contractorID = FcontractorID
     AND equipmentID = FequipmentID
     AND driverIdentifierID = FdriverIdentifierID
     AND deleted = FALSE;
  IF storeID IS NOT NULL THEN
    RAISE EXCEPTION 'O identificador de motorista id % já está armazenado no equipamento id %', FdriverIdentifierID, FequipmentID;
  END IF;

  -- Seleciona as posições de memória que estão ocupadas, de forma a
  -- encontrar uma posição livre para armazenar o identificador. Fazemos
  -- isto sequencialmente, pois a memória do equipamento é limitada e
  -- podemos ter posições ocupadas e livres de maneira intercalada.
  lastPosition := 0;
  newPosition := 0;
  FOR position IN
    SELECT memPosition
      FROM erp.driverIdentifierStore
     WHERE equipmentID = FequipmentID
       AND deleted = FALSE
     ORDER BY memPosition
  LOOP
    -- RAISE NOTICE 'Position: %', position;
    IF position - lastPosition > 1 THEN
      -- Encontramos uma posição livre
      -- RAISE NOTICE 'Position free: %', lastPosition + 1;
      newPosition := lastPosition + 1;
      EXIT;
    END IF;
    lastPosition := position;
  END LOOP;
  
  -- Se não encontramos uma posição livre, então a próxima posição é
  -- a última ocupada + 1
  -- RAISE NOTICE 'New position: %', newPosition;
  -- RAISE NOTICE 'Last position: %', lastPosition;
  IF newPosition = 0 THEN
    newPosition := lastPosition + 1;
  END IF;

  -- Insere o identificador do motorista no equipamento, sem indicar que
  -- está armazenado na memória, pois esta operação é feita em outra
  -- etapa pelo Gateway que envia o comando para o equipamento.
  INSERT INTO erp.driverIdentifierStore (
    contractorID,
    customerID,
    vehicleID,
    equipmentID,
    driverIdentifierID,
    memPosition,
    registeredByUserID
  ) VALUES (
    FcontractorID,
    FcustomerID,
    FvehicleID,
    FequipmentID,
    FdriverIdentifierID,
    newPosition,
    FuserID
  ) RETURNING driverIdentifierStoreID INTO storeID;

  -- Incrementa a quantidade de posições utilizadas na memória deste
  -- equipamento
  UPDATE erp.equipments
     SET iButtonsMemUsed = memUsed + 1
   WHERE equipmentID = FequipmentID;

  RETURN storeID;
END
$$
LANGUAGE 'plpgsql';

-- SELECT erp.insertDriverIdentifierInEquipment(1, 42, 26, 39, 1, 6) AS DriverIdentifierStoreID;
-- SELECT erp.insertDriverIdentifierInEquipment(1, 42, 26, 39, 2, 6) AS DriverIdentifierStoreID;
-- SELECT erp.insertDriverIdentifierInEquipment(1, 42, 26, 39, 3, 6) AS DriverIdentifierStoreID;

-- ---------------------------------------------------------------------
-- Remove um identificador de motorista do equipamento
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que remove um identificador de motorista do
-- equipamento.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.deleteDriverIdentifierFromEquipment(
  FcontractorID integer, FstoreID integer, FuserID integer)
RETURNS boolean AS
$$
DECLARE
  FequipmentID integer;
  FusedMem integer;
  Funiversal boolean;
BEGIN
  SELECT store.equipmentID,
         equipment.iButtonsMemUsed,
         identifier.isUniversal
    INTO FequipmentID,
         FusedMem,
         Funiversal
    FROM erp.driverIdentifiers AS identifier
   INNER JOIN erp.driverIdentifierStore AS store USING (driverIdentifierID)
   INNER JOIN erp.equipments AS equipment USING (equipmentID)
   WHERE store.contractorID = FcontractorID
     AND store.driverIdentifierStoreID = FstoreID;

  -- Verifica se o identificador é universal, ou seja, se ele deve ser
  -- mantido sempre em todos os equipamentos
  IF Funiversal THEN
    RAISE EXCEPTION 'O identificador de motorista é universal e não pode ser removido';
  END IF;
  
  -- Retira o identificador do motorista do equipamento, liberando a
  -- posição de memória.
  UPDATE erp.driverIdentifierStore
     SET toRemove = TRUE,
         removedAt = CASE
           WHEN stored THEN NULL
           ELSE CURRENT_TIMESTAMP
         END,
         deleted = TRUE,
         deletedAt = CURRENT_TIMESTAMP,
         deletedByUserID = FuserID
   WHERE contractorID = FcontractorID
     AND driverIdentifierStoreID = FstoreID;

  -- Decrementa a quantidade de posições utilizadas na memória deste
  -- equipamento
  if FusedMem > 0 THEN
    UPDATE erp.equipments
       SET iButtonsMemUsed = FusedMem - 1
     WHERE equipmentID = FequipmentID;
  END IF;

  RETURN TRUE;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Atualiza um identificador de motorista de um motorista
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que modifica um identificador de motorista,
-- retirando da memória de todos os equipamentos onde estiver
-- associado a informação do identificador anterior antes de modificar.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.updateDriverIdentifierForDriver(
  FcontractorID integer, FdriverID integer, FdriverIdentifierID integer,
  FuserID integer)
RETURNS boolean AS
$$
DECLARE
  storeData record;
  FequipmentID integer;
  FusedMem integer;
BEGIN
  -- Primeiro, seleciona todos os equipamentos onde o identificador
  -- anterior está associado e retira o identificador de motorista de
  -- cada um dos equipamentos
  FOR storeData IN
    SELECT store.driverIdentifierStoreID,
           store.customerID,
           store.vehicleID,
           store.equipmentID,
           equipment.iButtonsMemUsed,
           store.memPosition
      FROM erp.driverIdentifiers AS identifier
     INNER JOIN erp.driverIdentifierStore AS store USING (driverIdentifierID)
     INNER JOIN erp.equipments AS equipment USING (equipmentID)
     WHERE identifier.contractorID = FcontractorID
       AND identifier.driverID = FdriverID
       AND store.deleted = FALSE
  LOOP
    -- Retira o identificador do motorista do equipamento, liberando a
    -- posição de memória.
    UPDATE erp.driverIdentifierStore
       SET toRemove = TRUE,
           removedAt = CASE
             WHEN stored THEN NULL
             ELSE CURRENT_TIMESTAMP
           END,
           deleted = TRUE,
           deletedAt = CURRENT_TIMESTAMP,
           deletedByUserID = FuserID
     WHERE driverIdentifierStoreID = storeData.driverIdentifierStoreID;

    IF FdriverIdentifierID IS NOT NULL THEN
      -- Se informado um novo identificador, então adiciona a informação
      -- do novo identificador no equipamento
      INSERT INTO erp.driverIdentifierStore (contractorID, customerID,
        vehicleID, equipmentID, driverIdentifierID, memPosition,
        registeredByUserID
      ) VALUES (FcontractorID,
        storeData.customerID,
        storeData.vehicleID,
        storeData.equipmentID,
        FdriverIdentifierID,
        storeData.memPosition,
        FuserID
      );
    ELSE
      -- Como não tem um novo identificador, então decrementa a
      -- quantidade de posições utilizadas na memória deste equipamento,
      -- pois o identificador foi removido
      if storeData.iButtonsMemUsed > 0 THEN
        UPDATE erp.equipments
           SET iButtonsMemUsed = storeData.iButtonsMemUsed - 1
         WHERE equipmentID = storeData.equipmentID;
      END IF;
    END IF;
  END LOOP;

  -- Agora, retira o motorista do identificador anterior
  UPDATE erp.driverIdentifiers
     SET driverID = NULL,
         updatedAt = CURRENT_TIMESTAMP,
         updatedByUserID = FuserID
   WHERE contractorID = FcontractorID
     AND driverID = FdriverID;
  
  -- Por fim, associa o motorista ao novo identificador
  UPDATE erp.driverIdentifiers
     SET driverID = FdriverID,
         updatedAt = CURRENT_TIMESTAMP,
         updatedByUserID = FuserID
   WHERE contractorID = FcontractorID
     AND driverIdentifierID = FdriverIdentifierID;

  RETURN TRUE;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Libera um identificador de motorista de um motorista
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que libera um identificador de motorista,
-- retirando da memória de todos os equipamentos onde estiver
-- associado a informação do identificador.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.clearDriverInDriverIdentifier(
  FcontractorID integer, FdriverID integer, FuserID integer)
RETURNS boolean AS
$$
DECLARE
  storeData record;
  FequipmentID integer;
  FusedMem integer;
BEGIN
  -- Seleciona todos os equipamentos onde o identificador está associado
  -- e retira o identificador de motorista de cada um dos equipamentos
  FOR storeData IN
    SELECT store.equipmentID,
           equipment.iButtonsMemUsed,
           store.driverIdentifierStoreID
      FROM erp.driverIdentifiers AS identifier
     INNER JOIN erp.driverIdentifierStore AS store USING (driverIdentifierID)
     INNER JOIN erp.equipments AS equipment USING (equipmentID)
     WHERE identifier.contractorID = FcontractorID
       AND identifier.driverID = FdriverID
       AND store.deleted = FALSE
  LOOP
    -- Retira o identificador do motorista do equipamento, liberando a
    -- posição de memória.
    UPDATE erp.driverIdentifierStore
       SET toRemove = TRUE,
           removedAt = CASE
             WHEN stored THEN NULL
             ELSE CURRENT_TIMESTAMP
           END,
           deleted = TRUE,
           deletedAt = CURRENT_TIMESTAMP,
           deletedByUserID = FuserID
     WHERE driverIdentifierStoreID = storeData.driverIdentifierStoreID;

    -- Decrementa a quantidade de posições utilizadas na memória deste
    -- equipamento
    if storeData.iButtonsMemUsed > 0 THEN
      UPDATE erp.equipments
         SET iButtonsMemUsed = storeData.iButtonsMemUsed - 1
       WHERE equipmentID = storeData.equipmentID;
    END IF;
  END LOOP;

  -- Agora, retira o motorista do identificador
  UPDATE erp.driverIdentifiers
     SET driverID = NULL,
         updatedAt = CURRENT_TIMESTAMP,
         updatedByUserID = FuserID
   WHERE contractorID = FcontractorID
     AND driverID = FdriverID;

  RETURN TRUE;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Remove um identificador de motorista
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que remove um identificador de motorista,
-- e que também retira da memória de todos os equipamentos onde estiver
-- associado.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.deleteDriverIdentifier(
  FcontractorID integer, FdriverIdentifierID integer, FuserID integer)
RETURNS boolean AS
$$
DECLARE
  storeData record;
  FequipmentID integer;
  FusedMem integer;
BEGIN
  -- Primeiro, seleciona todos os equipamentos onde este identificador
  -- está associado e retira o identificador de motorista de cada um
  FOR storeData IN
    SELECT store.equipmentID,
           equipment.iButtonsMemUsed
      FROM erp.driverIdentifierStore AS store
     INNER JOIN erp.equipments AS equipment USING (equipmentID)
     WHERE store.contractorID = FcontractorID
       AND store.driverIdentifierID = FdriverIdentifierID
  LOOP
    -- Retira o identificador do motorista do equipamento, liberando a
    -- posição de memória.
    UPDATE erp.driverIdentifierStore
       SET toRemove = TRUE,
           removedAt = CASE
             WHEN stored THEN NULL
             ELSE CURRENT_TIMESTAMP
           END,
           deleted = TRUE,
           deletedAt = CURRENT_TIMESTAMP,
           deletedByUserID = FuserID
     WHERE contractorID = FcontractorID
       AND driverIdentifierID = FdriverIdentifierID
       AND ((stored = true) OR (stored = false AND deleted = false));

    -- Decrementa a quantidade de posições utilizadas na memória deste
    -- equipamento
    if storeData.iButtonsMemUsed > 0 THEN
      UPDATE erp.equipments
         SET iButtonsMemUsed = storeData.iButtonsMemUsed - 1
       WHERE equipmentID = storeData.equipmentID;
    END IF;
  END LOOP;

  -- Agora, retira o identificador de motorista
  UPDATE erp.driverIdentifiers
     SET active = FALSE,
         deleted = TRUE,
         deletedAt = CURRENT_TIMESTAMP,
         deletedByUserID = FuserID
   WHERE contractorID = FcontractorID
     AND driverIdentifierID = FdriverIdentifierID;

  RETURN TRUE;
END
$$
LANGUAGE 'plpgsql';
