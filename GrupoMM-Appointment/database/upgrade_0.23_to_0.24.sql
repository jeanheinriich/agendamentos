-- =====================================================================
-- MELHORIA NA ANÁLISE DE CLIENTES E ASSOCIADOS
-- =====================================================================
-- Esta modificação visa incluir um aprimoramento na análise de clientes
-- e associados
-- ---------------------------------------------------------------------

-- ---------------------------------------------------------------------
-- Alterações no cadastro de entidades
-- ---------------------------------------------------------------------

-- 1. Criamos uma função de agregamento para determinar a data em que o
--    cliente iniciou seu relacionamento
CREATE OR REPLACE FUNCTION activeDate(prev date, next date, endDate date)
  RETURNS date AS
$$
  SELECT CASE
           WHEN $3 IS NULL THEN LEAST($1, $2)
           ELSE $1
         END
$$
LANGUAGE 'sql' IMMUTABLE;

CREATE AGGREGATE joinedDate(date, date) (
  SFUNC=activeDate,
  STYPE=date
);

-- 2. Removemos a coluna que indica novo afiliado, pois agora ela é
--    desnecessária
ALTER TABLE erp.entities
  DROP COLUMN newAffiliated;

-- 3. Alteramos a função de obtenção de dados de entidades
DROP FUNCTION erp.getEntitiesData(FcontractorID integer,
  FentityID integer, Fgroup varchar, FsearchValue varchar(100),
  FsearchField varchar(20), Forder varchar, Fstatus integer,
  Ftype integer, Skip integer, LimitOf integer);
DROP TYPE erp.entityData;

-- ---------------------------------------------------------------------
-- Dados de entidades
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de entidades
-- e de suas unidades/filiais para o gerenciamento de contratantes,
-- clientes e fornecedores
-- ---------------------------------------------------------------------
CREATE TYPE erp.entityData AS
(
  entityID           integer,
  subsidiaryID       integer,
  affiliatedID       integer,
  juridicalperson    boolean,
  cooperative        boolean,
  headOffice         boolean,
  type               smallint,
  level              smallint,
  hasRelationship    boolean,
  activeRelationship boolean,
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
  singleFilter  varchar;
  customerFilter  varchar;
  affiliationFilter  varchar;
  noRelationshipFilter  varchar;
  activeRelationQuery  varchar;
  activeSubsidiaryRelationQuery  varchar;
  conditionalFilter  varchar;
  limits  varchar;
  blockedLevel  smallint;
  lastEntityID  integer;
  lastSubsidiaryID  integer;
  rowCount  integer;
  addParentEntity boolean;
BEGIN
  -- Analisa os valores de entrada, inicializando os parâmetros de
  -- processamento
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
    Forder := 'mainOrder ASC, name, headOffice DESC, subsidiaryName, affiliatedName NULLS FIRST, affiliatedHeadOffice DESC';
  END IF;
  limits := '';
  IF (LimitOf > 0) THEN
    limits := format(' LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  END IF;

  -- Analisa as condições de filtragem primárias, que são feitas para
  -- diminuirmos a quantidade de registros a serem analisados
  singleFilter := '';

  -- 1. Filtragem por contratante
  IF (FcontractorID > 0) THEN
    -- RAISE NOTICE 'Enabling filter by contractor [%]', FcontractorID;
    CASE Fgroup
      WHEN 'contractor' THEN
        singleFilter := format(' AND entity.entityID = %s',
                               FcontractorID);
      WHEN 'customer' THEN
        customerFilter = format(' AND entity.contractorID = %s',
                                FcontractorID);
        affiliationFilter = format(' AND entity.contractorID = %s',
                                   FcontractorID);
        noRelationshipFilter = format(' AND entity.contractorID = %s',
                                      FcontractorID);
      ELSE
        singleFilter = format(' AND entity.contractorID = %s',
                              FcontractorID);
    END CASE;
  END IF;

  -- 2. Filtragem por ID da entidade
  IF (FentityID > 0) THEN
    -- RAISE NOTICE 'Enabling filter by entity [%]', FcontractorID;
    CASE Fgroup
      WHEN 'customer' THEN
        customerFilter = format(' AND entity.entityID = %s',
                                FentityID);
        affiliationFilter = format(' AND (entity.entityID = %s OR affiliated.entityID = %s)',
                                   FentityID, FentityID);
        noRelationshipFilter = format(' AND entity.entityID = %s',
                                      FentityID);
      ELSE
        singleFilter = format(' AND entity.entityID = %s',
                              FentityID);
    END CASE;
  END IF;

  -- 3. Outros critérios de filtragem
  IF (FsearchValue IS NOT NULL) THEN
    IF (FsearchValue <> '') THEN
      -- RAISE NOTICE 'FsearchValue contains %', FsearchValue;
      -- RAISE NOTICE 'Enabling filter by [%]', FsearchField;
      CASE Fgroup
        WHEN 'customer' THEN
          -- Determina o campo onde será realizada a pesquisa e monta o
          -- filtro
          CASE (FsearchField)
            WHEN 'name' THEN
              customerFilter = format(' AND ((public.unaccented(entity.name) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(entity.tradingName) ILIKE public.unaccented(''%%%s%%'')))',
                                      FsearchValue, FsearchValue);
              affiliationFilter = format(' AND ((public.unaccented(entity.name) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(entity.tradingName) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(affiliated.name) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(affiliated.tradingName) ILIKE public.unaccented(''%%%s%%'')))',
                                         FsearchValue, FsearchValue, FsearchValue, FsearchValue);
              noRelationshipFilter = format(' AND ((public.unaccented(entity.name) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(entity.tradingName) ILIKE public.unaccented(''%%%s%%'')))',
                                            FsearchValue, FsearchValue);
            WHEN 'nationalregister' THEN
              customerFilter = format(' AND regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%''',
                                      regexp_replace(FsearchValue, '\D*', '', 'g'));
              affiliationFilter = format(' AND ((regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%'') OR (regexp_replace(affiliatedUnity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%''))',
                                         regexp_replace(FsearchValue, '\D*', '', 'g'),
                                         regexp_replace(FsearchValue, '\D*', '', 'g'));
              noRelationshipFilter = format(' AND regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%''',
                                      regexp_replace(FsearchValue, '\D*', '', 'g'));
          END CASE;
        ELSE
          -- Determina o campo onde será realizada a pesquisa e monta o
          -- filtro
          CASE (FsearchField)
            WHEN 'name' THEN
              singleFilter = format(' AND ((public.unaccented(entity.name) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(entity.tradingName) ILIKE public.unaccented(''%%%s%%'')))',
                                    FsearchValue, FsearchValue);
            WHEN 'nationalregister' THEN
              singleFilter = format(' AND regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%''',
                                    regexp_replace(FsearchValue, '\D*', '', 'g'));
          END CASE;
      END CASE;
    END IF;
  END IF;

  -- Exibe o filtro a ser aplicado
  -- CASE Fgroup
  --   WHEN 'customer' THEN
  --     RAISE NOTICE 'Customer filter contains %', customerFilter;
  --     RAISE NOTICE 'Affiliation filter contains %', affiliationFilter;
  --     RAISE NOTICE 'No relationship filter contains %', noRelationshipFilter;
  --   ELSE
  --     RAISE NOTICE 'Filter contains %', singleFilter;
  -- END CASE;

  -- Em função do grupo, determina a informação de ativo
  CASE Fgroup
    WHEN 'contractor' THEN
      activeRelationQuery = 'EXISTS (SELECT 1 FROM erp.entities ' ||
        'WHERE contractorid = entity.entityID AND customer = true)';
      activeSubsidiaryRelationQuery = 'EXISTS (SELECT 1 FROM erp.entities ' ||
        'WHERE contractorid = entity.entityID AND customer = true)';
    WHEN 'supplier' THEN
      activeRelationQuery = 'EXISTS (SELECT 1 FROM erp.equipments ' ||
        'WHERE supplierid = entity.entityID UNION SELECT 1 FROM ' ||
        'erp.simcards WHERE supplierid = entity.entityID)';
      activeSubsidiaryRelationQuery = 'EXISTS (SELECT 1 FROM erp.equipments ' ||
        'WHERE supplierid = entity.entityID AND subsidiaryID = ' ||
        'unity.subsidiaryID UNION SELECT 1 FROM erp.simcards WHERE ' ||
        'supplierid = entity.entityID AND subsidiaryID = ' ||
        'unity.subsidiaryID)';
    ELSE
      activeRelationQuery = 'TRUE';
      activeSubsidiaryRelationQuery = 'TRUE';
  END CASE;

  -- Analisa outras condições de filtragem
  -- 1. Filtragem pelo tipo. Os tipos possíveis são:
  --   1. Cliente
  --   2. Associado
  -- 2. Filtragem pelo estado. Os estados possíveis são:
  --   1. Inativo
  --   2. Ativo
  CASE (Ftype)
    WHEN 1 THEN
      -- Cliente
      CASE (Fstatus)
        WHEN 1 THEN
          -- Apenas clientes inativos
          conditionalFilter := '' ||
            ' AND entity.hasRelationship = TRUE' ||
            ' AND entity.mainOrder = 1'
            ' AND entity.activeSubsidiaryRelationship = FALSE'
          ;
        WHEN 2 THEN
          -- Apenas clientes ativos
          conditionalFilter := '' ||
            ' AND entity.hasRelationship = TRUE' ||
            ' AND entity.mainOrder = 1'
            ' AND entity.activeSubsidiaryRelationship = TRUE'
          ;
        ELSE
          -- Apenas clientes
          conditionalFilter := '' ||
            ' AND entity.hasRelationship = TRUE' ||
            ' AND entity.mainOrder = 1'
          ;
      END CASE;
    WHEN 2 THEN
      -- Associado
      CASE (Fstatus)
        WHEN 1 THEN
          -- Apenas associados inativos
          conditionalFilter := '' ||
            ' AND entity.hasRelationship = TRUE' ||
            ' AND entity.mainOrder = 2'
            ' AND entity.activeAffiliation = FALSE'
          ;
        WHEN 2 THEN
          -- Apenas associados ativos
          conditionalFilter := '' ||
            ' AND entity.hasRelationship = TRUE' ||
            ' AND entity.mainOrder = 2'
            ' AND entity.activeAffiliation = TRUE'
          ;
        ELSE
          -- Apenas associados
          conditionalFilter := '' ||
            ' AND entity.hasRelationship = TRUE' ||
            ' AND entity.mainOrder = 2'
          ;
      END CASE;
    ELSE
      -- Clientes e associado
      CASE (Fstatus)
        WHEN 1 THEN
          -- Apenas inativos
          conditionalFilter := '' ||
            ' AND entity.hasRelationship = TRUE' ||
            ' AND CASE'
            '       WHEN entity.mainOrder = 2 AND entity.affiliatedID IS NULL THEN entity.activeSubsidiaryRelationship = FALSE'
            '       WHEN entity.mainOrder = 2 AND entity.affiliatedID IS NULL THEN entity.activeSubsidiaryRelationship = FALSE'
            '       WHEN entity.mainOrder = 2 AND entity.affiliatedID IS NOT NULL THEN entity.activeAffiliation = FALSE'
            '       ELSE entity.activeSubsidiaryRelationship = FALSE'
            '     END'
          ;
        WHEN 2 THEN
          -- Apenas ativos
          conditionalFilter := '' ||
            ' AND entity.hasRelationship = TRUE' ||
            ' AND CASE'
            '       WHEN entity.mainOrder = 2 AND entity.affiliatedID IS NULL THEN entity.activeSubsidiaryRelationship = TRUE'
            '       WHEN entity.mainOrder = 2 AND entity.affiliatedID IS NULL THEN entity.activeSubsidiaryRelationship = TRUE'
            '       WHEN entity.mainOrder = 2 AND entity.affiliatedID IS NOT NULL THEN entity.activeAffiliation = TRUE'
            '       ELSE entity.activeSubsidiaryRelationship = TRUE'
            '     END'
          ;
        ELSE
          -- Tudo
      END CASE;
  END CASE;
  -- RAISE NOTICE 'Conditional filter contains %', conditionalFilter;

  -- Monta a consulta
  CASE Fgroup
    WHEN 'customer' THEN
      -- Clientes: podem ser diretos, associações, associados e/ou
      --           sem nenhum relacionamento
      query := format('WITH entity AS (
                         SELECT CASE
                                  WHEN entity.entityTypeID = 3 THEN 2
                                  ELSE 1
                                END AS mainOrder,
                                entity.entityID,
                                entity.contractor,
                                entity.customer,
                                entity.serviceProvider,
                                entity.seller,
                                entity.supplier,
                                entity.name,
                                entity.tradingName,
                                entity.entityTypeID,
                                TRUE AS hasRelationship,
                                EXISTS (SELECT 1
                                          FROM erp.contracts
                                         WHERE endDate IS NULL
                                           AND customerID = entity.entityID) AS activeRelationship,
                                entity.blocked AS entityBlocked,
                                entity.createdAt,
                                entity.updatedAt,
                                unity.subsidiaryID,
                                unity.headOffice,
                                unity.name AS subsidiaryName,
                                unity.nationalRegister,
                                unity.cityID,
                                EXISTS (SELECT 1
                                          FROM erp.contracts
                                         WHERE endDate IS NULL
                                           AND customerID = entity.entityID
                                           AND subsidiaryID = unity.subsidiaryID) AS activeSubsidiaryRelationship,
                                unity.blocked AS subsidiaryBlocked,
                                unity.createdAt AS subsidiaryCreatedAt,
                                unity.updatedAt AS subsidiaryUpdatedAt,
                                NULL AS affiliatedID,
                                NULL AS affiliatedName,
                                NULL AS affiliatedTradingName,
                                FALSE AS affiliatedBlocked,
                                NULL AS affiliatedSubsidiaryID,
                                FALSE AS affiliatedHeadOffice,
                                NULL AS affiliatedNationalRegister,
                                NULL AS affiliatedCityID,
                                FALSE AS affiliatedSubsidiaryBlocked,
                                FALSE AS activeAffiliation
                           FROM erp.entities AS entity
                          INNER JOIN erp.subsidiaries AS unity ON (entity.entityID = unity.entityID)
                          INNER JOIN erp.contracts AS relationship
                             ON (entity.entityID = relationship.customerID)
                          WHERE entity.customer = true
                            AND entity.deleted = FALSE
                            AND unity.deleted = FALSE %s
                          UNION 
                         SELECT 2 AS mainOrder,
                                entity.entityID,
                                entity.contractor,
                                entity.customer,
                                entity.serviceProvider,
                                entity.seller,
                                entity.supplier,
                                entity.name,
                                entity.tradingName,
                                entity.entityTypeID,
                                TRUE AS hasRelationship,
                                EXISTS (SELECT 1
                                          FROM erp.contracts
                                         WHERE endDate IS NULL
                                           AND customerID = entity.entityID) AS activeRelationship,
                                entity.blocked AS entityBlocked,
                                entity.createdAt,
                                entity.updatedAt,
                                unity.subsidiaryID,
                                unity.headOffice,
                                unity.name AS subsidiaryName,
                                unity.nationalRegister,
                                unity.cityID,
                                EXISTS (SELECT 1
                                          FROM erp.contracts AS activeContract
                                         WHERE endDate IS NULL
                                           AND customerID = entity.entityID
                                           AND subsidiaryID = unity.subsidiaryID) AS activeSubsidiaryRelationship,
                                unity.blocked AS subsidiaryBlocked,
                                unity.createdAt AS subsidiaryCreatedAt,
                                unity.updatedAt AS subsidiaryUpdatedAt,
                                affiliation.customerID AS affiliatedID,
                                affiliated.name AS affiliatedName,
                                affiliated.tradingName AS affiliatedTradingName,
                                affiliated.blocked AS affiliatedBlocked,
                                affiliatedUnity.subsidiaryID AS affiliatedSubsidiaryID,
                                affiliatedUnity.headOffice AS affiliatedHeadOffice,
                                affiliatedUnity.nationalRegister AS affiliatedNationalRegister,
                                affiliatedUnity.cityID AS affiliatedCityID,
                                affiliatedUnity.blocked AS affiliatedSubsidiaryBlocked,
                                EXISTS (SELECT 1
                                          FROM erp.affiliations
                                         WHERE unjoinedAt IS NULL
                                           AND associationID = entity.entityID
                                           AND associationUnityID = unity.subsidiaryID
                                           AND customerID = affiliated.entityID) AS activeAffiliation
                           FROM erp.entities AS entity
                          INNER JOIN erp.subsidiaries AS unity ON (entity.entityID = unity.entityID)
                          INNER JOIN (SELECT DISTINCT associationID,
                                             associationUnityID,
                                             customerID,
                                             subsidiaryID
                                        FROM erp.affiliations) AS affiliation
                             ON (entity.entityID = affiliation.associationID AND unity.subsidiaryID = associationUnityID)
                          INNER JOIN erp.entities AS affiliated
                             ON (affiliation.customerID = affiliated.entityID)
                          INNER JOIN erp.subsidiaries AS affiliatedUnity
                             ON (affiliated.entityID = affiliatedUnity.entityID AND affiliation.subsidiaryID = affiliatedUnity.subsidiaryID)
                          WHERE entity.customer = true %s
                          UNION 
                         SELECT 3 AS mainOrder,
                                entity.entityID,
                                entity.contractor,
                                entity.customer,
                                entity.serviceProvider,
                                entity.seller,
                                entity.supplier,
                                entity.name,
                                entity.tradingName,
                                entity.entityTypeID,
                                FALSE AS hasRelationship,
                                FALSE AS activeRelationship,
                                entity.blocked AS entityBlocked,
                                entity.createdAt,
                                entity.updatedAt,
                                unity.subsidiaryID,
                                unity.headOffice,
                                unity.name AS subsidiaryName,
                                unity.nationalRegister,
                                unity.cityID,
                                FALSE AS activeSubsidiaryRelationship,
                                unity.blocked AS subsidiaryBlocked,
                                unity.createdAt AS subsidiaryCreatedAt,
                                unity.updatedAt AS subsidiaryUpdatedAt,
                                NULL AS affiliatedID,
                                NULL AS affiliatedName,
                                NULL AS affiliatedTradingName,
                                FALSE AS affiliatedBlocked,
                                NULL AS affiliatedSubsidiaryID,
                                FALSE AS affiliatedHeadOffice,
                                NULL AS affiliatedNationalRegister,
                                NULL AS affiliatedCityID,
                                FALSE AS affiliatedSubsidiaryBlocked,
                                FALSE AS activeAffiliation
                           FROM erp.entities AS entity
                          INNER JOIN erp.subsidiaries AS unity ON (entity.entityID = unity.entityID)
                           LEFT JOIN erp.contracts AS relationship
                             ON (entity.entityID = relationship.customerID)
                           LEFT JOIN erp.affiliations AS affiliation
                             ON (entity.entityID = affiliation.customerID AND unity.subsidiaryID = affiliation.subsidiaryID)
                          WHERE entity.customer = true
                            AND relationship.contractID IS NULL
                            AND affiliation.affiliationID IS NULL %s
                       )
                       SELECT entity.mainOrder,
                              entity.entityID,
                              entity.contractor,
                              entity.customer,
                              entity.serviceProvider,
                              entity.seller,
                              entity.supplier,
                              entity.name,
                              entity.tradingName,
                              entity.entityTypeID,
                              type.name AS entityTypeName,
                              type.juridicalperson AS juridicalperson,
                              type.cooperative AS cooperative,
                              entity.hasRelationship,
                              entity.activeRelationship,
                              entity.entityBlocked,
                              entity.createdAt,
                              entity.updatedAt,
                              entity.subsidiaryID,
                              entity.headOffice,
                              entity.subsidiaryName,
                              entity.nationalRegister,
                              entity.cityID,
                              city.name AS cityName,
                              entity.activeSubsidiaryRelationship,
                              entity.subsidiaryBlocked,
                              entity.subsidiaryCreatedAt,
                              entity.subsidiaryUpdatedAt,
                              entity.affiliatedID,
                              entity.affiliatedName,
                              entity.affiliatedTradingName,
                              entity.affiliatedBlocked,
                              entity.affiliatedSubsidiaryID,
                              entity.affiliatedHeadOffice,
                              entity.affiliatedNationalRegister,
                              entity.affiliatedCityID,
                              affiliationCity.name AS affiliatedCityName,
                              entity.affiliatedSubsidiaryBlocked,
                              entity.activeAffiliation,
                              count(*) OVER(partition by entity.entityID) AS entityItems,
                              (SELECT count(*) FROM erp.subsidiaries AS S WHERE S.entityID = entity.entityID) AS unityItems,
                              count(*) OVER() AS fullcount
                         FROM entity
                        INNER JOIN erp.entitiesTypes AS type ON (entity.entityTypeID = type.entityTypeID)
                        INNER JOIN erp.cities AS city ON (entity.cityID = city.cityID)
                         LEFT JOIN erp.cities AS affiliationCity ON (entity.affiliatedCityID = affiliationCity.cityID)
                        WHERE (1=1) %s
                        ORDER BY %s%s;',
        customerFilter, affiliationFilter, noRelationshipFilter,
        conditionalFilter, Forder, limits
      );
    WHEN 'serviceprovider' THEN
      -- Prestadores de serviço: podem ser prestadores de serviços e
      --                         técnicos
    ELSE
      -- Contratantes, vendedores e fornecedores: são diretos, possuindo
      -- apenas a matriz e a filial
      query := format('WITH entity AS (
                         SELECT 1 AS mainOrder,
                                entity.entityID,
                                entity.contractor,
                                entity.customer,
                                entity.serviceProvider,
                                entity.seller,
                                entity.supplier,
                                entity.name,
                                entity.tradingName,
                                entity.entityTypeID,
                                TRUE AS hasRelationship,
                                %s AS activeRelationship,
                                entity.blocked AS entityBlocked,
                                entity.createdAt,
                                entity.updatedAt,
                                unity.subsidiaryID,
                                unity.headOffice,
                                unity.name AS subsidiaryName,
                                unity.nationalRegister,
                                unity.cityID,
                                %s AS activeSubsidiaryRelationship,
                                unity.blocked AS subsidiaryBlocked,
                                unity.createdAt AS subsidiaryCreatedAt,
                                unity.updatedAt AS subsidiaryUpdatedAt
                           FROM erp.entities AS entity
                          INNER JOIN erp.subsidiaries AS unity ON (entity.entityID = unity.entityID)
                          WHERE entity.%s = true
                            AND entity.deleted = FALSE
                            AND unity.deleted = FALSE %s
                       )
                       SELECT entity.mainOrder,
                              entity.entityID,
                              entity.contractor,
                              entity.customer,
                              entity.serviceProvider,
                              entity.seller,
                              entity.supplier,
                              entity.name,
                              entity.tradingName,
                              entity.entityTypeID,
                              type.name AS entityTypeName,
                              type.juridicalperson AS juridicalperson,
                              type.cooperative AS cooperative,
                              entity.hasRelationship,
                              entity.activeRelationship,
                              entity.entityBlocked,
                              entity.createdAt,
                              entity.updatedAt,
                              entity.subsidiaryID,
                              entity.headOffice,
                              entity.subsidiaryName,
                              entity.nationalRegister,
                              entity.cityID,
                              city.name AS cityName,
                              entity.activeSubsidiaryRelationship,
                              entity.subsidiaryBlocked,
                              entity.subsidiaryCreatedAt,
                              entity.subsidiaryUpdatedAt,
                              NULL AS affiliatedID,
                              NULL AS affiliatedName,
                              NULL AS affiliatedTradingName,
                              FALSE AS affiliatedBlocked,
                              NULL AS affiliatedSubsidiaryID,
                              FALSE AS affiliatedHeadOffice,
                              NULL AS affiliatedNationalRegister,
                              NULL AS affiliatedCityID,
                              NULL AS affiliatedCityName,
                              FALSE AS affiliatedSubsidiaryBlocked,
                              FALSE AS activeAffiliation,
                              count(*) OVER(partition by entity.entityID) AS entityItems,
                              (SELECT count(*) FROM erp.subsidiaries AS S WHERE S.entityID = entity.entityID) AS unityItems,
                              count(*) OVER() AS fullcount
                         FROM entity
                        INNER JOIN erp.entitiesTypes AS type ON (entity.entityTypeID = type.entityTypeID)
                        INNER JOIN erp.cities AS city ON (entity.cityID = city.cityID)
                        WHERE (1=1) %s
                        ORDER BY %s%s;',
        activeRelationQuery, activeSubsidiaryRelationQuery, Fgroup,
        singleFilter, conditionalFilter, Forder, limits
      );
  END CASE;
  -- RAISE NOTICE 'SQL: %',query;

  lastEntityID := 0;
  lastSubsidiaryID := 0;

  FOR row IN EXECUTE query
  LOOP
    -- RAISE NOTICE 'mainOrder: %', row.mainOrder;
    -- RAISE NOTICE 'lastEntityID: %', lastEntityID;
    -- RAISE NOTICE 'entityID: %', row.entityID;
    -- RAISE NOTICE 'name: %', row.name;
    -- RAISE NOTICE 'entityItems: %', row.entityItems;
    -- RAISE NOTICE 'unityItems: %', row.unityItems;

    IF (lastEntityID <> row.entityID) THEN
      -- Iniciamos a descrição de uma nova entidade
      lastEntityID := row.entityID;
      -- RAISE NOTICE 'Start new entity';

      -- Inicaliza a contagem de linhas desta entidade
      rowCount := 0;

      -- Estamos descrevendo uma nova entidade. Ela pode conter
      -- informações de matriz e filial ou titular e dependente.
      -- Então analisa se temos mais de uma unidade nesta entidade.
      IF (row.unityItems > 1) THEN
        -- Precisamos acrescentar a linha descrevendo a entidade
        -- principal separada das unidades/filiais
        -- RAISE NOTICE 'Add line with parent information';
        entityData.entityID            := row.entityID;
        entityData.subsidiaryID        := 0;
        entityData.affiliatedID        := 0;
        entityData.juridicalperson     := row.juridicalperson;
        entityData.cooperative         := row.cooperative;
        entityData.headOffice          := false;
        entityData.type                := 1;
        entityData.level               := 0;
        entityData.hasRelationship     := row.hasRelationship;
        entityData.activeRelationship  := row.activeRelationship;
        IF (row.cooperative) THEN
          entityData.activeAssociation := row.activeRelationship;
        ELSE
          entityData.activeAssociation := FALSE;
        END IF;
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

      rowCount := 1;
    END IF;

    IF (lastSubsidiaryID <> row.subsidiaryID) THEN
      -- Iniciamos a descrição de uma nova unidade/filial
      lastSubsidiaryID := row.subsidiaryID;
      -- RAISE NOTICE 'Start new subsidiary';

      -- Estamos descrevendo uma nova subsidiaria de uma entidade. Caso
      -- estejamos descrevendo um associado, será necessário adicionar
      -- uma linha para separar os dados deste dos da sua associação.
      IF ((row.mainOrder = 2) AND (row.affiliatedID IS NOT NULL)) THEN
        -- RAISE NOTICE 'Add line with parent information';
        
        -- Informa os dados da unidade
        entityData.entityID              := row.entityID;
        entityData.subsidiaryID          := row.subsidiaryID;
        entityData.affiliatedID          := 0;
        entityData.juridicalperson       := row.juridicalperson;
        entityData.cooperative           := row.cooperative;
        entityData.headOffice            := row.headOffice;
        entityData.type                  := 1;
        IF (row.unityItems > 1) THEN
          entityData.level               := 1;
        ELSE
          entityData.level               := 2;
        END IF;
        entityData.hasRelationship       := row.hasRelationship;
        IF (row.unityItems > 1) THEN
          entityData.activeRelationship  := row.activeSubsidiaryRelationship;
        ELSE
          entityData.activeRelationship  := row.activeRelationship;
        END IF;
        IF (row.cooperative) THEN
          IF (row.unityItems > 1) THEN
            entityData.activeAssociation := row.activeSubsidiaryRelationship;
          ELSE
            entityData.activeAssociation := row.activeRelationship;
          END IF;
        ELSE
          entityData.activeAssociation   := FALSE;
        END IF;
        IF (row.unityItems > 1) THEN
          -- RAISE NOTICE 'Use subsidiary data %', row.subsidiaryName;
          entityData.blocked             := row.subsidiaryBlocked;
          entityData.name                := row.subsidiaryName;
          entityData.tradingName         := '';
        ELSE
          -- RAISE NOTICE 'Use entity data %', row.name;
          entityData.blocked             := (row.subsidiaryBlocked OR row.entityBlocked);
          entityData.name                := row.name;
          entityData.tradingName         := row.tradingName;
        END IF;
        entityData.cityID                := row.cityID;
        entityData.cityName              := row.cityName;
        entityData.nationalregister      := row.nationalregister;
        IF (row.entityBlocked) THEN
          entityData.blockedLevel        := 1;
        ELSE
          IF (row.subsidiaryBlocked) THEN
            entityData.blockedLevel      := 2;
          ELSE
            entityData.blockedLevel      := 0;
          END IF;
        END IF;
        IF (row.unityItems > 1) THEN
          entityData.createdAt           := row.subsidiaryCreatedAt;
          entityData.updatedAt           := row.subsidiaryUpdatedAt;
        ELSE
          entityData.createdAt           := row.createdAt;
          entityData.updatedAt           := row.updatedAt;
        END IF;
        entityData.fullcount             := row.fullcount;

        RETURN NEXT entityData;

        rowCount := rowCount + 1;
      END IF;
    END IF;

    -- Informa os dados da entidade
    -- RAISE NOTICE 'Add row';
    entityData.entityID                 := row.entityID;
    entityData.subsidiaryID             := row.subsidiaryID;
    entityData.affiliatedID             := row.affiliatedID;
    entityData.juridicalperson          := row.juridicalperson;
    entityData.cooperative              := row.cooperative;
    entityData.headOffice               := row.headOffice;
    IF (row.mainOrder = 2) THEN
      entityData.type                   := 2;
      entityData.hasRelationship        := true;
      IF (row.affiliatedID IS NOT NULL) THEN
        entityData.activeRelationship   := row.activeAffiliation;
      ELSE
        IF (row.unityItems > 1) THEN
          entityData.activeRelationship := row.activeSubsidiaryRelationship;
        ELSE
          entityData.activeRelationship := row.activeRelationship;
        END IF;
      END IF;

      IF (row.unityItems > 1) THEN
        entityData.activeAssociation    := row.activeSubsidiaryRelationship;
      ELSE
        entityData.activeAssociation    := row.activeRelationship;
      END IF;
    ELSE
      entityData.type                   := 1;
      entityData.hasRelationship        := row.hasRelationship;
      IF (row.unityItems > 1) THEN
        entityData.activeRelationship   := row.activeSubsidiaryRelationship;
      ELSE
        entityData.activeRelationship   := row.activeRelationship;
      END IF;
      entityData.activeAssociation      := false;
    END IF;
    IF (row.affiliatedID IS NOT NULL) THEN
      -- RAISE NOTICE 'Row contains affiliated data';
      entityData.level                  := 3;
      entityData.name                   := row.affiliatedName;
      entityData.tradingName            := row.affiliatedTradingName;
      entityData.blocked                := row.affiliatedBlocked;
      entityData.cityID                 := row.affiliatedCityID;
      entityData.cityName               := row.affiliatedCityName;
      entityData.nationalregister       := row.affiliatedNationalregister;
      entityData.createdAt              := row.createdAt;
      entityData.updatedAt              := row.updatedAt;

      IF (row.entityBlocked) THEN
        entityData.blockedLevel         := 1;
      ELSE
        IF (row.affiliatedBlocked) THEN
          entityData.blockedLevel       := 3;
        ELSE
          IF (row.subsidiaryBlocked) THEN
            entityData.blockedLevel     := 2;
          ELSE
            entityData.blockedLevel     := 0;
          END IF;
        END IF;
      END IF;
    ELSE
      -- RAISE NOTICE 'Row contains entity data';
      IF (row.unityItems > 1) THEN
        entityData.level                := 1;
        entityData.name                 := row.subsidiaryName;
        entityData.tradingName          := '';
        entityData.blocked              := row.subsidiaryBlocked;
        entityData.createdAt            := row.subsidiaryCreatedAt;
        entityData.updatedAt            := row.subsidiaryUpdatedAt;
      ELSE
        entityData.level                := 2;
        entityData.name                 := row.name;
        entityData.tradingName          := row.tradingName;
        entityData.blocked              := (row.subsidiaryBlocked OR row.entityBlocked);
        entityData.createdAt            := row.createdAt;
        entityData.updatedAt            := row.updatedAt;
      END IF;
      entityData.cityID                 := row.cityID;
      entityData.cityName               := row.cityName;
      entityData.nationalregister       := row.nationalregister;

      IF (row.entityBlocked) THEN
        entityData.blockedLevel         := 1;
      ELSE
        IF (row.subsidiaryBlocked) THEN
          entityData.blockedLevel       := 2;
        ELSE
          entityData.blockedLevel       := 0;
        END IF;
      END IF;
    END IF;
    entityData.fullcount                := row.fullcount;

    RETURN NEXT entityData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getEntitiesData(1, 0, 'contractor', '', 'name', NULL, 0, 0, 0, 10);
-- SELECT * FROM erp.getEntitiesData(1, 0, 'customer', '', 'name', NULL, 0, 0, 0, 10);

SELECT * FROM erp.getEntitiesData(1, 0, 'customer', '', 'name', NULL, 1, 0, 0, 10);