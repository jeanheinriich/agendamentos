-- =====================================================================
-- INCLUSÃO DA DATA/HORA DE ÚLTIMA COMUNICAÇÃO DE UM EQUIPAMENTO
-- =====================================================================
-- Esta modificação visa incluir um aprimoramento no cadastro de
-- equipamentos ao incluir a data/hora da última comunicação do
-- dipositivo, permitindo análises de equipamentos que não comunicam à
-- um certo tempo.
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- Alterações no cadastro de equipamentos
-- ---------------------------------------------------------------------

-- 1. Acrescentamos a coluna que armazena a data/hora da última
-- comunicação
ALTER TABLE erp.equipments
  ADD COLUMN lastCommunication timestamp DEFAULT NULL;

-- 2. Acrescentamos as colunas para permitir desabilitar o envio de
--    mensagens de avisos em um veículo
ALTER TABLE erp.vehicles
  ADD COLUMN blockNotices boolean DEFAULT false;
ALTER TABLE erp.vehicles
  ADD COLUMN blockedDays integer DEFAULT NULL;
ALTER TABLE erp.vehicles
  ADD COLUMN remainingDays integer DEFAULT 0;
ALTER TABLE erp.vehicles
  ADD COLUMN blockedStartAt date DEFAULT NULL;
ALTER TABLE erp.vehicles
  ADD COLUMN blockedEndAt date DEFAULT NULL;


INSERT INTO erp.permissions (permissionID, name, description) VALUES
  ( 428, 'ERP\Cadastre\Vehicles\Get\MailData',
    'Recupera as informações de e-mails enviados de um veículo')
;

INSERT INTO erp.permissionsPerGroups (groupID, permissionID, httpMethod)
  (SELECT groupID, permissionID, method::httpMethod
     FROM generate_series(1,6) x(groupID),
          (VALUES (428, 'PATCH')) y(permissionID, method));

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
             AND queue.requestedAt BETWEEN equipment.installedAt AND equipment.uninstalledat
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

-- Atualizamos a função de obtenção de lista de telefones
DROP FUNCTION erp.getBillingPhoneList(FcontractorID integer,
  FphoneType integer, overdue boolean, sentToDunningBureau boolean,
  FamountOfVehicles integer);
DROP TYPE erp.billingContactData;

-- ---------------------------------------------------------------------
-- Obtém a relação de dados de contato de cobranças em aberto
-- ---------------------------------------------------------------------
-- Stored Procedure que obtém os dados de clientes com valores em aberto
-- e seus respectivos telefones de contato.
-- ---------------------------------------------------------------------
CREATE TYPE erp.billingContactData AS
(
  name         varchar(100),
  sequence     integer,
  phoneType    varchar(20),
  phoneNumber  varchar(16),
  comment      varchar(100)
);

CREATE OR REPLACE FUNCTION erp.getBillingPhoneList(FcontractorID integer,
  FphoneType integer, overdue boolean, sentToDunningBureau boolean,
  FamountOfVehicles integer, Ftype integer)
RETURNS SETOF erp.billingContactData AS
$$
DECLARE
  customer  record;
  phone  record;
  phoneData  erp.billingContactData%rowtype;
  restrictionFilter  integer;
  lastSequence  integer;
  query  varchar;
BEGIN
  IF (FphoneType IS NULL) THEN
    FphoneType := 0;
  END IF;
  IF (Ftype IS NULL) THEN
    Ftype := 1;
  END IF;

  IF (Ftype = 1) THEN
    IF (overdue) THEN
      -- Obtemos a relação de clientes que possuem valores em aberto à
      -- pelo menos 3 dias
      IF (sentToDunningBureau) THEN
        restrictionFilter := 1;
      ELSE
        restrictionFilter := 0;
      END IF;

      -- Monta a consulta
      query := format('
        SELECT C.name,
               I.customerID AS id,
               I.subsidiaryID,
               '''' AS comment
          FROM erp.payments AS P
         INNER JOIN erp.invoices AS I USING (invoiceID)
         INNER JOIN erp.entities AS C ON (I.customerID = C.entityID)
         WHERE P.paymentSituationID = 1
           AND P.dueDate < (CURRENT_DATE - interval ''3 days'')
           AND (P.restrictionid >> 2) & 1 = %s
           AND P.contractorID = %s
         GROUP BY name, customerID, subsidiaryID
         ORDER BY name, customerID, subsidiaryID',
         restrictionFilter, FcontractorID);
    ELSE
      -- Obtemos a relação de clientes que possuem veículos ativos

      -- Monta a consulta
      query := format('
        WITH customers AS (
              SELECT E.name,
                     CT.customerID,
                     CT.subsidiaryID
                FROM erp.contracts AS CT
               INNER JOIN erp.entities AS E ON (CT.customerID = E.entityID)
               WHERE CT.contractorID = %s
                 AND CT.active
                 AND CT.endDate IS NULL
                 AND E.entitytypeid IN (1, 2)
               GROUP BY E.name, CT.customerID, CT.subsidiaryID
            )
          SELECT C.name,
                 C.customerID AS id,
                 C.subsidiaryID,
                 COUNT(DISTINCT V.vehicleID) AS amountOfActiveVehicles,
                 '''' AS comment
            FROM customers AS C
           INNER JOIN erp.vehicles AS V ON (C.customerID = V.customerID)
           INNER JOIN erp.equipments AS E ON (V.vehicleID = E.vehicleID AND E.storageLocation = ''Installed'' AND E.customerPayerID = C.customerID)
           GROUP BY C.name, C.customerID, C.subsidiaryID
          HAVING COUNT(*) >= %s
           ORDER BY C.name, C.customerID;',
        FcontractorID, FamountOfVehicles);
    END IF;
  ELSIF (Ftype = 2) THEN
    -- Obtemos a relação de associados ativos

    -- Monta a consulta
    query := format('
      SELECT affiliated.name,
             affiliation.customerID AS id,
             affiliation.subsidiaryID,
             association.name AS comment
        FROM erp.entities AS association
       INNER JOIN erp.subsidiaries AS associationUnity ON (association.entityID = associationUnity.entityID)
       INNER JOIN (SELECT DISTINCT associationID,
                          associationUnityID,
                          customerID,
                          subsidiaryID
                     FROM erp.affiliations
                    WHERE unjoinedAt IS NULL) AS affiliation
          ON (association.entityID = affiliation.associationID AND associationUnity.subsidiaryID = associationUnityID)
       INNER JOIN erp.entities AS affiliated
          ON (affiliation.customerID = affiliated.entityID)
       INNER JOIN erp.subsidiaries AS affiliatedUnity
          ON (affiliated.entityID = affiliatedUnity.entityID AND affiliation.subsidiaryID = affiliatedUnity.subsidiaryID)
       WHERE association.customer = true
         AND association.contractorID = %s;',
      FcontractorID)
    ;
  ELSE
    -- Obtemos a relação de clientes com veículos que possuam rastreador
    -- com falha de comunicação a pelo menos 48h;

    -- Monta a consulta
    query := format('
      WITH equipmentsWithoutCommunication AS (
        SELECT vehicle.customerID,
               vehicle.subsidiaryID,
               customer.name AS customerName,
               equipment.customerPayerID,
               equipment.subsidiaryPayerID,
               CASE
                 WHEN vehicle.customerID <> equipment.customerPayerID THEN payer.name
                 ELSE ''''
               END AS payerName
          FROM erp.equipments AS equipment
         INNER JOIN erp.vehicles AS vehicle USING (vehicleID)
         INNER JOIN erp.installations AS item USING (installationID)
         INNER JOIN erp.contracts AS contract USING (contractID)
         INNER JOIN erp.entities AS payer ON (equipment.customerPayerID = payer.entityID)
         INNER JOIN erp.entities AS customer ON (vehicle.customerID = customer.entityID)
         WHERE equipment.contractorID = %s
           AND equipment.storageLocation = ''Installed''
           AND (equipment.lastCommunication < CURRENT_DATE - interval ''48h'')
           AND item.enddate IS NULL
           AND contract.active = TRUE
           AND contract.enddate IS NULL
           AND payer.blocked = FALSE
           AND customer.blocked = FALSE
           AND vehicle.blocked = FALSE
           AND vehicle.blockNotices = FALSE
      ) 
      SELECT customerName AS name,
             customerID AS id,
             subsidiaryID,
             payerName AS comment
        FROM equipmentsWithoutCommunication
       GROUP BY customerID, subsidiaryID, customerName, customerPayerID, subsidiaryPayerID, payerName;',
      FcontractorID)
    ;
  END IF;
  -- RAISE NOTICE 'SQL: %', query;

  -- Recupera os nomes dos clientes com faturas em atraso que estejam à
  -- receber e que não estejam em uma agência de cobrança
  FOR customer IN
    EXECUTE query
  LOOP
    phoneData.name := customer.name;
    phoneData.comment := customer.comment;
    lastSequence   := 0;

    -- Para este cliente, recupera os telefones principais
    FOR phone IN
      SELECT P.phoneNumber AS number,
             T.name AS type,
             ROW_NUMBER () OVER (ORDER BY P.entityID) AS sequence
        FROM erp.phones AS P
       INNER JOIN erp.phoneTypes AS T USING (phoneTypeID)
       WHERE P.entityID = customer.id
         AND P.subsidiaryID = customer.subsidiaryID
         AND P.phoneTypeID = CASE
                               WHEN FphoneType > 0 THEN FphoneType
                               ELSE P.phoneTypeID
                             END
       ORDER BY P.phoneid
    LOOP
      lastSequence          := phone.sequence;
      phoneData.sequence    := phone.sequence;
      phoneData.phoneType   := phone.type;
      phoneData.phoneNumber := phone.number;

      RETURN NEXT phoneData;
    END LOOP;

    -- Agora recupera os telefones de contatos adicionais
    FOR phone IN
      SELECT DISTINCT ON (M.mailingAddressID) mailingAddressID,
             M.phoneNumber AS number,
             T.name AS type,
             ROW_NUMBER () OVER (ORDER BY M.entityID) AS sequence
        FROM erp.mailingAddresses AS M
       INNER JOIN erp.phoneTypes AS T USING (phoneTypeID)
       INNER JOIN erp.actionsPerProfiles AS A USING (mailingProfileID)
       WHERE M.entityID = customer.id
         AND M.subsidiaryID = customer.subsidiaryID
         AND CASE
               WHEN overdue THEN A.systemActionID IN (3, 4)
               ELSE A.systemActionID IN (1, 2, 3, 4, 5)
             END
         AND coalesce(M.phoneNumber, '') <> ''
         AND M.phoneTypeID = CASE
                               WHEN FphoneType > 0 THEN FphoneType
                               ELSE M.phoneTypeID
                             END
       ORDER BY M.mailingAddressID
    LOOP
      phoneData.sequence    := lastSequence + phone.sequence;
      phoneData.phoneType   := phone.type;
      phoneData.phoneNumber := phone.number;

      RETURN NEXT phoneData;
    END LOOP;
  END LOOP;
END
$$
LANGUAGE 'plpgsql';
