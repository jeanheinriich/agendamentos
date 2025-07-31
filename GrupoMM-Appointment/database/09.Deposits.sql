-- =====================================================================
-- Controle de armazenamento
-- =====================================================================
-- Tabelas utilizada no controle dos locais onde os dispositivos
-- (equipamentos ou SIM Cards) estão armazenados, permitindo um registro
-- acerca da movimentação destes dispositivos.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Depósitos
-- ---------------------------------------------------------------------
-- Os locais físicos onde um dispositivo pode estar armazenado.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.deposits (
  depositID     serial,         -- Número de identificação do depósito
  contractorID  integer         -- ID do contratante
                NOT NULL,
  name          varchar(50)     -- O nome deste depósito
                NOT NULL,
  comments      text,           -- Uma descrição do local
  deviceType    DeviceType      -- O tipo de dispositivo armazenável
                NOT NULL
                DEFAULT 'Both',
  master        boolean         -- A flag indicativa de que o depósito é
                DEFAULT false,  -- o principal
  UNIQUE (contractorID, name),
  PRIMARY KEY (depositID),      -- O indice primário
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

INSERT INTO erp.deposits (contractorID, name, comments, deviceType, master) VALUES
  ( 1, 'Depósito principal', 'O depósito na sede do contratante', 'Both', true);

-- ---------------------------------------------------------------------
-- Transações no depósito
-- ---------------------------------------------------------------------
-- Gatilho para lidar com os depósitos
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.depositTransaction()
RETURNS trigger AS $$
BEGIN
  -- Esta função tem por finalidade garantir que tenhamos um único
  -- depósito principal. Faz uso da variável especial TG_OP para
  -- verificar a operação sendo executada e de TG_WHEN para determinar
  -- o instante em que isto ocorre
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'AFTER') THEN
      IF (NEW.master = true) THEN
        -- Precisamos forçar todos os demais depósitos deste contratante
        -- e que armazenem o mesmo tipo de dispositivo de que nenhum
        -- deles é o master agora
        UPDATE erp.deposits
           SET master = FALSE
         WHERE depositID <> NEW.depositID
           AND contractorID = NEW.contractorID
           AND deviceType = NEW.deviceType;
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica se informamos o ID de um contratante
      IF (NEW.contractorID IS NOT NULL) THEN
        -- Verifica se estamos modificando o contratante
        IF (NEW.contractorID <> OLD.contractorID) THEN
          -- O ID do contratante nunca pode ser modificado
          RAISE
            'Você não pode modificar o contratante'
            USING ERRCODE = 'restrict_violation';
        END IF;
      END IF;

      -- Verifica se modificamos o tipo de dispositivo que ele armazena
      IF (NEW.deviceType IS NOT NULL) THEN
        -- Verifica se estamos modificando o tipo de dispositivo
        -- armazenável
        IF (NEW.deviceType <> OLD.deviceType) THEN
          -- O tipo de dispositivo armazenável não pode ser modificado
          RAISE
            'Você não pode modificar o tipo de dispositivo que este depósito armazena'
            USING ERRCODE = 'restrict_violation';
        END IF;
      END IF;
    ELSIF (TG_WHEN = 'AFTER') THEN
      IF (NEW.master = true) THEN
        -- Precisamos forçar todos os demais depósitos deste contratante
        -- de que nenhum deles é o master agora
        UPDATE erp.deposits
           SET master = FALSE
         WHERE depositID <> NEW.depositID
           AND contractorID = NEW.contractorID
           AND deviceType = NEW.deviceType;
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Retornamos a entidade
    RETURN OLD;
  END IF;
  
  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER depositTransactionTriggerBefore
  BEFORE INSERT OR UPDATE OR DELETE ON erp.deposits
  FOR EACH ROW EXECUTE PROCEDURE erp.depositTransaction();
CREATE TRIGGER depositTransactionTriggerAfter
  AFTER INSERT OR UPDATE ON erp.deposits
  FOR EACH ROW EXECUTE PROCEDURE erp.depositTransaction();

-- ---------------------------------------------------------------------
-- Dados do local de armazenamento
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera o local de armazenamento de um
-- dispositivo
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getStorageLocation(storageLocation StorageType,
  FdepositID integer, typeOfDevice DeviceType, FdeviceID integer)
RETURNS varchar(50) AS $$
DECLARE
  locationName  varchar(50);
BEGIN
  CASE (storageLocation)
    WHEN 'Installed' THEN
      IF (typeOfDevice = 'SimCard') THEN
        locationName := 'Instalado no equipamento NS ';
        -- Recupera o número de série do equipamento
        SELECT 'Instalado no equipamento NS ' || serialnumber INTO locationName
          FROM erp.equipments
         WHERE equipments.equipmentID = FdeviceID;
        IF NOT FOUND THEN
          -- Disparamos uma exceção
          RAISE EXCEPTION 'O equipamento ID % é inválido', FdeviceID
          USING HINT = 'Por favor, verifique os equipamentos cadastrados.';
        END IF;
      ELSE
        -- Recupera a placa do veículo
        SELECT 'Instalado no veículo placa ' || plate INTO locationName
          FROM erp.vehicles
         WHERE vehicles.vehicleID = FdeviceID;
        IF NOT FOUND THEN
          -- Disparamos uma exceção
          RAISE EXCEPTION 'O veículo ID % é inválido', FdeviceID
          USING HINT = 'Por favor, verifique os veículos cadastrados.';
        END IF;
      END IF;
    WHEN 'StoredWithTechnician' THEN
      locationName := 'De posse do técnico';
    WHEN 'StoredWithServiceProvider' THEN
      locationName := 'De posse do prestador de serviços';
    WHEN 'UnderMaintenance' THEN
      locationName := 'Em manutenção';
    WHEN 'ReturnedToSupplier' THEN
      locationName := 'Devolvido ao fornecedor';
    ELSE
      -- Recupera o nome do depósito
      SELECT 'Disponível em ' || lower(name) INTO locationName
        FROM erp.deposits
       WHERE deposits.depositID = FdepositID;
      IF NOT FOUND THEN
        -- Disparamos uma exceção
        RAISE EXCEPTION 'O depósito ID % é inválido', FdepositID
        USING HINT = 'Por favor, verifique os locais de armazenamento cadastrados.';
      END IF;
  END CASE;

  RETURN locationName;
END;
$$ LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- Registro de movimentações nos dispositivos
-- ---------------------------------------------------------------------
-- Um histórico de todas as movimentações ocorridas com cada dispositivo
-- cadastrado para permitir rastreabilidade.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.deviceOperationLogs (
  logID             bigserial,      -- Número de identificação do registro
  contractorID      integer         -- ID do contratante
                    NOT NULL,
  assignedToID      integer,        -- O ID a quem foi comodatado
  deviceType        DeviceType      -- O tipo do dispositivo
                    NOT NULL
                    CHECK (deviceType IN ('SimCard', 'Equipment')),
  deviceID          integer         -- O ID do dispositivo
                    NOT NULL,
  operation         OperationType   -- A operação realizada
                    NOT NULL,
  storageLocation   StorageType     -- O local onde encontra-se armazenado
                    NOT NULL,
  installedAt       integer         -- O ID do equipamento ou veículo em
                    DEFAULT NULL,   -- que foi instalado
  slotNumber        smallint        -- O número do Slot do equipamento
                    DEFAULT NULL,   -- em que foi instalado (SIM Cards)
  equipmentStateID  integer         -- ID da situação em que se encontra
                    DEFAULT 1,      -- o equipamento
  technicianID      integer         -- O ID do técnico que está com a
                    DEFAULT NULL,   -- posse
  serviceProviderID integer         -- O ID do prestador de serviços que
                    DEFAULT NULL,   -- está com a posse
  depositID         integer         -- O ID do depósito onde está
                    DEFAULT NULL,   -- armazenado
  performedAt       timestamp       -- A data da operação
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  performedByUserID integer         -- O ID do usuário responsável pela
                    NOT NULL,       -- operação
  PRIMARY KEY (logID),              -- O indice primário
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (assignedToID)
    REFERENCES erp.entities(entityID)
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
  FOREIGN KEY (performedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Dados do histórico de um dispositivo
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de histórico
-- para um dispositivo
-- ---------------------------------------------------------------------
CREATE TYPE erp.historyData AS
(
  logID                 integer,
  deviceType            DeviceType,
  deviceID              integer,
  performedAt           timestamp,
  operation             OperationType,
  description           varchar(200),
  stateID               integer,
  stateName             varchar(30),
  performedByUserID     integer,
  performedByUserName   varchar(50),
  fullcount             integer
);

CREATE OR REPLACE FUNCTION erp.getHistoryData(FcontractorID integer,
  FdeviceType DeviceType, FDeviceID integer, FPeriod integer,
  Skip integer, LimitOf integer)
RETURNS SETOF erp.historyData AS
$$
DECLARE
  historyData  erp.historyData%rowtype;
  row          record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  FPeriodDate  date;
  description  varchar(200);
  assignedToID integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FdeviceType IS NULL) THEN
    FdeviceType = 'Both';
  END IF;
  IF (FdeviceID IS NULL) THEN
    FdeviceID = 0;
  END IF;
  CASE FPeriod
    WHEN 30 THEN
      -- Os últimos 30 dias
      FPeriodDate = CURRENT_DATE - interval '1 month';
    WHEN 60 THEN
      -- Os últimos 60 dias
      FPeriodDate = CURRENT_DATE - interval '2 month';
    WHEN 90 THEN
      -- Os últimos 60 dias
      FPeriodDate = CURRENT_DATE - interval '3 month';
    WHEN 180 THEN
      -- Os últimos 90 dias
      FPeriodDate = CURRENT_DATE - interval '6 month';
    WHEN 365 THEN
      -- O último ano
      FPeriodDate = CURRENT_DATE - interval '1 year';
    ELSE
      -- Qualquer período
      FPeriodDate = NULL;
  END CASE;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  
  filter := '';
  IF (FPeriodDate IS NOT NULL) THEN
    -- Determina um período para realizar a pesquisa
    filter := filter || format(' AND logs.performedAt >= ''%s 00:00:00''::timestamp',
                               FPeriodDate);
  ELSE
  END IF;

  IF (FDeviceID > 0) THEN
    filter := filter || format(' AND logs.deviceID = %s', FdeviceID);
  END IF;

  IF (FdeviceType <> 'Both') THEN
    filter := filter || format(' AND logs.deviceType = ''%s''', FdeviceType);
  END IF;

  -- Monta a consulta
  query := format('SELECT logs.logID,
                          logs.deviceType,
                          logs.deviceID,
                          logs.operation,
                          logs.storageLocation,
                          logs.depositID,
                          deposits.name AS depositName,
                          logs.installedAt,
                          CASE logs.deviceType
                            WHEN ''SimCard'' THEN equipments.serialNumber
                            ELSE vehicles.plate
                          END AS installedAtName,
                          logs.slotNumber,
                          logs.equipmentStateID,
                          states.name AS equipmentStateName,
                          logs.technicianID,
                          technicians.name AS technicianName,
                          logs.serviceProviderID,
                          serviceProviders.name AS serviceProviderName,
                          logs.assignedToID,
                          logs.performedAt,
                          logs.performedByUserID,
                          performedusers.name AS performedByUserName,
                          count(*) OVER() AS fullcount
                     FROM erp.deviceOperationLogs AS logs
                    INNER JOIN erp.equipmentStates AS states ON (logs.equipmentStateID = states.equipmentStateID)
                     LEFT JOIN erp.deposits ON (logs.depositID = deposits.depositID)
                     LEFT JOIN erp.equipments ON (logs.installedAt = equipments.equipmentID AND logs.deviceType = ''SimCard'')
                     LEFT JOIN erp.vehicles ON (logs.installedAt = vehicles.vehicleID AND logs.deviceType = ''Equipment'')
                     LEFT JOIN erp.users AS technicians ON (logs.technicianID = technicians.userID)
                     LEFT JOIN erp.entities AS serviceProviders ON (logs.serviceProviderID = serviceProviders.entityID)
                    INNER JOIN erp.users AS performedusers ON (logs.performedByUserID = performedusers.userID)
                    WHERE (logs.contractorID = %s OR logs.assignedToID = %s) %s
                    ORDER BY logs.performedAt %s',
                  FContractorID, FContractorID, filter, limits);
  -- RAISE NOTICE 'SQL: %', query;
  FOR row IN EXECUTE query
  LOOP
    historyData.logID               := row.logID;
    historyData.deviceType          := row.deviceType;
    historyData.deviceID            := row.deviceID;
    historyData.performedAt         := row.performedAt;
    historyData.operation           := row.operation;

    -- Descreve a operação realizada
    CASE row.operation
      WHEN 'Acquired' THEN
        description := 'Adquirido';
      WHEN 'Transferred' THEN
        BEGIN
          CASE row.storageLocation
            WHEN 'StoredWithTechnician' THEN
              description := 'Enviado para o(a) técnico(a) ' || row.technicianName;
            WHEN 'StoredWithServiceProvider' THEN
              description := 'Enviado para o prestador de serviços ' || row.serviceProviderName;
            ELSE
              IF (row.assignedToID IS NOT NULL) THEN
                description := 'Transferido para ' || row.depositName || ' do comodatário';
              ELSE
                description := 'Transferido para ' || row.depositName;
              END IF;
          END CASE;
        END;
      WHEN 'Returned' THEN
        BEGIN
          CASE row.storageLocation
            WHEN 'StoredWithTechnician' THEN
              description := 'Devolvido para o(a) técnico(a) ' || row.technicianName;
            WHEN 'StoredWithServiceProvider' THEN
              description := 'Devolvido para o prestador de serviços ' || row.serviceProviderName;
            WHEN 'ReturnedToSupplier' THEN
              description := 'Devolvido para o fornecedor';
            ELSE
              description := 'Devolvido e armazenado em ' || row.depositName;
          END CASE;
        END;
      WHEN 'Installed' THEN
        BEGIN
          CASE row.deviceType
            WHEN 'SimCard' THEN
              description := 'Instalado no slot ' || row.slotNumber || ' do equipamento nº de série ' || row.installedAtName;
            ELSE
              description := 'Instalado no veículo placa ' || row.installedAtName;
          END CASE;
        END;
      WHEN 'Uninstalled' THEN
        BEGIN
          CASE row.storageLocation
            WHEN 'StoredWithTechnician' THEN
              description := 'Desinstalado e de posse do(a) técnico(a) ' || row.technicianName;
            WHEN 'StoredWithServiceProvider' THEN
              description := 'Desinstalado e de posse do prestador de serviços ' || row.serviceProviderName;
            ELSE
              description := 'Desinstalado e armazenado em ' || row.depositName;
          END CASE;        
        END;
      WHEN 'DefectDetected' THEN
        description := 'Informado defeito';
      WHEN 'SentForMaintenance' THEN
        description := 'Enviado para manutenção em ' || row.serviceProviderName;
      ELSE
        description := 'Operação inválida';
    END CASE;
    historyData.description         := description;
    historyData.stateID             := row.equipmentStateID;
    historyData.stateName           := row.equipmentStateName;
    historyData.performedByUserID   := row.performedByUserID;
    historyData.performedByUserName := row.performedByUserName;
    historyData.fullcount           := row.fullcount;
    -- RAISE NOTICE 'Contractor %', row.contractorBlocked;
    -- RAISE NOTICE 'SIM Card %', row.simCardBlocked;
    -- RAISE NOTICE 'Subsidiary %', row.subsidiaryBlocked;

    RETURN NEXT historyData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getHistoryData(1, 'Both', 0, 0, 0, 0);
