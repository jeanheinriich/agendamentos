-- =====================================================================
-- Instalações
-- =====================================================================
-- Cada contrato irá gerir um número variável de instalações. Cada
-- instalação permite controlar o serviço sendo prestado para o cliente,
-- armazenando a informação do equipamento instalado, bem como determinar
-- efetivamente os valores a serem cobrados mensalmente.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Gerador do número de instalação
-- ---------------------------------------------------------------------
-- Gera um número de instalação único baseado na ID da instalação, bem
-- como na ID do contrato e na ID do contratante.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.generateInstallationNumber(contractorID INT, contractID INT, installationID INT)
  RETURNS char(12) AS $$
DECLARE
  InstallationNumber char(12);
  CRC bigint;
  DV  char;
BEGIN
  CRC := public.crc32(
    LPAD(contractorID::varchar, 10, '0') ||
    LPAD(contractID::varchar, 10, '0') ||
    LPAD(installationID::varchar, 10, '0')
  );
  DV  := erp.checkSumMod11(LPAD(CRC::varchar, 10, '0'), 9, '1', '0');
  InstallationNumber := LPAD(CRC::varchar, 10, '0') || '-' || DV;

  RETURN InstallationNumber;
END;
$$ LANGUAGE PLPGSQL;

-- ---------------------------------------------------------------------
-- Instalações
-- ---------------------------------------------------------------------
-- As instalações de cada cliente. Uma instalação controla o serviço
-- contratado em um determinado veículo, controlando os períodos em que
-- este foi prestado, bem como permitindo a troca simplificada tanto do
-- equipamento de rastreamento quanto do veículo sem prejuízo dos
-- cálculos do que será cobrado.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.installations (
  installationID              serial,         -- ID da instalação
  installationNumber          char(12),       -- Número da instalação
  contractorID                integer         -- ID do contratante
                              NOT NULL,
  customerID                  integer         -- ID do cliente
                              NOT NULL,
  subsidiaryID                integer         -- ID da unidade/filial deste cliente
                              NOT NULL,
  contractID                  integer         -- ID do contrato que rege
                              NOT NULL,       -- esta instalação
  planID                      integer         -- ID do plano que rege
                              NOT NULL,       -- esta instalação
  subscriptionPlanID          integer         -- ID da assinatura escolhida
                              NOT NULL,
  startDate                   date            -- A data de início da cobrança
                              DEFAULT NULL,   -- (a data de instalação)
  endDate                     date            -- A data de término da cobrança
                              DEFAULT NULL,
  monthPrice                  numeric(12,2),  -- Valor da mensalidade
  effectivePriceDate          date            -- A data de início da
                              NOT NULL        -- vigência deste valor
                              DEFAULT CURRENT_DATE,
  dateOfNextReadjustment      date            -- Data do próximo reajuste
                              DEFAULT NULL,   -- setada após a instalação
  lastDayOfCalculatedPeriod   date            -- O último dia do período
                              DEFAULT NULL,   -- de apuração dos valores
  lastDayOfBillingPeriod      date            -- O último dia do período
                              DEFAULT NULL,   -- já cobrado
  notChargeLoyaltyBreak       boolean         -- O indicativo de que não
                              NOT NULL        -- devemos cobrar multa por
                              DEFAULT false,  -- quebra de fidelidade
  createdAt                   timestamp       -- A data de criação desta instalação
                              NOT NULL
                              DEFAULT CURRENT_TIMESTAMP,
  createdByUserID             integer         -- O ID do usuário responsável pelo
                              NOT NULL,       -- cadastro
  updatedAt                   timestamp       -- A data da última modificação
                              NOT NULL
                              DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID             integer         -- O ID do usuário responsável pela
                              NOT NULL,       -- última modificação
  deleted                     boolean         -- O indicativo de instalação removida
                              NOT NULL
                              DEFAULT false,
  deletedAt                   timestamp       -- A data de remoção
                              DEFAULT NULL,
  deletedByUserID             integer         -- O ID do usuário responsável pela
                              DEFAULT NULL,   -- remoção
  PRIMARY KEY (installationID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractID)
    REFERENCES erp.contracts(contractID)
    ON DELETE RESTRICT,
  FOREIGN KEY (planID)
    REFERENCES erp.plans(planID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subscriptionPlanID)
    REFERENCES erp.subscriptionPlans(subscriptionPlanID)
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

-- Adicionamos a chave extrangeira para as instalações na tabela de
-- equipamentos
ALTER TABLE erp.equipments
  ADD CONSTRAINT equipments_installationid_fkey
      FOREIGN KEY (installationID)
        REFERENCES erp.installations(installationID)
        ON DELETE RESTRICT;

-- ---------------------------------------------------------------------
-- Histórico de reajustes das instalações
-- ---------------------------------------------------------------------
-- Contém os registros dos reajustes realizados em cada instalação, já
-- que a data de instalação do equipamento determina quando se deu o
-- início do contrato naquele registro de instalação
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.readjustmentsOnInstallations (
  readjustmentID      serial,         -- ID do reajuste
  contractID          integer         -- ID do contrato
                      NOT NULL,
  installationID      integer         -- ID da instalação
                      NOT NULL,
  monthPrice          numeric(12,2),  -- Valor da mensalidade
  readjustedAt        date            -- A data do reajuste
                      NOT NULL
                      DEFAULT CURRENT_DATE,
  readjustedByUserID  integer         -- O ID do usuário responsável
                      NOT NULL,       -- pelo reajuste
  PRIMARY KEY (readjustmentID),
  FOREIGN KEY (contractID)
    REFERENCES erp.contracts(contractID)
    ON DELETE CASCADE,
  FOREIGN KEY (installationID)
    REFERENCES erp.installations(installationID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Transações no histórico de reajustes das instalações
-- ---------------------------------------------------------------------
-- Gatilho para lidar com as operações nas instalações
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.installationTransaction()
RETURNS trigger AS $$
DECLARE
  installationNumber char(12);
  readjustmentperiod smallint;
  startTermAfterInstallation boolean;
  signatureDate date;
  startOfReadjustmentPeriod date;
  interaction smallint;
BEGIN
  -- Lida com os reajustes das mensalidades e o número da instalação.
  -- Faz uso da variável especial TG_OP para verificar a operação
  -- executada e de TG_WHEN para determinar o instante em que isto
  -- ocorre
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      IF (NEW.startDate IS NOT NULL) THEN
        -- Recuperamos a partir de quando deve ser considerado o período
        -- de reajuste
        SELECT INTO startTermAfterInstallation, signatureDate
               C.startTermAfterInstallation,
               C.signatureDate
          FROM erp.contracts AS C
         WHERE C.contractID = NEW.contractID;

        IF (startTermAfterInstallation) THEN
          -- A instalação do equipamento ocorreu, então NEW.startDate
          -- contém a data em que isto ocorreu, determinando o início da
          -- vigência desta instalação
          startOfReadjustmentPeriod := NEW.startDate;
        ELSE
          -- A instalação do equipamento ocorreu, mas a vigência da
          -- instalação segue a assinatura do contrato, então a data em
          -- que o contrato foi assinado é utilizado para determinar a
          -- data do próximo reajuste
          startOfReadjustmentPeriod := signatureDate;
        END IF;
        
        -- Recuperamos o período de reajuste do contrato
        SELECT INTO readjustmentPeriod
               P.readjustmentPeriod
          FROM erp.plans AS P
         WHERE P.planID = NEW.planID
           AND P.contractorID = NEW.contractorID;

        IF (readjustmentPeriod <= 0) THEN
          -- Disparamos uma exceção
          RAISE EXCEPTION 'Não foi possível determinar a duração do plano ID % informado.', NEW.planID
          USING HINT = 'O período de reajuste informado no plano é inválido.';
        END IF;

        IF ((NEW.dateOfNextReadjustment IS NULL) OR
            (NEW.dateOfNextReadjustment < CURRENT_DATE)) THEN
          -- Não foi informada a data do próximo reajuste, ou esta é
          -- inferior que a data atual então determina em função da data
          -- de início da instalação, acrescido do período (em meses) do
          -- tempo para reajuste.

          -- Força que a data de início da vigência da mensalidade seja
          -- a data a ser considerada como o início do período de
          -- reajuste
          NEW.effectivePriceDate := startOfReadjustmentPeriod;
          interaction := 0;

          LOOP
            IF (interaction > 0) THEN
              -- Fazemos com que a data de início de vigência desta
              -- mensalidade seja a data do último reajuste calculado
              NEW.effectivePriceDate := NEW.dateOfNextReadjustment;
            END IF;

            -- Calculamos a data do próximo reajuste
            NEW.dateOfNextReadjustment := (NEW.effectivePriceDate
              + interval '1 month' * readjustmentPeriod)::DATE;

            interaction := interaction + 1;

            -- Repetimos este processo até determinar uma data que seja
            -- posterior ao dia atual
            EXIT WHEN NEW.dateOfNextReadjustment > CURRENT_DATE;
          END LOOP;
        END IF;

        IF (NEW.effectivePriceDate < NEW.startDate) THEN
          -- Força que a data de início da vigência da mensalidade seja
          -- a data da instalação
          NEW.effectivePriceDate := NEW.startDate;
        END IF;

        IF (NEW.effectivePriceDate > (CURRENT_DATE + interval '1 month')::Date) THEN
          -- Disparamos uma exceção quando se tenta definir uma date de
          -- início da vigência superior a 1 mês em relação à data atual
          RAISE EXCEPTION 'A data % informada para início da vigência do valor da mensalidade é inválida.', NEW.effectivePriceDate
          USING HINT = 'A data de início da vigência do valor da mensalidade não pode ser uma data futura.';
        END IF;
      ELSE
        -- Sempre força para que a data do próximo reajuste seja nula,
        -- já que a instalação ainda não ocorreu
        NEW.dateOfNextReadjustment := NULL;
      END IF;
    ELSIF (TG_WHEN = 'AFTER') THEN
      -- Determina o número da instalação
      SELECT INTO installationNumber
        FROM erp.generateInstallationNumber(NEW.contractorID, NEW.contractID, NEW.installationID);
      
      -- Associamos ao novo registro
      NEW.installationNumber := installationNumber;

      IF (NEW.startDate IS NOT NULL) THEN
        -- Inserimos a tarifa inicial no histórico de reajustes como
        -- sendo a data da instalação
        INSERT INTO erp.readjustmentsOnInstallations (contractID,
          installationID, monthPrice, readjustedAt,
          readjustedByUserID) VALUES
          (NEW.contractID, NEW.installationID, NEW.monthPrice,
           NEW.effectivePriceDate, NEW.createdByUserID);
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica a instalação foi realizada
      IF ((OLD.startDate IS NULL) AND (NEW.startDate IS NOT NULL)) THEN
        -- Recuperamos a partir de quando deve ser considerado o período
        -- de reajuste
        SELECT INTO startTermAfterInstallation, signatureDate
               C.startTermAfterInstallation,
               C.signatureDate
          FROM erp.contracts AS C
         WHERE C.contractID = NEW.contractID;

        IF (startTermAfterInstallation) THEN
          -- A instalação do equipamento ocorreu, então NEW.startDate
          -- contém a data em que isto ocorreu, determinando o início da
          -- vigência desta instalação
          startOfReadjustmentPeriod := NEW.startDate;
        ELSE
          -- A instalação do equipamento ocorreu, mas a vigência da
          -- instalação segue a assinatura do contrato, então a data em
          -- que o contrato foi assinado é utilizado para determinar a
          -- data do próximo reajuste
          startOfReadjustmentPeriod := signatureDate;
        END IF;

        -- Recuperamos o período de reajuste do contrato
        SELECT INTO readjustmentPeriod
               P.readjustmentPeriod
          FROM erp.plans AS P
         WHERE P.planID = NEW.planID
           AND P.contractorID = NEW.contractorID;

        IF (readjustmentPeriod <= 0) THEN
          -- Disparamos uma exceção
          RAISE EXCEPTION 'Não foi possível determinar a duração do plano ID % informado.', NEW.planID
          USING HINT = 'O período de reajuste informado no plano é inválido.';
        END IF;

        IF ((NEW.dateOfNextReadjustment IS NULL) OR
            (NEW.dateOfNextReadjustment < CURRENT_DATE)) THEN
          -- Não foi informada a data do próximo reajuste, ou esta é
          -- inferior que a data atual então determina em função da data
          -- de início do período de reajuste, quando deve ocorrer o
          -- próximo evento. Faz isto acrescentando N vezes o período de
          -- reajuste (em meses) até chegar à uma data superior ao dia
          -- atual

          -- Força que a data de início da vigência da mensalidade seja
          -- a data a ser considerada como o início do período de
          -- reajuste
          NEW.effectivePriceDate := startOfReadjustmentPeriod;
          interaction := 0;

          LOOP
            IF (interaction > 0) THEN
              -- Fazemos com que a data de início de vigência desta
              -- mensalidade seja a data do último reajuste calculado
              NEW.effectivePriceDate := NEW.dateOfNextReadjustment;
            END IF;

            -- Calculamos a data do próximo reajuste
            NEW.dateOfNextReadjustment := (NEW.effectivePriceDate
              + interval '1 month' * readjustmentPeriod)::DATE;

            interaction := interaction + 1;

            -- Inserimos no histórico de reajustes estas informações
            INSERT INTO erp.readjustmentsOnInstallations (contractID,
              installationID, monthPrice, readjustedAt,
              readjustedByUserID) VALUES
              (NEW.contractID, NEW.installationID, NEW.monthPrice,
               NEW.effectivePriceDate, NEW.createdByUserID);

            -- Repetimos este processo até determinar uma data que seja
            -- posterior ao dia atual
            EXIT WHEN NEW.dateOfNextReadjustment > CURRENT_DATE;
          END LOOP;
        END IF;

        IF (NEW.effectivePriceDate < NEW.startDate) THEN
          -- Força que a data de início da vigência da mensalidade seja
          -- a data da instalação
          NEW.effectivePriceDate := NEW.startDate;
        END IF;

        -- IF (NEW.effectivePriceDate > (CURRENT_DATE + interval '1 month')::Date) THEN
        --   -- Disparamos uma exceção quando se tenta definir uma date de
        --   -- início da vigência superior a 1 mês em relação à data atual
        --   RAISE EXCEPTION 'A data % informada para início da vigência do valor da mensalidade é inválida.', NEW.effectivePriceDate
        --   USING HINT = 'A data de início da vigência do valor da mensalidade não pode ser uma data futura.';
        -- END IF;
      ELSIF (OLD.startDate IS NOT NULL) THEN
        -- A instalação já ocorreu
        IF (NEW.endDate IS NULL) THEN
          -- O contrato ainda está vigente, verificamos se ocorreu algum
          -- reajuste
          
          -- Verificamos alguma mudança da data de início da vigência da
          -- mensalidade
          IF (OLD.effectivePriceDate <> NEW.effectivePriceDate) THEN
            IF (OLD.effectivePriceDate < NEW.effectivePriceDate) THEN
              -- Consideramos que ocorreu algum reajuste. Verificamos se
              -- a data do próximo reajuste é válida
              IF (NEW.dateOfNextReadjustment < NEW.effectivePriceDate) THEN
                -- Não permitimos uma data superior à data do próximo
                -- reajuste. Disparamos uma exceção
                RAISE EXCEPTION 'A data % informada para início da vigência do valor da mensalidade é inválida.', NEW.effectivePriceDate
                USING HINT = 'A data de início da vigência da mensalidade não pode ser superior à data do próximo reajuste.';
              END IF;

              -- IF (NEW.effectivePriceDate > (CURRENT_DATE + interval '1 month')::Date) THEN
              --   -- Disparamos uma exceção quando se tenta definir uma date de
              --   -- início da vigência superior a 1 mês em relação à data atual
              --   RAISE EXCEPTION 'A data % informada para início da vigência do valor da mensalidade é inválida.', NEW.effectivePriceDate
              --   USING HINT = 'A data de início da vigência do valor da mensalidade não pode ser uma data futura.';
              -- END IF;

              -- Registra o reajuste de valores
              INSERT INTO erp.readjustmentsOnInstallations (contractID,
                installationID, monthPrice, readjustedAt,
                readjustedByUserID) VALUES
                (OLD.contractID, OLD.installationID, NEW.monthPrice,
                 NEW.effectivePriceDate, NEW.updatedByUserID);
            END IF;
          ELSE
            -- A data de início da vigência não sofreu modificações.

            -- Agora, verificamos alguma mudança do valor da mensalidade
            IF (OLD.monthPrice <> NEW.monthPrice) THEN
              -- Consideramos que ocorreu alguma correção de valor.

              -- Corrige o valor também no histórico
              UPDATE erp.readjustmentsOnInstallations
                 SET monthPrice = NEW.monthPrice,
                     readjustedByUserID = NEW.updatedByUserID
               WHERE contractID = OLD.contractID
                 AND installationID = OLD.installationID
                 AND readjustedAt = OLD.effectivePriceDate;
            END IF;
          END IF;
        END IF;
      ELSE
        -- Sempre força para que a data do próximo reajuste seja nula,
        -- já a instalação do equipamento ainda não ocorreu
        NEW.dateOfNextReadjustment := NULL;
      END IF;
    END IF;

    -- Retornamos a entidade modificada
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER installationTransactionTriggerBefore
  BEFORE INSERT OR UPDATE ON erp.installations
  FOR EACH ROW EXECUTE PROCEDURE erp.installationTransaction();

CREATE TRIGGER installationTransactionTriggerAfter
  AFTER INSERT ON erp.installations
  FOR EACH ROW EXECUTE PROCEDURE erp.installationTransaction();

-- ---------------------------------------------------------------------
-- Registros de instalação
-- ---------------------------------------------------------------------
-- Armazena as informações de quando um determinado equipamento foi
-- instalado em um veículo e quando o mesmo foi retirado. Esta tabela é
-- utilizada para determinar os períodos de cobrança dos serviços.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.installationRecords (
  installationRecordID  serial,   -- O ID do registro de instalação
  contractorID          integer   -- ID do contratante
                        NOT NULL,
  equipmentID           integer   -- ID do equipamento
                        NOT NULL,
  vehicleID             integer   -- ID do veículo
                        NOT NULL,
  installationID        integer   -- ID da instalação, que permite
                        NOT NULL, -- identificar o responsável pelo pagamento
  installedAt           date      -- A data em que ocorreu a instalação
                        NOT NULL,
  uninstalledAt         date      -- A data da desinstalação
                        DEFAULT NULL,
  createdAt             timestamp -- A data de criação do equipamento
                        NOT NULL
                        DEFAULT CURRENT_TIMESTAMP,
  createdByUserID       integer   -- O ID do usuário responsável
                        NOT NULL, -- pelo cadastro deste equipamento
  updatedAt             timestamp -- A data de modificação do
                        NOT NULL  -- equipamento
                        DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID       integer   -- O ID do usuário responsável pela
                        NOT NULL, -- última modificação
  PRIMARY KEY (installationRecordID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (equipmentID)
    REFERENCES erp.equipments(equipmentID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE RESTRICT,
  FOREIGN KEY (installationID)
    REFERENCES erp.installations(installationID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

CREATE INDEX idx_installationrecords_contractor 
    ON erp.installationRecords(contractorID, installationID, uninstalledAt, installedAt);

