-- =====================================================================
-- INFORMAÇÕES COMPLEMENTARES DE DOCUMENTAÇAO
-- =====================================================================
-- Tabelas utilizada a nível de cadastro em diversas partes.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Tipos de Documentos
-- ---------------------------------------------------------------------
-- Os tipos de documentos válidos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.documentTypes (
  documentTypeID   serial,      -- ID do tipo de documento
  name             varchar(30)  -- Nome do tipo de documento
                   NOT NULL,
  juridicalperson  boolean      -- Flag indicador de documento para
                   NOT NULL,    -- pessoa jurídica
  PRIMARY KEY (documentTypeID)
);

INSERT INTO erp.documentTypes (documentTypeID, name, juridicalperson) VALUES
  ( 1, 'RG', false),
  ( 2, 'Certidão de nascimento', false),
  ( 3, 'RNE', false),
  ( 4, 'Inscrição Estadual', true),
  --( 5, 'Registro Acadêmico (RA) ', false),
  ( 6, 'Inscrição Rural (RA) ', false);

ALTER SEQUENCE erp.documenttypes_documenttypeid_seq RESTART WITH 7;

-- ---------------------------------------------------------------------
-- Estado Civil
-- ---------------------------------------------------------------------
-- Os estados civís válidos.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.maritalStatus (
  maritalStatusID  serial,      -- ID do tipo de estado civíl
  name             varchar(30)  -- Nome do estado civil
                   NOT NULL,
  PRIMARY KEY (maritalStatusID),
  UNIQUE(name)
);

INSERT INTO erp.maritalStatus (maritalStatusID, name) VALUES
  ( 1, 'Não informado'),
  ( 2, 'Solteiro'),
  ( 3, 'Casado'),
  ( 4, 'União Estável'),
  ( 5, 'Separado Judicialmente'),
  ( 6, 'Viúvo'),
  ( 7, 'Divorciado');

ALTER SEQUENCE erp.maritalstatus_maritalstatusid_seq RESTART WITH 8;

-- ---------------------------------------------------------------------
-- Gêneros
-- ---------------------------------------------------------------------
-- Os tipos de gêneros (sexos) válidos para efeito de cadastro.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.genders (
  genderID  serial,      -- ID do gênero
  name      varchar(30)  -- Nome do gênero
            NOT NULL,
  PRIMARY KEY (genderID),
  UNIQUE(name)
);

INSERT INTO erp.genders (genderID, name) VALUES
  ( 1, 'Não informado'),
  ( 2, 'Masculino'),
  ( 3, 'Feminino');

ALTER SEQUENCE erp.genders_genderid_seq RESTART WITH 4;

-- ---------------------------------------------------------------------
-- Posição do símbolo de medida
-- ---------------------------------------------------------------------
-- As posições possíveis são: START (Início), END (Fim).
-- ---------------------------------------------------------------------
CREATE TYPE SymbolPosition AS ENUM('START', 'END');

-- ---------------------------------------------------------------------
-- Tipos de medidas de um valor
-- ---------------------------------------------------------------------
-- Armazena as informações de tipos de medidas de um valor.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.measureTypes (
  measureTypeID   serial,         -- ID do tipo de medida
  name            varchar(30)     -- Nome do tipo de medida
                  NOT NULL,
  symbol          varchar(3)      -- Símbolo do tipo de medida
                  NOT NULL,       -- A posição do símbolo da medida em
  position        SymbolPosition  -- em relação ao valor
                  DEFAULT 'START',
  PRIMARY KEY (measureTypeID)
);

-- Insere os tipos de medidas padrões
INSERT INTO erp.measureTypes (measureTypeID, name, symbol) VALUES
  ( 1, 'Valor', 'R$'),
  ( 2, 'Porcentagem', '%');

ALTER SEQUENCE erp.measuretypes_measuretypeid_seq RESTART WITH 3;
