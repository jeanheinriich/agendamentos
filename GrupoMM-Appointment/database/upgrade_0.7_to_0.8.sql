-- Acrescentamos o indicativo de associado à unidade/filial para
-- diferenciaros registros de associados dos das unidades/filiais da
-- cooperativa
ALTER TABLE erp.subsidiaries
  ADD COLUMN affiliated boolean DEFAULT false;

-- Acrescentamos o campo indicativo do usuário responsável pela remoção
-- do registro na tabela de unidade/filial
ALTER TABLE erp.subsidiaries
  ADD COLUMN deletedByUserID integer DEFAULT NULL
    REFERENCES erp.users(userID)
      ON DELETE RESTRICT;

-- Retirado o campo de e-mail da unidade/filial pois o mesmo agora será
-- armazenado em outra tabela
ALTER TABLE erp.subsidiaries
  DROP COLUMN email;

-- Acrescentamos os campos de eliminação do registro na tabela de
-- entidades
ALTER TABLE erp.entities
  ADD COLUMN deleted boolean NOT NULL DEFAULT false;
ALTER TABLE erp.entities
  ADD COLUMN deletedAt timestamp DEFAULT NULL;
ALTER TABLE erp.entities
  ADD COLUMN deletedByUserID integer DEFAULT NULL
    REFERENCES erp.users(userID)
      ON DELETE RESTRICT;

-- Acrescentamos os campos de eliminação do registro na tabela de
-- veículos
ALTER TABLE erp.vehicles
  ADD COLUMN deleted boolean NOT NULL DEFAULT false;
ALTER TABLE erp.vehicles
  ADD COLUMN deletedAt timestamp DEFAULT NULL;
ALTER TABLE erp.vehicles
  ADD COLUMN deletedByUserID integer DEFAULT NULL
    REFERENCES erp.users(userID)
      ON DELETE RESTRICT;


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
  ( 4, 'Emissão de recibo', 'quitter');

ALTER SEQUENCE erp.systemactions_systemactionid_seq RESTART WITH 5;

-- ---------------------------------------------------------------------
-- Perfis de recebimento de mensagens
-- ---------------------------------------------------------------------
-- Armazena as informações de perfis que descrevem os tipos de mensagens
-- a serem recebidas pelos clientes para eventos do sistma.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.mailingProfiles (
  mailingProfileID  serial,       -- ID do tipo de perfil
  contractorID      integer       -- ID do contratante
                    NOT NULL,
  name              varchar(30)   -- Nome do perfil
                    NOT NULL,
  description       text          -- Descrição do perfil
                    NOT NULL,
  PRIMARY KEY (mailingProfileID), -- O indice primário
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

INSERT INTO erp.mailingProfiles (mailingProfileID, contractorID, name, description) VALUES
  (1, 1, 'Administrativo', 'Recebe notificações de atendimentos agendados e executados'),
  (2, 1, 'Comercial', 'Não recebe notificações. É utilizado apenas para relacionamento comercial.'),
  (3, 1, 'Controladoria', 'Recebe todas as notificações para fins de acompanhamento.'),
  (4, 1, 'Financeiro', 'Recebe apenas notificações de cunho financeiro.'),
  (5, 1, 'Técnico', 'Recebe apenas notificações de cunho técnico.');

ALTER SEQUENCE erp.mailingprofiles_mailingprofileid_seq RESTART WITH 6;

-- ---------------------------------------------------------------------
-- Eventos do sistema por perfil
-- ---------------------------------------------------------------------
-- Contém as informações dos eventos do sistema que disparam o envio de
-- mensagens para cada perfil.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.actionsPerProfiles (
  actionPerProfileID  serial,  -- O ID da ação por perfil
  contractorID        integer       -- ID do contratante
                      NOT NULL,
  mailingProfileID    integer  -- O ID do perfil
                      NOT NULL,
  systemActionID      integer  -- O ID da ação do sistema
                      NOT NULL,
  PRIMARY KEY (actionPerProfileID),
  UNIQUE (mailingProfileID, systemActionID),
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (mailingProfileID)
    REFERENCES erp.mailingProfiles(mailingProfileID)
    ON DELETE CASCADE,
  FOREIGN KEY (systemActionID)
    REFERENCES erp.systemActions(systemActionID)
    ON DELETE RESTRICT
);

INSERT INTO erp.actionsPerProfiles (contractorID, mailingProfileID, systemActionID) VALUES
  (1, 1, 1),
  (1, 1, 2),
  (1, 3, 1),
  (1, 3, 2),
  (1, 3, 3),
  (1, 3, 4),
  (1, 4, 3),
  (1, 4, 4),
  (1, 5, 2);

-- Removemos a tabela de contatos
DROP TABLE erp.contacts;

-- Acrescentamos a nova tabela de endereços de e-mails por unidade/filial

-- ---------------------------------------------------------------------
-- Endereços de e-mails por unidade/filial
-- ---------------------------------------------------------------------
-- Contém as informações dos endereços de e-mails e telefones das
-- empresas por unidade/filial, bem como a informação de perfil da
-- informação de contato, que permite definir se o mesmo será utilizado
-- para o envio de mensagens na ocorrência de determinados eventos da
-- operação.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.mailingAddresses (
  mailingAddressID  serial,        -- O ID do endereço
  entityID          integer        -- O ID da entidade à qual pertence
                    NOT NULL,      -- este endereço
  subsidiaryID      integer        -- O ID da unidade/filial à qual
                    NOT NULL,      -- pertence este endereço
  mailingProfileID  integer        -- O ID do perfil deste endereço
                    NOT NULL,
  name              varchar(50)    -- O nome do contato correspondente
                    NOT NULL,      -- ao endereço
  attribute         varchar(50),   -- Atributo do endereço, como o nome
                                   -- do departamento ou observação
  email             varchar(100),  -- Email
  phoneTypeID       integer        -- O ID do tipo de telefone
                    NOT NULL,
  phoneNumber       varchar(20),   -- O telefone deste contato
  createdAt         timestamp      -- A data de criação do registro
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  createdByUserID   integer        -- O ID do usuário responsável
                    NOT NULL,      -- pelo cadastro deste registro
  updatedAt         timestamp      -- A data de modificação do registro
                    NOT NULL
                    DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID   integer        -- O ID do usuário responsável pela
                    NOT NULL,      -- última modificação neste registro
  deleted           boolean        -- O indicativo de que o registro foi
                    NOT NULL       -- removido
                    DEFAULT false,
  deletedAt         timestamp      -- A data de remoção do registro
                    DEFAULT NULL,
  deletedByUserID   integer        -- O ID do usuário responsável pela
                    DEFAULT NULL,  -- remoção do registro
  PRIMARY KEY (mailingAddressID),
  CONSTRAINT checkHaveLeastOneContactData
    CHECK (email IS NOT NULL OR phoneNumber IS NOT NULL),
  FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE CASCADE,
  FOREIGN KEY (mailingProfileID)
    REFERENCES erp.mailingProfiles(mailingProfileID)
    ON DELETE RESTRICT,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (deletedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

INSERT INTO erp.mailingAddresses (mailingAddressID, entityID,
    subsidiaryID, mailingProfileID, name, attribute, email,
    phoneTypeID, phoneNumber, createdByUserID, updatedByUserID) VALUES
  (1, 1, 1, 4, 'Emerson Cavalcanti', 'Financeiro',
    'emersoncavalcanti@gmail.com', 1, '(11) 2925-9187', 1, 1);

ALTER SEQUENCE erp.mailingaddresses_mailingaddressid_seq RESTART WITH 2;

-- Atualizamos as funções que sofreram modificações

-- Função que recupera as carteiras de usuários, onde eliminamos usuários
-- de empresas apagadas
CREATE OR REPLACE FUNCTION erp.UsersWallets(FcontractorID integer,
  FminimumLevel integer, FgroupID integer, FsearchValue varchar(50),
  FsearchField varchar(20), FOrder varchar, Skip integer,
  LimitOf integer)
RETURNS SETOF erp.userWallet AS
$$
DECLARE
  wallet       erp.userWallet%rowtype;
  row          record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  blockedLevel integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    -- Não filtramos por contratante
    FcontractorID = 0;
  END IF;
  IF (FminimumLevel IS NULL) THEN
    -- Não limitamos os tipos de usuário
    FminimumLevel = 0;
  END IF;
  IF (FgroupID IS NULL) THEN
    -- Não filtramos por grupo
    FminimumLevel = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    -- Definimos uma ordem padrão
    FOrder := 'contractorname, n, entityname, name';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  IF (FcontractorID > 0) THEN
    filter := format('contractorID = %s',
                 FcontractorID);
  ELSE
    filter := format('contractorID >= %s',
                 FcontractorID);
  END IF;
  IF (FminimumLevel > 0) THEN
    filter :=  filter || format(' AND groupID >= %s',
                 FminimumLevel);
  END IF;
  IF (FgroupID > 0) THEN
    filter :=  filter || format(' AND groupID = %s',
                 FgroupID);
  END IF;
  IF (FsearchValue IS NULL) THEN
    -- Não adiciona nenhum filtro
  ELSE
    -- Verificamos se temos algum termo a ser pesquisado
    IF (FsearchValue = '') THEN
      -- Como não temos nada a pesquisar, então ignora
    ELSE
      -- Adiciona o filtro pelo campo indicado
      filter :=  filter || format(' AND public.unaccented(%s) ILIKE public.unaccented(''%%%s%%'')',
                                  FsearchField, FsearchValue);
    END IF;
  END IF;

  -- Monta a consulta
  query := format('WITH RECURSIVE t(level) AS
                         ((SELECT 1 AS userlevel,
                                  users.entityid AS contractorid,
                                  contractor.name AS contractorname,
                                  contractor.blocked AS contractorblocked,
                                  contractor.entityid AS entityid,
                                  contractor.name AS entityname,
                                  contractor.blocked AS entityblocked,
                                  ''Contractor'' AS entityType,
                                  users.userid AS id,
                                  users.name,
                                  users.role,
                                  users.username,
                                  users.groupid,
                                  groups.name AS groupname,
                                  users.phonenumber,
                                  users.email,
                                  users.blocked AS userBlocked,
                                  users.expires,
                                  users.expiresat,
                                  users.createdat,
                                  users.updatedat,
                                  users.lastlogin,
                                  users.forcenewpassword
                             FROM erp.users
                            INNER JOIN erp.entities AS contractor USING (entityid)
                            INNER JOIN erp.groups USING (groupid)
                            WHERE users.contractorid = 0
                            ORDER BY users.name)
                            UNION
                          (SELECT 2 AS userlevel,
                                  users.contractorid,
                                  contractor.name AS contractorname,
                                  contractor.blocked AS contractorblocked,
                                  contractor.entityid AS entityid,
                                  contractor.name AS entityname,
                                  contractor.blocked AS entityblocked,
                                  ''Contractor'' AS entityType,
                                  users.userid AS id,
                                  users.name,
                                  users.role,
                                  users.username,
                                  users.groupid,
                                  groups.name AS groupname,
                                  users.phonenumber,
                                  users.email,
                                  users.blocked AS userBlocked,
                                  users.expires,
                                  users.expiresat,
                                  users.createdat,
                                  users.updatedat,
                                  users.lastlogin,
                                  users.forcenewpassword
                             FROM erp.users
                            INNER JOIN erp.entities AS contractor USING (entityid)
                            INNER JOIN erp.groups USING (groupid)
                            WHERE users.contractorid > 0
                              AND users.contractorid = users.entityid
                            ORDER BY users.name)
                            UNION
                          (SELECT 3 AS userlevel,
                                  users.contractorid,
                                  contractor.name AS contractorname,
                                  contractor.blocked AS contractorblocked,
                                  entity.entityid AS entityid,
                                  entity.name AS entityname,
                                  entity.blocked AS entityblocked,
                                  CASE
                                    WHEN entity.customer THEN ''Customer''
                                    WHEN entity.supplier AND entity.serviceProvider THEN ''ServiceProvider''
                                    WHEN entity.supplier AND NOT entity.serviceProvider THEN ''Supplier''
                                    ELSE ''Contractor''
                                  END AS entityType,
                                  users.userid AS id,
                                  users.name,
                                  users.role,
                                  users.username,
                                  users.groupid,
                                  groups.name AS groupname,
                                  users.phonenumber,
                                  users.email,
                                  users.blocked AS userBlocked,
                                  users.expires,
                                  users.expiresat,
                                  users.createdat,
                                  users.updatedat,
                                  users.lastlogin,
                                  users.forcenewpassword
                             FROM erp.users
                            INNER JOIN erp.entities AS contractor ON (contractor.entityid = users.contractorid)
                            INNER JOIN erp.entities AS entity ON (entity.entityid = users.entityid)
                            INNER JOIN erp.groups USING (groupid)
                            WHERE users.contractorid > 0
                              AND users.contractorid <> users.entityid
                              AND entity.deleted = false
                            ORDER BY entity.name, users.name))
                      SELECT *,
                             count(*) OVER() AS fullcount
                        FROM t
                       WHERE %s
                       ORDER BY %s %s',
                  filter, FOrder, limits);

  -- Executa a consulta e retorna os dados solicitados
  FOR row IN EXECUTE query
  LOOP
    wallet.level              := row.level;
    wallet.contractorID       := row.contractorID;
    wallet.contractorName     := row.contractorName;
    wallet.contractorBlocked  := row.contractorBlocked;
    wallet.entityID           := row.entityID;
    wallet.entityName         := row.entityName;
    wallet.entityBlocked      := row.entityBlocked;
    wallet.entityType         := row.entityType;
    wallet.userID             := row.id;
    wallet.name               := row.name;
    wallet.role               := row.role;
    wallet.username           := row.username;
    wallet.groupID            := row.groupID;
    wallet.groupName          := row.groupName;
    wallet.phoneNumber        := row.phoneNumber;
    wallet.email              := row.email;
    wallet.userBlocked        := row.userBlocked;

    -- Determina o nível de bloqueio. O nível de bloqueio mais baixo é o
    -- do usuário, seguido da empresa e por último o contratante
    blockedLevel := 0;
    -- Verifica se é um contratante
    IF (row.contractorID = row.entityID) THEN
      IF (row.userBlocked) THEN
        blockedLevel := blockedLevel|1;
      END IF;
      IF (row.contractorBlocked) THEN
        blockedLevel := blockedLevel|4;
      END IF;
    ELSE
      IF (row.userBlocked) THEN
        blockedLevel := blockedLevel|1;
      END IF;
      IF (row.entityBlocked) THEN
        blockedLevel := blockedLevel|2;
      END IF;
      IF (row.contractorBlocked) THEN
        blockedLevel := blockedLevel|4;
      END IF;
    END IF;
    wallet.blockedLevel       := blockedLevel;
    wallet.expires            := row.expires;
    wallet.expiresAt          := row.expiresAt;
    wallet.createdAt          := row.createdAt;
    wallet.updatedAt          := row.updatedAt;
    wallet.lastLogin          := row.lastLogin;
    wallet.forceNewPassword   := row.forceNewPassword;
    wallet.fullcount          := row.fullcount;

    RETURN NEXT wallet;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- Função que recupera os técnicos, eliminando àqueles de empresas
-- apagadas
CREATE TYPE erp.technician AS
(
  id                   integer,
  name                 varchar(50),
  role                 varchar(50),
  phoneNumber          varchar(16),
  serviceProviderID    integer,
  serviceProviderName  varchar(100),
  email                varchar(50),
  fullcount            integer
);

CREATE OR REPLACE FUNCTION erp.getTechnicians(FcontractorID integer,
  FsearchValue varchar(50), LimitOf integer)
RETURNS SETOF erp.technician AS
$$
DECLARE
  technician   erp.technician%rowtype;
  row          record;
  query        varchar;
  filter       varchar;
  limits       varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    -- O contratante é inválido
    RAISE
      'Informe o contratante'
      USING ERRCODE = 'restrict_violation';
  END IF;
  IF (FcontractorID > 0) THEN
    filter := format('users.contractorID = %s',
                     FcontractorID);
  ELSE
    -- O contratante é inválido
    RAISE
      'Informe o contratante'
      USING ERRCODE = 'restrict_violation';
  END IF;
  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- Monta o filtro
      filter :=  filter || format(' AND public.unaccented(users.name) ILIKE public.unaccented(''%%%s%%'')',
                                  FsearchValue);
    END IF;
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s',
                     LimitOf);
  ELSE
    limits := '';
  END IF;

  -- Monta a consulta
  query := format('SELECT users.userID,
                          users.name,
                          users.role,
                          users.username,
                          users.phoneNumber,
                          users.contractorID,
                          CASE
                            WHEN users.contractorID = 0 THEN ''Administradores ERP''
                            ELSE (SELECT E.name FROM erp.entities AS E WHERE E.entityID = users.contractorID)
                          END AS contractorName,
                          users.entityID AS serviceProviderID,
                          entities.name AS serviceProviderName,
                          users.email,
                          entities.blocked AS entityBlocked,
                          users.blocked AS userBlocked,
                          users.expires,
                          users.expiresAt,
                          count(*) OVER() AS fullcount
                     FROM erp.users
                    INNER JOIN erp.entities USING (entityID)
                    WHERE %s
                      AND ((users.blocked = false) AND
                           ((users.expires = false) OR ((users.expires = true) AND (users.expiresAt > CURRENT_DATE))) AND
                           (entities.blocked = false))
                      AND entities.deleted = false
                      AND users.groupID = 5
                    ORDER BY users.name %s;',
                  filter, limits);
  FOR row IN EXECUTE query
  LOOP
    technician.id                   := row.userID;
    technician.name                 := row.name;
    technician.role                 := row.role;
    technician.phoneNumber          := row.phoneNumber;
    technician.serviceProviderID    := row.serviceProviderID;
    technician.serviceProviderName  := row.serviceProviderName;
    technician.email                := row.email;
    technician.fullcount            := row.fullcount;

    RETURN NEXT technician;
  END loop;
END
$$
LANGUAGE 'plpgsql';


-- Função que recupera a informação de técnicos
CREATE OR REPLACE FUNCTION erp.getServiceProviders(FcontractorID integer,
  FsearchValue varchar(50), LimitOf integer)
RETURNS SETOF erp.serviceprovider AS
$$
DECLARE
  serviceprovider   erp.serviceprovider%rowtype;
  row          record;
  query        varchar;
  filter       varchar;
  limits       varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    -- O contratante é inválido
    RAISE
      'Informe o contratante'
      USING ERRCODE = 'restrict_violation';
  END IF;
  IF (FcontractorID > 0) THEN
    filter := format('entities.contractorID = %s',
                     FcontractorID);
  ELSE
    -- O contratante é inválido
    RAISE
      'Informe o contratante'
      USING ERRCODE = 'restrict_violation';
  END IF;
  IF (FsearchValue IS NULL) THEN
    -- RAISE NOTICE 'FsearchValue IS NULL';
  ELSE
    IF (FsearchValue = '') THEN
      -- RAISE NOTICE 'FsearchValue IS EMPTY';
    ELSE
      -- Monta o filtro
      filter :=  filter || format(' AND public.unaccented(entities.name) ILIKE public.unaccented(''%%%s%%'')',
                                  FsearchValue);
    END IF;
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s',
                     LimitOf);
  ELSE
    limits := '';
  END IF;

  -- Monta a consulta
  query := format('SELECT entities.entityID AS id,
                          entities.name,
                          count(*) OVER() AS fullcount
                     FROM erp.entities
                    WHERE %s
                      AND entities.blocked = false
                      AND entities.supplier = true
                      AND entities.serviceProvider = true
                      AND entities.deleted = false
                    ORDER BY entities.name %s;',
                  filter, limits);
  FOR row IN EXECUTE query
  LOOP
    serviceprovider.id                   := row.id;
    serviceprovider.name                 := row.name;
    serviceprovider.fullcount            := row.fullcount;

    RETURN NEXT serviceprovider;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- Atualiza a stored procedure que recupera as informações de entidades
-- e de suas unidades/filiais para o gerenciamento de contratantes,
-- clientes e fornecedores
DROP FUNCTION erp.getEntitiesData(FcontractorID integer,
  FentityID integer, Fgroup varchar, FsearchValue varchar(100),
  FsearchField varchar(20), FOrder varchar, Skip integer, LimitOf integer);
DROP TYPE erp.entityData;

CREATE TYPE erp.entityData AS
(
  entityID           integer,
  contractor         boolean,
  contractorID       integer,
  contractorName     varchar(100),
  contractorBlocked  boolean,
  customer           boolean,
  supplier           boolean,
  name               varchar(100),
  tradingName        varchar(100),
  entityTypeID       integer,
  entityTypeName     varchar(30),
  juridicalperson    boolean,
  cooperative        boolean,
  serviceprovider    boolean,
  entityBlocked      boolean,
  subsidiaryID       integer,
  affiliated         boolean,
  subsidiaryName     varchar(50),
  cityID             integer,
  cityName           varchar(50),
  nationalregister   varchar(18),
  subsidiaryBlocked  boolean,
  blockedLevel       smallint,
  createdAt          timestamp,
  updatedAt          timestamp,
  fullcount          integer
);

CREATE OR REPLACE FUNCTION erp.getEntitiesData(FcontractorID integer,
  FentityID integer, Fgroup varchar, FsearchValue varchar(100),
  FsearchField varchar(20), FOrder varchar, Skip integer, LimitOf integer)
RETURNS SETOF erp.entityData AS
$$
DECLARE
  entityData   erp.entityData%rowtype;
  row          record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  blockedLevel integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FentityID IS NULL) THEN
    FentityID = 0;
  END IF;
  IF (Fgroup IS NULL) THEN
    Fgroup = 'contractor';
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'entities.name, subsidiaries.subsidiaryid';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;

  -- Realiza a filtragem por contratante
  IF (FcontractorID > 0) THEN
    filter := format(' AND entities.contractorID = %s',
                     FcontractorID);
  ELSE
    filter := format(' AND entities.contractorID >= %s',
                     FcontractorID);
  END IF;

  -- Realiza a filtragem por grupo
  filter := filter || format(' AND entities.%s = true', Fgroup);

  -- Realiza a filtragem por entidade
  IF (FentityID > 0) THEN
    filter := filter || format(' AND entities.entityID = %s', FentityID);
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
        WHEN 'subsidiaryname' THEN
          field := 'subsidiaries.name';
        WHEN 'nationalregister' THEN
          field := 'subsidiaries.nationalregister';
        ELSE
          field := 'entities.' || FsearchField;
      END CASE;
      -- Monta o filtro
      filter := filter || format(' AND public.unaccented(%s) ILIKE public.unaccented(''%%%s%%'')',
                                  field, FsearchValue);
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  -- Monta a consulta
  query := format('SELECT entities.entityID,
                          entities.contractor,
                          entities.contractorID,
                          contractor.name AS contractorName,
                          contractor.blocked AS contractorBlocked,
                          entities.customer,
                          entities.supplier,
                          entities.name,
                          entities.tradingName,
                          entities.entityTypeID,
                          entitiesTypes.name AS entityTypeName,
                          entitiesTypes.juridicalperson AS juridicalperson,
                          entitiesTypes.cooperative AS cooperative,
                          entities.serviceprovider,
                          entities.blocked AS entityBlocked,
                          subsidiaries.subsidiaryID,
                          subsidiaries.affiliated,
                          subsidiaries.name AS subsidiaryName,
                          subsidiaries.cityID,
                          cities.name AS cityName,
                          subsidiaries.nationalRegister,
                          subsidiaries.blocked AS subsidiaryBlocked,
                          entities.createdAt,
                          entities.updatedAt,
                          count(*) OVER() AS fullcount
                     FROM erp.entities
                    INNER JOIN erp.entities AS contractor ON (entities.contractorID = contractor.entityID)
                    INNER JOIN erp.entitiesTypes ON (entities.entityTypeID = entitiesTypes.entityTypeID)
                    INNER JOIN erp.subsidiaries ON (entities.entityID = subsidiaries.entityID)
                    INNER JOIN erp.cities USING (cityID)
                    WHERE entities.deleted = false
                      AND subsidiaries.deleted = false %s
                    ORDER BY %s %s',
                  filter, FOrder, limits);
  FOR row IN EXECUTE query
  LOOP
    entityData.entityID           := row.entityID;
    entityData.contractor         := row.contractor;
    entityData.contractorID       := row.contractorID;
    entityData.contractorName     := row.contractorName;
    entityData.contractorBlocked  := row.contractorBlocked;
    entityData.customer           := row.customer;
    entityData.supplier           := row.supplier;
    entityData.name               := row.name;
    entityData.tradingName        := row.tradingName;
    entityData.entityTypeID       := row.entityTypeID;
    entityData.entityTypeName     := row.entityTypeName;
    entityData.serviceprovider    := row.serviceprovider;
    entityData.juridicalperson    := row.juridicalperson;
    entityData.cooperative        := row.cooperative;
    entityData.entityBlocked      := row.entityBlocked;
    entityData.subsidiaryID       := row.subsidiaryID;
    entityData.affiliated         := row.affiliated;
    entityData.subsidiaryName     := row.subsidiaryName;
    entityData.cityID             := row.cityID;
    entityData.cityName           := row.cityName;
    entityData.nationalregister   := row.nationalregister;
    entityData.subsidiaryBlocked  := row.subsidiaryBlocked;
    -- RAISE NOTICE 'Contractor %', row.contractorBlocked;
    -- RAISE NOTICE 'Entity %', row.entityBlocked;
    -- RAISE NOTICE 'Subsidiary %', row.subsidiaryBlocked;

    -- Determina o nível de bloqueio. O nível de bloqueio mais baixo é o
    -- da unidade/filial, seguido da empresa e por último o contratante
    blockedLevel := 0;
    -- Se somos um contratante
    IF (row.contractorID = row.entityID) THEN
      IF (row.subsidiaryBlocked) THEN
        blockedLevel := blockedLevel|1;
      END IF;
      IF (row.contractorBlocked) THEN
        blockedLevel := blockedLevel|4;
      END IF;
    ELSE
      IF (row.subsidiaryBlocked) THEN
        blockedLevel := blockedLevel|1;
      END IF;
      IF (row.entityBlocked) THEN
        blockedLevel := blockedLevel|2;
      END IF;
      IF (row.contractorBlocked) THEN
        blockedLevel := blockedLevel|4;
      END IF;
    END IF;
    entityData.blockedLevel       := blockedLevel;
    entityData.createdAt          := row.createdAt;
    entityData.updatedAt          := row.updatedAt;
    entityData.fullcount          := row.fullcount;

    RETURN NEXT entityData;
  END loop;
END
$$
LANGUAGE 'plpgsql';


-- Atualiza a stored procedure que recupera informações de veículos,
-- desconsiderando veículos removidos e veículos de entidades removidas
CREATE OR REPLACE FUNCTION erp.getVehiclesData(FcontractorID integer,
  FcustomerID integer, FsubsidiaryID integer, FvehicleID integer,
  FsearchValue varchar(100), FsearchField varchar(20),
  FOrder varchar, Skip integer, LimitOf integer)
RETURNS SETOF erp.vehicleData AS
$$
DECLARE
  vehicleData  erp.vehicleData%rowtype;
  row          record;
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
  IF (FvehicleID IS NULL) THEN
    FvehicleID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'customer.name ASC, subsidiary.subsidiaryid ASC, vehicles.plate ASC';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  filter := '';
  
  IF (FvehicleID > 0) THEN
    filter := format(' AND vehicles.vehicleID = %s',
                    FvehicleID);
  ELSE
    -- Realiza a filtragem por cliente
    IF (FcustomerID > 0) THEN
      filter := format(' AND customer.entityID = %s',
                      FcustomerID);
      IF (FsubsidiaryID > 0) THEN
        filter := filter || format(' AND subsidiary.subsidiaryID = %s',
                                  FsubsidiaryID);
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
          field := 'vehicleBrands.name';
        WHEN 'vehicleModelName' THEN
          field := 'vehicleModels.name';
        ELSE
          field := 'vehicles.' || FsearchField;
      END CASE;
      -- Monta o filtro
      filter := filter || format(' AND public.unaccented(%s) ILIKE public.unaccented(''%%%s%%'')',
                                  field, FsearchValue);
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  -- Monta a consulta
  query := format('SELECT vehicles.vehicleID,
                          vehicles.contractorID,
                          contractor.name AS contractorName,
                          contractor.blocked AS contractorBlocked,
                          vehicles.customerID,
                          customer.name AS customerName,
                          customer.blocked AS customerBlocked,
                          customer.entityTypeID AS customerTypeID,
                          customerType.name AS customerTypeName,
                          customerType.juridicalperson AS juridicalperson,
                          vehicles.subsidiaryID,
                          subsidiary.name AS subsidiaryName,
                          subsidiary.blocked AS subsidiaryBlocked,
                          vehicles.plate,
                          vehicles.vehicleTypeID,
                          vehicleTypes.name AS vehicleTypeName,
                          vehicles.vehicleBrandID,
                          vehicleBrands.name AS vehicleBrandName,
                          vehicles.vehicleModelID,
                          vehicleModels.name AS vehicleModelName,
                          vehicles.vehicleColorID,
                          vehicleColors.name AS vehicleColorName,
                          vehicleColors.color AS vehicleColor,
                          vehicles.carNumber,
                          vehicles.fuelType,
                          fuelTypes.name AS fuelTypeName,
                          vehicles.blocked AS vehicleBlocked,
                          vehicles.createdAt,
                          vehicles.updatedAt,
                          count(*) OVER() AS fullcount
                     FROM erp.vehicles
                    INNER JOIN erp.entities AS contractor ON (vehicles.contractorID = contractor.entityID)
                    INNER JOIN erp.entities AS customer ON (vehicles.customerID = customer.entityID)
                    INNER JOIN erp.entitiesTypes AS customerType ON (customer.entityTypeID = customerType.entityTypeID)
                    INNER JOIN erp.subsidiaries AS subsidiary ON (vehicles.subsidiaryID = subsidiary.subsidiaryID)
                    INNER JOIN erp.vehicleTypes ON (vehicles.vehicleTypeID = vehicleTypes.vehicleTypeID)
                    INNER JOIN erp.vehicleBrands ON (vehicles.vehicleBrandID = vehicleBrands.vehicleBrandID)
                    INNER JOIN erp.vehicleModels ON (vehicles.vehicleModelID = vehicleModels.vehicleModelID)
                    INNER JOIN erp.vehicleColors ON (vehicles.vehicleColorID = vehicleColors.vehicleColorID)
                    INNER JOIN erp.fuelTypes ON (vehicles.fuelType = fuelTypes.fuelType)
                    WHERE vehicles.contractorID = %s
                      AND vehicles.deleted = false
                      AND customer.deleted = false
                      AND subsidiary.deleted = false %s
                    ORDER BY %s %s',
                  fContractorID, filter, FOrder, limits);
  FOR row IN EXECUTE query
  LOOP
    vehicleData.vehicleID          := row.vehicleID;
    vehicleData.contractorID       := row.contractorID;
    vehicleData.contractorName     := row.contractorName;
    vehicleData.contractorBlocked  := row.contractorBlocked;
    vehicleData.customerID         := row.customerID;
    vehicleData.customerName       := row.customerName;
    vehicleData.customerBlocked    := row.customerBlocked;
    vehicleData.customerTypeID     := row.customerTypeID;
    vehicleData.customerTypeName   := row.customerTypeName;
    vehicleData.juridicalperson    := row.juridicalperson;
    vehicleData.subsidiaryID       := row.subsidiaryID;
    vehicleData.subsidiaryName     := row.subsidiaryName;
    vehicleData.subsidiaryBlocked  := row.subsidiaryBlocked;
    vehicleData.plate              := row.plate;
    vehicleData.vehicleTypeID      := row.vehicleTypeID;
    vehicleData.vehicleTypeName    := row.vehicleTypeName;
    vehicleData.vehicleBrandID     := row.vehicleBrandID;
    vehicleData.vehicleBrandName   := row.vehicleBrandName;
    vehicleData.vehicleModelID     := row.vehicleModelID;
    vehicleData.vehicleModelName   := row.vehicleModelName;
    vehicleData.vehicleColorID     := row.vehicleColorID;
    vehicleData.vehicleColorName   := row.vehicleColorName;
    vehicleData.vehicleColor       := row.vehicleColor;
    vehicleData.carNumber          := row.carNumber;
    vehicleData.fuelType           := row.fuelType;
    vehicleData.fuelTypeName       := row.fuelTypeName;
    vehicleData.vehicleBlocked     := row.vehicleBlocked;
    -- RAISE NOTICE 'Contractor %', row.contractorBlocked;
    -- RAISE NOTICE 'Vehicle %', row.vehicleBlocked;
    -- RAISE NOTICE 'Subsidiary %', row.subsidiaryBlocked;

    -- Determina o nível de bloqueio. O nível de bloqueio mais baixo é o
    -- do veículo, seguido da unidade/filial do cliente, da empresa e
    -- por último o contratante
    blockedLevel := 0;
    IF (row.vehicleBlocked) THEN
      blockedLevel := blockedLevel|1;
    END IF;
    IF (row.subsidiaryBlocked) THEN
      blockedLevel := blockedLevel|2;
    END IF;
    IF (row.customerBlocked) THEN
      blockedLevel := blockedLevel|4;
    END IF;
    IF (row.contractorBlocked) THEN
      blockedLevel := blockedLevel|8;
    END IF;
    vehicleData.blockedLevel       := blockedLevel;
    vehicleData.createdAt          := row.createdAt;
    vehicleData.updatedAt          := row.updatedAt;
    vehicleData.fullcount          := row.fullcount;

    RETURN NEXT vehicleData;
  END loop;
END
$$
LANGUAGE 'plpgsql';

-- A tabela que recupera informações de Sim Cards, eliminando àqueles de
-- fornecedores apagados
CREATE OR REPLACE FUNCTION erp.getSimCardsData(FcontractorID integer,
  FsupplierID integer, FsubsidiaryID integer, FsimcardID integer,
  FsearchValue varchar(100), FsearchField varchar(20), FtypeID integer,
  FmobileOperatorID integer, FstorageLocation varchar(30),
  FstorageID integer, FOrder varchar, Skip integer, LimitOf integer)
RETURNS SETOF erp.simCardData AS
$$
DECLARE
  simCardData  erp.simCardData%rowtype;
  row          record;
  query        varchar;
  field        varchar;
  filter       varchar;
  limits       varchar;
  blockedLevel integer;
BEGIN
  IF (FcontractorID IS NULL) THEN
    FcontractorID = 0;
  END IF;
  IF (FsupplierID IS NULL) THEN
    FsupplierID = 0;
  END IF;
  IF (FsubsidiaryID IS NULL) THEN
    FsubsidiaryID = 0;
  END IF;
  IF (FsimcardID IS NULL) THEN
    FsimcardID = 0;
  END IF;
  IF (FtypeID IS NULL) THEN
    FtypeID = 0;
  END IF;
  IF (FmobileOperatorID IS NULL) THEN
    FmobileOperatorID = 0;
  END IF;
  IF (FstorageLocation IS NULL) THEN
    FstorageLocation = 'Any';
  END IF;
  IF (FstorageID IS NULL) THEN
    FstorageID = 0;
  END IF;
  IF (FOrder IS NULL) THEN
    FOrder = 'supplier.name ASC, subsidiary.subsidiaryid ASC, simcards.iccID ASC';
  END IF;
  IF (LimitOf > 0) THEN
    limits := format('LIMIT %s OFFSET %s',
                     LimitOf, Skip);
  ELSE
    limits := '';
  END IF;
  
  IF (FsimcardID > 0) THEN
    filter := format(' AND simcards.simcardID = %s',
                    FsimcardID);
  ELSE
    -- Realiza a filtragem por cliente
    IF (FsupplierID > 0) THEN
      filter := format(' AND supplier.entityID = %s',
                      FsupplierID);
      IF (FsubsidiaryID > 0) THEN
        filter := filter || format(' AND subsidiary.subsidiaryID = %s',
                                  FsubsidiaryID);
      END IF;
    ELSE
      filter := '';
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
        WHEN 'simCardBrandName' THEN
          field := 'simCardBrands.name';
        WHEN 'simCardModelName' THEN
          field := 'simCardModels.name';
        ELSE
          field := 'simCards.' || FsearchField;
      END CASE;
      -- Monta o filtro
      filter := filter || format(' AND public.unaccented(%s) ILIKE public.unaccented(''%%%s%%'')',
                                  field, FsearchValue);
    END IF;
  END IF;
  -- RAISE NOTICE 'filter IS %', filter;

  IF (FtypeID > 0) THEN
    -- Monta o filtro
    filter := filter || format(' AND simcards.simcardTypeID = %s', FtypeID);
  END IF;

  IF (FmobileOperatorID > 0) THEN
    -- Monta o filtro
    filter := filter || format(' AND simcards.mobileOperatorID = %s', FmobileOperatorID);
  END IF;

  CASE (FstorageLocation)
    WHEN 'Installed' THEN
      -- Monta o filtro
      filter := filter || format(' AND simcards.storageLocation = ''%s''', FstorageLocation);
    WHEN 'StoredOnDeposit' THEN
      -- Monta o filtro
      IF FstorageID > 0 THEN
        filter := filter || format(' AND simcards.storageLocation = ''%s'' AND simcards.depositID = %s', FstorageLocation, FstorageID);
      ELSE
        filter := filter || format(' AND simcards.storageLocation = ''%s''', FstorageLocation);
      END IF;
    WHEN 'StoredWithTechnician' THEN
      -- Monta o filtro
      IF FstorageID > 0 THEN
        filter := filter || format(' AND simcards.storageLocation = ''%s'' AND simcards.technicianID = %s', FstorageLocation, FstorageID);
      ELSE
        filter := filter || format(' AND simcards.storageLocation = ''%s''', FstorageLocation);
      END IF;
    WHEN 'StoredWithServiceProvider' THEN
      -- Monta o filtro
      IF FstorageID > 0 THEN
        filter := filter || format(' AND simcards.storageLocation = ''%s'' AND simcards.serviceProviderID = %s', FstorageLocation, FstorageID);
      ELSE
        filter := filter || format(' AND simcards.storageLocation = ''%s''', FstorageLocation);
      END IF;
    ELSE
      -- Não faz nada
  END CASE;

  -- Monta a consulta
  query := format('SELECT simcards.simcardID,
                          simcards.contractorID,
                          contractor.name AS contractorName,
                          contractor.blocked AS contractorBlocked,
                          simcards.supplierID,
                          supplier.name AS supplierName,
                          supplier.blocked AS supplierBlocked,
                          supplierType.juridicalperson AS juridicalperson,
                          simcards.subsidiaryID,
                          subsidiary.name AS subsidiaryName,
                          subsidiary.blocked AS subsidiaryBlocked,
                          simcards.iccID,
                          simcards.imsi,
                          simcards.phoneNumber,
                          simcards.mobileOperatorID,
                          mobileOperators.name AS mobileOperatorName,
                          mobileOperators.logo AS mobileOperatorLogo,
                          simcards.simcardTypeID,
                          simcardTypes.name AS simcardTypeName,
                          simcards.assetNumber,
                          simcards.blocked AS simCardBlocked,
                          simcards.createdAt,
                          simcards.updatedAt,
                          simcards.storageLocation = ''Installed'' AS attached,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN simcards.equipmentID
                            ELSE 0
                          END AS equipmentID,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN equipments.serialnumber
                            ELSE ''''
                          END AS serialnumber,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN equipments.imei
                            ELSE ''''
                          END AS imei,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN equipments.equipmentModelID
                            ELSE 0
                          END AS equipmentModelID,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN equipmentmodels.name
                            ELSE ''''
                          END AS equipmentModelName,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN simcards.slotNumber
                            ELSE 0
                          END AS slotNumber,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN equipmentmodels.simcardTypeID
                            ELSE 0
                          END AS slotTypeID,
                          CASE
                            WHEN simcards.storageLocation = ''Installed'' THEN slotType.name
                            ELSE ''''
                          END AS slotTypeName,
                          count(*) OVER() AS fullcount
                     FROM erp.simcards
                    INNER JOIN erp.entities AS contractor ON (simcards.contractorID = contractor.entityID)
                    INNER JOIN erp.entities AS supplier ON (simcards.supplierID = supplier.entityID)
                    INNER JOIN erp.entitiesTypes AS supplierType ON (supplier.entityTypeID = supplierType.entityTypeID)
                    INNER JOIN erp.subsidiaries AS subsidiary ON (simcards.subsidiaryID = subsidiary.subsidiaryID)
                    INNER JOIN erp.mobileOperators USING (mobileOperatorID)
                    INNER JOIN erp.simcardTypes ON (simcards.simcardTypeID = simcardTypes.simcardTypeID)
                     LEFT JOIN erp.equipments USING (equipmentID)
                     LEFT JOIN erp.equipmentmodels USING (equipmentModelID)
                     LEFT JOIN erp.simcardTypes AS slotType ON (equipmentmodels.simcardTypeID = slotType.simcardTypeID)
                    WHERE simcards.contractorID = %s
                      AND supplier,deleted = false %s
                    ORDER BY %s %s',
                  fContractorID, filter, FOrder, limits);
  -- RAISE NOTICE 'SQL: %', query;
  FOR row IN EXECUTE query
  LOOP
    simCardData.simcardID              := row.simcardID;
    simCardData.contractorID           := row.contractorID;
    simCardData.contractorName         := row.contractorName;
    simCardData.contractorBlocked      := row.contractorBlocked;
    simCardData.supplierID             := row.supplierID;
    simCardData.supplierName           := row.supplierName;
    simCardData.supplierBlocked        := row.supplierBlocked;
    simCardData.juridicalperson        := row.juridicalperson;
    simCardData.subsidiaryID           := row.subsidiaryID;
    simCardData.subsidiaryName         := row.subsidiaryName;
    simCardData.subsidiaryBlocked      := row.subsidiaryBlocked;
    simCardData.iccID                  := row.iccID;
    simCardData.imsi                   := row.imsi;
    simCardData.phoneNumber            := row.phoneNumber;
    simCardData.mobileOperatorID       := row.mobileOperatorID;
    simCardData.mobileOperatorName     := row.mobileOperatorName;
    simCardData.mobileOperatorLogo     := row.mobileOperatorLogo;
    simCardData.simcardTypeID          := row.simcardTypeID;
    simCardData.simcardTypeName        := row.simcardTypeName;
    simCardData.attached               := row.attached;
    simCardData.equipmentID            := row.equipmentID;
    simCardData.serialnumber           := row.serialnumber;
    simCardData.imei                   := row.imei;
    simCardData.equipmentModelID       := row.equipmentModelID;
    simCardData.equipmentModelName     := row.equipmentModelName;
    simCardData.slotNumber             := row.slotNumber;
    simCardData.slotTypeID             := row.slotTypeID;
    simCardData.slotTypeName           := row.slotTypeName;
    simCardData.assetNumber            := row.assetNumber;
    simCardData.simCardBlocked         := row.simCardBlocked;
    -- RAISE NOTICE 'Contractor %', row.contractorBlocked;
    -- RAISE NOTICE 'Sim Card %', row.simCardBlocked;
    -- RAISE NOTICE 'Subsidiary %', row.subsidiaryBlocked;

    -- Determina o nível de bloqueio. O nível de bloqueio mais baixo é o
    -- do Sim Card, depois a unidade/filial do fornecedor, o próprio
    -- fornecedor e por último o contratante
    blockedLevel := 0;
    IF (row.simCardBlocked) THEN
      blockedLevel := blockedLevel|1;
    END IF;
    IF (row.subsidiaryBlocked) THEN
      blockedLevel := blockedLevel|2;
    END IF;
    IF (row.supplierBlocked) THEN
      blockedLevel := blockedLevel|4;
    END IF;
    IF (row.contractorBlocked) THEN
      blockedLevel := blockedLevel|8;
    END IF;
    simCardData.blockedLevel       := blockedLevel;
    simCardData.createdAt          := row.createdAt;
    simCardData.updatedAt          := row.updatedAt;
    simCardData.fullcount          := row.fullcount;

    RETURN NEXT simCardData;
  END loop;
END
$$
LANGUAGE 'plpgsql';


-- ---------------------------------------------------------------------
-- ######  ####  #####  ##    ##  #### 
-- ##     ##  ## ##  ## ###  ### ##   #
-- ####   ##  ## #####  ########   ##  
-- ##     ##  ## ##  ## ## ## ## #   ##
-- ##      ####  ##  ## ##    ##  #### 
-- ---------------------------------------------------------------------
-- Formulários
-- ---------------------------------------------------------------------
-- Armazena as informações de formulários dinâmicos. Formulários são
-- utilizados para, por exemplo, conferência do técnico na conclusão de
-- um atendimento no cliente.
-- ---------------------------------------------------------------------

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
