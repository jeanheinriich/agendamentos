-- Corrigimos o erro na função na interpretação do ano quando o mesmo
-- for inválido
CREATE OR REPLACE FUNCTION erp.getHolidaysOnYear(inquiredYear char(4),
  inquiredCityID integer)
RETURNS SETOF erp.holiday AS
$$
DECLARE
  holidayOnYear  erp.holiday%rowtype;
  nextHoliday    record;
  inquiredState  char(2);
  validYear      boolean;
BEGIN
  -- Validamos o ano fornecido
  EXECUTE 'SELECT $1 ~ ''^[0-9]{4}$'''
    INTO validYear
   USING inquiredYear;

  IF (validYear = FALSE) THEN
    -- Disparamos uma exceção
    RAISE EXCEPTION 'Informe um ano válido';
  END IF;

  -- Recuperamos a UF da cidade indicada
  -- Localiza primeiramente a UF da cidade indicada pelo ID da cidade
  EXECUTE 'SELECT state FROM erp.cities WHERE cityID = $1'
     INTO inquiredState
    USING inquiredCityID;

  -- Montamos a relação dos feriados para a cidade, unindo os feriados
  -- nacionais (fixos e móveis), estaduais e municipais
  FOR nextHoliday IN
  -- Selecionamos os feriados nacionais (fixos)
  SELECT holidayID AS id,
         geographicScope,
         day,
         public.DayOfWeekName(EXTRACT(DOW FROM (inquiredYear || '-' || month || '-' || day)::DATE)::int) AS dayOfWeekName,
         month,
         public.MonthName(month) AS monthName,
         (inquiredYear || '-' || month || '-' || day)::DATE AS fullDate,
         name
    FROM erp.holidays
   WHERE geographicScope = 'Nacional'
   UNION
  -- Selecionamos os feriados nacionais (móveis)
  -- Segunda-feira de carnaval
  SELECT 0,
         'Nacional',
         EXTRACT(DAY FROM public.easter(inquiredYear::int) - 48),
         public.DayOfWeekName(EXTRACT(DOW FROM public.easter(inquiredYear::int) - 48)::int),
         EXTRACT(MONTH FROM public.easter(inquiredYear::int) - 48),
         public.MonthName(EXTRACT(MONTH FROM public.easter(inquiredYear::int) - 48)::int),
         (public.easter(inquiredYear::int) - 48)::DATE,
         'Carnaval'
   UNION
  -- Terça-feira de carnaval
  SELECT 0,
         'Nacional',
         EXTRACT(DAY FROM public.easter(inquiredYear::int) - 47),
         public.DayOfWeekName(EXTRACT(DOW FROM public.easter(inquiredYear::int) - 47)::int),
         EXTRACT(MONTH FROM public.easter(inquiredYear::int) - 47),
         public.MonthName(EXTRACT(MONTH FROM public.easter(inquiredYear::int) - 47)::int),
         (public.easter(inquiredYear::int) - 47)::DATE,
         'Carnaval'
   UNION
  -- Sexta-feira da paixão
  SELECT 0,
         'Nacional',
         EXTRACT(DAY FROM public.easter(inquiredYear::int) - 2),
         public.DayOfWeekName(EXTRACT(DOW FROM public.easter(inquiredYear::int) - 2)::int),
         EXTRACT(MONTH FROM public.easter(inquiredYear::int) - 2),
         public.MonthName(EXTRACT(MONTH FROM public.easter(inquiredYear::int) - 2)::int),
         (public.easter(inquiredYear::int) - 2)::DATE,
         'Paixão de Cristo'
   UNION
  -- Domingo de páscoa
  SELECT 0,
         'Nacional',
         EXTRACT(DAY FROM public.easter(inquiredYear::int)),
         public.DayOfWeekName(EXTRACT(DOW FROM public.easter(inquiredYear::int))::int),
         EXTRACT(MONTH FROM public.easter(inquiredYear::int)),
         public.MonthName(EXTRACT(MONTH FROM public.easter(inquiredYear::int))::int),
         public.easter(inquiredYear::int)::DATE,
         'Páscoa'
   UNION
  -- Corpus Christi
  SELECT 0,
         'Nacional',
         EXTRACT(DAY FROM public.easter(inquiredYear::int) + 60),
         public.DayOfWeekName(EXTRACT(DOW FROM public.easter(inquiredYear::int) + 60)::int),
         EXTRACT(MONTH FROM public.easter(inquiredYear::int) + 60),
         public.MonthName(EXTRACT(MONTH FROM public.easter(inquiredYear::int) + 60)::int),
         (public.easter(inquiredYear::int) + 60)::DATE,
         'Corpus Christi'
   UNION
  -- Selecionamos os feriados estaduais
  SELECT holidayID AS id,
         geographicScope,
         day,
         public.DayOfWeekName(EXTRACT(DOW FROM (inquiredYear || '-' || month || '-' || day)::DATE)::int) AS dayOfWeekName,
         month,
         public.MonthName(month) AS monthName,
         (inquiredYear || '-' || month || '-' || day)::DATE AS fullDate,
         name
    FROM erp.holidays
   WHERE geographicScope = 'Estadual'
     AND state = inquiredState
   UNION
  -- Selecionamos os feriados municipais
  SELECT holidayID AS id,
         geographicScope,
         day,
         public.DayOfWeekName(EXTRACT(DOW FROM (inquiredYear || '-' || month || '-' || day)::DATE)::int) AS dayOfWeekName,
         month,
         public.MonthName(month) AS monthName,
         (inquiredYear || '-' || month || '-' || day)::DATE AS fullDate,
         name
    FROM erp.holidays
   WHERE geographicScope = 'Municipal'
     AND cityID = inquiredCityID
  loop
    holidayOnYear.id               = nextHoliday.id;
    holidayOnYear.geographicScope  = nextHoliday.geographicScope;
    holidayOnYear.day              = nextHoliday.day;
    holidayOnYear.dayOfWeekName    = nextHoliday.dayOfWeekName;
    holidayOnYear.month            = nextHoliday.month;
    holidayOnYear.monthName        = nextHoliday.monthName;
    holidayOnYear.fullDate         = nextHoliday.fullDate;
    holidayOnYear.name             = nextHoliday.name;

    RETURN NEXT holidayOnYear;
  END loop;
  IF (inquiredCityID = 882) THEN
    -- Acrescenta o feriado de Nossa Senhora da Penha em Vitória - ES
    holidayOnYear.id               = 0;
    holidayOnYear.geographicScope  = 'Municipal';
    holidayOnYear.day              = EXTRACT(DAY FROM public.easter(inquiredYear::int) + 8);
    holidayOnYear.dayOfWeekName    = public.DayOfWeekName(EXTRACT(DOW FROM public.easter(inquiredYear::int) + 8)::int);
    holidayOnYear.month            = EXTRACT(MONTH FROM public.easter(inquiredYear::int) + 8);
    holidayOnYear.monthName        = public.MonthName(EXTRACT(MONTH FROM public.easter(inquiredYear::int) + 8)::int);
    holidayOnYear.fullDate         = (public.easter(inquiredYear::int) + 8)::DATE;
    holidayOnYear.name             = 'Nossa Senhora da Penha';

    RETURN NEXT holidayOnYear;
  END IF;
END
$$
LANGUAGE 'plpgsql';

-- ---------------------------------------------------------------------
-- Determinar se uma rota é permitida para um determinado grupo
-- ---------------------------------------------------------------------
-- Permite verificar se uma determinada rota é permitida para um
-- usuário.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.hasPermission(FgroupID integer,
  FrouteName varchar(100), FhttpMethod HTTPMethod)
RETURNS boolean AS
$$
DECLARE
  amount integer;
BEGIN
  IF (FgroupID IS NULL) THEN
    -- O usuário é inválido
    RAISE
      'Informe o usuário'
      USING ERRCODE = 'restrict_violation';
  END IF;
  IF (FrouteName IS NULL) THEN
    -- A rota é inválida
    RAISE
      'Informe a rota'
      USING ERRCODE = 'restrict_violation';
  ELSE
    IF (FrouteName = '') THEN
      -- A rota é inválida
      RAISE
        'Informe a rota'
        USING ERRCODE = 'restrict_violation';
    END IF;
  END IF;

  -- Monta a consulta
  SELECT count(*) into amount
    FROM erp.permissions AS P
   INNER JOIN erp.permissionspergroups AS PG USING (permissionid)
   WHERE P.name = FrouteName
     AND PG.httpMethod = FhttpMethod
     AND PG.groupid = FgroupID;

  IF (amount > 0) THEN
    RETURN true;
  END IF;

  RETURN false;
END
$$
LANGUAGE 'plpgsql';

-- Acrescentamos uma coluna para determinar o último posicionamento
-- obtido do veículo
ALTER TABLE stc.vehicles
 ADD COLUMN lastPositionID  integer
    DEFAULT 0;

-- Acrescentamos uma coluna para determinar o último posicionamento
-- obtido do veículo
ALTER TABLE stc.devices
 ADD COLUMN deviceModelID integer;

CREATE TABLE IF NOT EXISTS stc.deviceModels (
  contractorID      integer,      -- Número de identificação do contratante
                                  -- no sistema ERP
  deviceModelID     serial,       -- ID do modelo de rastreador
  manufactureID     char(2),      -- ID do fabricante do rastreador
  name              varchar(100), -- Nome do modelo do rastreador
  ableToKeyboard    boolean       -- A flag indicativa de que o modelo é
                    DEFAULT false,-- capaz de usar teclados
  PRIMARY KEY (contractorID, deviceModelID),
  FOREIGN KEY (contractorID, manufactureID)
    REFERENCES stc.manufactures(contractorID, manufactureID)
    ON DELETE RESTRICT,
  FOREIGN KEY (contractorID)
    REFERENCES erp.entities(entityID)
    ON DELETE RESTRICT
);

ALTER TABLE stc.devicemodels
 ADD COLUMN ableToKeyboard boolean DEFAULT false;

