-- Acrescentamos a informação de suporte à interface RS232 no modelo de
-- equipamento, nor permitindo adicionar periféricos, se necessário
ALTER TABLE erp.equipmentModels
  ADD COLUMN hasRS232Interface boolean DEFAULT false;

-- Acrescentamos a informação de telefones adicionais.

-- ---------------------------------------------------------------------
-- Telefones adicionais por unidade/filial
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones adicionais por unidade/filial.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.phones (
  phoneID       serial,        -- O ID do telefone
  entityID      integer        -- O ID da entidade à qual pertence este
                NOT NULL,      -- telefone
  subsidiaryID  integer        -- O ID da unidade/filial  à qual pertence
                NOT NULL,      -- este telefone
  phoneTypeID   integer        -- O ID do tipo de telefone
                NOT NULL,
  phoneNumber   varchar(20)    -- O número do telefone
                NOT NULL,
  PRIMARY KEY (phoneID),
  FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE RESTRICT,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

-- Acrescentamos novamente o campo de e-mail
ALTER TABLE erp.subsidiaries
  ADD COLUMN email varchar(100);

-- Retiramos o telefone da unidade/filial, pois agora eles estarão na
-- tabela de telefones
ALTER TABLE erp.subsidiaries
  DROP COLUMN phoneNumber;
