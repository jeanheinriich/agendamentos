-- =====================================================================
-- Controle de e-mails enviados
-- =====================================================================
-- O controle das operações de envio de e-mais para os clientes.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Dados de endereços de e-mails de acordo com o perfil
-- ---------------------------------------------------------------------
-- Função que recupera os endereços de e-mail de acordo com um perfil a
-- ser usado em formato JSON
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getMails(FcontractorID integer,
  FentityID integer, FsubsidiaryID integer, FsystemActionID integer)
  RETURNS json AS
$$
DECLARE
  subsidiaryFilter  varchar;
  query  varchar;
  address  record;
  mailings  json[];
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

  -- Selecionamos primeiramente os e-mails principais
  query := format('
    SELECT M.email,
           CASE
             WHEN E.entityTypeID = 2 THEN S.name
             ELSE E.name
           END AS name
      FROM erp.entities AS E
     INNER JOIN erp.subsidiaries AS S USING (entityID)
     INNER JOIN erp.mailings AS M USING (subsidiaryID)
     WHERE E.entityID = %s %s
     ORDER BY S.subsidiaryid, M.mailingid;',
     FentityID, subsidiaryFilter
  );
  FOR address IN EXECUTE query
  LOOP
    -- Adicionamos o endereço de e-mail a nossa relação de e-mails
    -- RAISE NOTICE 'Endereço: % %', address.email, address.name;
    mailings := mailings || Array[format('{"email":"%s","name":"%s"}', address.email, address.name)::json];
  END LOOP;

  -- Agora selecionamos os e-mails adicionais
  query := format('
    SELECT M.email,
           M.name
      FROM erp.entities AS E
     INNER JOIN erp.subsidiaries AS S USING (entityID)
     INNER JOIN erp.mailingAddresses AS M USING (subsidiaryID)
     INNER JOIN erp.actionsPerProfiles AS A USING (mailingProfileID)
     WHERE E.entityID = %s %s
       AND A.systemActionID = %s
       AND coalesce(M.email, '''') <> ''''
     ORDER BY S.subsidiaryid, M.mailingAddressID;',
     FentityID, subsidiaryFilter, FsystemActionID
  );

  FOR address IN EXECUTE query
  LOOP
    -- Adicionamos o endereço de e-mail a nossa relação de e-mails
    -- RAISE NOTICE 'Endereço: % %', address.email, address.name;
    mailings := mailings || Array[format('{"email":"%s","name":"%s"}', address.email, address.name)::json];
  END LOOP;
  
  RETURN array_to_json(mailings);
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- A fila de e-mails
-- ---------------------------------------------------------------------
-- Uma fila de e-mails que precisam ser enviados. Sempre que necessário,
-- um registro é adicionado nesta tabela quando um e-mail precisa ser
-- enviado, e possa ocorrer em segundo-plano, liberando a interface
-- imediatamente sem que o usuário necessite aguardar o envio.
-- Posteriormente uma tarefa se encarrega por recuperar este registro e
-- realizar o devido envio, registrando o sucesso (ou não) deste envio.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.emailsQueue (
  queueID           serial,       -- ID do registro na fila
  contractorID      integer       -- ID do contratante
                    NOT NULL,
  mailEventID       integer       -- ID do evento que originou o envio
                    NOT NULL,
  originRecordID    integer       -- ID do registro que originou o envio
                    NOT NULL,
  requestedAt       timestamp     -- Data/hora da requisição
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  sentTo            text,         -- Os endereços do(s) destinatário(s)
  sentStatus        integer       -- O indicativo do resultado do envio:
                    NOT NULL      -- 0: Aguardando envio, 1: enviado
                    DEFAULT 0,    -- 2: erro ao enviar
  attempts          smallint      -- O número de tentativa de envio
                    NOT NULL
                    DEFAULT 0,
  statusAt          timestamp     -- Data/hora do resultado do envio
                    DEFAULT NULL,
  recordsOnScope    int[],        -- Os registros incluídos no envio
  reasons           text          -- O motivo em caso de falha no envio
                    DEFAULT NULL,
  PRIMARY KEY (queueID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (mailEventID)
    REFERENCES erp.mailEvents(mailEventID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Recupera os estados dos e-mails na fila
-- ---------------------------------------------------------------------
-- Função que recupera os dados dos envios de e-mail que tenham relação
-- com um determinado pagamento.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getMailStatus(FpaymentID integer)
  RETURNS SETOF jsonb AS
$$
DECLARE
  -- Os dados dos e-mails
  mail  record;
  scope  int[];

  -- O resultado do envio
  statusContent  jsonb;
BEGIN
  -- Inicializamos o controle do status
  statusContent := jsonb '{"0":0,"1":0,"2":0}';
  scope := array_append('{}', FpaymentID);

  -- Recupera as informações dos emails
  FOR mail IN
    SELECT count(*) AS amount,
           sentStatus
      FROM erp.emailsQueue
     WHERE recordsonscope @> scope
     GROUP BY sentStatus
  LOOP
    -- Adicionamos as informações de envios
    statusContent := jsonb_set(statusContent, string_to_array(mail.sentStatus::text, null), to_jsonb(mail.amount), true);
  END LOOP;

  RETURN NEXT statusContent;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Recupera os estados dos e-mails na fila para um veículo
-- ---------------------------------------------------------------------
-- Função que recupera os dados dos envios de e-mail que tenham relação
-- com um determinado veículo de cliente.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getMailStatusForVehicle(
  FcontractorID integer, FcustomerID integer, FvehicleID integer)
  RETURNS SETOF jsonb AS
$$
DECLARE
  -- Os dados dos e-mails
  mail  record;
  scope  int[];

  -- O resultado do envio
  statusContent  jsonb;
BEGIN
  -- Inicializamos o controle do status
  statusContent := jsonb '{"0":0,"1":0,"2":0}';

  -- Recupera as informações dos emails
  FOR mail IN
    WITH installedEquipments AS (
      SELECT equipmentID,
             installedAt,
             COALESCE(uninstalledAt, CURRENT_DATE) AS uninstalledAt
        FROM erp.installationrecords
       WHERE vehicleID = FvehicleID
         AND contractorID = FcontractorID
    )
    SELECT count(*) AS amount,
           queue.sentStatus
      FROM installedEquipments AS equipment
     INNER JOIN erp.emailsqueue AS queue
        ON (
             queue.recordsonscope @> array_prepend(equipment.equipmentID, '{}'::int[])
             AND DATE(queue.requestedAt) BETWEEN equipment.installedAt AND equipment.uninstalledat
             AND queue.originRecordID = FcustomerID
           )
     GROUP BY queue.sentStatus
  LOOP
    -- Adicionamos as informações de envios
    statusContent := jsonb_set(statusContent, string_to_array(mail.sentStatus::text, null), to_jsonb(mail.amount), true);
  END LOOP;

  RETURN NEXT statusContent;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Recupera os provedores de uma relação de e-mails
-- ---------------------------------------------------------------------
-- Função que recupera os provedores de e-mail de uma lista de endereços
-- de e-mail.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION getProviders(adresses TEXT)
RETURNS text[] AS
$$
DECLARE
  providers text[];
  mailprovider text;
  mails text[];
  email text;
BEGIN
  -- Divide a lista de endereços de e-mail na virgula
  -- Exemplo: "name1@gmail.com, name2@hotmail.com" -> ["name1@gmail.com", "name2@hotmail.com"]
  mails := string_to_array(adresses, ',');

  -- Para cada endereço de e-mail, extrai o provedor
  FOREACH email IN ARRAY mails
  LOOP
    -- Obtém o provedor do endereço de e-mail
    mailprovider := split_part(email, '@', 2);

    -- Adiciona o provedor à matriz
    providers := providers || ARRAY[mailprovider];
  END LOOP;

  RETURN providers;
END
$$
LANGUAGE 'plpgsql';
