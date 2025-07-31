-- =====================================================================
-- Períodos cobrados
-- =====================================================================
-- O controle dos períodos já cobrados por instalação. Quando realizamos
-- a cobrança de valores de um período, seja antecipadamente e/ou após a
-- execução do serviço, registramos nesta tabela para termos um controle
-- do período já faturado e permitir determinar novos períodos.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Os períodos já cobrados
-- ---------------------------------------------------------------------
-- Armazena as informações de periodos já cobrados em cada instalação.
-- Permite realizar análise das cobranças já realizadas quando for
-- necessário determinar um novo período a ser cobrado de uma instalação
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.billedPeriods (
  billedPeriodID        serial,         -- ID do período cobrado
  contractorID          integer         -- ID do contratante
                        NOT NULL,
  installationID        integer         -- ID da instalação
                        NOT NULL,
  referenceMonthYear    char(7)         -- O mês/ano de referência
                        NOT NULL,
  startDate             date            -- A data de início do período
                        NOT NULL,       -- apurado
  endDate               date            -- A data de término do período
                        NOT NULL,       -- apurado
  monthPrice            numeric(12,2)   -- Valor da mensalidade
                        NOT NULL
                        DEFAULT 0.00,
  grossValue            numeric(12,2)   -- Valor bruto calculado
                        NOT NULL
                        DEFAULT 0.00,
  discountValue         numeric(12,2)   -- Valor do desconto concedido
                        NOT NULL
                        DEFAULT 0.00,
  finalValue            numeric(12,2)   -- Valor final a ser cobrado
                        NOT NULL
                        DEFAULT 0.00,
  invoiceID             integer         -- O ID da fatura onde foi
                        DEFAULT NULL,   -- cobrado o periodo
  PRIMARY KEY (billedPeriodID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (installationID)
    REFERENCES erp.installations(installationID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Transações em períodos cobrados
-- ---------------------------------------------------------------------
-- Gatilho para lidar com as operações dos períodos cobrados de uma
-- instalação
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.billedPeriodTransaction()
RETURNS trigger AS $$
DECLARE
  FlastDayOfBillingPeriod  date;
BEGIN
  -- Lida com os períodos cobrados de uma instalação. Faz uso da
  -- variável especial TG_OP para verificar a operação executada e de
  -- TG_WHEN para determinar o instante em que isto ocorre
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Descobrimos a informação do último período cobrado para esta
      -- instalação
      SELECT INTO FlastDayOfBillingPeriod
             enddate
        FROM erp.billedPeriods
       WHERE installationid = NEW.installationID
       ORDER BY startDate DESC FETCH FIRST ROW ONLY;

      -- Atualizamos a data do último dia do período cobrado para esta
      -- instalação
      UPDATE erp.installations
         SET lastDayOfBillingPeriod = FlastDayOfBillingPeriod
       WHERE installationID = NEW.installationID;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Descobrimos a informação do último período cobrado para esta
      -- instalação
      SELECT INTO FlastDayOfBillingPeriod
             enddate
        FROM erp.billedPeriods
       WHERE installationid = OLD.installationID
       ORDER BY startDate DESC FETCH FIRST ROW ONLY;

      -- Atualizamos a data do último dia do período cobrado para esta
      -- instalação
      UPDATE erp.installations
         SET lastDayOfBillingPeriod = FlastDayOfBillingPeriod
       WHERE installationID = OLD.installationID;
    END IF;

    -- Retornamos a entidade modificada
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    IF (TG_WHEN = 'AFTER') THEN
      -- Descobrimos a informação do último período cobrado para esta
      -- instalação
      SELECT INTO FlastDayOfBillingPeriod
             enddate
        FROM erp.billedPeriods
       WHERE installationid = OLD.installationID
       ORDER BY startDate DESC FETCH FIRST ROW ONLY;
      IF NOT FOUND THEN
        -- Deixamos como nulo o último período cobrado
        UPDATE erp.installations
           SET lastDayOfBillingPeriod = NULL
         WHERE installationID = OLD.installationID;
      ELSE
        -- Atualizamos a data do último dia do período cobrado para esta
        -- instalação
        UPDATE erp.installations
           SET lastDayOfBillingPeriod = FlastDayOfBillingPeriod
         WHERE installationID = OLD.installationID;
      END IF;
    END IF;

    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER billedPeriodTransactionTriggerAfter
  AFTER INSERT OR UPDATE OR DELETE ON erp.billedPeriods
  FOR EACH ROW EXECUTE PROCEDURE erp.billedPeriodTransaction();

