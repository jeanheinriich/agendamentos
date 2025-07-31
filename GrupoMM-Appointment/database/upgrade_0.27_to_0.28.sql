-- =====================================================================
-- MODIFICAÇÃO DO VÍNCULO DE ITEM DE CONTRATO COM VEÍCULO
-- =====================================================================
-- Esta modificação visa incluir toda a lógica para permitir identificar
-- quando um veículo está cadastrado num item de contrato
-- ---------------------------------------------------------------------

-- Precisamos retirar a obrigatoriedade de campos da tabela que
-- determina o relacionamento do veículo com o item de contrato
ALTER TABLE erp.installationRecords
  ALTER COLUMN equipmentID
    DROP NOT NULL;
ALTER TABLE erp.installationRecords
  ALTER COLUMN installedAt
    DROP NOT NULL;

-- Precisamos modificar o gatilho que lida com as transações de
-- equipamentos

-- ---------------------------------------------------------------------
-- Transações em equipamentos
-- ---------------------------------------------------------------------
-- Lida com as transações ocorridas em equipamentos de rastreamento para
-- lidar com as informações armazenadas no contrato.
-- ---------------------------------------------------------------------
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
        -- RAISE NOTICE 'Pode ter sido atualizada a informação de instalação';
        -- Pode ter sido atualizada a informação de instalação
        IF ( (OLD.installedAt <> NEW.installedAt) AND
             (OLD.installationID <> NEW.installationID) ) THEN
          -- Modificamos a data de instalação e em qual instalação ocorreu
          -- no registro de instalação deste equipamento
          -- RAISE NOTICE 'Modificamos a data de instalação e em qual instalação ocorreu';
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
        -- devidamente atualizada se o cliente for uma associação
        -- RAISE NOTICE 'Precisamos garantir que a informação de associado seja devidamente atualizada';
        SELECT INTO cooperative
               type.cooperative
          FROM erp.entities AS customer
         INNER JOIN erp.entitiesTypes AS type USING (entityTypeID)
         WHERE customer.entityID = NEW.customerPayerID;
        IF (cooperative) THEN
          -- Garantimos que o cliente seja corretamente indicado como
          -- associado desta associação
          -- RAISE NOTICE 'É COOPERATIVA';
          SELECT INTO joint
                 affiliationID,
                 joinedAt,
                 unjoinedAt
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
           FETCH FIRST ROW ONLY;
          IF FOUND THEN
            -- O cliente já está vinculado à associação, então
            -- atualizamos o registro, se necessário
            -- RAISE NOTICE 'O cliente já está vinculado à associação';
            IF (joint.joinedAt > NEW.installedAt) THEN
              -- Atualizamos a data à partir da qual o cliente se tornou
              -- associado desta cooperativa
              -- RAISE NOTICE 'Atualizamos a data à partir da qual o cliente se tornou associado desta cooperativa';
              UPDATE erp.affiliations
                 SET joinedAt = NEW.installedAt,
                     unjoinedAt = NULL
               WHERE affiliationID = joint.affiliationID;
            ELSE
              IF (joint.unjoinedAt IS NOT NULL) THEN
                -- Retiramos o término do vínculo, já que ele retornou
                -- RAISE NOTICE 'Atualizamos apenas que o cliente retomou a associação com esta cooperativa';
                UPDATE erp.affiliations
                   SET unjoinedAt = NULL
                 WHERE affiliationID = joint.affiliationID;
              END IF;
            END IF;
          ELSE
            -- O cliente não está vinculado à associação, então criamos
            -- um novo registro indicando esta afiliação
            -- RAISE NOTICE 'O cliente não está vinculado à associação, então criamos um novo registro indicando esta afiliação';
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

      IF (OLD.customerPayerID IS NOT NULL) THEN
        -- Verificamos se o pagante anterior era uma associação
        SELECT INTO cooperative
               type.cooperative
          FROM erp.entities AS customer
         INNER JOIN erp.entitiesTypes AS type USING (entityTypeID)
         WHERE customer.entityID = OLD.customerPayerID;

        IF (cooperative) THEN
          -- Precisamos garantir que a informação de associado seja
          -- devidamente atualizada
          -- RAISE NOTICE 'O pagante anterior era uma associação';
          IF (
               (
                 (NEW.storageLocation = 'Installed') AND
                 (NEW.customerPayerID <> OLD.customerPayerID)
               ) OR
               (
                 (NEW.storageLocation <> 'Installed')
               )
             ) THEN
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
            -- RAISE NOTICE 'Restam % afiliados', remainingJoints;
            IF (remainingJoints = 0) THEN
              -- Localizamos o registro de indicação de que o cliente é
              -- associado e atualizamos que o mesmo deixou de ser
              -- RAISE NOTICE 'O cliente deixou de ser associado desta cooperativa';
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
                -- RAISE NOTICE 'Retiramos o vínculo com esta cooperativa';
                UPDATE erp.affiliations
                   SET unjoinedAt = CURRENT_DATE
                 WHERE affiliationID = joint.affiliationID;
              END IF;
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

CREATE TRIGGER equipmentTransactionTriggerBefore
  BEFORE INSERT OR UPDATE OR DELETE ON erp.equipments
  FOR EACH ROW EXECUTE PROCEDURE erp.equipmentTransaction();
CREATE TRIGGER equipmentTransactionTriggerAfter
  AFTER INSERT OR UPDATE OR DELETE ON erp.equipments
  FOR EACH ROW EXECUTE PROCEDURE erp.equipmentTransaction();
