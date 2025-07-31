-- =====================================================================
-- INFORMAÇÕES DE ENTIDADES DO SISTEMA
-- =====================================================================
-- Tabelas utilizada no controle de entidades. Uma entidade pode ser um
-- contratante, cliente (pessoa física ou jurídica), fornecedor, dentre
-- outros.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Entidades
-- ---------------------------------------------------------------------
-- Armazena as informações de entidades. Uma entidade pode ser um
-- contratante do sistema, um cliente do contratante e/ou um fornecedor.
-- As entidades podem ser pessoas físicas e/ou jurídicas.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.entities (
  entityID              serial,         -- O ID da entidade
  contractor            boolean         -- A flag indicativa de
                        NOT NULL        -- contratante do sistema
                        DEFAULT FALSE,
  contractorID          integer         -- ID da entidade contratante
                        NOT NULL
                        DEFAULT 0,
  customer              boolean         -- A flag indicativa de cliente
                        NOT NULL        -- de um contratante
                        DEFAULT FALSE,
  supplier              boolean         -- A flag indicativa de fornecedor
                        NOT NULL        -- de um contratante
                        DEFAULT FALSE,
  serviceProvider       boolean         -- A flag indicativa de prestador
                        NOT NULL        -- de serviços de um contratante
                        DEFAULT FALSE,
  seller                boolean         -- A flag indicativa de vendedor
                        NOT NULL        -- de um contratante
                        DEFAULT FALSE,
  monitor               boolean         -- A flag indicativa de empresa
                        NOT NULL        -- de monitoramento
                        DEFAULT FALSE,
  rapidresponse         boolean         -- A flag indicativa de empresa
                        NOT NULL        -- pronta resposta
                        DEFAULT FALSE,
  name                  varchar(100)    -- O nome ou a razão social
                        NOT NULL,       -- (para empresas)
  tradingName           varchar(100)    -- O nome/marca fantasia
                        DEFAULT NULL,
  entityUUID            char(36),       -- O id único para esta entidade
  entityAlias           varchar(5)      -- Um apelido numérico que nos permite
                        DEFAULT NULL,   -- localizar o UUID do contratante
  tenantName            varchar(20)     -- Um apelido que nos permite
                        DEFAULT NULL,   -- localizar o UUID do contratante
  entityTypeID          integer         -- O ID do tipo de entidade legal
                        NOT NULL,
  defaultCoordinateID   integer,        -- O ID da coordenada geográfica padrão
  blocked               boolean         -- O indicativo de entidade
                        NOT NULL        -- bloqueada
                        DEFAULT FALSE,
  stcKey                varchar(32)     -- A chave de acesso que habilita
                        DEFAULT NULL,   -- os serviços de integração com
                                        -- o sistema STC
  note                  text,           -- Um campo de observação
  contactData           jsonb,          -- O conteúdo dos dados de contato
  themingData           jsonb,          -- O conteúdo dos dados de tema
  issueInvoice          boolean         -- O indicativo de que deve emitir
                        NOT NULL        -- notas fiscais
                        DEFAULT false,
  enableAtMonitoring    boolean         -- O indicativo de habilitar na
                        DEFAULT false,  -- central de monitoramento
  monitoringID          integer         -- O ID da central de monitoramento
                        DEFAULT NULL,
  useMainPhonesForCall  boolean         -- O indicativo de usar os telefones
                        DEFAULT false,  -- principais em caso de emergência
  dispatchRapidResponse boolean         -- A flag para indicar envio de
                        DEFAULT false,  -- pronta resposta
  rapidResponseID       integer         -- O ID da central de pronta
                        DEFAULT NULL,   -- resposta
  noteForMonitoring     text,           -- Um campo de observação para emergência
  emergencyInstructions text,           -- Um campo de instruções de emergência
  securityPassword      varchar(100)    -- Senha de segurança
                        DEFAULT NULL,
  verificationPassword  varchar(100)    -- Resposta a sua senha de segurança
                        DEFAULT NULL,   -- (senha e contra-senha)
  createdAt             timestamp       -- A data de criação da entidade
                        NOT NULL
                        DEFAULT CURRENT_TIMESTAMP,
  createdByUserID       integer         -- O ID do usuário responsável
                        NOT NULL,       -- pelo cadastro desta entidade
  updatedAt             timestamp       -- A data da última modificação
                        NOT NULL        -- desta entidade
                        DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID       integer         -- O ID do usuário responsável pela
                        NOT NULL,       -- última modificação desta entidade
  deleted               boolean         -- O indicativo de entidade removida
                        NOT NULL
                        DEFAULT false,
  deletedAt             timestamp       -- A data de remoção da entidade
                        DEFAULT NULL,
  deletedByUserID       integer         -- O ID do usuário responsável
                        DEFAULT NULL,   -- pela remoção da entidade
  PRIMARY KEY (entityID),             -- O indice primário
  FOREIGN KEY (entityTypeID)
    REFERENCES erp.entitiesTypes(entityTypeID)
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

-- Adiciona a chave extrangeira para unir a tabela de usuários com a
-- tabela de entidades
ALTER TABLE erp.users
  ADD CONSTRAINT users_entityid_fkey
    FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE;

CREATE INDEX idx_entities_entityid_contractor_monitor ON erp.entities(entityid, contractor, monitor);
CREATE INDEX idx_entities_contractorid_entityid ON erp.entities(contractorid, entityid);

-- ---------------------------------------------------------------------
-- Gatilho de ID do contratante
-- ---------------------------------------------------------------------
-- Gatilho para lidar com o ID único da entidade e a informação de
-- contratante
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.entityTransaction()
RETURNS trigger AS $BODY$
DECLARE
  entityUUID  char(36);
BEGIN
  -- Faz a atualização dos valores nos processos em que se cria ou
  -- altera uma entidade. Faz uso da variável especial TG_OP para
  -- para verificar a operação executada e de TG_WHEN para determinar o
  -- instante em que isto ocorre.
  IF (TG_OP = 'INSERT') THEN
    IF (TG_WHEN = 'BEFORE') THEN
      -- Verifica o próximo ID da transação e associa ao novo registro
      EXECUTE 'SELECT md5(random()::text || clock_timestamp()::text)::uuid;'
         INTO entityUUID;
      NEW.entityUUID := entityUUID;

      IF (NEW.contractor OR NEW.monitor) THEN
        -- Acrescentamos um alias para identificar o contratante e/ou a
        -- empresa de monitoramento. Este alias é o CRC16 do UUID do
        -- contratante/empresa de monitoramento
        NEW.entityAlias = public.crc16(CAST(entityUUID AS varchar));
      END IF;

      -- Verifica se é um contratante
      IF (NEW.contractor = true) THEN
        -- Copiamos a ID da entidade para o contratante pois são a mesma
        -- informação
        NEW.contractorID := NEW.entityID;
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Sempre mantemos a UUID sem modificação
    NEW.entityUUID := OLD.entityUUID;

    -- Retornamos a nova entidade
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$BODY$ LANGUAGE plpgsql;

CREATE TRIGGER entityTransactionTriggerBefore
  BEFORE INSERT OR UPDATE ON erp.entities
  FOR EACH ROW EXECUTE PROCEDURE erp.entityTransaction();

-- Insere a entidade contratante padrão
INSERT INTO erp.entities (entityID, contractor, customer, supplier,
  seller, name, tradingName, tenantName, contactData, entityTypeID,
  createdByUserID, updatedByUserID) VALUES
  (1, true, false, false, false, 'Yemanja Artigos Religiosos Ltda - ME',
   'Yemanja Artigos Religiosos', 'yemanja',
   '["Central de Atendimento", "(11) 2658-9104", "(11) 93239-1515", "contato@grupomm.srv.br"]',
   1, 1, 1);

ALTER SEQUENCE erp.entities_entityid_seq RESTART WITH 2;

-- ---------------------------------------------------------------------
-- Unidades/filiais
-- ---------------------------------------------------------------------
-- Armazena as informações de unidades/filiais das entidades.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.subsidiaries (
  subsidiaryID            serial,        -- O ID da unidade/filial
  entityID                integer        -- O ID da entidade à qual
                          NOT NULL,      -- pertence esta entidade
  name                    varchar(100)   -- Identificação da filial
                          NOT NULL,
  headOffice              boolean        -- A indicação de que este
                          DEFAULT false, -- registro é do titular/matriz
  address                 varchar(100)   -- O endereço
                          NOT NULL,
  streetNumber            varchar(10),   -- O número da casa
  complement              varchar(30),   -- O complemento do endereço
  district                varchar(50),   -- O bairro
  cityID                  integer        -- O ID da cidade
                          NOT NULL,
  postalCode              char(9)        -- O CEP
                          NOT NULL,
  regionalDocumentType    integer        -- ID do tipo do documento
                          NOT NULL       -- (Padrão: Inscrição Estadual)
                          DEFAULT 4,
  regionalDocumentNumber  varchar(20)    -- Número do documento
                          DEFAULT 'Isento',
  regionalDocumentState   char(2)        -- O estado (UF) onde foi
                          NOT NULL,      -- emitido o documento
  nationalRegister        varchar(18)    -- CPF ou CNPJ
                          NOT NULL
                          DEFAULT '00.000.000/0000-00',
  municipalInscription    varchar(15),   -- A inscrição municipal
  birthday                date,          -- A data de nascimento
  genderID                integer,       -- O ID do gênero
  maritalStatusID         integer,       -- O ID do estado civil
  personName              varchar(50),   -- O nome da pessoa para PJ
  department              varchar(50),   -- O departamento da pessoa para PJ
  blocked                 boolean        -- O indicativo de unidade/filial
                          NOT NULL       -- bloqueada
                          DEFAULT false,
  createdAt               timestamp      -- A data de criação da
                          NOT NULL       --  unidade/filial
                          DEFAULT CURRENT_TIMESTAMP,
  createdByUserID         integer        -- O ID do usuário responsável
                          NOT NULL,      -- pelo cadastro desta filial
  updatedAt               timestamp      -- A data de modificação da
                          NOT NULL       -- filial
                          DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID         integer        -- O ID do usuário responsável
                          NOT NULL,      -- pela última modificação
  deleted                 boolean        -- O indicativo de unidade/filial
                          NOT NULL       -- removida
                          DEFAULT false,
  deletedAt               timestamp      -- A data de remoção da
                          DEFAULT NULL,  -- unidade/filial
  deletedByUserID         integer        -- O ID do usuário responsável
                          DEFAULT NULL,  -- pela remoção da unidade/filial
  PRIMARY KEY (subsidiaryID),            -- O indice primário
  UNIQUE (entityID, nationalRegister),
  FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (cityID)
    REFERENCES erp.cities(cityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (regionalDocumentType)
    REFERENCES erp.documentTypes(documentTypeID)
    ON DELETE CASCADE,
  FOREIGN KEY (genderID)
    REFERENCES erp.genders(genderID)
    ON DELETE RESTRICT,
  FOREIGN KEY (maritalStatusID)
    REFERENCES erp.maritalStatuss(maritalStatusID)
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

-- Insere a unidade/filial padrão
INSERT INTO erp.subsidiaries (subsidiaryID, entityID, name, headOffice,
  address, streetNumber, complement, district, cityID, postalCode,
  regionalDocumentType, regionalDocumentNumber, regionalDocumentState,
  nationalRegister, personName, department, createdByUserID,
  updatedByUserID) VALUES
  (1, 1, 'Matriz', true, 'Av. do Rio Pequeno', '1084', '',
    'Rio Pequeno', 5346, '05379-000', 4, 'Isento', 'SP',
    '00.299.716/0001-31', 'Emerson', 'Comercial', 1, 1);

ALTER SEQUENCE erp.subsidiaries_subsidiaryid_seq RESTART WITH 2;

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

-- ---------------------------------------------------------------------
-- E-mails principais por unidade/filial
-- ---------------------------------------------------------------------
-- Contém as informações dos e-mails por unidade/filial.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.mailings (
  mailingID     serial,        -- O ID do e-mail
  entityID      integer        -- O ID da entidade à qual pertence este
                NOT NULL,      -- e-mail
  subsidiaryID  integer        -- O ID da unidade/filial  à qual pertence
                NOT NULL,      -- este e-mail
  email         varchar(100)   -- O endereço de e-mail
                NOT NULL,
  CHECK (POSITION(' ' IN email) = 0),
  PRIMARY KEY (mailingID),
  FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE CASCADE
);


INSERT INTO erp.phones (entityID, subsidiaryID, email) VALUES
  (1, 1, 'contato@yemanja.com.br');

-- ---------------------------------------------------------------------
-- Perfis de recebimento de mensagens
-- ---------------------------------------------------------------------
-- Armazena as informações de perfis que descrevem os tipos de mensagens
-- a serem recebidas pelos clientes para eventos do sistema.
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
  (5, 1, 'Técnico', 'Recebe apenas notificações de cunho técnico.'),
  (6, 1, 'Emergência', 'Recebe apenas ligações da central de monitoramento.');

ALTER SEQUENCE erp.mailingprofiles_mailingprofileid_seq RESTART WITH 7;

-- ---------------------------------------------------------------------
-- Eventos do sistema por perfil
-- ---------------------------------------------------------------------
-- Contém as informações dos eventos do sistema que disparam o envio de
-- mensagens para cada perfil.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.actionsPerProfiles (
  actionPerProfileID  serial,    -- O ID da ação por perfil
  contractorID        integer    -- ID do contratante
                      NOT NULL,
  mailingProfileID    integer    -- O ID do perfil
                      NOT NULL,
  systemActionID      integer    -- O ID da ação do sistema
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
  (1, 5, 2),
  (1, 6, 5);

-- ---------------------------------------------------------------------
-- Informações de contatos adicionais por unidade/filial
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
  email             varchar(100),  -- O endereço de e-mail
  phoneTypeID       integer        -- O ID do tipo de telefone
                    NOT NULL,
  phoneNumber       varchar(20),   -- O número de telefone deste contato
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
    ON DELETE CASCADE,
  FOREIGN KEY (subsidiaryID)
    REFERENCES erp.subsidiaries(subsidiaryID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

INSERT INTO erp.phones (phoneID, entityID, subsidiaryID, phoneTypeID, phoneNumber) VALUES
  (1, 1, 1, 1, '(11) 2925-9187');

ALTER SEQUENCE erp.phones_phoneid_seq RESTART WITH 2;

-- ---------------------------------------------------------------------
-- Telefones de emergência
-- ---------------------------------------------------------------------
-- Contém as informações dos telefones de contato em casos de emergência
-- para uso na central de monitoramento.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.emergencyphones (
  emergencyPhoneID  serial,        -- O ID do telefone
  entityID          integer        -- O ID da entidade à qual pertence
                    NOT NULL,      -- este telefone
  phoneTypeID       integer        -- O ID do tipo de telefone
                    NOT NULL,
  phoneNumber       varchar(20)    -- O número do telefone
                    NOT NULL,
  PRIMARY KEY (emergencyPhoneID),
  FOREIGN KEY (entityID)
    REFERENCES erp.entities(entityID)
    ON DELETE CASCADE,
  FOREIGN KEY (phoneTypeID)
    REFERENCES erp.phoneTypes(phoneTypeID)
    ON DELETE RESTRICT
);

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
  ELSE
    limits := '';
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
        customerFilter := format(' AND entity.contractorID = %s',
                                 FcontractorID);
        affiliationFilter := format(' AND entity.contractorID = %s',
                                    FcontractorID);
        noRelationshipFilter := format(' AND entity.contractorID = %s',
                                       FcontractorID);
      ELSE
        singleFilter := format(' AND entity.contractorID = %s',
                               FcontractorID);
    END CASE;
  END IF;

  -- 2. Filtragem por ID da entidade
  IF (FentityID > 0) THEN
    -- RAISE NOTICE 'Enabling filter by entity [%]', FcontractorID;
    CASE Fgroup
      WHEN 'customer' THEN
        customerFilter := customerFilter
          || format(' AND entity.entityID = %s', FentityID)
        ;
        affiliationFilter := affiliationFilter
          || format(' AND (entity.entityID = %s OR affiliated.entityID = %s)',
                    FentityID, FentityID)
        ;
        noRelationshipFilter := noRelationshipFilter
          || format(' AND entity.entityID = %s', FentityID)
        ;
      ELSE
        singleFilter := singleFilter
          || format(' AND entity.entityID = %s', FentityID)
        ;
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
              customerFilter := customerFilter
                || format(' AND ((public.unaccented(entity.name) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(entity.tradingName) ILIKE public.unaccented(''%%%s%%'')))',
                          FsearchValue, FsearchValue)
              ;
              affiliationFilter := affiliationFilter
                || format(' AND ((public.unaccented(entity.name) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(entity.tradingName) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(affiliated.name) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(affiliated.tradingName) ILIKE public.unaccented(''%%%s%%'')))',
                          FsearchValue, FsearchValue, FsearchValue, FsearchValue)
              ;
              noRelationshipFilter := noRelationshipFilter
                || format(' AND ((public.unaccented(entity.name) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(entity.tradingName) ILIKE public.unaccented(''%%%s%%'')))',
                          FsearchValue, FsearchValue)
              ;
            WHEN 'nationalregister' THEN
              customerFilter := customerFilter
                || format(' AND regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%''',
                          regexp_replace(FsearchValue, '\D*', '', 'g'))
              ;
              affiliationFilter := affiliationFilter
                || format(' AND ((regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%'') OR (regexp_replace(affiliatedUnity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%''))',
                          regexp_replace(FsearchValue, '\D*', '', 'g'),
                          regexp_replace(FsearchValue, '\D*', '', 'g'))
              ;
              noRelationshipFilter := noRelationshipFilter
                || format(' AND regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%''',
                          regexp_replace(FsearchValue, '\D*', '', 'g'))
              ;
          END CASE;
        ELSE
          -- Determina o campo onde será realizada a pesquisa e monta o
          -- filtro
          CASE (FsearchField)
            WHEN 'name' THEN
              singleFilter := singleFilter
                || format(' AND ((public.unaccented(entity.name) ILIKE public.unaccented(''%%%s%%'')) OR (public.unaccented(entity.tradingName) ILIKE public.unaccented(''%%%s%%'')))',
                          FsearchValue, FsearchValue)
              ;
            WHEN 'nationalregister' THEN
              singleFilter := singleFilter
                || format(' AND regexp_replace(unity.nationalregister, ''\D*'', '''', ''g'') ILIKE ''%%%s%%''',
                          regexp_replace(FsearchValue, '\D*', '', 'g'))
                ;
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

-- ---------------------------------------------------------------------
-- Carteiras de usuário
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de usuários
-- para o gerenciamento de usuários
-- ---------------------------------------------------------------------
-- DROP FUNCTION IF EXISTS erp.UsersWallets(FcontractorID integer,
--   FentityID integer, FminimumLevel integer, FgroupID integer,
--   FsearchValue varchar(50), FsearchField varchar(20), FOrder varchar,
--   Skip integer, LimitOf integer);
-- DROP TYPE IF EXISTS erp.userWallet;
CREATE TYPE erp.userWallet AS
(
  level               smallint,
  contractorID        integer,
  contractorName      varchar(100),
  contractorBlocked   boolean,
  entityID            integer,
  entityName          varchar(100),
  entityBlocked       boolean,
  entityType          varchar(15),
  userID              integer,
  name                varchar(100),
  role                varchar(50),
  username            varchar(25),
  groupID             integer,
  groupName           varchar(50),
  phoneNumber         varchar(16),
  email               varchar(50),
  subaccount          boolean,
  seeAllVehicles      boolean,
  modules             text[],
  userblocked         boolean,
  affiliationBlocking jsonb,
  blockedAt           timestamp,
  blockedLevel        smallint,
  expires             boolean,
  expiresAt           date,
  timeRestriction     boolean,
  suspended           boolean,
  createdAt           timestamp,
  updatedAt           timestamp,
  lastLogin           timestamp,
  forceNewPassword    boolean,
  fullcount           integer
);

CREATE OR REPLACE FUNCTION erp.UsersWallets(FcontractorID integer,
  FentityID integer, FminimumLevel integer, FgroupID integer,
  FsearchValue varchar(50), FsearchField varchar(20), FOrder varchar,
  Skip integer, LimitOf integer)
RETURNS SETOF erp.userWallet AS
$$
DECLARE
  wallet erp.userWallet%rowtype;
  row record;
  query varchar;
  field varchar;

  filter varchar;
  limits varchar;
  blockedLevel integer;
  isAssociation boolean;
  associationFilter varchar;
BEGIN
  IF (FcontractorID IS NULL) THEN
    -- Não filtramos por contratante
    FcontractorID = 0;
  END IF;
  IF (FentityID IS NULL) THEN
    -- Não filtramos por entidade
    FentityID = 0;
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
    FOrder := 'contractorname, level, entityname, name';
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
  associationFilter := '';
  IF (FentityID > 0) THEN
    -- Determina se a entidade é de uma associação
    SELECT entityTypeID = 3 AS association
      INTO isAssociation
      FROM erp.entities
      WHERE entityID = FentityID;

    IF isAssociation THEN
      filter := filter || format(' AND (entityID = %s OR EXISTS (SELECT 1 FROM erp.affiliations WHERE customerid = entityID AND associationid = %s AND unjoinedat IS NULL))',
                   FentityID, FentityID);
      associationFilter := format(' AND B.associationID = %s', FentityID);
    ELSE
      filter := filter || format(' AND entityID = %s',
                  FentityID);
    END IF;
  END IF;

  IF (FminimumLevel > 0) THEN
    filter := filter || format(' AND groupID >= %s',
                 FminimumLevel);
  END IF;
  IF (FgroupID > 0) THEN
    filter := filter || format(' AND groupID = %s',
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
  -- RAISE NOTICE 'Filter contém %s', filter;

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
                                  users.subaccount,
                                  users.seeallvehicles,
                                  users.modules,
                                  users.blocked AS userBlocked,
                                  ''[]''::jsonb AS affiliationBlocking,
                                  users.expires,
                                  users.expiresat,
                                  users.timeRestriction,
                                  users.suspended,
                                  users.createdat,
                                  users.updatedat,
                                  users.lastlogin,
                                  users.forcenewpassword
                             FROM erp.users
                            INNER JOIN erp.entities AS contractor USING (entityid)
                            INNER JOIN erp.groups USING (groupid)
                            WHERE users.contractorid = 0
                              AND contractor.contractor = true
                            ORDER BY users.name)
                            UNION
                          (SELECT 1 AS userlevel,
                                  users.entityid AS contractorid,
                                  monitoring.name AS contractorname,
                                  monitoring.blocked AS contractorblocked,
                                  monitoring.entityid AS entityid,
                                  monitoring.name AS entityname,
                                  monitoring.blocked AS entityblocked,
                                  ''Contractor'' AS entityType,
                                  users.userid AS id,
                                  users.name,
                                  users.role,
                                  users.username,
                                  users.groupid,
                                  groups.name AS groupname,
                                  users.phonenumber,
                                  users.email,
                                  users.subaccount,
                                  users.seeallvehicles,
                                  users.modules,
                                  users.blocked AS userBlocked,
                                  ''[]''::jsonb AS affiliationBlocking,
                                  users.expires,
                                  users.expiresat,
                                  users.timeRestriction,
                                  users.suspended,
                                  users.createdat,
                                  users.updatedat,
                                  users.lastlogin,
                                  users.forcenewpassword
                             FROM erp.users
                            INNER JOIN erp.entities AS monitoring USING (entityid)
                            INNER JOIN erp.groups USING (groupid)
                            WHERE users.contractorid = 0
                              AND monitoring.monitor = true
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
                                  users.subaccount,
                                  users.seeallvehicles,
                                  users.modules,
                                  users.blocked AS userBlocked,
                                  ''[]''::jsonb AS affiliationBlocking,
                                  users.expires,
                                  users.expiresat,
                                  users.timeRestriction,
                                  users.suspended,
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
                                  contractor.entityid AS entityid,
                                  contractor.name AS entityname,
                                  contractor.blocked AS entityblocked,
                                  ''Multientity'' AS entityType,
                                  users.userid AS id,
                                  users.name,
                                  users.role,
                                  users.username,
                                  users.groupid,
                                  groups.name AS groupname,
                                  users.phonenumber,
                                  users.email,
                                  users.subaccount,
                                  users.seeallvehicles,
                                  users.modules,
                                  users.blocked AS userBlocked,
                                  ''[]''::jsonb AS affiliationBlocking,
                                  users.expires,
                                  users.expiresat,
                                  users.timeRestriction,
                                  users.suspended,
                                  users.createdat,
                                  users.updatedat,
                                  users.lastlogin,
                                  users.forcenewpassword
                             FROM erp.users
                            INNER JOIN erp.entities AS contractor ON (erp.users.contractorid = contractor.entityid)
                            INNER JOIN erp.groups USING (groupid)
                            WHERE users.contractorid > 0
                              AND users.entityid IS NULL
                            ORDER BY users.name)
                            UNION
                          (SELECT 4 AS userlevel,
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
                                  users.subaccount,
                                  users.seeallvehicles,
                                  users.modules,
                                  users.blocked AS userBlocked,
                                  (SELECT COALESCE(jsonb_agg(json_build_object(
                                            ''associationID'', B.associationID,
                                            ''associationName'', A.name,
                                            ''blockedAt'', B.blockedAt,
                                            ''blockedByUserID'', B.blockedByUserID,
                                            ''blockedByUserName'', U.name
                                          )), ''[]''::jsonb)
                                    FROM erp.affiliateBlocking AS B
                                    INNER JOIN erp.entities AS A ON A.entityID = B.associationID
                                    INNER JOIN erp.users AS U ON U.userID = B.blockedByUserID
                                    WHERE B.customerID = users.entityID %s
                                      AND B.unblockedAt IS NULL
                                  ) AS affiliationBlocking,
                                  users.expires,
                                  users.expiresat,
                                  users.timeRestriction,
                                  users.suspended,
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
                  associationFilter, filter, FOrder, limits);
  -- RAISE NOTICE 'SQL: %',query;

  -- Executa a consulta e retorna os dados solicitados
  FOR row IN EXECUTE query
  LOOP
    wallet.level               := row.level;
    wallet.contractorID        := row.contractorID;
    wallet.contractorName      := row.contractorName;
    wallet.contractorBlocked   := row.contractorBlocked;
    wallet.entityID            := row.entityID;
    wallet.entityName          := row.entityName;
    wallet.entityBlocked       := row.entityBlocked;
    wallet.entityType          := row.entityType;
    wallet.userID              := row.id;
    wallet.name                := row.name;
    wallet.role                := row.role;
    wallet.username            := row.username;
    wallet.groupID             := row.groupID;
    wallet.groupName           := row.groupName;
    wallet.phoneNumber         := row.phoneNumber;
    wallet.email               := row.email;
    wallet.subaccount          := row.subaccount;
    wallet.seeAllVehicles      := row.seeAllVehicles;
    wallet.modules             := row.modules;
    wallet.userBlocked         := row.userBlocked;
    -- RAISE NOTICE 'affiliationBlocking: %', row.affiliationBlocking;
    wallet.affiliationBlocking := row.affiliationBlocking;

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
    wallet.timeRestriction    := row.timeRestriction;
    wallet.suspended          := row.suspended;
    wallet.createdAt          := row.createdAt;
    wallet.updatedAt          := row.updatedAt;
    wallet.lastLogin          := row.lastLogin;
    wallet.forceNewPassword   := row.forceNewPassword;
    wallet.fullcount          := row.fullcount;

    RETURN NEXT wallet;
  END LOOP;
END
$$
LANGUAGE 'plpgsql';

-- SELECT * FROM erp.UsersWallets2(0, 0, 0, 0, '', 'name', 'contractorname, level, entityname, name', 0, 1000);

-- ---------------------------------------------------------------------
-- Dados dos técnicos
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de técnicos
-- ---------------------------------------------------------------------
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

-- SELECT * FROM erp.getTechnicians(1, '', 10);

-- ---------------------------------------------------------------------
-- Dados de prestadores de serviços
-- ---------------------------------------------------------------------
-- Cria uma stored procedure que recupera as informações de técnicos
-- ---------------------------------------------------------------------
CREATE TYPE erp.serviceprovider AS
(
  id                   integer,
  name                 varchar(50),
  fullcount            integer
);

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

-- SELECT * FROM erp.getServiceProviders(1, '', 10);

-- ---------------------------------------------------------------------
-- Dados de telefones de acordo com o perfil
-- ---------------------------------------------------------------------
-- Função que recupera os telefones de acordo com um perfil a ser usado
-- em formato de matriz.
-- ---------------------------------------------------------------------
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
  
  --RETURN array_to_string(phones, ' / ');
  RETURN phones;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Dados de telefones completos de acordo com o perfil
-- ---------------------------------------------------------------------
-- Função que recupera os dados completos de telefones de acordo com um
-- perfil a ser usado em formato de json.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getPhonesData(FcontractorID integer,
  FentityID integer, FsubsidiaryID integer, FsystemActionID integer)
  RETURNS json AS
$$
DECLARE
  subsidiaryFilter  varchar;
  query1  varchar;
  query2  varchar;
  phoneRecord record;
  json_result json;
  json_phones json;
  json_mailing json;
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
  query1 := format('
    SELECT json_agg(json_build_object(
             ''number'', P.phonenumber,
             ''typeID'', P.phoneTypeID,
             ''typeName'', PT.name,
             ''contact'', S.personName,
             ''class'', ''Principal''
           )) AS phones
      FROM erp.entities AS E
     INNER JOIN erp.subsidiaries AS S USING (entityID)
     INNER JOIN erp.phones AS P USING (subsidiaryID)
     INNER JOIN erp.phoneTypes AS PT USING (phoneTypeID)
     WHERE E.entityID = %s %s;',
     FentityID, subsidiaryFilter
  );

  EXECUTE query1 INTO json_phones;

  -- Agora selecionamos os telefones adicionais
  query2 := format('
    SELECT json_agg(json_build_object(
             ''number'', M.phonenumber,
             ''typeID'', M.phoneTypeID,
             ''typeName'', PT.name,
             ''contact'', M.name,
             ''class'', SA.name
           ))
      FROM erp.entities AS E
     INNER JOIN erp.subsidiaries AS S USING (entityID)
     INNER JOIN erp.mailingAddresses AS M USING (subsidiaryID)
     INNER JOIN erp.actionsPerProfiles AS A USING (mailingProfileID)
     INNER JOIN erp.systemactions AS SA USING (systemActionID)
      LEFT JOIN erp.phoneTypes AS PT USING (phoneTypeID)
     WHERE E.entityID = %s %s
       AND A.systemActionID = %s
       AND coalesce(M.phonenumber, '''') <> '''';',
     FentityID, subsidiaryFilter, FsystemActionID
  );

  -- RAISE NOTICE 'query2: %', query2;
  EXECUTE query2 INTO json_mailing;

  -- Concatena os dois arrays JSON, tratando casos de NULL
  IF json_phones IS NULL THEN
    json_phones := '[]'::json;
  END IF;
  IF json_mailing IS NULL THEN
    json_mailing := '[]'::json;
  END IF;
  
  json_result := (json_phones::jsonb || json_mailing::jsonb)::json;
  
  RETURN json_result;
END;
$$ LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Bloqueios de associados
-- ---------------------------------------------------------------------
-- Armazena as informações de bloqueios do acesso aos veículos de uma
-- associação, sem bloquear o acesso total do usuário. Se faz para o
-- cliente que contrata o serviço da associação e, por algum motivo,
-- esta associação deseja bloquear o seu acesso aos equipamentos por ela
-- gerenciados.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.affiliateBlocking (
  affiliateBlockingID serial,        -- O ID do bloqueio de afiliado  
  associationID       integer        -- O ID da cooperativa
                      NOT NULL,
  customerID          integer        -- O ID do cliente associado
                      NOT NULL,
  blockedAt           timestamp      -- A data/hora do bloqueio
                      NOT NULL
                      DEFAULT CURRENT_TIMESTAMP,
  blockedByUserID     integer        -- O ID do usuário responsável
                      NOT NULL,      -- pelo bloqueio
  unblockedAt         timestamp      -- A data/hora do desbloqueio
                      DEFAULT NULL,
  unblockedByUserID   integer        -- O ID do usuário responsável
                      DEFAULT NULL,  -- pelo desbloqueio
  PRIMARY KEY (affiliateBlockingID), -- O índice primário
  FOREIGN KEY (associationID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (customerID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT,
  FOREIGN KEY (blockedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (unblockedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);
