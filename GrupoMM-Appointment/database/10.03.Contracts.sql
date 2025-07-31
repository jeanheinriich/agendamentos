-- =====================================================================
-- Contratos
-- =====================================================================
-- Contém as informações dos contratos celebrados com cada cliente.
-- Quando um cliente contrata o serviço, um contrato é celebrado. Este
-- contrato é baseado num plano de serviços e nele serão informadas
-- todas as condições e valores cobrados. Apesar dos valores se basearem
-- num plano ofertado, seus valores podem ser modificados em função de
-- acordos com o cliente.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Contratos
-- ---------------------------------------------------------------------
-- Contém as informações dos contratos celebrados com cada cliente.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.contracts (
  contractID                    serial,           -- ID do contrato
  contractorID                  integer           -- ID do contratante
                                NOT NULL,
  customerID                    integer           -- ID do cliente
                                NOT NULL,
  subsidiaryID                  integer           -- ID da unidade/filial
                                NOT NULL,         -- do cliente que contratou
  customerPayerID               integer           -- ID do cliente
                                NOT NULL,
  subsidiaryPayerID             integer           -- ID da unidade/filial do
                                NOT NULL,         -- cliente que contratou
  planId                        integer           -- O ID do plano contratado
                                NOT NULL,
  subscriptionPlanID            integer           -- ID da assinatura
                                NOT NULL,         -- escolhida
  signatureDate                 date              -- A data de início da
                                DEFAULT NULL,     -- vigência do contrato
  endDate                       date              -- A data de término da
                                DEFAULT NULL,     -- vigência do contrato
  monthPrice                    numeric(12,2),    -- Valor da mensalidade
  effectivePriceDate            date              -- A data de início da
                                NOT NULL          -- vigência deste valor
                                DEFAULT CURRENT_DATE,
  dateOfNextReadjustment        date              -- Data do próximo reajuste
                                DEFAULT NULL,     -- setada após a assinatura
  dueDayID                      integer           -- ID do dia de vencimento
                                NOT NULL,
  paymentConditionID            integer           -- ID da condição de pagamento
                                NOT NULL,         -- da mensalidade
  prepaid                       boolean           -- Mensalidades são cobradas
                                NOT NULL          -- antes do início da prestação
                                DEFAULT false,    -- do serviço (pré-pagas)
  additionalPaymentConditionID  integer           -- ID da condição de pagamento
                                NOT NULL,         -- de valores adicionais (em caso de pré-pago ou boleto)
  chargeAnyTariffs              boolean           -- Permitir cobrar tarifas
                                NOT NULL          -- definidas pelo meio de
                                DEFAULT true,     -- pagamento
  unifyBilling                  boolean           -- Unificar cobranças de
                                NOT NULL          -- instalações numa única
                                DEFAULT true,     -- cobrança
  startTermAfterInstallation    boolean           -- Início da vigência se
                                NOT NULL          -- dá após a instalação
                                DEFAULT true,
  manualReadjustment            boolean           -- Reajuste manual do
                                NOT NULL          -- contrato
                                DEFAULT false,
  active                        boolean           -- Indicador de contrato
                                NOT NULL          -- ativo
                                DEFAULT false,
  notChargeLoyaltyBreak         boolean           -- O indicativo de que não
                                NOT NULL          -- devemos cobrar multa por
                                DEFAULT false,    -- quebra de fidelidade
  maxWaitingTime                integer           -- Tempo máximo de espera
                                NOT NULL          -- do técnico no local
                                DEFAULT 15,       -- aguardando
  unproductiveVisit             numeric(12, 2)    -- O valor da multa por
                                NOT NULL          -- visita improdutiva do
                                DEFAULT 100.0000, -- técnico no local
  unproductiveVisitType         integer           -- Tipo da cobrança
                                NOT NULL          --   1: valor
                                DEFAULT 2,        --   2: porcentagem
  minimumTime                   integer           -- Tempo mínimo para
                                NOT NULL          -- solicitar cancelamento
                                DEFAULT 1,        -- ou reagendamento
  minimumTimeType               integer           -- Unidade de tempo
                                NOT NULL          --   1: Horas
                                DEFAULT 2,        --   2: Dias
  frustratedVisit               numeric(12, 2)    -- O valor da multa por
                                NOT NULL          -- visita frustrada do
                                DEFAULT 100.0000, -- técnico
  frustratedVisitType           integer           -- Tipo da cobrança
                                NOT NULL          --   1: valor
                                DEFAULT 2,        --   2: porcentagem
  geographicCoordinateID        integer           -- A coordenada geográfica
                                NOT NULL,         -- de referência para cálculo de deslocamento
  createdAt                     timestamp         -- A data de criação do
                                NOT NULL          -- contrato
                                DEFAULT CURRENT_TIMESTAMP,
  createdByUserID               integer           -- O ID do usuário responsável
                                NOT NULL,         -- pelo cadastro
  updatedAt                     timestamp         -- A data de modificação
                                NOT NULL
                                DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID               integer           -- O ID do usuário responsável
                                NOT NULL,         -- pela última modificação
  deleted                       boolean           -- Flag indicador de
                                NOT NULL          -- contrato removido
                                DEFAULT false,
  deletedAt                     timestamp         -- A data de remoção
                                DEFAULT NULL,
  deletedByUserID               integer           -- O ID do usuário responsável
                                DEFAULT NULL,     -- pela remoção deste contrato
  PRIMARY KEY (contractID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (planID)
    REFERENCES erp.plans(planID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subscriptionPlanID)
    REFERENCES erp.subscriptionPlans(subscriptionPlanID)
    ON DELETE RESTRICT,
  FOREIGN KEY (dueDayID)
    REFERENCES erp.dueDays(dueDayID)
    ON DELETE RESTRICT,
  FOREIGN KEY (unproductiveVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (frustratedVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (frustratedVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (geographicCoordinateID)
    REFERENCES erp.geographicCoordinates(geographicCoordinateID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

CREATE INDEX idx_contracts_deleted_contractorid ON erp.contracts(deleted, contractorid);
CREATE INDEX idx_contracts_planid ON erp.contracts(planid);
CREATE INDEX idx_contracts_subsidaryid ON erp.contracts(subsidiaryid);

-- Gatilho para lidar com modificações no contrato
CREATE OR REPLACE FUNCTION erp.contractTransaction()
RETURNS trigger AS $BODY$
DECLARE
  amount  integer;
BEGIN
  -- Faz a atualização de itens de contrato em função de modificações à
  -- nível de contrato. Faz uso da variável especial TG_OP para verificar
  -- a operação executada e de TG_WHEN para determinar o instante em que
  -- isto ocorre.
  IF (TG_OP = 'INSERT') THEN

    -- Retornamos o novo contrato
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Verifica se foi marcado para não cobrar multa por quebra de
      -- contrato
      IF (OLD.notChargeLoyaltyBreak <> NEW.notChargeLoyaltyBreak) THEN
        -- Precisamos forçar para que todos os demais itens deste
        -- contrato cobrem (ou não) a multa por quebra do período de
        -- fidelidade em função do que está estipulado no contrato
        UPDATE erp.installations
           SET notChargeLoyaltyBreak = NEW.notChargeLoyaltyBreak
         WHERE contractID = OLD.contractID;
      END IF;
    END IF;

    -- Retornamos o novo contrato
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER contractsTransactionTriggerAfter
  AFTER INSERT OR UPDATE ON erp.contracts
  FOR EACH ROW EXECUTE PROCEDURE erp.contractTransaction();

-- ---------------------------------------------------------------------
-- Número de contrato
-- ---------------------------------------------------------------------
-- Stored Procedure que converte a data de criação do contrato em um
-- identificador de contrato
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getContractNumber(fCreatedAt timestamp)
RETURNS char(16) AS
$$
DECLARE
  year        integer;
  daysToDate  integer;
  day         integer;
  hour        integer;
  minute      integer;
  second      integer;
BEGIN
  SELECT EXTRACT(YEAR FROM fCreatedAt) INTO year;
  SELECT
    DATE_PART('doy', fCreatedAt) INTO daysToDate;
  SELECT EXTRACT(DAY FROM fCreatedAt) INTO day;
  SELECT EXTRACT(HOUR FROM fCreatedAt) INTO hour;
  SELECT EXTRACT(MINUTE FROM fCreatedAt) INTO minute;
  SELECT EXTRACT(SECOND FROM fCreatedAt) INTO second;

  RETURN year::text ||
         '.' ||
         LPAD((daysToDate * 24)::text, 4, '0') ||
         '.' ||
         LPAD(((hour * 60 * 60) + (MINUTE * 60) + second)::text, 5, '0');
END;
$$ LANGUAGE PLPGSQL;

-- ---------------------------------------------------------------------
-- Histórico de reajustes das mensalidades de cada contrato
-- ---------------------------------------------------------------------
-- Contém os registros dos reajustes realizados em cada contrato
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.readjustments (
  readjustmentID      serial,         -- ID do reajuste
  contractID          integer         -- ID do contrato
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
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Reajustes de contrato
-- ---------------------------------------------------------------------
-- Gatilho para lidar com as operações dos reajustes de valores de um
-- contrato
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.readjustmentTransaction()
RETURNS trigger AS $$
DECLARE
  readjustmentPeriod smallint;
  interaction smallint;
BEGIN
  -- Lida com reajustes da mensalidade num plano. Faz uso da variável
  -- especial TG_OP para verificar a operação executada e de TG_WHEN
  -- para determinar o instante em que isto ocorre
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Inserimos a informação de quem vai pagar, se ausente
      IF NEW.customerPayerID IS NULL THEN
        NEW.customerPayerID := NEW.customerID;
      END IF;
      IF NEW.subsidiaryPayerID IS NULL THEN
        NEW.subsidiaryPayerID := NEW.subsidiaryID;
      END IF;
      
      IF (NEW.signatureDate IS NOT NULL) THEN
        -- O contrato já foi assinado e NEW.signatureDate contém a data
        -- em que isto ocorreu
          
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
          -- de assinatura do contrato, acrescido do período (em meses)
          -- do tempo para reajuste.

          -- Força que a data de início da vigência da mensalidade seja
          -- a data da assinatura do contrato
          NEW.effectivePriceDate := NEW.signatureDate;
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

        IF (NEW.effectivePriceDate < NEW.signatureDate) THEN
          -- Força que a data de início da vigência da mensalidade seja
          -- a data da assinatura do contrato
          NEW.effectivePriceDate := NEW.signatureDate;
        END IF;

        IF (NEW.effectivePriceDate > (CURRENT_DATE + interval '1 month')::Date) THEN
          -- Disparamos uma exceção quando se tenta definir uma date de
          -- início da vigência superior a 1 mês em relação à data atual
          RAISE EXCEPTION 'A data % informada para início da vigência do valor da mensalidade é inválida.', NEW.effectivePriceDate
          USING HINT = 'A data de início da vigência do valor da mensalidade não pode ser uma data futura.';
        END IF;
      ELSE
        -- Sempre força para que a data do próximo reajuste seja nula,
        -- já que o contrato ainda não foi assinado
        NEW.dateOfNextReadjustment := NULL;
      END IF;
    ELSIF (TG_WHEN = 'AFTER') THEN
      IF (NEW.signatureDate IS NOT NULL) THEN
        -- Inserimos a tarifa inicial no histórico de reajustes como
        -- sendo a data da assinatura do contrato
        INSERT INTO erp.readjustments (contractID, monthPrice,
          readjustedAt, readjustedByUserID) VALUES
          (NEW.contractID, NEW.monthPrice, NEW.effectivePriceDate,
           NEW.createdByUserID);
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica se o contrato foi assinado
      IF ((OLD.signatureDate IS NULL) AND (NEW.signatureDate IS NOT NULL)) THEN
        -- O contrato foi assinado e NEW.signatureDate contém a data em que
        -- isto ocorreu

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
          -- de assinatura do contrato, acrescido do período (em meses)
          -- do tempo para reajuste.

          -- Força que a data de início da vigência da mensalidade seja
          -- a data da assinatura do contrato
          NEW.effectivePriceDate := NEW.signatureDate;
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
            INSERT INTO erp.readjustments (contractID, monthPrice,
              readjustedAt, readjustedByUserID) VALUES
              (NEW.contractID, NEW.monthPrice, NEW.effectivePriceDate,
               NEW.createdByUserID);

            -- Repetimos este processo até determinar uma data que seja
            -- posterior ao dia atual
            EXIT WHEN NEW.dateOfNextReadjustment > CURRENT_DATE;
          END LOOP;
        END IF;

        IF (NEW.effectivePriceDate < NEW.signatureDate) THEN
          -- Força que a data de início da vigência da mensalidade seja
          -- a data da assinatura do contrato
          NEW.effectivePriceDate := NEW.signatureDate;
        END IF;

        IF (NEW.effectivePriceDate > (CURRENT_DATE + interval '1 month')::Date) THEN
          -- Disparamos uma exceção quando se tenta definir uma date de
          -- início da vigência superior a 1 mês em relação à data atual
          RAISE EXCEPTION 'A data % informada para início da vigência do valor da mensalidade é inválida.', NEW.effectivePriceDate
          USING HINT = 'A data de início da vigência da mensalidade não pode ser uma data futura.';
        END IF;
      ELSIF (OLD.signatureDate IS NOT NULL) THEN
        -- O contrato já se encontra assinado
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
                USING HINT = 'A data de início da vigência do valor da mensalidade não pode ser superior à data do próximo reajuste.';
              END IF;

              IF (NEW.effectivePriceDate > (CURRENT_DATE + interval '1 month')::Date) THEN
                -- Disparamos uma exceção quando se tenta definir uma date de
                -- início da vigência superior a 1 mês em relação à data atual
                RAISE EXCEPTION 'A data % informada para início da vigência do valor da mensalidade é inválida.', NEW.effectivePriceDate
                USING HINT = 'A data de início da vigência do valor da mensalidade não pode ser uma data futura.';
              END IF;

              -- Registra o reajuste de valores
              INSERT INTO erp.readjustments (contractID, monthPrice,
                readjustedAt, readjustedByUserID) VALUES
                (OLD.contractID, NEW.monthPrice, NEW.effectivePriceDate,
                 NEW.updatedByUserID);
            END IF;
          ELSE
            -- A data de início da vigência não sofreu modificações.

            -- Agora, verificamos alguma mudança do valor da mensalidade
            IF (OLD.monthPrice <> NEW.monthPrice) THEN
              -- Consideramos que ocorreu alguma correção de valor.

              -- Corrige o valor também no histórico
              UPDATE erp.readjustments
                 SET monthPrice = NEW.monthPrice,
                     readjustedByUserID = NEW.updatedByUserID
               WHERE contractID = OLD.contractID
                 AND readjustedAt = OLD.effectivePriceDate;
            END IF;
          END IF;
        ELSE
          -- Estamos lidando com o encerramento do contrato
          IF (OLD.endDate IS NULL) THEN
            -- Estamos encerrando o contrato, então precisamos encerrar
            -- todas as instalações deste contrato que ainda estejam
            -- ativas
            UPDATE erp.installations
               SET endDate = NEW.endDate
             WHERE contractID = OLD.contractID
               AND endDate IS NULL;
          ELSE
            -- O contrato já estava encerrado
            IF (OLD.endDate <> NEW.endDate) THEN
              -- Ocorreu uma mudança da data de encerramento do contrato
              -- então precisamos modificar as datas de encerramento das
              -- instalações também
              UPDATE erp.installations
                 SET endDate = NEW.endDate
               WHERE contractID = OLD.contractID
                 AND endDate = OLD.endDate;
            END IF;
          END IF;
        END IF;
      ELSE
        -- Sempre força para que a data do próximo reajuste seja nula,
        -- já que o contrato ainda não foi assinado
        NEW.dateOfNextReadjustment := NULL;
      END IF;
    END IF;

    -- Retornamos a entidade modificada
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Removemos todos os registros de reajuste deste contrato
      DELETE FROM erp.readjustments
       WHERE contractID = OLD.contractID;
    END IF;

    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER readjustmentTransactionTriggerBefore
  BEFORE INSERT OR UPDATE OR DELETE ON erp.contracts
  FOR EACH ROW EXECUTE PROCEDURE erp.readjustmentTransaction();
CREATE TRIGGER readjustmentTransactionTriggerAfter
  AFTER INSERT OR UPDATE OR DELETE ON erp.contracts
  FOR EACH ROW EXECUTE PROCEDURE erp.readjustmentTransaction();

-- ---------------------------------------------------------------------
-- Tarifas cobradas por contrato
-- ---------------------------------------------------------------------
-- Contém as informações das tarifas cobradas para um determinado
-- contrato. Estes valores são cobrados conforme estipulado.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.contractCharges (
  contractChargeID  serial,       -- ID da cobrança no contrato
  contractID        integer       -- Número de identificação do contrato
                    NOT NULL,
  billingTypeID     integer       -- ID do tipo de cobrança
                    NOT NULL,
  chargeValue       numeric(12,2) -- Valor cobrado
                    NOT NULL
                    DEFAULT 0.00,
  createdAt         timestamp     -- A data de inclusão da tarifa neste
                    NOT NULL      -- contrato
                    DEFAULT CURRENT_TIMESTAMP,
  createdByUserID   integer       -- O ID do usuário responsável pelo
                    NOT NULL,     -- cadastro
  updatedAt         timestamp     -- A data de modificação
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID   integer       -- O ID do usuário responsável pela
                    NOT NULL,     -- última modificação
  PRIMARY KEY (contractChargeID),
  FOREIGN KEY (contractID)
    REFERENCES erp.contracts(contractID)
    ON DELETE CASCADE,
  FOREIGN KEY (billingTypeID)
    REFERENCES erp.billingTypes(billingTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Características técnicas por contrato
-- ---------------------------------------------------------------------
-- Cada plano possui um conjunto de características técnicas que nos
-- permitem determinar quais modelos de equipamentos atendem a estes
-- requisitos. Todavia, um determinado contrato pode requerer
-- características adicionais que serão informadas.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.contractFeatures (
  contractFeatureID  serial,         -- ID da característica técnica adicional
  contractID         integer         -- ID do contrato
                     NOT NULL,
  featureID          integer         -- ID da característica técnica
                     NOT NULL,
  PRIMARY KEY (contractFeatureID),
  FOREIGN KEY (contractID)
    REFERENCES erp.contracts(contractID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Taxa de deslocamento
-- ---------------------------------------------------------------------
-- A taxa de deslocamento é o valor a ser cobrado do cliente quando for
-- necessário deslocar um técnico para atendê-lo. Valores de distância
-- nulo são considerados como o valor máximo a ser cobrado do cliente.
-- Neste caso, se especificarmos os valores 5, 10 e NULO, será
-- considerado que de 0 até 5km, será cobrado uma taxa, acima de 5 e até
-- 10km, será cobrado a segunda taxa e, qualquer valor acima disto será
-- cobrada a taxa presente em nulo. Deve existir ao menos uma taxa
-- descrita. Caso não se deseje cobrar, basta colocar o valor 0,00 no 
-- campo 'value'.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.displacementFees (
  displacementFeeID serial,         -- ID da taxa disponível por contrato
  contractID        integer         -- O ID do contrato
                    NOT NULL,
  distance          integer         -- A distância (em km) até a qual
                    DEFAULT NULL,   -- esta faixa está compreendida
  value             numeric(8,2)    -- A taxa a ser cobrada (por padrão
                    NOT NULL        -- não cobra)
                    DEFAULT 0.00,
  CHECK (distance IS NULL OR distance > 0),
  PRIMARY KEY (displacementFeeID),
  FOREIGN KEY (contractID)
    REFERENCES erp.contracts(contractID)
    ON DELETE CASCADE
);
