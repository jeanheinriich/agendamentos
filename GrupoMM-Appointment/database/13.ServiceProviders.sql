-- =====================================================================
-- Prestadores de Serviços
-- =====================================================================
-- Os prestadores de serviços são responsáveis por executar os serviços
-- técnicos no cliente. Inclui o cadastro do técnico e dos valores a
-- serem pagos a cada serviço executado.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Informações adicionais do prestador de serviços
-- ---------------------------------------------------------------------
-- Contém as informações complementares para cadastro do prestador de
-- serviços, além das já armazenadas no cadastro da entidade.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.serviceProviders (
  serviceProviderID       integer           -- ID do prestador de serviços
                          NOT NULL
                          UNIQUE,
  occupationArea          text,             -- Área de atuação
  unproductiveVisit       numeric(12, 2)    -- O valor pago ao técnico
                          NOT NULL          -- em caso de visita
                          DEFAULT 100.0000, -- improdutiva
  unproductiveVisitType   integer           -- Tipo da cobrança
                          NOT NULL          --   1: valor
                          DEFAULT 2,        --   2: porcentagem
  frustratedVisit         numeric(12, 2)    -- O valor pago ao técnico
                          NOT NULL          -- em caso de visita
                          DEFAULT 100.0000, -- frustrada
  frustratedVisitType     integer           -- Tipo da cobrança
                          NOT NULL          --   1: valor
                          DEFAULT 2,        --   2: porcentagem
  unrealizedVisit         numeric(12, 2)    -- O valor cobrado do técnico
                          NOT NULL          -- em caso de visita não
                          DEFAULT 100.0000, -- realiada
  unrealizedVisitType     integer           -- Tipo da cobrança
                          NOT NULL          --   1: valor
                          DEFAULT 2,        --   2: porcentagem
  geographicCoordinateID  integer           -- A coordenada geográfica
                          NOT NULL,         -- de referência para cálculo de deslocamento
  PRIMARY KEY (serviceProviderID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (unproductiveVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (frustratedVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (unrealizedVisitType)
    REFERENCES erp.measureTypes(measureTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (geographicCoordinateID)
    REFERENCES erp.geographicCoordinates(geographicCoordinateID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Valores pagos por deslocamento
-- ---------------------------------------------------------------------
-- O valor a ser pago ao prestador de serviços quando um técnico deste
-- executar um atendimento no cliente pelo deslocamento deste. Valores
-- de distância nulo são considerados como o valor máximo a ser pago ao
-- respectivo prestador. Neste caso, se especificarmos os valores 5, 10
-- e NULO, será considerado que de 0 até 5km, será pago um valor, acima
-- de 5 e até 10km, será pago o segundo valor e, qualquer valor acima
-- disto será pago o valor presente em nulo. Deve existir ao menos um
-- valor descrito. Caso não se deseje pagar, basta colocar o valor 0,00
-- no campo 'value'.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.displacementPaids (
  displacementPaidID  serial,         -- ID do valor pago
  serviceProviderID   integer         -- ID do prestador de serviços
                      NOT NULL,
  distance            integer         -- A distância (em km) até a qual
                      DEFAULT NULL,   -- esta faixa está compreendida
  value               numeric(8,2)    -- A taxa a ser cobrada (por padrão
                      NOT NULL        -- não cobra)
                      DEFAULT 0.00,
  CHECK (distance IS NULL OR distance > 0),
  PRIMARY KEY (displacementPaidID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Valores de serviços 
-- ---------------------------------------------------------------------
-- Contém as informações dos serviços que cada prestador está habilitado
-- à prestar e o respectivo valor a ser pago pela sua execução.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.servicePrices (
  servicePriceID    serial,         -- ID do preço por serviço
  serviceProviderID integer         -- ID do prestador de serviços
                    NOT NULL,
  billingTypeID     integer         -- ID do tipo de cobrança
                    NOT NULL,
  priceValue        numeric(12,2)   -- Valor pago
                    NOT NULL
                    DEFAULT 0.00,
  createdAt         timestamp       -- A data de inclusão do preço neste
                    NOT NULL        -- prestador de serviços
                    DEFAULT CURRENT_TIMESTAMP,
  createdByUserID   integer         -- O ID do usuário responsável pelo
                    NOT NULL,       -- cadastro
  updatedAt         timestamp       -- A data de modificação
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID   integer         -- O ID do usuário responsável pela
                    NOT NULL,       -- última modificação
  PRIMARY KEY (servicePriceID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (billingTypeID)
    REFERENCES erp.billingTypes(billingTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Técnicos por prestador de serviços
-- ---------------------------------------------------------------------
-- Contém as informações dos serviços que cada prestador está habilitado
-- à prestar e o respectivo valor a ser pago pela sua execução.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.technicians (
  technicianID            serial,         -- ID do técnico
  contractorID            integer         -- ID do contratante
                          NOT NULL,
  serviceProviderID       integer         -- ID do prestador de serviços
                          NOT NULL,
  name                    varchar(100)    -- O nome do técnico
                          NOT NULL,
  technicianIsTheProvider boolean         -- O indicativo de que este
                          DEFAULT false,  -- técnico é próprio prestador
  address                 varchar(100)    -- O endereço
                          NOT NULL,
  streetNumber            varchar(10),    -- O número da casa
  complement              varchar(30),    -- O complemento do endereço
  district                varchar(50),    -- O bairro
  cityID                  integer         -- O ID da cidade
                          NOT NULL,
  postalCode              char(9)         -- O CEP
                          NOT NULL,
  regionalDocumentType    integer         -- ID do tipo do documento
                          NOT NULL        -- (Padrão: RG)
                          DEFAULT 1,
  regionalDocumentNumber  varchar(20)     -- Número do documento
                          DEFAULT NULL,
  regionalDocumentState   char(2)         -- O estado (UF) onde foi
                          DEFAULT NULL,   -- emitido o documento
  cpf                     varchar(14)     -- O CPF
                          NOT NULL
                          DEFAULT '000.000.000-00',
  birthday                date,           -- A data de nascimento
  genderID                integer,        -- O ID do gênero
  plate                   varchar(7)      -- Placa do veículo
                          DEFAULT NULL,
  vehicleTypeID           integer         -- ID do tipo do veículo
                          DEFAULT NULL,
  vehicleBrandID          integer         -- ID da marca do veículo
                          DEFAULT NULL,
  vehicleModelID          integer         -- ID do modelo do veículo
                          DEFAULT NULL,
  vehicleColorID          integer         -- O ID da cor predominante do
                          DEFAULT NULL,   -- veículo
  blocked                 boolean         -- O indicativo de técnico
                          NOT NULL        -- bloqueado
                          DEFAULT false,
  createdAt               timestamp       -- A data de inclusão do
                          NOT NULL        -- técnico
                          DEFAULT CURRENT_TIMESTAMP,
  createdByUserID         integer         -- O ID do usuário responsável
                          NOT NULL,       -- pelo cadastro
  updatedAt               timestamp       -- A data de modificação do
                          NOT NULL        -- técnico
                          DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID         integer         -- O ID do usuário responsável
                          NOT NULL,       -- pela última modificação
  PRIMARY KEY (technicianID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (cityID)
    REFERENCES erp.cities(cityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleTypeID)
    REFERENCES erp.vehicleTypes(vehicleTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleBrandID)
    REFERENCES erp.vehicleBrands(vehicleBrandID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleModelID)
    REFERENCES erp.vehicleModels(vehicleModelID)
    ON DELETE RESTRICT,
  FOREIGN KEY (vehicleColorID)
    REFERENCES erp.vehicleColors(vehicleColorID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- E-mails por técnico
-- ---------------------------------------------------------------------
-- Contém as informações dos e-mails por técnico.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.technicianMailings (
  technicianMailingID serial,        -- O ID do e-mail
  serviceProviderID   integer        -- O ID do prestador de serviços ao
                      NOT NULL,      -- qual pertence este e-mail
  technicianID        integer        -- O ID do técnico ao qual pertence
                      NOT NULL,      -- este e-mail
  email               varchar(100)   -- O endereço de e-mail
                      NOT NULL,
  CHECK (POSITION(' ' IN email) = 0),
  PRIMARY KEY (technicianMailingID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (technicianID)
    REFERENCES erp.technicians(technicianID)
    ON DELETE CASCADE
);

-- ---------------------------------------------------------------------
-- Telefones adicionais por técnico
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones adicionais por técnico.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.technicianPhones (
  technicianPhoneID serial,        -- O ID do telefone
  serviceProviderID integer        -- O ID do prestador de serviços ao
                    NOT NULL,      -- qual pertence este e-mail
  technicianID      integer        -- O ID do técnico ao qual pertence
                    NOT NULL,      -- este e-mail
  phoneTypeID       integer        -- O ID do tipo de telefone
                    NOT NULL,
  phoneNumber       varchar(20)    -- O número do telefone
                    NOT NULL,
  PRIMARY KEY (technicianPhoneID),
  FOREIGN KEY (serviceProviderID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (technicianID)
    REFERENCES erp.technicians(technicianID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

-- ---------------------------------------------------------------------
-- Dados de prestadores de serviços
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de prestadores
-- de serviços e de seus respectivos técnicos.
-- ---------------------------------------------------------------------
CREATE TYPE erp.serviceProviderData AS
(
  entityID                 integer,
  subsidiaryID             integer,
  technicianID             integer,
  juridicalperson          boolean,
  technicianIsTheProvider  boolean,
  level                    smallint,
  active                   boolean,
  activeTechnician         boolean,
  name                     varchar(100),
  tradingName              varchar(100),
  blocked                  boolean,
  cityID                   integer,
  cityName                 varchar(50),
  occupationArea           text,
  phones                   text,
  nationalregister         varchar(18),
  blockedLevel             smallint,
  createdAt                timestamp,
  updatedAt                timestamp,
  fullcount                integer
);

CREATE OR REPLACE FUNCTION erp.getServiceProvidersData(
  FcontractorID integer, FserviceProviderID integer,
  FsearchValue varchar(100), FsearchField varchar(20), Forder varchar,
  Fstatus integer, Skip integer, LimitOf integer)
RETURNS SETOF erp.serviceProviderData AS
$$
DECLARE
  entityData  erp.serviceProviderData%rowtype;
  row  record;
  query  varchar;
  field  varchar;
  filter  varchar;
  typeFilter  varchar;
  limits  varchar;
  blockedLevel  smallint;
  lastServiceProviderID  integer;
  rowCount  int;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID := 0;
  END IF;
  IF (FserviceProviderID IS NULL) THEN
    FserviceProviderID := 0;
  END IF;
  IF (Fstatus IS NULL) THEN
    Fstatus := 0;
  END IF;
  IF (Forder IS NULL) THEN
    Forder := 'name, technicianIsTheProvider DESC, technicianname NULLS FIRST';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;

  -- Lida com o estado. Os estados possíveis são:
  --   1: inativo
  --   2: ativo
  typeFilter := '(1 = 1)';
  IF (Fstatus > 0) THEN
    IF (Fstatus = 1) THEN
      typeFilter := ' AND (numberOfActiveTechnicians = 0)';
    ELSE
      typeFilter := ' AND (numberOfActiveTechnicians > 0)';
    END IF;
  END IF;

  -- Lida com o ID do contratante
  IF (FcontractorID > 0) THEN
    -- Realiza a filtragem pelo contratante
    filter := format(' AND entity.contractorID = %s',
                     FcontractorID);
  END IF;

  -- Lida com o ID do prestador de serviços
  IF (FserviceProviderID > 0) THEN
    -- Realiza a filtragem pelo prestador de serviços
    filter := filter || format(' AND entity.entityID = %s', FserviceProviderID);
  END IF;

  -- Lida com o campo de pesquisa
  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- RAISE NOTICE 'FsearchValue IS NOT NULL';

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
            format('public.unaccented(technician.name) ' ||
                   'ILIKE public.unaccented(''%%%s%%'')',
                   FsearchValue) ||
            ')'
          ;
        WHEN 'nationalregister' THEN
          filter := filter || ' AND (' ||
            format('(regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%'')',
                   regexp_replace(FsearchValue, '\D*', '', 'g')) ||
            ' OR ' ||
            format('(regexp_replace(technician.cpf, ''\D*'', '''', ''g'') ILIKE ''%%%s%%'')',
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
                            entity.name,
                            entity.tradingName,
                            entity.entityTypeID,
                            type.name AS entityTypeName,
                            type.juridicalperson AS juridicalperson,
                            technician.technicianID,
                            technician.name AS technicianName,
                            technician.technicianIsTheProvider,
                            unity.subsidiaryID,
                            unity.cityID,
                            city.name AS cityName,
                            complement.occupationArea,
                            technician.cityID AS technicianCityID,
                            technicianCity.name AS technicianCityName,
                            unity.nationalRegister,
                            technician.cpf AS technicianCPF,
                            entity.blocked AS entityBlocked,
                            unity.blocked AS subsidiaryBlocked,
                            technician.blocked AS technicianBlocked,
                            entity.createdAt,
                            entity.updatedAt,
                            technician.createdAt AS technicianCreatedAt,
                            technician.updatedAt AS technicianUpdatedAt,
                            (
                              SELECT count(*)
                                FROM erp.technicians AS T
                               WHERE T.serviceproviderid = entity.entityID
                            ) AS numberOfTechnicians,
                            (
                              SELECT count(*)
                                FROM erp.technicians AS T
                               WHERE T.serviceproviderid = entity.entityID
                                 AND T.blocked = false
                            ) AS numberOfActiveTechnicians,
                            count(*) OVER(partition by entity.entityid) AS entityItems
                       FROM erp.entities AS entity
                      INNER JOIN erp.entitiesTypes AS type ON (entity.entityTypeID = type.entityTypeID)
                      INNER JOIN erp.subsidiaries AS unity ON (entity.entityID = unity.entityID AND unity.headOffice = true)
                      INNER JOIN erp.cities AS city ON (unity.cityID = city.cityID)
                      INNER JOIN erp.serviceProviders AS complement ON (entity.entityID = complement.serviceProviderID)
                       LEFT JOIN erp.technicians AS technician ON (entity.entityID = technician.serviceProviderID)
                       LEFT JOIN erp.cities AS technicianCity ON (technician.cityID = technicianCity.cityID)
                      WHERE entity.serviceProvider = true
                        AND entity.deleted = false
                        AND unity.deleted = false %s
                   )
                    SELECT *,
                           (numberOfActiveTechnicians > 0) AS active,
                           count(*) OVER() AS fullcount
                      FROM items
                     WHERE %s
                      ORDER BY %s %s',
                  filter, typeFilter,
                  Forder, limits);
  -- RAISE NOTICE 'SQL: %',query;

  lastServiceProviderID := 0;

  FOR row IN EXECUTE query
  LOOP
    -- RAISE NOTICE 'lastServiceProviderID: %', lastServiceProviderID;
    -- RAISE NOTICE 'entityID: %', row.entityID;
    -- RAISE NOTICE 'entity name: %', row.name;
    -- RAISE NOTICE 'entityItems: %', row.entityItems;
    -- RAISE NOTICE 'technicianID: %', row.technicianID;
    -- RAISE NOTICE 'technicianName: %', row.technicianName;

    IF (lastServiceProviderID <> row.entityID) THEN
      -- Iniciamos um novo grupo
      -- RAISE NOTICE 'Identificado um novo prestador de serviços com ID %', row.entityID;
      lastServiceProviderID := row.entityID;

      -- Indicamos que ainda não foi adicionada nenhuma linha
      rowCount := 0;

      -- Verifica se precisamos dividir esta entidade em mais de uma
      -- linha, criando uma linha de agrupamento
      IF ( (row.juridicalperson = TRUE) AND (row.technicianID IS NOT NULL) ) THEN
        -- RAISE NOTICE 'Adicionando uma linha para informar o prestador';
        -- Descrevemos aqui o prestador de serviços
        entityData.entityID                  := row.entityID;
        entityData.subsidiaryID              := row.subsidiaryID;
        entityData.technicianID              := 0;
        entityData.juridicalperson           := row.juridicalperson;
        entityData.level                     := 0;
        entityData.active                    := row.active;
        IF (row.juridicalperson) THEN
          entityData.technicianIsTheProvider := false;
        ELSE
          entityData.technicianIsTheProvider := row.technicianIsTheProvider;
        END IF;
        entityData.name                      := row.name;
        entityData.tradingName               := row.tradingName;
        entityData.blocked                   := row.entityBlocked;
        entityData.cityID                    := row.cityID;
        entityData.cityName                  := row.cityName;
        entityData.occupationArea            := row.occupationArea;
        entityData.nationalregister          := row.nationalRegister;
        IF (row.entityBlocked) THEN
          entityData.blockedLevel            := 1;
        ELSE
          entityData.blockedLevel            := 0;
        END IF;
        entityData.createdAt                 := row.createdAt;
        entityData.updatedAt                 := row.updatedAt;
        entityData.fullcount                 := row.fullcount;
        rowCount := 1;

        RETURN NEXT entityData;
      END IF;
    END IF;

    entityData.entityID                  := row.entityID;
    entityData.subsidiaryID              := row.subsidiaryID;
    entityData.technicianID              := row.technicianID;
    entityData.juridicalperson           := row.juridicalperson;
    IF (row.juridicalperson) THEN
      entityData.technicianIsTheProvider := false;
    ELSE
      entityData.technicianIsTheProvider := row.technicianIsTheProvider;
    END IF;
    entityData.active                    := row.active;
    IF ( rowCount > 0 ) THEN
      -- RAISE NOTICE 'Adicionando o técnico %', row.technicianName;
      entityData.level                   := 1;
      entityData.name                    := row.technicianName;
      entityData.tradingName             := '';
      entityData.blocked                 := row.technicianBlocked;
      entityData.cityID                  := row.technicianCityID;
      entityData.cityName                := row.technicianCityName;
      entityData.occupationArea          := '';
      entityData.nationalregister        := row.technicianCPF;
      IF (row.entityBlocked) THEN
        entityData.blockedLevel          := 1;
      ELSE
        IF (row.technicianBlocked) THEN
          entityData.blockedLevel        := 2;
        ELSE
          entityData.blockedLevel        := 0;
        END IF;
      END IF;
      entityData.createdAt               := row.technicianCreatedAt;
      entityData.updatedAt               := row.technicianUpdatedAt;
    ELSE
      -- RAISE NOTICE 'Adicionando o prestador %', row.name;
      entityData.level                   := 0;
      entityData.name                    := row.name;
      entityData.tradingName             := row.tradingName;
      entityData.blocked                 := row.entityBlocked;
      entityData.cityID                  := row.cityID;
      entityData.cityName                := row.cityName;
      entityData.occupationArea          := row.occupationArea;
      entityData.nationalregister        := row.nationalRegister;
      IF (row.entityBlocked) THEN
        entityData.blockedLevel          := 1;
      ELSE
        entityData.blockedLevel          := 0;
      END IF;
      entityData.createdAt               := row.createdAt;
      entityData.updatedAt               := row.updatedAt;
    END IF;
    entityData.fullcount                 := row.fullcount;
    rowCount := rowCount + 1;

    RETURN NEXT entityData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.getServiceProvidersData(1, 0, '', '', NULL, 0, 0, 0);

-- ---------------------------------------------------------------------
-- Dados de telefones do técnico
-- ---------------------------------------------------------------------
-- Função que recupera os telefones de um técnico em formato JSON
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getTechnicianPhones(FtechnicianID integer)
  RETURNS text AS
$$
DECLARE
  subsidiaryFilter  varchar;
  query  varchar;
  address  record;
  phones  varchar[];
BEGIN
  -- Selecionamos os telefones do técnico
  query := format('
    SELECT phonenumber
      FROM erp.technicianPhones
     WHERE technicianID = %s
     ORDER BY technicianPhoneid;',
     FtechnicianID
  );
  FOR address IN EXECUTE query
  LOOP
    -- Adicionamos o número de telefone a nossa relação de telefones
    -- RAISE NOTICE 'Telefone: %', address.phonenumber;
    phones := phones || Array[address.phonenumber];
  END LOOP;
  
  RETURN array_to_string(phones, ' / ');
END;
$$ LANGUAGE 'plpgsql';
