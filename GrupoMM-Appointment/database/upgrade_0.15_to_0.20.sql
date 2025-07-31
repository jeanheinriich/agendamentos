-- =====================================================================
-- MODIFICAÇÃO DO CADASTRO DE CLIENTES
-- =====================================================================
-- Retirado a figura do associado e criada uma nova estrutura que
-- armazena os associados de uma cooperativa. Agora os cadastros de
-- clientes são únicos. Quando esta entidade é cliente direto, ela irá
-- possuir um contrato ativo. Quando possuir um ou mais veículos geridos
-- por uma cooperativa, ele aparece como associado. Quando não possuir
-- contratos ativos nem veículos rastreados, automaticamente ele passa
-- como inativo.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Afiliações
-- ---------------------------------------------------------------------
-- Armazena as informações de quais clientes possui vínculo com uma
-- associação (cooperativa), de forma que possamos exibir os afiliados
-- à uma associação corretamente.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.affiliations (
  affiliationID       serial,        -- O ID da afiliação
  associationID       integer        -- O ID da cooperativa
                      NOT NULL,
  associationUnityID  integer        -- O ID da unidade/filial da
                      NOT NULL,      -- cooperativa onde está vinculado
  customerID          integer        -- O ID do cliente associado
                      NOT NULL,
  subsidiaryID        integer        -- O ID da unidade/filial do
                      NOT NULL,      -- cliente
  joinedAt            date           -- A data da filiação
                      NOT NULL
                      DEFAULT CURRENT_DATE,
  unjoinedAt          date           -- A data da desfiliação
                      DEFAULT NULL,
  PRIMARY KEY (affiliationID),            -- O índice primário
  FOREIGN KEY (associationID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (associationUnityID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE RESTRICT
);

-- Acrescentamos a coluna de indicação de matriz/titular
ALTER TABLE erp.subsidiaries
  ADD COLUMN headOffice boolean DEFAULT FALSE;

-- ---------------------------------------------------------------------
-- Transforma associados em clientes
-- ---------------------------------------------------------------------
-- Função que transforma os associados antigos em clientes no novo
-- formato
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.transformAffiliatedInUser()
RETURNS boolean AS
$$
DECLARE
  affiliated  record;
  newCustomerID  integer;
BEGIN
  FOR affiliated IN
    SELECT C.contractorID,
           S.subsidiaryID AS id,
           S.entityID AS associationID,
           C.name AS associationName,
           S.name,
           CASE
             WHEN length(S.nationalregister) = 14 THEN 2
             ELSE 1
           END AS entityTypeID,
           S.blocked,
           S.createdat,
           S.createdbyuserid,
           S.updatedat,
           S.updatedbyuserid
      FROM erp.subsidiaries AS S
     INNER JOIN erp.entities AS C USING (entityid)
     WHERE S.affiliated = true
  LOOP
    -- Para cada associado, criamos um novo cadastro como cliente e
    -- transferimos estes dados para este cadastro

    -- Criamos um novo cadastro de cliente
    INSERT INTO erp.entities
              (contractorid, customer, supplier, serviceProvider, name,
               entitytypeid, blocked, createdat, createdbyuserid,
               updatedat, updatedbyuserid)
        VALUES (affiliated.contractorID, true, false, false,
               affiliated.name, affiliated.entityTypeID,
               affiliated.blocked, affiliated.createdat,
               affiliated.createdbyuserid, affiliated.updatedat,
               affiliated.updatedbyuserid)
     RETURNING entityid INTO newCustomerID;

    RAISE NOTICE 'Transformando o associado [%] % da associação % no cliente ID %', affiliated.id, affiliated.name, affiliated.associationName, newCustomerID;

    -- Transferimos a unidade/filial do associado para o cliente
    UPDATE erp.subsidiaries
      SET entityid = newCustomerID,
          headOffice = true
    WHERE subsidiaryid = affiliated.id;

    -- Transferimos os veículos do associado para o cliente
    UPDATE erp.vehicles
      SET customerid = newCustomerID
    WHERE subsidiaryid = affiliated.id;

    -- Atualizamos os dados de telefones e e-mails
    UPDATE erp.phones
      SET entityid = newCustomerID
    WHERE subsidiaryid = affiliated.id;
    UPDATE erp.mailings
      SET entityid = newCustomerID
    WHERE subsidiaryid = affiliated.id;
    UPDATE erp.mailingAddresses
      SET entityid = newCustomerID
    WHERE subsidiaryid = affiliated.id;
  END LOOP;

  -- Indica que tudo deu certo
  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- Retiramos a obrigatoriedade de tradingName
ALTER TABLE erp.entities
  ALTER COLUMN tradingName DROP NOT NULL;

-- Executamos a função e convertemos os associados em clientes
SELECT erp.transformAffiliatedInUser();

-- Removemos a função
DROP FUNCTION erp.transformAffiliatedInUser();

-- Criamos os registros de filiação para indicar quais clientes são
-- associados e estão ativos
INSERT INTO erp.affiliations
           (associationID, associationUnityID, customerID, subsidiaryID,
            joinedAt)
           (SELECT E.customerPayerID AS associationID,
                   E.subsidiaryPayerID AS associationUnityID,
                   V.customerID,
                   V.subsidiaryID,
                   E.installedAt AS joinedAt
              FROM erp.equipments AS E
             INNER JOIN erp.vehicles AS V USING (vehicleID)
             WHERE E.customerpayerID IN (SELECT C.entityID
                                           FROM erp.entities AS C
                                          WHERE C.customer = true
                                            AND C.entitytypeid = 3));

-- ---------------------------------------------------------------------
-- Popula os registros de vinculos anteriores de associados
-- ---------------------------------------------------------------------
-- Função que atualiza os vínculos antigos de associados
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.generateAssociationRecords()
RETURNS boolean AS
$$
DECLARE
  oldAffiliation  record;
  currentAffiliation  record;
  query  varchar;
  newCustomerID  integer;
BEGIN
  FOR oldAffiliation IN
    SELECT installation.customerID AS associationID,
           installation.subsidiaryID AS associationUnityID,
           vehicle.customerID,
           vehicle.subsidiaryID,
           max(record.installedAt) AS joinedAt,
           min(record.uninstalledAt) AS unjoinedAt
      FROM erp.installationrecords AS record
     INNER JOIN erp.vehicles AS vehicle USING (vehicleID)
     INNER JOIN erp.installations AS installation USING (installationID)
     INNER JOIN erp.entities AS association ON (installation.customerID = association.entityID AND association.entityTypeID = 3)
     INNER JOIN erp.entities AS customer ON (vehicle.customerID = customer.entityID)
     WHERE uninstalledAT IS NOT NULL
       AND installationID IS NOT NULL
     GROUP BY associationID, associationUnityID, vehicle.customerID, vehicle.subsidiaryID
     ORDER BY associationID, associationUnityID, vehicle.customerID, vehicle.subsidiaryID
  LOOP
    RAISE NOTICE 'Associação: %, Cliente: %', oldAffiliation.associationID, oldAffiliation.customerID;

    -- Para cada registro de afiliação, analisamos se o cliente já
    -- possui um registro de afiliado existente
    SELECT INTO currentAffiliation
           affiliationID AS ID,
           joinedAt,
           unjoinedAt
      FROM erp.affiliations
     WHERE associationID = oldAffiliation.associationID
       AND associationUnityID = oldAffiliation.associationUnityID
       AND customerID = oldAffiliation.customerID
       AND subsidiaryID = oldAffiliation.subsidiaryID;
    IF FOUND THEN
      -- Analisamos se existe um intervalo separando os períodos em que
      -- este cliente permaneceu associado
      RAISE NOTICE 'Já temos um registro [%]', currentAffiliation.ID;

      IF (currentAffiliation.joinedAt > oldAffiliation.unjoinedAt) THEN
        RAISE NOTICE 'O início do período atual % é maior do que o final do período antigo %', TO_CHAR(currentAffiliation.joinedAt, 'DD/MM/YYYY'), TO_CHAR(oldAffiliation.unjoinedAt, 'DD/MM/YYYY');
        IF (DATE_PART('day', currentAffiliation.joinedAt::timestamp - oldAffiliation.unjoinedAt::timestamp) > 10 ) THEN
          -- Criamos um registro separado, já que existiu um intervalo
          -- entre os registros superior a 10 dias
          RAISE NOTICE 'Ocorreu uma diferença de 10 mais de 10 dias';
          RAISE NOTICE 'Apenas insere';
          INSERT INTO erp.affiliations
                      (associationID, associationUnityID, customerID,
                       subsidiaryID, joinedAt, unjoinedAt)
               VALUES (oldAffiliation.associationID,
                       oldAffiliation.associationUnityID,
                       oldAffiliation.customerID,
                       oldAffiliation.subsidiaryID,
                       oldAffiliation.joinedAt,
                       oldAffiliation.unjoinedAt);
        ELSE
          -- Apenas atualizamos o registro existente
          RAISE NOTICE 'Ocorreu uma diferença menor do que 10 dias';
          RAISE NOTICE 'Atualiza o registro atual';
          UPDATE erp.affiliations
             SET joinedAt = CASE
                              WHEN joinedAt <= oldAffiliation.joinedAt THEN joinedAt
                              ELSE oldAffiliation.joinedAt
                            END,
                 unjoinedAt = CASE
                                WHEN unjoinedAt IS NULL THEN NULL
                                WHEN unjoinedAt >= oldAffiliation.unjoinedAt THEN unjoinedAt
                                ELSE oldAffiliation.unjoinedAt
                              END
           WHERE affiliationID = currentAffiliation.ID;
        END IF;
      ELSE
        RAISE NOTICE 'O início do período atual % menor ou igual ao final do período antigo %', TO_CHAR(currentAffiliation.joinedAt, 'DD/MM/YYYY'), TO_CHAR(oldAffiliation.unjoinedAt, 'DD/MM/YYYY');
        IF (currentAffiliation.unjoinedAt IS NULL) THEN
          RAISE NOTICE 'Atualizamos o período atual ativo';
          UPDATE erp.affiliations
             SET joinedAt = CASE
                              WHEN joinedAt <= oldAffiliation.joinedAt THEN joinedAt
                              ELSE oldAffiliation.joinedAt
                            END
           WHERE affiliationID = currentAffiliation.ID;
        ELSE
          RAISE NOTICE 'Atualizamos o período atual inativo';
          UPDATE erp.affiliations
             SET joinedAt = CASE
                              WHEN joinedAt <= oldAffiliation.joinedAt THEN joinedAt
                              ELSE oldAffiliation.joinedAt
                            END,
                 unjoinedAt = CASE
                                WHEN unjoinedAt IS NULL THEN NULL
                                WHEN unjoinedAt >= oldAffiliation.unjoinedAt THEN unjoinedAt
                                ELSE oldAffiliation.unjoinedAt
                              END
           WHERE affiliationID = currentAffiliation.ID;
        END IF;
      END IF;
    ELSE
      -- Não existe um registro, então insere
      RAISE NOTICE 'Não existe um registro, então insere';
      INSERT INTO erp.affiliations
                  (associationID, associationUnityID, customerID,
                   subsidiaryID, joinedAt, unjoinedAt)
           VALUES (oldAffiliation.associationID,
                   oldAffiliation.associationUnityID,
                   oldAffiliation.customerID,
                   oldAffiliation.subsidiaryID,
                   oldAffiliation.joinedAt,
                   oldAffiliation.unjoinedAt);
    END IF;
  END LOOP;

  -- Indica que tudo deu certo
  RETURN true;
END;
$$ LANGUAGE 'plpgsql';

-- Executamos a função e geramos os registros de vínculos
SELECT erp.generateAssociationRecords();

-- Removemos a função
DROP FUNCTION erp.generateAssociationRecords();

-- Removemos a coluna de afiliados
ALTER TABLE erp.subsidiaries
  DROP COLUMN affiliated;

-- Acrescentamos a coluna de novo afiliado
ALTER TABLE erp.entities
  ADD COLUMN newAffiliated boolean
      NOT NULL DEFAULT FALSE;

-- Alteramos a recuperação de dados das entidades para excluir a
-- informação de unidade/filial 'associado' e incluindo a informação de
-- unidade/filial 'Matriz'
DROP FUNCTION erp.getEntitiesData(FcontractorID integer,
  FentityID integer, Fgroup varchar, FsearchValue varchar(100),
  FsearchField varchar(20), Forder varchar, Skip integer, LimitOf integer);

DROP TYPE erp.entityData;

-- Criamos um novo tipo e função para recuperar os dados cadastrais de
-- uma entidade. Agora as informações são enviadas prontas para o
-- browser, eliminando porções de código que tornavam o processo mais
-- lento
CREATE TYPE erp.entityData AS
(
  entityID           integer,
  subsidiaryID       integer,
  affiliatedID       integer,
  juridicalperson    boolean,
  cooperative        boolean,
  serviceProvider    boolean,
  headOffice         boolean,
  type               smallint,
  level              smallint,
  active             boolean,
  activeAssociation  boolean,
  name               varchar(100),
  tradingName        varchar(100),
  blocked            boolean,
  cityID             integer,
  cityName           varchar(50),
  nationalregister   varchar(18),
  blockedLevel       smallint,
  createdAt          timestamp,
  updatedAt          timestamp,
  fullcount          integer
);

CREATE OR REPLACE FUNCTION erp.getEntitiesData(FcontractorID integer,
  FentityID integer, Fgroup varchar, FsearchValue varchar(100),
  FsearchField varchar(20), Forder varchar, Fstatus integer,
  Ftype integer, Skip integer, LimitOf integer)
RETURNS SETOF erp.entityData AS
$$
DECLARE
  entityData  erp.entityData%rowtype;
  row  record;
  query  varchar;
  field  varchar;
  filter  varchar;
  typeFilter  varchar;
  limits  varchar;
  blockedLevel  smallint;
  lastEntityID  integer;
  lastSubsidiaryID  integer;
  avoidAffiliateDuplicationFilter  varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID := 0;
  END IF;
  IF (FentityID IS NULL) THEN
    FentityID := 0;
  END IF;
  IF (Fgroup IS NULL) THEN
    Fgroup := 'contractor';
  END IF;
  IF (Fstatus IS NULL) THEN
    Fstatus := 0;
  END IF;
  IF (Ftype IS NULL) THEN
    Ftype := 0;
  END IF;
  IF (Forder IS NULL) THEN
    Forder := 'cooperative ASC, name, headOffice DESC, subsidiaryname, affiliatedname NULLS FIRST';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;

  -- O filtro para eliminar a duplicidade de cadastros de associados
  IF (Fgroup = 'customer') THEN
    avoidAffiliateDuplicationFilter := '( NOT((numberOfContracts = 0) AND (numberOfAffiliations > 0)) )';
  ELSE
    avoidAffiliateDuplicationFilter := '(numberOfContracts = 0)';
  END IF;

  -- Os estados possíveis são: (1) inativo e (2) ativo
  typeFilter := '';
  IF (Fstatus > 0) THEN
    IF (Fstatus = 1) THEN
      typeFilter := ' AND ' ||
        'CASE' ||
        '   WHEN affiliatedID IS NULL THEN (numberOfActiveContracts = 0)' ||
        '   ELSE (numberOfActiveAffiliations = 0)' ||
        ' END';
    ELSE
      typeFilter := ' AND ' ||
        'CASE' ||
        '   WHEN affiliatedID IS NULL THEN (numberOfActiveContracts > 0)' ||
        '   ELSE (numberOfActiveAffiliations > 0)' ||
        ' END';
    END IF;
  END IF;

  -- Os tipos possíveis são:
  --   (1) cliente, (2) associado
  IF (Ftype > 0) THEN
    IF (Ftype = 1) THEN
      typeFilter := typeFilter || ' AND ' ||
        '((numberOfContracts > 0) AND (affiliatedID IS NULL))';
    ELSE
      typeFilter := typeFilter || ' AND ' ||
        '((numberOfAffiliations > 0) AND (affiliatedID IS NOT NULL))';
    END IF;
  END IF;

  -- Realiza a filtragem por contratante
  IF (FcontractorID > 0) THEN
    filter := format(' AND entity.contractorID = %s',
                     FcontractorID);
  ELSE
    filter := format(' AND entity.contractorID >= %s',
                     FcontractorID);
  END IF;

  -- Realiza a filtragem por grupo
  filter := filter || format(' AND entity.%s = true', Fgroup);

  IF (FentityID > 0) THEN
    -- Realiza a filtragem por entidade
    filter := filter || format(' AND entity.entityID = %s', FentityID);
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- Determina o campo onde será realizada a pesquisa
      CASE (FsearchField)
        WHEN 'name' THEN
          filter := filter || ' AND (' ||
            format('public.unaccented(entity.name) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ' OR ' ||
            format('public.unaccented(entity.tradingName) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ' OR ' ||
            format('public.unaccented(unity.name) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ' OR ' ||
            format('public.unaccented(affiliationEntity.name) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ' OR ' ||
            format('public.unaccented(affiliationEntity.tradingName) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ' OR ' ||
            format('public.unaccented(affiliationUnity.name) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ')'
          ;
        WHEN 'nationalregister' THEN
          filter := filter || ' AND (' ||
            format('(regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%'')',
                   regexp_replace(FsearchValue, '\D*', '', 'g')) ||
            ' OR ' ||
            format('(regexp_replace(affiliationUnity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%'')',
                   regexp_replace(FsearchValue, '\D*', '', 'g')) ||
            ')'
          ;
        ELSE
          -- Monta o filtro
          field := 'entity.' || FsearchField;
          filter := filter || ' AND ' ||
            format('public.unaccented(%s) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   field, FsearchValue);
      END CASE;
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  -- Monta a consulta
  query := format('WITH items AS (
                     SELECT entity.entityID,
                            entity.contractor,
                            entity.customer,
                            entity.supplier,
                            entity.newAffiliated,
                            entity.name,
                            entity.tradingName,
                            entity.entityTypeID,
                            type.name AS entityTypeName,
                            type.juridicalperson AS juridicalperson,
                            type.cooperative AS cooperative,
                            entity.serviceProvider,
                            entity.blocked AS entityBlocked,
                            unity.subsidiaryID,
                            unity.headOffice,
                            unity.name AS subsidiaryName,
                            joint.customerID AS affiliatedID,
                            joint.joinedat,
                            affiliationEntity.name AS affiliatedName,
                            affiliationEntity.tradingName AS affiliatedTradingName,
                            affiliationEntity.blocked AS affiliatedBlocked,
                            unity.cityID,
                            city.name AS cityName,
                            affiliationUnity.cityID AS affiliatedCityID,
                            affiliationCity.name AS affiliatedCityName,
                            unity.nationalRegister,
                            affiliationUnity.nationalRegister AS affiliatedNationalRegister,
                            affiliationUnity.blocked AS affiliatedSubsidiaryBlocked,
                            unity.blocked AS subsidiaryBlocked,
                            entity.createdAt,
                            entity.updatedAt,
                            unity.createdAt AS unityCreatedAt,
                            unity.updatedAt AS unityUpdatedAt,
                            (
                              SELECT count(*)
                                FROM erp.contracts AS entityContract
                               WHERE entityContract.customerID = entity.entityID
                            ) AS numberOfContracts,
                            (
                              SELECT count(*)
                                FROM erp.contracts AS entityContract
                               WHERE entityContract.customerID = entity.entityID
                                 AND entityContract.endDate IS NULL
                            ) AS numberOfActiveContracts,
                            (
                              SELECT count(*)
                                FROM erp.affiliations
                               WHERE affiliations.associationid = entity.entityID
                            ) AS numberOfAssociates,
                            (
                              SELECT count(*)
                                FROM erp.affiliations
                               WHERE affiliations.associationid = entity.entityID
                                 AND affiliations.unjoinedat IS NULL
                            ) AS numberOfActiveAssociates,
                            (
                              SELECT count(*)
                                FROM erp.affiliations
                               WHERE affiliations.associationid = entity.entityID
                                 AND affiliations.customerid = affiliationEntity.entityID
                            ) AS numberOfAffiliations,
                            (
                              SELECT count(*)
                                FROM erp.affiliations
                               WHERE affiliations.associationid = entity.entityID
                                 AND affiliations.customerid = affiliationEntity.entityID
                                 AND affiliations.unjoinedat IS NULL
                            ) AS numberOfActiveAffiliations,
                            count(*) OVER(partition by entity.entityid) AS entityItems,
                            (SELECT count(*) FROM erp.subsidiaries AS S WHERE S.entityID = entity.entityid) AS unityItems
                       FROM erp.entities AS entity
                      INNER JOIN erp.entitiesTypes AS type ON (entity.entityTypeID = type.entityTypeID)
                      INNER JOIN erp.subsidiaries AS unity ON (entity.entityID = unity.entityID)
                      INNER JOIN erp.cities AS city ON (unity.cityID = city.cityID)
                       LEFT JOIN erp.affiliations AS joint ON (type.cooperative = true AND entity.entityID = joint.associationID AND unity.subsidiaryID = joint.associationUnityID)
                       LEFT JOIN erp.entities AS affiliationEntity ON (joint.customerID = affiliationEntity.entityID)
                       LEFT JOIN erp.subsidiaries AS affiliationUnity ON (joint.subsidiaryID = affiliationUnity.subsidiaryID)
                       LEFT JOIN erp.cities AS affiliationCity ON (affiliationUnity.cityID = affiliationCity.cityID)
                      WHERE entity.deleted = false
                        AND unity.deleted = false %s
                   )
                    SELECT *,
                           CASE
                             WHEN affiliatedID IS NULL THEN (numberOfActiveContracts > 0)
                             ELSE (numberOfActiveAffiliations > 0)
                           END AS active,
                           CASE
                             WHEN cooperative THEN (numberOfActiveAssociates > 0)
                             ELSE FALSE
                           END AS activeAssociation,
                           CASE
                             WHEN ((numberOfContracts > 0) AND (affiliatedID IS NULL)) THEN 1
                             WHEN ((numberOfAffiliations > 0) AND (affiliatedID IS NOT NULL)) THEN 2
                             ELSE 0
                           END AS type,
                           count(*) OVER() AS fullcount
                      FROM items
                     WHERE %s %s
                      ORDER BY %s %s',
                  filter, avoidAffiliateDuplicationFilter, typeFilter,
                  Forder, limits);
  -- RAISE NOTICE 'SQL: %',query;

  lastEntityID := 0;
  lastSubsidiaryID := 0;

  FOR row IN EXECUTE query
  LOOP
    IF (lastEntityID <> row.entityID) THEN
      -- Iniciamos um novo grupo
      lastEntityID := row.entityID;

      -- Verifica se precisamos dividir esta entidade em mais de uma
      -- linha
      IF (row.unityItems > 1) THEN
        -- Descrevemos aqui a entidade principal
        entityData.entityID            := row.entityID;
        entityData.subsidiaryID        := 0;
        entityData.affiliatedID        := 0;
        entityData.juridicalperson     := row.juridicalperson;
        entityData.cooperative         := row.cooperative;
        entityData.serviceProvider     := row.serviceProvider;
        entityData.headOffice          := false;
        entityData.type                := 1;
        entityData.level               := 0;
        IF (row.cooperative) THEN
          entityData.active            := row.activeAssociation;
        ELSE
          entityData.active            := row.active;
        END IF;
        entityData.activeAssociation   := row.activeAssociation;
        entityData.name                := row.name;
        entityData.tradingName         := row.tradingName;
        entityData.blocked             := row.entityBlocked;
        entityData.cityID              := 0;
        entityData.cityName            := '';
        entityData.nationalregister    := '';
        IF (row.entityBlocked) THEN
          entityData.blockedLevel      := 1;
        ELSE
          entityData.blockedLevel      := 0;
        END IF;
        entityData.createdAt           := row.createdAt;
        entityData.updatedAt           := row.updatedAt;
        entityData.fullcount           := row.fullcount;

        RETURN NEXT entityData;
      END IF;
    END IF;

    IF (lastSubsidiaryID <> row.subsidiaryID) THEN
      -- Iniciamos um novo subgrupo
      lastSubsidiaryID := row.subsidiaryID;

      -- Verifica se precisamos subdividir esta entidade em mais de uma
      -- linha
      IF ( row.cooperative ) THEN
        -- Temos que separar esta entidade em mais de uma linha de forma
        -- a exibir a unidade/filial desta entidade e/ou a cooperativa
        -- do associado
        
        -- Informa os dados da unidade
        entityData.entityID            := row.entityID;
        entityData.subsidiaryID        := row.subsidiaryID;
        entityData.affiliatedID        := 0;
        entityData.juridicalperson     := row.juridicalperson;
        entityData.cooperative         := row.cooperative;
        entityData.serviceProvider     := row.serviceProvider;
        entityData.headOffice          := row.headOffice;
        entityData.type                := 1;
        IF (row.unityItems > 1) THEN
          entityData.level             := 1;
        ELSE
          entityData.level             := 2;
        END IF;
        IF (row.cooperative) THEN
          entityData.active            := row.activeAssociation;
        ELSE
          entityData.active            := row.active;
        END IF;
        entityData.activeAssociation   := row.activeAssociation;
        IF (row.unityItems > 1) THEN
          entityData.blocked           := row.subsidiaryBlocked;
          entityData.name              := row.subsidiaryName;
          entityData.tradingName       := '';
        ELSE
          entityData.blocked           := (row.subsidiaryBlocked OR row.entityBlocked);
          entityData.name              := row.name;
          entityData.tradingName       := row.tradingName;
        END IF;
        entityData.cityID              := row.cityID;
        entityData.cityName            := row.cityName;
        entityData.nationalregister    := row.nationalregister;
        IF (row.entityBlocked) THEN
          entityData.blockedLevel      := 1;
        ELSE
          IF (row.subsidiaryBlocked) THEN
            entityData.blockedLevel    := 2;
          ELSE
            entityData.blockedLevel    := 0;
          END IF;
        END IF;
        IF (row.unityItems > 1) THEN
          entityData.createdAt         := row.unityCreatedAt;
          entityData.updatedAt         := row.unityUpdatedAt;
        ELSE
          entityData.createdAt         := row.createdAt;
          entityData.updatedAt         := row.updatedAt;
        END IF;
        entityData.fullcount           := row.fullcount;

        RETURN NEXT entityData;
      END IF;
    END IF;

    -- Informa os dados da entidade
    entityData.entityID              := row.entityID;
    entityData.subsidiaryID          := row.subsidiaryID;
    entityData.affiliatedID          := row.affiliatedID;
    entityData.juridicalperson       := row.juridicalperson;
    entityData.cooperative           := row.cooperative;
    entityData.serviceProvider       := row.serviceProvider;
    entityData.headOffice            := row.headOffice;
    entityData.type                  := row.type;
    entityData.active                := row.active;
    entityData.activeAssociation     := row.activeAssociation;
    IF (row.affiliatedID > 0) THEN
      entityData.level               := 3;
      entityData.name                := row.affiliatedName;
      entityData.tradingName         := row.affiliatedTradingName;
      entityData.blocked             := row.affiliatedBlocked;
      entityData.cityID              := row.affiliatedCityID;
      entityData.cityName            := row.affiliatedCityName;
      entityData.nationalregister    := row.affiliatedNationalregister;
      entityData.createdAt           := row.joinedat;
      entityData.updatedAt           := row.joinedat;

      IF (row.entityBlocked) THEN
        entityData.blockedLevel      := 1;
      ELSE
        IF (row.affiliatedBlocked) THEN
          entityData.blockedLevel    := 3;
        ELSE
          IF (row.subsidiaryBlocked) THEN
            entityData.blockedLevel  := 2;
          ELSE
            entityData.blockedLevel  := 0;
          END IF;
        END IF;
      END IF;
    ELSE
      IF (row.entityItems > 1) THEN
        entityData.level             := 1;
        entityData.name              := row.subsidiaryName;
        entityData.tradingName       := '';
        entityData.blocked           := row.subsidiaryBlocked;
        entityData.createdAt         := row.unityCreatedAt;
        entityData.updatedAt         := row.unityUpdatedAt;
      ELSE
        entityData.level             := 2;
        entityData.name              := row.name;
        entityData.tradingName       := row.tradingName;
        entityData.blocked           := (row.subsidiaryBlocked OR row.entityBlocked);
        entityData.createdAt         := row.createdAt;
        entityData.updatedAt         := row.updatedAt;
      END IF;
      entityData.cityID              := row.cityID;
      entityData.cityName            := row.cityName;
      entityData.nationalregister    := row.nationalregister;

      IF (row.entityBlocked) THEN
        entityData.blockedLevel      := 1;
      ELSE
        IF (row.subsidiaryBlocked) THEN
          entityData.blockedLevel    := 2;
        ELSE
          entityData.blockedLevel    := 0;
        END IF;
      END IF;
    END IF;
    entityData.fullcount             := row.fullcount;

    RETURN NEXT entityData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getEntitiesData(1, 0, 'contractor', '', 'name', NULL, 0, 0, 0, 10);
-- SELECT * FROM erp.getEntitiesData(1, 0, 'customer', '', 'name', NULL, 0, 0, 0, 10);

-- ---------------------------------------------------------------------
-- Atualizamos a função de obtenção de dados de cobrança
-- ---------------------------------------------------------------------
DROP FUNCTION erp.getBillingsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FinvoiceID integer,
  FsearchValue varchar(100), FsearchField varchar(20),
  FinMonthlyCalculation boolean, FOrder varchar, Skip integer,
  LimitOf integer);
DROP TYPE erp.billingData;

CREATE TYPE erp.billingData AS
(
  billingID            integer,
  customerID           integer,
  customerName         varchar(100),
  cooperative          boolean,
  juridicalperson      boolean,
  subsidiaryID         integer,
  subsidiaryName       varchar(100),
  contractID           integer,
  contractNumber       varchar(16),
  planID               integer,
  planName             varchar(50),
  dueDay               smallint,
  installationID       integer,
  installationNumber   char(12),
  vehicleID            integer,
  plate                varchar(7),
  billingDate          date,
  name                 varchar(100),
  billingValue         numeric(12,2),
  installmentNumber    smallint,
  numberOfInstallments smallint,
  granted              boolean,
  reasonforgranting    text,
  renegotiated         boolean,
  renegotiationID      integer,
  inMonthlyCalculation boolean,
  ascertainedPeriodID  integer,
  invoiceID            integer,
  fullcount            integer
);

CREATE OR REPLACE FUNCTION erp.getBillingsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FinvoiceID integer,
  FsearchValue varchar(100), FsearchField varchar(20),
  FinMonthlyCalculation boolean, FOrder varchar, Skip integer,
  LimitOf integer)
RETURNS SETOF erp.billingData AS
$$
DECLARE
  billingData  erp.billingData%rowtype;
  row          record;
  vehicleData  record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  blockedLevel integer;
  Finvoiced    varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FcustomerID IS NULL) THEN
    FcustomerID = 0;
  END IF;
  IF (FinvoiceID IS NULL) THEN
    FinvoiceID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FinMonthlyCalculation IS NULL) THEN
    FinMonthlyCalculation = FALSE;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'customers.name, installations.installationID, billings.billingDate, billings.installmentNumber';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';
  
  -- Realiza a filtragem por cliente
  IF (FcustomerID > 0) THEN
    filter := format(' AND customers.entityID = %s',
                    FcustomerID);
    IF (FsubsidiaryID > 0) THEN
      filter := filter || format(' AND subsidiaries.subsidiaryID = %s',
                                 FsubsidiaryID);
    END IF;
  END IF;

  IF (FinvoiceID > 0) THEN
    -- Visualizamos apenas os valores que estão em uma fatura
    filter := filter || format(' AND billings.invoiceID = %s',
                               FinvoiceID);
    Finvoiced := 'true';
  ELSE
    Finvoiced := 'false';
  END IF;

  IF (FinMonthlyCalculation) THEN
    -- Visualizamos apenas os valores que estão no processo de análise
    -- para o faturamento
    filter := filter || format(' AND billings.invoiceID IS NOT NULL');
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- Determina o campo onde será realizada a pesquisa
      CASE (FsearchField)
        WHEN 'plate' THEN
          -- Localizamos instalações em que este veículo esteve associado
          FsearchValue := UPPER(FsearchValue);
          filter := filter || 
            format(' AND installations.installationID IN ('
              || 'SELECT I.installationID'
              || '  FROM erp.vehicles AS V '
              || ' INNER JOIN erp.installationRecords AS I USING (vehicleID)'
              || ' WHERE V.plate ILIKE ''%%%s%%'''
              || ' GROUP BY I.installationID)',
            FsearchValue);
        WHEN 'installationid' THEN
          -- Localizamos pelo ID da instalação
          filter := filter ||
            format(' AND installations.installationid = %s', FsearchValue);
        WHEN 'contractNumber' THEN
          -- Localizamos pelo número do contrato
          field := 'erp.getContractNumber(contracts.createdat)';
          filter := filter ||
            format(' AND %s ILIKE ''%%%s%%''', field, FsearchValue);
        ELSE
          -- Localizamos pelo número da instalação
          field := 'installations.installationNumber';
          filter := filter ||
            format(' AND %s ILIKE ''%%%s%%''', field, FsearchValue);
      END CASE;
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  -- Monta a consulta
  query := format('
    SELECT billings.billingID,
           contracts.customerID,
           customers.name AS customerName,
           entitiesTypes.cooperative,
           entitiesTypes.juridicalperson,
           contracts.subsidiaryID,
           subsidiaries.name AS subsidiaryName,
           billings.contractID,
           erp.getContractNumber(contracts.createdat) AS contractNumber,
           contracts.planid,
           plans.name AS planName,
           dueDays.day AS dueDay,
           billings.installationID,
           installations.installationNumber,
           billings.billingDate,
           CASE
             WHEN billings.renegotiationid IS NOT NULL AND billings.numberofinstallments > 0 THEN ''Renegociação de '' || billings.name || '' (Parcela '' || billings.installmentNumber || '' de '' || billings.numberofinstallments || '')''
             WHEN billings.renegotiationid IS NOT NULL AND billings.numberofinstallments = 0 THEN ''Renegociação de '' || billings.name
             WHEN billings.numberofinstallments > 0 THEN billings.name || '' (Parcela '' || billings.installmentNumber || '' de '' || billings.numberofinstallments || '')''
             ELSE billings.name
           END AS name,
           billings.value AS billingValue,
           billings.installmentNumber,
           billings.numberOfInstallments,
           billings.granted,
           billings.reasonforgranting,
           billings.renegotiated,
           billings.renegotiationid,
           CASE
             WHEN billings.invoiceID IS NULL THEN FALSE
             ELSE TRUE
           END AS inMonthlyCalculation,
           billings.ascertainedPeriodID,
           billings.invoiceID,
           count(*) OVER() AS fullcount
      FROM erp.billings
     INNER JOIN erp.contracts ON (billings.contractID = contracts.contractID)
     INNER JOIN erp.plans ON (contracts.planID = plans.planID)
     INNER JOIN erp.dueDays ON (contracts.dueDayID = dueDays.dueDayID)
     INNER JOIN erp.entities AS customers ON (contracts.customerID = customers.entityID)
     INNER JOIN erp.entitiesTypes ON (customers.entityTypeID = entitiesTypes.entityTypeID)
     INNER JOIN erp.subsidiaries ON (contracts.subsidiaryID = subsidiaries.subsidiaryID)
      LEFT JOIN erp.installations ON (billings.installationID = installations.installationID)
     WHERE contracts.contractorID = %s
       AND billings.invoiced = %s
       AND contracts.deleted = false
       AND customers.deleted = false
       AND subsidiaries.deleted = false %s
     ORDER BY %s %s;',
    fContractorID, Finvoiced, filter, FOrder, limits);
  -- RAISE NOTICE 'Query IS %', query;
  FOR row IN EXECUTE query
  LOOP
    billingData.billingID            := row.billingID;
    billingData.customerID           := row.customerID;
    billingData.customerName         := row.customerName;
    billingData.cooperative          := row.cooperative;
    billingData.juridicalperson      := row.juridicalperson;
    billingData.subsidiaryID         := row.subsidiaryID;
    billingData.subsidiaryName       := row.subsidiaryName;
    billingData.contractID           := row.contractID;
    billingData.contractNumber       := row.contractNumber;
    billingData.planID               := row.planID;
    billingData.planName             := row.planName;
    billingData.dueDay               := row.dueDay;
    billingData.installationID       := row.installationID;
    billingData.installationNumber   := row.installationNumber;
    billingData.billingDate          := row.billingDate;
    billingData.name                 := row.name;
    billingData.billingValue         := row.billingValue;
    billingData.installmentNumber    := row.installmentNumber;
    billingData.numberOfInstallments := row.numberOfInstallments;
    billingData.granted              := row.granted;
    billingData.reasonforgranting    := row.reasonforgranting;
    billingData.renegotiated         := row.renegotiated;
    billingData.renegotiationID      := row.renegotiationID;
    billingData.inMonthlyCalculation := row.inMonthlyCalculation;
    billingData.ascertainedPeriodID  := row.ascertainedPeriodID;
    billingData.invoiceID            := row.invoiceID;
    billingData.fullcount            := row.fullcount;

    -- Localizamos o veículo
    SELECT DISTINCT ON (I.installationID)
           R.vehicleID,
           V.plate
      INTO vehicleData
      FROM erp.installations AS I
     INNER JOIN erp.installationRecords AS R USING (installationID)
     INNER JOIN erp.vehicles AS V USING (vehicleID)
     WHERE I.installationID = row.installationID
     ORDER BY I.installationID, R.uninstalledAt NULLS FIRST, R.installedAt DESC;
    IF NOT FOUND THEN
      billingData.vehicleID = NULL;
      billingData.plate     = NULL;
    ELSE
      billingData.vehicleID = vehicleData.vehicleID;
      billingData.plate     = vehicleData.plate;
    END IF;

    RETURN NEXT billingData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Atualizamos a função de obtenção de dados de contratos
-- ---------------------------------------------------------------------
DROP FUNCTION erp.getContractsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FcontractID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Factive boolean,
  FtoCarnet boolean, FonlyActive boolean, FOrder varchar, Skip integer,
  LimitOf integer);
DROP TYPE erp.contractData;

CREATE TYPE erp.contractData AS
(
  contractID                integer,
  contractorID              integer,
  contractorName            varchar(100),
  contractorBlocked         boolean,
  customerID                integer,
  customerName              varchar(100),
  customerBlocked           boolean,
  customerTypeID            integer,
  customerTypeName          varchar(30),
  cooperative               boolean,
  juridicalperson           boolean,
  subsidiaryID              integer,
  subsidiaryName            varchar(100),
  subsidiaryBlocked         boolean,
  contractNumber            varchar(16),
  planID                    integer,
  planName                  varchar(50),
  dueDay                    smallint,
  signatureDate             date,
  contractendDate           date,
  paymentConditionID        integer,
  paymentConditionName      varchar(50),
  numberOfParcels           integer,
  contractPrice             numeric(12,2),
  contractActive            boolean,
  installationID            integer,
  installationNumber        char(12),
  noTracker                 boolean,
  containsTrackingData      boolean,
  monthPrice                numeric(12,2),
  startDate                 date,
  endDate                   date,
  dateOfNextReadjustment    date,
  lastDayOfCalculatedPeriod date,
  lastDayOfBillingPeriod    date,
  firstDueDate              date,
  blockedLevel              smallint,
  vehicleID                 integer,
  plate                     varchar(7),
  vehicleTypeID             integer,
  vehicleTypeName           varchar(30),
  vehicleBrandID            integer,
  vehicleBrandName          varchar(30),
  vehicleModelID            integer,
  vehicleModelName          varchar(50),
  vehicleColorID            integer,
  vehicleColorName          varchar(30),
  vehicleColor              varchar(30),
  vehicleBlocked            boolean,
  fullcount                 integer
);

CREATE OR REPLACE FUNCTION erp.getContractsData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FcontractID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Factive boolean,
  FtoCarnet boolean, FonlyActive boolean, FOrder varchar, Skip integer,
  LimitOf integer)
RETURNS SETOF erp.contractData AS
$$
DECLARE
  contractData erp.contractData%rowtype;
  row          record;
  vehicleData  record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  blockedLevel integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FcustomerID IS NULL) THEN
    FcustomerID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FcontractID IS NULL) THEN
    FcontractID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'customer.name ASC, subsidiary.subsidiaryid ASC, contracts.signaturedate ASC';
  END IF;
  IF (FtoCarnet IS NULL) THEN
    FtoCarnet = FALSE;
  END IF;
  IF (FonlyActive IS NULL) THEN
    FonlyActive = FALSE;
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';
  
  IF (FcontractID > 0) THEN
    filter := format(' AND contracts.contractID = %s',
                    FcontractID);
  ELSE
    -- Realiza a filtragem por cliente
    IF (FcustomerID > 0) THEN
      filter := format(' AND contracts.customerID = %s',
                      FcustomerID);
      IF (FsubsidiaryID > 0) THEN
        filter := filter || format(' AND contracts.subsidiaryID = %s',
                                  FsubsidiaryID);
      END IF;
    END IF;
  END IF;

  IF (FtoCarnet) THEN
    -- Incluímos apenas contratos cuja forma de pagamento seja em carnê
    filter := filter || ' AND contracts.paymentConditionID IN '
      || '('
      ||   'SELECT Carnets.paymentConditionID FROM '
      ||   '('
      ||     'SELECT COND.paymentconditionid, '
      ||            'string_to_array(COND.paymentinterval, ''/'') AS parcels '
      ||       'FROM erp.paymentconditions AS COND '
      ||      'WHERE COND.paymentmethodid = 5 '
      ||        'AND COND.paymentformid = 2 '
      ||        'AND COND.timeunit = ''MONTH'''
      ||   ') AS Carnets '
      ||   'WHERE array_length(Carnets.parcels, 1) > 1 '
      ||     'AND (Carnets.parcels::INT[])[1] > 0'
      || ')'
    ;
  END IF;

  IF (FonlyActive) THEN
    -- Incluímos apenas contratos que não estejam encerrados
    filter := filter || ' AND contracts.endDate IS NULL AND installations.endDate IS NULL';
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      IF (FsearchField = 'plate') THEN
        -- Localizamos instalações em que este veículo esteve associado
        FsearchValue := UPPER(FsearchValue);
        filter := filter || 
          format(' AND installations.installationID IN ('
            || 'SELECT I.installationID'
            || '  FROM erp.vehicles AS V '
            || ' INNER JOIN erp.installationRecords AS I USING (vehicleID)'
            || ' WHERE V.plate ILIKE ''%%%s%%'''
            || ' GROUP BY I.installationID)',
          FsearchValue);
      ELSE
        -- Determina o campo onde será realizada a pesquisa
        CASE (FsearchField)
          WHEN 'contractNumber' THEN
            field := 'erp.getContractNumber(contracts.createdat)';
          ELSE
            field := 'installations.installationNumber';
        END CASE;
        -- Monta o filtro
        filter := filter || format(' AND %s ILIKE ''%%%s%%''',
                                    field, FsearchValue);
      END IF;
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  IF (Factive IS NOT NULL) THEN
    IF (Factive = TRUE) THEN
      -- Adiciona a opção de filtragem de contratos ativos
      filter := filter || ' AND contracts.active = true';
    ELSE
      -- Adiciona a opção de filtragem de contratos inativos
      filter := filter || ' AND contracts.active = false';
    END IF;
  END IF;

  -- Monta a consulta
  query := format('SELECT contracts.contractID,
                          contracts.contractorID,
                          contractor.name AS contractorName,
                          contractor.blocked AS contractorBlocked,
                          contracts.customerID,
                          customer.name AS customerName,
                          customer.blocked AS customerBlocked,
                          customer.entityTypeID AS customerTypeID,
                          customerType.name AS customerTypeName,
                          customerType.cooperative,
                          customerType.juridicalperson,
                          contracts.subsidiaryID,
                          subsidiary.name AS subsidiaryName,
                          subsidiary.blocked AS subsidiaryBlocked,
                          erp.getContractNumber(contracts.createdat) AS contractNumber,
                          contracts.planID,
                          plans.name AS planName,
                          contracts.subscriptionPlanID,
                          dueDays.day AS dueDay,
                          contracts.signaturedate,
                          contracts.enddate AS contractenddate,
                          contracts.paymentConditionID,
                          paymentConditions.name AS paymentConditionName,
                          CASE
                            WHEN paymentConditions.timeunit = ''MONTH'' AND paymentConditions.paymentformid = 2 AND paymentConditions.paymentmethodid = 5 THEN array_upper(string_to_array(paymentConditions.paymentinterval, ''/'')::int[], 1)
                            ELSE subscriptionPlans.numberOfMonths
                          END AS numberOfParcels,
                          contracts.monthprice AS contractPrice,
                          contracts.active AS contractActive,
                          installations.installationID,
                          installations.installationNumber,
                          installations.monthprice,
                          installations.startDate,
                          installations.endDate,
                          installations.dateOfNextReadjustment,
                          installations.lastDayOfCalculatedPeriod,
                          installations.lastDayOfBillingPeriod,
                          CASE
                            WHEN installations.lastDayOfBillingPeriod IS NULL THEN ((date_trunc(''month'', (CURRENT_DATE + interval ''1 day'')) + interval ''1 month'') + (dueDays.day - 1) * interval ''1 day'')::Date
                            ELSE ((date_trunc(''month'', (installations.lastDayOfBillingPeriod + interval ''1 day'')) + interval ''1 month'') + (dueDays.day - 1) * interval ''1 day'')::Date
                          END AS firstDueDate,
                          vehicle.vehicleID,
                          vehicle.plate,
                          vehicle.vehicleTypeID,
                          vehicle.vehicleTypeName,
                          vehicle.vehicleBrandID,
                          vehicle.vehicleBrandName,
                          vehicle.vehicleModelID,
                          vehicle.vehicleModelName,
                          vehicle.vehicleColorID,
                          vehicle.vehicleColorName,
                          vehicle.blocked AS vehicleBlocked,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID
                                     AND uninstalledAt IS NULL) AS tracked,
                          EXISTS (SELECT 1
                                    FROM erp.installationRecords AS R
                                   WHERE R.installationID = installations.installationID) AS containsTrackingData,
                          count(*) OVER() AS fullcount
                     FROM erp.contracts
                    INNER JOIN erp.subscriptionPlans USING (subscriptionPlanID)
                    INNER JOIN erp.entities AS contractor ON (contracts.contractorID = contractor.entityID)
                    INNER JOIN erp.entities AS customer ON (contracts.customerID = customer.entityID)
                    INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
                    INNER JOIN erp.subsidiaries AS subsidiary ON (contracts.subsidiaryID = subsidiary.subsidiaryID)
                    INNER JOIN erp.dueDays ON (contracts.dueDayID = dueDays.dueDayID)
                    INNER JOIN erp.paymentConditions ON (contracts.paymentConditionID = paymentConditions.paymentConditionID)
                    INNER JOIN erp.plans ON (contracts.planID = plans.planID)
                    INNER JOIN erp.installations ON (contracts.contractID = installations.contractID)
                    INNER JOIN erp.getMostRecentVehicleOnInstallation(contracts.contractorID, installations.installationid) AS vehicle ON (installations.installationID = vehicle.installationID)
                    WHERE contracts.contractorID = %s
                      AND contracts.deleted = false
                      AND customer.deleted = false
                      AND subsidiary.deleted = false %s
                    ORDER BY %s %s',
                  fContractorID, filter, FOrder, limits);
  -- RAISE NOTICE 'Query: %', query;
  FOR row IN EXECUTE query
  LOOP
    contractData.contractID                 := row.contractID;
    contractData.contractorID               := row.contractorID;
    contractData.contractorName             := row.contractorName;
    contractData.contractorBlocked          := row.contractorBlocked;
    contractData.customerID                 := row.customerID;
    contractData.customerName               := row.customerName;
    contractData.customerBlocked            := row.customerBlocked;
    contractData.customerTypeID             := row.customerTypeID;
    contractData.customerTypeName           := row.customerTypeName;
    contractData.juridicalperson            := row.juridicalperson;
    contractData.cooperative                := row.cooperative;
    contractData.subsidiaryID               := row.subsidiaryID;
    contractData.subsidiaryName             := row.subsidiaryName;
    contractData.subsidiaryBlocked          := row.subsidiaryBlocked;
    contractData.contractNumber             := row.contractNumber;
    contractData.planID                     := row.planID;
    contractData.planName                   := row.planName;
    contractData.dueDay                     := row.dueDay;
    contractData.signatureDate              := row.signatureDate;
    contractData.contractEndDate            := row.contractEndDate;
    contractData.paymentConditionID         := row.paymentConditionID;
    contractData.paymentConditionName       := row.paymentConditionName;
    contractData.numberOfParcels            := row.numberOfParcels;
    contractData.contractPrice              := row.contractPrice;
    contractData.contractActive             := row.contractActive;
    contractData.installationID             := row.installationID;
    contractData.installationNumber         := row.installationNumber;
    contractData.noTracker                  := NOT row.tracked;
    contractData.containsTrackingData       := row.containsTrackingData;
    contractData.monthPrice                 := row.monthPrice;
    contractData.startDate                  := row.startDate;
    contractData.endDate                    := row.endDate;
    contractData.dateOfNextReadjustment     := row.dateOfNextReadjustment;
    contractData.lastDayOfCalculatedPeriod  := row.lastDayOfCalculatedPeriod;
    contractData.lastDayOfBillingPeriod     := row.lastDayOfBillingPeriod;
    contractData.firstDueDate               := row.firstDueDate;
    -- RAISE NOTICE 'Contractor %', row.contractorBlocked;
    -- RAISE NOTICE 'Vehicle %', row.vehicleBlocked;
    -- RAISE NOTICE 'Subsidiary %', row.subsidiaryBlocked;
    contractData.vehicleID                  := row.vehicleID;
    contractData.plate                      := row.plate;
    contractData.vehicleTypeID              := row.vehicleTypeID;
    contractData.vehicleTypeName            := row.vehicleTypeName;
    contractData.vehicleBrandID             := row.vehicleBrandID;
    contractData.vehicleBrandName           := row.vehicleBrandName;
    contractData.vehicleModelID             := row.vehicleModelID;
    contractData.vehicleModelName           := row.vehicleModelName;
    contractData.vehicleColorID             := row.vehicleColorID;
    contractData.vehicleColorName           := row.vehicleColorName;
    contractData.vehicleBlocked             := row.vehicleBlocked;

    -- Determina o nível de bloqueio. O nível de bloqueio mais baixo é o
    -- da instalação, seguido do contrato, da unidade/filial do cliente,
    -- da empresa e por último o do contratante
    blockedLevel := 0;
    IF (row.endDate IS NOT NULL) THEN
      -- A instalação foi encerrada
      blockedLevel := blockedLevel|1;
    END IF;
    IF ( (row.contractEndDate IS NOT NULL) OR
         (row.contractActive = FALSE) ) THEN
      -- O contrato está encerrado ou foi inativado
      blockedLevel := blockedLevel|2;
    END IF;
    IF (row.subsidiaryBlocked) THEN
      -- A unidade/filial do cliente foi inativada
      blockedLevel := blockedLevel|4;
    END IF;
    IF (row.customerBlocked) THEN
      -- O cliente foi bloqueado
      blockedLevel := blockedLevel|8;
    END IF;
    IF (row.contractorBlocked) THEN
      -- O contratante foi bloqueado
      blockedLevel := blockedLevel|16;
    END IF;
    contractData.blockedLevel := blockedLevel;
    contractData.fullcount    := row.fullcount;

    RETURN NEXT contractData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- Modifica a obtenção dos dados de veículos para a nova estrutura
DROP FUNCTION erp.getVehiclesData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FvehicleID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Finstalled boolean,
  Factive boolean, FOrder varchar, Skip integer, LimitOf integer);
DROP TYPE erp.vehicleData;

CREATE TYPE erp.vehicleData AS
(
  customerID         integer,
  subsidiaryID       integer,
  juridicalperson    boolean,
  cooperative        boolean,
  headOffice         boolean,
  type               smallint,
  level              smallint,
  active             boolean,
  activeAssociation  boolean,
  name               varchar(100),
  tradingName        varchar(100),
  blocked            boolean,
  vehicleID          integer,
  vehicleTypeID      integer,
  vehicleTypeName    varchar(30),
  vehicleSubtypeID   integer,
  vehicleSubtypeName varchar(30),
  vehicleBrandID     integer,
  vehicleBrandName   varchar(30),
  vehicleModelID     integer,
  vehicleModelName   varchar(50),
  vehicleColorID     integer,
  vehicleColorName   varchar(30),
  vehicleColor       varchar(30),
  carNumber          varchar(20),
  fuelType           char(1),
  fuelTypeName       varchar(30),
  blockedLevel       smallint,
  createdAt          timestamp,
  fullcount          integer
);

CREATE OR REPLACE FUNCTION erp.getVehiclesData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FvehicleID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Factive boolean,
  FOrder varchar, Fstatus integer, Ftype integer,
  Skip integer, LimitOf integer)
RETURNS SETOF erp.vehicleData AS
$$
DECLARE
  vehicleData  erp.vehicleData%rowtype;
  row          record;
  query        varchar;
  field        varchar;
  filter       varchar;
  typeFilter  varchar;
  limits       varchar;
  blockedLevel integer;
  lastEntityID  integer;
  lastSubsidiaryID  integer;
  lastEntityPayerID  integer;
  lastSubsidiaryPayerID  integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FcustomerID IS NULL) THEN
    FcustomerID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FvehicleID IS NULL) THEN
    FvehicleID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'cooperative ASC, itemName, itemOrder, customerName, headOffice DESC, subsidiaryName, plate';
  END IF;
  IF (Fstatus IS NULL) THEN
    Fstatus := 0;
  END IF;
  IF (Ftype IS NULL) THEN
    Ftype := 0;
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';

  -- Os estados possíveis são: (1) inativo e (2) ativo
  typeFilter := '';
  IF (Fstatus > 0) THEN
    IF (Fstatus = 1) THEN
      typeFilter := ' AND active = FALSE';
    ELSE
      typeFilter := ' AND active = TRUE';
    END IF;
  END IF;
  
  IF (FvehicleID > 0) THEN
    -- Realiza a filtragem por veículo
    typeFilter := typeFilter || format(' AND vehicleID = %s', FvehicleID);
  ELSE
    IF (FcustomerID > 0) THEN
      -- Realiza a filtragem por cliente
      typeFilter := typeFilter || format(' AND ( (itemID = %s) OR (customerID = %s))', FcustomerID, FcustomerID);
      IF (FsubsidiaryID > 0) THEN
        typeFilter := typeFilter ||
          format(' AND ((itemUnityID = %s) OR (subsidiaryID = %s))', FsubsidiaryID, FsubsidiaryID);
      END IF;
    END IF;
  END IF;

  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    -- RAISE NOTICE 'FsearchValue IS NOT NULL';
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- Determina o campo onde será realizada a pesquisa
      CASE (FsearchField)
        WHEN 'vehicleBrandName' THEN
          field := 'vehicleBrand.name';
        WHEN 'vehicleModelName' THEN
          field := 'vehicleModel.name';
        ELSE
          field := 'vehicle.' || FsearchField;
      END CASE;

      -- Monta o filtro
      filter := format(' AND public.unaccented(%s) ILIKE public.unaccented(''%%%s%%'')',
                       field, FsearchValue);
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  IF (Factive IS NOT NULL) THEN
    IF (Factive = TRUE) THEN
      -- Adiciona a opção de filtragem de veículos ativos
      filter := filter || ' AND vehicle.blocked = false';
    ELSE
      -- Adiciona a opção de filtragem de veículos inativos
      filter := filter || ' AND vehicle.blocked = true';
    END IF;
  END IF;

  -- Monta a consulta
  query := format('WITH items AS (
                    SELECT CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN equipment.customerPayerID
                             ELSE vehicle.customerID
                           END AS itemID,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN customerPayer.blocked
                             ELSE customer.blocked
                           END AS itemBlocked,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN customerPayer.name
                             ELSE customer.name
                           END AS itemName,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN customerPayer.tradingName
                             ELSE customer.tradingName
                           END AS itemTradingName,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.subsidiaryID <> equipment.subsidiaryID
                             THEN unityPayer.subsidiaryID
                             ELSE unity.subsidiaryID
                           END AS itemUnityID,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.subsidiaryID <> equipment.subsidiaryID
                             THEN unityPayer.name
                             ELSE unity.name
                           END AS itemUnity,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.subsidiaryID <> equipment.subsidiaryID
                             THEN unityPayer.headOffice
                             ELSE unity.headOffice
                           END AS itemUnityHeadOffice,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.subsidiaryID <> equipment.subsidiaryID
                             THEN unityPayer.blocked
                             ELSE unity.blocked
                           END AS itemUnityBlocked,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN customerPayerType.cooperative
                             ELSE customerType.cooperative
                           END AS cooperative,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN customerPayerType.juridicalperson
                             ELSE customerType.juridicalperson
                           END AS juridicalpersonOfItem,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID = equipment.customerPayerID
                             THEN 1
                             WHEN equipment.customerPayerID IS NOT NULL
                              AND vehicle.customerID <> equipment.customerPayerID
                             THEN 3
                             ELSE 2
                           END AS itemOrder,
                           vehicle.customerID,
                           customer.name AS customerName,
                           customer.tradingName,
                           customer.blocked AS customerBlocked,
                           customerType.juridicalperson,
                           unity.name AS subsidiaryName,
                           unity.blocked AS subsidiaryBlocked,
                           unity.headOffice,
                           vehicle.subsidiaryID,
                           unity.blocked AS subsidiaryBlocked,
                           vehicle.vehicleID AS id,
                           vehicle.plate,
                           vehicle.vehicleTypeID,
                           type.name AS vehicleTypeName,
                           CASE
                             WHEN model.vehicleSubtypeID IS NULL THEN 0
                             ELSE model.vehicleSubtypeID
                           END AS vehicleSubtypeID,
                           CASE
                             WHEN model.vehicleSubtypeID IS NULL THEN ''Não informado''
                             ELSE subtype.name
                           END AS vehicleSubtypeName,
                           vehicle.vehicleBrandID,
                           brand.name AS vehicleBrandName,
                           vehicle.vehicleModelID,
                           model.name AS vehicleModelName,
                           vehicle.vehicleColorID,
                           color.name AS vehicleColorName,
                           color.color AS vehicleColor,
                           vehicle.carNumber,
                           vehicle.fuelType,
                           fuel.name AS fuelTypeName,
                           vehicle.blocked AS vehicleBlocked,
                           vehicle.createdAt,
                           CASE
                             WHEN equipment.customerPayerID IS NOT NULL
                             THEN true
                             ELSE false
                           END AS active
                      FROM erp.vehicles AS vehicle
                     INNER JOIN erp.vehicleTypes AS type ON (vehicle.vehicleTypeID = type.vehicleTypeID)
                     INNER JOIN erp.vehicleBrands AS brand ON (vehicle.vehicleBrandID = brand.vehicleBrandID)
                     INNER JOIN erp.vehicleModels AS model ON (vehicle.vehicleModelID = model.vehicleModelID)
                      LEFT JOIN erp.vehicleSubtypes AS subtype ON (model.vehicleSubtypeID = subtype.vehicleSubtypeID)
                     INNER JOIN erp.vehicleColors AS color USING (vehicleColorID)
                     INNER JOIN erp.fuelTypes AS fuel USING (fuelType)
                     INNER JOIN erp.entities AS customer ON (vehicle.customerID = customer.entityID)
                     INNER JOIN erp.subsidiaries AS unity ON (vehicle.subsidiaryID = unity.subsidiaryID)
                     INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
                      LEFT JOIN erp.equipments AS equipment USING (vehicleID)
                      LEFT JOIN erp.entities AS customerPayer ON (equipment.customerPayerID = customerPayer.entityID)
                      LEFT JOIN erp.entitiesTypes AS customerPayerType ON (customerPayer.entityTypeID = customerPayerType.entityTypeID)
                      LEFT JOIN erp.subsidiaries AS unityPayer ON (equipment.subsidiaryPayerID = unityPayer.subsidiaryID)
                     WHERE vehicle.contractorID = %s
                       AND vehicle.deleted = false %s
                  ) SELECT *,
                           (SELECT count(*) FROM erp.subsidiaries AS S WHERE S.entityID = itemID) AS unityItems,
                           (SELECT count(*) FROM erp.subsidiaries AS S WHERE S.entityID = customerID) AS unityCustomerItems,
                           count(*) OVER() AS fullcount
                      FROM items
                     WHERE (1=1) %s
                     ORDER BY %s %s;',
                  fContractorID, filter, typeFilter, FOrder, limits);
  -- RAISE NOTICE 'SQL: %',query;

  lastEntityPayerID := 0;
  lastSubsidiaryPayerID := 0;
  lastEntityID := 0;
  lastSubsidiaryID := 0;

  FOR row IN EXECUTE query
  LOOP
    -- RAISE NOTICE 'lastEntityPayerID = %', lastEntityPayerID;
    -- RAISE NOTICE 'itemID = %', row.itemID;
    IF (lastEntityPayerID <> row.itemID) THEN
      -- Iniciamos um novo grupo
      lastEntityPayerID := row.itemID;
      lastEntityID := row.itemID;

      -- Verifica se precisamos dividir esta entidade em mais de uma
      -- linha
      -- RAISE NOTICE 'unityItems = %', row.unityItems;
      IF (row.unityItems > 1) THEN
        -- Descrevemos aqui a entidade principal
        vehicleData.customerID         := row.itemID;
        vehicleData.subsidiaryID       := 0;
        vehicleData.juridicalperson    := row.juridicalpersonOfItem;
        vehicleData.cooperative        := row.cooperative;
        vehicleData.headOffice         := false;
        vehicleData.type               := 1;
        vehicleData.level              := 0;
        vehicleData.active             := NOT row.itemBlocked;
        vehicleData.activeAssociation  := NOT row.itemBlocked;
        vehicleData.name               := row.itemName;
        vehicleData.tradingName        := row.itemTradingName;
        vehicleData.blocked            := row.itemBlocked;
        vehicleData.vehicleID          := NULL;
        vehicleData.vehicleTypeID      := NULL;
        vehicleData.vehicleTypeName    := NULL;
        vehicleData.vehicleSubtypeID   := NULL;
        vehicleData.vehicleSubtypeName := NULL;
        vehicleData.vehicleBrandID     := NULL;
        vehicleData.vehicleBrandName   := NULL;
        vehicleData.vehicleModelID     := NULL;
        vehicleData.vehicleModelName   := NULL;
        vehicleData.vehicleColorID     := NULL;
        vehicleData.vehicleColorName   := NULL;
        vehicleData.vehicleColor       := NULL;
        vehicleData.carNumber          := NULL;
        vehicleData.fuelType           := NULL;
        vehicleData.fuelTypeName       := NULL;

        IF (row.itemBlocked) THEN
          vehicleData.blockedLevel     := 1;
        ELSE
          vehicleData.blockedLevel     := 0;
        END IF;
        vehicleData.createdAt          := row.createdAt;
        vehicleData.fullcount          := row.fullcount;

        RETURN NEXT vehicleData;
      END IF;
    END IF;

    -- RAISE NOTICE 'lastSubsidiaryPayerID = %', lastSubsidiaryPayerID;
    -- RAISE NOTICE 'itemUnityID = %', row.itemUnityID;
    IF (lastSubsidiaryPayerID <> row.itemUnityID) THEN
      -- Iniciamos um novo subgrupo
      lastSubsidiaryPayerID := row.itemUnityID;
      lastSubsidiaryID := row.itemUnityID;

      -- Informa os dados da unidade (ou do cliente se houver uma
      -- unidade apenas)
      vehicleData.customerID         := row.itemID;
      vehicleData.subsidiaryID       := row.itemUnityID;
      vehicleData.juridicalperson    := row.juridicalpersonOfItem;
      vehicleData.cooperative        := row.cooperative;
      vehicleData.headOffice         := row.itemUnityHeadOffice;
      vehicleData.type               := 1;
      IF (row.unityItems > 1) THEN
        vehicleData.level            := 1;
      ELSE
        vehicleData.level            := 2;
      END IF;
      vehicleData.active             := NOT(row.itemBlocked AND row.itemUnityBlocked);
      vehicleData.activeAssociation  := NOT row.itemBlocked;
      IF (row.unityItems > 1) THEN
        vehicleData.name             := row.itemUnity;
        vehicleData.tradingName      := '';
      ELSE
        vehicleData.name             := row.itemName;
        vehicleData.tradingName      := row.itemTradingName;
      END IF;
      IF (row.unityItems > 1) THEN
        vehicleData.blocked          := row.itemUnityBlocked;
      ELSE
        vehicleData.blocked          := row.itemBlocked;
      END IF;
      vehicleData.vehicleID          := NULL;
      vehicleData.vehicleTypeID      := NULL;
      vehicleData.vehicleTypeName    := NULL;
      vehicleData.vehicleSubtypeID   := NULL;
      vehicleData.vehicleSubtypeName := NULL;
      vehicleData.vehicleBrandID     := NULL;
      vehicleData.vehicleBrandName   := NULL;
      vehicleData.vehicleModelID     := NULL;
      vehicleData.vehicleModelName   := NULL;
      vehicleData.vehicleColorID     := NULL;
      vehicleData.vehicleColorName   := NULL;
      vehicleData.vehicleColor       := NULL;
      vehicleData.carNumber          := NULL;
      vehicleData.fuelType           := NULL;
      vehicleData.fuelTypeName       := NULL;

      IF (row.itemBlocked) THEN
        vehicleData.blockedLevel     := 1;
      ELSE
        IF (row.itemUnityBlocked) THEN
          vehicleData.blockedLevel   := 2;
        ELSE
          vehicleData.blockedLevel   := 0;
        END IF;
      END IF;
      vehicleData.createdAt          := row.createdAt;
      vehicleData.fullcount          := row.fullcount;

      RETURN NEXT vehicleData;
    END IF;

    IF (lastEntityID <> row.customerID) THEN
      -- Iniciamos um novo grupo
      lastEntityID := row.customerID;

      -- Verifica se precisamos dividir esta entidade em mais de uma
      -- linha
      IF (row.unityCustomerItems > 1) THEN
        -- Descrevemos aqui a entidade secundária
        vehicleData.customerID           := row.customerID;
        vehicleData.subsidiaryID         := 0;
        vehicleData.juridicalperson      := row.juridicalperson;
        vehicleData.cooperative          := false;
        vehicleData.headOffice           := false;
        vehicleData.type                 := 2;
        vehicleData.level                := 3;
        vehicleData.active               := NOT row.customerBlocked;
        vehicleData.activeAssociation    := NOT row.itemBlocked;
        vehicleData.name                 := row.customerName;
        vehicleData.tradingName          := row.tradingName;
        vehicleData.blocked              := row.customerBlocked;
        vehicleData.vehicleID            := NULL;
        vehicleData.vehicleTypeID        := NULL;
        vehicleData.vehicleTypeName      := NULL;
        vehicleData.vehicleSubtypeID     := NULL;
        vehicleData.vehicleSubtypeName   := NULL;
        vehicleData.vehicleBrandID       := NULL;
        vehicleData.vehicleBrandName     := NULL;
        vehicleData.vehicleModelID       := NULL;
        vehicleData.vehicleModelName     := NULL;
        vehicleData.vehicleColorID       := NULL;
        vehicleData.vehicleColorName     := NULL;
        vehicleData.vehicleColor         := NULL;
        vehicleData.carNumber            := NULL;
        vehicleData.fuelType             := NULL;
        vehicleData.fuelTypeName         := NULL;

        IF (row.itemID = row.customerID) THEN
          IF (row.itemBlocked) THEN
            vehicleData.blockedLevel     := 1;
          ELSE
            IF (row.itemUnityBlocked) THEN
              vehicleData.blockedLevel   := 2;
            ELSE
              vehicleData.blockedLevel   := 0;
            END IF;
          END IF;
        ELSE
          IF (row.itemBlocked) THEN
            vehicleData.blockedLevel     := 1;
          ELSE
            IF (row.itemUnityBlocked) THEN
              vehicleData.blockedLevel   := 2;
            ELSE
              IF (row.customerBlocked) THEN
                vehicleData.blockedLevel := 3;
              ELSE
                vehicleData.blockedLevel := 0;
              END IF;
            END IF;
          END IF;
        END IF;

        vehicleData.createdAt            := row.createdAt;
        vehicleData.fullcount            := row.fullcount;

        RETURN NEXT vehicleData;
      END IF;
    END IF;

    IF (lastSubsidiaryID <> row.subsidiaryID) THEN
      -- Iniciamos um novo subgrupo
      lastSubsidiaryID := row.subsidiaryID;

      -- Informa os dados da unidade (ou do cliente se houver uma
      -- unidade apenas) da entidade secundária
      vehicleData.customerID             := row.customerID;
      vehicleData.subsidiaryID           := row.subsidiaryID;
      vehicleData.juridicalperson        := row.juridicalperson;
      vehicleData.cooperative            := false;
      vehicleData.headOffice             := row.headOffice;
      vehicleData.type                   := 2;
      IF (row.unityCustomerItems > 1) THEN
        vehicleData.level                := 4;
      ELSE
        vehicleData.level                := 5;
      END IF;
      vehicleData.active                 := NOT(row.customerBlocked AND row.subsidiaryBlocked);
      vehicleData.activeAssociation      := NOT row.itemBlocked;
      vehicleData.name                   := row.subsidiaryName;
      vehicleData.tradingName            := '';
      vehicleData.blocked                := row.subsidiaryBlocked;
      vehicleData.vehicleID              := NULL;
      vehicleData.vehicleTypeID          := NULL;
      vehicleData.vehicleTypeName        := NULL;
      vehicleData.vehicleSubtypeID       := NULL;
      vehicleData.vehicleSubtypeName     := NULL;
      vehicleData.vehicleBrandID         := NULL;
      vehicleData.vehicleBrandName       := NULL;
      vehicleData.vehicleModelID         := NULL;
      vehicleData.vehicleModelName       := NULL;
      vehicleData.vehicleColorID         := NULL;
      vehicleData.vehicleColorName       := NULL;
      vehicleData.vehicleColor           := NULL;
      vehicleData.carNumber              := NULL;
      vehicleData.fuelType               := NULL;
      vehicleData.fuelTypeName           := NULL;

      IF ( (row.itemID = row.customerID) OR
           (row.itemUnityID = row.subsidiaryID) ) THEN
        IF (row.itemBlocked) THEN
          vehicleData.blockedLevel       := 1;
        ELSE
          IF (row.itemUnityBlocked) THEN
            vehicleData.blockedLevel     := 2;
          ELSE
            vehicleData.blockedLevel     := 0;
          END IF;
        END IF;
      ELSE
        IF (row.itemBlocked) THEN
          vehicleData.blockedLevel       := 1;
        ELSE
          IF (row.itemUnityBlocked) THEN
            vehicleData.blockedLevel     := 2;
          ELSE
            IF (row.customerBlocked) THEN
              vehicleData.blockedLevel   := 3;
            ELSE
              IF (row.subsidiaryBlocked) THEN
                vehicleData.blockedLevel := 4;
              ELSE
                vehicleData.blockedLevel := 0;
              END IF;
            END IF;
          END IF;
        END IF;
      END IF;

      vehicleData.createdAt              := row.createdAt;
      vehicleData.fullcount              := row.fullcount;

      RETURN NEXT vehicleData;
    END IF;

    -- Informa os dados do veículo
    vehicleData.customerID               := row.customerID;
    vehicleData.subsidiaryID             := row.subsidiaryID;
    vehicleData.juridicalperson          := row.juridicalperson;
    vehicleData.cooperative              := false;
    vehicleData.headOffice               := row.headOffice;
    vehicleData.type                     := 3;
    vehicleData.level                    := 6;
    vehicleData.active                   := row.active;
    vehicleData.activeAssociation        := NOT row.itemBlocked;
    vehicleData.name                     := row.plate;
    vehicleData.tradingName              := NULL;
    vehicleData.blocked                  := row.vehicleBlocked;
    vehicleData.vehicleID                := row.id;
    vehicleData.vehicleTypeID            := row.vehicleTypeID;
    vehicleData.vehicleTypeName          := row.vehicleTypeName;
    vehicleData.vehicleSubtypeID         := row.vehicleSubtypeID;
    vehicleData.vehicleSubtypeName       := row.vehicleSubtypeName;
    vehicleData.vehicleBrandID           := row.vehicleBrandID;
    vehicleData.vehicleBrandName         := row.vehicleBrandName;
    vehicleData.vehicleModelID           := row.vehicleModelID;
    vehicleData.vehicleModelName         := row.vehicleModelName;
    vehicleData.vehicleColorID           := row.vehicleColorID;
    vehicleData.vehicleColorName         := row.vehicleColorName;
    vehicleData.vehicleColor             := row.vehicleColor;
    vehicleData.carNumber                := row.carNumber;
    vehicleData.fuelType                 := row.fuelType;
    vehicleData.fuelTypeName             := row.fuelTypeName;

    IF ( (row.itemID = row.customerID) OR
         (row.itemUnityID = row.subsidiaryID) ) THEN
      IF (row.itemBlocked) THEN
        vehicleData.blockedLevel         := 1;
      ELSE
        IF (row.itemUnityBlocked) THEN
          vehicleData.blockedLevel       := 2;
        ELSE
          IF (row.vehicleBlocked) THEN
            vehicleData.blockedLevel     := 5;
          ELSE
            vehicleData.blockedLevel     := 0;
          END IF;
        END IF;
      END IF;
    ELSE
      IF (row.itemBlocked) THEN
        vehicleData.blockedLevel         := 1;
      ELSE
        IF (row.itemUnityBlocked) THEN
          vehicleData.blockedLevel       := 2;
        ELSE
          IF (row.customerBlocked) THEN
            vehicleData.blockedLevel     := 3;
          ELSE
            IF (row.subsidiaryBlocked) THEN
              vehicleData.blockedLevel   := 4;
            ELSE
              IF (row.vehicleBlocked) THEN
                vehicleData.blockedLevel := 5;
              ELSE
                vehicleData.blockedLevel := 0;
              END IF;
            END IF;
          END IF;
        END IF;
      END IF;
    END IF;

    vehicleData.createdAt                := row.createdAt;
    vehicleData.fullcount                := row.fullcount;

    RETURN NEXT vehicleData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getVehiclesData(1, 0, 0, 0, '', 'name', NULL, NULL, 0, 0, 0, 0);

-- Corrigimos a trigger de transações ocorridas no equipamento, de forma
-- a garantir o vínculo com a associação
CREATE OR REPLACE FUNCTION erp.equipmentTransaction()
RETURNS trigger AS $$
DECLARE
  logOperation  boolean;
  operation public.operationtype;
  reason varchar(100);
  installationStartDate date;
  cooperative  boolean;
  joined  integer;
  vehicle  record;
BEGIN
  -- Lida com a movimentação do equipamento. Faz uso da variável especial
  -- TG_OP para verificar a operação executada e de TG_WHEN para
  -- determinar o instante em que isto ocorre
  -- RAISE NOTICE 'Operation % %', TG_OP, TG_WHEN;
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica se informamos o local de armazenamento
      IF (NEW.storageLocation IS NULL) THEN
        -- Não foi informado um local de armazenamento, então definimos
        -- como estando armazenado no depósito
        NEW.storageLocation := 'StoredOnDeposit';
      ELSE
        IF (NEW.storageLocation <> 'StoredOnDeposit') THEN
          -- O local de armazenamento é inválido
          RAISE
            'Não é possível utilizar o local de armazenamento informado nesta operação'
            USING ERRCODE = 'restrict_violation';
        END IF;
      END IF;

      -- Força as demais informações em função do mesmo estar sendo
      -- armazenando num depósito
      -- 1. Não está associado à um técnico
      NEW.technicianID := null;
      -- 2. Não está associado à um prestador de serviços
      NEW.serviceProviderID := null;
      -- 3. Não está instalado em um veículo
      NEW.vehicleID := null;
      -- 3. Não pertence à nenhuma instalação
      NEW.installationID := null;
      -- 4. Não possui uma data de instalação
      NEW.installedAt := null;
      -- 5. Não está bloqueado
      NEW.blocked := false;
      -- 6. Está em pleno funcionamento
      NEW.equipmentStateID := 1;

      IF (NEW.depositID IS NULL) THEN
        RAISE
          'O ID do depósito onde o equipamento será armazenado não pode ser nulo'
          USING ERRCODE = 'not_null_violation';
      END IF;
    ELSIF (TG_WHEN = 'AFTER') THEN
      -- Registramos a aquisição do equipamento
      INSERT INTO erp.deviceOperationLogs (contractorID, deviceType,
        deviceID, operation, storageLocation, depositID, performedAt,
        performedByUserID) VALUES
        (NEW.contractorID, 'Equipment', NEW.equipmentID, 'Acquired', 
         'StoredOnDeposit', NEW.depositID, NEW.createdAt,
         NEW.createdByUserID);
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica se informamos o ID de um contratante
      IF (NEW.contractorID IS NOT NULL) THEN
        -- Verifica se estamos modificando o contratante
        IF (NEW.contractorID <> OLD.contractorID) THEN
          -- O ID do contratante nunca pode ser modificado
          RAISE
            'Você não pode modificar o contratante'
            USING ERRCODE = 'restrict_violation';
        END IF;
      END IF;

      -- Verifica se estamos bloqueando o equipamento
      IF (NEW.blocked = true) THEN
        IF (OLD.storageLocation <> 'StoredOnDeposit') THEN
          -- O equipamento deve estar de posse do contratante
          CASE (NEW.storageLocation)
            WHEN 'Installed' THEN
              reason := 'está instalado em um veículo';
            WHEN 'StoredWithTechnician' THEN
              reason := 'está de posse de um técnico';
            WHEN 'StoredWithServiceProvider' THEN
              reason := 'está de posse de um prestador de serviços';
            WHEN 'UnderMaintenance' THEN
              reason := 'está em manutenção';
            ELSE
              reason := 'foi devolvido ao fornecedor';
          END CASE;
          
          RAISE
            'Você não pode bloquear um equipamento que %', reason
            USING ERRCODE = 'restrict_violation';
        END IF;
      END IF;

      -- Verifica se estamos realizando uma movimentação do equipamento
      IF ( (OLD.storageLocation <> NEW.storageLocation) OR
           (OLD.depositID <> NEW.depositID) OR
           (OLD.technicianID <> NEW.technicianID) OR
           (OLD.serviceProviderID <> NEW.serviceProviderID) OR
           (OLD.vehicleID <> NEW.vehicleID) OR
           (OLD.equipmentStateID <> NEW.equipmentStateID) ) THEN
        -- RAISE NOTICE 'Ocorreu alguma modificação que precisa ser analisada';
        -- Verifica se o equipamento encontra-se bloqueado
        IF (OLD.blocked = true) THEN
          IF (NEW.storageLocation <> 'StoredOnDeposit') THEN
            -- Não podemos movimentar um equipamento bloqueado, então
            -- determina o motivo
            CASE (NEW.storageLocation)
              WHEN 'Installed' THEN
                reason := 'instalar';
              WHEN 'StoredWithTechnician' THEN
                reason := 'enviar para um técnico';
              WHEN 'StoredWithServiceProvider' THEN
                reason := 'enviar para um prestador de serviços';
              WHEN 'UnderMaintenance' THEN
                reason := 'enviar para manutenção';
              ELSE
                reason := 'devolver ao fornecedor';
            END CASE;

            RAISE
              'Você não pode % um equipamento que está bloqueado', reason
              USING ERRCODE = 'restrict_violation';
          END IF;
        END IF;
        
        -- Conforme o local de armazenamento, realiza as devidas
        -- checagens
        -- RAISE NOTICE 'NEW storageLocation %', NEW.storageLocation;
        CASE (NEW.storageLocation)
          WHEN 'StoredOnDeposit' THEN
            -- Quando especificado um depósito, verifica se foi informada
            -- a ID deste depósito
            IF (NEW.depositID IS NULL) THEN
              RAISE
                'O ID do depósito onde o equipamento será armazenado não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;

            -- Força as demais informações em função do mesmo estar sendo
            -- armazenando num depósito
            -- 1. Não está associado à um técnico
            NEW.technicianID := null;
            -- 2. Não está associado à um prestador de serviços
            NEW.serviceProviderID := null;
            -- 3. Não está instalado em um veículo
            NEW.vehicleID := null;
            -- 4. Não pertence à nehuma instalação
            NEW.installationID := null;
            -- 5. Não possui uma data de instalação
            NEW.installedAt := null;

            -- Verifica de onde está saíndo
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- Estamos recebendo um equipamento que estava instalado
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Você não pode informar que um equipamento retirado está em manutenção'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
              WHEN 'UnderMaintenance' THEN
                -- Estamos recebendo um equipamento que estava em
                -- manutenção
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Você deve informar a situação do recebimento do equipamento após o retorno da manutenção'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
              WHEN 'StoredWithTechnician', 'StoredWithServiceProvider' THEN
                IF (OLD.equipmentStateID <> NEW.equipmentStateID) THEN
                  RAISE
                    'Você não deve modificar a situação do equipamento numa movimentação'
                    USING ERRCODE = 'restrict_violation';
                END IF;
              WHEN 'ReturnedToSupplier' THEN
                IF (NEW.equipmentStateID <> 1) THEN
                  RAISE
                    'Você não deve receber equipamentos de um fornecedor com defeitos'
                    USING ERRCODE = 'restrict_violation';
                END IF;
              ELSE
                IF (NEW.equipmentStateID = 3) THEN
                  RAISE
                    'Você não deve indicar que um equipamento está em manutenção num depósito'
                    USING ERRCODE = 'restrict_violation';
                END IF;
            END CASE;
          WHEN 'Installed' THEN
            -- Quando especificado que está instalado em um veículo,
            -- verifica se foi informado o ID do veículo em que o mesmo
            -- foi instalado
            IF (NEW.vehicleID IS NULL) THEN
              RAISE
                'O ID do veículo onde o equipamento será instalado não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;
            IF (NEW.installationID IS NULL) THEN
              RAISE
                'O ID da instalação não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;
            IF (NEW.installedAt IS NULL) THEN
              RAISE
                'A data da instalação não pode ser nula'
                USING ERRCODE = 'not_null_violation';
            END IF;

            -- Força as demais informações em função do mesmo estar
            -- sendo instalado em um equipamento
            -- 1. Não está associado à um técnico
            NEW.technicianID := null;
            -- 2. Não está associado à um prestador de serviços
            NEW.serviceProviderID := null;

            -- Verifica de onde está saíndo
            CASE (OLD.storageLocation)
              WHEN 'ReturnedToSupplier' THEN
                -- Estamos tentando instalar um equipamento que não está
                -- mais de posse do contratante (está de posse do
                -- fornecedor)
                RAISE
                  'Você não pode instalar um equipamento que está de posse do fornecedor'
                  USING ERRCODE = 'restrict_violation';
              WHEN 'UnderMaintenance' THEN
                -- Estamos tentando instalar um equipamento que está em
                -- manutenção
                RAISE
                  'Você não pode instalar um equipamento que está em manutenção'
                  USING ERRCODE = 'restrict_violation';
              ELSE
                -- Prossegue normalmente
            END CASE;

            -- Verifica o estado do equipamento
            CASE (OLD.equipmentStateID)
              WHEN 2 THEN
                -- Estamos tentando instalar um equipamento que está com
                -- defeito
                RAISE
                  'Você não pode instalar um equipamento que está com defeito'
                  USING ERRCODE = 'restrict_violation';
              WHEN 3 THEN
                -- Estamos tentando instalar um equipamento que está em
                -- manutenção
                RAISE
                  'Você não pode instalar um equipamento que está em manutenção'
                  USING ERRCODE = 'restrict_violation';
              WHEN 4 THEN
                -- Estamos tentando instalar um equipamento que está
                -- inutilizado
                RAISE
                  'Você não pode instalar um equipamento que está em inutilizado'
                  USING ERRCODE = 'restrict_violation';
              ELSE
                -- Prossegue normalmente
            END CASE;

            IF (NEW.equipmentStateID <> 1) THEN
              RAISE
                'A situação do equipamento é inválida para uma operação de instalação'
                USING ERRCODE = 'restrict_violation';
            END IF;
          WHEN 'StoredWithTechnician' THEN
            -- Quando especificado que está de posse de um técnico, 
            -- verifica se foi informada a ID dele
            IF (NEW.technicianID IS NULL) THEN
              RAISE
                'O ID do técnico que está de posse do equipamento não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;

            -- Força as demais informações em função do mesmo estar de
            -- posse do técnico
            -- 1. Não está associado à um prestador de serviços
            NEW.serviceProviderID := null;
            -- 2. Não está instalado em um veículo
            NEW.vehicleID := null;
            -- 3. Não pertence a nenhuma instalação
            NEW.installationID := null;
            -- 4. Não possui uma data de instalação
            NEW.installedAt := null;

            -- Verifica de onde está saíndo
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- O técnico está retirando um equipamento de um veículo
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Situação do equipamento inválida'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
              WHEN 'ReturnedToSupplier' THEN
                -- Estamos tentando enviar um equipamento que não está
                -- mais de posse do contratante (está de posse do
                -- fornecedor)
                RAISE
                  'Você não pode enviar um equipamento que está de posse do fornecedor'
                  USING ERRCODE = 'restrict_violation';
              WHEN 'UnderMaintenance' THEN
                -- Estamos recebendo um equipamento que estava em
                -- manutenção
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Você deve informar a situação do recebimento do equipamento após o retorno da manutenção'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
              ELSE
                IF (OLD.equipmentStateID <> NEW.equipmentStateID) THEN
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Situação do equipamento inválida'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
            END CASE;
          WHEN 'StoredWithServiceProvider' THEN
            -- Quando especificado que está de posse de um prestador de 
            -- serviços, verifica se foi informada a ID dele
            IF (NEW.serviceProviderID IS NULL) THEN
              RAISE
                'O ID do prestador de serviços que está de posse do equipamento não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;

            -- Força as demais informações em função do mesmo estar de
            -- posse de um prestador de serviços
            -- 1. Não está associado à um técnico
            NEW.technicianID := null;
            -- 2. Não está instalado em um veículo
            NEW.vehicleID := null;
            -- 3. Não pertence à nenhuma instalação
            NEW.installationID := null;
            -- 4. Não possui uma data de instalação
            NEW.installedAt := null;

            -- Verifica de onde está saíndo
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- A prestador de serviços está retirando um equipamento
                -- de um veículo
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Situação do equipamento inválida'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
              WHEN 'ReturnedToSupplier' THEN
                -- Estamos tentando enviar um equipamento que não está
                -- mais de posse do contratante (está de posse do
                -- fornecedor)
                RAISE
                  'Você não pode enviar um equipamento que está de posse do fornecedor'
                  USING ERRCODE = 'restrict_violation';
              WHEN 'UnderMaintenance' THEN
                -- Estamos recebendo um equipamento que estava em
                -- manutenção
                IF (NEW.equipmentStateID IS NULL) THEN
                  RAISE
                    'O ID da situação do equipamento não pode ser nulo'
                    USING ERRCODE = 'not_null_violation';
                ELSE
                  IF (NEW.equipmentStateID = 3) THEN
                    RAISE
                      'Você deve informar a situação do recebimento do equipamento após o retorno da manutenção'
                      USING ERRCODE = 'restrict_violation';
                  END IF;
                END IF;
            END CASE;
          WHEN 'UnderMaintenance' THEN
            -- Quando especificado que está em manutenção, verifica se
            -- foi informado a ID do prestador de serviços
            IF (NEW.serviceProviderID IS NULL) THEN
              RAISE
                'O ID do prestador de serviços que fará a manutenção do equipamento não pode ser nulo'
                USING ERRCODE = 'not_null_violation';
            END IF;

            CASE (OLD.equipmentStateID)
              WHEN 1 THEN
                -- Estamos tentando enviar um equipamento que não está com
                -- defeito
                RAISE
                  'Você não pode enviar para manutenção um equipamento que não está com defeito'
                  USING ERRCODE = 'restrict_violation';
              WHEN 2, 3 THEN
                -- Estamos enviando um equipamento que está com defeito,
                -- então prossegue normalmente
              ELSE
                RAISE
                  'Você não pode enviar para manutenção um equipamento que está inutilizado'
                  USING ERRCODE = 'restrict_violation';
            END CASE;

            -- Força as demais informações em função do mesmo estar sendo
            -- enviado para manutenção
            -- 1. Não está associado à um técnico
            NEW.technicianID := null;
            -- 2. Não está instalado em um veículo
            NEW.vehicleID := null;
            -- 3. Não pertence à nenuma instalação
            NEW.installationID := null;
            -- 4. Não possui uma data de instalação
            NEW.installedAt := null;
            -- 5. Informa sempre que está em manutenção
            NEW.equipmentStateID := 3;

            -- Verifica de onde está saíndo
            IF (OLD.storageLocation = 'ReturnedToSupplier') THEN
              -- Estamos tentando enviar um equipamento que não está mais
              -- de posse do contratante (está de posse do fornecedor)
              RAISE
                'Você não pode enviar para manutenção um equipamento que está de posse do fornecedor'
                USING ERRCODE = 'restrict_violation';
            END IF;
          WHEN 'ReturnedToSupplier' THEN
            -- Força as demais informações em função do mesmo estar sendo
            -- devolvido ao fornecedor
            -- 1. Não está associado à um técnico
            NEW.technicianID := null;
            -- 2. Não está associado à um prestador de serviços
            NEW.serviceProviderID := null;
            -- 3. Não está instalado em um veículo
            NEW.vehicleID := null;
            -- 4. Não pertence à nenhuma instalação
            NEW.installationID := null;
            -- 5. Não possui uma data de instalação
            NEW.installedAt := null;
            -- 6. Não está indicado nenhum Slot do equipamento
            NEW.slotNumber := 0;

            -- Verifica de onde está saíndo
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- Estamos tentando devolver um equipamento que está
                -- instalado
                RAISE
                  'Você não pode devolver ao fornecedor um equipamento que ainda está instalado'
                  USING ERRCODE = 'restrict_violation';
              WHEN 'StoredWithTechnician', 'StoredWithServiceProvider', 'UnderMaintenance' THEN
                -- Estamos tentando devolver um equipamento que está de
                -- posse de terceiros
                RAISE
                  'Você não pode devolver ao fornecedor um equipamento que ainda está de posse de terceiros'
                  USING ERRCODE = 'restrict_violation';
              ELSE
                -- Prossegue normalmente
            END CASE;

            IF (NEW.equipmentStateID = 3) THEN
              RAISE
                'Situação do equipamento inválida'
                USING ERRCODE = 'restrict_violation';
            END IF;
          ELSE
            -- O tipo de armazenamento é inválido
            RAISE
              'Local de armazenamento inválido'
              USING ERRCODE = 'restrict_violation';
        END CASE;
      END IF;
    ELSIF (TG_WHEN = 'AFTER') THEN
      IF ( (OLD.storageLocation = 'Installed') AND
           (NEW.storageLocation = 'Installed') ) THEN
        -- Pode ter sido atualizada a informação de instalação
        IF ( (OLD.installedAt <> NEW.installedAt) AND
             (OLD.installationID <> NEW.installationID) ) THEN
          -- Modificamos a data de instalação e em qual instalação ocorreu
          -- no registro de instalação deste equipamento
          UPDATE erp.installationRecords
             SET installedat = NEW.installedAt,
                 installationID = NEW.installationID
           WHERE contractorID = NEW.contractorID
             AND equipmentID = NEW.equipmentID
             AND vehicleID = NEW.vehicleID
             AND uninstalledAt IS NULL;
        END IF;
      END IF;

      IF (NEW.customerPayerID IS NOT NULL) THEN
        -- Precisamos garantir que a informação de associado seja
        -- devidamente atualizada
        SELECT type.cooperative INTO cooperative
          FROM erp.entities AS customer
         INNER JOIN erp.entitiesTypes AS type USING (entityTypeID)
         WHERE customer.entityID = NEW.customerPayerID;
        IF (cooperative) THEN
          -- Garantimos que o cliente seja corretamente indicado como
          -- associado desta associação
          SELECT count(*) INTO joined
            FROM erp.affiliations AS association
           WHERE association.associationID = NEW.customerPayerID
             AND association.customerID IN (
                   SELECT V.customerID
                     FROM erp.vehicles AS V
                    WHERE V.vehicleID = NEW.vehicleID
                 )
             AND association.unjoinedAt IS NULL;

          IF (joined = 0) THEN
            FOR vehicle IN
              SELECT V.customerID,
                     V.subsidiaryID
                FROM erp.vehicles AS V
               WHERE V.vehicleID = NEW.vehicleID
            LOOP
              INSERT INTO erp.affiliations
                         (associationID, associationUnityID, customerID,
                          subsidiaryID, joinedAt)
                   VALUES (NEW.customerPayerID,
                          NEW.subsidiaryPayerID,
                          vehicle.customerID,
                          vehicle.subsidiaryID,
                          NEW.installedAt);
            END LOOP;
          END IF;
        END IF;
      END IF;

      IF (OLD.customerPayerID IS NOT NULL AND NEW.customerPayerID IS NULL) THEN
        -- Precisamos garantir que a informação de associado seja
        -- devidamente atualizada
        SELECT type.cooperative INTO cooperative
          FROM erp.entities AS customer
         INNER JOIN erp.entitiesTypes AS type USING (entityTypeID)
         WHERE customer.entityID = OLD.customerPayerID;

        IF (cooperative) THEN
          -- Garantimos que o cliente seja corretamente indicado como
          -- associado desta associação
          SELECT count(*) INTO joined
            FROM erp.affiliations AS association
           WHERE association.associationID = OLD.customerPayerID
             AND association.customerID IN (
                   SELECT V.customerID
                     FROM erp.vehicles AS V
                    WHERE V.vehicleID = OLD.vehicleID
                 )
             AND association.unjoinedAt IS NULL;

          IF (joined > 0) THEN
            FOR vehicle IN
              SELECT V.customerID,
                     V.subsidiaryID
                FROM erp.vehicles AS V
               WHERE V.vehicleID = OLD.vehicleID
            LOOP
              UPDATE erp.affiliations
                 SET unjoinedAt = CURRENT_DATE
               WHERE associationID = OLD.customerPayerID
                 AND associationUnityID = OLD.subsidiaryPayerID
                 AND customerID = vehicle.customerID
                 AND subsidiaryID = vehicle.subsidiaryID
                 AND unjoinedAt IS NULL;
            END LOOP;
          END IF;
        END IF;
      END IF;

      -- Verifica se ocorreu alguma outra modificação
      IF ( (OLD.storageLocation <> NEW.storageLocation) OR
           (OLD.depositID <> NEW.depositID) OR
           (OLD.technicianID <> NEW.technicianID) OR
           (OLD.serviceProviderID <> NEW.serviceProviderID) OR
           (OLD.vehicleID <> NEW.vehicleID) ) THEN
        -- Conforme o local de armazenamento, realiza as devidas
        -- checagens
        CASE (NEW.storageLocation)
          WHEN 'StoredOnDeposit' THEN
            -- Verifica se estamos enviando para um depósito ou é uma
            -- devolução
            CASE (OLD.storageLocation)
              WHEN 'StoredOnDeposit' THEN
                -- Verifica se o depósito foi modificado
                IF (OLD.depositID <> NEW.depositID) THEN
                  -- Força o registro da movimentação
                  logOperation := true;
                  operation    := 'Transferred';
                END IF;
              WHEN 'Installed' THEN
                -- Estamos desinstalando, então registramos a operação
                -- no log
                logOperation := true;
                operation    := 'Uninstalled';

                -- Registramos a desinstalação também no último registro
                -- deste equipamento para o veículo no qual ele estava
                -- vinculado
                UPDATE erp.installationRecords
                   SET uninstalledAt = CURRENT_DATE
                 WHERE contractorID = OLD.contractorID
                   AND equipmentID = OLD.equipmentID
                   AND vehicleID = OLD.vehicleID
                   AND uninstalledAt IS NULL;
              WHEN 'ReturnedToSupplier' THEN
                -- Estamos adquirindo novamente
                logOperation := true;
                operation    := 'Acquired';
              ELSE
                -- Estamos realizando a devolução
                logOperation := true;
                operation    := 'Returned';
            END CASE;
          WHEN 'Installed' THEN
            -- Estamos instalando, então registramos a operação
            -- no log
            logOperation := true;
            operation    := 'Installed';

            -- Registramos a instalação também deste equipamento no
            -- veículo no qual ele foi vinculado
            INSERT INTO erp.installationRecords
                   (contractorID, equipmentID, vehicleID, installationID,
                    installedAt, createdAt, createdByUserID, updatedAt,
                    updatedByUserID) VALUES
                   (NEW.contractorID, NEW.equipmentID, NEW.vehicleID,
                    NEW.installationID, NEW.installedAt, NEW.updatedAt,
                    NEW.updatedByUserID, NEW.updatedAt, NEW.updatedByUserID);

            -- Verificamos se a instalação já teve seu início determinado
            SELECT INTO installationStartDate
                   startDate
              FROM erp.installations
             WHERE installationID = NEW.installationID;
            IF (installationStartDate IS NULL) THEN
              -- A primeira instalação determina o início da prestação
              -- de serviços
              UPDATE erp.installations
                 SET startDate = NEW.installedAt,
                     updatedAt = NEW.updatedAt,
                     updatedByUserID = NEW.updatedByUserID
               WHERE installationID = NEW.installationID;
            END IF;
          WHEN 'StoredWithTechnician' THEN
            -- Verifica se estamos enviando para um técnico ou é uma
            -- desinstalação
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- Estamos desinstalando, então registramos a operação
                -- no log
                logOperation := true;
                operation    := 'Uninstalled';

                -- Registramos a desinstalação também no último registro
                -- deste equipamento para o veículo no qual ele estava
                -- vinculado
                UPDATE erp.installationRecords
                   SET uninstalledAt = CURRENT_DATE
                 WHERE contractorID = OLD.contractorID
                   AND equipmentID = OLD.equipmentID
                   AND vehicleID = OLD.vehicleID
                   AND uninstalledAt IS NULL;
              WHEN 'StoredOnDeposit', 'StoredWithServiceProvider' THEN
                -- Estamos realizando a transferência
                logOperation := true;
                operation    := 'Transferred';
              WHEN 'StoredWithTechnician' THEN
                -- Verifica se o técnico foi modificado
                IF (OLD.technicianID <> NEW.technicianID) THEN
                  -- Força o registro da movimentação
                  logOperation := true;
                  operation    := 'Transferred';
                END IF;
              WHEN 'UnderMaintenance' THEN
                -- Estamos devolvendo um equipamento que estava em
                -- manutenção
                logOperation := true;
                operation    := 'Returned';
              ELSE
                -- Prossegue normalmente
            END CASE;
          WHEN 'StoredWithServiceProvider' THEN
            -- Verifica se estamos enviando para um prestador de serviços
            -- ou é uma desinstalação e/ou devolução
            CASE (OLD.storageLocation)
              WHEN 'Installed' THEN
                -- Estamos desinstalando, então registramos a operação
                -- no log
                logOperation := true;
                operation    := 'Uninstalled';

                -- Registramos a desinstalação também no último registro
                -- deste equipamento para o veículo no qual ele estava
                -- vinculado
                UPDATE erp.installationRecords
                   SET uninstalledAt = CURRENT_DATE
                 WHERE contractorID = OLD.contractorID
                   AND equipmentID = OLD.equipmentID
                   AND vehicleID = OLD.vehicleID
                   AND uninstalledAt IS NULL;
              WHEN 'StoredOnDeposit' THEN
                -- Estamos realizando a transferência
                logOperation := true;
                operation    := 'Transferred';
              WHEN 'StoredWithServiceProvider' THEN
                -- Verifica se o prestador de serviços foi modificado
                IF (OLD.serviceProviderID <> NEW.serviceProviderID) THEN
                  -- Força o registro da movimentação
                  logOperation := true;
                  operation    := 'Transferred';
                END IF;
              WHEN 'StoredWithTechnician', 'UnderMaintenance' THEN
                -- Estamos realizando a devolução
                logOperation := true;
                operation    := 'Returned';
              ELSE
                -- Prossegue normalmente
            END CASE;
          WHEN 'ReturnedToSupplier' THEN
            -- Verifica se estamos devolvendo para o fornecedor
            CASE (OLD.storageLocation)
              WHEN 'StoredOnDeposit', 'UnderMaintenance' THEN
                -- Estamos realizando a devolução
                logOperation := true;
                operation    := 'Returned';
              ELSE
                -- Prossegue normalmente
            END CASE;
          ELSE
            -- Prossegue normalmente
        END CASE;
      ELSE
        IF (OLD.equipmentStateID <> NEW.equipmentStateID) THEN
          -- Estamos registrando um defeito
          logOperation := true;
          operation    := 'DefectDetected';
        END IF;
      END IF;

      IF (logOperation = true) THEN
        -- Registramos a operação do equipamento
        INSERT INTO erp.deviceOperationLogs (contractorID, deviceType,
          deviceID, operation, storageLocation, installedAt, slotNumber,
          equipmentStateID, technicianID, serviceProviderID, depositID,
          performedAt, performedByUserID) VALUES
          (OLD.contractorID, 'Equipment', OLD.equipmentID, operation,
           NEW.storageLocation, NEW.vehicleID, null,
           NEW.equipmentStateID, NEW.technicianID,
           NEW.serviceProviderID, NEW.depositID, NEW.updatedAt,
           NEW.updatedByUserID);
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'DELETE') THEN
    -- Removemos todas as informações do histórico deste dispositivo
    DELETE FROM erp.deviceOperationLogs
     WHERE deviceType = 'Equipment'
       AND deviceID = OLD.equipmentID;

    -- Removemos todos os registros de instalação deste equipamento
    DELETE FROM erp.installationRecords
     WHERE contractorID = OLD.contractorID
       AND equipmentID = OLD.equipmentID;

    -- Retornamos a entidade
    RETURN OLD;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;
