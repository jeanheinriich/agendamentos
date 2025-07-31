-- =====================================================================
-- Formulários
-- =====================================================================
-- Armazena as informações de formulários dinâmicos. Formulários são
-- utilizados para, por exemplo, conferência do técnico na conclusão de
-- um atendimento no cliente.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Tipos de valores armazenados no campo
-- ---------------------------------------------------------------------
-- Os tipos de valores que um campo pode armazenar
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.valueTypes (
  valueTypeID   serial,        -- Número de identificação do tipo de valor
  name          varchar(20)    -- O nome deste tipo de valor
                NOT NULL,
  type          varchar(10),   -- O tipo da variável no PHP
  fractional    boolean        -- O indicativo de que o valor possui
                DEFAULT false, -- casas decimais
  UNIQUE (name),
  PRIMARY KEY (valueTypeID)    -- O indice primário
);

INSERT INTO erp.valueTypes (valueTypeID, name, type, fractional) VALUES
  (1, 'Boleano', 'bool', false),
  (2, 'Inteiro', 'int', false),
  (3, 'Real', 'double', true),
  (4, 'Texto', 'string', false),
  (5, 'Matriz', 'array', false);

ALTER SEQUENCE erp.valuetypes_valuetypeid_seq RESTART WITH 6;

-- ---------------------------------------------------------------------
-- Tipos de campos do formulário de conferência
-- ---------------------------------------------------------------------
-- Os tipos de campos que podem ser utilizados
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.fieldTypes (
  fieldTypeID   serial,        -- Número de identificação do tipo de campo
  name          varchar(20)    -- O nome deste tipo de campo
                NOT NULL,
  comments      varchar(100),  -- Uma descrição do campo
  fieldClass    varchar(20)    -- O nome da classe que cria este tipo
                NOT NULL,      -- de campo
  valueTypeID   integer        -- ID do tipo de valor aceito
                NOT NULL,
  fixedsize     smallint       -- O tamanho do campo quando este
                NOT NULL       -- tiver um tamanho fixo
                DEFAULT 0,
  decimalPlaces smallint       -- O número de casas decimais
                NOT NULL
                DEFAULT 0,
  mask          varchar(10),   -- A máscara a ser utilizada
  UNIQUE (name),
  PRIMARY KEY (fieldTypeID),   -- O indice primário
  FOREIGN KEY (valueTypeID)
    REFERENCES erp.valueTypes(valueTypeID)
    ON DELETE CASCADE
);

INSERT INTO erp.fieldTypes (name, comments, fieldClass, valueTypeID, fixedsize, decimalPlaces, mask) VALUES
  ('Data', 'Um campo para entrada de datas', 'DateInput', 4, 10, 0, 'date'),
  ('Hora', 'Um campo para entrada de horas', 'TimeInput', 4, 8, 0, 'time'),
  ('E-mail', 'Um campo para entrada de endereços de e-mail', 'EmailInput', 4, 0, 0, ''),
  ('Oculto', 'Um campo para armazenamento de valores ocultos', 'HiddenInput', 4, 0, 0, ''),
  ('Radio', 'Um campo para criar seleções na forma de botões de rádio, onde apenas uma opção é válida', 'RadioInput', 5, 0, 0, ''),
  ('Seleção', 'Um campo para seleção de valores na forma de uma caixa de seleção', 'Select', 5, 0, 0, ''),
  ('Senha', 'Um campo para entrada de senha', 'PasswordInput', 4, 0, 0, ''),
  ('Texto', 'Um campo para entrada de textos', 'TextInput', 4, 0, 0, ''),
  ('Verificação', 'Um campo de verificação', 'Checkbox', 1, 0, 0, '');
