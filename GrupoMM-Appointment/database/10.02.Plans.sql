-- =====================================================================
-- Planos de serviços
-- =====================================================================
-- Os planos nos permitem controlar os valores cobrados por cada serviço
-- prestado ao cliente. Ao adquirir um plano, o cliente estabelece um
-- contrato onde as condições do plano são aplicadas. Cada plano prevê
-- um preço a ser cobrado por mês (base) e pode prever planos de
-- assinaturas maiores (ex: 3 meses, 6 meses ou 1 ano) que são pagas de
-- uma única vêz, mas que garante ao cliente um desconto pelo serviço
-- que será prestado por um período estipulado.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Planos
-- ---------------------------------------------------------------------
-- Os planos de serviços disponíveis.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.plans (
  planID                      serial,          -- ID do plano
  contractorID                integer          -- ID do contratante
                              NOT NULL,
  name                        varchar(50)      -- Nome do plano
                              NOT NULL,
  description                 text,            -- Uma descrição do plano
  monthPrice                  numeric(12,2),   -- Valor mensal
  duration                    smallint         -- Duração do contrato (em
                              NOT NULL         -- meses)
                              DEFAULT 12,
  loyaltyPeriod               smallint         -- Duração do período de
                              NOT NULL         -- fidelidade (em meses)
                              DEFAULT 0,
  loyaltyFine                 numeric(8,4)     -- O valor na multa cobrado
                              NOT NULL         -- em caso de rompimento antes
                              DEFAULT 10.0000, -- do final do período
  indicatorID                 integer          -- O ID do indicador financeiro
                              NOT NULL,        -- a ser utilizado para reajuste
  readjustmentPeriod          smallint         -- O período de meses após o
                              NOT NULL         -- qual se aplica o reajuste
                              DEFAULT 12,
  readjustWithSingleValue     boolean          -- O indicativo de que o reajuste
                              DEFAULT false,   -- irá ocorrer com um valor único
  fineValue                   numeric(8,4)     -- Taxa de multa a ser cobrado
                              NOT NULL         -- sobre o valor devido em
                              DEFAULT 0.0000,  -- caso de atraso no pagamento
  arrearInterestType          integer          -- Tipo da taxa diária dos juros de mora
                              NOT NULL         --   1: valor
                              DEFAULT 2,       --   2: porcentagem
  arrearInterest              numeric(8,4)     -- Taxa diária dos juros de
                              NOT NULL         -- mora que incidirão sobre
                              DEFAULT 0.0333,  -- o valor devido em caso de
                                               -- atraso no pagamento
  drivingPositioningInterval  smallint         -- O intervalo de transmissão
                              NOT NULL         -- quando o veículo está com
                              DEFAULT 60,      -- a ignição ligada (em segundos)
  stoppedPositioningInterval  smallint         -- O intervalo de transmissão
                              NOT NULL         -- quando o veículo está com
                              DEFAULT 3600,    -- a ignição desligada
  allowExtendingDeadline      boolean          -- Flag indicador de permissão
                              NOT NULL         -- para estender prazo de
                              DEFAULT false,   -- boletos vencidos
  dueDateOnlyInWorkingDays    boolean          -- Flag indicador de
                              NOT NULL         -- vencimento apenas em dias
                              DEFAULT true,    -- úteis
  prorata                     boolean          -- Flag indicador de cobrança
                              NOT NULL         -- proporcional aos dias
                              DEFAULT true,    -- contratados
  active                      boolean          -- Flag indicador de plano
                              NOT NULL         -- ativo
                              DEFAULT false,
  createdAt                   timestamp        -- A data de criação do
                              NOT NULL         -- plano
                              DEFAULT CURRENT_TIMESTAMP,
  createdByUserID             integer          -- O ID do usuário responsável
                              NOT NULL,        -- pelo cadastro
  updatedAt                   timestamp        -- A data de modificação
                              NOT NULL
                              DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID             integer          -- O ID do usuário responsável
                              NOT NULL,        -- pela última modificação
  deleted                     boolean          -- Flag indicador de plano
                              NOT NULL         -- apagado
                              DEFAULT false,
  deletedAt                   timestamp        -- A data de remoção
                              DEFAULT NULL,
  deletedByUserID             integer          -- O ID do usuário
                              DEFAULT NULL,    -- responsável pela remoção
  PRIMARY KEY (planID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (indicatorID)
    REFERENCES erp.indicators(indicatorID)
    ON DELETE RESTRICT,
  FOREIGN KEY (arrearInterestType)
    REFERENCES erp.measureTypes(measureTypeID)
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

INSERT INTO erp.plans (planID, contractorID, name, description,
  monthPrice, duration, loyaltyPeriod, loyaltyFine, indicatorID,
  readjustmentPeriod, readjustWithSingleValue, fineValue, arrearInterest,
  drivingPositioningInterval, stoppedPositioningInterval, 
  allowExtendingDeadline, dueDateOnlyInWorkingDays, prorata, active,
  createdByUserID, updatedByUserID) VALUES
  (1, 1, 'Básico',
    'Serviço de rastreamento básico (sem bloqueio):\n' ||
    ' - Localização em tempo real 24 horas por dia;\n' ||
    ' - Cobertura nacional;\n' ||
    ' - Histórico de percurso;', 65.00, 12, 12, 10.0000, 2, 12, false,
    2.0000, 0.0333, 1*60, 60*60, false, true, true, true,  1, 1),
  (2, 1, 'Light',
    'Serviço de rastreamento light (sem bloqueio):\n' ||
    ' - Localização em tempo real 24 horas por dia;\n' ||
    ' - Cobertura nacional;\n' ||
    ' - Histórico de percurso;', 30.00, 12, 12, 10.0000, 2, 12, false,
    2.0000, 0.0333, 1*60, 60*60, false, true, true, true,  1, 1),
  (3, 1, 'Avançado',
    'Serviço de rastreamento avançado:\n' ||
    ' - Localização em tempo real 24 horas por dia;\n' ||
    ' - Cobertura nacional;\n' ||
    ' - Histórico de percurso;\n' ||
    ' - Controle de jornada;\n', 80.00, 12, 12, 10.0000, 2, 12, false,
    2.0000, 0.0333, 1*60, 60*60, false, true, true, true,  1, 1);

ALTER SEQUENCE erp.plans_planid_seq RESTART WITH 4;

-- ---------------------------------------------------------------------
-- Planos de assinatura
-- ---------------------------------------------------------------------
-- Um plano de assinatura oferece descontos em relação ao valor base de
-- um plano para o cliente que contratar, pagando antecipadamente, por
-- um período de meses o referido plano.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.subscriptionPlans (
  subscriptionPlanID  serial,         -- ID da assinatura disponível por plano
  planId              integer         -- O ID do plano
                      NOT NULL,
  numberOfMonths      smallint        -- O tempo da assinatura em meses
                      NOT NULL
                      DEFAULT 1,
  discountRate        numeric(8,4)    -- A taxa de desconto
                      NOT NULL        -- oferecida
                      DEFAULT 0.0000,
  PRIMARY KEY (subscriptionPlanID),
  FOREIGN KEY (planID)
    REFERENCES erp.plans(planID)
    ON DELETE CASCADE
);

INSERT INTO erp.subscriptionPlans (planID,
  numberOfMonths, discountRate) VALUES
  -- 1. Plano Básico
  (1,  1,  0.0000),
  (1,  3,  3.0000),
  (1,  6,  5.0000),
  (1, 12, 10.0000),
  -- 2. Plano Light
  (2,  1,  0.0000),
  -- 3. Plano Avançado
  (3,  1,  0.0000),
  (3,  3,  3.0000),
  (3,  6,  5.0000),
  (3, 12, 10.0000);

-- ---------------------------------------------------------------------
-- Tipos de cobranças por plano
-- ---------------------------------------------------------------------
-- Contém as informações dos tipos de cobranças permitidas para um
-- determinado plano de serviços. Contratos nele baseados utilizam estes
-- valores como referência, porém permitindo definir valores diferentes
-- em caso de negociação com o cliente.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.planCharges (
  planChargeID    serial,         -- ID da tarifa cobrada no plano
  planID          integer         -- ID do plano
                  NOT NULL,
  billingTypeID   integer         -- ID do tipo de cobrança
                  NOT NULL,
  chargeValue     numeric(12,2)   -- Valor cobrado
                  NOT NULL
                  DEFAULT 0.00,
  createdAt       timestamp       -- A data de inclusão da tarifa neste
                  NOT NULL        -- plano
                  DEFAULT CURRENT_TIMESTAMP,
  createdByUserID integer         -- O ID do usuário responsável pelo
                  NOT NULL,       -- cadastro
  updatedAt       timestamp       -- A data de modificação
                  NOT NULL
                  DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID integer         -- O ID do usuário responsável pela
                  NOT NULL,       -- última modificação
  PRIMARY KEY (planChargeID),
  FOREIGN KEY (planID)
    REFERENCES erp.plans(planID)
    ON DELETE CASCADE,
  FOREIGN KEY (billingTypeID)
    REFERENCES erp.billingTypes(billingTypeID)
    ON DELETE RESTRICT
);

INSERT INTO erp.planCharges (planID, billingTypeID, chargeValue, createdByUserID, updatedByUserID) VALUES
  -- 1. Plano Básico
  ( 1, 1, 150.00, 1, 1), -- Adesão
  ( 1, 2, 150.00, 1, 1), -- Instalação
  ( 1, 3, 150.00, 1, 1), -- Reinstalação
  ( 1, 4,  80.00, 1, 1), -- Manutenção
  ( 1, 5, 180.00, 1, 1), -- Transferência
  ( 1, 6, 120.00, 1, 1), -- Retirada
  -- 2. Plano Light
  ( 2, 1, 100.00, 1, 1), -- Adesão
  ( 2, 2, 100.00, 1, 1), -- Instalação
  ( 2, 3, 100.00, 1, 1), -- Reinstalação
  ( 2, 4,  80.00, 1, 1), -- Manutenção
  ( 2, 5, 180.00, 1, 1), -- Transferência
  ( 2, 6,  40.00, 1, 1), -- Retirada
  -- 3. Plano Avançado
  ( 3, 1, 150.00, 1, 1), -- Adesão
  ( 3, 2, 150.00, 1, 1), -- Instalação
  ( 3, 3, 150.00, 1, 1), -- Reinstalação
  ( 3, 4,  80.00, 1, 1), -- Manutenção
  ( 3, 5, 180.00, 1, 1), -- Transferência
  ( 3, 6, 120.00, 1, 1); -- Retirada

-- ---------------------------------------------------------------------
-- Características técnicas por plano
-- ---------------------------------------------------------------------
-- Cada plano possui um conjunto de características técnicas que nos
-- permitem determinar quais modelos de equipamentos atendem a estes
-- requisitos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.planFeatures (
  planFeatureID  serial,         -- ID da característica técnica do plano
  planID         integer         -- ID do plano
                 NOT NULL,
  featureID      integer         -- ID da característica técnica
                 NOT NULL,
  PRIMARY KEY (planFeatureID),
  FOREIGN KEY (planID)
    REFERENCES erp.plans(planID)
    ON DELETE CASCADE
);

INSERT INTO erp.planFeatures (planID, featureID) VALUES
  (1,  1),
  (1,  7),
  (1, 11),
  (2,  1),
  (2,  7),
  (2, 11),
  (3,  1),
  (3,  2),
  (3,  3),
  (3,  4),
  (3,  7),
  (3, 11),
  (3, 20);
INSERT INTO erp.planFeatures (planID, featureID) VALUES
  (4,  1),
  (4,  7),
  (4, 11),
  (5,  1),
  (5,  7),
  (5, 11),
  (6,  1),
  (6,  7),
  (6, 11),
  (7,  1),
  (7,  7),
  (7, 11),
  (8,  1),
  (8,  7),
  (8, 11);
