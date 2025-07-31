-- =====================================================================
-- INFORMAÇÔES COMPLEMENTARES RELACIONADAS COM EQUIPAMENTOS
-- =====================================================================
-- Tabelas utilizada a nível de cadastro em diversas partes.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Estados possíveis de um equipamento
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.equipmentStates (
  equipmentStateID  serial,       -- ID da situação
  name              varchar(30)   -- Situação em que se encontra o
                    NOT NULL,     -- equipamento
  PRIMARY KEY (equipmentStateID)
);

INSERT INTO erp.equipmentStates (equipmentStateID, name) VALUES
  (1, 'Normal'),
  (2, 'Com defeito'),
  (3, 'Em manutenção'),
  (4, 'Inutilizado');

ALTER SEQUENCE erp.equipmentstates_equipmentstateid_seq RESTART WITH 5;

-- ---------------------------------------------------------------------
-- Tipos de acessórios
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.accessoryTypes (
  accessoryTypeID serial,      -- O ID do tipo de acessório
  name            varchar(50)  -- O nome do tipo de acessório
                  NOT NULL,
  description     text         -- A descrição do tipo de acessório
                  NOT NULL,
  PRIMARY KEY (accessoryTypeID)
);

INSERT INTO erp.accessoryTypes (accessoryTypeID, name, description) VALUES
  (1, 'Botão de pânico',
    'Permite o envio de sinalização de socorro em situações de pânico ' ||
    'ou risco iminente.'),
  (2, 'Leitora de iButton',
    'Permite a leitura de dispositivos iButton de forma a identificar ' ||
    'o motorista que está na direção do veículo.'),
  (3, 'Teclado',
    'O terminal de dados, é dotado de macros pré definidas, e reúne ' ||
    'todas as necessidades de informação para a jornada de trabalho, ' ||
    'com a facilidade de um toque. Permite a identificação do ' ||
    'motorista que está na direção do veículo e bloquear o uso para ' ||
    'motoristas não cadastrados.'),
  (4, 'Sensor de temperatura',
    'Permite monitorar e transportar com maior segurança recursos que ' ||
    'necessitam do controle térmico. O controle da temperatura reduz ' ||
    'as chances de haver perda de produtos, garantindo a entrega até ' ||
    'o destino final.'),
  (5, 'Sensor de plataforma',
    'Permite monitorar quando a plataforma do seu guincho é acionada.'),
  (6, 'Sensor de caçamba',
    'Permite monitorar quando a caçamba é acionada.'),
  (7, 'Sensor de porta',
    'É utilizado para identificar movimentação, seja de abertura e/ou ' ||
    'fechamento da porta do veículo.'),
  (8, 'Sensor de engate e desengate para carretas',
    'Tem como finalidade identificar e disponibilizar a informação de ' ||
    'que a “carreta” foi desengatada do “cavalo”.'),
  (9, 'Sensor de velocidade na chuva',
    'Receba os alertas de velocidades em pista molhada, quando o ' ||
    'motorista ligar o limpador o rastreador recebera o sinal e ' ||
    'começara a calcular a velocidade de acordo com os parâmetros ' ||
    'pré estabelecidos para pista molhada');

ALTER SEQUENCE erp.accessorytypes_accessorytypeid_seq RESTART WITH 10;

-- ---------------------------------------------------------------------
-- Características técnicas
-- ---------------------------------------------------------------------
-- Armazena as informações de características técnicas que um equipamento
-- de rastreamento deve ter para que o mesmo atenda aos requisitos de um
-- contrato.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.features (
  featureID           serial,         -- O ID da característica
  name                varchar(50)     -- O nome da característica
                      NOT NULL,
  needAnalogInput     boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de entrada analógica
  needAnalogOutput    boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de saída analógica
  needDigitalInput    boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de entrada digital
  needDigitalOutput   boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de saída digital
  needRFModule        boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- do módulo RF
  needOnOffButton     boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de botão liga/desliga
  needBoxOpenSensor   boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de sensor de caixa aberta
  needRS232Interface  boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- da interface RS232
  needIbuttonInput    boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de entrada iButton
  needAntiJammer      boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de um anti-jammer
  needRPMInput        boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de entrada RPM física
  needOdometerInput   boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de entrada para Odômetro física
  needAccelerometer   boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de um sensor de acelerômetro
  needAccessory       boolean         -- O indicativo de que necessita
                      DEFAULT false,  -- de um acessório
  accessoryTypeID     integer         -- O tipo de acessório requerido
                      DEFAULT NULL
                      CHECK ( (NOT needAccessory) OR (accessoryTypeID IS NOT NULL) ),
  PRIMARY KEY (featureID),
  FOREIGN KEY (accessoryTypeID)
    REFERENCES erp.accessoryTypes(accessoryTypeID)
    ON DELETE RESTRICT
);

-- Insere as características
INSERT INTO erp.features (featureID, name, needAnalogInput,
  needAnalogOutput, needDigitalInput, needDigitalOutput, needRFModule,
  needOnOffButton, needBoxOpenSensor, needRS232Interface,
  needIbuttonInput, needAntiJammer, needRPMInput, needOdometerInput,
  needAccelerometer, needAccessory, accessoryTypeID) VALUES
  (1, 'Acelerômetro', false, false, false, false, false, false, false, false, false, false, false, false, true, false, NULL),
  (2, 'Anti Jammer', false, false, false, false, false, false, false, false, false, true, false, false, false, false, NULL),
  (3, 'Bloqueio', false, false, false, true, false, false, false, false, false, false, false, false, false, false, NULL),
  (4, 'Botão de pânico', false, false, true, false, false, false, false, false, false, false, false, false, false, true, 1),
  (5, 'Controle de RPM', false, false, false, false, false, false, false, false, false, false, true, false, false, false, NULL),
  (6, 'Controle de velocidade', false, false, false, false, false, false, false, false, false, false, false, false, false, false, NULL),
  (7, 'Estado da ignição', false, false, false, false, false, false, false, false, false, false, false, false, false, false, NULL),
  (8, 'Horímetro físico', false, false, true, false, false, false, false, false, false, false, false, false, false, false, NULL),
  (9, 'Horímetro por GPS', false, false, false, false, false, false, false, false, false, false, false, false, false, false, NULL),
  (10, 'Leitora de iButton', false, false, false, false, false, false, false, false, true, false, false, false, false, true, 2),
  (11, 'Localização', false, false, false, false, false, false, false, false, false, false, false, false, false, false, NULL),
  (12, 'Odômetro físico', false, false, false, false, false, false, false, false, false, false, false, true, false, false, NULL),
  (13, 'Odômetro por GPS', false, false, false, false, false, false, false, false, false, false, false, false, false, false, NULL),
  (14, 'Sensor de caçamba', false, false, true, false, false, false, false, false, false, false, false, false, false, true, 6),
  (15, 'Sensor de engate e desengate para carretas', false, false, true, false, false, false, false, false, false, false, false, false, false, true, 8),
  (16, 'Sensor de plataforma', false, false, true, false, false, false, false, false, false, false, false, false, false, true, 5),
  (17, 'Sensor de porta', false, false, true, false, false, false, false, false, false, false, false, false, false, true, 7),
  (18, 'Sensor de temperatura', false, false, true, false, false, false, false, false, false, false, false, false, false, true, 4),
  (19, 'Sensor de velocidade na chuva', false, false, true, false, false, false, false, false, false, false, false, false, false, true, 9),
  (20, 'Teclado', false, false, false, false, false, false, false, true, false, false, false, false, false, true, 3),
  (21, 'Telemetria', false, false, false, false, false, false, false, false, false, false, true, true, true, false, NULL);

ALTER SEQUENCE erp.features_featureid_seq RESTART WITH 22;

-- ---------------------------------------------------------------------
-- Tipos de armazenamentos
-- ---------------------------------------------------------------------
-- Indentifica os tipos de armazenamentos possíveis para um dispositivo.
-- Com isto podemos determinar os locais físicos onde um dispositivo
-- (SIM Card ou Equipamento) encontra-se, permitindo o correto controle.
-- 
-- Os tipos de armazenamento são:
--   - StoredOnDeposit: quando um dispositivo está armazenado em um
--     depósito físico.
--   - Installed: quando um dispositivo está instalado, ou seja, o Sim
--     Card está inserido em um equipamento ou o equipamento está
--     instalado em um veículo.
--   - StoredWithTechnician: indica que o dispositivo está de posse do
--     técnico (ex: para ser instalado).
--   - StoredWithServiceProvider: indica que o dispositivo está de posse
--     do prestador de serviços (ex: para ser enviado aos respectivos
--     técnicos para instalação).
--   - UnderMaintenance: indica que o dispositivo está em manutenção.
--   - ReturnedToSupplier: indica que o dispositivo foi devolvido para o
--     fornecedor.
-- ---------------------------------------------------------------------
CREATE TYPE StorageType AS ENUM('StoredOnDeposit', 'Installed',
  'StoredWithTechnician', 'StoredWithServiceProvider',
  'UnderMaintenance', 'ReturnedToSupplier');

-- ---------------------------------------------------------------------
-- Tipos de dispositivos armazenáveis num depósito
-- ---------------------------------------------------------------------
-- Os tipos de dispositivos que podem ser armazenáveis num determinado
-- depósito.
-- ---------------------------------------------------------------------
CREATE TYPE DeviceType AS ENUM('Both', 'SimCard', 'Equipment');

-- ---------------------------------------------------------------------
-- Tipo de operações
-- ---------------------------------------------------------------------
-- As possíveis operações realizadas com cada dispositivo:
--   - Acquired: quando é adquirido (situação inicial)
--   - Transferred: quando é transferido para outro local
--   - Installed: quando é instalado
--   - Uninstalled: quando é desinstalado
--   - Replaced: quando é substituído
--   - DefectDetected: quando é observado um defeito no dispositivo
--   - SentForMaintenance: quando é enviado para manutenção
-- ---------------------------------------------------------------------
CREATE TYPE OperationType AS ENUM('Acquired', 'Transferred', 'Returned',
  'Installed', 'Uninstalled', 'DefectDetected', 'SentForMaintenance');

-- ---------------------------------------------------------------------
-- Os tipos de protocolos de comunicação existentes.
-- ---------------------------------------------------------------------
-- A tabela que armazena os tipos de protocolos suportados pelo sistema.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.protocols (
  protocolID  serial,         -- ID do protocolo
  name        varchar(20)     -- O nome do protocolo
              NOT NULL,
  UNIQUE (name),
  PRIMARY KEY (protocolID)
);

INSERT INTO erp.protocols (protocolID, name) VALUES
  (1, 'GT06'),
  (2, 'EasyTrack'),
  (3, 'Suntech ST200'),
  (4, 'Suntech ST300'),
  (5, 'RST');

ALTER SEQUENCE erp.protocols_protocolid_seq RESTART WITH 6;

-- ---------------------------------------------------------------------
-- As variantes de protocolos
-- ---------------------------------------------------------------------
-- A tabela que armazena as variantes de um mesmo protocolo, de forma a
-- separar os comandos suportados por cada variante.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.protocolVariants (
  protocolVariantID serial,        -- O ID da variante do protocolo
  protocolID        integer        -- O ID do protocolo
                    NOT NULL,
  name              varchar(50)    -- O nome da variante do protocolo
                    NOT NULL,
  description       text           -- A descrição da variante do
                    NOT NULL,      -- protocolo
  UNIQUE (name, protocolID),
  PRIMARY KEY (protocolVariantID), -- O indice primário
  FOREIGN KEY (protocolID)
    REFERENCES erp.protocols(protocolID)
    ON DELETE CASCADE
);

INSERT INTO erp.protocolVariants (protocolVariantID, protocolID, name,
  description) VALUES
  (1, 1, 'X3 Tech', 'Compatível com equipamentos X3 Tech, tais como NT20 e NT40'),
  (2, 1, 'J16 Padrão', 'Compatível com equipamentos J16 compatíveis'),
  (3, 1, 'JimiIout', 'Compatível com o VL03'),
  (4, 2, 'BWS', 'Compatível com os equipamentos E3 e E3+'),
  (5, 1, 'OneBlock', 'Compatível com o OneBlock 2G/4G'),
  (6, 3, 'ST215E', 'Suntech modelo ST215E'),
  (7, 3, 'ST215H', 'Suntech modelo ST215H'),
  (8, 3, 'ST215I', 'Suntech modelo ST215I'),
  (9, 3, 'ST215LC', 'Suntech modelo ST215LC'),
  (10, 3, 'ST215W', 'Suntech modelo ST215W'),
  (11, 3, 'ST215WLC', 'Suntech modelo ST215WLC'),
  (12, 3, 'ST240', 'Suntech modelo ST240'),
  (13, 4, 'ST300H', 'Suntech modelo ST300H'),
  (14, 4, 'ST300HD', 'Suntech modelo ST300HD'),
  (15, 4, 'ST300R', 'Suntech modelo ST300R'),
  (16, 4, 'ST310U', 'Suntech modelo ST310U'),
  (17, 4, 'ST340/ST340N', 'Suntech modelo ST340'),
  (18, 4, 'ST340LC', 'Suntech modelo ST340LC'),
  (19, 4, 'ST340RB', 'Suntech modelo ST340RB'),
  (20, 4, 'ST340U', 'Suntech modelo ST340U'),
  (21, 4, 'ST340UR', 'Suntech modelo ST340UR'),
  (22, 4, 'ST350', 'Suntech modelo ST350'),
  (23, 4, 'ST350LC2', 'Suntech modelo ST350LC2'),
  (24, 4, 'ST350LC4', 'Suntech modelo ST350LC4'),
  (25, 4, 'ST380', 'Suntech modelo ST380'),
  (26, 4, 'ST390', 'Suntech modelo ST390'),
  (27, 4, 'ST400', 'Suntech modelo ST400'),
  (28, 4, 'ST410G', 'Suntech modelo ST410G'),
  (29, 4, 'ST419', 'Suntech modelo ST419'),
  (30, 4, 'ST420', 'Suntech modelo ST420'),
  (31, 4, 'ST440', 'Suntech modelo ST440'),
  (32, 4, 'ST449', 'Suntech modelo ST449'),
  (33, 4, 'J16 YG', 'Compatível com equipamentos J16 com comandos YG'),
  (34, 5, 'RST-LC', 'Compatível com equipamentos RST-LC'),
  (35, 1, 'OneBlock iButton', 'Compatível com o OneBlock 2G/4G com suporte a iButton'),
  (36, 1, 'Jimi DashCam', 'Equipamento com sistema de câmera veicular e ADAS/DMS');

ALTER SEQUENCE erp.protocolvariants_protocolvariantid_seq RESTART WITH 37;

-- ---------------------------------------------------------------------
-- Tipos de tecnologias de identificação de motoristas
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.identifierTechnologies (
    identifierTechnologyID  serial,       -- ID da tenologia de identificação
    name                    varchar(50)   -- O nome da tecnologia
                            NOT NULL,
    PRIMARY KEY (identifierTechnologyID)
);

INSERT INTO erp.identifierTechnologies (identifierTechnologyID, name) VALUES
  (1, 'iButton');

ALTER SEQUENCE erp.identifiertechnologies_identifiertechnologyid_seq RESTART WITH 2;


-- ---------------------------------------------------------------------
-- Marcas de Equipamentos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.equipmentBrands (
  equipmentBrandID  serial,       -- ID da marca do equipamento
  name              varchar(30)   -- Marca do equipamento
                    NOT NULL,
  madeTracker       boolean       -- Flag que indica que esta marca
                    NOT NULL      -- fabrica rastreadores
                    DEFAULT true,
  madeAccessory     boolean       -- Flag que indica que esta marca
                    NOT NULL      -- fabrica acessórios
                    DEFAULT false,
  PRIMARY KEY (equipmentBrandID)
);

INSERT INTO erp.equipmentBrands (equipmentBrandID, name, madeTracker,
  madeAccessory) VALUES
  ( 1, 'Não informado',       true,  true),
  ( 2, 'Indefinido',          true,  false),
  ( 3, 'BWS IoT',             true,  false),
  ( 4, 'Coban',               true,  false),
  ( 5, 'Concox',              true,  false),
  ( 6, 'GlobalStar',          true,  false),
  ( 7, 'Hinova Equipamentos', true,  false),
  ( 8, 'Jimi IoT',            true,  false),
  ( 9, 'MaxTrack',            true,  false),
  (10, 'Multiportal',         true,  false),
  (11, 'Nonus',               true,  false),
  (12, 'On Star',             true,  false),
  (13, 'OneBlock',            true,  false),
  (14, 'Quecklink',           true,  false),
  (15, 'SGBras',              false, true),
  (16, 'Shen Daovay',         true,  false),
  (17, 'SigFox',              true,  false),
  (18, 'STG',                 true,  false),
  (19, 'Suntech',             true,  false),
  (20, 'Tracker King',        true,  false),
  (21, 'X3Tech',              true,  false);

ALTER SEQUENCE erp.equipmentbrands_equipmentbrandid_seq RESTART WITH 22;


-- ---------------------------------------------------------------------
-- Modelos de Equipamentos
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS erp.equipmentModels (
  equipmentModelID      serial,         -- ID do modelo de equipamento
  name                  varchar(50)     -- Nome do modelo do equipamento
                        NOT NULL,
  equipmentBrandID      integer         -- ID da marca do equipamento
                        NOT NULL,
  maxSimCards           smallint        -- Número máximo de SIM Cards
                        NOT NULL        -- associáveis ao equipamento
                        DEFAULT 1,
  simcardTypeID         integer         -- O tipo (modelo) do SIM Card aceito
                        NOT NULL,
  analogInput           smallint        -- Número de entradas analógicas
                        NOT NULL
                        DEFAULT 0,
  analogOutput          smallint        -- Número de saídas analógicas
                        NOT NULL
                        DEFAULT 0,
  digitalInput          smallint        -- Número de entradas digitais
                        NOT NULL
                        DEFAULT 0,
  digitalOutput         smallint        -- Número de saídas digitais
                        NOT NULL
                        DEFAULT 0,
  hasRFModule           boolean         -- O indicativo de que este modelo
                        DEFAULT false,  -- possui módulo RF
  hasOnOffButton        boolean         -- O indicativo de que este modelo
                        DEFAULT false,  -- possui botão liga/desliga
  hasBoxOpenSensor      boolean         -- O indicativo de que este modelo
                        DEFAULT false,  -- possui sensor de abertura de caixa
  hasRS232Interface     boolean         -- O indicativo de que este modelo
                        DEFAULT false,  -- possui a interface RS232
  hasIbuttonInput       boolean         -- O indicativo de que este modelo
                        DEFAULT false,  -- possui a interface iButton
  iButtonsMemSize       integer         -- A quantidade de iButtons que
                        NOT NULL        -- podem ser armazenados
                        DEFAULT 0,
  hasAntiJammer         boolean         -- O indicativo de que este modelo
                        DEFAULT false,  -- possui anti-jammer
  hasRPMInput           boolean         -- O indicativo de que este modelo
                        DEFAULT false,  -- possui entrada para tacômetro
  hasHorimeter          boolean         -- O indicativo de que este modelo
                        DEFAULT false,  -- possui horímetro
  hasOdometerInput      boolean         -- O indicativo de que este modelo
                        DEFAULT false,  -- possui entrada para odômetro
  hasAccelerometer      boolean         -- O indicativo de que este modelo
                        DEFAULT false,  -- possui sensor de acelerômetro
  serialNumberSize      integer,        -- A quantidade de dígitos do número
                                        -- de série
  reducedNumberSize     integer         -- A quantidade de dígitos do número
                        DEFAULT 0,      -- de série utilizados no formato
                                        -- reduzido para identificar
  protocolID            integer         -- O ID do protocolo de comunicação
                        DEFAULT NULL,   -- utilizado pelo equipamento
  protocolVariantID     integer         -- O ID da variante de protocolo
                        DEFAULT NULL,   -- utilizado pelo equipamento
  operatingFrequenceID  integer         -- A frequência de operação do
                        NOT NULL        -- aparelho
                        DEFAULT 1,
  createdAt             timestamp       -- A data de criação do modelo de
                        NOT NULL        -- equipamento
                        DEFAULT CURRENT_TIMESTAMP,
  createdByUserID       integer         -- O ID do usuário responsável pelo
                        NOT NULL,       -- cadastro deste modelo de equipamento
  updatedAt             timestamp       -- A data de modificação do modelo de
                        NOT NULL        -- equipamento
                        DEFAULT CURRENT_TIMESTAMP,
  updatedByUserID       integer         -- O ID do usuário responsável pela
                        NOT NULL,       -- última modificação deste modelo
  PRIMARY KEY (equipmentModelID),
  FOREIGN KEY (equipmentBrandID)
    REFERENCES erp.equipmentBrands(equipmentBrandID)
    ON DELETE CASCADE,
  FOREIGN KEY (simcardTypeID)
    REFERENCES erp.simcardTypes(simcardTypeID)
    ON DELETE RESTRICT,
  FOREIGN KEY (protocolID)
    REFERENCES erp.protocols(protocolID)
    ON DELETE RESTRICT,
  FOREIGN KEY (protocolVariantID)
    REFERENCES erp.protocolVariants(protocolVariantID)
    ON DELETE RESTRICT,
  FOREIGN KEY (createdByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT,
  FOREIGN KEY (updatedByUserID)
    REFERENCES erp.users(userID)
    ON DELETE RESTRICT
);

INSERT INTO erp.equipmentModels (equipmentModelID, name,
  equipmentBrandID, maxSimCards, simcardTypeID, analogInput, analogOutput,
  digitalInput, digitalOutput, serialNumberSize, reducedNumberSize,
  hasRFModule, hasOnOffButton, hasBoxOpenSensor, hasRS232Interface,
  hasIbuttonInput, iButtonsMemSize, hasAntiJammer, hasRPMInput,
  hasHorimeter, hasOdometerInput, hasAccelerometer, protocolID,
  protocolVariantID, operatingFrequenceID, createdByUserID,
  updatedByUserID) VALUES 
  ( 1, 'Rastreador fake',  1, 0, 1, 0, 0, 0, 0, 15, 0, false, false, false, false, false,   0, false, false, false, false, false, NULL, NULL, 1, 1, 1),
  ( 2, 'E3',               3, 1, 1, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false, false, false,  true,    2,    4, 2, 1, 1),
  ( 3, 'E3 + Long Life',   3, 1, 1, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false, false, false,  true,    2,    4, 2, 1, 1),
  ( 4, 'E3+',              3, 1, 1, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false, false, false,  true,    2,    4, 2, 1, 1),
  ( 5, 'TK-311',           4, 1, 1, 0, 0, 1, 1,  9, 0, false, false, false, false, false,   0, false, false, false, false, false, NULL, NULL, 2, 1, 1),
  ( 6, 'HE-114',           7, 1, 1, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false, false, false, false, NULL, NULL, 2, 1, 1),
  ( 7, 'VL03',             8, 1, 1, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false, false, false, false,    1,    3, 2, 1, 1),
  ( 8, 'JC450',            8, 1, 1, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false, false, false, false,    1,   36, 2, 1, 1),
  ( 9, 'RST-MINI-LC',     10, 1, 1, 0, 0, 2, 2,  9, 0, false, false, false,  true, false,   0,  true, false, false, false,  true,    5,   34, 2, 1, 1),
  (10, 'N2',              11, 1, 1, 0, 0, 1, 1, 15, 0, false,  true, false, false, false,   0, false, false, false, false,  true,    1,    3, 2, 1, 1),
  (11, 'N4',              11, 1, 1, 0, 0, 1, 1, 15, 0, false,  true, false, false, false,   0, false, false, false, false,  true,    1,    3, 4, 1, 1),
  (12, 'EC33',            12, 1, 1, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0,  true, false, false, false,  true,    1,    2, 2, 1, 1),
  (13, '2G',              13, 1, 1, 0, 0, 1, 1, 15, 0, false,  true, false, false, false,   0, false, false, false, false,  true,    1,    5, 2, 1, 1),
  (14, '4G',              13, 1, 1, 0, 0, 1, 1, 15, 0, false,  true, false, false, false,   0, false, false, false, false,  true,    1,    5, 4, 1, 1),
  (15, '4G LC',           13, 1, 1, 0, 0, 1, 1, 15, 0, false,  true, false, false, false,   0, false, false, false, false,  true,    1,    2, 4, 1, 1),
  (16, '4G mini',         13, 1, 1, 0, 0, 1, 1, 15, 0, false,  true, false, false, false,   0, false, false, false, false,  true,    1,    5, 4, 1, 1),
  (17, '4G iButton',      13, 1, 1, 0, 0, 1, 1, 15, 0, false,  true, false, false,  true, 100, false, false, false, false,  true,    1,   35, 4, 1, 1),
  (18, 'J16',             16, 1, 1, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false, false, false,  true,    1,    2, 4, 1, 1),
  (19, 'J16 Port',        16, 1, 1, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false, false, false,  true,    1,    2, 4, 1, 1),
  (20, 'J16 YG',          16, 1, 1, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false, false, false,  true,    1,   33, 4, 1, 1),
  (21, 'ST215E',          19, 1, 3, 0, 0, 3, 2,  6, 0, false, false, false, false, false,   0, false, false,  true, false,  true,    3,    6, 2, 1, 1),
  (22, 'ST215H',          19, 1, 3, 0, 0, 2, 2,  6, 0, false, false, false, false,  true,   0,  true,  true,  true,  true,  true,    3,    7, 2, 1, 1),
  (23, 'ST215I',          19, 1, 3, 0, 0, 3, 2,  6, 0, false, false, false, false, false,   0, false, false,  true, false,  true,    3,    8, 2, 1, 1),
  (24, 'ST215LC',         19, 1, 3, 0, 0, 1, 1,  6, 0, false, false, false, false, false,   0, false, false,  true, false,  true,    3,    9, 2, 1, 1),
  (25, 'ST215W',          19, 1, 3, 0, 0, 1, 2,  6, 0, false, false, false, false, false,   0, false, false,  true, false,  true,    3,   10, 2, 1, 1),
  (26, 'ST215WLC',        19, 1, 3, 0, 0, 1, 1,  6, 0, false, false, false, false, false,   0, false, false,  true, false,  true,    3,   11, 2, 1, 1),
  (27, 'ST240',           19, 1, 3, 0, 0, 3, 2,  6, 0, false, false, false, false, false,   0, false, false,  true, false,  true,    3,   12, 2, 1, 1),
  (28, 'ST300H',          19, 1, 3, 0, 0, 2, 2,  9, 6, false, false, false,  true,  true, 100,  true,  true,  true,  true,  true,    4,   13, 2, 1, 1),
  (29, 'ST300HD',         19, 1, 3, 0, 0, 2, 2,  9, 6, false, false, false,  true, false,   0,  true, false,  true, false,  true,    4,   14, 2, 1, 1),
  (30, 'ST300R',          19, 1, 3, 0, 0, 3, 2,  9, 6, false, false, false,  true, false,   0,  true, false,  true, false,  true,    4,   15, 2, 1, 1),
  (31, 'ST310U',          19, 1, 3, 0, 0, 2, 1,  9, 6, false, false, false, false, false,   0,  true, false,  true, false,  true,    4,   16, 2, 1, 1),
  (32, 'ST340',           19, 1, 3, 0, 0, 3, 2,  9, 6, false, false, false, false, false,   0,  true, false,  true, false,  true,    4,   17, 2, 1, 1),
  (33, 'ST340LC',         19, 1, 3, 0, 0, 1, 1,  9, 6, false, false, false, false, false,   0,  true, false,  true, false,  true,    4,   18, 2, 1, 1),
  (34, 'ST340N',          19, 1, 3, 0, 0, 3, 2,  9, 6, false, false, false, false, false,   0,  true, false,  true, false,  true,    4,   17, 2, 1, 1),
  (35, 'ST340RB',         19, 1, 3, 0, 0, 2, 1,  9, 6, false, false, false,  true, false,   0,  true, false,  true, false,  true,    4,   19, 2, 1, 1),
  (36, 'ST340U',          19, 1, 3, 0, 0, 3, 3,  9, 6, false, false, false, false, false,   0,  true, false,  true, false,  true,    4,   20, 2, 1, 1),
  (37, 'ST340UR',         19, 1, 3, 0, 0, 2, 1,  9, 6, false, false, false,  true,  true, 100,  true, false,  true, false,  true,    4,   21, 2, 1, 1),
  (38, 'ST350',           19, 1, 3, 0, 0, 1, 1,  9, 6, false, false, false, false, false,   0,  true, false,  true, false,  true,    4,   22, 2, 1, 1),
  (39, 'ST350LC2',        19, 1, 3, 0, 0, 0, 0,  9, 6, false, false, false, false, false,   0,  true, false,  true, false,  true,    4,   23, 2, 1, 1),
  (40, 'ST350LC4',        19, 1, 3, 0, 0, 1, 1,  9, 6, false, false, false, false, false,   0,  true, false,  true, false,  true,    4,   24, 2, 1, 1),
  (41, 'ST380',           19, 1, 3, 0, 0, 1, 1,  9, 6,  true, false, false, false, false,   0,  true, false,  true, false,  true,    4,   25, 2, 1, 1),
  (42, 'ST390',           19, 1, 3, 0, 0, 1, 1,  9, 6,  true, false, false, false, false,   0,  true, false,  true, false,  true,    4,   26, 2, 1, 1),
  (43, 'ST400',           19, 1, 3, 0, 0, 0, 0,  9, 6,  true,  true,  true, false, false,   0,  true, false,  true, false, false,    4,   27, 2, 1, 1),
  (44, 'ST410G',          19, 1, 3, 0, 0, 0, 0,  9, 6,  true,  true,  true, false, false,   0,  true, false,  true, false, false,    4,   28, 2, 1, 1),
  (45, 'ST419',           19, 1, 3, 0, 0, 0, 0,  9, 6,  true,  true,  true, false, false,   0,  true, false,  true, false, false,    4,   29, 2, 1, 1),
  (46, 'ST420',           19, 1, 3, 0, 0, 0, 0,  9, 6,  true,  true,  true, false, false,   0,  true, false,  true, false, false,    4,   30, 2, 1, 1),
  (47, 'ST440',           19, 1, 3, 0, 0, 0, 0,  9, 6,  true,  true,  true, false, false,   0,  true, false,  true, false, false,    4,   31, 2, 1, 1),
  (48, 'ST449',           19, 1, 3, 0, 0, 0, 0,  9, 6,  true,  true,  true, false, false,   0,  true, false,  true, false, false,    4,   32, 2, 1, 1),
  (49, 'ST940',           19, 1, 3, 0, 0, 0, 0,  9, 6,  true,  true, false, false, false,   0, false, false, false, false,  true,    4,   13, 2, 1, 1),
  (50, 'ST4305',          19, 1, 3, 0, 0, 1, 1,  9, 6,  true, false, false, false,  true, 100, false, false,  true, false,  true,    4,   27, 4, 1, 1),
  (51, 'ST4315U',         19, 1, 3, 0, 0, 1, 1,  9, 6,  true, false, false, false, false,   0, false, false,  true, false,  true,    4,   27, 4, 1, 1),
  (52, 'NT20',            21, 1, 3, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false,  true, false,  true,    1,    1, 2, 1, 1),
  (53, 'NT26',            21, 1, 3, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false,  true, false,  true,    1,    1, 2, 1, 1),
  (54, 'NT40',            21, 1, 3, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false,  true, false,  true,    1,    1, 4, 1, 1),
  (55, 'XT40',            21, 1, 3, 0, 0, 1, 1, 15, 0, false, false, false, false, false,   0, false, false,  true, false,  true,    1,    1, 4, 1, 1);

ALTER SEQUENCE erp.equipmentmodels_equipmentmodelid_seq RESTART WITH 56;

-- ---------------------------------------------------------------------
-- Transações nos modelos de equipamentos
-- ---------------------------------------------------------------------
-- Gatilho para lidar com os modelos de equipamentos
-- TODO: Precisa criar uma modificação para lidar com os equipamentos
-- que estão instalados para certificar que a modificação de uma das
-- características não vai resultar em equipamentos que deixem de atender
-- ao que está estipulado no contrato do cliente.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.equipmentModelTransaction()
RETURNS trigger AS $$
DECLARE
  amountOfRegisters     integer;
BEGIN
  -- Faz a verificação da quantidade máxima de SIM Cards por equipamento
  -- em caso de mudança. Faz uso da variável especial TG_OP para
  -- verificar a operação executada.
  IF (TG_OP = 'INSERT') THEN
    -- Retornamos a nova entidade
    RETURN NEW;
  ELSIF (TG_OP = 'UPDATE') THEN
    -- Verifica se estamos modificando a quantidade máxima de Sim
    -- Cards neste modelo de equipamento
    IF (NEW.maxSimCards <> OLD.maxSimCards) THEN
      -- Verificamos a quantidade de Slots existentes
      SELECT count(*) INTO amountOfRegisters
        FROM erp.simcards
       INNER JOIN erp.equipments USING (equipmentID)
       WHERE equipments.equipmentModelID = OLD.equipmentModelID
         AND simcards.slotNumber > NEW.maxSimCards;

      IF FOUND THEN
        IF (amountOfRegisters > 0) THEN
          -- Disparamos uma exceção
          RAISE EXCEPTION 'Temos % equipamentos que possuem SIM Cards instalados em slots que ultrapassam o limite de % por equipamento.', amountOfRegisters, NEW.maxSimCards
          USING HINT = 'Por favor, verifique os equipamentos cadastrados.';
        END IF;
      END IF;
    END IF;

    -- Retornamos a nova entidade
    RETURN NEW;
  END IF;

  -- Qualquer outro resultado é ignorado após o processamento anterior
  RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER equipmentModelTransactionTriggerBefore
  BEFORE INSERT OR UPDATE ON erp.equipmentModels
  FOR EACH ROW EXECUTE PROCEDURE erp.equipmentModelTransaction();
