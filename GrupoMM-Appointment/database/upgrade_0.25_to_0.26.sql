-- =====================================================================
-- INCLUSÃO DE CAMPOS DE TELEFONES ADICIONAIS PARA O VEÍCULO
-- =====================================================================
-- Esta modificação visa incluir campos de telefone para o proprietário
-- do veículo quando o mesmo não for o próprio cliente e outro para o
-- local onde o veículo permanece quando este não for a sede do cliente
-- ou do proprietário do veículo
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- Telefones do proprietário de um veículo
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones do proprietário de um veículo
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.ownerPhones (
  ownerPhoneID  serial,        -- O ID do telefone
  vehicleID     integer        -- O ID do veículo
                NOT NULL,
  phoneTypeID   integer        -- O ID do tipo de telefone
                NOT NULL,
  phoneNumber   varchar(20)    -- O número do telefone
                NOT NULL,
  PRIMARY KEY (ownerPhoneID),
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Telefones do endereço onde o veículo permanece
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones do local onde o veículo permanece
-- a maior parte do tempo, e que não é o mesmo do cliente ou o do
-- proprietário do veículo
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.anotherPhones (
  anotherPhoneID  serial,        -- O ID do telefone
  vehicleID       integer        -- O ID do veículo
                  NOT NULL,
  phoneTypeID     integer        -- O ID do tipo de telefone
                  NOT NULL,
  phoneNumber     varchar(20)    -- O número do telefone
                  NOT NULL,
  PRIMARY KEY (anotherPhoneID),
  FOREIGN KEY (vehicleID)
    REFERENCES erp.vehicles(vehicleID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Dados de telefones de acordo com o perfil
-- ---------------------------------------------------------------------
-- Alteramos a função que recupera os telefones de acordo com um perfil
-- a ser usado para retornar em forma de matriz
-- ---------------------------------------------------------------------

-- Apagamos a função antiga
DROP FUNCTION erp.getPhones(FcontractorID integer,
  FentityID integer, FsubsidiaryID integer, FsystemActionID integer);

-- Recriamos com a modificação
CREATE OR REPLACE FUNCTION erp.getPhones(FcontractorID integer,
  FentityID integer, FsubsidiaryID integer, FsystemActionID integer)
  RETURNS varchar[] AS
$$
DECLARE
  subsidiaryFilter  varchar;
  query  varchar;
  address  record;
  phones  varchar[];
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FentityID IS NULL) THEN
    FentityID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FsystemActionID IS NULL) THEN
    FsystemActionID = 0;
  END IF;

  -- Realiza a filtragem por unidade/filial
  IF (FsubsidiaryID > 0) THEN
    subsidiaryFilter := format(' AND S.subsidiaryID = %s', FsubsidiaryID);
  ELSE
    subsidiaryFilter := '';
  END IF;

  -- Selecionamos primeiramente os telefones principais
  query := format('
    SELECT p.phonenumber
      FROM erp.entities AS E
     INNER JOIN erp.subsidiaries AS S USING (entityID)
     INNER JOIN erp.phones AS P USING (subsidiaryID)
     WHERE E.entityID = %s %s
     ORDER BY S.subsidiaryid, P.phoneid;',
     FentityID, subsidiaryFilter
  );
  FOR address IN EXECUTE query
  LOOP
    -- Adicionamos o número de telefone a nossa relação de telefones
    -- RAISE NOTICE 'Telefone: %', address.phonenumber;
    phones := phones || Array[address.phonenumber];
  END LOOP;

  -- Agora selecionamos os telefones adicionais
  query := format('
    SELECT M.phonenumber
      FROM erp.entities AS E
     INNER JOIN erp.subsidiaries AS S USING (entityID)
     INNER JOIN erp.mailingAddresses AS M USING (subsidiaryID)
     INNER JOIN erp.actionsPerProfiles AS A USING (mailingProfileID)
     WHERE E.entityID = %s %s
       AND A.systemActionID = %s
       AND coalesce(M.phonenumber, '''') <> ''''
     ORDER BY S.subsidiaryid, M.mailingAddressID;',
     FentityID, subsidiaryFilter, FsystemActionID
  );

  FOR address IN EXECUTE query
  LOOP
    -- Adicionamos o número de telefone a nossa relação de telefones
    -- RAISE NOTICE 'Telefone: %', address.phonenumber;
    phones := phones || Array[address.phonenumber];
  END LOOP;
  
  RETURN phones;
END;
$$ LANGUAGE 'plpgsql';
