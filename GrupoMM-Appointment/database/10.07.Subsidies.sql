-- =====================================================================
-- Subsídios em contratos
-- =====================================================================
-- Tabelas para controle de gratuidades e bonificações concedidas em um
-- contrato
-- =====================================================================

-- ---------------------------------------------------------------------
-- Os subsídios (bonificação, período de gratuídade) que podem ser
-- concedidos à um cliente em uma instalação.
-- ---------------------------------------------------------------------
-- Armazena as informações de periodos de gratuidade para a cobrança de
-- um equipamento, bem como de equipamentos bonificados (quando a
-- cobrança não deve ser feita. Esta tabela é utilizada no processamento
-- para determinação dos períodos a serem cobrados de cada serviço.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.subsidies (
  subsidyID             serial,         -- O ID do registro de subsídio
  contractorID          integer         -- ID do contratante
                        NOT NULL,
  installationID        integer         -- ID da instalação
                        NOT NULL,
  bonus                 boolean         -- Indicador de dispositivo
                        DEFAULT FALSE,  -- bonificado (não cobrado)
  periodStartedAt       date            -- A data do início do período
                        NOT NULL,       -- de subsídio
  periodEndedAt         date            -- A data do término do período
                        DEFAULT NULL,   -- de subsídio (Nulo = indeterminado)
  discountType          integer         -- Tipo do desconto concedido
                        NOT NULL        --   1: valor
                        DEFAULT 1,      --   2: porcentagem
  discountValue         numeric(12,4)   -- Valor do desconto concedido
                        NOT NULL
                        DEFAULT 0.0000,
  createdAt             timestamp       -- A data de criação
                        NOT NULL
                        DEFAULT CURRENT_TIMESTAMP,
  createdByUserID       integer         -- O ID do usuário responsável
                        NOT NULL,       -- pelo cadastro
  updatedAt             timestamp       -- A data de modificação
                        NOT NULL
                        DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID       integer         -- O ID do usuário responsável
                        NOT NULL,       -- pela última modificação
  CHECK ( (bonus AND (periodEndedAt IS NULL)) OR
          ((NOT bonus) OR (periodEndedAt IS NOT NULL)) ),
  PRIMARY KEY (subsidyID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (installationID)
    REFERENCES erp.installations(installationID)
    ON DELETE CASCADE,
  FOREIGN KEY (discountType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);
