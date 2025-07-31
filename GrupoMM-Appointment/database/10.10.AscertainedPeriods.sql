-- =====================================================================
-- Períodos apurados
-- =====================================================================
-- O controle dos períodos para os quais realizamos a apuração dos
-- valores de serviços executados por instalação. Quando realizamos os
-- cálculos do que precisa ser cobrado num período, precisamos determinar
-- quantos dias deste período efetivamente o serviço foi prestado em
-- função da existência de um equipamento instalado e vinculado.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Os períodos já apurados para efeito de cobrança de cada instalação
-- ---------------------------------------------------------------------
-- Armazena as informações de periodos computados em cada instalação.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.ascertainedPeriods (
  ascertainedPeriodID   serial,         -- ID do período apurado
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
  ascertainedDays       smallint        -- A quantidade de dias computados
                        NOT NULL
                        DEFAULT 0,
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
  PRIMARY KEY (ascertainedPeriodID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (installationID)
    REFERENCES erp.installations(installationID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Os detalhamentos dos períodos já apurados para efeito de cobrança de
-- cada instalação
-- ---------------------------------------------------------------------
-- São discriminados o veículo e o equipamento de rastreamento em que os
-- serviços foram apurados, bem como determina os valores a serem
-- cobrados. Um período apurado pode ter um ou mais detalhes, em função
-- da substituíção dos equipamentos de rastreamento e/ou troca de
-- veículos que podem ocorrer durante o processo.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.ascertainedPeriodDetails (
  detailID              serial,         -- ID do detalhamento do período apurado
  ascertainedPeriodID   integer         -- ID do período apurado
                        NOT NULL,
  vehicleID             integer         -- ID do veículo onde o período foi
                        NOT NULL,       -- apurado
  equipmentID           integer         -- ID do equipamento onde o período
                        NOT NULL,       -- foi apurado
  installationRecordID  integer         -- ID do registro de instalação
                        NOT NULL,       -- do qual o período foi apurado
  periodStartedAt       date            -- A data do início do período
                        NOT NULL,       -- apurado
  periodEndedAt         date            -- A data do fim do período
                        NOT NULL,       -- apurado
  duration              smallint        -- Quantidade de dias computados
                        NOT NULL,
  periodValue           numeric(12,2)   -- Valor calculado deste período
                        NOT NULL,
  subsidyID             integer         -- O ID do subsídio, caso este
                        DEFAULT NULL,   -- seja um desconto a ser aplicado
  billedBefore          boolean         -- O indicativo de que este período
                        NOT NULL        -- já foi cobrado antecipadamente
                        DEFAULT FALSE,
  PRIMARY KEY (detailID),
  FOREIGN KEY (ascertainedPeriodID)
    REFERENCES erp.ascertainedPeriods(ascertainedPeriodID)
    ON DELETE CASCADE,
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE RESTRICT,
  FOREIGN KEY (equipmentID)
    REFERENCES erp.equipments(equipmentID)
    ON DELETE RESTRICT
);
