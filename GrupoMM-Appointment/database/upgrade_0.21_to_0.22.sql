-- =====================================================================
-- MODIFICAÇÕES NO CADASTRO DE VEÍCULOS PARA SUPORTE A 2 RASTREADORES
-- =====================================================================
-- Esta modificação visa melhorar o suporte a múltiplos rastreadores,
-- sendo um o rastreador principal e os demais rastreadores de
-- contingência.
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- Alterações no cadastro de equipamentos
-- ---------------------------------------------------------------------
-- Precisamos passar os campos de local da instalação dos equipamentos
-- para dentro da tabela de equipamentos, transferindo estas informações
-- que hoje estão na tabela de veículos para esta tabela.
-- 
-- Também é preciso definir quem é o rastreador principal dos diversos
-- rastreadores que podem estar vinculados à um veículo, tornando
-- automaticamente todos os demais rastreadores reserva. Também é
-- preciso implementar mecanismo para lidar com o rastreador principal
-- e reserva no que tange ao processo de vínculo dentro do sistema.
-- ---------------------------------------------------------------------

-- O indicativo de rastreador principal
ALTER TABLE erp.equipments
  ADD COLUMN main boolean DEFAULT false;
-- O local de instalação do equipamento
ALTER TABLE erp.equipments
  ADD COLUMN installationSite varchar(100) DEFAULT NULL;
-- O local onde foi instalado o bloqueio
ALTER TABLE erp.equipments
  ADD COLUMN blockingSite varchar(100) DEFAULT NULL;
-- O local onde foi instalado a sirene
ALTER TABLE erp.equipments
  ADD COLUMN sirenSite varchar(100) DEFAULT NULL;
-- O local onde foi instalado o botão de pânico
ALTER TABLE erp.equipments
  ADD COLUMN panicButtonSite varchar(100) DEFAULT NULL;

-- Trazemos os campos da tabela veículos para a tabela de equipamentos
WITH oldData AS (
  SELECT E.equipmentID,
         V.installationSite,
         V.blockingSite,
         V.sirenSite,
         V.panicButtonSite
    FROM erp.vehicles AS V
   INNER JOIN erp.equipments AS E USING (vehicleID)
   WHERE E.vehicleID = V.vehicleID
     AND E.storagelocation = 'Installed'
)
UPDATE erp.equipments AS E
   SET installationSite=oldData.installationSite,
       blockingSite=oldData.blockingSite,
       sirenSite=oldData.sirenSite,
       panicButtonSite=oldData.panicButtonSite
  FROM oldData
 WHERE E.equipmentID = oldData.equipmentID;

-- Marcamos quais os equipamentos são os principais, considerando o
-- equipamento mais antigo como o principal sempre
WITH masterEquipment AS (
  SELECT DISTINCT ON (vehicleID)
         equipmentID,
         vehicleID
    FROM erp.equipments
   WHERE storagelocation = 'Installed'
   ORDER BY vehicleID, installedAt
)
UPDATE erp.equipments AS E
   SET main=true
  FROM masterEquipment
 WHERE E.equipmentID = masterEquipment.equipmentID;

-- Apagamos os campos obsoletos
ALTER TABLE erp.vehicles
  DROP COLUMN installationSite;
-- O local onde foi instalado o bloqueio
ALTER TABLE erp.vehicles
  DROP COLUMN blockingSite;
-- O local onde foi instalado a sirene
ALTER TABLE erp.vehicles
  DROP COLUMN sirenSite;
-- O local onde foi instalado o botão de pânico
ALTER TABLE erp.vehicles
  DROP COLUMN panicButtonSite;

-- Atualizamos a função que lida com as transações em equipamentos
CREATE OR REPLACE FUNCTION erp.equipmentTransaction()
RETURNS trigger AS $$
DECLARE
  logOperation  boolean;
  operation public.operationtype;
  reason varchar(100);
  installationStartDate date;
  cooperative  boolean;
  joint  record;
  remainingJoints  integer;
  vehicle  record;
BEGIN
  -- Lida com a movimentação do equipamento. Faz uso da variável especial
  -- TG_OP para verificar a operação executada e de TG_WHEN para
  -- determinar o instante em que isto ocorre
  -- RAISE NOTICE 'Operation % %', TG_OP, TG_WHEN;
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica se informamos o local de armazenamento
      IF (NEW.storageLocation IS NULL) THEN
        -- Não foi informado um local de armazenamento, então definimos
        -- como estando armazenado no depósito
        NEW.storageLocation := 'StoredOnDeposit';
      ELSE
        IF (NEW.storageLocation <> 'StoredOnDeposit') THEN
          -- O local de armazenamento é inválido
          RAISE
            'Não é possível utilizar o local de armazenamento informado nesta operação'
            USING ERRCODE = 'restrict_violation';
        END IF;
      END IF;

      -- Força as demais informações em função do mesmo estar sendo
      -- armazenando num depósito
      -- 1. Não está associado à um técnico
      NEW.technicianID := null;
      -- 2. Não está associado à um prestador de serviços
      NEW.serviceProviderID := null;
      -- 3. Não está instalado em um veículo
      NEW.vehicleID := null;
      -- 3. Não pertence à nenhuma instalação
      NEW.installationID := null;
      -- 4. Não possui uma data de instalação
      NEW.installedAt := null;
      -- 5. Não está bloqueado
      NEW.blocked := false;
      -- 6. Está em pleno funcionamento
      NEW.equipmentStateID := 1;
      -- 7. Os locais de instalação não estão definidos
      NEW.installationSite := null;
      NEW.blockingSite := null;
      NEW.sirenSite := null;
      NEW.panicButtonSite := null;
      -- 8. Não é o equipamento principal
      NEW.main := false;

      IF (NEW.depositID IS NULL) THEN
        RAISE
          'O ID do depósito onde o equipamento será armazenado não pode ser nulo'
          USING ERRCODE = 'not_null_violation';
      END IF;
    ELSIF (TG_WHEN = 'AFTER') THEN
      -- Registramos a aquisição do equipamento
      INSERT INTO erp.deviceOperationLogs (contractorID, deviceType,
        deviceID, operation, storageLocation, depositID, performedAt,
        performedByUserID) VALUES
        (NEW.contractorID, 'Equipment', NEW.equipmentID, 'Acquired', 
         'StoredOnDeposit', NEW.depositID, NEW.createdAt,
         NEW.createdByUserID);
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

      -- Verifica se estamos bloqueando o equipamento
      IF (NEW.blocked = true) THEN
        IF (OLD.storageLocation <> 'StoredOnDeposit') THEN
          -- O equipamento deve estar de posse do contratante
          CASE (NEW.storageLocation)
            WHEN 'Installed' THEN
              reason := 'está instalado em um veículo';
            WHEN 'StoredWithTechnician' THEN
              reason := 'está de posse de um técnico';
            WHEN 'StoredWithServiceProvider' THEN
              reason := 'está de posse de um prestador de serviços';
            WHEN 'UnderMaintenance' THEN
              reason := 'está em manutenção';
            ELSE
              reason := 'foi devolvido ao fornecedor';
          END CASE;
          
          RAISE
            'Você não pode bloquear um equipamento que %', reason
            USING ERRCODE = 'restrict_violation';
        END IF;
      END IF;

      -- Verifica se estamos realizando uma movimentação do equipamento
      IF ( (OLD.storageLocation <> NEW.storageLocation) OR
           (OLD.depositID <> NEW.depositID) OR
           (OLD.technicianID <> NEW.technicianID) OR
           (OLD.serviceProviderID <> NEW.serviceProviderID) OR
           (OLD.vehicleID <> NEW.vehicleID) OR
           (OLD.equipmentStateID <> NEW.equipmentStateID) ) THEN
        -- RAISE NOTICE 'Ocorreu alguma modificação que precisa ser analisada';
        -- Verifica se o equipamento encontra-se bloqueado
        IF (OLD.blocked = true) THEN
          IF (NEW.storageLocation <> 'StoredOnDeposit') THEN
            -- Não podemos movimentar um equipamento bloqueado, então
            -- determina o motivo
            CASE (NEW.storageLocation)
              WHEN 'Installed' THEN
                reason := 'instalar';
              WHEN 'StoredWithTechnician' THEN
                reason := 'enviar para um técnico';
              WHEN 'StoredWithServiceProvider' THEN
                reason := 'enviar para um prestador de serviços';
              WHEN 'UnderMaintenance' THEN
                reason := 'enviar para manutenção';
              ELSE
                reason := 'devolver ao fornecedor';
            END CASE;

            RAISE
              'Você não pode % um equipamento que está bloqueado', reason
              USING ERRCODE = 'restrict_violation';
          END IF;
        END IF;
        
        -- Conforme o local de armazenamento, realiza as devidas
        -- checagens
        -- RAISE NOTICE 'NEW storageLocation %', NEW.storageLocation;
        CASE (NEW.storageLocation)
          WHEN 'StoredOnDeposit' THEN
            -- Quando especificado um depósito, verifica se foi informada
            -- a ID deste depósito
            IF (NEW.depositID IS NULL) THEN
              RAISE
                'O ID do depósito onde o equipamento será armazenado não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;

            -- Força as demais informações em função do mesmo estar sendo
            -- armazenando num depósito
            -- 1. Não está associado à um técnico
            NEW.technicianID := null;
            -- 2. Não está associado à um prestador de serviços
            NEW.serviceProviderID := null;
            -- 3. Não está instalado em um veículo
            NEW.vehicleID := null;
            -- 4. Não pertence à nehuma instalação
            NEW.installationID := null;
            -- 5. Não possui uma data de instalação
            NEW.installedAt := null;
            -- 6. Os locais de instalação não estão definidos
            NEW.installationSite := null;
            NEW.blockingSite := null;
            NEW.sirenSite := null;
            NEW.panicButtonSite := null;
            -- 7. Não é o equipamento principal
            NEW.main := false;

            -- Verifica de onde está saíndo
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- Estamos recebendo um equipamento que estava instalado
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Você não pode informar que um equipamento retirado está em manutenção'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
              WHEN 'UnderMaintenance' THEN
                -- Estamos recebendo um equipamento que estava em
                -- manutenção
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Você deve informar a situação do recebimento do equipamento após o retorno da manutenção'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
              WHEN 'StoredWithTechnician', 'StoredWithServiceProvider' THEN
                IF (OLD.equipmentStateID <> NEW.equipmentStateID) THEN
                  RAISE
                    'Você não deve modificar a situação do equipamento numa movimentação'
                    USING ERRCODE = 'restrict_violation';
                END IF;
              WHEN 'ReturnedToSupplier' THEN
                IF (NEW.equipmentStateID <> 1) THEN
                  RAISE
                    'Você não deve receber equipamentos de um fornecedor com defeitos'
                    USING ERRCODE = 'restrict_violation';
                END IF;
              ELSE
                IF (NEW.equipmentStateID = 3) THEN
                  RAISE
                    'Você não deve indicar que um equipamento está em manutenção num depósito'
                    USING ERRCODE = 'restrict_violation';
                END IF;
            END CASE;
          WHEN 'Installed' THEN
            -- Quando especificado que está instalado em um veículo,
            -- verifica se foi informado o ID do veículo em que o mesmo
            -- foi instalado
            IF (NEW.vehicleID IS NULL) THEN
              RAISE
                'O ID do veículo onde o equipamento será instalado não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;
            IF (NEW.installationID IS NULL) THEN
              RAISE
                'O ID da instalação não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;
            IF (NEW.installedAt IS NULL) THEN
              RAISE
                'A data da instalação não pode ser nula'
                USING ERRCODE = 'not_null_violation';
            END IF;

            -- Força as demais informações em função do mesmo estar
            -- sendo instalado em um equipamento
            -- 1. Não está associado à um técnico
            NEW.technicianID := null;
            -- 2. Não está associado à um prestador de serviços
            NEW.serviceProviderID := null;

            -- Verifica de onde está saíndo
            CASE (OLD.storageLocation)
              WHEN 'ReturnedToSupplier' THEN
                -- Estamos tentando instalar um equipamento que não está
                -- mais de posse do contratante (está de posse do
                -- fornecedor)
                RAISE
                  'Você não pode instalar um equipamento que está de posse do fornecedor'
                  USING ERRCODE = 'restrict_violation';
              WHEN 'UnderMaintenance' THEN
                -- Estamos tentando instalar um equipamento que está em
                -- manutenção
                RAISE
                  'Você não pode instalar um equipamento que está em manutenção'
                  USING ERRCODE = 'restrict_violation';
              ELSE
                -- Prossegue normalmente
            END CASE;

            -- Verifica o estado do equipamento
            CASE (OLD.equipmentStateID)
              WHEN 2 THEN
                -- Estamos tentando instalar um equipamento que está com
                -- defeito
                RAISE
                  'Você não pode instalar um equipamento que está com defeito'
                  USING ERRCODE = 'restrict_violation';
              WHEN 3 THEN
                -- Estamos tentando instalar um equipamento que está em
                -- manutenção
                RAISE
                  'Você não pode instalar um equipamento que está em manutenção'
                  USING ERRCODE = 'restrict_violation';
              WHEN 4 THEN
                -- Estamos tentando instalar um equipamento que está
                -- inutilizado
                RAISE
                  'Você não pode instalar um equipamento que está em inutilizado'
                  USING ERRCODE = 'restrict_violation';
              ELSE
                -- Prossegue normalmente
            END CASE;

            IF (NEW.equipmentStateID <> 1) THEN
              RAISE
                'A situação do equipamento é inválida para uma operação de instalação'
                USING ERRCODE = 'restrict_violation';
            END IF;
          WHEN 'StoredWithTechnician' THEN
            -- Quando especificado que está de posse de um técnico, 
            -- verifica se foi informada a ID dele
            IF (NEW.technicianID IS NULL) THEN
              RAISE
                'O ID do técnico que está de posse do equipamento não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;

            -- Força as demais informações em função do mesmo estar de
            -- posse do técnico
            -- 1. Não está associado à um prestador de serviços
            NEW.serviceProviderID := null;
            -- 2. Não está instalado em um veículo
            NEW.vehicleID := null;
            -- 3. Não pertence a nenhuma instalação
            NEW.installationID := null;
            -- 4. Não possui uma data de instalação
            NEW.installedAt := null;
            -- 5. Os locais de instalação não estão definidos
            NEW.installationSite := null;
            NEW.blockingSite := null;
            NEW.sirenSite := null;
            NEW.panicButtonSite := null;
            -- 6. Não é o equipamento principal
            NEW.main := false;

            -- Verifica de onde está saíndo
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- O técnico está retirando um equipamento de um veículo
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Situação do equipamento inválida'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
              WHEN 'ReturnedToSupplier' THEN
                -- Estamos tentando enviar um equipamento que não está
                -- mais de posse do contratante (está de posse do
                -- fornecedor)
                RAISE
                  'Você não pode enviar um equipamento que está de posse do fornecedor'
                  USING ERRCODE = 'restrict_violation';
              WHEN 'UnderMaintenance' THEN
                -- Estamos recebendo um equipamento que estava em
                -- manutenção
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Você deve informar a situação do recebimento do equipamento após o retorno da manutenção'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
              ELSE
                IF (OLD.equipmentStateID <> NEW.equipmentStateID) THEN
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Situação do equipamento inválida'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
            END CASE;
          WHEN 'StoredWithServiceProvider' THEN
            -- Quando especificado que está de posse de um prestador de 
            -- serviços, verifica se foi informada a ID dele
            IF (NEW.serviceProviderID IS NULL) THEN
              RAISE
                'O ID do prestador de serviços que está de posse do equipamento não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;

            -- Força as demais informações em função do mesmo estar de
            -- posse de um prestador de serviços
            -- 1. Não está associado à um técnico
            NEW.technicianID := null;
            -- 2. Não está instalado em um veículo
            NEW.vehicleID := null;
            -- 3. Não pertence à nenhuma instalação
            NEW.installationID := null;
            -- 4. Não possui uma data de instalação
            NEW.installedAt := null;
            -- 5. Os locais de instalação não estão definidos
            NEW.installationSite := null;
            NEW.blockingSite := null;
            NEW.sirenSite := null;
            NEW.panicButtonSite := null;
            -- 6. Não é o equipamento principal
            NEW.main := false;

            -- Verifica de onde está saíndo
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- A prestador de serviços está retirando um equipamento
                -- de um veículo
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Situação do equipamento inválida'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
              WHEN 'ReturnedToSupplier' THEN
                -- Estamos tentando enviar um equipamento que não está
                -- mais de posse do contratante (está de posse do
                -- fornecedor)
                RAISE
                  'Você não pode enviar um equipamento que está de posse do fornecedor'
                  USING ERRCODE = 'restrict_violation';
              WHEN 'UnderMaintenance' THEN
                -- Estamos recebendo um equipamento que estava em
                -- manutenção
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Você deve informar a situação do recebimento do equipamento após o retorno da manutenção'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
            END CASE;
          WHEN 'UnderMaintenance' THEN
            -- Quando especificado que está em manutenção, verifica se
            -- foi informado a ID do prestador de serviços
            IF (NEW.serviceProviderID IS NULL) THEN
              RAISE
                'O ID do prestador de serviços que fará a manutenção do equipamento não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;

            CASE (OLD.equipmentStateID)
              WHEN 1 THEN
                -- Estamos tentando enviar um equipamento que não está com
                -- defeito
                RAISE
                  'Você não pode enviar para manutenção um equipamento que não está com defeito'
                  USING ERRCODE = 'restrict_violation';
              WHEN 2, 3 THEN
                -- Estamos enviando um equipamento que está com defeito,
                -- então prossegue normalmente
              ELSE
                RAISE
                  'Você não pode enviar para manutenção um equipamento que está inutilizado'
                  USING ERRCODE = 'restrict_violation';
            END CASE;

            -- Força as demais informações em função do mesmo estar sendo
            -- enviado para manutenção
            -- 1. Não está associado à um técnico
            NEW.technicianID := null;
            -- 2. Não está instalado em um veículo
            NEW.vehicleID := null;
            -- 3. Não pertence à nenuma instalação
            NEW.installationID := null;
            -- 4. Não possui uma data de instalação
            NEW.installedAt := null;
            -- 5. Informa sempre que está em manutenção
            NEW.equipmentStateID := 3;
            -- 6. Os locais de instalação não estão definidos
            NEW.installationSite := null;
            NEW.blockingSite := null;
            NEW.sirenSite := null;
            NEW.panicButtonSite := null;
            -- 7. Não é o equipamento principal
            NEW.main := false;

            -- Verifica de onde está saíndo
            IF (OLD.storageLocation = 'ReturnedToSupplier') THEN
              -- Estamos tentando enviar um equipamento que não está mais
              -- de posse do contratante (está de posse do fornecedor)
              RAISE
                'Você não pode enviar para manutenção um equipamento que está de posse do fornecedor'
                USING ERRCODE = 'restrict_violation';
            END IF;
          WHEN 'ReturnedToSupplier' THEN
            -- Força as demais informações em função do mesmo estar sendo
            -- devolvido ao fornecedor
            -- 1. Não está associado à um técnico
            NEW.technicianID := null;
            -- 2. Não está associado à um prestador de serviços
            NEW.serviceProviderID := null;
            -- 3. Não está instalado em um veículo
            NEW.vehicleID := null;
            -- 4. Não pertence à nenhuma instalação
            NEW.installationID := null;
            -- 5. Não possui uma data de instalação
            NEW.installedAt := null;
            -- 6. Não está indicado nenhum Slot do equipamento
            NEW.slotNumber := 0;
            -- 7. Os locais de instalação não estão definidos
            NEW.installationSite := null;
            NEW.blockingSite := null;
            NEW.sirenSite := null;
            NEW.panicButtonSite := null;
            -- 8. Não é o equipamento principal
            NEW.main := false;

            -- Verifica de onde está saíndo
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- Estamos tentando devolver um equipamento que está
                -- instalado
                RAISE
                  'Você não pode devolver ao fornecedor um equipamento que ainda está instalado'
                  USING ERRCODE = 'restrict_violation';
              WHEN 'StoredWithTechnician', 'StoredWithServiceProvider', 'UnderMaintenance' THEN
                -- Estamos tentando devolver um equipamento que está de
                -- posse de terceiros
                RAISE
                  'Você não pode devolver ao fornecedor um equipamento que ainda está de posse de terceiros'
                  USING ERRCODE = 'restrict_violation';
              ELSE
                -- Prossegue normalmente
            END CASE;

            IF (NEW.equipmentStateID = 3) THEN
              RAISE
                'Situação do equipamento inválida'
                USING ERRCODE = 'restrict_violation';
            END IF;
          ELSE
            -- O tipo de armazenamento é inválido
            RAISE
              'Local de armazenamento inválido'
              USING ERRCODE = 'restrict_violation';
        END CASE;
      END IF;
    ELSIF (TG_WHEN = 'AFTER') THEN
      IF ( (OLD.storageLocation = 'Installed') AND
           (NEW.storageLocation = 'Installed') ) THEN
        -- Pode ter sido atualizada a informação de instalação
        IF ( (OLD.installedAt <> NEW.installedAt) AND
             (OLD.installationID <> NEW.installationID) ) THEN
          -- Modificamos a data de instalação e em qual instalação ocorreu
          -- no registro de instalação deste equipamento
          UPDATE erp.installationRecords
             SET installedat = NEW.installedAt,
                 installationID = NEW.installationID
           WHERE contractorID = NEW.contractorID
             AND equipmentID = NEW.equipmentID
             AND vehicleID = NEW.vehicleID
             AND uninstalledAt IS NULL;
        END IF;
      END IF;

      IF (NEW.customerPayerID IS NOT NULL) THEN
        -- Precisamos garantir que a informação de associado seja
        -- devidamente atualizada
        SELECT INTO cooperative
               type.cooperative
          FROM erp.entities AS customer
         INNER JOIN erp.entitiesTypes AS type USING (entityTypeID)
         WHERE customer.entityID = NEW.customerPayerID;
        IF (cooperative) THEN
          -- Garantimos que o cliente seja corretamente indicado como
          -- associado desta associação
          SELECT INTO joint
                 affiliationID,
                 joinedAt
            FROM erp.affiliations AS association
           WHERE association.associationID = NEW.customerPayerID
             AND association.associationUnityID = NEW.subsidiaryPayerID
             AND association.customerID = (
                   SELECT V.customerID
                     FROM erp.vehicles AS V
                    WHERE V.vehicleID = NEW.vehicleID
                 )
             AND association.subsidiaryID = (
                   SELECT V.subsidiaryID
                     FROM erp.vehicles AS V
                    WHERE V.vehicleID = NEW.vehicleID
                 )
             AND association.unjoinedAt IS NULL
           FETCH FIRST ROW ONLY;
          IF FOUND THEN
            -- O cliente já está vinculado à associação, então
            -- atualizamos o registro, se necessário
            IF (joint.joinedAt > NEW.installedAt) THEN
              -- Atualizamos a data à partir da qual o cliente se tornou
              -- associado desta cooperativa
              UPDATE erp.affiliations
                 SET joinedAt = NEW.installedAt
               WHERE affiliationID = joint.affiliationID;
            END IF;
          ELSE
            -- O cliente não está vinculado à associação, então criamos
            -- um novo registro indicando esta afiliação
            SELECT INTO vehicle
                   V.customerID,
                   V.subsidiaryID
              FROM erp.vehicles AS V
             WHERE V.vehicleID = NEW.vehicleID;

            INSERT INTO erp.affiliations
                       (associationID, associationUnityID, customerID,
                        subsidiaryID, joinedAt)
                 VALUES (NEW.customerPayerID,
                        NEW.subsidiaryPayerID,
                        vehicle.customerID,
                        vehicle.subsidiaryID,
                        NEW.installedAt);
          END IF;
        END IF;
      END IF;

      IF (OLD.customerPayerID IS NOT NULL AND NEW.customerPayerID IS NULL) THEN
        -- Precisamos garantir que a informação de associado seja
        -- devidamente atualizada
        SELECT INTO cooperative
               type.cooperative
          FROM erp.entities AS customer
         INNER JOIN erp.entitiesTypes AS type USING (entityTypeID)
         WHERE customer.entityID = OLD.customerPayerID;

        IF (cooperative) THEN
          -- Garantimos que o cliente seja corretamente indicado como
          -- associado desta associação se ele ainda contiver outros
          -- vínculos
          SELECT INTO remainingJoints
                 count(*)
            FROM erp.equipments AS E
           INNER JOIN erp.vehicles AS V USING (vehicleID)
           WHERE E.customerPayerID = OLD.customerPayerID
             AND E.subsidiaryPayerID = OLD.subsidiaryPayerID
             AND V.customerID = (
                   SELECT customerID
                     FROM erp.vehicles
                    WHERE vehicleID = OLD.vehicleID
                 )
             AND V.subsidiaryID = (
                   SELECT subsidiaryID
                     FROM erp.vehicles
                    WHERE vehicleID = OLD.vehicleID
                 );
          IF (remainingJoints = 0) THEN
            -- Localizamos o registro de indicação de que o cliente é
            -- associado e atualizamos que o mesmo deixou de ser
            SELECT INTO joint
                   affiliationID
              FROM erp.affiliations AS association
             WHERE association.associationID = OLD.customerPayerID
               AND association.associationUnityID = OLD.subsidiaryPayerID
               AND association.customerID = (
                     SELECT V.customerID
                       FROM erp.vehicles AS V
                      WHERE V.vehicleID = OLD.vehicleID
                   )
               AND association.subsidiaryID = (
                     SELECT V.subsidiaryID
                       FROM erp.vehicles AS V
                      WHERE V.vehicleID = OLD.vehicleID
                   )
               AND association.unjoinedAt IS NULL
             FETCH FIRST ROW ONLY;
            IF FOUND THEN
              UPDATE erp.affiliations
                 SET unjoinedAt = CURRENT_DATE
               WHERE affiliationID = joint.affiliationID;
            END IF;
          END IF;
        END IF;
      END IF;

      -- Verifica se ocorreu alguma outra modificação
      IF ( (OLD.storageLocation <> NEW.storageLocation) OR
           (OLD.depositID <> NEW.depositID) OR
           (OLD.technicianID <> NEW.technicianID) OR
           (OLD.serviceProviderID <> NEW.serviceProviderID) OR
           (OLD.vehicleID <> NEW.vehicleID) ) THEN
        -- Conforme o local de armazenamento, realiza as devidas
        -- checagens
        CASE (NEW.storageLocation)
          WHEN 'StoredOnDeposit' THEN
            -- Verifica se estamos enviando para um depósito ou é uma
            -- devolução
            CASE (OLD.storageLocation)
              WHEN 'StoredOnDeposit' THEN
                -- Verifica se o depósito foi modificado
                IF (OLD.depositID <> NEW.depositID) THEN
                  -- Força o registro da movimentação
                  logOperation := true;
                  operation    := 'Transferred';
                END IF;
              WHEN 'Installed' THEN
                -- Estamos desinstalando, então registramos a operação
                -- no log
                logOperation := true;
                operation    := 'Uninstalled';

                -- Registramos a desinstalação também no último registro
                -- deste equipamento para o veículo no qual ele estava
                -- vinculado
                UPDATE erp.installationRecords
                   SET uninstalledAt = CURRENT_DATE
                 WHERE contractorID = OLD.contractorID
                   AND equipmentID = OLD.equipmentID
                   AND vehicleID = OLD.vehicleID
                   AND uninstalledAt IS NULL;
              WHEN 'ReturnedToSupplier' THEN
                -- Estamos adquirindo novamente
                logOperation := true;
                operation    := 'Acquired';
              ELSE
                -- Estamos realizando a devolução
                logOperation := true;
                operation    := 'Returned';
            END CASE;
          WHEN 'Installed' THEN
            -- Estamos instalando, então registramos a operação
            -- no log
            logOperation := true;
            operation    := 'Installed';

            -- Registramos a instalação também deste equipamento no
            -- veículo no qual ele foi vinculado
            INSERT INTO erp.installationRecords
                   (contractorID, equipmentID, vehicleID, installationID,
                    installedAt, createdAt, createdByUserID, updatedAt,
                    updatedByUserID) VALUES
                   (NEW.contractorID, NEW.equipmentID, NEW.vehicleID,
                    NEW.installationID, NEW.installedAt, NEW.updatedAt,
                    NEW.updatedByUserID, NEW.updatedAt, NEW.updatedByUserID);

            -- Verificamos se a instalação já teve seu início determinado
            SELECT INTO installationStartDate
                   startDate
              FROM erp.installations
             WHERE installationID = NEW.installationID;
            IF (installationStartDate IS NULL) THEN
              -- A primeira instalação determina o início da prestação
              -- de serviços
              UPDATE erp.installations
                 SET startDate = NEW.installedAt,
                     updatedAt = NEW.updatedAt,
                     updatedByUserID = NEW.updatedByUserID
               WHERE installationID = NEW.installationID;
            END IF;
          WHEN 'StoredWithTechnician' THEN
            -- Verifica se estamos enviando para um técnico ou é uma
            -- desinstalação
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- Estamos desinstalando, então registramos a operação
                -- no log
                logOperation := true;
                operation    := 'Uninstalled';

                -- Registramos a desinstalação também no último registro
                -- deste equipamento para o veículo no qual ele estava
                -- vinculado
                UPDATE erp.installationRecords
                   SET uninstalledAt = CURRENT_DATE
                 WHERE contractorID = OLD.contractorID
                   AND equipmentID = OLD.equipmentID
                   AND vehicleID = OLD.vehicleID
                   AND uninstalledAt IS NULL;
              WHEN 'StoredOnDeposit', 'StoredWithServiceProvider' THEN
                -- Estamos realizando a transferência
                logOperation := true;
                operation    := 'Transferred';
              WHEN 'StoredWithTechnician' THEN
                -- Verifica se o técnico foi modificado
                IF (OLD.technicianID <> NEW.technicianID) THEN
                  -- Força o registro da movimentação
                  logOperation := true;
                  operation    := 'Transferred';
                END IF;
              WHEN 'UnderMaintenance' THEN
                -- Estamos devolvendo um equipamento que estava em
                -- manutenção
                logOperation := true;
                operation    := 'Returned';
              ELSE
                -- Prossegue normalmente
            END CASE;
          WHEN 'StoredWithServiceProvider' THEN
            -- Verifica se estamos enviando para um prestador de serviços
            -- ou é uma desinstalação e/ou devolução
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- Estamos desinstalando, então registramos a operação
                -- no log
                logOperation := true;
                operation    := 'Uninstalled';

                -- Registramos a desinstalação também no último registro
                -- deste equipamento para o veículo no qual ele estava
                -- vinculado
                UPDATE erp.installationRecords
                   SET uninstalledAt = CURRENT_DATE
                 WHERE contractorID = OLD.contractorID
                   AND equipmentID = OLD.equipmentID
                   AND vehicleID = OLD.vehicleID
                   AND uninstalledAt IS NULL;
              WHEN 'StoredOnDeposit' THEN
                -- Estamos realizando a transferência
                logOperation := true;
                operation    := 'Transferred';
              WHEN 'StoredWithServiceProvider' THEN
                -- Verifica se o prestador de serviços foi modificado
                IF (OLD.serviceProviderID <> NEW.serviceProviderID) THEN
                  -- Força o registro da movimentação
                  logOperation := true;
                  operation    := 'Transferred';
                END IF;
              WHEN 'StoredWithTechnician', 'UnderMaintenance' THEN
                -- Estamos realizando a devolução
                logOperation := true;
                operation    := 'Returned';
              ELSE
                -- Prossegue normalmente
            END CASE;
          WHEN 'ReturnedToSupplier' THEN
            -- Verifica se estamos devolvendo para o fornecedor
            CASE (OLD.storageLocation)
              WHEN 'StoredOnDeposit', 'UnderMaintenance' THEN
                -- Estamos realizando a devolução
                logOperation := true;
                operation    := 'Returned';
              ELSE
                -- Prossegue normalmente
            END CASE;
          ELSE
            -- Prossegue normalmente
        END CASE;
      ELSE
        IF (OLD.equipmentStateID <> NEW.equipmentStateID) THEN
          -- Estamos registrando um defeito
          logOperation := true;
          operation    := 'DefectDetected';
        END IF;
      END IF;

      IF (logOperation = true) THEN
        -- Registramos a operação do equipamento
        INSERT INTO erp.deviceOperationLogs (contractorID, deviceType,
          deviceID, operation, storageLocation, installedAt, slotNumber,
          equipmentStateID, technicianID, serviceProviderID, depositID,
          performedAt, performedByUserID) VALUES
          (OLD.contractorID, 'Equipment', OLD.equipmentID, operation,
           NEW.storageLocation, NEW.vehicleID, null,
           NEW.equipmentStateID, NEW.technicianID,
           NEW.serviceProviderID, NEW.depositID, NEW.updatedAt,
           NEW.updatedByUserID);
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Removemos todas as informações do histórico deste dispositivo
    DELETE FROM erp.deviceOperationLogs
     WHERE deviceType = 'Equipment'
       AND deviceID = OLD.equipmentID;

    -- Removemos todos os registros de instalação deste equipamento
    DELETE FROM erp.installationRecords
     WHERE contractorID = OLD.contractorID
       AND equipmentID = OLD.equipmentID;

    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Atualizamos a função que obtém os dados de equipamentos em um veículo
DROP FUNCTION erp.getEquipmentsPerVehicleData(FcontractorID integer,
  FvehicleID integer);
DROP TYPE erp.equipmentPerVehicleData;

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
  blockingSite           varchar(100),
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
                   EQPTO.blockingSite,
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
             ORDER BY EQPTO.main DESC, EQPTO.installedAt'
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
    equipmentPerVehicleData.blockingSite        := row.blockingSite;
    equipmentPerVehicleData.sirenSite           := row.sirenSite;
    equipmentPerVehicleData.panicButtonSite     := row.panicButtonSite;

    RETURN NEXT equipmentPerVehicleData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- Recria a função de obtenção dos dados de veículos
DROP FUNCTION erp.getVehiclesData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FvehicleID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Factive boolean,
  FOrder varchar, Fstatus integer, Ftype integer,
  Skip integer, LimitOf integer);
DROP TYPE erp.vehicleData;

CREATE TYPE erp.vehicleData AS
(
  customerID         integer,
  subsidiaryID       integer,
  juridicalperson    boolean,
  cooperative        boolean,
  headOffice         boolean,
  type               smallint,
  level              smallint,
  active             boolean,
  activeAssociation  boolean,
  name               varchar(100),
  tradingName        varchar(100),
  blocked            boolean,
  vehicleID          integer,
  vehicleTypeID      integer,
  vehicleTypeName    varchar(30),
  vehicleSubtypeID   integer,
  vehicleSubtypeName varchar(30),
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
  withoutMainTracker boolean,
  blockedLevel       smallint,
  createdAt          timestamp,
  fullcount          integer
);

CREATE OR REPLACE FUNCTION erp.getVehiclesData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FvehicleID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Factive boolean,
  FOrder varchar, Fstatus integer, Ftype integer,
  Skip integer, LimitOf integer)
RETURNS SETOF erp.vehicleData AS
$$
DECLARE
  vehicleData  erp.vehicleData%rowtype;
  row          record;
  query        varchar;
  field        varchar;
  filter       varchar;
  typeFilter  varchar;
  limits       varchar;
  blockedLevel integer;
  lastEntityID  integer;
  lastSubsidiaryID  integer;
  lastEntityPayerID  integer;
  lastSubsidiaryPayerID  integer;
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
    FOrder = 'cooperative ASC, itemName, itemOrder, customerName, headOffice DESC, subsidiaryName, plate';
  END IF;
  IF (Fstatus IS NULL) THEN
    Fstatus := 0;
  END IF;
  IF (Ftype IS NULL) THEN
    Ftype := 0;
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';

  -- Os estados possíveis são: (1) inativo e (2) ativo
  typeFilter := '';
  IF (Fstatus > 0) THEN
    IF (Fstatus = 1) THEN
      typeFilter := ' AND active = FALSE';
    ELSE
      typeFilter := ' AND active = TRUE';
    END IF;
  END IF;
  
  IF (FvehicleID > 0) THEN
    -- Realiza a filtragem por veículo
    typeFilter := typeFilter || format(' AND vehicleID = %s', FvehicleID);
  ELSE
    IF (FcustomerID > 0) THEN
      -- Realiza a filtragem por cliente
      typeFilter := typeFilter || format(' AND ( (itemID = %s) OR (customerID = %s))', FcustomerID, FcustomerID);
      IF (FsubsidiaryID > 0) THEN
        typeFilter := typeFilter ||
          format(' AND ((itemUnityID = %s) OR (subsidiaryID = %s))', FsubsidiaryID, FsubsidiaryID);
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
          field := 'vehicleBrand.name';
        WHEN 'vehicleModelName' THEN
          field := 'vehicleModel.name';
        ELSE
          field := 'vehicle.' || FsearchField;
      END CASE;

      -- Monta o filtro
      filter := format(' AND public.unaccented(%s) ILIKE public.unaccented(''%%%s%%'')',
                       field, FsearchValue);
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  IF (Factive IS NOT NULL) THEN
    IF (Factive = TRUE) THEN
      -- Adiciona a opção de filtragem de veículos ativos
      filter := filter || ' AND vehicle.blocked = false';
    ELSE
      -- Adiciona a opção de filtragem de veículos inativos
      filter := filter || ' AND vehicle.blocked = true';
    END IF;
  END IF;

  -- Monta a consulta
  query := format('WITH items AS (
                    SELECT CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN equipment.customerPayerID
                             ELSE vehicle.customerID
                           END AS itemID,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN customerPayer.blocked
                             ELSE customer.blocked
                           END AS itemBlocked,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN customerPayer.name
                             ELSE customer.name
                           END AS itemName,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN customerPayer.tradingName
                             ELSE customer.tradingName
                           END AS itemTradingName,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.subsidiaryID <> equipment.subsidiaryID
                             THEN unityPayer.subsidiaryID
                             ELSE unity.subsidiaryID
                           END AS itemUnityID,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.subsidiaryID <> equipment.subsidiaryID
                             THEN unityPayer.name
                             ELSE unity.name
                           END AS itemUnity,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.subsidiaryID <> equipment.subsidiaryID
                             THEN unityPayer.headOffice
                             ELSE unity.headOffice
                           END AS itemUnityHeadOffice,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.subsidiaryID <> equipment.subsidiaryID
                             THEN unityPayer.blocked
                             ELSE unity.blocked
                           END AS itemUnityBlocked,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN customerPayerType.cooperative
                             ELSE customerType.cooperative
                           END AS cooperative,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN customerPayerType.juridicalperson
                             ELSE customerType.juridicalperson
                           END AS juridicalpersonOfItem,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID = equipment.customerPayerID
                             THEN 1
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN 3
                             ELSE 2
                           END AS itemOrder,
                           vehicle.customerID,
                           customer.name AS customerName,
                           customer.tradingName,
                           customer.blocked AS customerBlocked,
                           customerType.juridicalperson,
                           unity.name AS subsidiaryName,
                           unity.blocked AS subsidiaryBlocked,
                           unity.headOffice,
                           vehicle.subsidiaryID,
                           unity.blocked AS subsidiaryBlocked,
                           vehicle.vehicleID AS id,
                           vehicle.plate,
                           vehicle.vehicleTypeID,
                           type.name AS vehicleTypeName,
                           CASE
                             WHEN model.vehicleSubtypeID IS NULL THEN 0
                             ELSE model.vehicleSubtypeID
                           END AS vehicleSubtypeID,
                           CASE
                             WHEN model.vehicleSubtypeID IS NULL THEN ''Não informado''
                             ELSE subtype.name
                           END AS vehicleSubtypeName,
                           vehicle.vehicleBrandID,
                           brand.name AS vehicleBrandName,
                           vehicle.vehicleModelID,
                           model.name AS vehicleModelName,
                           vehicle.vehicleColorID,
                           color.name AS vehicleColorName,
                           color.color AS vehicleColor,
                           vehicle.carNumber,
                           vehicle.fuelType,
                           fuel.name AS fuelTypeName,
                           vehicle.blocked AS vehicleBlocked,
                           vehicle.createdAt,
                           (SELECT count(*) FROM erp.equipments AS E WHERE E.vehicleID = vehicle.vehicleID) AS amountOfEquipmentsOnVehicle,
                           equipment.customerPayerID
                      FROM erp.vehicles AS vehicle
                     INNER JOIN erp.vehicleTypes AS type ON (vehicle.vehicleTypeID = type.vehicleTypeID)
                     INNER JOIN erp.vehicleBrands AS brand ON (vehicle.vehicleBrandID = brand.vehicleBrandID)
                     INNER JOIN erp.vehicleModels AS model ON (vehicle.vehicleModelID = model.vehicleModelID)
                      LEFT JOIN erp.vehicleSubtypes AS subtype ON (model.vehicleSubtypeID = subtype.vehicleSubtypeID)
                     INNER JOIN erp.vehicleColors AS color USING (vehicleColorID)
                     INNER JOIN erp.fuelTypes AS fuel USING (fuelType)
                     INNER JOIN erp.entities AS customer ON (vehicle.customerID = customer.entityID)
                     INNER JOIN erp.subsidiaries AS unity ON (vehicle.subsidiaryID = unity.subsidiaryID)
                     INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
                      LEFT JOIN erp.equipments AS equipment ON (vehicle.vehicleID = equipment.vehicleID AND equipment.main = true)
                      LEFT JOIN erp.entities AS customerPayer ON (equipment.customerPayerID = customerPayer.entityID)
                      LEFT JOIN erp.entitiesTypes AS customerPayerType ON (customerPayer.entityTypeID = customerPayerType.entityTypeID)
                      LEFT JOIN erp.subsidiaries AS unityPayer ON (equipment.subsidiaryPayerID = unityPayer.subsidiaryID)
                     WHERE vehicle.contractorID = %s
                       AND vehicle.deleted = false %s
                  ) SELECT *,
                           (SELECT count(*) FROM erp.subsidiaries AS S WHERE S.entityID = itemID) AS unityItems,
                           (SELECT count(*) FROM erp.subsidiaries AS S WHERE S.entityID = customerID) AS unityCustomerItems,
                           CASE
                             WHEN customerPayerID IS NOT NULL THEN true
                             WHEN customerPayerID IS NULL AND amountOfEquipmentsOnVehicle > 0 THEN true
                             ELSE false
                           END AS active,
                           CASE
                             WHEN customerPayerID IS NOT NULL THEN false
                             WHEN customerPayerID IS NULL AND amountOfEquipmentsOnVehicle > 0 THEN true
                             ELSE false
                           END AS withoutMainTracker,
                           count(*) OVER() AS fullcount
                      FROM items
                     WHERE (1=1) %s
                     ORDER BY %s %s;',
                  fContractorID, filter, typeFilter, FOrder, limits);
  -- RAISE NOTICE 'SQL: %',query;

  lastEntityPayerID := 0;
  lastSubsidiaryPayerID := 0;
  lastEntityID := 0;
  lastSubsidiaryID := 0;

  FOR row IN EXECUTE query
  LOOP
    -- RAISE NOTICE 'lastEntityPayerID = %', lastEntityPayerID;
    -- RAISE NOTICE 'itemID = %', row.itemID;
    IF (lastEntityPayerID <> row.itemID) THEN
      -- Iniciamos um novo grupo
      lastEntityPayerID := row.itemID;
      lastEntityID := row.itemID;

      -- Verifica se precisamos dividir esta entidade em mais de uma
      -- linha
      -- RAISE NOTICE 'unityItems = %', row.unityItems;
      IF (row.unityItems > 1) THEN
        -- Descrevemos aqui a entidade principal
        vehicleData.customerID         := row.itemID;
        vehicleData.subsidiaryID       := 0;
        vehicleData.juridicalperson    := row.juridicalpersonOfItem;
        vehicleData.cooperative        := row.cooperative;
        vehicleData.headOffice         := false;
        vehicleData.type               := 1;
        vehicleData.level              := 0;
        vehicleData.active             := NOT row.itemBlocked;
        vehicleData.activeAssociation  := NOT row.itemBlocked;
        vehicleData.name               := row.itemName;
        vehicleData.tradingName        := row.itemTradingName;
        vehicleData.blocked            := row.itemBlocked;
        vehicleData.vehicleID          := NULL;
        vehicleData.vehicleTypeID      := NULL;
        vehicleData.vehicleTypeName    := NULL;
        vehicleData.vehicleSubtypeID   := NULL;
        vehicleData.vehicleSubtypeName := NULL;
        vehicleData.vehicleBrandID     := NULL;
        vehicleData.vehicleBrandName   := NULL;
        vehicleData.vehicleModelID     := NULL;
        vehicleData.vehicleModelName   := NULL;
        vehicleData.vehicleColorID     := NULL;
        vehicleData.vehicleColorName   := NULL;
        vehicleData.vehicleColor       := NULL;
        vehicleData.carNumber          := NULL;
        vehicleData.fuelType           := NULL;
        vehicleData.fuelTypeName       := NULL;

        IF (row.itemBlocked) THEN
          vehicleData.blockedLevel     := 1;
        ELSE
          vehicleData.blockedLevel     := 0;
        END IF;
        vehicleData.withoutMainTracker := false;
        vehicleData.createdAt          := row.createdAt;
        vehicleData.fullcount          := row.fullcount;

        RETURN NEXT vehicleData;
      END IF;
    END IF;

    -- RAISE NOTICE 'lastSubsidiaryPayerID = %', lastSubsidiaryPayerID;
    -- RAISE NOTICE 'itemUnityID = %', row.itemUnityID;
    IF (lastSubsidiaryPayerID <> row.itemUnityID) THEN
      -- Iniciamos um novo subgrupo
      lastSubsidiaryPayerID := row.itemUnityID;
      lastSubsidiaryID := row.itemUnityID;

      -- Informa os dados da unidade (ou do cliente se houver uma
      -- unidade apenas)
      vehicleData.customerID         := row.itemID;
      vehicleData.subsidiaryID       := row.itemUnityID;
      vehicleData.juridicalperson    := row.juridicalpersonOfItem;
      vehicleData.cooperative        := row.cooperative;
      vehicleData.headOffice         := row.itemUnityHeadOffice;
      vehicleData.type               := 1;
      IF (row.unityItems > 1) THEN
        vehicleData.level            := 1;
      ELSE
        vehicleData.level            := 2;
      END IF;
      vehicleData.active             := NOT(row.itemBlocked AND row.itemUnityBlocked);
      vehicleData.activeAssociation  := NOT row.itemBlocked;
      IF (row.unityItems > 1) THEN
        vehicleData.name             := row.itemUnity;
        vehicleData.tradingName      := '';
        vehicleData.blocked          := row.itemUnityBlocked;
      ELSE
        vehicleData.name             := row.itemName;
        vehicleData.tradingName      := row.itemTradingName;
        vehicleData.blocked          := row.itemBlocked;
      END IF;
      vehicleData.vehicleID          := NULL;
      vehicleData.vehicleTypeID      := NULL;
      vehicleData.vehicleTypeName    := NULL;
      vehicleData.vehicleSubtypeID   := NULL;
      vehicleData.vehicleSubtypeName := NULL;
      vehicleData.vehicleBrandID     := NULL;
      vehicleData.vehicleBrandName   := NULL;
      vehicleData.vehicleModelID     := NULL;
      vehicleData.vehicleModelName   := NULL;
      vehicleData.vehicleColorID     := NULL;
      vehicleData.vehicleColorName   := NULL;
      vehicleData.vehicleColor       := NULL;
      vehicleData.carNumber          := NULL;
      vehicleData.fuelType           := NULL;
      vehicleData.fuelTypeName       := NULL;

      IF (row.itemBlocked) THEN
        vehicleData.blockedLevel     := 1;
      ELSE
        IF (row.itemUnityBlocked) THEN
          vehicleData.blockedLevel   := 2;
        ELSE
          vehicleData.blockedLevel   := 0;
        END IF;
      END IF;
      vehicleData.withoutMainTracker := false;
      vehicleData.createdAt          := row.createdAt;
      vehicleData.fullcount          := row.fullcount;

      RETURN NEXT vehicleData;
    END IF;

    IF (lastEntityID <> row.customerID) THEN
      -- Iniciamos um novo grupo
      lastEntityID := row.customerID;

      -- Verifica se precisamos dividir esta entidade em mais de uma
      -- linha
      IF (row.unityCustomerItems > 1) THEN
        -- Descrevemos aqui a entidade secundária
        vehicleData.customerID           := row.customerID;
        vehicleData.subsidiaryID         := 0;
        vehicleData.juridicalperson      := row.juridicalperson;
        vehicleData.cooperative          := false;
        vehicleData.headOffice           := false;
        vehicleData.type                 := 2;
        vehicleData.level                := 3;
        vehicleData.active               := NOT row.customerBlocked;
        vehicleData.activeAssociation    := NOT row.itemBlocked;
        vehicleData.name                 := row.customerName;
        vehicleData.tradingName          := row.tradingName;
        vehicleData.blocked              := row.customerBlocked;
        vehicleData.vehicleID            := NULL;
        vehicleData.vehicleTypeID        := NULL;
        vehicleData.vehicleTypeName      := NULL;
        vehicleData.vehicleSubtypeID     := NULL;
        vehicleData.vehicleSubtypeName   := NULL;
        vehicleData.vehicleBrandID       := NULL;
        vehicleData.vehicleBrandName     := NULL;
        vehicleData.vehicleModelID       := NULL;
        vehicleData.vehicleModelName     := NULL;
        vehicleData.vehicleColorID       := NULL;
        vehicleData.vehicleColorName     := NULL;
        vehicleData.vehicleColor         := NULL;
        vehicleData.carNumber            := NULL;
        vehicleData.fuelType             := NULL;
        vehicleData.fuelTypeName         := NULL;

        IF (row.itemID = row.customerID) THEN
          IF (row.itemBlocked) THEN
            vehicleData.blockedLevel     := 1;
          ELSE
            IF (row.itemUnityBlocked) THEN
              vehicleData.blockedLevel   := 2;
            ELSE
              vehicleData.blockedLevel   := 0;
            END IF;
          END IF;
        ELSE
          IF (row.itemBlocked) THEN
            vehicleData.blockedLevel     := 1;
          ELSE
            IF (row.itemUnityBlocked) THEN
              vehicleData.blockedLevel   := 2;
            ELSE
              IF (row.customerBlocked) THEN
                vehicleData.blockedLevel := 3;
              ELSE
                vehicleData.blockedLevel := 0;
              END IF;
            END IF;
          END IF;
        END IF;

        vehicleData.withoutMainTracker   := false;
        vehicleData.createdAt            := row.createdAt;
        vehicleData.fullcount            := row.fullcount;

        RETURN NEXT vehicleData;
      END IF;
    END IF;

    IF (lastSubsidiaryID <> row.subsidiaryID) THEN
      -- Iniciamos um novo subgrupo
      lastSubsidiaryID := row.subsidiaryID;

      -- Informa os dados da unidade (ou do cliente se houver uma
      -- unidade apenas) da entidade secundária
      vehicleData.customerID             := row.customerID;
      vehicleData.subsidiaryID           := row.subsidiaryID;
      vehicleData.juridicalperson        := row.juridicalperson;
      vehicleData.cooperative            := false;
      vehicleData.headOffice             := row.headOffice;
      vehicleData.type                   := 2;
      IF (row.unityCustomerItems > 1) THEN
        vehicleData.level                := 4;
      ELSE
        vehicleData.level                := 5;
      END IF;
      vehicleData.active                 := NOT(row.customerBlocked AND row.subsidiaryBlocked);
      vehicleData.activeAssociation      := NOT row.itemBlocked;
      IF (row.unityCustomerItems > 1) THEN
        vehicleData.name                   := row.subsidiaryName;
        vehicleData.tradingName            := '';
        vehicleData.blocked                := row.subsidiaryBlocked;
      ELSE
        vehicleData.name                   := row.customerName;
        vehicleData.tradingName            := row.tradingName;
        vehicleData.blocked                := row.customerBlocked;
      END IF;
      vehicleData.vehicleID              := NULL;
      vehicleData.vehicleTypeID          := NULL;
      vehicleData.vehicleTypeName        := NULL;
      vehicleData.vehicleSubtypeID       := NULL;
      vehicleData.vehicleSubtypeName     := NULL;
      vehicleData.vehicleBrandID         := NULL;
      vehicleData.vehicleBrandName       := NULL;
      vehicleData.vehicleModelID         := NULL;
      vehicleData.vehicleModelName       := NULL;
      vehicleData.vehicleColorID         := NULL;
      vehicleData.vehicleColorName       := NULL;
      vehicleData.vehicleColor           := NULL;
      vehicleData.carNumber              := NULL;
      vehicleData.fuelType               := NULL;
      vehicleData.fuelTypeName           := NULL;

      IF ( (row.itemID = row.customerID) OR
           (row.itemUnityID = row.subsidiaryID) ) THEN
        IF (row.itemBlocked) THEN
          vehicleData.blockedLevel       := 1;
        ELSE
          IF (row.itemUnityBlocked) THEN
            vehicleData.blockedLevel     := 2;
          ELSE
            vehicleData.blockedLevel     := 0;
          END IF;
        END IF;
      ELSE
        IF (row.itemBlocked) THEN
          vehicleData.blockedLevel       := 1;
        ELSE
          IF (row.itemUnityBlocked) THEN
            vehicleData.blockedLevel     := 2;
          ELSE
            IF (row.customerBlocked) THEN
              vehicleData.blockedLevel   := 3;
            ELSE
              IF (row.subsidiaryBlocked) THEN
                vehicleData.blockedLevel := 4;
              ELSE
                vehicleData.blockedLevel := 0;
              END IF;
            END IF;
          END IF;
        END IF;
      END IF;

      vehicleData.withoutMainTracker     := false;
      vehicleData.createdAt              := row.createdAt;
      vehicleData.fullcount              := row.fullcount;

      RETURN NEXT vehicleData;
    END IF;

    -- Informa os dados do veículo
    vehicleData.customerID               := row.customerID;
    vehicleData.subsidiaryID             := row.subsidiaryID;
    vehicleData.juridicalperson          := row.juridicalperson;
    vehicleData.cooperative              := false;
    vehicleData.headOffice               := row.headOffice;
    vehicleData.type                     := 3;
    vehicleData.level                    := 6;
    vehicleData.active                   := row.active;
    vehicleData.activeAssociation        := NOT row.itemBlocked;
    vehicleData.name                     := row.plate;
    vehicleData.tradingName              := NULL;
    vehicleData.blocked                  := row.vehicleBlocked;
    vehicleData.vehicleID                := row.id;
    vehicleData.vehicleTypeID            := row.vehicleTypeID;
    vehicleData.vehicleTypeName          := row.vehicleTypeName;
    vehicleData.vehicleSubtypeID         := row.vehicleSubtypeID;
    vehicleData.vehicleSubtypeName       := row.vehicleSubtypeName;
    vehicleData.vehicleBrandID           := row.vehicleBrandID;
    vehicleData.vehicleBrandName         := row.vehicleBrandName;
    vehicleData.vehicleModelID           := row.vehicleModelID;
    vehicleData.vehicleModelName         := row.vehicleModelName;
    vehicleData.vehicleColorID           := row.vehicleColorID;
    vehicleData.vehicleColorName         := row.vehicleColorName;
    vehicleData.vehicleColor             := row.vehicleColor;
    vehicleData.carNumber                := row.carNumber;
    vehicleData.fuelType                 := row.fuelType;
    vehicleData.fuelTypeName             := row.fuelTypeName;

    -- Determina o nível de bloqueio do veículo
    IF ( (row.itemID = row.customerID) OR
         (row.itemUnityID = row.subsidiaryID) ) THEN
      IF (row.itemBlocked) THEN
        vehicleData.blockedLevel         := 1;
      ELSE
        IF (row.itemUnityBlocked) THEN
          vehicleData.blockedLevel       := 2;
        ELSE
          IF (row.vehicleBlocked) THEN
            vehicleData.blockedLevel     := 5;
          ELSE
            vehicleData.blockedLevel     := 0;
          END IF;
        END IF;
      END IF;
    ELSE
      IF (row.itemBlocked) THEN
        vehicleData.blockedLevel         := 1;
      ELSE
        IF (row.itemUnityBlocked) THEN
          vehicleData.blockedLevel       := 2;
        ELSE
          IF (row.customerBlocked) THEN
            vehicleData.blockedLevel     := 3;
          ELSE
            IF (row.subsidiaryBlocked) THEN
              vehicleData.blockedLevel   := 4;
            ELSE
              IF (row.vehicleBlocked) THEN
                vehicleData.blockedLevel := 5;
              ELSE
                vehicleData.blockedLevel := 0;
              END IF;
            END IF;
          END IF;
        END IF;
      END IF;
    END IF;

    vehicleData.withoutMainTracker       := row.withoutMainTracker;
    vehicleData.createdAt                := row.createdAt;
    vehicleData.fullcount                := row.fullcount;

    RETURN NEXT vehicleData;
  END loop;
END
$$
LANGUAGE 'plpgsql';
