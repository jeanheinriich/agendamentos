-- =====================================================================
-- INFORMAÇÕES RELACIONADAS COM CARACTERÍSTICAS DO SISTEMA
-- =====================================================================
-- Tabelas utilizada a nível de cadastro em diversas partes.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Tipos de entidades
-- ---------------------------------------------------------------------
-- Armazena as informações de tipos de entidades.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.entitiesTypes (
  entityTypeID      serial,      -- O ID do tipo de entidade
  name              varchar(30)  -- O nome do tipo de entidade
                    NOT NULL,
  juridicalperson   boolean      -- Flag indicador de pessoa jurídica
                    NOT NULL,
  cooperative       boolean      -- Flag indicador de cooperativa
                    NOT NULL,
  PRIMARY KEY (entityTypeID)
);

-- Insere os tipos de entidades
INSERT INTO erp.entitiesTypes (entityTypeID, name, juridicalperson,
  cooperative) VALUES
  ( 1, 'Pessoa jurídica', true, false),
  ( 2, 'Pessoa física', false, false),
  ( 3, 'Associação', true, true);

ALTER SEQUENCE erp.entitiestypes_entitytypeid_seq RESTART WITH 4;

-- ---------------------------------------------------------------------
-- Ações do sistema
-- ---------------------------------------------------------------------
-- As ações do sistema que disparam o envio de mensagens.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.systemActions (
  systemActionID  serial,       -- ID da ação do sistema
  name            varchar(30)   -- Nome da ação
                  NOT NULL,
  action          varchar(30)   -- O nome da ação interna
                  NOT NULL,
  PRIMARY KEY (systemActionID)
);

-- Insere as ações disponíveis
INSERT INTO erp.systemActions (systemActionID, name, action) VALUES
  ( 1, 'Agendamento', 'scheduling'),
  ( 2, 'Atendimento técnico', 'attendance'),
  ( 3, 'Emissão de boleto', 'bankslip'),
  ( 4, 'Emissão de recibo', 'quitter'),
  ( 5, 'Contatos de emergência', 'emergency');

ALTER SEQUENCE erp.systemactions_systemactionid_seq RESTART WITH 6;

-- ---------------------------------------------------------------------
-- Os tipos de eventos para envio de um e-mail
-- ---------------------------------------------------------------------
-- Os tipos de eventos que originam a necessidade do envio de e-mail.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.mailEvents (
  mailEventID serial,       -- ID do tipo de evento
  name        varchar(50)   -- O nome do evento
              NOT NULL,
  priority    smallint      -- A prioridade para o envio:
              NOT NULL      -- 1: Prioritária, 2: Normal, 3: Baixa
              DEFAULT 2,
  PRIMARY KEY (mailEventID)
);

INSERT INTO erp.mailEvents (mailEventID, name, priority) VALUES
  (1, 'Envio de boleto de cobrança', 2),
  (2, 'Aviso de boleto a vencer', 3),
  (3, 'Aviso de boleto vencido', 3),
  (4, 'Envio de recibo de pagamento', 2),
  (5, 'Mensagem de aviso', 4),
  (6, 'Aviso de equipamento sem comunicação', 3),
  (7, 'Envio de código de confirmação de conta', 1);

ALTER SEQUENCE erp.mailevents_maileventid_seq RESTART WITH 8;

-- ---------------------------------------------------------------------
-- Tipos de posse de um dispositivo
-- ---------------------------------------------------------------------
-- As condições da propriedade (ou posse) de um dispositivo, seja ele um
-- equipamento ou Sim/Card.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.ownershipTypes (
  ownershipTypeID   serial,      -- O ID do tipo de propriedade
  name              varchar(20)  -- O nome do tipo da propriedade
                    NOT NULL,
  PRIMARY KEY (ownershipTypeID)
);

INSERT INTO erp.ownershipTypes (name) VALUES
  ('Adquirido'),
  ('Comodato');

-- ---------------------------------------------------------------------
-- Estado possíveis para uma ordem de serviço
-- ---------------------------------------------------------------------
CREATE TYPE OrderState AS ENUM('Registered', 'Scheduled', 'Closed',
  'ClosedWithPending', 'Cancelled');

-- ---------------------------------------------------------------------
-- Tipos de encerramentos possíveis para uma ordem de serviço
-- ---------------------------------------------------------------------
CREATE TYPE ClosingState AS ENUM('ToPerform', 'Performed', 'NotPerformed',
  'VisitNotPerformed', 'FailedVisit', 'UnproductiveVisit');
