-- =====================================================================
-- FERIADOS
-- =====================================================================
-- Tabelas e funções para controle dos feriados.
-- ---------------------------------------------------------------------
-- Aqui estão as bases para definição de feriados. Temos a determinação
-- dos feriados móveis, como Páscoa e Carnaval, e os fixos. Os feriados
-- podem ser nacionais, estaduais e/ou municipais. Também está prevista
-- a possibilidade de modificar um feriado apenas em um determinado ano
-- em função de decreto municipal, estadual ou federal.
-- =====================================================================

-- ---------------------------------------------------------------------
-- Dia da Páscoa
-- ---------------------------------------------------------------------
-- Função para determinar a data da páscoa, com base no ano fornecido,
-- usando o método de Carter.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION public.easter(year integer)
RETURNS DATE AS $$
DECLARE
  B integer;
  D integer;
  E integer;
  Q integer;
  day integer;
  month integer;
BEGIN
  IF ((year < 1900) OR (year > 2099)) THEN
    -- Disparamos uma exceção
    RAISE EXCEPTION '% está fora da faixa', year
    USING HINT = 'Os anos devem estar entre 1900 e 2099';
  END IF;

  B := 255 - 11 * ($1 % 19);
  D := ((B - 21) % 30) + 21;

  IF (D > 38) THEN
    D := D - 1;
  END IF;

  E := ($1 + $1/4 + D + 1) % 7;
  Q := D + 7 - E;

  IF (Q < 32) THEN
    day:=Q;
    month := 3;
  ELSE
    day := Q - 31;
    month := 4;
  END IF;
  
  RETURN to_date(to_char(day, '00')
            || to_char(month, '00') || to_char(year,'0000'), 'DD MM YYYY');
END;
$$ LANGUAGE plpgsql IMMUTABLE STRICT;

-- ---------------------------------------------------------------------
-- Feriados
-- ---------------------------------------------------------------------
-- Tabela que contém os feriados estaduais e municipais
-- ---------------------------------------------------------------------
CREATE TYPE GeographicScopeOfHoliday AS ENUM ('Nacional', 'Estadual', 'Municipal');

CREATE TABLE IF NOT EXISTS erp.holidays (
  holidayID       serial,           -- ID do feriado
  geographicScope GeographicScopeOfHoliday
                  NOT NULL,         -- O alcance do feriado
  state           char(2)           -- O estado (UF) no qual o feriado
                  DEFAULT NULL,     -- é válido (nulo se for nacional)
  cityID          integer           -- A ID da cidade na qual o feriado
                  DEFAULT NULL,     -- é válido (nulo se não for municipal)
  name            varchar(100)      -- O nome do feriado
                  NOT NULL,
  month           smallint          -- O número do mês em que ocorre o
                  NOT NULL,         -- feriado
  day             smallint          -- O dia do mês em que ocorre o
                  NOT NULL,         -- feriado
  PRIMARY KEY (holidayID),
  UNIQUE (state, cityID, day, month),
  FOREIGN KEY (state)
    REFERENCES erp.states(state)
    ON DELETE CASCADE,
  FOREIGN KEY (cityID)
    REFERENCES erp.cities(cityID)
    ON DELETE CASCADE
);

-- Insere a relação de feriados nacionais, estaduais e municipais
-- existentes (exceto os feriados móveis)

-- Feriados nacionais
INSERT INTO erp.holidays (geographicScope, name, month, day) VALUES
  ('Nacional', 'Confraternização Universal', 1, 1),
  ('Nacional', 'Tiradentes', 4, 21),
  ('Nacional', 'Dia do Trabalho', 5, 1),
  ('Nacional', 'Proclamação da Independência', 9, 7),
  ('Nacional', 'Nossa Senhora de Aparecida (Padroeira do Brasil)', 10, 12),
  ('Nacional', 'Finados', 11, 2),
  ('Nacional', 'Proclamação da República', 11, 15),
  ('Nacional', 'Natal', 12, 25);


-- Acre (AC)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'AC', 'Dia do católico', 1, 20),
  ('Estadual', 'AC', 'Dia do evangélico', 1, 25),
  ('Estadual', 'AC', 'Dia Internacional da Mulher', 3, 8),
  ('Estadual', 'AC', 'Aniversário do estado do Acre', 6, 15),
  ('Estadual', 'AC', 'Dia da Amazônia', 9, 5),
  ('Estadual', 'AC', 'Assinatura do Tratado de Petrópolis', 11, 17);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AC', 1, 'Aniversário da cidade', 4, 25),
  ('Municipal', 'AC', 2, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'AC', 3, 'Aniversário da cidade', 7, 3),
  ('Municipal', 'AC', 4, 'Aniversário da cidade', 4, 28),
  ('Municipal', 'AC', 5, 'Aniversário da cidade', 4, 28),
  ('Municipal', 'AC', 6, 'Aniversário da cidade', 9, 28),
  ('Municipal', 'AC', 7, 'Aniversário da cidade', 4, 28),
  ('Municipal', 'AC', 8, 'Aniversário da cidade', 12, 21),
  ('Municipal', 'AC', 9, 'Aniversário da cidade', 4, 28),
  ('Municipal', 'AC', 10, 'Aniversário da cidade', 5, 30);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AC', 11, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'AC', 12, 'Aniversário da cidade', 11, 5),
  ('Municipal', 'AC', 13, 'Aniversário da cidade', 3, 30),
  ('Municipal', 'AC', 14, 'Aniversário da cidade', 4, 28),
  ('Municipal', 'AC', 15, 'Aniversário da cidade', 4, 28),
  ('Municipal', 'AC', 16, 'Aniversário da cidade', 12, 28),
  ('Municipal', 'AC', 17, 'Aniversário da cidade', 7, 28),
  ('Municipal', 'AC', 18, 'Aniversário da cidade', 4, 28),
  ('Municipal', 'AC', 20, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'AC', 19, 'Aniversário da cidade', 9, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AC', 21, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'AC', 22, 'Aniversário da cidade', 3, 22);


-- Alagoas (AL)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'AL', 'São João', 6, 24),
  ('Estadual', 'AL', 'São Pedro', 6, 29),
  ('Estadual', 'AL', 'Emancipação Política de Alagoas', 9, 16),
  ('Estadual', 'AL', 'Morte de Zumbi dos Palmares', 11, 20),
  ('Estadual', 'AL', 'Dia do evangélico', 11, 30);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AL', 23, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'AL', 26, 'Aniversário da cidade', 2, 1),
  ('Municipal', 'AL', 31, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'AL', 33, 'Aniversário da cidade', 5, 18),
  ('Municipal', 'AL', 35, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'AL', 37, 'Aniversário da cidade', 6, 8),
  ('Municipal', 'AL', 42, 'Aniversário da cidade', 3, 11),
  ('Municipal', 'AL', 44, 'Aniversário da cidade', 6, 12),
  ('Municipal', 'AL', 46, 'Aniversário da cidade', 5, 16),
  ('Municipal', 'AL', 47, 'Aniversário da cidade', 4, 23);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AL', 48, 'Aniversário da cidade', 2, 14),
  ('Municipal', 'AL', 49, 'Aniversário da cidade', 7, 8),
  ('Municipal', 'AL', 51, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'AL', 53, 'Aniversário da cidade', 4, 28),
  ('Municipal', 'AL', 54, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'AL', 57, 'Aniversário da cidade', 1, 12),
  ('Municipal', 'AL', 60, 'Aniversário da cidade', 2, 4),
  ('Municipal', 'AL', 62, 'Aniversário da cidade', 6, 20),
  ('Municipal', 'AL', 63, 'Aniversário da cidade', 2, 3),
  ('Municipal', 'AL', 66, 'Aniversário da cidade', 7, 9);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AL', 68, 'Aniversário da cidade', 5, 31),
  ('Municipal', 'AL', 72, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'AL', 73, 'Aniversário da cidade', 1, 2),
  ('Municipal', 'AL', 71, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'AL', 76, 'Aniversário da cidade', 6, 5),
  ('Municipal', 'AL', 77, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'AL', 80, 'Aniversário da cidade', 6, 15),
  ('Municipal', 'AL', 81, 'Aniversário da cidade', 5, 16),
  ('Municipal', 'AL', 86, 'Aniversário da cidade', 2, 2),
  ('Municipal', 'AL', 87, 'Aniversário da cidade', 6, 21);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AL', 90, 'Aniversário da cidade', 3, 3),
  ('Municipal', 'AL', 91, 'Aniversário da cidade', 4, 7),
  ('Municipal', 'AL', 93, 'Aniversário da cidade', 7, 14),
  ('Municipal', 'AL', 95, 'Aniversário da cidade', 4, 12),
  ('Municipal', 'AL', 96, 'Aniversário da cidade', 5, 31),
  ('Municipal', 'AL', 97, 'Aniversário da cidade', 3, 16),
  ('Municipal', 'AL', 99, 'Aniversário da cidade', 6, 3),
  ('Municipal', 'AL', 100, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'AL', 101, 'Aniversário da cidade', 4, 12),
  ('Municipal', 'AL', 102, 'Aniversário da cidade', 6, 9);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AL', 104, 'Aniversário da cidade', 3, 16),
  ('Municipal', 'AL', 105, 'Aniversário da cidade', 7, 13),
  ('Municipal', 'AL', 108, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'AL', 109, 'Aniversário da cidade', 6, 14),
  ('Municipal', 'AL', 113, 'Aniversário da cidade', 5, 16),
  ('Municipal', 'AL', 114, 'Aniversário da cidade', 6, 18),
  ('Municipal', 'AL', 115, 'Aniversário da cidade', 6, 7),
  ('Municipal', 'AL', 118, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'AL', 122, 'Aniversário da cidade', 5, 16);


-- Amazonas (AM)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'AM', 'Elevação do Amazonas à categoria de província', 9, 5),
  ('Estadual', 'AM', 'Dia da Consciência Negra', 11, 20);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AM', 125, 'Aniversário da cidade', 12, 10),
  ('Municipal', 'AM', 126, 'Aniversário da cidade', 7, 2),
  ('Municipal', 'AM', 127, 'Aniversário da cidade', 12, 10),
  ('Municipal', 'AM', 128, 'Aniversário da cidade', 12, 29),
  ('Municipal', 'AM', 129, 'Aniversário da cidade', 12, 30),
  ('Municipal', 'AM', 130, 'Aniversário da cidade', 2, 23),
  ('Municipal', 'AM', 131, 'Aniversário da cidade', 3, 3),
  ('Municipal', 'AM', 132, 'Aniversário da cidade', 5, 6),
  ('Municipal', 'AM', 133, 'Aniversário da cidade', 6, 9),
  ('Municipal', 'AM', 134, 'Aniversário da cidade', 1, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AM', 135, 'Aniversário da cidade', 12, 10),
  ('Municipal', 'AM', 136, 'Aniversário da cidade', 1, 31),
  ('Municipal', 'AM', 137, 'Aniversário da cidade', 10, 22),
  ('Municipal', 'AM', 138, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'AM', 139, 'Aniversário da cidade', 12, 28),
  ('Municipal', 'AM', 140, 'Aniversário da cidade', 10, 10),
  ('Municipal', 'AM', 141, 'Aniversário da cidade', 9, 27),
  ('Municipal', 'AM', 142, 'Aniversário da cidade', 12, 19),
  ('Municipal', 'AM', 143, 'Aniversário da cidade', 12, 1),
  ('Municipal', 'AM', 144, 'Aniversário da cidade', 8, 2);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AM', 145, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'AM', 146, 'Aniversário da cidade', 9, 8),
  ('Municipal', 'AM', 147, 'Aniversário da cidade', 1, 31),
  ('Municipal', 'AM', 148, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'AM', 149, 'Aniversário da cidade', 12, 12),
  ('Municipal', 'AM', 150, 'Aniversário da cidade', 5, 15),
  ('Municipal', 'AM', 151, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'AM', 152, 'Aniversário da cidade', 12, 10),
  ('Municipal', 'AM', 153, 'Aniversário da cidade', 4, 25),
  ('Municipal', 'AM', 154, 'Aniversário da cidade', 12, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AM', 155, 'Aniversário da cidade', 7, 24),
  ('Municipal', 'AM', 156, 'Aniversário da cidade', 12, 19),
  ('Municipal', 'AM', 157, 'Aniversário da cidade', 12, 19),
  ('Municipal', 'AM', 158, 'Aniversário da cidade', 4, 11),
  ('Municipal', 'AM', 159, 'Aniversário da cidade', 3, 7),
  ('Municipal', 'AM', 160, 'Aniversário da cidade', 7, 16),
  ('Municipal', 'AM', 161, 'Aniversário da cidade', 2, 25),
  ('Municipal', 'AM', 162, 'Aniversário da cidade', 10, 24),
  ('Municipal', 'AM', 162, 'Nossa Senhora da Conceição (Padroeira da cidade)', 12, 8),
  ('Municipal', 'AM', 163, 'Aniversário da cidade', 5, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AM', 164, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'AM', 165, 'Aniversário da cidade', 6, 25),
  ('Municipal', 'AM', 166, 'Aniversário da cidade', 1, 31),
  ('Municipal', 'AM', 167, 'Aniversário da cidade', 12, 19),
  ('Municipal', 'AM', 168, 'Aniversário da cidade', 12, 19),
  ('Municipal', 'AM', 169, 'Aniversário da cidade', 12, 19),
  ('Municipal', 'AM', 170, 'Aniversário da cidade', 10, 15),
  ('Municipal', 'AM', 171, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'AM', 172, 'Aniversário da cidade', 12, 10),
  ('Municipal', 'AM', 173, 'Aniversário da cidade', 3, 31);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AM', 174, 'Aniversário da cidade', 12, 29),
  ('Municipal', 'AM', 175, 'Aniversário da cidade', 3, 13),
  ('Municipal', 'AM', 176, 'Aniversário da cidade', 9, 3),
  ('Municipal', 'AM', 177, 'Aniversário da cidade', 5, 31),
  ('Municipal', 'AM', 178, 'Aniversário da cidade', 12, 10),
  ('Municipal', 'AM', 179, 'Aniversário da cidade', 1, 23),
  ('Municipal', 'AM', 180, 'Aniversário da cidade', 2, 1),
  ('Municipal', 'AM', 181, 'Aniversário da cidade', 12, 19),
  ('Municipal', 'AM', 182, 'Aniversário da cidade', 6, 15),
  ('Municipal', 'AM', 183, 'Aniversário da cidade', 12, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AM', 184, 'Aniversário da cidade', 12, 10),
  ('Municipal', 'AM', 185, 'Aniversário da cidade', 5, 12),
  ('Municipal', 'AM', 186, 'Aniversário da cidade', 1, 24);


-- Amapá (AP)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'AP', 'São José (Padroeiro do Estado)', 3, 19),
  ('Estadual', 'AP', 'São Tiago', 7, 25),
  ('Estadual', 'AP', 'Criação do Território Federal', 9, 13),
  ('Estadual', 'AP', 'Dia da Consciência Negra', 11, 20);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AP', 187, 'Aniversário da cidade', 10, 22),
  ('Municipal', 'AP', 188, 'Aniversário da cidade', 12, 22),
  ('Municipal', 'AP', 189, 'Aniversário da cidade', 5, 1),
  ('Municipal', 'AP', 190, 'Aniversário da cidade', 12, 17),
  ('Municipal', 'AP', 191, 'Aniversário da cidade', 5, 1),
  ('Municipal', 'AP', 192, 'Aniversário da cidade', 12, 17),
  ('Municipal', 'AP', 193, 'Aniversário da cidade', 2, 4),
  ('Municipal', 'AP', 193, 'Nossa Senhora da Conceição (Padroeira da cidade)', 12, 8),
  ('Municipal', 'AP', 194, 'Aniversário da cidade', 1, 23),
  ('Municipal', 'AP', 195, 'Aniversário da cidade', 5, 23),
  ('Municipal', 'AP', 197, 'Aniversário da cidade', 5, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'AP', 198, 'Aniversário da cidade', 5, 1),
  ('Municipal', 'AP', 199, 'Aniversário da cidade', 12, 17),
  ('Municipal', 'AP', 200, 'Aniversário da cidade', 5, 1),
  ('Municipal', 'AP', 201, 'Aniversário da cidade', 12, 17),
  ('Municipal', 'AP', 202, 'Aniversário da cidade', 9, 8);


-- Bahia (BA)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'BA', 'Independência da Bahia', 7, 2);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'BA', 203, 'Aniversário da cidade', 2, 22),
  ('Municipal', 'BA', 206, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 209, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'BA', 211, 'Aniversário da cidade', 3, 15),
  ('Municipal', 'BA', 214, 'Aniversário da cidade', 2, 25),
  ('Municipal', 'BA', 225, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 230, 'Aniversário da cidade', 2, 7),
  ('Municipal', 'BA', 234, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 245, 'Aniversário da cidade', 2, 22),
  ('Municipal', 'BA', 263, 'Aniversário da cidade', 3, 13),
  ('Municipal', 'BA', 266, 'Aniversário da cidade', 1, 8),
  ('Municipal', 'BA', 272, 'São Tomaz de Cantuária  (Padroeiro da cidade)', 1, 7),
  ('Municipal', 'BA', 272, 'São João', 6, 24),
  ('Municipal', 'BA', 272, 'Aniversário da Cidade', 9, 28),
  ('Municipal', 'BA', 272, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'BA', 284, 'Aniversário da cidade', 2, 26),
  ('Municipal', 'BA', 287, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 318, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'BA', 332, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 339, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'BA', 343, 'Aniversário da cidade', 3, 14),
  ('Municipal', 'BA', 344, 'Aniversário da cidade', 2, 25),
  ('Municipal', 'BA', 359, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'BA', 364, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 373, 'Aniversário da cidade', 1, 28),
  ('Municipal', 'BA', 380, 'Aniversário da cidade', 1, 27);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'BA', 385, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 403, 'Aniversário da cidade', 1, 17),
  ('Municipal', 'BA', 407, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 417, 'Aniversário da cidade', 1, 31),
  ('Municipal', 'BA', 421, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 425, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'BA', 433, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'BA', 449, 'Aniversário da cidade', 2, 25),
  ('Municipal', 'BA', 469, 'Aniversário da cidade', 3, 1),
  ('Municipal', 'BA', 481, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 485, 'Aniversário da cidade', 2, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'BA', 501, 'Aniversário da cidade', 1, 15),
  ('Municipal', 'BA', 502, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'BA', 503, 'Aniversário da cidade', 3, 4),
  ('Municipal', 'BA', 505, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 512, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 518, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 520, 'Aniversário da cidade', 3, 15),
  ('Municipal', 'BA', 530, 'Aniversário da cidade', 3, 3),
  ('Municipal', 'BA', 538, 'São João', 6, 24),
  ('Municipal', 'BA', 538, 'Nossa Senhora da Conceição', 12, 8),
  ('Municipal', 'BA', 551, 'Aniversário da cidade', 3, 13),
  ('Municipal', 'BA', 554, 'Aniversário da cidade', 2, 22);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'BA', 560, 'Aniversário da cidade', 2, 25),
  ('Municipal', 'BA', 577, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'BA', 580, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 582, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 586, 'Aniversário da cidade', 2, 25),
  ('Municipal', 'BA', 602, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 609, 'Aniversário da cidade', 2, 25),
  ('Municipal', 'BA', 611, 'Aniversário da cidade', 2, 25),
  ('Municipal', 'BA', 614, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'BA', 617, 'Aniversário da cidade', 2, 25);


-- Ceará (CE)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'CE', 'São José', 3, 19),
  ('Estadual', 'CE', 'Abolição da Escravidão no Ceará', 3, 25);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'CE', 628, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'CE', 629, 'Aniversário da cidade', 5, 6),
  ('Municipal', 'CE', 630, 'Aniversário da cidade', 1, 25),
  ('Municipal', 'CE', 631, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'CE', 636, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'CE', 637, 'Aniversário da cidade', 3, 14),
  ('Municipal', 'CE', 641, 'Aniversário da cidade', 1, 25),
  ('Municipal', 'CE', 643, 'Aniversário da cidade', 4, 15),
  ('Municipal', 'CE', 645, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'CE', 646, 'Aniversário da cidade', 4, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'CE', 648, 'Aniversário da cidade', 2, 23),
  ('Municipal', 'CE', 666, 'Aniversário da cidade', 3, 27),
  ('Municipal', 'CE', 667, 'Aniversário da cidade', 3, 13),
  ('Municipal', 'CE', 671, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'CE', 672, 'Aniversário da cidade', 1, 14),
  ('Municipal', 'CE', 673, 'Aniversário da cidade', 4, 28),
  ('Municipal', 'CE', 677, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'CE', 678, 'Aniversário da cidade', 4, 13),
  ('Municipal', 'CE', 678, 'Nossa Senhora da Assunção', 8, 15),
  ('Municipal', 'CE', 679, 'Aniversário da cidade', 3, 27),
  ('Municipal', 'CE', 682, 'Aniversário da cidade', 4, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'CE', 684, 'Aniversário da cidade', 2, 9),
  ('Municipal', 'CE', 685, 'Aniversário da cidade', 5, 23),
  ('Municipal', 'CE', 686, 'Aniversário da cidade', 3, 17),
  ('Municipal', 'CE', 690, 'Aniversário da cidade', 3, 6),
  ('Municipal', 'CE', 691, 'Aniversário da cidade', 5, 8),
  ('Municipal', 'CE', 693, 'Aniversário da cidade', 5, 5),
  ('Municipal', 'CE', 694, 'Aniversário da cidade', 1, 22),
  ('Municipal', 'CE', 696, 'Aniversário da cidade', 1, 25),
  ('Municipal', 'CE', 702, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'CE', 703, 'Aniversário da cidade', 5, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'CE', 705, 'Aniversário da cidade', 3, 27),
  ('Municipal', 'CE', 709, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'CE', 712, 'Aniversário da cidade', 3, 9),
  ('Municipal', 'CE', 715, 'Aniversário da cidade', 1, 3),
  ('Municipal', 'CE', 717, 'Aniversário da cidade', 3, 6),
  ('Municipal', 'CE', 723, 'Aniversário da cidade', 3, 6),
  ('Municipal', 'CE', 726, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'CE', 727, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'CE', 731, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'CE', 732, 'Aniversário da cidade', 5, 12);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'CE', 740, 'Aniversário da cidade', 3, 14),
  ('Municipal', 'CE', 741, 'Aniversário da cidade', 4, 26),
  ('Municipal', 'CE', 746, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'CE', 750, 'Aniversário da cidade', 5, 8),
  ('Municipal', 'CE', 753, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'CE', 755, 'Aniversário da cidade', 1, 25),
  ('Municipal', 'CE', 762, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'CE', 766, 'Aniversário da cidade', 5, 15),
  ('Municipal', 'CE', 769, 'Aniversário da cidade', 2, 1),
  ('Municipal', 'CE', 771, 'Aniversário da cidade', 5, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'CE', 791, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'CE', 792, 'Aniversário da cidade', 1, 13),
  ('Municipal', 'CE', 798, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'CE', 800, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'CE', 801, 'Aniversário da cidade', 2, 5);


-- Distrito Federal (DF)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'DF', 'Fundação de Brasília', 4, 21),
  ('Estadual', 'DF', 'Dia do evangélico', 9, 30);

-- Feriados municipais
-- Não temos


-- Espírito Santo (ES)

-- Feriados estaduais
-- Não temos

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'ES', 805, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'ES', 806, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'ES', 807, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'ES', 808, 'Aniversário da cidade', 1, 6),
  ('Municipal', 'ES', 809, 'Aniversário da cidade', 12, 24),
  ('Municipal', 'ES', 810, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'ES', 811, 'Aniversário da cidade', 8, 12),
  ('Municipal', 'ES', 812, 'Aniversário da cidade', 8, 16),
  ('Municipal', 'ES', 813, 'Aniversário da cidade', 4, 3),
  ('Municipal', 'ES', 814, 'Aniversário da cidade', 4, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'ES', 815, 'Aniversário da cidade', 4, 18),
  ('Municipal', 'ES', 816, 'Aniversário da cidade', 9, 12),
  ('Municipal', 'ES', 817, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'ES', 818, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'ES', 819, 'Aniversário da cidade', 12, 15),
  ('Municipal', 'ES', 820, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'ES', 821, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'ES', 821, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'ES', 822, 'Aniversário da cidade', 1, 2),
  ('Municipal', 'ES', 823, 'Aniversário da cidade', 8, 22),
  ('Municipal', 'ES', 824, 'Aniversário da cidade', 10, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'ES', 825, 'Aniversário da cidade', 5, 9),
  ('Municipal', 'ES', 826, 'Aniversário da cidade', 6, 5),
  ('Municipal', 'ES', 827, 'Aniversário da cidade', 6, 12),
  ('Municipal', 'ES', 828, 'Aniversário da cidade', 4, 7),
  ('Municipal', 'ES', 829, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'ES', 830, 'Aniversário da cidade', 7, 5),
  ('Municipal', 'ES', 831, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'ES', 832, 'Aniversário da cidade', 12, 25),
  ('Municipal', 'ES', 833, 'Aniversário da cidade', 9, 19),
  ('Municipal', 'ES', 833, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'ES', 834, 'Aniversário da cidade', 11, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'ES', 835, 'Aniversário da cidade', 9, 11),
  ('Municipal', 'ES', 836, 'Aniversário da cidade', 9, 15),
  ('Municipal', 'ES', 837, 'Aniversário da cidade', 11, 11),
  ('Municipal', 'ES', 838, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'ES', 839, 'Aniversário da cidade', 2, 17),
  ('Municipal', 'ES', 840, 'Aniversário da cidade', 3, 30),
  ('Municipal', 'ES', 841, 'Aniversário da cidade', 4, 18),
  ('Municipal', 'ES', 842, 'Aniversário da cidade', 11, 11),
  ('Municipal', 'ES', 843, 'Aniversário da cidade', 1, 31),
  ('Municipal', 'ES', 844, 'Aniversário da cidade', 11, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'ES', 845, 'Aniversário da cidade', 1, 29),
  ('Municipal', 'ES', 846, 'Aniversário da cidade', 5, 16),
  ('Municipal', 'ES', 847, 'Aniversário da cidade', 8, 22),
  ('Municipal', 'ES', 848, 'Aniversário da cidade', 1, 7),
  ('Municipal', 'ES', 849, 'Aniversário da cidade', 10, 16),
  ('Municipal', 'ES', 850, 'Aniversário da cidade', 10, 31),
  ('Municipal', 'ES', 851, 'Aniversário da cidade', 5, 15),
  ('Municipal', 'ES', 852, 'Aniversário da cidade', 7, 8),
  ('Municipal', 'ES', 853, 'Aniversário da cidade', 4, 16),
  ('Municipal', 'ES', 854, 'Aniversário da cidade', 12, 11);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'ES', 855, 'Aniversário da cidade', 3, 1),
  ('Municipal', 'ES', 856, 'Aniversário da cidade', 10, 22),
  ('Municipal', 'ES', 857, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'ES', 858, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'ES', 859, 'Aniversário da cidade', 12, 23),
  ('Municipal', 'ES', 860, 'Aniversário da cidade', 4, 22),
  ('Municipal', 'ES', 861, 'Aniversário da cidade', 12, 24),
  ('Municipal', 'ES', 862, 'Aniversário da cidade', 3, 30),
  ('Municipal', 'ES', 863, 'Aniversário da cidade', 4, 4),
  ('Municipal', 'ES', 864, 'Aniversário da cidade', 1, 31);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'ES', 865, 'Aniversário da cidade', 11, 23),
  ('Municipal', 'ES', 866, 'Aniversário da cidade', 5, 6),
  ('Municipal', 'ES', 867, 'Aniversário da cidade', 5, 6),
  ('Municipal', 'ES', 868, 'Aniversário da cidade', 10, 15),
  ('Municipal', 'ES', 869, 'Aniversário da cidade', 3, 30),
  ('Municipal', 'ES', 870, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'ES', 871, 'Aniversário da cidade', 11, 5),
  ('Municipal', 'ES', 872, 'Aniversário da cidade', 9, 21),
  ('Municipal', 'ES', 873, 'Aniversário da cidade', 6, 25),
  ('Municipal', 'ES', 874, 'Aniversário da cidade', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'ES', 875, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'ES', 876, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'ES', 877, 'Aniversário da cidade', 5, 6),
  ('Municipal', 'ES', 878, 'Aniversário da cidade', 7, 23),
  ('Municipal', 'ES', 879, 'Aniversário da cidade', 1, 16),
  ('Municipal', 'ES', 880, 'Aniversário da cidade', 3, 23),
  ('Municipal', 'ES', 881, 'Aniversário da cidade', 5, 23),
  ('Municipal', 'ES', 882, 'Nossa Senhora da Vitória', 9, 8);


-- Goiás (GO)

-- Feriados estaduais
-- Não temos

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'GO', 883, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'GO', 885, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'GO', 892, 'Aniversário da cidade', 1, 16),
  ('Municipal', 'GO', 901, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'GO', 901, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'GO', 907, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'GO', 908, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'GO', 922, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'GO', 923, 'Aniversário da cidade', 4, 26),
  ('Municipal', 'GO', 927, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'GO', 931, 'Aniversário da cidade', 4, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'GO', 933, 'Aniversário da cidade', 1, 31),
  ('Municipal', 'GO', 940, 'Aniversário da cidade', 1, 16),
  ('Municipal', 'GO', 945, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'GO', 952, 'Aniversário da cidade', 5, 28),
  ('Municipal', 'GO', 963, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'GO', 964, 'Aniversário da cidade', 4, 15),
  ('Municipal', 'GO', 970, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'GO', 975, 'Aniversário da cidade', 5, 6),
  ('Municipal', 'GO', 977, 'Nossa Senhora Auxiliadora', 5, 24),
  ('Municipal', 'GO', 977, 'Aniversário da cidade', 10, 24),
  ('Municipal', 'GO', 977, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'GO', 978, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'GO', 980, 'Aniversário da cidade', 1, 21),
  ('Municipal', 'GO', 983, 'Aniversário da cidade', 4, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'GO', 985, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'GO', 990, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'GO', 991, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'GO', 992, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'GO', 1003, 'Aniversário da cidade', 1, 6),
  ('Municipal', 'GO', 1011, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'GO', 1012, 'Aniversário da cidade', 1, 16),
  ('Municipal', 'GO', 1025, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'GO', 1031, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'GO', 1040, 'Aniversário da cidade', 3, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'GO', 1043, 'Aniversário da cidade', 2, 2),
  ('Municipal', 'GO', 1045, 'Aniversário da cidade', 1, 16),
  ('Municipal', 'GO', 1054, 'Aniversário da cidade', 5, 9),
  ('Municipal', 'GO', 1059, 'Aniversário da cidade', 1, 16),
  ('Municipal', 'GO', 1069, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'GO', 1072, 'Aniversário da cidade', 4, 15),
  ('Municipal', 'GO', 1075, 'Aniversário da cidade', 1, 16),
  ('Municipal', 'GO', 1076, 'Aniversário da cidade', 1, 22),
  ('Municipal', 'GO', 1079, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'GO', 1082, 'Aniversário da cidade', 1, 5);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'GO', 1087, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'GO', 1088, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'GO', 1093, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'GO', 1095, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'GO', 1103, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'GO', 1109, 'Aniversário da cidade', 1, 14),
  ('Municipal', 'GO', 1113, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'GO', 1116, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'GO', 1119, 'Aniversário da cidade', 4, 30),
  ('Municipal', 'GO', 1127, 'Aniversário da cidade', 4, 29);


-- Maranhão (MA)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'MA', 'Adesão do Maranhão à independência do Brasil', 7, 28),
  ('Estadual', 'MA', 'Dia da Consciência Negra', 11, 20);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MA', 1130, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'MA', 1133, 'Aniversário da cidade', 2, 11),
  ('Municipal', 'MA', 1134, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'MA', 1142, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MA', 1144, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'MA', 1145, 'Aniversário da cidade', 1, 17),
  ('Municipal', 'MA', 1150, 'Aniversário da cidade', 1, 2),
  ('Municipal', 'MA', 1152, 'Aniversário da cidade', 3, 22),
  ('Municipal', 'MA', 1153, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'MA', 1162, 'Aniversário da cidade', 3, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MA', 1164, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MA', 1171, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MA', 1177, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MA', 1185, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'MA', 1189, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'MA', 1191, 'Aniversário da cidade', 4, 8),
  ('Municipal', 'MA', 1195, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MA', 1202, 'Aniversário da cidade', 1, 18),
  ('Municipal', 'MA', 1207, 'Aniversário da cidade', 3, 11),
  ('Municipal', 'MA', 1213, 'Aniversário da cidade', 1, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MA', 1217, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'MA', 1227, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MA', 1234, 'Aniversário da cidade', 1, 15),
  ('Municipal', 'MA', 1239, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MA', 1241, 'Aniversário da cidade', 3, 11),
  ('Municipal', 'MA', 1242, 'Aniversário da cidade', 2, 15),
  ('Municipal', 'MA', 1246, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'MA', 1247, 'Aniversário da cidade', 3, 15),
  ('Municipal', 'MA', 1248, 'Aniversário da cidade', 2, 1),
  ('Municipal', 'MA', 1258, 'Aniversário da cidade', 1, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MA', 1259, 'Aniversário da cidade', 1, 17),
  ('Municipal', 'MA', 1265, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'MA', 1266, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'MA', 1269, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'MA', 1270, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MA', 1275, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'MA', 1276, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'MA', 1281, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MA', 1282, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'MA', 1287, 'Aniversário da cidade', 4, 6),
  ('Municipal', 'MA', 1291, 'Aniversário da cidade', 3, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MA', 1292, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'MA', 1300, 'Aniversário da cidade', 3, 30),
  ('Municipal', 'MA', 1301, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'MA', 1314, 'São Pedro', 6, 29),
  ('Municipal', 'MA', 1314, 'Aniversário da cidade', 9, 8),
  ('Municipal', 'MA', 1314, 'Nossa Senhora da Conceição', 12, 8),
  ('Municipal', 'MA', 1319, 'Aniversário da cidade', 2, 10),
  ('Municipal', 'MA', 1326, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MA', 1328, 'Aniversário da cidade', 1, 13),
  ('Municipal', 'MA', 1331, 'Aniversário da cidade', 4, 5),
  ('Municipal', 'MA', 1335, 'Aniversário da cidade', 1, 8),
  ('Municipal', 'MA', 1338, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'MA', 1340, 'Aniversário da cidade', 3, 29);


-- Minas Gerais (MG)

-- Feriados estaduais
-- Não temos

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MG', 1347, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1350, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1353, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1360, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'MG', 1362, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1368, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1388, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1400, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1404, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1407, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1411, 'Santa Imaculada Conceicao', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MG', 1417, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'MG', 1437, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1482, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1484, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1488, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1494, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1505, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1514, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1531, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1532, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1533, 'Aniversário da cidade', 1, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MG', 1537, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1557, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1563, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1568, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1606, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1613, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1621, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1634, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1659, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1668, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'MG', 1677, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1679, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MG', 1686, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1700, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1721, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1733, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1739, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'MG', 1742, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1748, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1764, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'MG', 1768, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1788, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1791, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1793, 'Aniversário da cidade', 1, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MG', 1797, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1813, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1818, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1820, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1846, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'MG', 1847, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1870, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1872, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1910, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1934, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 1936, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MG', 2052, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'MG', 2113, 'Dia da Consciência Negra', 11, 20);


-- Mato Grosso do Sul (MS)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'MS', 'Criação do estado', 10, 11);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MS', 2199, 'Aniversário da cidade', 2, 8),
  ('Municipal', 'MS', 2200, 'Aniversário da cidade', 4, 22),
  ('Municipal', 'MS', 2202, 'Aniversário da cidade', 5, 8),
  ('Municipal', 'MS', 2203, 'Aniversário da cidade', 11, 11),
  ('Municipal', 'MS', 2204, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MS', 2205, 'Aniversário da cidade', 3, 18),
  ('Municipal', 'MS', 2206, 'Aniversário da cidade', 9, 28),
  ('Municipal', 'MS', 2207, 'Aniversário da cidade', 8, 15),
  ('Municipal', 'MS', 2208, 'Aniversário da cidade', 4, 13),
  ('Municipal', 'MS', 2209, 'Aniversário da cidade', 6, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MS', 2210, 'Aniversário da cidade', 12, 11),
  ('Municipal', 'MS', 2211, 'Aniversário da cidade', 11, 12),
  ('Municipal', 'MS', 2212, 'Aniversário da cidade', 7, 20),
  ('Municipal', 'MS', 2213, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MS', 2214, 'Aniversário da cidade', 10, 2),
  ('Municipal', 'MS', 2215, 'Aniversário da cidade', 4, 25),
  ('Municipal', 'MS', 2216, 'Aniversário da cidade', 12, 20),
  ('Municipal', 'MS', 2217, 'Aniversário da cidade', 9, 30),
  ('Municipal', 'MS', 2218, 'Aniversário da cidade', 8, 26),
  ('Municipal', 'MS', 2219, 'Aniversário da cidade', 5, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MS', 2220, 'Aniversário da cidade', 8, 3),
  ('Municipal', 'MS', 2221, 'Aniversário da cidade', 10, 22),
  ('Municipal', 'MS', 2222, 'Aniversário da cidade', 12, 11),
  ('Municipal', 'MS', 2223, 'Aniversário da cidade', 5, 24),
  ('Municipal', 'MS', 2224, 'Aniversário da cidade', 9, 21),
  ('Municipal', 'MS', 2224, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'MS', 2225, 'Aniversário da cidade', 5, 12),
  ('Municipal', 'MS', 2226, 'Aniversário da cidade', 4, 11),
  ('Municipal', 'MS', 2227, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MS', 2228, 'Aniversário da cidade', 11, 13),
  ('Municipal', 'MS', 2229, 'Aniversário da cidade', 5, 12);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MS', 2230, 'Aniversário da cidade', 12, 20),
  ('Municipal', 'MS', 2231, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MS', 2232, 'Aniversário da cidade', 7, 9),
  ('Municipal', 'MS', 2233, 'Aniversário da cidade', 9, 29),
  ('Municipal', 'MS', 2234, 'Aniversário da cidade', 5, 2),
  ('Municipal', 'MS', 2235, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'MS', 2236, 'Aniversário da cidade', 4, 8),
  ('Municipal', 'MS', 2237, 'Aniversário da cidade', 5, 4),
  ('Municipal', 'MS', 2238, 'Aniversário da cidade', 12, 10),
  ('Municipal', 'MS', 2239, 'Aniversário da cidade', 5, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MS', 2240, 'Aniversário da cidade', 11, 11),
  ('Municipal', 'MS', 2241, 'Aniversário da cidade', 4, 30),
  ('Municipal', 'MS', 2242, 'Aniversário da cidade', 12, 12),
  ('Municipal', 'MS', 2243, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'MS', 2244, 'Aniversário da cidade', 11, 11),
  ('Municipal', 'MS', 2245, 'Aniversário da cidade', 12, 14),
  ('Municipal', 'MS', 2246, 'Aniversário da cidade', 9, 2),
  ('Municipal', 'MS', 2247, 'Aniversário da cidade', 4, 22),
  ('Municipal', 'MS', 2248, 'Aniversário da cidade', 6, 11),
  ('Municipal', 'MS', 2249, 'Aniversário da cidade', 7, 16);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MS', 2250, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MS', 2251, 'Aniversário da cidade', 11, 11),
  ('Municipal', 'MS', 2252, 'Aniversário da cidade', 4, 8),
  ('Municipal', 'MS', 2253, 'Aniversário da cidade', 10, 27),
  ('Municipal', 'MS', 2254, 'Aniversário da cidade', 4, 30),
  ('Municipal', 'MS', 2255, 'Aniversário da cidade', 4, 30),
  ('Municipal', 'MS', 2256, 'Aniversário da cidade', 7, 4),
  ('Municipal', 'MS', 2257, 'Aniversário da cidade', 11, 17),
  ('Municipal', 'MS', 2258, 'Aniversário da cidade', 11, 11),
  ('Municipal', 'MS', 2259, 'Aniversário da cidade', 7, 18);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MS', 2260, 'Aniversário da cidade', 6, 3),
  ('Municipal', 'MS', 2261, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'MS', 2262, 'Aniversário da cidade', 12, 26),
  ('Municipal', 'MS', 2263, 'Aniversário da cidade', 5, 9),
  ('Municipal', 'MS', 2264, 'Aniversário da cidade', 12, 16),
  ('Municipal', 'MS', 2265, 'Aniversário da cidade', 11, 23),
  ('Municipal', 'MS', 2266, 'Aniversário da cidade', 12, 18),
  ('Municipal', 'MS', 2267, 'Aniversário da cidade', 5, 12),
  ('Municipal', 'MS', 2268, 'Aniversário da cidade', 5, 12),
  ('Municipal', 'MS', 2269, 'Aniversário da cidade', 5, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MS', 2270, 'Aniversário da cidade', 12, 11),
  ('Municipal', 'MS', 2271, 'Aniversário da cidade', 6, 3),
  ('Municipal', 'MS', 2272, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MS', 2273, 'Aniversário da cidade', 5, 12),
  ('Municipal', 'MS', 2274, 'Aniversário da cidade', 5, 8),
  ('Municipal', 'MS', 2275, 'Aniversário da cidade', 6, 15),
  ('Municipal', 'MS', 2276, 'Aniversário da cidade', 6, 20);


-- Mato Grosso (MT)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'MT', 'Dia da Consciência Negra', 11, 20);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MT', 2279, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'MT', 2281, 'Aniversário da cidade', 4, 19),
  ('Municipal', 'MT', 2284, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2286, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'MT', 2287, 'Aniversário da cidade', 2, 24),
  ('Municipal', 'MT', 2288, 'Aniversário da cidade', 5, 23),
  ('Municipal', 'MT', 2289, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'MT', 2291, 'Aniversário da cidade', 3, 13),
  ('Municipal', 'MT', 2292, 'Aniversário da cidade', 4, 19),
  ('Municipal', 'MT', 2295, 'Aniversário da cidade', 6, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MT', 2297, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2298, 'Aniversário da cidade', 7, 4),
  ('Municipal', 'MT', 2299, 'Aniversário da cidade', 7, 4),
  ('Municipal', 'MT', 2302, 'Aniversário da cidade', 2, 15),
  ('Municipal', 'MT', 2306, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2307, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2308, 'Aniversário da cidade', 5, 7),
  ('Municipal', 'MT', 2310, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2313, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'MT', 2314, 'Aniversário da cidade', 4, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MT', 2316, 'Aniversário da cidade', 5, 6),
  ('Municipal', 'MT', 2318, 'Aniversário da cidade', 4, 14),
  ('Municipal', 'MT', 2320, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'MT', 2323, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'MT', 2324, 'Aniversário da cidade', 6, 2),
  ('Municipal', 'MT', 2327, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'MT', 2328, 'Aniversário da cidade', 4, 3),
  ('Municipal', 'MT', 2329, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2335, 'Aniversário da cidade', 5, 9),
  ('Municipal', 'MT', 2336, 'Aniversário da cidade', 7, 4);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MT', 2340, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'MT', 2341, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2343, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'MT', 2344, 'Aniversário da cidade', 5, 1),
  ('Municipal', 'MT', 2345, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'MT', 2346, 'Aniversário da cidade', 5, 17),
  ('Municipal', 'MT', 2349, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2357, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2360, 'Aniversário da cidade', 4, 14),
  ('Municipal', 'MT', 2361, 'Aniversário da cidade', 5, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MT', 2364, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2365, 'Aniversário da cidade', 6, 29),
  ('Municipal', 'MT', 2366, 'Aniversário da cidade', 6, 29),
  ('Municipal', 'MT', 2367, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2368, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2370, 'Aniversário da cidade', 1, 12),
  ('Municipal', 'MT', 2374, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2379, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2381, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'MT', 2382, 'Aniversário da cidade', 5, 3);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MT', 2384, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'MT', 2385, 'Aniversário da cidade', 1, 28),
  ('Municipal', 'MT', 2387, 'Aniversário da cidade', 6, 25),
  ('Municipal', 'MT', 2392, 'Aniversário da cidade', 3, 4),
  ('Municipal', 'MT', 2394, 'Aniversário da cidade', 1, 28),
  ('Municipal', 'MT', 2395, 'Aniversário da cidade', 6, 13),
  ('Municipal', 'MT', 2396, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2398, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'MT', 2400, 'Aniversário da cidade', 6, 15),
  ('Municipal', 'MT', 2405, 'Aniversário da cidade', 5, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'MT', 2407, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2409, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'MT', 2410, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'MT', 2413, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'MT', 2414, 'Aniversário da cidade', 5, 15),
  ('Municipal', 'MT', 2415, 'Aniversário da cidade', 6, 27),
  ('Municipal', 'MT', 2416, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'MT', 2417, 'Aniversário da cidade', 5, 13);


-- Pará (PA)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'PA', 'Adesão do Grão-Pará à independência do Brasil', 8, 15),
  ('Estadual', 'PA', 'Nossa Senhora da Conceição', 12, 8);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PA', 2421, 'Aniversário da cidade', 8, 2),
  ('Municipal', 'PA', 2423, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'PA', 2427, 'Aniversário da cidade', 1, 3),
  ('Municipal', 'PA', 2429, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'PA', 2432, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'PA', 2435, 'Aniversário da cidade', 8, 14),
  ('Municipal', 'PA', 2436, 'Aniversário da cidade', 1, 12),
  ('Municipal', 'PA', 2437, 'Aniversário da cidade', 5, 4),
  ('Municipal', 'PA', 2439, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2441, 'Aniversário da cidade', 7, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PA', 2443, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2447, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2451, 'Aniversário da cidade', 7, 31),
  ('Municipal', 'PA', 2453, 'Aniversário da cidade', 1, 28),
  ('Municipal', 'PA', 2454, 'Aniversário da cidade', 6, 6),
  ('Municipal', 'PA', 2456, 'Aniversário da cidade', 5, 30),
  ('Municipal', 'PA', 2457, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2459, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2460, 'Aniversário da cidade', 7, 4),
  ('Municipal', 'PA', 2463, 'Aniversário da cidade', 5, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PA', 2466, 'Aniversário da cidade', 4, 21),
  ('Municipal', 'PA', 2467, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2471, 'Aniversário da cidade', 5, 23),
  ('Municipal', 'PA', 2476, 'Aniversário da cidade', 7, 14),
  ('Municipal', 'PA', 2479, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'PA', 2481, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'PA', 2482, 'Aniversário da cidade', 3, 27),
  ('Municipal', 'PA', 2483, 'Aniversário da cidade', 4, 5),
  ('Municipal', 'PA', 2484, 'Aniversário da cidade', 5, 28),
  ('Municipal', 'PA', 2486, 'Aniversário da cidade', 4, 21);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PA', 2487, 'Aniversário da cidade', 5, 12),
  ('Municipal', 'PA', 2489, 'Aniversário da cidade', 7, 6),
  ('Municipal', 'PA', 2491, 'Aniversário da cidade', 3, 15),
  ('Municipal', 'PA', 2492, 'Aniversário da cidade', 5, 28),
  ('Municipal', 'PA', 2500, 'Aniversário da cidade', 6, 9),
  ('Municipal', 'PA', 2501, 'Aniversário da cidade', 5, 29),
  ('Municipal', 'PA', 2502, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2503, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2505, 'Aniversário da cidade', 1, 23),
  ('Municipal', 'PA', 2506, 'Aniversário da cidade', 5, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PA', 2507, 'Aniversário da cidade', 1, 29),
  ('Municipal', 'PA', 2511, 'Aniversário da cidade', 4, 30),
  ('Municipal', 'PA', 2512, 'Aniversário da cidade', 1, 24),
  ('Municipal', 'PA', 2514, 'Aniversário da cidade', 1, 7),
  ('Municipal', 'PA', 2515, 'Aniversário da cidade', 2, 11),
  ('Municipal', 'PA', 2516, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PA', 2517, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'PA', 2518, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'PA', 2519, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'PA', 2520, 'Aniversário da cidade', 2, 12);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PA', 2522, 'Aniversário da cidade', 3, 10),
  ('Municipal', 'PA', 2524, 'Aniversário da cidade', 4, 8),
  ('Municipal', 'PA', 2527, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2530, 'Aniversário da cidade', 6, 22),
  ('Municipal', 'PA', 2531, 'Aniversário da cidade', 3, 14),
  ('Municipal', 'PA', 2533, 'Aniversário da cidade', 8, 14),
  ('Municipal', 'PA', 2536, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'PA', 2538, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2540, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2541, 'Aniversário da cidade', 5, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PA', 2543, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'PA', 2544, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'PA', 2546, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'PA', 2547, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2548, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PA', 2553, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'PA', 2557, 'Aniversário da cidade', 1, 6),
  ('Municipal', 'PA', 2560, 'Aniversário da cidade', 5, 13);


-- Paraíba (PB)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'PB', 'São João', 6, 24),
  ('Estadual', 'PB', 'Fundação do estado da Paraíba', 8, 5);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PB', 2566, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2567, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2568, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'PB', 2569, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2575, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2579, 'Aniversário da cidade', 1, 2),
  ('Municipal', 'PB', 2581, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2583, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2590, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2604, 'Aniversário da cidade', 4, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PB', 2607, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2608, 'Aniversário da cidade', 1, 13),
  ('Municipal', 'PB', 2609, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'PB', 2612, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2614, 'Aniversário da cidade', 4, 27),
  ('Municipal', 'PB', 2622, 'Aniversário da cidade', 4, 4),
  ('Municipal', 'PB', 2623, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2624, 'Aniversário da cidade', 3, 7),
  ('Municipal', 'PB', 2631, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2640, 'Aniversário da cidade', 4, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PB', 2643, 'Aniversário da cidade', 1, 2),
  ('Municipal', 'PB', 2644, 'Aniversário da cidade', 4, 17),
  ('Municipal', 'PB', 2646, 'Aniversário da cidade', 1, 4),
  ('Municipal', 'PB', 2649, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'PB', 2652, 'Aniversário da cidade', 1, 30),
  ('Municipal', 'PB', 2654, 'Aniversário da cidade', 8, 5),
  ('Municipal', 'PB', 2654, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'PB', 2654, 'Nossa Senhora da Imaculada Conceição', 12, 8),
  ('Municipal', 'PB', 2656, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PB', 2657, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PB', 2662, 'Aniversário da cidade', 1, 4),
  ('Municipal', 'PB', 2665, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2676, 'Aniversário da cidade', 4, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PB', 2677, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2690, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'PB', 2691, 'Aniversário da cidade', 1, 15),
  ('Municipal', 'PB', 2692, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2696, 'Aniversário da cidade', 3, 30),
  ('Municipal', 'PB', 2699, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2701, 'Aniversário da cidade', 3, 9),
  ('Municipal', 'PB', 2708, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2711, 'Aniversário da cidade', 1, 7),
  ('Municipal', 'PB', 2713, 'Aniversário da cidade', 1, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PB', 2717, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2718, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2720, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2728, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'PB', 2730, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'PB', 2749, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'PB', 2751, 'Aniversário da cidade', 1, 10),
  ('Municipal', 'PB', 2760, 'Aniversário da cidade', 4, 27),
  ('Municipal', 'PB', 2761, 'Aniversário da cidade', 1, 21),
  ('Municipal', 'PB', 2768, 'Aniversário da cidade', 1, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PB', 2771, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'PB', 2773, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PB', 2780, 'Aniversário da cidade', 1, 11);


-- Pernambuco (PE)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'PE', 'São João', 6, 24);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PE', 2784, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'PE', 2789, 'Aniversário da cidade', 5, 24),
  ('Municipal', 'PE', 2801, 'Aniversário da cidade', 5, 7),
  ('Municipal', 'PE', 2803, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'PE', 2804, 'Aniversário da cidade', 5, 18),
  ('Municipal', 'PE', 2807, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'PE', 2808, 'Aniversário da cidade', 5, 20),
  ('Municipal', 'PE', 2809, 'Aniversário da cidade', 3, 1),
  ('Municipal', 'PE', 2810, 'Aniversário da cidade', 3, 1),
  ('Municipal', 'PE', 2818, 'Aniversário da cidade', 2, 22);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PE', 2819, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'PE', 2820, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'PE', 2828, 'Aniversário da cidade', 5, 18),
  ('Municipal', 'PE', 2847, 'Aniversário da cidade', 4, 30),
  ('Municipal', 'PE', 2850, 'Aniversário da cidade', 2, 4),
  ('Municipal', 'PE', 2852, 'Aniversário da cidade', 5, 5),
  ('Municipal', 'PE', 2854, 'Aniversário da cidade', 3, 15),
  ('Municipal', 'PE', 2860, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PE', 2861, 'Aniversário da cidade', 1, 6),
  ('Municipal', 'PE', 2863, 'Aniversário da cidade', 3, 30);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PE', 2864, 'Aniversário da cidade', 3, 2),
  ('Municipal', 'PE', 2866, 'Aniversário da cidade', 4, 28),
  ('Municipal', 'PE', 2867, 'Aniversário da cidade', 2, 4),
  ('Municipal', 'PE', 2869, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'PE', 2871, 'Aniversário da cidade', 5, 4),
  ('Municipal', 'PE', 2873, 'Aniversário da cidade', 3, 2),
  ('Municipal', 'PE', 2878, 'Aniversário da cidade', 3, 11),
  ('Municipal', 'PE', 2882, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'PE', 2885, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'PE', 2886, 'Aniversário da cidade', 4, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PE', 2891, 'Aniversário da cidade', 3, 11),
  ('Municipal', 'PE', 2892, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'PE', 2895, 'Aniversário da cidade', 3, 12),
  ('Municipal', 'PE', 2897, 'Aniversário da cidade', 3, 24),
  ('Municipal', 'PE', 2898, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'PE', 2901, 'Aniversário da cidade', 5, 18),
  ('Municipal', 'PE', 2902, 'Aniversário da cidade', 2, 2),
  ('Municipal', 'PE', 2905, 'Aniversário da cidade', 2, 4),
  ('Municipal', 'PE', 2908, 'Aniversário da cidade', 4, 20),
  ('Municipal', 'PE', 2914, 'Aniversário da cidade', 5, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PE', 2916, 'Aniversário da cidade', 3, 12),
  ('Municipal', 'PE', 2931, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PE', 2932, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'PE', 2933, 'Aniversário da cidade', 4, 30),
  ('Municipal', 'PE', 2937, 'Aniversário da cidade', 4, 11),
  ('Municipal', 'PE', 2939, 'Aniversário da cidade', 3, 9),
  ('Municipal', 'PE', 2940, 'Aniversário da cidade', 1, 10),
  ('Municipal', 'PE', 2942, 'Aniversário da cidade', 5, 6),
  ('Municipal', 'PE', 2944, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'PE', 2945, 'Aniversário da cidade', 1, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PE', 2950, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'PE', 2952, 'Aniversário da cidade', 5, 20),
  ('Municipal', 'PE', 2953, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PE', 2954, 'Aniversário da cidade', 3, 1),
  ('Municipal', 'PE', 2955, 'Aniversário da cidade', 4, 8),
  ('Municipal', 'PE', 2961, 'Aniversário da cidade', 4, 11),
  ('Municipal', 'PE', 2962, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'PE', 2963, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'PE', 2967, 'Aniversário da cidade', 5, 6);


-- Piauí (PI)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'PI', 'Dia do Piauí', 10, 19);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PI', 2969, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PI', 2972, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'PI', 2974, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PI', 2976, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 2980, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'PI', 2981, 'Aniversário da cidade', 2, 27),
  ('Municipal', 'PI', 2987, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 2992, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 2995, 'Aniversário da cidade', 1, 22),
  ('Municipal', 'PI', 2996, 'Aniversário da cidade', 1, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PI', 2997, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 2998, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'PI', 3002, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3004, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3013, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3014, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3018, 'Aniversário da cidade', 3, 10),
  ('Municipal', 'PI', 3019, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3022, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3037, 'Aniversário da cidade', 1, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PI', 3038, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3042, 'Aniversário da cidade', 4, 5),
  ('Municipal', 'PI', 3048, 'Aniversário da cidade', 1, 19),
  ('Municipal', 'PI', 3057, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3060, 'Aniversário da cidade', 1, 24),
  ('Municipal', 'PI', 3061, 'Aniversário da cidade', 4, 1),
  ('Municipal', 'PI', 3062, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3069, 'Aniversário da cidade', 2, 21),
  ('Municipal', 'PI', 3072, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'PI', 3076, 'Aniversário da cidade', 4, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PI', 3078, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3079, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'PI', 3081, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3084, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3088, 'Aniversário da cidade', 3, 10),
  ('Municipal', 'PI', 3090, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'PI', 3101, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3106, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3108, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3110, 'Aniversário da cidade', 1, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PI', 3113, 'Aniversário da cidade', 1, 17),
  ('Municipal', 'PI', 3115, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3118, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3123, 'Aniversário da cidade', 1, 29),
  ('Municipal', 'PI', 3125, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PI', 3135, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PI', 3149, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'PI', 3157, 'Aniversário da cidade', 3, 30),
  ('Municipal', 'PI', 3158, 'Aniversário da cidade', 4, 11),
  ('Municipal', 'PI', 3159, 'Aniversário da cidade', 1, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PI', 3161, 'Aniversário da cidade', 1, 24),
  ('Municipal', 'PI', 3172, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'PI', 3175, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PI', 3176, 'Aniversário da cidade', 1, 24);


-- Paraná (PR)

-- Feriados estaduais
-- Não temos

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3193, 'Feriado Municipal', 10, 17),
  ('Municipal', 'PR', 3193, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3194, 'Feriado Municipal', 12, 4),
  ('Municipal', 'PR', 3194, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3194, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3195, 'Feriado Municipal', 11, 18),
  ('Municipal', 'PR', 3195, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3196, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3196, 'Feriado Municipal', 10, 28),
  ('Municipal', 'PR', 3197, 'Feriado Municipal', 4, 27);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3201, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3201, 'Feriado Municipal', 12, 12),
  ('Municipal', 'PR', 3201, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3199, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3199, 'Feriado Municipal', 5, 5),
  ('Municipal', 'PR', 3200, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3200, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3200, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3202, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3202, 'Feriado Municipal', 6, 27);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3203, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3203, 'Feriado Municipal', 11, 12),
  ('Municipal', 'PR', 3204, 'Feriado Municipal', 10, 1),
  ('Municipal', 'PR', 3204, 'Feriado Municipal', 11, 28),
  ('Municipal', 'PR', 3204, 'Feriado Municipal', 9, 1),
  ('Municipal', 'PR', 3205, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3206, 'Feriado Municipal', 9, 16),
  ('Municipal', 'PR', 3206, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3208, 'Feriado Municipal', 11, 6),
  ('Municipal', 'PR', 3208, 'Feriado Municipal', 8, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3209, 'Feriado Municipal', 10, 24),
  ('Municipal', 'PR', 3209, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3210, 'Aniversário da cidade', 1, 28),
  ('Municipal', 'PR', 3210, 'Feriado Municipal', 2, 11),
  ('Municipal', 'PR', 3211, 'Feriado Municipal', 10, 10),
  ('Municipal', 'PR', 3212, 'São João', 6, 24),
  ('Municipal', 'PR', 3212, 'Feriado Municipal', 12, 18),
  ('Municipal', 'PR', 3212, 'Feriado Municipal', 3, 25),
  ('Municipal', 'PR', 3214, 'Feriado Municipal', 11, 29),
  ('Municipal', 'PR', 3214, 'Feriado Municipal', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3215, 'Feriado Municipal', 10, 30),
  ('Municipal', 'PR', 3215, 'Aniversário da cidade', 2, 11),
  ('Municipal', 'PR', 3218, 'Feriado Municipal', 7, 16),
  ('Municipal', 'PR', 3219, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3219, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3220, 'Feriado Municipal', 7, 28),
  ('Municipal', 'PR', 3220, 'Feriado Municipal', 8, 22),
  ('Municipal', 'PR', 3220, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3221, 'Aniversário da cidade', 1, 25),
  ('Municipal', 'PR', 3221, 'Feriado Municipal', 8, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3222, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3222, 'Feriado Municipal', 9, 1),
  ('Municipal', 'PR', 3222, 'Feriado Municipal', 10, 1),
  ('Municipal', 'PR', 3222, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3223, 'Feriado Municipal', 5, 22),
  ('Municipal', 'PR', 3223, 'Feriado Municipal', 11, 27),
  ('Municipal', 'PR', 3223, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3225, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3227, 'Feriado Municipal', 10, 16),
  ('Municipal', 'PR', 3227, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3228, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3228, 'Feriado Municipal', 12, 4),
  ('Municipal', 'PR', 3229, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3229, 'Feriado Municipal', 9, 8),
  ('Municipal', 'PR', 3232, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3233, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3233, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3233, 'Aniversário da cidade', 4, 12),
  ('Municipal', 'PR', 3236, 'Aniversário da cidade', 1, 8),
  ('Municipal', 'PR', 3237, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3238, 'Feriado Municipal', 5, 3),
  ('Municipal', 'PR', 3238, 'Feriado Municipal', 9, 19),
  ('Municipal', 'PR', 3239, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'PR', 3240, 'Feriado Municipal', 11, 19),
  ('Municipal', 'PR', 3240, 'Feriado Municipal', 12, 13),
  ('Municipal', 'PR', 3241, 'Feriado Municipal', 11, 28),
  ('Municipal', 'PR', 3241, 'Feriado Municipal', 6, 20),
  ('Municipal', 'PR', 3242, 'São João', 6, 24),
  ('Municipal', 'PR', 3242, 'Feriado Municipal', 7, 20),
  ('Municipal', 'PR', 3243, 'Feriado Municipal', 10, 4);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3243, 'Feriado Municipal', 11, 26),
  ('Municipal', 'PR', 3244, 'Feriado Municipal', 5, 31),
  ('Municipal', 'PR', 3244, 'Feriado Municipal', 9, 21),
  ('Municipal', 'PR', 3245, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3245, 'Feriado Municipal', 10, 11),
  ('Municipal', 'PR', 3246, 'Feriado Municipal', 10, 22),
  ('Municipal', 'PR', 3246, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3246, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3247, 'Feriado Municipal', 10, 1),
  ('Municipal', 'PR', 3247, 'Feriado Municipal', 9, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3247, 'Feriado Municipal', 11, 4),
  ('Municipal', 'PR', 3249, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3249, 'São João', 6, 24),
  ('Municipal', 'PR', 3250, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3250, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3250, 'Feriado Municipal', 1, 21),
  ('Municipal', 'PR', 3251, 'Feriado Municipal', 10, 29),
  ('Municipal', 'PR', 3252, 'Aniversário da cidade', 2, 23),
  ('Municipal', 'PR', 3252, 'Feriado Municipal', 2, 2),
  ('Municipal', 'PR', 3254, 'Feriado Municipal', 3, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3254, 'Feriado Municipal', 10, 10),
  ('Municipal', 'PR', 3255, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3255, 'Feriado Municipal', 11, 26),
  ('Municipal', 'PR', 3256, 'Feriado Municipal', 8, 27),
  ('Municipal', 'PR', 3256, 'Feriado Municipal', 8, 11),
  ('Municipal', 'PR', 3257, 'Feriado Municipal', 5, 12),
  ('Municipal', 'PR', 3257, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3257, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3258, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3258, 'Feriado Municipal', 5, 31);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3259, 'Feriado Municipal', 4, 28),
  ('Municipal', 'PR', 3260, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3260, 'Feriado Municipal', 4, 4),
  ('Municipal', 'PR', 3261, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3261, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'PR', 3262, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3263, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'PR', 3263, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3264, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3264, 'São Sebastião', 1, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3265, 'Feriado Municipal', 11, 27),
  ('Municipal', 'PR', 3266, 'Feriado Municipal', 9, 8),
  ('Municipal', 'PR', 3266, 'Feriado Municipal', 10, 27),
  ('Municipal', 'PR', 3267, 'Feriado Municipal', 10, 8),
  ('Municipal', 'PR', 3267, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3268, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3268, 'Feriado Municipal', 10, 4),
  ('Municipal', 'PR', 3269, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3269, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3270, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3270, 'Feriado Municipal', 7, 6),
  ('Municipal', 'PR', 3271, 'Feriado Municipal', 6, 28),
  ('Municipal', 'PR', 3271, 'Feriado Municipal', 9, 8),
  ('Municipal', 'PR', 3272, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'PR', 3272, 'Feriado Municipal', 10, 7),
  ('Municipal', 'PR', 3272, 'Feriado Municipal', 5, 26),
  ('Municipal', 'PR', 3273, 'Feriado Municipal', 5, 24),
  ('Municipal', 'PR', 3274, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'PR', 3275, 'Feriado Municipal', 12, 3),
  ('Municipal', 'PR', 3276, 'Feriado Municipal', 11, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3276, 'São João', 6, 24),
  ('Municipal', 'PR', 3277, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3277, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3277, 'Feriado Municipal', 10, 28),
  ('Municipal', 'PR', 3278, 'Aniversário da cidade', 2, 15),
  ('Municipal', 'PR', 3280, 'Feriado Municipal', 8, 16),
  ('Municipal', 'PR', 3280, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3281, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3281, 'Feriado Municipal', 5, 27),
  ('Municipal', 'PR', 3284, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3284, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3284, 'Feriado Municipal', 8, 26),
  ('Municipal', 'PR', 3285, 'Feriado Municipal', 10, 28),
  ('Municipal', 'PR', 3285, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3282, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3282, 'Feriado Municipal', 12, 26),
  ('Municipal', 'PR', 3287, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'PR', 3287, 'Nossa Senhora da Luz dos Pinhais (Padroeira da cidade)', 9, 8),
  ('Municipal', 'PR', 3288, 'Feriado Municipal', 10, 26),
  ('Municipal', 'PR', 3290, 'Feriado Municipal', 3, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3292, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3292, 'Feriado Municipal', 11, 28),
  ('Municipal', 'PR', 3293, 'Aniversário da cidade', 2, 1),
  ('Municipal', 'PR', 3293, 'Feriado Municipal', 2, 7),
  ('Municipal', 'PR', 3293, 'Feriado Municipal', 1, 17),
  ('Municipal', 'PR', 3294, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3294, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3294, 'Feriado Municipal', 8, 16),
  ('Municipal', 'PR', 3296, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3296, 'Feriado Municipal', 12, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3297, 'Feriado Municipal', 9, 8),
  ('Municipal', 'PR', 3297, 'Feriado Municipal', 11, 26),
  ('Municipal', 'PR', 3298, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3298, 'Feriado Municipal', 6, 20),
  ('Municipal', 'PR', 3302, 'Feriado Municipal', 12, 12),
  ('Municipal', 'PR', 3302, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3303, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'PR', 3304, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3306, 'Feriado Municipal', 4, 20),
  ('Municipal', 'PR', 3306, 'Feriado Municipal', 12, 4);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3306, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3308, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3307, 'Feriado Municipal', 12, 22),
  ('Municipal', 'PR', 3309, 'Feriado Municipal', 10, 7),
  ('Municipal', 'PR', 3310, 'São João', 6, 24),
  ('Municipal', 'PR', 3310, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3311, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3311, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3312, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3312, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3313, 'Feriado Municipal', 6, 10),
  ('Municipal', 'PR', 3313, 'São João', 6, 24),
  ('Municipal', 'PR', 3315, 'Aniversário da cidade', 3, 1),
  ('Municipal', 'PR', 3316, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3316, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3317, 'Feriado Municipal', 11, 19),
  ('Municipal', 'PR', 3318, 'Aniversário da cidade', 4, 5),
  ('Municipal', 'PR', 3318, 'Feriado Municipal', 10, 4),
  ('Municipal', 'PR', 3319, 'Feriado Municipal', 2, 2),
  ('Municipal', 'PR', 3321, 'Aniversário da cidade', 3, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3321, 'Feriado Municipal', 10, 28),
  ('Municipal', 'PR', 3322, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3322, 'Feriado Municipal', 2, 2),
  ('Municipal', 'PR', 3325, 'Aniversário da cidade', 3, 2),
  ('Municipal', 'PR', 3325, 'Feriado Municipal', 8, 17),
  ('Municipal', 'PR', 3326, 'Aniversário da cidade', 1, 25),
  ('Municipal', 'PR', 3326, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3327, 'Feriado Municipal', 12, 1),
  ('Municipal', 'PR', 3327, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3328, 'Feriado Municipal', 11, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3329, 'Nossa Senhora do Belém (Padroeira do município)', 2, 2),
  ('Municipal', 'PR', 3329, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'PR', 3329, 'Centenário de fundação do município', 12, 9),
  ('Municipal', 'PR', 3330, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3330, 'Aniversário da cidade', 3, 11),
  ('Municipal', 'PR', 3331, 'Feriado Municipal', 4, 29),
  ('Municipal', 'PR', 3332, 'Feriado Municipal', 11, 16),
  ('Municipal', 'PR', 3332, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3333, 'Feriado Municipal', 11, 9),
  ('Municipal', 'PR', 3333, 'Feriado Municipal', 6, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3334, 'Feriado Municipal', 6, 12),
  ('Municipal', 'PR', 3335, 'Feriado Municipal', 11, 8),
  ('Municipal', 'PR', 3335, 'Feriado Municipal', 5, 31),
  ('Municipal', 'PR', 3336, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3338, 'Feriado Municipal', 11, 10),
  ('Municipal', 'PR', 3339, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3340, 'Feriado Municipal', 5, 3),
  ('Municipal', 'PR', 3340, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3340, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3341, 'Feriado Municipal', 11, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3342, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3343, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3344, 'Feriado Municipal', 12, 7),
  ('Municipal', 'PR', 3344, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3345, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3345, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3346, 'São João', 6, 24),
  ('Municipal', 'PR', 3347, 'Feriado Municipal', 7, 15),
  ('Municipal', 'PR', 3347, 'Feriado Municipal', 9, 8),
  ('Municipal', 'PR', 3348, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3348, 'Feriado Municipal', 8, 23),
  ('Municipal', 'PR', 3349, 'Feriado Municipal', 11, 30),
  ('Municipal', 'PR', 3350, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3350, 'Feriado Municipal', 11, 10),
  ('Municipal', 'PR', 3350, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3351, 'Feriado Municipal', 12, 3),
  ('Municipal', 'PR', 3351, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3351, 'Feriado Municipal', 11, 30),
  ('Municipal', 'PR', 3352, 'Feriado Municipal', 11, 27),
  ('Municipal', 'PR', 3352, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3354, 'Feriado Municipal', 12, 10),
  ('Municipal', 'PR', 3354, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3355, 'Feriado Municipal', 8, 13),
  ('Municipal', 'PR', 3355, 'Feriado Municipal', 11, 19),
  ('Municipal', 'PR', 3355, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3356, 'Feriado Municipal', 6, 10),
  ('Municipal', 'PR', 3356, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3357, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3357, 'Feriado Municipal', 11, 19),
  ('Municipal', 'PR', 3358, 'Feriado Municipal', 8, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3358, 'Feriado Municipal', 5, 2),
  ('Municipal', 'PR', 3359, 'Feriado Municipal', 11, 18),
  ('Municipal', 'PR', 3360, 'Feriado Municipal', 9, 15),
  ('Municipal', 'PR', 3361, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'PR', 3361, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3362, 'Feriado Municipal', 11, 7),
  ('Municipal', 'PR', 3362, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3363, 'Feriado Municipal', 9, 15),
  ('Municipal', 'PR', 3363, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3364, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3365, 'Feriado Municipal', 11, 18),
  ('Municipal', 'PR', 3366, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3366, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3367, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3367, 'Feriado Municipal', 12, 13),
  ('Municipal', 'PR', 3368, 'Feriado Municipal', 4, 28),
  ('Municipal', 'PR', 3369, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3369, 'Feriado Municipal', 12, 11),
  ('Municipal', 'PR', 3370, 'Feriado Municipal', 10, 10),
  ('Municipal', 'PR', 3370, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3371, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3372, 'Feriado Municipal', 9, 21),
  ('Municipal', 'PR', 3373, 'Feriado Municipal', 10, 4),
  ('Municipal', 'PR', 3373, 'Feriado Municipal', 11, 9),
  ('Municipal', 'PR', 3374, 'Feriado Municipal', 12, 16),
  ('Municipal', 'PR', 3374, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3375, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3375, 'Feriado Municipal', 7, 13),
  ('Municipal', 'PR', 3375, 'Feriado Municipal', 10, 29),
  ('Municipal', 'PR', 3376, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3376, 'Feriado Municipal', 8, 7),
  ('Municipal', 'PR', 3376, 'Feriado Municipal', 10, 5),
  ('Municipal', 'PR', 3377, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3377, 'Feriado Municipal', 2, 9),
  ('Municipal', 'PR', 3378, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'PR', 3379, 'Feriado Municipal', 11, 30),
  ('Municipal', 'PR', 3379, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3380, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3380, 'Feriado Municipal', 8, 20),
  ('Municipal', 'PR', 3383, 'Feriado Municipal', 11, 27);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3384, 'Feriado Municipal', 7, 1),
  ('Municipal', 'PR', 3385, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'PR', 3385, 'Aniversário da cidade', 12, 10),
  ('Municipal', 'PR', 3386, 'Feriado Municipal', 9, 25),
  ('Municipal', 'PR', 3387, 'Feriado Municipal', 12, 26),
  ('Municipal', 'PR', 3387, 'Feriado Municipal', 5, 22),
  ('Municipal', 'PR', 3388, 'Feriado Municipal', 5, 11),
  ('Municipal', 'PR', 3388, 'Aniversário da cidade', 1, 27),
  ('Municipal', 'PR', 3389, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3389, 'Feriado Municipal', 9, 21),
  ('Municipal', 'PR', 3390, 'Feriado Municipal', 9, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3390, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3391, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3393, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3393, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3394, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3394, 'Feriado Municipal', 12, 21),
  ('Municipal', 'PR', 3395, 'Feriado Municipal', 11, 21),
  ('Municipal', 'PR', 3395, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3396, 'Aniversário da cidade', 1, 8),
  ('Municipal', 'PR', 3396, 'Feriado Municipal', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3396, 'Feriado Municipal', 9, 19),
  ('Municipal', 'PR', 3397, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3397, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3398, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3398, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3399, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3399, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3400, 'Feriado Municipal', 9, 15),
  ('Municipal', 'PR', 3400, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3401, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3401, 'Feriado Municipal', 1, 16),
  ('Municipal', 'PR', 3401, 'Feriado Municipal', 10, 19),
  ('Municipal', 'PR', 3402, 'Feriado Municipal', 11, 29),
  ('Municipal', 'PR', 3402, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3403, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3404, 'Feriado Municipal', 1, 24),
  ('Municipal', 'PR', 3404, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3405, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3405, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3405, 'Aniversário da cidade', 4, 17);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3406, 'Feriado Municipal', 5, 22),
  ('Municipal', 'PR', 3406, 'Feriado Municipal', 11, 25),
  ('Municipal', 'PR', 3408, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3408, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3409, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3409, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3410, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3410, 'Feriado Municipal', 6, 12),
  ('Municipal', 'PR', 3411, 'Aniversário da cidade', 1, 31),
  ('Municipal', 'PR', 3412, 'Feriado Municipal', 5, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3412, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3412, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3413, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3414, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3414, 'Feriado Municipal', 9, 13),
  ('Municipal', 'PR', 3415, 'São João', 6, 24),
  ('Municipal', 'PR', 3415, 'Feriado Municipal', 11, 13),
  ('Municipal', 'PR', 3416, 'Feriado Municipal', 11, 11),
  ('Municipal', 'PR', 3416, 'Feriado Municipal', 4, 30),
  ('Municipal', 'PR', 3417, 'Aniversário da cidade', 2, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3417, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3417, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3417, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3418, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3418, 'São João', 6, 24),
  ('Municipal', 'PR', 3419, 'Feriado Municipal', 9, 8),
  ('Municipal', 'PR', 3420, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3421, 'Feriado Municipal', 5, 31),
  ('Municipal', 'PR', 3423, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3423, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3424, 'Feriado Municipal', 8, 16),
  ('Municipal', 'PR', 3424, 'Feriado Municipal', 9, 25),
  ('Municipal', 'PR', 3425, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3425, 'São João', 6, 24),
  ('Municipal', 'PR', 3425, 'Feriado Municipal', 11, 29),
  ('Municipal', 'PR', 3428, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3428, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3429, 'São João', 6, 24),
  ('Municipal', 'PR', 3429, 'Feriado Municipal', 5, 16),
  ('Municipal', 'PR', 3430, 'Aniversário da cidade', 3, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3430, 'Feriado Municipal', 8, 21),
  ('Municipal', 'PR', 3431, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3431, 'Feriado Municipal', 11, 13),
  ('Municipal', 'PR', 3432, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3432, 'Feriado Municipal', 1, 22),
  ('Municipal', 'PR', 3432, 'Aniversário da cidade', 2, 1),
  ('Municipal', 'PR', 3434, 'Feriado Municipal', 8, 23),
  ('Municipal', 'PR', 3434, 'Feriado Municipal', 4, 29),
  ('Municipal', 'PR', 3434, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3435, 'São Pedro', 6, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3435, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3437, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3437, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3438, 'Feriado Municipal', 11, 19),
  ('Municipal', 'PR', 3438, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3438, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3439, 'Feriado Municipal', 6, 12),
  ('Municipal', 'PR', 3440, 'Feriado Municipal', 8, 4),
  ('Municipal', 'PR', 3440, 'Feriado Municipal', 11, 19),
  ('Municipal', 'PR', 3441, 'Aniversário da cidade', 4, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3441, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3442, 'Feriado Municipal', 4, 7),
  ('Municipal', 'PR', 3442, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3443, 'Feriado Municipal', 10, 23),
  ('Municipal', 'PR', 3443, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3443, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3444, 'Feriado Municipal', 1, 22),
  ('Municipal', 'PR', 3444, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3445, 'Feriado Municipal', 11, 27),
  ('Municipal', 'PR', 3445, 'Aniversário da cidade', 3, 12);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3446, 'Feriado Municipal', 2, 11),
  ('Municipal', 'PR', 3446, 'Feriado Municipal', 11, 26),
  ('Municipal', 'PR', 3447, 'Feriado Municipal', 7, 29),
  ('Municipal', 'PR', 3447, 'Feriado Municipal', 10, 7),
  ('Municipal', 'PR', 3448, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3449, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3450, 'Feriado Municipal', 6, 18),
  ('Municipal', 'PR', 3450, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3451, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3451, 'Feriado Municipal', 12, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3452, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3452, 'Feriado Municipal', 11, 4),
  ('Municipal', 'PR', 3453, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3454, 'São João', 6, 24),
  ('Municipal', 'PR', 3454, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3455, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3455, 'Feriado Municipal', 10, 4),
  ('Municipal', 'PR', 3455, 'Feriado Municipal', 6, 26),
  ('Municipal', 'PR', 3455, 'Feriado Municipal', 4, 29),
  ('Municipal', 'PR', 3456, 'Feriado Municipal', 9, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3456, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3458, 'Feriado Municipal', 11, 27),
  ('Municipal', 'PR', 3459, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'PR', 3461, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3461, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3462, 'Feriado Municipal', 12, 15),
  ('Municipal', 'PR', 3463, 'Feriado Municipal', 12, 27),
  ('Municipal', 'PR', 3463, 'Feriado Municipal', 4, 23),
  ('Municipal', 'PR', 3464, 'Aniversário da cidade', 1, 29),
  ('Municipal', 'PR', 3465, 'Aniversário da cidade', 1, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3465, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3465, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3466, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3467, 'Feriado Municipal', 10, 1),
  ('Municipal', 'PR', 3467, 'Feriado Municipal', 11, 12),
  ('Municipal', 'PR', 3468, 'Feriado Municipal', 2, 11),
  ('Municipal', 'PR', 3469, 'Feriado Municipal', 9, 15),
  ('Municipal', 'PR', 3469, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3471, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3472, 'Feriado Municipal', 11, 9);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3472, 'Feriado Municipal', 11, 1),
  ('Municipal', 'PR', 3475, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3475, 'Feriado Municipal', 9, 29),
  ('Municipal', 'PR', 3477, 'Feriado Municipal', 5, 11),
  ('Municipal', 'PR', 3478, 'Feriado Municipal', 5, 31),
  ('Municipal', 'PR', 3478, 'Feriado Municipal', 11, 29),
  ('Municipal', 'PR', 3480, 'Feriado Municipal', 11, 12),
  ('Municipal', 'PR', 3480, 'Feriado Municipal', 8, 12),
  ('Municipal', 'PR', 3480, 'São João', 6, 24),
  ('Municipal', 'PR', 3482, 'Feriado Municipal', 10, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3482, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3483, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3483, 'Feriado Municipal', 11, 9),
  ('Municipal', 'PR', 3484, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3484, 'Feriado Municipal', 9, 13),
  ('Municipal', 'PR', 3485, 'Feriado Municipal', 10, 18),
  ('Municipal', 'PR', 3486, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3486, 'Feriado Municipal', 12, 5),
  ('Municipal', 'PR', 3487, 'Feriado Municipal', 10, 28),
  ('Municipal', 'PR', 3487, 'Feriado Municipal', 12, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3488, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3488, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3488, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3490, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3490, 'Feriado Municipal', 11, 19),
  ('Municipal', 'PR', 3491, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'PR', 3492, 'Feriado Municipal', 11, 12),
  ('Municipal', 'PR', 3493, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3493, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3493, 'Feriado Municipal', 9, 21);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3494, 'Feriado Municipal', 11, 29),
  ('Municipal', 'PR', 3494, 'Feriado Municipal', 9, 19),
  ('Municipal', 'PR', 3495, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'PR', 3495, 'São João', 6, 24),
  ('Municipal', 'PR', 3497, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3497, 'Feriado Municipal', 7, 1),
  ('Municipal', 'PR', 3498, 'Feriado Municipal', 10, 10),
  ('Municipal', 'PR', 3498, 'Feriado Municipal', 9, 9),
  ('Municipal', 'PR', 3498, 'Feriado Municipal', 6, 9),
  ('Municipal', 'PR', 3499, 'Feriado Municipal', 7, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3499, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3500, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3500, 'Feriado Municipal', 4, 28),
  ('Municipal', 'PR', 3501, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3501, 'Feriado Municipal', 4, 17),
  ('Municipal', 'PR', 3501, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3501, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'PR', 3502, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3502, 'Feriado Municipal', 12, 26),
  ('Municipal', 'PR', 3503, 'Feriado Municipal', 8, 16);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3503, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3503, 'Feriado Municipal', 10, 11),
  ('Municipal', 'PR', 3504, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3505, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3505, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3506, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3506, 'Feriado Municipal', 12, 6),
  ('Municipal', 'PR', 3506, 'Feriado Municipal', 11, 5),
  ('Municipal', 'PR', 3508, 'Feriado Municipal', 10, 30),
  ('Municipal', 'PR', 3508, 'Feriado Municipal', 10, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3509, 'Feriado Municipal', 9, 1),
  ('Municipal', 'PR', 3509, 'Feriado Municipal', 11, 26),
  ('Municipal', 'PR', 3509, 'Feriado Municipal', 10, 1),
  ('Municipal', 'PR', 3510, 'Feriado Municipal', 10, 4),
  ('Municipal', 'PR', 3510, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3511, 'Feriado Municipal', 9, 25),
  ('Municipal', 'PR', 3511, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3512, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'PR', 3512, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3513, 'Feriado Municipal', 11, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3513, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3513, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3514, 'Feriado Municipal', 11, 22),
  ('Municipal', 'PR', 3514, 'Feriado Municipal', 7, 28),
  ('Municipal', 'PR', 3515, 'Feriado Municipal', 9, 14),
  ('Municipal', 'PR', 3515, 'Feriado Municipal', 12, 5),
  ('Municipal', 'PR', 3516, 'Feriado Municipal', 5, 31),
  ('Municipal', 'PR', 3516, 'Feriado Municipal', 12, 9),
  ('Municipal', 'PR', 3517, 'Feriado Municipal', 10, 31),
  ('Municipal', 'PR', 3517, 'Feriado Municipal', 5, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3517, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3518, 'Feriado Municipal', 1, 21),
  ('Municipal', 'PR', 3518, 'Feriado Municipal', 12, 3),
  ('Municipal', 'PR', 3518, 'Feriado Municipal', 1, 25),
  ('Municipal', 'PR', 3519, 'Feriado Municipal', 11, 5),
  ('Municipal', 'PR', 3520, 'Feriado Municipal', 11, 17),
  ('Municipal', 'PR', 3522, 'Feriado Municipal', 7, 11),
  ('Municipal', 'PR', 3522, 'Feriado Municipal', 9, 8),
  ('Municipal', 'PR', 3522, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3523, 'Feriado Municipal', 10, 11);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3523, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3527, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3527, 'Feriado Municipal', 10, 22),
  ('Municipal', 'PR', 3525, 'Feriado Municipal', 6, 12),
  ('Municipal', 'PR', 3525, 'Feriado Municipal', 10, 15),
  ('Municipal', 'PR', 3526, 'Feriado Municipal', 9, 1),
  ('Municipal', 'PR', 3526, 'Feriado Municipal', 5, 3),
  ('Municipal', 'PR', 3526, 'Feriado Municipal', 10, 1),
  ('Municipal', 'PR', 3528, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3528, 'Feriado Municipal', 8, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3529, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3530, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3530, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3531, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3531, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3532, 'Feriado Municipal', 7, 31),
  ('Municipal', 'PR', 3532, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3533, 'Feriado Municipal', 11, 4),
  ('Municipal', 'PR', 3534, 'Feriado Municipal', 10, 11),
  ('Municipal', 'PR', 3534, 'Feriado Municipal', 9, 30);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3535, 'São João', 6, 24),
  ('Municipal', 'PR', 3535, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3535, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3536, 'Feriado Municipal', 11, 26),
  ('Municipal', 'PR', 3536, 'São João', 6, 24),
  ('Municipal', 'PR', 3537, 'São João', 6, 24),
  ('Municipal', 'PR', 3538, 'São João', 6, 24),
  ('Municipal', 'PR', 3538, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3538, 'Aniversário da cidade', 2, 15),
  ('Municipal', 'PR', 3539, 'Feriado Municipal', 4, 23);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3539, 'Feriado Municipal', 11, 23),
  ('Municipal', 'PR', 3540, 'Feriado Municipal', 12, 9),
  ('Municipal', 'PR', 3540, 'Feriado Municipal', 4, 23),
  ('Municipal', 'PR', 3542, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3542, 'Feriado Municipal', 10, 21),
  ('Municipal', 'PR', 3543, 'Feriado Municipal', 4, 17),
  ('Municipal', 'PR', 3543, 'Feriado Municipal', 3, 19),
  ('Municipal', 'PR', 3544, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'PR', 3546, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3546, 'Feriado Municipal', 9, 21);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3547, 'Feriado Municipal', 11, 28),
  ('Municipal', 'PR', 3547, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3547, 'Feriado Municipal', 9, 29),
  ('Municipal', 'PR', 3548, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3549, 'São Pedro', 6, 29),
  ('Municipal', 'PR', 3549, 'Feriado Municipal', 10, 30),
  ('Municipal', 'PR', 3550, 'Feriado Municipal', 12, 30),
  ('Municipal', 'PR', 3551, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3551, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3552, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3552, 'Feriado Municipal', 7, 3),
  ('Municipal', 'PR', 3553, 'Feriado Municipal', 8, 16),
  ('Municipal', 'PR', 3553, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3553, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3553, 'Feriado Municipal', 9, 22),
  ('Municipal', 'PR', 3554, 'Feriado Municipal', 11, 27),
  ('Municipal', 'PR', 3554, 'Feriado Municipal', 10, 14),
  ('Municipal', 'PR', 3555, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'PR', 3556, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3556, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3556, 'Aniversário da cidade', 3, 1),
  ('Municipal', 'PR', 3558, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3558, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3559, 'Feriado Municipal', 6, 6),
  ('Municipal', 'PR', 3559, 'Feriado Municipal', 10, 1),
  ('Municipal', 'PR', 3559, 'Feriado Municipal', 9, 1),
  ('Municipal', 'PR', 3560, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3560, 'Feriado Municipal', 9, 23),
  ('Municipal', 'PR', 3561, 'Aniversário da cidade', 1, 21),
  ('Municipal', 'PR', 3561, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3561, 'Feriado Municipal', 7, 25),
  ('Municipal', 'PR', 3561, 'Feriado Municipal', 10, 28),
  ('Municipal', 'PR', 3562, 'Feriado Municipal', 12, 13),
  ('Municipal', 'PR', 3562, 'Feriado Municipal', 8, 16),
  ('Municipal', 'PR', 3563, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3563, 'Feriado Municipal', 11, 26),
  ('Municipal', 'PR', 3563, 'Feriado Municipal', 3, 15),
  ('Municipal', 'PR', 3563, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3564, 'Aniversário da cidade', 4, 11),
  ('Municipal', 'PR', 3565, 'Aniversário da cidade', 2, 2);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3565, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3566, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3566, 'Feriado Municipal', 7, 14),
  ('Municipal', 'PR', 3567, 'Feriado Municipal', 6, 27),
  ('Municipal', 'PR', 3567, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'PR', 3568, 'Feriado Municipal', 7, 13),
  ('Municipal', 'PR', 3568, 'Feriado Municipal', 10, 28),
  ('Municipal', 'PR', 3569, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3570, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3570, 'Feriado Municipal', 12, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3571, 'Aniversário da cidade', 3, 18),
  ('Municipal', 'PR', 3571, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3571, 'Feriado Municipal', 10, 27),
  ('Municipal', 'PR', 3572, 'Feriado Municipal', 11, 14),
  ('Municipal', 'PR', 3572, 'Feriado Municipal', 9, 15),
  ('Municipal', 'PR', 3573, 'Feriado Municipal', 12, 14),
  ('Municipal', 'PR', 3574, 'Feriado Municipal', 6, 2),
  ('Municipal', 'PR', 3574, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3575, 'Feriado Municipal', 5, 13),
  ('Municipal', 'PR', 3577, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3577, 'Feriado Municipal', 11, 27),
  ('Municipal', 'PR', 3578, 'Feriado Municipal', 2, 11),
  ('Municipal', 'PR', 3579, 'Feriado Municipal', 5, 12),
  ('Municipal', 'PR', 3580, 'Feriado Municipal', 11, 4),
  ('Municipal', 'PR', 3580, 'Feriado Municipal', 6, 13),
  ('Municipal', 'PR', 3581, 'Feriado Municipal', 6, 26),
  ('Municipal', 'PR', 3581, 'Feriado Municipal', 8, 15),
  ('Municipal', 'PR', 3581, 'Feriado Municipal', 10, 4),
  ('Municipal', 'PR', 3582, 'Feriado Municipal', 7, 1),
  ('Municipal', 'PR', 3582, 'Aniversário da cidade', 3, 27);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3583, 'Feriado Municipal', 12, 8),
  ('Municipal', 'PR', 3584, 'Feriado Municipal', 5, 5),
  ('Municipal', 'PR', 3587, 'Feriado Municipal', 10, 26),
  ('Municipal', 'PR', 3587, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3588, 'Feriado Municipal', 8, 26),
  ('Municipal', 'PR', 3588, 'Feriado Municipal', 5, 17),
  ('Municipal', 'PR', 3589, 'Feriado Municipal', 11, 29),
  ('Municipal', 'PR', 3589, 'Feriado Municipal', 8, 6),
  ('Municipal', 'PR', 3590, 'São Sebastião', 1, 20),
  ('Municipal', 'PR', 3590, 'Feriado Municipal', 11, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'PR', 3591, 'Feriado Municipal', 7, 26),
  ('Municipal', 'PR', 3591, 'Feriado Municipal', 7, 16);


-- Rio de Janeiro (RJ)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'RJ', 'Dia de São Jorge', 4, 23),
  ('Estadual', 'RJ', 'Dia da Consciência Negra', 11, 20);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3592, 'Aniversário da cidade', 1, 6),
  ('Municipal', 'RJ', 3592, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3593, 'São Sebastião', 1, 20),
  ('Municipal', 'RJ', 3593, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'RJ', 3594, 'Aniversário da cidade', 2, 6),
  ('Municipal', 'RJ', 3594, 'São Sebastião', 1, 20),
  ('Municipal', 'RJ', 3595, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'RJ', 3596, 'Aniversário da cidade', 11, 12),
  ('Municipal', 'RJ', 3597, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'RJ', 3597, 'Feriado Municipal', 10, 18);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3598, 'Feriado Municipal', 7, 26),
  ('Municipal', 'RJ', 3598, 'Aniversário da cidade', 3, 10),
  ('Municipal', 'RJ', 3599, 'Aniversário da cidade', 10, 3),
  ('Municipal', 'RJ', 3599, 'São Sebastião', 1, 20),
  ('Municipal', 'RJ', 3600, 'Aniversário da cidade', 4, 3),
  ('Municipal', 'RJ', 3600, 'Feriado Municipal', 2, 18),
  ('Municipal', 'RJ', 3600, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3600, 'Feriado Municipal', 6, 20),
  ('Municipal', 'RJ', 3601, 'Aniversário da cidade', 3, 5),
  ('Municipal', 'RJ', 3601, 'Feriado Municipal', 3, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3601, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3602, 'Aniversário da cidade', 8, 15),
  ('Municipal', 'RJ', 3602, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3603, 'Aniversário da cidade', 11, 13),
  ('Municipal', 'RJ', 3603, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RJ', 3604, 'Aniversário da cidade', 5, 15),
  ('Municipal', 'RJ', 3604, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3605, 'Aniversário da cidade', 11, 5),
  ('Municipal', 'RJ', 3605, 'São João', 6, 24),
  ('Municipal', 'RJ', 3605, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3606, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'RJ', 3606, 'Feriado Municipal', 1, 15),
  ('Municipal', 'RJ', 3606, 'Feriado Municipal', 8, 6),
  ('Municipal', 'RJ', 3606, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3607, 'Aniversário da cidade', 3, 9),
  ('Municipal', 'RJ', 3607, 'São Pedro', 6, 29),
  ('Municipal', 'RJ', 3607, 'Feriado Municipal', 5, 22),
  ('Municipal', 'RJ', 3607, 'Feriado Municipal', 10, 2),
  ('Municipal', 'RJ', 3608, 'Aniversário da cidade', 3, 13),
  ('Municipal', 'RJ', 3608, 'Feriado Municipal', 8, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3609, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'RJ', 3609, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3609, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RJ', 3609, 'Feriado Municipal', 7, 31),
  ('Municipal', 'RJ', 3610, 'Aniversário da cidade', 10, 13),
  ('Municipal', 'RJ', 3610, 'Feriado Municipal', 7, 16),
  ('Municipal', 'RJ', 3611, 'Aniversário da cidade', 9, 15),
  ('Municipal', 'RJ', 3611, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RJ', 3611, 'São João', 6, 24),
  ('Municipal', 'RJ', 3612, 'Aniversário da cidade', 6, 30);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3613, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3613, 'Aniversário da cidade', 3, 15),
  ('Municipal', 'RJ', 3614, 'Aniversário da cidade', 12, 31),
  ('Municipal', 'RJ', 3614, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RJ', 3615, 'Aniversário da cidade', 5, 8),
  ('Municipal', 'RJ', 3615, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3615, 'Feriado Municipal', 9, 8),
  ('Municipal', 'RJ', 3616, 'Aniversário da cidade', 12, 31),
  ('Municipal', 'RJ', 3616, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RJ', 3616, 'Feriado Municipal', 2, 18);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3617, 'Aniversário da cidade', 10, 4),
  ('Municipal', 'RJ', 3617, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RJ', 3618, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'RJ', 3618, 'Feriado Municipal', 11, 26),
  ('Municipal', 'RJ', 3618, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RJ', 3619, 'Aniversário da cidade', 6, 8),
  ('Municipal', 'RJ', 3619, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3620, 'São João', 6, 24),
  ('Municipal', 'RJ', 3620, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'RJ', 3621, 'Feriado Municipal', 12, 3);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3621, 'Aniversário da cidade', 7, 5),
  ('Municipal', 'RJ', 3622, 'Aniversário da cidade', 6, 12),
  ('Municipal', 'RJ', 3622, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3623, 'Aniversário da cidade', 10, 28),
  ('Municipal', 'RJ', 3623, 'Feriado Municipal', 2, 4),
  ('Municipal', 'RJ', 3624, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'RJ', 3624, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RJ', 3625, 'Aniversário da cidade', 6, 1),
  ('Municipal', 'RJ', 3625, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RJ', 3626, 'Aniversário da cidade', 6, 30);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3626, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3627, 'São João', 6, 24),
  ('Municipal', 'RJ', 3627, 'Feriado Municipal', 7, 29),
  ('Municipal', 'RJ', 3627, 'Aniversário da cidade', 9, 14),
  ('Municipal', 'RJ', 3628, 'São João', 6, 24),
  ('Municipal', 'RJ', 3628, 'Aniversário da cidade', 7, 29),
  ('Municipal', 'RJ', 3629, 'Aniversário da cidade', 12, 28),
  ('Municipal', 'RJ', 3629, 'São João', 6, 24),
  ('Municipal', 'RJ', 3630, 'Feriado Municipal', 10, 3),
  ('Municipal', 'RJ', 3630, 'Feriado Municipal', 9, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3630, 'Aniversário da cidade', 6, 9),
  ('Municipal', 'RJ', 3631, 'Aniversário da cidade', 11, 11),
  ('Municipal', 'RJ', 3631, 'Feriado Municipal', 9, 8),
  ('Municipal', 'RJ', 3632, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RJ', 3632, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'RJ', 3633, 'Aniversário da cidade', 7, 11),
  ('Municipal', 'RJ', 3633, 'Feriado Municipal', 9, 14),
  ('Municipal', 'RJ', 3634, 'Aniversário da cidade', 9, 25),
  ('Municipal', 'RJ', 3634, 'Feriado Municipal', 11, 27),
  ('Municipal', 'RJ', 3634, 'Feriado Municipal', 2, 18);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3635, 'Aniversário da cidade', 10, 25),
  ('Municipal', 'RJ', 3635, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RJ', 3635, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RJ', 3636, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'RJ', 3636, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RJ', 3637, 'Aniversário da cidade', 6, 20),
  ('Municipal', 'RJ', 3637, 'Feriado Municipal', 9, 8),
  ('Municipal', 'RJ', 3637, 'Feriado Municipal', 9, 6),
  ('Municipal', 'RJ', 3637, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3638, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3638, 'Feriado Municipal', 2, 18),
  ('Municipal', 'RJ', 3638, 'Aniversário da cidade', 8, 21),
  ('Municipal', 'RJ', 3639, 'Feriado Municipal', 2, 18),
  ('Municipal', 'RJ', 3639, 'São João', 6, 24),
  ('Municipal', 'RJ', 3639, 'Aniversário da cidade', 11, 22),
  ('Municipal', 'RJ', 3640, 'Aniversário da cidade', 5, 16),
  ('Municipal', 'RJ', 3640, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RJ', 3641, 'Feriado Municipal', 2, 18),
  ('Municipal', 'RJ', 3641, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RJ', 3641, 'Aniversário da cidade', 1, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3642, 'Aniversário da cidade', 8, 8),
  ('Municipal', 'RJ', 3643, 'São Pedro', 6, 29),
  ('Municipal', 'RJ', 3643, 'Aniversário da cidade', 1, 15),
  ('Municipal', 'RJ', 3644, 'Feriado Municipal', 2, 28),
  ('Municipal', 'RJ', 3644, 'Feriado Municipal', 9, 8),
  ('Municipal', 'RJ', 3645, 'Aniversário da cidade', 12, 15),
  ('Municipal', 'RJ', 3645, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3646, 'São Pedro', 6, 29),
  ('Municipal', 'RJ', 3646, 'Aniversário da cidade', 3, 16),
  ('Municipal', 'RJ', 3647, 'Aniversário da cidade', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3647, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3648, 'Feriado Municipal', 7, 26),
  ('Municipal', 'RJ', 3648, 'Aniversário da cidade', 10, 17),
  ('Municipal', 'RJ', 3649, 'Feriado Municipal', 8, 20),
  ('Municipal', 'RJ', 3649, 'Feriado Municipal', 8, 11),
  ('Municipal', 'RJ', 3649, 'São Sebastião', 1, 20),
  ('Municipal', 'RJ', 3649, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RJ', 3649, 'Aniversário da cidade', 8, 21),
  ('Municipal', 'RJ', 3650, 'Aniversário da cidade', 11, 5),
  ('Municipal', 'RJ', 3651, 'Aniversário da cidade', 11, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3651, 'Feriado Municipal', 10, 7),
  ('Municipal', 'RJ', 3652, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'RJ', 3652, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3653, 'Feriado Municipal', 2, 17),
  ('Municipal', 'RJ', 3653, 'Feriado Municipal', 6, 12),
  ('Municipal', 'RJ', 3653, 'Feriado Municipal', 7, 29),
  ('Municipal', 'RJ', 3653, 'São João', 6, 24),
  ('Municipal', 'RJ', 3653, 'Aniversário da cidade', 12, 5),
  ('Municipal', 'RJ', 3654, 'Aniversário da cidade', 9, 29),
  ('Municipal', 'RJ', 3655, 'Aniversário da cidade', 5, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3655, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3656, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'RJ', 3657, 'Aniversário da cidade', 3, 17),
  ('Municipal', 'RJ', 3657, 'Feriado Municipal', 10, 15),
  ('Municipal', 'RJ', 3658, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'RJ', 3658, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3659, 'São Sebastião (Padroeiro da cidade)', 1, 20),
  ('Municipal', 'RJ', 3660, 'Aniversário da cidade', 6, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3660, 'Feriado Municipal', 7, 22),
  ('Municipal', 'RJ', 3660, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RJ', 3660, 'São Pedro', 6, 29),
  ('Municipal', 'RJ', 3661, 'Feriado Municipal', 7, 30),
  ('Municipal', 'RJ', 3661, 'Feriado Municipal', 11, 16),
  ('Municipal', 'RJ', 3661, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3661, 'Aniversário da cidade', 6, 13),
  ('Municipal', 'RJ', 3662, 'Feriado Municipal', 8, 6),
  ('Municipal', 'RJ', 3662, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3662, 'Feriado Municipal', 4, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3662, 'Aniversário da cidade', 4, 19),
  ('Municipal', 'RJ', 3663, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3663, 'Feriado Municipal', 11, 30),
  ('Municipal', 'RJ', 3663, 'Feriado Municipal', 4, 2),
  ('Municipal', 'RJ', 3663, 'Aniversário da cidade', 1, 18),
  ('Municipal', 'RJ', 3664, 'Feriado Municipal', 1, 10),
  ('Municipal', 'RJ', 3664, 'Feriado Municipal', 2, 18),
  ('Municipal', 'RJ', 3664, 'Aniversário da cidade', 9, 22),
  ('Municipal', 'RJ', 3665, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3665, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3665, 'Feriado Municipal', 4, 16),
  ('Municipal', 'RJ', 3665, 'Aniversário da cidade', 6, 17),
  ('Municipal', 'RJ', 3666, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RJ', 3666, 'Feriado Municipal', 2, 18),
  ('Municipal', 'RJ', 3666, 'São João', 6, 24),
  ('Municipal', 'RJ', 3666, 'Aniversário da cidade', 8, 21),
  ('Municipal', 'RJ', 3667, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RJ', 3667, 'Aniversário da cidade', 12, 28),
  ('Municipal', 'RJ', 3668, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RJ', 3668, 'Feriado Municipal', 8, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3668, 'Aniversário da cidade', 12, 15),
  ('Municipal', 'RJ', 3669, 'São Pedro', 6, 29),
  ('Municipal', 'RJ', 3669, 'Aniversário da cidade', 5, 16),
  ('Municipal', 'RJ', 3670, 'Feriado Municipal', 3, 25),
  ('Municipal', 'RJ', 3670, 'São João', 6, 24),
  ('Municipal', 'RJ', 3670, 'Feriado Municipal', 4, 24),
  ('Municipal', 'RJ', 3670, 'São Sebastião', 1, 20),
  ('Municipal', 'RJ', 3670, 'Feriado Municipal', 12, 13),
  ('Municipal', 'RJ', 3670, 'Aniversário da cidade', 4, 17),
  ('Municipal', 'RJ', 3671, 'Aniversário da cidade', 9, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3671, 'Feriado Municipal', 12, 7),
  ('Municipal', 'RJ', 3671, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RJ', 3672, 'Aniversário da cidade', 5, 8),
  ('Municipal', 'RJ', 3672, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RJ', 3672, 'Feriado Municipal', 9, 8),
  ('Municipal', 'RJ', 3673, 'Feriado Municipal', 3, 13),
  ('Municipal', 'RJ', 3673, 'Feriado Municipal', 4, 2),
  ('Municipal', 'RJ', 3673, 'Feriado Municipal', 10, 1),
  ('Municipal', 'RJ', 3673, 'Aniversário da cidade', 10, 12),
  ('Municipal', 'RJ', 3674, 'Aniversário da cidade', 5, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3674, 'Feriado Municipal', 9, 8),
  ('Municipal', 'RJ', 3675, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'RJ', 3675, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3676, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RJ', 3676, 'Feriado Municipal', 12, 28),
  ('Municipal', 'RJ', 3676, 'Aniversário da cidade', 11, 15),
  ('Municipal', 'RJ', 3677, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RJ', 3677, 'Feriado Municipal', 7, 9),
  ('Municipal', 'RJ', 3677, 'Feriado Municipal', 10, 15),
  ('Municipal', 'RJ', 3677, 'Aniversário da cidade', 7, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3678, 'Aniversário da cidade', 4, 25),
  ('Municipal', 'RJ', 3679, 'São Sebastião', 1, 20),
  ('Municipal', 'RJ', 3679, 'Aniversário da cidade', 12, 14),
  ('Municipal', 'RJ', 3680, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RJ', 3680, 'Aniversário da cidade', 9, 29),
  ('Municipal', 'RJ', 3681, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'RJ', 3681, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3681, 'Feriado Municipal', 8, 10),
  ('Municipal', 'RJ', 3681, 'São Sebastião', 1, 20),
  ('Municipal', 'RJ', 3682, 'Aniversário da cidade', 9, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RJ', 3682, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RJ', 3683, 'Aniversário da cidade', 7, 17);


-- Rio Grande do Norte (RN)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'RN', 'Mártires de Cunhaú e Uruaçu', 10, 3);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RN', 3684, 'Aniversário da cidade', 4, 11),
  ('Municipal', 'RN', 3690, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'RN', 3692, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'RN', 3693, 'Aniversário da cidade', 3, 23),
  ('Municipal', 'RN', 3697, 'Aniversário da cidade', 1, 17),
  ('Municipal', 'RN', 3702, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'RN', 3703, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'RN', 3705, 'Aniversário da cidade', 1, 19),
  ('Municipal', 'RN', 3707, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'RN', 3708, 'Aniversário da cidade', 4, 16);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RN', 3709, 'Aniversário da cidade', 3, 5),
  ('Municipal', 'RN', 3718, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'RN', 3719, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RN', 3720, 'Aniversário da cidade', 3, 17),
  ('Municipal', 'RN', 3721, 'Aniversário da cidade', 1, 4),
  ('Municipal', 'RN', 3722, 'Aniversário da cidade', 4, 4),
  ('Municipal', 'RN', 3728, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'RN', 3730, 'Aniversário da cidade', 4, 4),
  ('Municipal', 'RN', 3732, 'Aniversário da cidade', 5, 7),
  ('Municipal', 'RN', 3738, 'Aniversário da cidade', 3, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RN', 3740, 'Aniversário da cidade', 6, 12),
  ('Municipal', 'RN', 3742, 'Aniversário da cidade', 5, 18),
  ('Municipal', 'RN', 3743, 'Aniversário da cidade', 5, 8),
  ('Municipal', 'RN', 3750, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'RN', 3751, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'RN', 3752, 'Aniversário da cidade', 5, 12),
  ('Municipal', 'RN', 3753, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'RN', 3754, 'Aniversário da cidade', 1, 2),
  ('Municipal', 'RN', 3755, 'Aniversário da cidade', 5, 7),
  ('Municipal', 'RN', 3766, 'Aniversário da cidade', 5, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RN', 3770, 'Aniversário da cidade', 3, 15),
  ('Municipal', 'RN', 3772, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'RN', 3776, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'RN', 3777, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'RN', 3778, 'Aniversário da cidade', 5, 8),
  ('Municipal', 'RN', 3781, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'RN', 3785, 'Aniversário da cidade', 5, 7),
  ('Municipal', 'RN', 3786, 'Aniversário da cidade', 1, 19),
  ('Municipal', 'RN', 3788, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'RN', 3795, 'Aniversário da cidade', 4, 5);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RN', 3798, 'Aniversário da cidade', 5, 9),
  ('Municipal', 'RN', 3799, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'RN', 3802, 'Aniversário da cidade', 5, 9),
  ('Municipal', 'RN', 3803, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'RN', 3807, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'RN', 3821, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'RN', 3826, 'Aniversário da cidade', 1, 21),
  ('Municipal', 'RN', 3828, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'RN', 3835, 'Aniversário da cidade', 3, 10),
  ('Municipal', 'RN', 3837, 'Aniversário da cidade', 2, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RN', 3840, 'Aniversário da cidade', 4, 3),
  ('Municipal', 'RN', 3841, 'Aniversário da cidade', 5, 10),
  ('Municipal', 'RN', 3842, 'Aniversário da cidade', 4, 11),
  ('Municipal', 'RN', 3848, 'Aniversário da cidade', 3, 26);


-- Rondônia (RO)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'RO', 'Criação do estado', 1, 4),
  ('Estadual', 'RO', 'Dia do evangélico', 6, 18);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RO', 3851, 'Aniversário da cidade', 10, 22),
  ('Municipal', 'RO', 3852, 'Aniversário da cidade', 6, 22),
  ('Municipal', 'RO', 3853, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3854, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'RO', 3855, 'Aniversário da cidade', 10, 11),
  ('Municipal', 'RO', 3856, 'Aniversário da cidade', 12, 27),
  ('Municipal', 'RO', 3857, 'Aniversário da cidade', 7, 5),
  ('Municipal', 'RO', 3858, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'RO', 3859, 'Aniversário da cidade', 11, 26),
  ('Municipal', 'RO', 3860, 'Aniversário da cidade', 2, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RO', 3861, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3862, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3863, 'Aniversário da cidade', 8, 5),
  ('Municipal', 'RO', 3864, 'Aniversário da cidade', 12, 27),
  ('Municipal', 'RO', 3865, 'Aniversário da cidade', 5, 16),
  ('Municipal', 'RO', 3866, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3867, 'Aniversário da cidade', 6, 16),
  ('Municipal', 'RO', 3868, 'Aniversário da cidade', 6, 22),
  ('Municipal', 'RO', 3869, 'Aniversário da cidade', 6, 16),
  ('Municipal', 'RO', 3870, 'Aniversário da cidade', 2, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RO', 3872, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3873, 'Aniversário da cidade', 11, 7),
  ('Municipal', 'RO', 3874, 'Aniversário da cidade', 11, 22),
  ('Municipal', 'RO', 3875, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'RO', 3876, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3877, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3878, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3879, 'Aniversário da cidade', 6, 19),
  ('Municipal', 'RO', 3880, 'Aniversário da cidade', 7, 21),
  ('Municipal', 'RO', 3881, 'Aniversário da cidade', 6, 22);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RO', 3882, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3883, 'Aniversário da cidade', 6, 16),
  ('Municipal', 'RO', 3884, 'Aniversário da cidade', 7, 4),
  ('Municipal', 'RO', 3885, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'RO', 3886, 'Aniversário da cidade', 12, 27),
  ('Municipal', 'RO', 3887, 'Instalação do município de Porto Velho', 1, 24),
  ('Municipal', 'RO', 3887, 'Nossa Senhora Auxiliadora (Padroeira da cidade)', 5, 24),
  ('Municipal', 'RO', 3887, 'Aniversário da cidade', 10, 2),
  ('Municipal', 'RO', 3888, 'Aniversário da cidade', 5, 16),
  ('Municipal', 'RO', 3889, 'Aniversário da cidade', 11, 22),
  ('Municipal', 'RO', 3890, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3891, 'Aniversário da cidade', 8, 5);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RO', 3892, 'Aniversário da cidade', 5, 11),
  ('Municipal', 'RO', 3893, 'Aniversário da cidade', 6, 22),
  ('Municipal', 'RO', 3894, 'Aniversário da cidade', 12, 27),
  ('Municipal', 'RO', 3895, 'Aniversário da cidade', 7, 6),
  ('Municipal', 'RO', 3896, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3897, 'Aniversário da cidade', 6, 22),
  ('Municipal', 'RO', 3898, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3899, 'Aniversário da cidade', 2, 13),
  ('Municipal', 'RO', 3900, 'Aniversário da cidade', 6, 22),
  ('Municipal', 'RO', 3901, 'Aniversário da cidade', 4, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RO', 3902, 'Aniversário da cidade', 11, 23);


-- Roraima (RR)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'RR', 'Criação do estado', 10, 5),
  ('Estadual', 'RR', 'Dia da Consciência Negra', 11, 20);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RR', 3903, 'Aniversário da cidade', 7, 1),
  ('Municipal', 'RR', 3904, 'Aniversário da cidade', 10, 17),
  ('Municipal', 'RR', 3905, 'Aniversário da cidade', 7, 9),
  ('Municipal', 'RR', 3906, 'Aniversário da cidade', 7, 1),
  ('Municipal', 'RR', 3907, 'Aniversário da cidade', 10, 17),
  ('Municipal', 'RR', 3908, 'Aniversário da cidade', 5, 27),
  ('Municipal', 'RR', 3909, 'Aniversário da cidade', 11, 4),
  ('Municipal', 'RR', 3910, 'Aniversário da cidade', 11, 4),
  ('Municipal', 'RR', 3911, 'Aniversário da cidade', 7, 1),
  ('Municipal', 'RR', 3912, 'Aniversário da cidade', 7, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RR', 3913, 'Aniversário da cidade', 10, 17),
  ('Municipal', 'RR', 3914, 'Aniversário da cidade', 10, 17),
  ('Municipal', 'RR', 3915, 'Aniversário da cidade', 7, 1),
  ('Municipal', 'RR', 3916, 'Aniversário da cidade', 7, 1),
  ('Municipal', 'RR', 3917, 'Aniversário da cidade', 10, 17);


-- Rio Grande do Sul (RS)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'RS', 'Revolução Farroupilha', 9, 20);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3919, 'Feriado Municipal', 8, 16),
  ('Municipal', 'RS', 3919, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3920, 'Aniversário da cidade', 2, 16),
  ('Municipal', 'RS', 3920, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3920, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 3921, 'Feriado Municipal', 5, 29),
  ('Municipal', 'RS', 3921, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3922, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3922, 'Feriado Municipal', 10, 9),
  ('Municipal', 'RS', 3923, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3924, 'São Sebastião', 1, 20),
  ('Municipal', 'RS', 3924, 'Feriado Municipal', 12, 31),
  ('Municipal', 'RS', 3926, 'Feriado Municipal', 10, 4),
  ('Municipal', 'RS', 3926, 'Feriado Municipal', 4, 13),
  ('Municipal', 'RS', 3926, 'Feriado Municipal', 8, 16),
  ('Municipal', 'RS', 3928, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RS', 3929, 'Feriado Municipal', 9, 17),
  ('Municipal', 'RS', 3930, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 3930, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 3931, 'Aniversário da cidade', 3, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3931, 'Feriado Municipal', 9, 29),
  ('Municipal', 'RS', 3933, 'Feriado Municipal', 11, 4),
  ('Municipal', 'RS', 3933, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3934, 'Feriado Municipal', 5, 26),
  ('Municipal', 'RS', 3934, 'Aniversário da cidade', 2, 11),
  ('Municipal', 'RS', 3935, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RS', 3937, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3937, 'Feriado Municipal', 10, 4),
  ('Municipal', 'RS', 3938, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 3940, 'Feriado Municipal', 4, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3942, 'Feriado Municipal', 4, 12),
  ('Municipal', 'RS', 3942, 'Feriado Municipal', 12, 4),
  ('Municipal', 'RS', 3941, 'Feriado Municipal', 11, 6),
  ('Municipal', 'RS', 3941, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3943, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3943, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'RS', 3943, 'Feriado Municipal', 3, 24),
  ('Municipal', 'RS', 3944, 'Aniversário da cidade', 2, 16),
  ('Municipal', 'RS', 3944, 'São João', 6, 24),
  ('Municipal', 'RS', 3945, 'Feriado Municipal', 5, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3946, 'Feriado Municipal', 11, 24),
  ('Municipal', 'RS', 3946, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RS', 3947, 'Feriado Municipal', 5, 24),
  ('Municipal', 'RS', 3947, 'São Sebastião', 1, 20),
  ('Municipal', 'RS', 3948, 'Feriado Municipal', 10, 22),
  ('Municipal', 'RS', 3948, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 3949, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 3949, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 3950, 'Aniversário da cidade', 1, 23),
  ('Municipal', 'RS', 3951, 'Aniversário da cidade', 3, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3957, 'Feriado Municipal', 9, 15),
  ('Municipal', 'RS', 3957, 'Feriado Municipal', 5, 30),
  ('Municipal', 'RS', 3952, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RS', 3954, 'Aniversário da cidade', 2, 17),
  ('Municipal', 'RS', 3954, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 3955, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RS', 3956, 'Feriado Municipal', 3, 24),
  ('Municipal', 'RS', 3956, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 3958, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3958, 'Feriado Municipal', 11, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3958, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3960, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 3961, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RS', 3962, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 3962, 'Feriado Municipal', 12, 2),
  ('Municipal', 'RS', 3966, 'Feriado Municipal', 5, 15),
  ('Municipal', 'RS', 3966, 'Feriado Municipal', 7, 16),
  ('Municipal', 'RS', 3967, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 3968, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RS', 3969, 'Feriado Municipal', 10, 31);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3970, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3970, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3971, 'Feriado Municipal', 3, 4),
  ('Municipal', 'RS', 3973, 'Feriado Municipal', 11, 25),
  ('Municipal', 'RS', 3973, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3973, 'Feriado Municipal', 5, 8),
  ('Municipal', 'RS', 3973, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3974, 'Feriado Municipal', 4, 11),
  ('Municipal', 'RS', 3974, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3975, 'Feriado Municipal', 10, 9);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3975, 'Feriado Municipal', 12, 4),
  ('Municipal', 'RS', 3976, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RS', 3976, 'Feriado Municipal', 10, 25),
  ('Municipal', 'RS', 3977, 'São Pedro', 6, 29),
  ('Municipal', 'RS', 3977, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3978, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3979, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 3980, 'Feriado Municipal', 6, 1),
  ('Municipal', 'RS', 3980, 'Feriado Municipal', 2, 11),
  ('Municipal', 'RS', 3981, 'Feriado Municipal', 5, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3981, 'Feriado Municipal', 12, 13),
  ('Municipal', 'RS', 3983, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 3983, 'São João', 6, 24),
  ('Municipal', 'RS', 3985, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3986, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 3987, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3987, 'Feriado Municipal', 10, 9),
  ('Municipal', 'RS', 3988, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 3988, 'Feriado Municipal', 5, 31),
  ('Municipal', 'RS', 3989, 'Aniversário da cidade', 1, 31);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3990, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3990, 'São Sebastião', 1, 20),
  ('Municipal', 'RS', 3990, 'Feriado Municipal', 6, 3),
  ('Municipal', 'RS', 3992, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 3992, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3993, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3993, 'Feriado Municipal', 10, 9),
  ('Municipal', 'RS', 3993, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3994, 'Feriado Municipal', 3, 25),
  ('Municipal', 'RS', 3994, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 3995, 'Feriado Municipal', 5, 17),
  ('Municipal', 'RS', 3995, 'Feriado Municipal', 5, 26),
  ('Municipal', 'RS', 3996, 'Feriado Municipal', 6, 27),
  ('Municipal', 'RS', 3996, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 3996, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'RS', 3996, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 3996, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 3997, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4000, 'Feriado Municipal', 2, 11),
  ('Municipal', 'RS', 4000, 'Feriado Municipal', 4, 12);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4002, 'Feriado Municipal', 5, 3),
  ('Municipal', 'RS', 4003, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4003, 'Feriado Municipal', 7, 26),
  ('Municipal', 'RS', 4004, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RS', 4005, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4005, 'Feriado Municipal', 4, 23),
  ('Municipal', 'RS', 4007, 'Aniversário da cidade', 1, 24),
  ('Municipal', 'RS', 4008, 'Feriado Municipal', 9, 25),
  ('Municipal', 'RS', 4009, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RS', 4010, 'Feriado Municipal', 2, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4010, 'Feriado Municipal', 6, 21),
  ('Municipal', 'RS', 4012, 'Feriado Municipal', 10, 16),
  ('Municipal', 'RS', 4013, 'Feriado Municipal', 5, 26),
  ('Municipal', 'RS', 4014, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RS', 4014, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RS', 4015, 'Feriado Municipal', 10, 22),
  ('Municipal', 'RS', 4015, 'Feriado Municipal', 10, 7),
  ('Municipal', 'RS', 4016, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4016, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4016, 'Feriado Municipal', 5, 12);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4018, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4018, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 4020, 'Feriado Municipal', 6, 3),
  ('Municipal', 'RS', 4020, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4021, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4021, 'Feriado Municipal', 3, 28),
  ('Municipal', 'RS', 4021, 'Feriado Municipal', 12, 4),
  ('Municipal', 'RS', 4022, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'RS', 4023, 'Feriado Municipal', 12, 15),
  ('Municipal', 'RS', 4024, 'Feriado Municipal', 10, 22);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4026, 'São João', 6, 24),
  ('Municipal', 'RS', 4026, 'Feriado Municipal', 11, 21),
  ('Municipal', 'RS', 4026, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4027, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4027, 'Feriado Municipal', 5, 19),
  ('Municipal', 'RS', 4028, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4029, 'Feriado Municipal', 9, 13),
  ('Municipal', 'RS', 4029, 'São João', 6, 24),
  ('Municipal', 'RS', 4030, 'Feriado Municipal', 5, 14),
  ('Municipal', 'RS', 4030, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4031, 'Feriado Municipal', 8, 16),
  ('Municipal', 'RS', 4031, 'Feriado Municipal', 4, 14),
  ('Municipal', 'RS', 4031, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4035, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4035, 'Feriado Municipal', 4, 14),
  ('Municipal', 'RS', 4037, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 4039, 'Aniversário da cidade', 2, 28),
  ('Municipal', 'RS', 4039, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4040, 'Feriado Municipal', 4, 29),
  ('Municipal', 'RS', 4040, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4041, 'Feriado Municipal', 12, 28),
  ('Municipal', 'RS', 4041, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4041, 'Feriado Municipal', 10, 28),
  ('Municipal', 'RS', 4042, 'Feriado Municipal', 8, 18),
  ('Municipal', 'RS', 4044, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4045, 'Feriado Municipal', 5, 29),
  ('Municipal', 'RS', 4045, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4049, 'Feriado Municipal', 9, 10),
  ('Municipal', 'RS', 4049, 'Feriado Municipal', 9, 29),
  ('Municipal', 'RS', 4051, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4051, 'Feriado Municipal', 8, 16),
  ('Municipal', 'RS', 4052, 'Feriado Municipal', 8, 15),
  ('Municipal', 'RS', 4052, 'Feriado Municipal', 12, 9),
  ('Municipal', 'RS', 4053, 'Feriado Municipal', 10, 30),
  ('Municipal', 'RS', 4055, 'Feriado Municipal', 7, 17),
  ('Municipal', 'RS', 4055, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4056, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4056, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4056, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4058, 'Feriado Municipal', 6, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4058, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4059, 'São Pedro', 6, 29),
  ('Municipal', 'RS', 4060, 'Feriado Municipal', 12, 4),
  ('Municipal', 'RS', 4063, 'Feriado Municipal', 4, 13),
  ('Municipal', 'RS', 4062, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4062, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4064, 'Feriado Municipal', 4, 11),
  ('Municipal', 'RS', 4064, 'São Sebastião', 1, 20),
  ('Municipal', 'RS', 4066, 'Feriado Municipal', 4, 11),
  ('Municipal', 'RS', 4067, 'Feriado Municipal', 6, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4068, 'Feriado Municipal', 4, 12),
  ('Municipal', 'RS', 4068, 'Feriado Municipal', 10, 1),
  ('Municipal', 'RS', 4069, 'Feriado Municipal', 11, 27),
  ('Municipal', 'RS', 4069, 'Feriado Municipal', 8, 6),
  ('Municipal', 'RS', 4069, 'São João', 6, 24),
  ('Municipal', 'RS', 4071, 'Aniversário da cidade', 2, 28),
  ('Municipal', 'RS', 4071, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4073, 'Feriado Municipal', 9, 8),
  ('Municipal', 'RS', 4074, 'Aniversário da cidade', 2, 28),
  ('Municipal', 'RS', 4074, 'Feriado Municipal', 8, 22);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4075, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4075, 'Feriado Municipal', 5, 20),
  ('Municipal', 'RS', 4076, 'Feriado Municipal', 12, 28),
  ('Municipal', 'RS', 4076, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4077, 'Feriado Municipal', 4, 29),
  ('Municipal', 'RS', 4078, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4078, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4079, 'Feriado Municipal', 5, 26),
  ('Municipal', 'RS', 4080, 'Feriado Municipal', 11, 30),
  ('Municipal', 'RS', 4080, 'Feriado Municipal', 8, 16);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4083, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4084, 'Feriado Municipal', 5, 26),
  ('Municipal', 'RS', 4086, 'Feriado Municipal', 7, 9),
  ('Municipal', 'RS', 4087, 'São João', 6, 24),
  ('Municipal', 'RS', 4087, 'Feriado Municipal', 10, 9),
  ('Municipal', 'RS', 4089, 'Feriado Municipal', 5, 3),
  ('Municipal', 'RS', 4089, 'São Pedro', 6, 29),
  ('Municipal', 'RS', 4090, 'Aniversário da cidade', 2, 28),
  ('Municipal', 'RS', 4090, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4091, 'São Pedro', 6, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4093, 'Feriado Municipal', 12, 15),
  ('Municipal', 'RS', 4094, 'Feriado Municipal', 12, 6),
  ('Municipal', 'RS', 4094, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4094, 'Feriado Municipal', 1, 15),
  ('Municipal', 'RS', 4094, 'Feriado Municipal', 5, 4),
  ('Municipal', 'RS', 4096, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4097, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4097, 'Aniversário da cidade', 1, 28),
  ('Municipal', 'RS', 4102, 'Feriado Municipal', 8, 2),
  ('Municipal', 'RS', 4104, 'Feriado Municipal', 10, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4104, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4105, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4106, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4107, 'Feriado Municipal', 4, 13),
  ('Municipal', 'RS', 4108, 'Aniversário da cidade', 1, 18),
  ('Municipal', 'RS', 4108, 'São João', 6, 24),
  ('Municipal', 'RS', 4109, 'Feriado Municipal', 5, 24),
  ('Municipal', 'RS', 4110, 'Feriado Municipal', 2, 28),
  ('Municipal', 'RS', 4110, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4112, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4112, 'Aniversário da cidade', 3, 1),
  ('Municipal', 'RS', 4114, 'Feriado Municipal', 11, 22),
  ('Municipal', 'RS', 4115, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4115, 'Feriado Municipal', 5, 29),
  ('Municipal', 'RS', 4115, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4116, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4117, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4117, 'Aniversário da cidade', 2, 28),
  ('Municipal', 'RS', 4118, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4119, 'Feriado Municipal', 10, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4120, 'Feriado Municipal', 8, 16),
  ('Municipal', 'RS', 4121, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4121, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4122, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4122, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4123, 'Feriado Municipal', 8, 23),
  ('Municipal', 'RS', 4123, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4123, 'Feriado Municipal', 9, 5),
  ('Municipal', 'RS', 4125, 'Feriado Municipal', 5, 26),
  ('Municipal', 'RS', 4125, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4127, 'Feriado Municipal', 5, 24),
  ('Municipal', 'RS', 4127, 'Feriado Municipal', 7, 1),
  ('Municipal', 'RS', 4127, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4130, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4131, 'Feriado Municipal', 12, 6),
  ('Municipal', 'RS', 4131, 'Feriado Municipal', 3, 17),
  ('Municipal', 'RS', 4133, 'Feriado Municipal', 4, 10),
  ('Municipal', 'RS', 4133, 'Feriado Municipal', 8, 16),
  ('Municipal', 'RS', 4134, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4134, 'Feriado Municipal', 5, 9);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4135, 'Feriado Municipal', 5, 17),
  ('Municipal', 'RS', 4135, 'Feriado Municipal', 10, 19),
  ('Municipal', 'RS', 4138, 'Feriado Municipal', 6, 1),
  ('Municipal', 'RS', 4138, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4139, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4139, 'Feriado Municipal', 11, 23),
  ('Municipal', 'RS', 4140, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4140, 'Feriado Municipal', 8, 16),
  ('Municipal', 'RS', 4141, 'São Sebastião', 1, 20),
  ('Municipal', 'RS', 4143, 'Feriado Municipal', 5, 12);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4144, 'Feriado Municipal', 7, 14),
  ('Municipal', 'RS', 4147, 'Feriado Municipal', 5, 10),
  ('Municipal', 'RS', 4149, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'RS', 4149, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4151, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4151, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4152, 'São Sebastião', 1, 20),
  ('Municipal', 'RS', 4152, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4152, 'Feriado Municipal', 6, 1),
  ('Municipal', 'RS', 4156, 'Feriado Municipal', 5, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4156, 'Feriado Municipal', 10, 7),
  ('Municipal', 'RS', 4156, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4158, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4158, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4159, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4159, 'Feriado Municipal', 11, 10),
  ('Municipal', 'RS', 4161, 'Aniversário da cidade', 2, 28),
  ('Municipal', 'RS', 4162, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'RS', 4162, 'São João', 6, 24),
  ('Municipal', 'RS', 4164, 'Feriado Municipal', 10, 4);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4164, 'Feriado Municipal', 5, 22),
  ('Municipal', 'RS', 4166, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4170, 'Aniversário da cidade', 3, 15),
  ('Municipal', 'RS', 4170, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4170, 'São Sebastião', 1, 20),
  ('Municipal', 'RS', 4171, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4171, 'Feriado Municipal', 12, 4),
  ('Municipal', 'RS', 4172, 'Feriado Municipal', 8, 6),
  ('Municipal', 'RS', 4172, 'Feriado Municipal', 12, 15),
  ('Municipal', 'RS', 4175, 'Feriado Municipal', 10, 4);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4175, 'Feriado Municipal', 5, 26),
  ('Municipal', 'RS', 4175, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4176, 'São João', 6, 24),
  ('Municipal', 'RS', 4177, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4177, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4177, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4179, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 4181, 'Feriado Municipal', 8, 25),
  ('Municipal', 'RS', 4181, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4181, 'Feriado Municipal', 2, 2);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4182, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4185, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4187, 'Feriado Municipal', 5, 31),
  ('Municipal', 'RS', 4187, 'Feriado Municipal', 9, 8),
  ('Municipal', 'RS', 4188, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 4188, 'Feriado Municipal', 2, 11),
  ('Municipal', 'RS', 4189, 'Feriado Municipal', 4, 12),
  ('Municipal', 'RS', 4189, 'Feriado Municipal', 11, 25),
  ('Municipal', 'RS', 4189, 'Feriado Municipal', 10, 13),
  ('Municipal', 'RS', 4190, 'Feriado Municipal', 5, 23);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4190, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4192, 'São João', 6, 24),
  ('Municipal', 'RS', 4192, 'Feriado Municipal', 4, 11),
  ('Municipal', 'RS', 4194, 'Feriado Municipal', 4, 13),
  ('Municipal', 'RS', 4194, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4195, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4195, 'Feriado Municipal', 12, 5),
  ('Municipal', 'RS', 4196, 'Feriado Municipal', 5, 26),
  ('Municipal', 'RS', 4197, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4197, 'Feriado Municipal', 7, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4198, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4198, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4198, 'Aniversário da cidade', 2, 28),
  ('Municipal', 'RS', 4199, 'São João', 6, 24),
  ('Municipal', 'RS', 4201, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'RS', 4201, 'Feriado Municipal', 5, 26),
  ('Municipal', 'RS', 4202, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4205, 'Feriado Municipal', 4, 5),
  ('Municipal', 'RS', 4205, 'Feriado Municipal', 5, 17),
  ('Municipal', 'RS', 4209, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4210, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'RS', 4210, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4211, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 4211, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4212, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4212, 'Feriado Municipal', 5, 6),
  ('Municipal', 'RS', 4213, 'Feriado Municipal', 10, 1),
  ('Municipal', 'RS', 4213, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4213, 'Feriado Municipal', 5, 22),
  ('Municipal', 'RS', 4214, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4214, 'Feriado Municipal', 2, 28),
  ('Municipal', 'RS', 4215, 'Feriado Municipal', 5, 13),
  ('Municipal', 'RS', 4216, 'Feriado Municipal', 7, 9),
  ('Municipal', 'RS', 4216, 'Feriado Municipal', 2, 3),
  ('Municipal', 'RS', 4217, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4217, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 4217, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4219, 'Feriado Municipal', 3, 28),
  ('Municipal', 'RS', 4219, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4221, 'Feriado Municipal', 10, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4221, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4221, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4222, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4224, 'Feriado Municipal', 4, 13),
  ('Municipal', 'RS', 4224, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4226, 'Feriado Municipal', 4, 3),
  ('Municipal', 'RS', 4226, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4227, 'Feriado Municipal', 5, 15),
  ('Municipal', 'RS', 4228, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4232, 'Feriado Municipal', 3, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4232, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4232, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4233, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4234, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4235, 'Feriado Municipal', 11, 30),
  ('Municipal', 'RS', 4235, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4236, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4236, 'Feriado Municipal', 7, 6),
  ('Municipal', 'RS', 4236, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4237, 'Feriado Municipal', 8, 16);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4237, 'Feriado Municipal', 4, 13),
  ('Municipal', 'RS', 4241, 'Feriado Municipal', 10, 9),
  ('Municipal', 'RS', 4242, 'Nossa Senhora dos Navegantes (Padroeira da cidade)', 2, 2),
  ('Municipal', 'RS', 4243, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4243, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4243, 'Feriado Municipal', 8, 6),
  ('Municipal', 'RS', 4246, 'Feriado Municipal', 5, 15),
  ('Municipal', 'RS', 4246, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4246, 'Feriado Municipal', 12, 3),
  ('Municipal', 'RS', 4247, 'Feriado Municipal', 4, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4247, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4249, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4249, 'Feriado Municipal', 5, 24),
  ('Municipal', 'RS', 4252, 'São João', 6, 24),
  ('Municipal', 'RS', 4252, 'Feriado Municipal', 4, 8),
  ('Municipal', 'RS', 4255, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4255, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4256, 'Aniversário da cidade', 1, 21),
  ('Municipal', 'RS', 4256, 'Feriado Municipal', 4, 12),
  ('Municipal', 'RS', 4257, 'Feriado Municipal', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4257, 'Feriado Municipal', 2, 11),
  ('Municipal', 'RS', 4258, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4258, 'Feriado Municipal', 3, 25),
  ('Municipal', 'RS', 4260, 'Aniversário da cidade', 2, 19),
  ('Municipal', 'RS', 4260, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4260, 'São Pedro', 6, 29),
  ('Municipal', 'RS', 4261, 'Feriado Municipal', 10, 7),
  ('Municipal', 'RS', 4261, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4262, 'Feriado Municipal', 4, 10),
  ('Municipal', 'RS', 4263, 'Aniversário da cidade', 2, 18);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4263, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4264, 'Feriado Municipal', 4, 15),
  ('Municipal', 'RS', 4264, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4266, 'Aniversário da cidade', 2, 28),
  ('Municipal', 'RS', 4267, 'Feriado Municipal', 12, 26),
  ('Municipal', 'RS', 4267, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4268, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4268, 'Feriado Municipal', 11, 21),
  ('Municipal', 'RS', 4268, 'Feriado Municipal', 3, 28),
  ('Municipal', 'RS', 4269, 'Feriado Municipal', 10, 31);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4269, 'Feriado Municipal', 5, 15),
  ('Municipal', 'RS', 4270, 'Feriado Municipal', 4, 19),
  ('Municipal', 'RS', 4272, 'Feriado Municipal', 1, 6),
  ('Municipal', 'RS', 4272, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4273, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4273, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 4273, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4275, 'Feriado Municipal', 10, 9),
  ('Municipal', 'RS', 4275, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4276, 'Aniversário da cidade', 2, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4276, 'São João', 6, 24),
  ('Municipal', 'RS', 4278, 'Feriado Municipal', 12, 4),
  ('Municipal', 'RS', 4278, 'Aniversário da cidade', 1, 31),
  ('Municipal', 'RS', 4280, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4281, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4283, 'Feriado Municipal', 5, 17),
  ('Municipal', 'RS', 4283, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4284, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4284, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 4288, 'Feriado Municipal', 9, 17);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4288, 'Feriado Municipal', 7, 26),
  ('Municipal', 'RS', 4277, 'Feriado Municipal', 7, 30),
  ('Municipal', 'RS', 4285, 'Feriado Municipal', 8, 10),
  ('Municipal', 'RS', 4285, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4287, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4289, 'Aniversário da cidade', 1, 4),
  ('Municipal', 'RS', 4289, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4291, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4292, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4292, 'Feriado Municipal', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4295, 'São João', 6, 24),
  ('Municipal', 'RS', 4295, 'Feriado Municipal', 5, 30),
  ('Municipal', 'RS', 4296, 'Aniversário da cidade', 1, 28),
  ('Municipal', 'RS', 4296, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4298, 'Feriado Municipal', 10, 10),
  ('Municipal', 'RS', 4299, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4299, 'Feriado Municipal', 8, 8),
  ('Municipal', 'RS', 4300, 'Feriado Municipal', 10, 4),
  ('Municipal', 'RS', 4300, 'Aniversário da cidade', 1, 4),
  ('Municipal', 'RS', 4301, 'Feriado Municipal', 4, 2);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4301, 'Aniversário da cidade', 1, 7),
  ('Municipal', 'RS', 4302, 'Feriado Municipal', 4, 4),
  ('Municipal', 'RS', 4303, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4303, 'Feriado Municipal', 9, 30),
  ('Municipal', 'RS', 4304, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4305, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4306, 'Feriado Municipal', 4, 23),
  ('Municipal', 'RS', 4306, 'Feriado Municipal', 11, 30),
  ('Municipal', 'RS', 4308, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4309, 'Feriado Municipal', 12, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4311, 'Feriado Municipal', 10, 25),
  ('Municipal', 'RS', 4311, 'São Pedro', 6, 29),
  ('Municipal', 'RS', 4312, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4312, 'Feriado Municipal', 9, 10),
  ('Municipal', 'RS', 4314, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4314, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4314, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'RS', 4315, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4315, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4316, 'Feriado Municipal', 2, 2);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4316, 'Feriado Municipal', 8, 10),
  ('Municipal', 'RS', 4317, 'Feriado Municipal', 6, 3),
  ('Municipal', 'RS', 4318, 'Feriado Municipal', 4, 25),
  ('Municipal', 'RS', 4319, 'Feriado Municipal', 11, 11),
  ('Municipal', 'RS', 4319, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4319, 'Feriado Municipal', 3, 30),
  ('Municipal', 'RS', 4321, 'Feriado Municipal', 9, 29),
  ('Municipal', 'RS', 4321, 'Feriado Municipal', 4, 29),
  ('Municipal', 'RS', 4322, 'Feriado Municipal', 12, 6),
  ('Municipal', 'RS', 4322, 'Feriado Municipal', 5, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4323, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4323, 'São Pedro', 6, 29),
  ('Municipal', 'RS', 4327, 'São Pedro', 6, 29),
  ('Municipal', 'RS', 4327, 'Feriado Municipal', 3, 22),
  ('Municipal', 'RS', 4328, 'São Sebastião', 1, 20),
  ('Municipal', 'RS', 4329, 'Feriado Municipal', 4, 29),
  ('Municipal', 'RS', 4330, 'Aniversário da cidade', 2, 17),
  ('Municipal', 'RS', 4333, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4333, 'Feriado Municipal', 4, 29),
  ('Municipal', 'RS', 4334, 'Feriado Municipal', 4, 5);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4334, 'Feriado Municipal', 4, 29),
  ('Municipal', 'RS', 4335, 'Aniversário da cidade', 2, 28),
  ('Municipal', 'RS', 4336, 'Feriado Municipal', 8, 20),
  ('Municipal', 'RS', 4336, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4337, 'Feriado Municipal', 6, 27),
  ('Municipal', 'RS', 4337, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4338, 'Feriado Municipal', 6, 4),
  ('Municipal', 'RS', 4338, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4340, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4340, 'Feriado Municipal', 5, 5);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4341, 'Feriado Municipal', 5, 13),
  ('Municipal', 'RS', 4341, 'Feriado Municipal', 9, 22),
  ('Municipal', 'RS', 4341, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4344, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4346, 'Feriado Municipal', 11, 5),
  ('Municipal', 'RS', 4347, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4347, 'Feriado Municipal', 3, 24),
  ('Municipal', 'RS', 4350, 'Feriado Municipal', 12, 11),
  ('Municipal', 'RS', 4351, 'Feriado Municipal', 5, 17),
  ('Municipal', 'RS', 4351, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4352, 'Feriado Municipal', 12, 3),
  ('Municipal', 'RS', 4353, 'Feriado Municipal', 3, 29),
  ('Municipal', 'RS', 4355, 'Feriado Municipal', 8, 9),
  ('Municipal', 'RS', 4355, 'Feriado Municipal', 12, 31),
  ('Municipal', 'RS', 4356, 'Feriado Municipal', 2, 28),
  ('Municipal', 'RS', 4357, 'Feriado Municipal', 7, 16),
  ('Municipal', 'RS', 4358, 'Feriado Municipal', 4, 17),
  ('Municipal', 'RS', 4359, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4359, 'Feriado Municipal', 7, 4),
  ('Municipal', 'RS', 4361, 'Feriado Municipal', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4361, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 4362, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4362, 'Feriado Municipal', 8, 18),
  ('Municipal', 'RS', 4363, 'Feriado Municipal', 4, 13),
  ('Municipal', 'RS', 4363, 'São Pedro', 6, 29),
  ('Municipal', 'RS', 4364, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4368, 'Feriado Municipal', 8, 8),
  ('Municipal', 'RS', 4368, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4368, 'Feriado Municipal', 5, 21),
  ('Municipal', 'RS', 4369, 'São Pedro', 6, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4371, 'Feriado Municipal', 11, 30),
  ('Municipal', 'RS', 4372, 'Feriado Municipal', 4, 29),
  ('Municipal', 'RS', 4372, 'Feriado Municipal', 3, 19),
  ('Municipal', 'RS', 4373, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4374, 'Feriado Municipal', 5, 3),
  ('Municipal', 'RS', 4374, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4376, 'Feriado Municipal', 5, 12),
  ('Municipal', 'RS', 4376, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4376, 'Feriado Municipal', 8, 16),
  ('Municipal', 'RS', 4377, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4377, 'Feriado Municipal', 12, 28),
  ('Municipal', 'RS', 4378, 'Feriado Municipal', 12, 15),
  ('Municipal', 'RS', 4378, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4378, 'Feriado Municipal', 11, 1),
  ('Municipal', 'RS', 4379, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4379, 'Feriado Municipal', 8, 6),
  ('Municipal', 'RS', 4380, 'Feriado Municipal', 11, 21),
  ('Municipal', 'RS', 4380, 'Feriado Municipal', 9, 10),
  ('Municipal', 'RS', 4380, 'Feriado Municipal', 8, 16),
  ('Municipal', 'RS', 4383, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4383, 'Feriado Municipal', 12, 31),
  ('Municipal', 'RS', 4384, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4385, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4385, 'Feriado Municipal', 9, 10),
  ('Municipal', 'RS', 4390, 'Feriado Municipal', 7, 26),
  ('Municipal', 'RS', 4391, 'Feriado Municipal', 9, 8),
  ('Municipal', 'RS', 4391, 'Feriado Municipal', 10, 22),
  ('Municipal', 'RS', 4392, 'Aniversário da cidade', 3, 2),
  ('Municipal', 'RS', 4392, 'Feriado Municipal', 11, 10),
  ('Municipal', 'RS', 4392, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4395, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4396, 'São Sebastião', 1, 20),
  ('Municipal', 'RS', 4396, 'Feriado Municipal', 5, 11),
  ('Municipal', 'RS', 4397, 'Feriado Municipal', 7, 25),
  ('Municipal', 'RS', 4398, 'Aniversário da cidade', 1, 15),
  ('Municipal', 'RS', 4399, 'Feriado Municipal', 12, 28),
  ('Municipal', 'RS', 4400, 'Feriado Municipal', 5, 28),
  ('Municipal', 'RS', 4401, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4401, 'Feriado Municipal', 9, 14),
  ('Municipal', 'RS', 4402, 'Feriado Municipal', 5, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'RS', 4402, 'Feriado Municipal', 2, 2),
  ('Municipal', 'RS', 4403, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4404, 'Feriado Municipal', 6, 13),
  ('Municipal', 'RS', 4406, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4407, 'Feriado Municipal', 3, 20),
  ('Municipal', 'RS', 4410, 'Feriado Municipal', 5, 9),
  ('Municipal', 'RS', 4410, 'Feriado Municipal', 12, 8),
  ('Municipal', 'RS', 4412, 'Feriado Municipal', 3, 24),
  ('Municipal', 'RS', 4412, 'Feriado Municipal', 10, 31),
  ('Municipal', 'RS', 4413, 'São Pedro', 6, 29);


-- Santa Catarina (SC)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'SC', 'Data Magna do Estado de Santa Catarina', 8, 11);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4414, 'Feriado Municipal', 11, 21),
  ('Municipal', 'SC', 4414, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4414, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4415, 'Feriado Municipal', 7, 27),
  ('Municipal', 'SC', 4415, 'São Sebastião', 1, 20),
  ('Municipal', 'SC', 4416, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4416, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4417, 'Feriado Municipal', 5, 26),
  ('Municipal', 'SC', 4417, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4417, 'Feriado Municipal', 6, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4418, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SC', 4418, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4419, 'Feriado Municipal', 12, 14),
  ('Municipal', 'SC', 4419, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4421, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4421, 'Feriado Municipal', 12, 29),
  ('Municipal', 'SC', 4421, 'Feriado Municipal', 7, 1),
  ('Municipal', 'SC', 4421, 'Feriado Municipal', 6, 2),
  ('Municipal', 'SC', 4422, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4422, 'Feriado Municipal', 8, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4424, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'SC', 4424, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4424, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SC', 4425, 'Feriado Municipal', 12, 7),
  ('Municipal', 'SC', 4425, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4425, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SC', 4425, 'Feriado Municipal', 2, 11),
  ('Municipal', 'SC', 4425, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4426, 'Feriado Municipal', 12, 4),
  ('Municipal', 'SC', 4427, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4427, 'São Sebastião', 1, 20),
  ('Municipal', 'SC', 4427, 'Feriado Municipal', 12, 29),
  ('Municipal', 'SC', 4427, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4428, 'Feriado Municipal', 11, 6),
  ('Municipal', 'SC', 4429, 'Feriado Municipal', 6, 1),
  ('Municipal', 'SC', 4431, 'Feriado Municipal', 4, 5),
  ('Municipal', 'SC', 4431, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SC', 4432, 'Feriado Municipal', 4, 3),
  ('Municipal', 'SC', 4432, 'Feriado Municipal', 5, 4),
  ('Municipal', 'SC', 4433, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4433, 'Feriado Municipal', 12, 19),
  ('Municipal', 'SC', 4433, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SC', 4434, 'Feriado Municipal', 12, 15),
  ('Municipal', 'SC', 4434, 'Feriado Municipal', 7, 12),
  ('Municipal', 'SC', 4435, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SC', 4437, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SC', 4437, 'Feriado Municipal', 12, 27),
  ('Municipal', 'SC', 4437, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4438, 'Feriado Municipal', 6, 6),
  ('Municipal', 'SC', 4438, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4440, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SC', 4441, 'Feriado Municipal', 7, 20),
  ('Municipal', 'SC', 4446, 'Feriado Municipal', 12, 7),
  ('Municipal', 'SC', 4448, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SC', 4449, 'Feriado Municipal', 4, 25),
  ('Municipal', 'SC', 4449, 'Feriado Municipal', 6, 2),
  ('Municipal', 'SC', 4450, 'Feriado Municipal', 5, 17),
  ('Municipal', 'SC', 4451, 'Feriado Municipal', 9, 2),
  ('Municipal', 'SC', 4457, 'Aniversário da cidade', 3, 15),
  ('Municipal', 'SC', 4457, 'Feriado Municipal', 11, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4457, 'Feriado Municipal', 2, 2),
  ('Municipal', 'SC', 4453, 'Aniversário da cidade', 3, 5),
  ('Municipal', 'SC', 4453, 'São Sebastião', 1, 20),
  ('Municipal', 'SC', 4456, 'Aniversário da cidade', 1, 14),
  ('Municipal', 'SC', 4456, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4456, 'Feriado Municipal', 6, 27),
  ('Municipal', 'SC', 4458, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SC', 4459, 'Feriado Municipal', 10, 22),
  ('Municipal', 'SC', 4460, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4460, 'Feriado Municipal', 9, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4462, 'Feriado Municipal', 8, 4),
  ('Municipal', 'SC', 4463, 'Feriado Municipal', 3, 25),
  ('Municipal', 'SC', 4464, 'Feriado Municipal', 8, 8),
  ('Municipal', 'SC', 4464, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4464, 'Feriado Municipal', 6, 6),
  ('Municipal', 'SC', 4465, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SC', 4466, 'Feriado Municipal', 4, 5),
  ('Municipal', 'SC', 4467, 'Aniversário da cidade', 3, 18),
  ('Municipal', 'SC', 4468, 'Feriado Municipal', 12, 3),
  ('Municipal', 'SC', 4468, 'Feriado Municipal', 11, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4469, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4469, 'Feriado Municipal', 7, 27),
  ('Municipal', 'SC', 4470, 'Feriado Municipal', 3, 30),
  ('Municipal', 'SC', 4470, 'São João', 6, 24),
  ('Municipal', 'SC', 4472, 'Feriado Municipal', 9, 12),
  ('Municipal', 'SC', 4472, 'Feriado Municipal', 5, 3),
  ('Municipal', 'SC', 4474, 'Aniversário da cidade', 2, 17),
  ('Municipal', 'SC', 4475, 'Feriado Municipal', 3, 30),
  ('Municipal', 'SC', 4475, 'São João', 6, 24),
  ('Municipal', 'SC', 4476, 'Aniversário da cidade', 3, 16);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4477, 'Feriado Municipal', 9, 30),
  ('Municipal', 'SC', 4477, 'Feriado Municipal', 12, 14),
  ('Municipal', 'SC', 4478, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4478, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4478, 'Feriado Municipal', 1, 25),
  ('Municipal', 'SC', 4479, 'Feriado Municipal', 9, 26),
  ('Municipal', 'SC', 4481, 'Aniversário da cidade', 8, 25),
  ('Municipal', 'SC', 4482, 'Feriado Municipal', 9, 26),
  ('Municipal', 'SC', 4482, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SC', 4483, 'Feriado Municipal', 7, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4485, 'Feriado Municipal', 10, 6),
  ('Municipal', 'SC', 4487, 'Feriado Municipal', 5, 10),
  ('Municipal', 'SC', 4487, 'Feriado Municipal', 7, 12),
  ('Municipal', 'SC', 4489, 'Feriado Municipal', 12, 4),
  ('Municipal', 'SC', 4489, 'Aniversário da cidade', 1, 6),
  ('Municipal', 'SC', 4490, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4490, 'Feriado Municipal', 7, 20),
  ('Municipal', 'SC', 4490, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4492, 'Feriado Municipal', 6, 11),
  ('Municipal', 'SC', 4492, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4493, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SC', 4493, 'Feriado Municipal', 12, 16),
  ('Municipal', 'SC', 4494, 'Aniversário da cidade', 3, 14),
  ('Municipal', 'SC', 4495, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4499, 'Feriado Municipal', 6, 18),
  ('Municipal', 'SC', 4499, 'São Sebastião', 1, 20),
  ('Municipal', 'SC', 4500, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SC', 4502, 'Feriado Municipal', 3, 23),
  ('Municipal', 'SC', 4502, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SC', 4503, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SC', 4504, 'Feriado Municipal', 12, 4);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4504, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4505, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4505, 'Feriado Municipal', 12, 31),
  ('Municipal', 'SC', 4507, 'Feriado Municipal', 4, 7),
  ('Municipal', 'SC', 4507, 'Feriado Municipal', 9, 29),
  ('Municipal', 'SC', 4508, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SC', 4508, 'Feriado Municipal', 12, 19),
  ('Municipal', 'SC', 4508, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SC', 4509, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4510, 'Aniversário da cidade', 3, 18);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4511, 'Feriado Municipal', 11, 6),
  ('Municipal', 'SC', 4512, 'São João', 6, 24),
  ('Municipal', 'SC', 4512, 'Feriado Municipal', 7, 20),
  ('Municipal', 'SC', 4513, 'Feriado Municipal', 12, 29),
  ('Municipal', 'SC', 4514, 'Feriado Municipal', 6, 10),
  ('Municipal', 'SC', 4515, 'Feriado Municipal', 5, 26),
  ('Municipal', 'SC', 4515, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4515, 'Feriado Municipal', 10, 1),
  ('Municipal', 'SC', 4516, 'Feriado Municipal', 8, 28),
  ('Municipal', 'SC', 4517, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4521, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4522, 'Aniversário da cidade', 3, 11),
  ('Municipal', 'SC', 4522, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4523, 'Feriado Municipal', 8, 7),
  ('Municipal', 'SC', 4523, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4524, 'Feriado Municipal', 6, 21),
  ('Municipal', 'SC', 4525, 'São João', 6, 24),
  ('Municipal', 'SC', 4525, 'Feriado Municipal', 8, 27),
  ('Municipal', 'SC', 4526, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4527, 'Feriado Municipal', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4527, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4527, 'Feriado Municipal', 9, 10),
  ('Municipal', 'SC', 4528, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4528, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SC', 4530, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SC', 4530, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4530, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4531, 'Feriado Municipal', 6, 1),
  ('Municipal', 'SC', 4531, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4534, 'Feriado Municipal', 10, 31);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4534, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4534, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4535, 'Feriado Municipal', 10, 22),
  ('Municipal', 'SC', 4535, 'Feriado Municipal', 9, 11),
  ('Municipal', 'SC', 4536, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SC', 4537, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SC', 4537, 'Feriado Municipal', 7, 22),
  ('Municipal', 'SC', 4537, 'Feriado Municipal', 7, 28),
  ('Municipal', 'SC', 4538, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SC', 4538, 'São Pedro', 6, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4539, 'Feriado Municipal', 11, 27),
  ('Municipal', 'SC', 4540, 'Feriado Municipal', 6, 15),
  ('Municipal', 'SC', 4541, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SC', 4541, 'Feriado Municipal', 2, 2),
  ('Municipal', 'SC', 4542, 'Aniversário da cidade', 2, 14),
  ('Municipal', 'SC', 4542, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4543, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4543, 'Feriado Municipal', 6, 27),
  ('Municipal', 'SC', 4543, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4544, 'Aniversário da cidade', 2, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4544, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4545, 'Feriado Municipal', 9, 11),
  ('Municipal', 'SC', 4546, 'Feriado Municipal', 10, 3),
  ('Municipal', 'SC', 4546, 'Feriado Municipal', 7, 23),
  ('Municipal', 'SC', 4547, 'Feriado Municipal', 9, 15),
  ('Municipal', 'SC', 4548, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SC', 4548, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4549, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'SC', 4550, 'Feriado Municipal', 8, 29),
  ('Municipal', 'SC', 4551, 'Aniversário da cidade', 3, 9);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4552, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4552, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4552, 'São Sebastião', 1, 20),
  ('Municipal', 'SC', 4554, 'Feriado Municipal', 11, 11),
  ('Municipal', 'SC', 4555, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SC', 4556, 'Feriado Municipal', 2, 2),
  ('Municipal', 'SC', 4556, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SC', 4558, 'Feriado Municipal', 6, 12),
  ('Municipal', 'SC', 4559, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'SC', 4559, 'Feriado Municipal', 8, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4559, 'Feriado Municipal', 12, 4),
  ('Municipal', 'SC', 4560, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SC', 4560, 'Feriado Municipal', 12, 19),
  ('Municipal', 'SC', 4561, 'Feriado Municipal', 12, 12),
  ('Municipal', 'SC', 4561, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4561, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4562, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4562, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4563, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4563, 'Feriado Municipal', 12, 31);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4564, 'Feriado Municipal', 7, 18),
  ('Municipal', 'SC', 4567, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SC', 4568, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SC', 4568, 'Feriado Municipal', 12, 28),
  ('Municipal', 'SC', 4568, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4568, 'Feriado Municipal', 2, 11),
  ('Municipal', 'SC', 4568, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4569, 'Aniversário da cidade', 1, 23),
  ('Municipal', 'SC', 4569, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4569, 'Feriado Municipal', 11, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4569, 'Feriado Municipal', 5, 19),
  ('Municipal', 'SC', 4570, 'Feriado Municipal', 5, 12),
  ('Municipal', 'SC', 4570, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4570, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4571, 'Feriado Municipal', 7, 27),
  ('Municipal', 'SC', 4571, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4572, 'Feriado Municipal', 6, 11),
  ('Municipal', 'SC', 4572, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4573, 'Feriado Municipal', 6, 2),
  ('Municipal', 'SC', 4573, 'Feriado Municipal', 11, 11);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4574, 'São João', 6, 24),
  ('Municipal', 'SC', 4574, 'Feriado Municipal', 11, 1),
  ('Municipal', 'SC', 4574, 'Feriado Municipal', 4, 23),
  ('Municipal', 'SC', 4574, 'Feriado Municipal', 9, 6),
  ('Municipal', 'SC', 4575, 'Feriado Municipal', 12, 20),
  ('Municipal', 'SC', 4575, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SC', 4577, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4577, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4577, 'Feriado Municipal', 12, 30),
  ('Municipal', 'SC', 4578, 'Feriado Municipal', 2, 2);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4578, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4580, 'Feriado Municipal', 5, 15),
  ('Municipal', 'SC', 4580, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4580, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SC', 4581, 'Feriado Municipal', 8, 16),
  ('Municipal', 'SC', 4583, 'Feriado Municipal', 2, 2),
  ('Municipal', 'SC', 4583, 'Feriado Municipal', 8, 26),
  ('Municipal', 'SC', 4584, 'Feriado Municipal', 12, 28),
  ('Municipal', 'SC', 4584, 'Feriado Municipal', 5, 13),
  ('Municipal', 'SC', 4584, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4586, 'Feriado Municipal', 7, 9),
  ('Municipal', 'SC', 4586, 'Feriado Municipal', 8, 8),
  ('Municipal', 'SC', 4587, 'Feriado Municipal', 6, 20),
  ('Municipal', 'SC', 4588, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SC', 4589, 'Feriado Municipal', 8, 30),
  ('Municipal', 'SC', 4590, 'Feriado Municipal', 5, 10),
  ('Municipal', 'SC', 4591, 'Feriado Municipal', 2, 2),
  ('Municipal', 'SC', 4592, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SC', 4595, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SC', 4595, 'Feriado Municipal', 4, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4596, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4596, 'Feriado Municipal', 12, 30),
  ('Municipal', 'SC', 4596, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4598, 'Aniversário da cidade', 3, 2),
  ('Municipal', 'SC', 4598, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4598, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4599, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SC', 4599, 'Feriado Municipal', 4, 11),
  ('Municipal', 'SC', 4599, 'São Sebastião', 1, 20),
  ('Municipal', 'SC', 4600, 'Aniversário da cidade', 1, 9);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4603, 'Feriado Municipal', 11, 1),
  ('Municipal', 'SC', 4603, 'Feriado Municipal', 12, 30),
  ('Municipal', 'SC', 4604, 'Feriado Municipal', 9, 29),
  ('Municipal', 'SC', 4604, 'Feriado Municipal', 12, 29),
  ('Municipal', 'SC', 4605, 'Feriado Municipal', 7, 19),
  ('Municipal', 'SC', 4606, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SC', 4606, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4607, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4607, 'Feriado Municipal', 8, 16),
  ('Municipal', 'SC', 4607, 'Feriado Municipal', 8, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4608, 'Feriado Municipal', 12, 30),
  ('Municipal', 'SC', 4609, 'São Pedro', 6, 29),
  ('Municipal', 'SC', 4610, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SC', 4612, 'Aniversário da cidade', 1, 21),
  ('Municipal', 'SC', 4612, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4613, 'Feriado Municipal', 9, 20),
  ('Municipal', 'SC', 4613, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SC', 4615, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SC', 4615, 'Feriado Municipal', 7, 27),
  ('Municipal', 'SC', 4616, 'Feriado Municipal', 10, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4616, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SC', 4617, 'Feriado Municipal', 9, 5),
  ('Municipal', 'SC', 4618, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4619, 'São Sebastião', 1, 20),
  ('Municipal', 'SC', 4620, 'Feriado Municipal', 10, 3),
  ('Municipal', 'SC', 4620, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4620, 'Feriado Municipal', 2, 4),
  ('Municipal', 'SC', 4621, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4622, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SC', 4622, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4622, 'Feriado Municipal', 12, 12),
  ('Municipal', 'SC', 4624, 'Feriado Municipal', 10, 6),
  ('Municipal', 'SC', 4625, 'Feriado Municipal', 8, 22),
  ('Municipal', 'SC', 4625, 'Feriado Municipal', 11, 8),
  ('Municipal', 'SC', 4626, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SC', 4626, 'Feriado Municipal', 7, 27),
  ('Municipal', 'SC', 4627, 'Feriado Municipal', 12, 29),
  ('Municipal', 'SC', 4627, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4628, 'Feriado Municipal', 6, 23),
  ('Municipal', 'SC', 4630, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4629, 'Feriado Municipal', 4, 15),
  ('Municipal', 'SC', 4631, 'Feriado Municipal', 4, 25),
  ('Municipal', 'SC', 4631, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4631, 'Feriado Municipal', 6, 21),
  ('Municipal', 'SC', 4632, 'Feriado Municipal', 4, 24),
  ('Municipal', 'SC', 4634, 'Feriado Municipal', 12, 12),
  ('Municipal', 'SC', 4634, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4635, 'Aniversário da cidade', 3, 14),
  ('Municipal', 'SC', 4636, 'Feriado Municipal', 9, 23),
  ('Municipal', 'SC', 4636, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4636, 'Feriado Municipal', 10, 31),
  ('Municipal', 'SC', 4636, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4637, 'Feriado Municipal', 9, 19),
  ('Municipal', 'SC', 4637, 'Feriado Municipal', 12, 29),
  ('Municipal', 'SC', 4639, 'Feriado Municipal', 2, 16),
  ('Municipal', 'SC', 4639, 'Feriado Municipal', 12, 15),
  ('Municipal', 'SC', 4642, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SC', 4643, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4643, 'Feriado Municipal', 8, 30),
  ('Municipal', 'SC', 4643, 'Feriado Municipal', 5, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4644, 'Aniversário da cidade', 1, 4),
  ('Municipal', 'SC', 4644, 'Feriado Municipal', 8, 23),
  ('Municipal', 'SC', 4648, 'Feriado Municipal', 7, 10),
  ('Municipal', 'SC', 4648, 'Feriado Municipal', 1, 15),
  ('Municipal', 'SC', 4649, 'Feriado Municipal', 9, 23),
  ('Municipal', 'SC', 4651, 'Feriado Municipal', 6, 5),
  ('Municipal', 'SC', 4651, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4651, 'Feriado Municipal', 12, 29),
  ('Municipal', 'SC', 4652, 'Feriado Municipal', 11, 4),
  ('Municipal', 'SC', 4652, 'Feriado Municipal', 12, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4652, 'Aniversário da cidade', 2, 21),
  ('Municipal', 'SC', 4654, 'Feriado Municipal', 4, 7),
  ('Municipal', 'SC', 4654, 'Feriado Municipal', 8, 8),
  ('Municipal', 'SC', 4655, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SC', 4655, 'Feriado Municipal', 4, 15),
  ('Municipal', 'SC', 4656, 'Feriado Municipal', 7, 19),
  ('Municipal', 'SC', 4658, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4658, 'Feriado Municipal', 12, 12),
  ('Municipal', 'SC', 4659, 'Feriado Municipal', 6, 27),
  ('Municipal', 'SC', 4660, 'Feriado Municipal', 5, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4661, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SC', 4662, 'Feriado Municipal', 7, 27),
  ('Municipal', 'SC', 4662, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SC', 4663, 'São Pedro', 6, 29),
  ('Municipal', 'SC', 4664, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SC', 4665, 'Feriado Municipal', 6, 12),
  ('Municipal', 'SC', 4665, 'Feriado Municipal', 3, 26),
  ('Municipal', 'SC', 4666, 'Feriado Municipal', 11, 14),
  ('Municipal', 'SC', 4666, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4667, 'Aniversário da cidade', 1, 9);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4668, 'Feriado Municipal', 9, 29),
  ('Municipal', 'SC', 4668, 'Aniversário da cidade', 2, 15),
  ('Municipal', 'SC', 4670, 'Feriado Municipal', 12, 30),
  ('Municipal', 'SC', 4671, 'Feriado Municipal', 10, 3),
  ('Municipal', 'SC', 4672, 'Feriado Municipal', 4, 3),
  ('Municipal', 'SC', 4672, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4674, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4674, 'Feriado Municipal', 12, 4),
  ('Municipal', 'SC', 4675, 'São Sebastião', 1, 20),
  ('Municipal', 'SC', 4675, 'Feriado Municipal', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4677, 'Aniversário da cidade', 2, 12),
  ('Municipal', 'SC', 4678, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SC', 4678, 'Aniversário da cidade', 2, 19),
  ('Municipal', 'SC', 4680, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SC', 4680, 'São Sebastião', 1, 20),
  ('Municipal', 'SC', 4681, 'Feriado Municipal', 9, 23),
  ('Municipal', 'SC', 4681, 'Feriado Municipal', 8, 16),
  ('Municipal', 'SC', 4683, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SC', 4683, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4684, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4684, 'Aniversário da cidade', 1, 23),
  ('Municipal', 'SC', 4686, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SC', 4686, 'Feriado Municipal', 5, 13),
  ('Municipal', 'SC', 4686, 'Feriado Municipal', 9, 28),
  ('Municipal', 'SC', 4687, 'Feriado Municipal', 10, 13),
  ('Municipal', 'SC', 4688, 'Feriado Municipal', 7, 22),
  ('Municipal', 'SC', 4689, 'Feriado Municipal', 9, 15),
  ('Municipal', 'SC', 4690, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4690, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4691, 'Aniversário da cidade', 3, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4691, 'Feriado Municipal', 7, 12),
  ('Municipal', 'SC', 4692, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4692, 'Feriado Municipal', 8, 25),
  ('Municipal', 'SC', 4692, 'Aniversário da cidade', 1, 4),
  ('Municipal', 'SC', 4693, 'Aniversário da cidade', 2, 3),
  ('Municipal', 'SC', 4693, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SC', 4694, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SC', 4694, 'Feriado Municipal', 6, 1),
  ('Municipal', 'SC', 4695, 'Feriado Municipal', 5, 26),
  ('Municipal', 'SC', 4695, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4696, 'São Pedro', 6, 29),
  ('Municipal', 'SC', 4697, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SC', 4699, 'Aniversário da cidade', 2, 17),
  ('Municipal', 'SC', 4699, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4700, 'Aniversário da cidade', 3, 1),
  ('Municipal', 'SC', 4700, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SC', 4701, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SC', 4701, 'Feriado Municipal', 12, 26),
  ('Municipal', 'SC', 4702, 'Feriado Municipal', 6, 15),
  ('Municipal', 'SC', 4702, 'Feriado Municipal', 12, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SC', 4702, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4703, 'Aniversário da cidade', 2, 27),
  ('Municipal', 'SC', 4704, 'Aniversário da cidade', 2, 2),
  ('Municipal', 'SC', 4704, 'Feriado Municipal', 10, 7),
  ('Municipal', 'SC', 4704, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SC', 4705, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'SC', 4705, 'Feriado Municipal', 6, 21);


-- Sergipe (SE)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'SE', 'Independência de Sergipe', 7, 8);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SE', 4707, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4708, 'Aniversário da cidade', 4, 4),
  ('Municipal', 'SE', 4709, 'Aniversário da cidade', 3, 17),
  ('Municipal', 'SE', 4710, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'SE', 4711, 'Aniversário da cidade', 11, 11),
  ('Municipal', 'SE', 4712, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4713, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'SE', 4714, 'Aniversário da cidade', 10, 22),
  ('Municipal', 'SE', 4715, 'Aniversário da cidade', 10, 29),
  ('Municipal', 'SE', 4716, 'Aniversário da cidade', 1, 23);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SE', 4717, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4718, 'Aniversário da cidade', 8, 28),
  ('Municipal', 'SE', 4719, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4720, 'Aniversário da cidade', 10, 16),
  ('Municipal', 'SE', 4721, 'Aniversário da cidade', 10, 4),
  ('Municipal', 'SE', 4722, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'SE', 4723, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4724, 'Aniversário da cidade', 3, 12),
  ('Municipal', 'SE', 4725, 'Aniversário da cidade', 5, 4),
  ('Municipal', 'SE', 4726, 'Aniversário da cidade', 10, 18);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SE', 4727, 'Aniversário da cidade', 10, 23),
  ('Municipal', 'SE', 4728, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'SE', 4729, 'Aniversário da cidade', 11, 21),
  ('Municipal', 'SE', 4730, 'Aniversário da cidade', 2, 5),
  ('Municipal', 'SE', 4731, 'Aniversário da cidade', 1, 30),
  ('Municipal', 'SE', 4732, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'SE', 4733, 'Aniversário da cidade', 8, 28),
  ('Municipal', 'SE', 4734, 'Aniversário da cidade', 10, 19),
  ('Municipal', 'SE', 4735, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4736, 'Aniversário da cidade', 3, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SE', 4737, 'Aniversário da cidade', 6, 11),
  ('Municipal', 'SE', 4738, 'Aniversário da cidade', 2, 6),
  ('Municipal', 'SE', 4739, 'Aniversário da cidade', 4, 20),
  ('Municipal', 'SE', 4740, 'Aniversário da cidade', 8, 7),
  ('Municipal', 'SE', 4741, 'Aniversário da cidade', 11, 23),
  ('Municipal', 'SE', 4742, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4743, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4744, 'Aniversário da cidade', 5, 5),
  ('Municipal', 'SE', 4745, 'Aniversário da cidade', 3, 12),
  ('Municipal', 'SE', 4746, 'Aniversário da cidade', 11, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SE', 4747, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'SE', 4748, 'Aniversário da cidade', 10, 18),
  ('Municipal', 'SE', 4749, 'Aniversário da cidade', 12, 24),
  ('Municipal', 'SE', 4750, 'Aniversário da cidade', 9, 26),
  ('Municipal', 'SE', 4751, 'Aniversário da cidade', 10, 23),
  ('Municipal', 'SE', 4752, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'SE', 4753, 'Aniversário da cidade', 7, 7),
  ('Municipal', 'SE', 4754, 'Aniversário da cidade', 10, 7),
  ('Municipal', 'SE', 4755, 'Aniversário da cidade', 11, 21),
  ('Municipal', 'SE', 4756, 'Aniversário da cidade', 11, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SE', 4757, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4758, 'Aniversário da cidade', 11, 26),
  ('Municipal', 'SE', 4759, 'Aniversário da cidade', 11, 23),
  ('Municipal', 'SE', 4760, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4761, 'Aniversário da cidade', 2, 19),
  ('Municipal', 'SE', 4762, 'Aniversário da cidade', 2, 7),
  ('Municipal', 'SE', 4763, 'Aniversário da cidade', 5, 9),
  ('Municipal', 'SE', 4764, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'SE', 4765, 'Aniversário da cidade', 12, 18),
  ('Municipal', 'SE', 4766, 'Aniversário da cidade', 3, 12);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SE', 4767, 'Aniversário da cidade', 10, 4),
  ('Municipal', 'SE', 4768, 'Aniversário da cidade', 2, 16),
  ('Municipal', 'SE', 4770, 'Aniversário da cidade', 4, 6),
  ('Municipal', 'SE', 4769, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4771, 'Aniversário da cidade', 12, 15),
  ('Municipal', 'SE', 4772, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'SE', 4773, 'Aniversário da cidade', 10, 21),
  ('Municipal', 'SE', 4774, 'Aniversário da cidade', 6, 17),
  ('Municipal', 'SE', 4775, 'Aniversário da cidade', 11, 26),
  ('Municipal', 'SE', 4776, 'Aniversário da cidade', 6, 12);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SE', 4777, 'Aniversário da cidade', 12, 15),
  ('Municipal', 'SE', 4778, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'SE', 4779, 'Aniversário da cidade', 10, 23),
  ('Municipal', 'SE', 4780, 'Aniversário da cidade', 11, 25),
  ('Municipal', 'SE', 4781, 'Aniversário da cidade', 2, 6);


-- São Paulo (SP)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'SP', 'Revolução Constitucionalista de 1932', 7, 9);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4782, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 4782, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4782, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4783, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 4783, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4784, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4784, 'Aniversário da cidade', 8, 6),
  ('Municipal', 'SP', 4785, 'Feriado Municipal', 7, 3),
  ('Municipal', 'SP', 4785, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4785, 'Feriado Municipal', 2, 11);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4785, 'Aniversário da cidade', 7, 2),
  ('Municipal', 'SP', 4786, 'Feriado Municipal', 7, 2),
  ('Municipal', 'SP', 4786, 'Aniversário da cidade', 11, 16),
  ('Municipal', 'SP', 4787, 'Feriado Municipal', 12, 4),
  ('Municipal', 'SP', 4787, 'Aniversário da cidade', 4, 20),
  ('Municipal', 'SP', 4788, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4788, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4788, 'Aniversário da cidade', 7, 25),
  ('Municipal', 'SP', 4789, 'Aniversário da cidade', 7, 27),
  ('Municipal', 'SP', 4791, 'Aniversário da cidade', 12, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4791, 'Feriado Municipal', 12, 24),
  ('Municipal', 'SP', 4791, 'Feriado Municipal', 4, 4),
  ('Municipal', 'SP', 4791, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4792, 'Aniversário da cidade', 12, 31),
  ('Municipal', 'SP', 4792, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4792, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4792, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4793, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4793, 'Aniversário da cidade', 3, 9),
  ('Municipal', 'SP', 4794, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4794, 'Aniversário da cidade', 8, 15),
  ('Municipal', 'SP', 4795, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'SP', 4796, 'Feriado Municipal', 4, 10),
  ('Municipal', 'SP', 4796, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 4797, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4797, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 4798, 'Aniversário da cidade', 11, 22),
  ('Municipal', 'SP', 4799, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4799, 'Feriado Municipal', 8, 8),
  ('Municipal', 'SP', 4800, 'Aniversário da cidade', 8, 27);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4800, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4800, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4801, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4801, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'SP', 4802, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4802, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 4803, 'Aniversário da cidade', 4, 8),
  ('Municipal', 'SP', 4803, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4803, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 4804, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4804, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4804, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4804, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 4804, 'Aniversário da cidade', 6, 21),
  ('Municipal', 'SP', 4805, 'Aniversário da cidade', 7, 11),
  ('Municipal', 'SP', 4805, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4806, 'Aniversário da cidade', 3, 11),
  ('Municipal', 'SP', 4807, 'Aniversário da cidade', 4, 15),
  ('Municipal', 'SP', 4807, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4807, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 4808, 'Aniversário da cidade', 12, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4808, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 4809, 'Aniversário da cidade', 12, 17),
  ('Municipal', 'SP', 4809, 'Feriado Municipal', 4, 16),
  ('Municipal', 'SP', 4809, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4810, 'Aniversário da cidade', 3, 22),
  ('Municipal', 'SP', 4811, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4811, 'Aniversário da cidade', 8, 14),
  ('Municipal', 'SP', 4812, 'Feriado Municipal', 9, 16),
  ('Municipal', 'SP', 4812, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 4813, 'Feriado Municipal', 12, 2),
  ('Municipal', 'SP', 4813, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4814, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4814, 'Aniversário da cidade', 4, 7),
  ('Municipal', 'SP', 4815, 'Aniversário da cidade', 4, 4),
  ('Municipal', 'SP', 4815, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4815, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4816, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 4816, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4816, 'Feriado Municipal', 11, 30),
  ('Municipal', 'SP', 4817, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 4818, 'Aniversário da cidade', 8, 22);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4818, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4819, 'Aniversário da cidade', 3, 24),
  ('Municipal', 'SP', 4819, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4819, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4821, 'Aniversário da cidade', 4, 1),
  ('Municipal', 'SP', 4821, 'Feriado Municipal', 11, 25),
  ('Municipal', 'SP', 4822, 'Aniversário da cidade', 7, 26),
  ('Municipal', 'SP', 4822, 'Feriado Municipal', 7, 27),
  ('Municipal', 'SP', 4823, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4823, 'Aniversário da cidade', 5, 3);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4824, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'SP', 4824, 'São João', 6, 24),
  ('Municipal', 'SP', 4825, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'SP', 4825, 'Feriado Municipal', 9, 15),
  ('Municipal', 'SP', 4826, 'Aniversário da cidade', 6, 8),
  ('Municipal', 'SP', 4826, 'Dia do Padroeiro Senhor Bom Jesus', 8, 6),
  ('Municipal', 'SP', 4826, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4827, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 4828, 'Aniversário da cidade', 7, 1),
  ('Municipal', 'SP', 4829, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 4830, 'Aniversário da cidade', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4831, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4831, 'Feriado Municipal', 12, 2),
  ('Municipal', 'SP', 4832, 'Aniversário da cidade', 12, 29),
  ('Municipal', 'SP', 4832, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SP', 4833, 'Aniversário da cidade', 9, 15),
  ('Municipal', 'SP', 4834, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 4834, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4834, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4835, 'Aniversário da cidade', 6, 14),
  ('Municipal', 'SP', 4835, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4835, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4836, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4836, 'Aniversário da cidade', 11, 17),
  ('Municipal', 'SP', 4837, 'Aniversário da cidade', 7, 10),
  ('Municipal', 'SP', 4837, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4837, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 4838, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'SP', 4839, 'Aniversário da cidade', 1, 31),
  ('Municipal', 'SP', 4839, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4839, 'Feriado Municipal', 8, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4840, 'Aniversário da cidade', 6, 16),
  ('Municipal', 'SP', 4840, 'Feriado Municipal', 7, 15),
  ('Municipal', 'SP', 4841, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 4842, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 4843, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'SP', 4843, 'Feriado Municipal', 8, 19),
  ('Municipal', 'SP', 4844, 'Aniversário da cidade', 8, 25),
  ('Municipal', 'SP', 4844, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4845, 'Aniversário da cidade', 12, 30),
  ('Municipal', 'SP', 4845, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4845, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4846, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4846, 'São João Batista (Padroeiro da cidade)', 6, 24),
  ('Municipal', 'SP', 4847, 'Aniversário da cidade', 7, 18),
  ('Municipal', 'SP', 4847, 'Feriado Municipal', 12, 3),
  ('Municipal', 'SP', 4847, 'Feriado Municipal', 6, 18),
  ('Municipal', 'SP', 4848, 'Aniversário da cidade', 3, 14),
  ('Municipal', 'SP', 4848, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 4849, 'Aniversário da cidade', 8, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4850, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 4850, 'São João', 6, 24),
  ('Municipal', 'SP', 4851, 'Aniversário da cidade', 3, 27),
  ('Municipal', 'SP', 4851, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4851, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4852, 'Aniversário da cidade', 10, 9),
  ('Municipal', 'SP', 4852, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4853, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 4853, 'São João', 6, 24),
  ('Municipal', 'SP', 4853, 'Feriado Municipal', 10, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4854, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 4854, 'Feriado Municipal', 4, 18),
  ('Municipal', 'SP', 4854, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 4855, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4856, 'Aniversário da cidade', 5, 5),
  ('Municipal', 'SP', 4856, 'Dia de São Benedito (Padroeiro da cidade)', 10, 5),
  ('Municipal', 'SP', 4856, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4857, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4857, 'Feriado Municipal', 7, 21),
  ('Municipal', 'SP', 4857, 'Aniversário da cidade', 7, 22),
  ('Municipal', 'SP', 4858, 'Aniversário da cidade', 5, 23);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4858, 'São João', 6, 24),
  ('Municipal', 'SP', 4859, 'Aniversário da cidade', 12, 21),
  ('Municipal', 'SP', 4859, 'Feriado Municipal', 9, 9),
  ('Municipal', 'SP', 4859, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4860, 'Aniversário da cidade', 9, 5),
  ('Municipal', 'SP', 4860, 'Feriado Municipal', 8, 16),
  ('Municipal', 'SP', 4860, 'Feriado Municipal', 9, 6),
  ('Municipal', 'SP', 4861, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 4861, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'SP', 4862, 'Feriado Municipal', 7, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4863, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4863, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'SP', 4864, 'Aniversário da cidade', 5, 7),
  ('Municipal', 'SP', 4865, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'SP', 4865, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4865, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4866, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SP', 4867, 'Aniversário da cidade', 4, 14),
  ('Municipal', 'SP', 4867, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 4868, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4868, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4868, 'Aniversário da cidade', 12, 15),
  ('Municipal', 'SP', 4869, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4869, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'SP', 4871, 'Aniversário da cidade', 8, 22),
  ('Municipal', 'SP', 4871, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4872, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 4873, 'Aniversário da cidade', 1, 25),
  ('Municipal', 'SP', 4873, 'Feriado Municipal', 9, 27),
  ('Municipal', 'SP', 4873, 'Feriado Municipal', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4873, 'Feriado Municipal', 8, 16),
  ('Municipal', 'SP', 4874, 'Aniversário da cidade', 8, 24),
  ('Municipal', 'SP', 4874, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4874, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4875, 'Aniversário da cidade', 9, 6),
  ('Municipal', 'SP', 4875, 'Feriado Municipal', 7, 28),
  ('Municipal', 'SP', 4875, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 4876, 'Feriado Municipal', 5, 17),
  ('Municipal', 'SP', 4876, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 4876, 'Feriado Municipal', 12, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4877, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4877, 'Aniversário da cidade', 3, 24),
  ('Municipal', 'SP', 4878, 'São João', 6, 24),
  ('Municipal', 'SP', 4878, 'Feriado Municipal', 4, 13),
  ('Municipal', 'SP', 4878, 'Aniversário da cidade', 4, 14),
  ('Municipal', 'SP', 4879, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 4879, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4879, 'Feriado Municipal', 3, 9),
  ('Municipal', 'SP', 4879, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4880, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4880, 'Feriado Municipal', 4, 27),
  ('Municipal', 'SP', 4881, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4881, 'Aniversário da cidade', 4, 11),
  ('Municipal', 'SP', 4882, 'Aniversário da cidade', 12, 30),
  ('Municipal', 'SP', 4882, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4882, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4883, 'Aniversário da cidade', 12, 14),
  ('Municipal', 'SP', 4883, 'Santo Antônio (Padroeiro da cidade)', 6, 13),
  ('Municipal', 'SP', 4883, 'Consciência negra', 11, 20),
  ('Municipal', 'SP', 4884, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 4884, 'Aniversário da cidade', 3, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4885, 'São Sebastião (Padroeiro da cidade)', 1, 20),
  ('Municipal', 'SP', 4885, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 4886, 'Feriado Municipal', 5, 19),
  ('Municipal', 'SP', 4886, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4887, 'Aniversário da cidade', 5, 13),
  ('Municipal', 'SP', 4887, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4887, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4888, 'Aniversário da cidade', 8, 18),
  ('Municipal', 'SP', 4888, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4888, 'Feriado Municipal', 7, 11);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4890, 'Aniversário da cidade', 7, 14),
  ('Municipal', 'SP', 4890, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4890, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4891, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'SP', 4891, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 4891, 'Feriado Municipal', 10, 7),
  ('Municipal', 'SP', 4892, 'Feriado Municipal', 10, 1),
  ('Municipal', 'SP', 4892, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4892, 'Aniversário da cidade', 4, 29),
  ('Municipal', 'SP', 4893, 'Aniversário da cidade', 3, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4893, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4893, 'São João', 6, 24),
  ('Municipal', 'SP', 4894, 'São João', 6, 24),
  ('Municipal', 'SP', 4894, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4894, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4894, 'Aniversário da cidade', 8, 12),
  ('Municipal', 'SP', 4895, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4896, 'Feriado Municipal', 9, 15),
  ('Municipal', 'SP', 4896, 'Aniversário da cidade', 10, 26),
  ('Municipal', 'SP', 4897, 'Feriado Municipal', 8, 16),
  ('Municipal', 'SP', 4897, 'Feriado Municipal', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4897, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 4898, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 4899, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4899, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4899, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'SP', 4900, 'Aniversário da cidade', 10, 4),
  ('Municipal', 'SP', 4900, 'Feriado Municipal', 5, 29),
  ('Municipal', 'SP', 4900, 'Feriado Municipal', 3, 26),
  ('Municipal', 'SP', 4901, 'São João', 6, 24),
  ('Municipal', 'SP', 4901, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4902, 'Aniversário da cidade', 4, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4902, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4902, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4902, 'Feriado Municipal', 4, 13),
  ('Municipal', 'SP', 4903, 'São Pedro (Padroeiro da cidade)', 6, 29),
  ('Municipal', 'SP', 4903, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4903, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'SP', 4904, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'SP', 4904, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4905, 'Aniversário da cidade', 10, 25),
  ('Municipal', 'SP', 4906, 'Feriado Municipal', 1, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4906, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 4906, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 4906, 'Santa Rita de Cássia (Padroeira da Cidade)', 5, 22),
  ('Municipal', 'SP', 4907, 'Aniversário da cidade', 8, 10),
  ('Municipal', 'SP', 4907, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4908, 'Aniversário da cidade', 4, 14),
  ('Municipal', 'SP', 4908, 'Feriado Municipal', 8, 8),
  ('Municipal', 'SP', 4909, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4909, 'Feriado Municipal', 7, 4),
  ('Municipal', 'SP', 4909, 'Aniversário da cidade', 5, 3);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4910, 'Aniversário da cidade', 6, 21),
  ('Municipal', 'SP', 4910, 'Feriado Municipal', 3, 16),
  ('Municipal', 'SP', 4910, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4911, 'Feriado Municipal', 10, 1),
  ('Municipal', 'SP', 4911, 'Aniversário da cidade', 10, 10),
  ('Municipal', 'SP', 4912, 'Aniversário da cidade', 4, 3),
  ('Municipal', 'SP', 4912, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4913, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 4914, 'Aniversário da cidade', 12, 30),
  ('Municipal', 'SP', 4914, 'Feriado Municipal', 10, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4914, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4914, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4915, 'Feriado Municipal', 12, 4),
  ('Municipal', 'SP', 4915, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4916, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 4916, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4917, 'Aniversário da cidade', 4, 21),
  ('Municipal', 'SP', 4917, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4917, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4918, 'Feriado Municipal', 7, 16);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4918, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4918, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 4919, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'SP', 4920, 'Feriado Municipal', 12, 4),
  ('Municipal', 'SP', 4920, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4920, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 4921, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4921, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4921, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 4922, 'Aniversário da cidade', 6, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4922, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4922, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4923, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'SP', 4923, 'Feriado Municipal', 6, 6),
  ('Municipal', 'SP', 4923, 'Feriado Municipal', 4, 5),
  ('Municipal', 'SP', 4923, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4924, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4924, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 4925, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 4926, 'Aniversário da cidade', 10, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4926, 'Feriado Municipal', 2, 9),
  ('Municipal', 'SP', 4926, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4927, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'SP', 4927, 'Nossa Senhora de Monte Serrat (Padroeira da cidade)', 9, 8),
  ('Municipal', 'SP', 4928, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 4929, 'Aniversário da cidade', 8, 16),
  ('Municipal', 'SP', 4929, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4929, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4929, 'Feriado Municipal', 7, 28),
  ('Municipal', 'SP', 4930, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4930, 'Aniversário da cidade', 4, 4),
  ('Municipal', 'SP', 4931, 'Aniversário da cidade', 10, 2),
  ('Municipal', 'SP', 4931, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4932, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4932, 'Feriado Municipal', 4, 26),
  ('Municipal', 'SP', 4932, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'SP', 4933, 'Aniversário da cidade', 4, 20),
  ('Municipal', 'SP', 4933, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4934, 'Aniversário da cidade', 9, 6),
  ('Municipal', 'SP', 4934, 'Feriado Municipal', 9, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4935, 'Aniversário da cidade', 12, 8),
  ('Municipal', 'SP', 4935, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4936, 'Aniversário da cidade', 1, 6),
  ('Municipal', 'SP', 4937, 'Feriado Municipal', 12, 30),
  ('Municipal', 'SP', 4937, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4937, 'Aniversário da cidade', 12, 39),
  ('Municipal', 'SP', 4938, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'SP', 4938, 'Feriado Municipal', 4, 2),
  ('Municipal', 'SP', 4939, 'Aniversário da cidade', 2, 4),
  ('Municipal', 'SP', 4940, 'Feriado Municipal', 1, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4940, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4940, 'Aniversário da cidade', 10, 19),
  ('Municipal', 'SP', 4941, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 4941, 'São João', 6, 24),
  ('Municipal', 'SP', 4942, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4943, 'Aniversário da cidade', 12, 11),
  ('Municipal', 'SP', 4943, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SP', 4944, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'SP', 4944, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4945, 'Aniversário da cidade', 11, 30);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4946, 'Aniversário da cidade', 3, 10),
  ('Municipal', 'SP', 4946, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 4946, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4947, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 4947, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4949, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 4949, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SP', 4950, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 4950, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4951, 'FSanta Terezinha (Padroeira da cidade)', 10, 1),
  ('Municipal', 'SP', 4951, 'Aniversário da cidade', 3, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4953, 'Feriado Municipal', 5, 19),
  ('Municipal', 'SP', 4953, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 4954, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SP', 4954, 'Aniversário da cidade', 12, 27),
  ('Municipal', 'SP', 4956, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 4956, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4957, 'Aniversário da cidade', 1, 25),
  ('Municipal', 'SP', 4957, 'Feriado Municipal', 9, 25),
  ('Municipal', 'SP', 4958, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'SP', 4958, 'São Sebastião', 1, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4958, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SP', 4958, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 4959, 'Aniversário da cidade', 9, 15),
  ('Municipal', 'SP', 4959, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4959, 'Feriado Municipal', 1, 9),
  ('Municipal', 'SP', 4959, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4960, 'Aniversário da cidade', 3, 31),
  ('Municipal', 'SP', 4962, 'Feriado Municipal', 7, 5),
  ('Municipal', 'SP', 4962, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'SP', 4961, 'São Sebastião', 1, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4961, 'Feriado Municipal', 7, 5),
  ('Municipal', 'SP', 4961, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SP', 4964, 'Aniversário da cidade', 10, 14),
  ('Municipal', 'SP', 4964, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4965, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 4966, 'Aniversário da cidade', 8, 6),
  ('Municipal', 'SP', 4966, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4966, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4967, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4967, 'Aniversário da cidade', 10, 25),
  ('Municipal', 'SP', 4969, 'Aniversário da cidade', 11, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4969, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4969, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4970, 'Aniversário da cidade', 3, 21),
  ('Municipal', 'SP', 4970, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4971, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 4971, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4971, 'Nossa Senhora Conceição (Padroeira da cidade)', 12, 8),
  ('Municipal', 'SP', 4972, 'Aniversário da cidade', 11, 3),
  ('Municipal', 'SP', 4972, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 4973, 'Feriado Municipal', 3, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4973, 'Aniversário da cidade', 4, 14),
  ('Municipal', 'SP', 4974, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 4974, 'Aniversário da cidade', 5, 5),
  ('Municipal', 'SP', 4975, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4975, 'Feriado Municipal', 1, 6),
  ('Municipal', 'SP', 4975, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 4977, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 4977, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4978, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'SP', 4978, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4978, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4979, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 4979, 'São João', 6, 24),
  ('Municipal', 'SP', 4979, 'Aniversário da cidade', 12, 30),
  ('Municipal', 'SP', 4980, 'São João', 6, 24),
  ('Municipal', 'SP', 4980, 'Aniversário da cidade', 12, 13),
  ('Municipal', 'SP', 4981, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4981, 'Aniversário da cidade', 11, 8),
  ('Municipal', 'SP', 4982, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4982, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4982, 'Feriado Municipal', 5, 18),
  ('Municipal', 'SP', 4983, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4983, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 4984, 'Feriado Municipal', 12, 24),
  ('Municipal', 'SP', 4984, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4984, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 4984, 'Feriado Municipal', 5, 2),
  ('Municipal', 'SP', 4985, 'Aniversário da cidade', 9, 15),
  ('Municipal', 'SP', 4985, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4986, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4986, 'Aniversário da cidade', 10, 12),
  ('Municipal', 'SP', 4987, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 4987, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4987, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 4987, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 4988, 'Aniversário da cidade', 8, 18),
  ('Municipal', 'SP', 4989, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4989, 'Feriado Municipal', 10, 1),
  ('Municipal', 'SP', 4989, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 4990, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4990, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4990, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 4991, 'Aniversário da cidade', 9, 19),
  ('Municipal', 'SP', 4992, 'Feriado Municipal', 10, 25),
  ('Municipal', 'SP', 4992, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 4992, 'Feriado Municipal', 4, 13),
  ('Municipal', 'SP', 4992, 'Feriado Municipal', 4, 9),
  ('Municipal', 'SP', 4992, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 4993, 'São João', 6, 24),
  ('Municipal', 'SP', 4993, 'Aniversário da cidade', 3, 16);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4994, 'Aniversário da cidade', 9, 21),
  ('Municipal', 'SP', 4994, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4995, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4995, 'Feriado Municipal', 1, 15),
  ('Municipal', 'SP', 4995, 'Aniversário da cidade', 6, 30),
  ('Municipal', 'SP', 4996, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 4996, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 4997, 'Aniversário da cidade', 11, 5),
  ('Municipal', 'SP', 4998, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 4998, 'Feriado Municipal', 8, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 4998, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'SP', 4999, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 4999, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 5000, 'Aniversário da cidade', 10, 27),
  ('Municipal', 'SP', 5001, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5001, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 5002, 'Aniversário da cidade', 4, 15),
  ('Municipal', 'SP', 5002, 'São João', 6, 24),
  ('Municipal', 'SP', 5003, 'Aniversário da cidade', 6, 21),
  ('Municipal', 'SP', 5003, 'São Pedro', 6, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5004, 'Aniversário da cidade', 1, 9),
  ('Municipal', 'SP', 5004, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5004, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SP', 5005, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5005, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5006, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5006, 'Aniversário da cidade', 12, 12),
  ('Municipal', 'SP', 5007, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 5007, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5007, 'Feriado Municipal', 2, 11);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5008, 'Aniversário da cidade', 7, 4),
  ('Municipal', 'SP', 5008, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5009, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5009, 'Aniversário da cidade', 3, 24),
  ('Municipal', 'SP', 5010, 'Feriado Municipal', 12, 30),
  ('Municipal', 'SP', 5010, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5010, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5011, 'Feriado Municipal', 11, 30),
  ('Municipal', 'SP', 5011, 'São João', 6, 24),
  ('Municipal', 'SP', 5012, 'Feriado Municipal', 7, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5012, 'Feriado Municipal', 2, 11),
  ('Municipal', 'SP', 5012, 'Aniversário da cidade', 10, 19),
  ('Municipal', 'SP', 5013, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5013, 'Feriado Municipal', 5, 22),
  ('Municipal', 'SP', 5014, 'Feriado Municipal', 12, 5),
  ('Municipal', 'SP', 5014, 'Aniversário da cidade', 12, 30),
  ('Municipal', 'SP', 5015, 'Feriado Municipal', 12, 3),
  ('Municipal', 'SP', 5015, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5015, 'Feriado Municipal', 8, 5),
  ('Municipal', 'SP', 5015, 'Feriado Municipal', 1, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5018, 'Aniversário da cidade', 9, 3),
  ('Municipal', 'SP', 5018, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5016, 'Aniversário da cidade', 3, 5),
  ('Municipal', 'SP', 5017, 'Aniversário da cidade', 10, 15),
  ('Municipal', 'SP', 5017, 'Feriado Municipal', 10, 4),
  ('Municipal', 'SP', 5019, 'Aniversário da cidade', 12, 9),
  ('Municipal', 'SP', 5019, 'Feriado Municipal', 2, 2),
  ('Municipal', 'SP', 5020, 'Aniversário da cidade', 3, 17),
  ('Municipal', 'SP', 5020, 'Feriado Municipal', 10, 7),
  ('Municipal', 'SP', 5021, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5021, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5021, 'Aniversário da cidade', 5, 12),
  ('Municipal', 'SP', 5022, 'Feriado Municipal', 8, 22),
  ('Municipal', 'SP', 5022, 'Aniversário da cidade', 8, 20),
  ('Municipal', 'SP', 5023, 'Aniversário da cidade', 9, 20),
  ('Municipal', 'SP', 5023, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5024, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5024, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5025, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5025, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5025, 'Feriado Municipal', 3, 21);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5027, 'Aniversário da cidade', 1, 12),
  ('Municipal', 'SP', 5027, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5027, 'Feriado Municipal', 12, 31),
  ('Municipal', 'SP', 5028, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5028, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'SP', 5029, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 5030, 'Feriado Municipal', 11, 1),
  ('Municipal', 'SP', 5030, 'Feriado Municipal', 7, 16),
  ('Municipal', 'SP', 5030, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 5031, 'Aniversário da cidade', 5, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5031, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5032, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5032, 'Aniversário da cidade', 4, 25),
  ('Municipal', 'SP', 5033, 'Feriado Municipal', 8, 31),
  ('Municipal', 'SP', 5033, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5033, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5034, 'Aniversário da cidade', 4, 4),
  ('Municipal', 'SP', 5034, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5035, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'SP', 5035, 'Feriado Municipal', 8, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5036, 'Feriado Municipal', 6, 9),
  ('Municipal', 'SP', 5036, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5036, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5036, 'Aniversário da cidade', 4, 22),
  ('Municipal', 'SP', 5037, 'Aniversário da cidade', 11, 19),
  ('Municipal', 'SP', 5038, 'Aniversário da cidade', 5, 8),
  ('Municipal', 'SP', 5038, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5039, 'Aniversário da cidade', 11, 5),
  ('Municipal', 'SP', 5040, 'Aniversário da cidade', 9, 20),
  ('Municipal', 'SP', 5040, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5040, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5041, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 5041, 'São Judas Tadeu (Padroeiro da cidade)', 10, 28),
  ('Municipal', 'SP', 5041, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5042, 'Aniversário da cidade', 10, 24),
  ('Municipal', 'SP', 5042, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5042, 'Feriado Municipal', 5, 13),
  ('Municipal', 'SP', 5043, 'Aniversário da cidade', 3, 12),
  ('Municipal', 'SP', 5044, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5044, 'Aniversário da cidade', 10, 20),
  ('Municipal', 'SP', 5045, 'Aniversário da cidade', 3, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5045, 'São João', 6, 24),
  ('Municipal', 'SP', 5045, 'Feriado Municipal', 8, 21),
  ('Municipal', 'SP', 5046, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5046, 'Aniversário da cidade', 9, 11),
  ('Municipal', 'SP', 5047, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5047, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5048, 'Aniversário da cidade', 9, 8),
  ('Municipal', 'SP', 5048, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5049, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5049, 'São Pedro', 6, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5049, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5049, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5049, 'Aniversário da cidade', 8, 28),
  ('Municipal', 'SP', 5050, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'SP', 5050, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5051, 'Aniversário da cidade', 11, 1),
  ('Municipal', 'SP', 5051, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5051, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5052, 'Aniversário da cidade', 7, 24),
  ('Municipal', 'SP', 5052, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5053, 'Aniversário da cidade', 3, 25),
  ('Municipal', 'SP', 5053, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5053, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5054, 'Feriado Municipal', 3, 27),
  ('Municipal', 'SP', 5055, 'Aniversário da cidade', 8, 27),
  ('Municipal', 'SP', 5055, 'Feriado Municipal', 11, 1),
  ('Municipal', 'SP', 5056, 'Aniversário da cidade', 2, 2),
  ('Municipal', 'SP', 5056, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5057, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5057, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5058, 'Feriado Municipal', 7, 16);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5058, 'Feriado Municipal', 3, 10),
  ('Municipal', 'SP', 5059, 'Aniversário da cidade', 3, 18),
  ('Municipal', 'SP', 5059, 'Feriado Municipal', 9, 29),
  ('Municipal', 'SP', 5060, 'Aniversário da cidade', 7, 16),
  ('Municipal', 'SP', 5061, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5061, 'Aniversário da cidade', 4, 3),
  ('Municipal', 'SP', 5062, 'Aniversário da cidade', 4, 4),
  ('Municipal', 'SP', 5062, 'Feriado Municipal', 9, 25),
  ('Municipal', 'SP', 5063, 'Aniversário da cidade', 6, 23),
  ('Municipal', 'SP', 5063, 'São Pedro', 6, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5063, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5064, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5064, 'Aniversário da cidade', 9, 12),
  ('Municipal', 'SP', 5065, 'Aniversário da cidade', 4, 15),
  ('Municipal', 'SP', 5065, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5066, 'Aniversário da cidade', 3, 30),
  ('Municipal', 'SP', 5066, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5067, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5067, 'Aniversário da cidade', 12, 8),
  ('Municipal', 'SP', 5068, 'Feriado Municipal', 8, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5068, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5068, 'Aniversário da cidade', 7, 27),
  ('Municipal', 'SP', 5069, 'Aniversário da cidade', 4, 17),
  ('Municipal', 'SP', 5069, 'Feriado Municipal', 7, 16),
  ('Municipal', 'SP', 5069, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5070, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5070, 'Aniversário da cidade', 8, 15),
  ('Municipal', 'SP', 5071, 'Aniversário da cidade', 4, 7),
  ('Municipal', 'SP', 5071, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5071, 'São Sebastião', 1, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5072, 'Feriado Municipal', 8, 17),
  ('Municipal', 'SP', 5072, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5073, 'São João', 6, 24),
  ('Municipal', 'SP', 5073, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5073, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 5074, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5075, 'Feriado Municipal', 2, 21),
  ('Municipal', 'SP', 5075, 'Aniversário da cidade', 12, 25),
  ('Municipal', 'SP', 5076, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5076, 'Feriado Municipal', 5, 21);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5077, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5077, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5077, 'Aniversário da cidade', 12, 14),
  ('Municipal', 'SP', 5078, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5079, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5079, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'SP', 5080, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'SP', 5080, 'Nossa Senhora do Desterro (Padroeira da cidade)', 9, 15),
  ('Municipal', 'SP', 5080, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5081, 'Aniversário da cidade', 12, 23),
  ('Municipal', 'SP', 5081, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5082, 'Aniversário da cidade', 10, 10),
  ('Municipal', 'SP', 5082, 'São João', 6, 24),
  ('Municipal', 'SP', 5083, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5083, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 5084, 'Aniversário da cidade', 6, 27),
  ('Municipal', 'SP', 5084, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5085, 'Aniversário da cidade', 8, 29),
  ('Municipal', 'SP', 5085, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5086, 'Feriado Municipal', 9, 15),
  ('Municipal', 'SP', 5086, 'Aniversário da cidade', 4, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5087, 'Aniversário da cidade', 9, 15),
  ('Municipal', 'SP', 5087, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5088, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5088, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5089, 'Aniversário da cidade', 4, 21),
  ('Municipal', 'SP', 5089, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5090, 'Aniversário da cidade', 11, 14),
  ('Municipal', 'SP', 5090, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5090, 'Feriado Municipal', 4, 13),
  ('Municipal', 'SP', 5091, 'Aniversário da cidade', 3, 5);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5092, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5092, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5093, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5093, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5094, 'Aniversário da cidade', 6, 29),
  ('Municipal', 'SP', 5095, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5095, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SP', 5095, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 5096, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 5097, 'Feriado Municipal', 7, 31);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5097, 'Aniversário da cidade', 12, 30),
  ('Municipal', 'SP', 5098, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5098, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 5099, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5099, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5100, 'Aniversário da cidade', 5, 2),
  ('Municipal', 'SP', 5100, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5100, 'Feriado Municipal', 4, 2),
  ('Municipal', 'SP', 5101, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5101, 'Aniversário da cidade', 10, 27);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5102, 'Aniversário da cidade', 10, 3),
  ('Municipal', 'SP', 5102, 'Feriado Municipal', 10, 1),
  ('Municipal', 'SP', 5102, 'Feriado Municipal', 3, 25),
  ('Municipal', 'SP', 5102, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5103, 'Aniversário da cidade', 10, 27),
  ('Municipal', 'SP', 5103, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5103, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5104, 'Aniversário da cidade', 3, 27),
  ('Municipal', 'SP', 5104, 'Nossa Senhora do Desterro (Padroeira da cidade)', 9, 15),
  ('Municipal', 'SP', 5105, 'Aniversário da cidade', 11, 30);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5105, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5106, 'Feriado Municipal', 10, 1),
  ('Municipal', 'SP', 5106, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5106, 'Aniversário da cidade', 9, 11),
  ('Municipal', 'SP', 5107, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5107, 'Feriado Municipal', 5, 4),
  ('Municipal', 'SP', 5107, 'Aniversário da cidade', 12, 19),
  ('Municipal', 'SP', 5108, 'Feriado Municipal', 10, 27),
  ('Municipal', 'SP', 5109, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5109, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5110, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5110, 'Aniversário da cidade', 4, 4),
  ('Municipal', 'SP', 5111, 'Feriado Municipal', 6, 16),
  ('Municipal', 'SP', 5112, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5112, 'Feriado Municipal', 12, 2),
  ('Municipal', 'SP', 5112, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5112, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5113, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5113, 'Aniversário da cidade', 8, 27),
  ('Municipal', 'SP', 5114, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5114, 'Aniversário da cidade', 12, 8),
  ('Municipal', 'SP', 5115, 'Feriado Municipal', 8, 17),
  ('Municipal', 'SP', 5115, 'Feriado Municipal', 11, 1),
  ('Municipal', 'SP', 5115, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5116, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 5117, 'Aniversário da cidade', 8, 6),
  ('Municipal', 'SP', 5118, 'Feriado Municipal', 3, 8),
  ('Municipal', 'SP', 5118, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5118, 'Feriado Municipal', 9, 29),
  ('Municipal', 'SP', 5118, 'Aniversário da cidade', 1, 14);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5119, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5119, 'Aniversário da cidade', 8, 29),
  ('Municipal', 'SP', 5121, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 5121, 'Feriado Municipal', 9, 15),
  ('Municipal', 'SP', 5120, 'Aniversário da cidade', 2, 21),
  ('Municipal', 'SP', 5120, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5120, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5120, 'Feriado Municipal', 11, 1),
  ('Municipal', 'SP', 5122, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5123, 'Aniversário da cidade', 11, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5123, 'Feriado Municipal', 10, 1),
  ('Municipal', 'SP', 5124, 'Aniversário da cidade', 9, 6),
  ('Municipal', 'SP', 5124, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5124, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5125, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5125, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5125, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5126, 'Aniversário da cidade', 4, 5),
  ('Municipal', 'SP', 5126, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5126, 'São Sebastião', 1, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5127, 'Aniversário da cidade', 9, 1),
  ('Municipal', 'SP', 5127, 'Dia de Sant''Ana (Padroeira da cidade)', 7, 26),
  ('Municipal', 'SP', 5128, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5129, 'Feriado Municipal', 10, 22),
  ('Municipal', 'SP', 5130, 'Feriado Municipal', 11, 1),
  ('Municipal', 'SP', 5130, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5130, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5131, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5131, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5131, 'Feriado Municipal', 3, 21);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5131, 'Feriado Municipal', 11, 27),
  ('Municipal', 'SP', 5132, 'Aniversário da cidade', 12, 7),
  ('Municipal', 'SP', 5133, 'Aniversário da cidade', 8, 6),
  ('Municipal', 'SP', 5133, 'Feriado Municipal', 12, 24),
  ('Municipal', 'SP', 5133, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5134, 'Aniversário da cidade', 5, 15),
  ('Municipal', 'SP', 5134, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5135, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5135, 'Aniversário da cidade', 3, 10),
  ('Municipal', 'SP', 5136, 'Aniversário da cidade', 6, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5136, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5137, 'Aniversário da cidade', 11, 22),
  ('Municipal', 'SP', 5137, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5137, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5139, 'Aniversário da cidade', 4, 26),
  ('Municipal', 'SP', 5139, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5139, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5138, 'Aniversário da cidade', 3, 24),
  ('Municipal', 'SP', 5138, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5140, 'Aniversário da cidade', 1, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5140, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5141, 'Aniversário da cidade', 6, 29),
  ('Municipal', 'SP', 5141, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5142, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'SP', 5143, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5145, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5145, 'Feriado Municipal', 4, 2),
  ('Municipal', 'SP', 5145, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5145, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5146, 'Aniversário da cidade', 8, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5146, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5146, 'Feriado Municipal', 5, 29),
  ('Municipal', 'SP', 5147, 'Feriado Municipal', 6, 10),
  ('Municipal', 'SP', 5147, 'Feriado Municipal', 11, 21),
  ('Municipal', 'SP', 5148, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'SP', 5148, 'Feriado Municipal', 11, 30),
  ('Municipal', 'SP', 5149, 'São João', 6, 24),
  ('Municipal', 'SP', 5149, 'Feriado Municipal', 1, 6),
  ('Municipal', 'SP', 5150, 'Feriado Municipal', 3, 25),
  ('Municipal', 'SP', 5150, 'Feriado Municipal', 7, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5150, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5150, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5150, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5150, 'Aniversário da cidade', 9, 6),
  ('Municipal', 'SP', 5151, 'Feriado Municipal', 11, 30),
  ('Municipal', 'SP', 5151, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5151, 'Feriado Municipal', 1, 6),
  ('Municipal', 'SP', 5151, 'Aniversário da cidade', 10, 12),
  ('Municipal', 'SP', 5153, 'Aniversário da cidade', 6, 29),
  ('Municipal', 'SP', 5155, 'Aniversário da cidade', 12, 30);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5155, 'Feriado Municipal', 6, 15),
  ('Municipal', 'SP', 5156, 'Aniversário da cidade', 3, 22),
  ('Municipal', 'SP', 5156, 'Feriado Municipal', 10, 5),
  ('Municipal', 'SP', 5156, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5157, 'Aniversário da cidade', 10, 20),
  ('Municipal', 'SP', 5157, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5157, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5158, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5158, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5161, 'Aniversário da cidade', 5, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5159, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5159, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5159, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5160, 'Aniversário da cidade', 9, 15),
  ('Municipal', 'SP', 5162, 'Aniversário da cidade', 10, 28),
  ('Municipal', 'SP', 5162, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5163, 'Aniversário da cidade', 9, 9),
  ('Municipal', 'SP', 5163, 'Feriado Municipal', 5, 3),
  ('Municipal', 'SP', 5163, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5164, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5164, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5164, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5165, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5165, 'Aniversário da cidade', 4, 7),
  ('Municipal', 'SP', 5166, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5166, 'São João', 6, 24),
  ('Municipal', 'SP', 5166, 'Aniversário da cidade', 3, 2),
  ('Municipal', 'SP', 5167, 'Aniversário da cidade', 3, 22),
  ('Municipal', 'SP', 5167, 'São João', 6, 24),
  ('Municipal', 'SP', 5167, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5167, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5168, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 5168, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SP', 5169, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5169, 'Feriado Municipal', 10, 1),
  ('Municipal', 'SP', 5169, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5170, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5170, 'Aniversário da cidade', 3, 30),
  ('Municipal', 'SP', 5171, 'Aniversário da cidade', 2, 19),
  ('Municipal', 'SP', 5171, 'Santo Antônio (Padroeiro da cidade)', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5172, 'Aniversário da cidade', 4, 24),
  ('Municipal', 'SP', 5172, 'Feriado Municipal', 7, 16),
  ('Municipal', 'SP', 5173, 'Aniversário da cidade', 6, 6),
  ('Municipal', 'SP', 5173, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5174, 'Aniversário da cidade', 12, 13),
  ('Municipal', 'SP', 5174, 'PADROEIRO DE OURINHOS', 8, 6),
  ('Municipal', 'SP', 5176, 'São João', 6, 24),
  ('Municipal', 'SP', 5176, 'Feriado Municipal', 12, 27),
  ('Municipal', 'SP', 5175, 'Aniversário da cidade', 3, 23),
  ('Municipal', 'SP', 5175, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5175, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5177, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'SP', 5177, 'Feriado Municipal', 1, 6),
  ('Municipal', 'SP', 5177, 'Feriado Municipal', 11, 27),
  ('Municipal', 'SP', 5178, 'Aniversário da cidade', 5, 30),
  ('Municipal', 'SP', 5178, 'São João', 6, 24),
  ('Municipal', 'SP', 5179, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5179, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5180, 'Aniversário da cidade', 12, 13),
  ('Municipal', 'SP', 5181, 'Aniversário da cidade', 1, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5181, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5182, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 5183, 'Feriado Municipal', 3, 12),
  ('Municipal', 'SP', 5184, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5184, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5184, 'Feriado Municipal', 7, 10),
  ('Municipal', 'SP', 5185, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5185, 'Feriado Municipal', 10, 5),
  ('Municipal', 'SP', 5185, 'Aniversário da cidade', 8, 15),
  ('Municipal', 'SP', 5186, 'Aniversário da cidade', 4, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5186, 'Feriado Municipal', 5, 9),
  ('Municipal', 'SP', 5186, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5187, 'Feriado Municipal', 10, 28),
  ('Municipal', 'SP', 5187, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5187, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5187, 'Feriado Municipal', 9, 16),
  ('Municipal', 'SP', 5187, 'Aniversário da cidade', 9, 17),
  ('Municipal', 'SP', 5188, 'Feriado Municipal', 7, 25),
  ('Municipal', 'SP', 5188, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5189, 'Aniversário da cidade', 2, 18);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5189, 'Feriado Municipal', 5, 31),
  ('Municipal', 'SP', 5190, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5190, 'Feriado Municipal', 10, 11),
  ('Municipal', 'SP', 5190, 'Aniversário da cidade', 12, 30),
  ('Municipal', 'SP', 5191, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'SP', 5192, 'Feriado Municipal', 7, 28),
  ('Municipal', 'SP', 5192, 'Aniversário da cidade', 3, 10),
  ('Municipal', 'SP', 5193, 'Aniversário da cidade', 6, 29),
  ('Municipal', 'SP', 5194, 'Feriado Municipal', 6, 15),
  ('Municipal', 'SP', 5194, 'Aniversário da cidade', 2, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5196, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 5196, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5196, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5197, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'SP', 5197, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5198, 'Aniversário da cidade', 4, 6),
  ('Municipal', 'SP', 5198, 'Feriado Municipal', 8, 9),
  ('Municipal', 'SP', 5198, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5199, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5199, 'Aniversário da cidade', 8, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5200, 'Aniversário da cidade', 8, 16),
  ('Municipal', 'SP', 5200, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5200, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5200, 'Feriado Municipal', 3, 18),
  ('Municipal', 'SP', 5201, 'Aniversário da cidade', 10, 31),
  ('Municipal', 'SP', 5201, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5201, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5202, 'Aniversário da cidade', 9, 21),
  ('Municipal', 'SP', 5202, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5202, 'Feriado Municipal', 8, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5203, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'SP', 5203, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5203, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5204, 'Feriado Municipal', 10, 4),
  ('Municipal', 'SP', 5204, 'Aniversário da cidade', 10, 25),
  ('Municipal', 'SP', 5205, 'Aniversário da cidade', 8, 11),
  ('Municipal', 'SP', 5205, 'Feriado Municipal', 12, 3),
  ('Municipal', 'SP', 5205, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5206, 'Feriado Municipal', 8, 11),
  ('Municipal', 'SP', 5206, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5207, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5207, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5207, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 5208, 'Aniversário da cidade', 11, 8),
  ('Municipal', 'SP', 5208, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5208, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5209, 'Aniversário da cidade', 5, 20),
  ('Municipal', 'SP', 5209, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5210, 'Aniversário da cidade', 11, 5),
  ('Municipal', 'SP', 5211, 'Aniversário da cidade', 7, 10),
  ('Municipal', 'SP', 5211, 'Feriado Municipal', 9, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5212, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5212, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5212, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5213, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 5213, 'Feriado Municipal', 9, 24),
  ('Municipal', 'SP', 5214, 'Aniversário da cidade', 3, 20),
  ('Municipal', 'SP', 5214, 'Feriado Municipal', 9, 29),
  ('Municipal', 'SP', 5215, 'Aniversário da cidade', 6, 15),
  ('Municipal', 'SP', 5215, 'Feriado Municipal', 9, 29),
  ('Municipal', 'SP', 5216, 'Aniversário da cidade', 6, 16);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5216, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5216, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5217, 'Aniversário da cidade', 8, 1),
  ('Municipal', 'SP', 5217, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5217, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5217, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5218, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'SP', 5219, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5219, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5219, 'São João', 6, 24);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5219, 'Aniversário da cidade', 3, 29),
  ('Municipal', 'SP', 5220, 'Aniversário da cidade', 3, 7),
  ('Municipal', 'SP', 5220, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5221, 'Aniversário da cidade', 8, 6),
  ('Municipal', 'SP', 5221, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5222, 'Aniversário da cidade', 4, 9),
  ('Municipal', 'SP', 5222, 'São João', 6, 24),
  ('Municipal', 'SP', 5223, 'Aniversário da cidade', 8, 6),
  ('Municipal', 'SP', 5223, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5224, 'Feriado Municipal', 5, 18);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5225, 'Aniversário da cidade', 7, 27),
  ('Municipal', 'SP', 5225, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5226, 'Aniversário da cidade', 4, 3),
  ('Municipal', 'SP', 5226, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5227, 'Aniversário da cidade', 12, 30),
  ('Municipal', 'SP', 5227, 'Feriado Municipal', 7, 16),
  ('Municipal', 'SP', 5227, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5228, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'SP', 5229, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 5229, 'Feriado Municipal', 6, 13);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5229, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5230, 'Feriado Municipal', 10, 7),
  ('Municipal', 'SP', 5230, 'Aniversário da cidade', 9, 17),
  ('Municipal', 'SP', 5231, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5231, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5231, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'SP', 5232, 'Aniversário da cidade', 10, 18),
  ('Municipal', 'SP', 5232, 'Feriado Municipal', 8, 10),
  ('Municipal', 'SP', 5234, 'Aniversário da cidade', 4, 8),
  ('Municipal', 'SP', 5234, 'Feriado Municipal', 9, 17);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5234, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5235, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5236, 'Aniversário da cidade', 6, 4),
  ('Municipal', 'SP', 5236, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5236, 'Feriado Municipal', 7, 21),
  ('Municipal', 'SP', 5236, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5237, 'Aniversário da cidade', 10, 13),
  ('Municipal', 'SP', 5237, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5237, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5238, 'Aniversário da cidade', 7, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5238, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5239, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 5239, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5240, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5240, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5242, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5242, 'Aniversário da cidade', 6, 13),
  ('Municipal', 'SP', 5243, 'Aniversário da cidade', 1, 19),
  ('Municipal', 'SP', 5243, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5244, 'Feriado Municipal', 3, 22);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5244, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5245, 'Feriado Municipal', 11, 22),
  ('Municipal', 'SP', 5245, 'Feriado Municipal', 12, 2),
  ('Municipal', 'SP', 5246, 'Aniversário da cidade', 10, 12),
  ('Municipal', 'SP', 5246, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5247, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5247, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5247, 'Aniversário da cidade', 3, 27),
  ('Municipal', 'SP', 5248, 'Aniversário da cidade', 9, 14),
  ('Municipal', 'SP', 5248, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5248, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5249, 'Aniversário da cidade', 9, 2),
  ('Municipal', 'SP', 5249, 'Feriado Municipal', 5, 13),
  ('Municipal', 'SP', 5250, 'Feriado Municipal', 11, 29),
  ('Municipal', 'SP', 5250, 'Aniversário da cidade', 5, 1),
  ('Municipal', 'SP', 5252, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5252, 'Aniversário da cidade', 1, 16),
  ('Municipal', 'SP', 5253, 'Aniversário da cidade', 3, 28),
  ('Municipal', 'SP', 5253, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5254, 'Aniversário da cidade', 3, 4);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5254, 'São João', 6, 24),
  ('Municipal', 'SP', 5255, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 5256, 'Feriado Municipal', 2, 11),
  ('Municipal', 'SP', 5257, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5257, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5257, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5258, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5258, 'Feriado Municipal', 8, 25),
  ('Municipal', 'SP', 5258, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5258, 'Aniversário da cidade', 5, 3);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5259, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5259, 'Aniversário da cidade', 6, 28),
  ('Municipal', 'SP', 5260, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5260, 'Aniversário da cidade', 4, 3),
  ('Municipal', 'SP', 5261, 'Feriado Municipal', 12, 3),
  ('Municipal', 'SP', 5261, 'Feriado Municipal', 11, 30),
  ('Municipal', 'SP', 5262, 'Aniversário da cidade', 2, 28),
  ('Municipal', 'SP', 5262, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5262, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5263, 'Aniversário da cidade', 10, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5263, 'São João', 6, 24),
  ('Municipal', 'SP', 5263, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5264, 'Feriado Municipal', 3, 5),
  ('Municipal', 'SP', 5264, 'Aniversário da cidade', 8, 6),
  ('Municipal', 'SP', 5265, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5265, 'Feriado Municipal', 9, 6),
  ('Municipal', 'SP', 5265, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5265, 'Aniversário da cidade', 9, 5),
  ('Municipal', 'SP', 5266, 'Feriado Municipal', 9, 14),
  ('Municipal', 'SP', 5266, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5266, 'Aniversário da cidade', 4, 7),
  ('Municipal', 'SP', 5267, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5267, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5269, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 5270, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5270, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 5271, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5271, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5271, 'Aniversário da cidade', 6, 19),
  ('Municipal', 'SP', 5272, 'Aniversário da cidade', 12, 25);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5272, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5272, 'Feriado Municipal', 12, 24),
  ('Municipal', 'SP', 5272, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5273, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5273, 'Aniversário da cidade', 8, 20),
  ('Municipal', 'SP', 5274, 'Feriado Municipal', 10, 4),
  ('Municipal', 'SP', 5274, 'Aniversário da cidade', 10, 12),
  ('Municipal', 'SP', 5275, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5275, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5276, 'Aniversário da cidade', 7, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5276, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5276, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5277, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5277, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 5277, 'São Sebastião (Padroeiro da cidade)', 1, 20),
  ('Municipal', 'SP', 5278, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5278, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5278, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'SP', 5279, 'Aniversário da cidade', 12, 30),
  ('Municipal', 'SP', 5279, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5279, 'Feriado Municipal', 8, 6);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5280, 'Aniversário da cidade', 11, 5),
  ('Municipal', 'SP', 5280, 'Feriado Municipal', 2, 2),
  ('Municipal', 'SP', 5281, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5281, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5281, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5282, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5282, 'Feriado Municipal', 10, 4),
  ('Municipal', 'SP', 5282, 'Aniversário da cidade', 8, 24),
  ('Municipal', 'SP', 5283, 'Aniversário da cidade', 10, 3),
  ('Municipal', 'SP', 5284, 'Aniversário da cidade', 1, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5284, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5285, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 5285, 'Feriado Municipal', 10, 5),
  ('Municipal', 'SP', 5286, 'Aniversário da cidade', 11, 30),
  ('Municipal', 'SP', 5286, 'Feriado Municipal', 10, 5),
  ('Municipal', 'SP', 5286, 'Feriado Municipal', 4, 4),
  ('Municipal', 'SP', 5287, 'Feriado Municipal', 5, 22),
  ('Municipal', 'SP', 5288, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5288, 'São José (Padroeiro da cidade)', 3, 19),
  ('Municipal', 'SP', 5288, 'Aniversário da cidade', 2, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5289, 'Feriado Municipal', 12, 13),
  ('Municipal', 'SP', 5289, 'São João', 6, 24),
  ('Municipal', 'SP', 5289, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 5290, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 5290, 'Feriado Municipal', 6, 30),
  ('Municipal', 'SP', 5291, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5291, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5292, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5292, 'Feriado Municipal', 12, 30),
  ('Municipal', 'SP', 5293, 'Aniversário da cidade', 12, 27);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5293, 'Feriado Municipal', 9, 15),
  ('Municipal', 'SP', 5294, 'Aniversário da cidade', 12, 31),
  ('Municipal', 'SP', 5294, 'Feriado Municipal', 10, 28),
  ('Municipal', 'SP', 5294, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5294, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5295, 'Feriado Municipal', 12, 16),
  ('Municipal', 'SP', 5295, 'Aniversário da cidade', 3, 22),
  ('Municipal', 'SP', 5296, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5296, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5296, 'Feriado Municipal', 10, 4);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5298, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'SP', 5299, 'Feriado Municipal', 5, 20),
  ('Municipal', 'SP', 5300, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5300, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 5302, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 5302, 'Feriado Municipal', 9, 14),
  ('Municipal', 'SP', 5303, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'SP', 5303, 'Feriado Municipal', 5, 13),
  ('Municipal', 'SP', 5304, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5304, 'Feriado Municipal', 7, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5305, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5306, 'Aniversário da cidade', 8, 16),
  ('Municipal', 'SP', 5306, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5307, 'Feriado Municipal', 7, 4),
  ('Municipal', 'SP', 5307, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5308, 'Feriado Municipal', 12, 19),
  ('Municipal', 'SP', 5308, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5309, 'Aniversário da cidade', 10, 27),
  ('Municipal', 'SP', 5309, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5310, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5310, 'Feriado Municipal', 9, 24),
  ('Municipal', 'SP', 5311, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'SP', 5312, 'Aniversário da cidade', 5, 22),
  ('Municipal', 'SP', 5313, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5313, 'Feriado Municipal', 9, 4),
  ('Municipal', 'SP', 5315, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5315, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5316, 'Aniversário da cidade', 11, 14),
  ('Municipal', 'SP', 5316, 'Sant''Ana (Padroeira da cidade)', 7, 26),
  ('Municipal', 'SP', 5317, 'Feriado Municipal', 1, 22);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5317, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5317, 'Aniversário da cidade', 11, 19),
  ('Municipal', 'SP', 5318, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5318, 'Aniversário da cidade', 4, 8),
  ('Municipal', 'SP', 5319, 'Feriado Municipal', 1, 6),
  ('Municipal', 'SP', 5319, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5319, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5320, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5320, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5320, 'Aniversário da cidade', 6, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5321, 'Aniversário da cidade', 5, 8),
  ('Municipal', 'SP', 5322, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5322, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5322, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'SP', 5323, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5323, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'SP', 5324, 'Aniversário da cidade', 4, 26),
  ('Municipal', 'SP', 5324, 'Feriado Municipal', 4, 19),
  ('Municipal', 'SP', 5324, 'Feriado Municipal', 12, 10),
  ('Municipal', 'SP', 5325, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5325, 'Feriado Municipal', 11, 27),
  ('Municipal', 'SP', 5325, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 5326, 'Aniversário da cidade', 1, 26),
  ('Municipal', 'SP', 5326, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5326, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5327, 'Feriado Municipal', 4, 9),
  ('Municipal', 'SP', 5327, 'Feriado Municipal', 7, 11),
  ('Municipal', 'SP', 5327, 'Aniversário da cidade', 8, 16),
  ('Municipal', 'SP', 5328, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5328, 'Aniversário da cidade', 8, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5329, 'Aniversário da cidade', 7, 28),
  ('Municipal', 'SP', 5329, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5330, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5330, 'Aniversário da cidade', 11, 4),
  ('Municipal', 'SP', 5331, 'Feriado Municipal', 10, 4),
  ('Municipal', 'SP', 5331, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 5332, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5332, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5333, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5335, 'Aniversário da cidade', 6, 24),
  ('Municipal', 'SP', 5336, 'Feriado Municipal', 7, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5336, 'Aniversário da cidade', 5, 30),
  ('Municipal', 'SP', 5337, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5337, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5337, 'Aniversário da cidade', 4, 3),
  ('Municipal', 'SP', 5338, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5338, 'Aniversário da cidade', 3, 9),
  ('Municipal', 'SP', 5339, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5339, 'Aniversário da cidade', 3, 19),
  ('Municipal', 'SP', 5340, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5340, 'Aniversário da cidade', 3, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5341, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5341, 'Aniversário da cidade', 7, 27),
  ('Municipal', 'SP', 5342, 'Aniversário da cidade', 3, 12),
  ('Municipal', 'SP', 5342, 'São Lourenço (Padroeiro da cidade)', 8, 10),
  ('Municipal', 'SP', 5342, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5343, 'Feriado Municipal', 8, 19),
  ('Municipal', 'SP', 5343, 'Feriado Municipal', 5, 8),
  ('Municipal', 'SP', 5344, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5344, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5344, 'Aniversário da cidade', 6, 17);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5345, 'Feriado Municipal', 9, 29),
  ('Municipal', 'SP', 5345, 'Aniversário da cidade', 4, 1),
  ('Municipal', 'SP', 5346, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5346, 'Aniversário da cidade', 1, 25),
  ('Municipal', 'SP', 5347, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5347, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5347, 'Aniversário da cidade', 2, 22),
  ('Municipal', 'SP', 5348, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5348, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5348, 'Aniversário da cidade', 5, 29);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5349, 'Aniversário da cidade', 8, 16),
  ('Municipal', 'SP', 5350, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5350, 'Aniversário da cidade', 3, 16),
  ('Municipal', 'SP', 5351, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5351, 'Aniversário da cidade', 11, 4),
  ('Municipal', 'SP', 5352, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5352, 'Aniversário da cidade', 10, 28),
  ('Municipal', 'SP', 5353, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5353, 'Aniversário da cidade', 1, 22),
  ('Municipal', 'SP', 5354, 'Feriado Municipal', 9, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5354, 'Aniversário da cidade', 3, 13),
  ('Municipal', 'SP', 5355, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5355, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 5356, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5356, 'Feriado Municipal', 2, 28),
  ('Municipal', 'SP', 5356, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5356, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5356, 'Aniversário da cidade', 2, 22),
  ('Municipal', 'SP', 5357, 'Aniversário da cidade', 11, 14),
  ('Municipal', 'SP', 5357, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5357, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5359, 'Aniversário da cidade', 4, 10),
  ('Municipal', 'SP', 5359, 'Feriado Municipal', 9, 15),
  ('Municipal', 'SP', 5358, 'Aniversário da cidade', 9, 23),
  ('Municipal', 'SP', 5358, 'Feriado Municipal', 11, 1),
  ('Municipal', 'SP', 5358, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5360, 'Feriado Municipal', 12, 5),
  ('Municipal', 'SP', 5361, 'Aniversário da cidade', 12, 18),
  ('Municipal', 'SP', 5361, 'São João', 6, 24),
  ('Municipal', 'SP', 5361, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5362, 'Feriado Municipal', 4, 5),
  ('Municipal', 'SP', 5362, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5362, 'Feriado Municipal', 11, 1),
  ('Municipal', 'SP', 5362, 'Aniversário da cidade', 2, 19),
  ('Municipal', 'SP', 5363, 'Aniversário da cidade', 2, 22),
  ('Municipal', 'SP', 5363, 'Feriado Municipal', 2, 28),
  ('Municipal', 'SP', 5363, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5364, 'Aniversário da cidade', 8, 9),
  ('Municipal', 'SP', 5364, 'Feriado Municipal', 8, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5365, 'Aniversário da cidade', 8, 15),
  ('Municipal', 'SP', 5365, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5366, 'Aniversário da cidade', 9, 10),
  ('Municipal', 'SP', 5366, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5367, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5367, 'Aniversário da cidade', 7, 26),
  ('Municipal', 'SP', 5368, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5369, 'São Sebastião (Padroeiro da cidade)', 1, 20),
  ('Municipal', 'SP', 5369, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'SP', 5369, 'Dia da Consciência Negra', 11, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5370, 'Feriado Municipal', 10, 11),
  ('Municipal', 'SP', 5370, 'Aniversário da cidade', 11, 27),
  ('Municipal', 'SP', 5371, 'Aniversário da cidade', 4, 26),
  ('Municipal', 'SP', 5372, 'Aniversário da cidade', 2, 19),
  ('Municipal', 'SP', 5372, 'Dia do Amor Misericordioso de Deus', 10, 1),
  ('Municipal', 'SP', 5373, 'Aniversário da cidade', 11, 1),
  ('Municipal', 'SP', 5373, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5374, 'Feriado Municipal', 5, 22),
  ('Municipal', 'SP', 5374, 'Aniversário da cidade', 2, 18),
  ('Municipal', 'SP', 5375, 'Feriado Municipal', 12, 30),
  ('Municipal', 'SP', 5375, 'Aniversário da cidade', 3, 19);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5376, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5376, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5376, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5377, 'Feriado Municipal', 6, 16),
  ('Municipal', 'SP', 5377, 'Aniversário da cidade', 8, 20),
  ('Municipal', 'SP', 5378, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5378, 'Feriado Municipal', 7, 4),
  ('Municipal', 'SP', 5379, 'Feriado Municipal', 11, 25),
  ('Municipal', 'SP', 5379, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5379, 'Aniversário da cidade', 2, 19),
  ('Municipal', 'SP', 5380, 'Aniversário da cidade', 12, 27);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5382, 'Aniversário da cidade', 8, 16),
  ('Municipal', 'SP', 5382, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5383, 'Aniversário da cidade', 8, 16),
  ('Municipal', 'SP', 5384, 'Aniversário da cidade', 12, 31),
  ('Municipal', 'SP', 5385, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5385, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5385, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5385, 'Feriado Municipal', 10, 28),
  ('Municipal', 'SP', 5386, 'Feriado Municipal', 11, 30),
  ('Municipal', 'SP', 5386, 'Aniversário da cidade', 10, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5387, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5387, 'Aniversário da cidade', 8, 11),
  ('Municipal', 'SP', 5388, 'Feriado Municipal', 4, 13),
  ('Municipal', 'SP', 5388, 'Feriado Municipal', 4, 9),
  ('Municipal', 'SP', 5388, 'Feriado Municipal', 10, 4),
  ('Municipal', 'SP', 5388, 'Feriado Municipal', 12, 5),
  ('Municipal', 'SP', 5389, 'Feriado Municipal', 5, 26),
  ('Municipal', 'SP', 5389, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5389, 'Aniversário da cidade', 4, 25),
  ('Municipal', 'SP', 5390, 'Aniversário da cidade', 1, 7);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5390, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5391, 'Aniversário da cidade', 3, 26),
  ('Municipal', 'SP', 5391, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5392, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5392, 'Aniversário da cidade', 3, 8),
  ('Municipal', 'SP', 5393, 'Aniversário da cidade', 10, 24),
  ('Municipal', 'SP', 5393, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5394, 'Aniversário da cidade', 10, 27),
  ('Municipal', 'SP', 5395, 'Aniversário da cidade', 4, 7),
  ('Municipal', 'SP', 5395, 'Feriado Municipal', 12, 8);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5395, 'Feriado Municipal', 3, 19),
  ('Municipal', 'SP', 5397, 'Feriado Municipal', 8, 6),
  ('Municipal', 'SP', 5397, 'Aniversário da cidade', 11, 26),
  ('Municipal', 'SP', 5398, 'Feriado Municipal', 2, 18),
  ('Municipal', 'SP', 5398, 'Aniversário da cidade', 10, 12),
  ('Municipal', 'SP', 5399, 'Aniversário da cidade', 5, 19),
  ('Municipal', 'SP', 5400, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5400, 'Aniversário da cidade', 10, 12),
  ('Municipal', 'SP', 5401, 'Aniversário da cidade', 8, 28),
  ('Municipal', 'SP', 5401, 'Feriado Municipal', 8, 15);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5402, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5402, 'Feriado Municipal', 9, 27),
  ('Municipal', 'SP', 5402, 'Aniversário da cidade', 7, 19),
  ('Municipal', 'SP', 5403, 'Aniversário da cidade', 8, 6),
  ('Municipal', 'SP', 5403, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5405, 'Aniversário da cidade', 10, 28),
  ('Municipal', 'SP', 5405, 'Feriado Municipal', 9, 14),
  ('Municipal', 'SP', 5405, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5406, 'Aniversário da cidade', 4, 2),
  ('Municipal', 'SP', 5407, 'Feriado Municipal', 3, 28);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5407, 'Feriado Municipal', 7, 8),
  ('Municipal', 'SP', 5408, 'São João', 6, 24),
  ('Municipal', 'SP', 5408, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5408, 'Feriado Municipal', 9, 8),
  ('Municipal', 'SP', 5409, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5409, 'Feriado Municipal', 10, 5),
  ('Municipal', 'SP', 5409, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5409, 'Aniversário da cidade', 6, 10),
  ('Municipal', 'SP', 5410, 'Dia de Santo Antônio (Padroeiro da cidade)', 6, 13),
  ('Municipal', 'SP', 5410, 'Aniversário da cidade', 1, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5411, 'Feriado Municipal', 8, 10),
  ('Municipal', 'SP', 5411, 'Aniversário da cidade', 9, 24),
  ('Municipal', 'SP', 5412, 'Aniversário da cidade', 5, 3),
  ('Municipal', 'SP', 5412, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5413, 'Aniversário da cidade', 5, 28),
  ('Municipal', 'SP', 5413, 'São Sebastião', 1, 20),
  ('Municipal', 'SP', 5413, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5414, 'Feriado Municipal', 8, 15),
  ('Municipal', 'SP', 5414, 'Feriado Municipal', 5, 24),
  ('Municipal', 'SP', 5414, 'Aniversário da cidade', 5, 30);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5415, 'Feriado Municipal', 12, 30),
  ('Municipal', 'SP', 5415, 'Feriado Municipal', 6, 13),
  ('Municipal', 'SP', 5416, 'Aniversário da cidade', 9, 26),
  ('Municipal', 'SP', 5416, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5417, 'Feriado Municipal', 11, 27),
  ('Municipal', 'SP', 5418, 'Feriado Municipal', 3, 21),
  ('Municipal', 'SP', 5418, 'Feriado Municipal', 9, 15),
  ('Municipal', 'SP', 5419, 'Aniversário da cidade', 1, 25),
  ('Municipal', 'SP', 5419, 'Feriado Municipal', 7, 1),
  ('Municipal', 'SP', 5420, 'Aniversário da cidade', 4, 2);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'SP', 5420, 'Feriado Municipal', 7, 26),
  ('Municipal', 'SP', 5421, 'Aniversário da cidade', 3, 23),
  ('Municipal', 'SP', 5421, 'São Pedro', 6, 29),
  ('Municipal', 'SP', 5421, 'Feriado Municipal', 4, 4),
  ('Municipal', 'SP', 5422, 'Feriado Municipal', 2, 18),
  ('Municipal', 'SP', 5422, 'Feriado Municipal', 5, 22),
  ('Municipal', 'SP', 5422, 'Feriado Municipal', 3, 25),
  ('Municipal', 'SP', 5424, 'Feriado Municipal', 12, 8),
  ('Municipal', 'SP', 5424, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'SP', 5425, 'Aniversário da cidade', 8, 8);


-- Tocantins (TO)

-- Feriados estaduais
INSERT INTO erp.holidays (geographicScope, state, name, month, day) VALUES
  ('Estadual', 'TO', 'Nossa Senhora da Natividade (Padroeira do Estado)', 9, 8),
  ('Estadual', 'TO', 'Criação do estado do Tocantins', 10, 5);

-- Feriados municipais
INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'TO', 5427, 'Aniversário da cidade', 5, 1),
  ('Municipal', 'TO', 5428, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'TO', 5429, 'Aniversário da cidade', 1, 10),
  ('Municipal', 'TO', 5430, 'Aniversário da cidade', 1, 30),
  ('Municipal', 'TO', 5433, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5434, 'Aniversário da cidade', 6, 1),
  ('Municipal', 'TO', 5439, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5443, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'TO', 5447, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'TO', 5448, 'Aniversário da cidade', 4, 26);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'TO', 5449, 'Aniversário da cidade', 1, 11),
  ('Municipal', 'TO', 5450, 'Aniversário da cidade', 6, 1),
  ('Municipal', 'TO', 5451, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5454, 'Aniversário da cidade', 6, 1),
  ('Municipal', 'TO', 5455, 'Aniversário da cidade', 2, 10),
  ('Municipal', 'TO', 5456, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'TO', 5457, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5458, 'Aniversário da cidade', 1, 20),
  ('Municipal', 'TO', 5459, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5460, 'Aniversário da cidade', 6, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'TO', 5461, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5463, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'TO', 5464, 'Aniversário da cidade', 4, 21),
  ('Municipal', 'TO', 5465, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'TO', 5466, 'Aniversário da cidade', 6, 1),
  ('Municipal', 'TO', 5470, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'TO', 5473, 'Aniversário da cidade', 6, 1),
  ('Municipal', 'TO', 5476, 'Aniversário da cidade', 2, 10),
  ('Municipal', 'TO', 5477, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'TO', 5479, 'Aniversário da cidade', 1, 1);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'TO', 5481, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5482, 'Aniversário da cidade', 6, 1),
  ('Municipal', 'TO', 5484, 'Aniversário da cidade', 4, 11),
  ('Municipal', 'TO', 5489, 'Aniversário da cidade', 2, 10),
  ('Municipal', 'TO', 5493, 'Aniversário da cidade', 2, 10),
  ('Municipal', 'TO', 5494, 'Aniversário da cidade', 2, 10),
  ('Municipal', 'TO', 5495, 'Aniversário da cidade', 5, 5),
  ('Municipal', 'TO', 5498, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'TO', 5499, 'Aniversário da cidade', 6, 1),
  ('Municipal', 'TO', 5500, 'Aniversário da cidade', 2, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'TO', 5501, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5505, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'TO', 5507, 'Aniversário da cidade', 6, 1),
  ('Municipal', 'TO', 5510, 'Aniversário da cidade', 6, 1),
  ('Municipal', 'TO', 5513, 'Aniversário da cidade', 2, 10),
  ('Municipal', 'TO', 5514, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'TO', 5515, 'São José (Padroeiro da cidade)', 3, 19),
  ('Municipal', 'TO', 5515, 'Aniversário da cidade', 5, 20),
  ('Municipal', 'TO', 5516, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5517, 'Aniversário da cidade', 2, 10),
  ('Municipal', 'TO', 5521, 'Aniversário da cidade', 2, 10);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'TO', 5526, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'TO', 5530, 'Aniversário da cidade', 1, 14),
  ('Municipal', 'TO', 5531, 'Dia da Consciência Negra', 11, 20),
  ('Municipal', 'TO', 5536, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5537, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5539, 'Aniversário da cidade', 5, 14),
  ('Municipal', 'TO', 5544, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'TO', 5547, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'TO', 5548, 'Aniversário da cidade', 2, 10),
  ('Municipal', 'TO', 5549, 'Aniversário da cidade', 2, 22),
  ('Municipal', 'TO', 5550, 'Aniversário da cidade', 2, 20);

INSERT INTO erp.holidays (geographicScope, state, cityid, name, month, day) VALUES
  ('Municipal', 'TO', 5551, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5553, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'TO', 5556, 'Aniversário da cidade', 2, 20),
  ('Municipal', 'TO', 5558, 'Aniversário da cidade', 2, 10),
  ('Municipal', 'TO', 5559, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'TO', 5562, 'Aniversário da cidade', 5, 26),
  ('Municipal', 'TO', 5563, 'Aniversário da cidade', 1, 1),
  ('Municipal', 'TO', 5564, 'Aniversário da cidade', 2, 1);

-- ---------------------------------------------------------------------
-- Feriados móveis
-- ---------------------------------------------------------------------
-- Obtém todos os feriados móveis para um determinado ano
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.mobileHolidays(year integer)
RETURNS
  table (
    name   varchar(100),
    day    smallint,
    month  smallint,
    state  char(2),
    cityID integer
  ) AS $$
  -- Segunda-feira de carnaval
  SELECT nationalHolidays.name,
         nationalHolidays.day,
         nationalHolidays.month,
         NULL AS state,
         NULL AS cityID
    FROM (
      SELECT 'Carnaval' AS name,
             EXTRACT(DAY FROM mondayOfCarnival.date) AS day,
             EXTRACT(MONTH FROM mondayOfCarnival.date) AS month
        FROM (SELECT (public.easter(year) - 48) AS date) AS mondayOfCarnival
       UNION
      -- Terça-feira de carnaval
      SELECT 'Carnaval' AS name,
             EXTRACT(DAY FROM tuesdayOfCarnival.date) AS day,
             EXTRACT(MONTH FROM tuesdayOfCarnival.date) AS month
        FROM (SELECT (public.easter(year) - 47) AS date) AS tuesdayOfCarnival
       UNION
      -- Sexta-feira da paixão
      SELECT 'Paixão de Cristo' AS name,
             EXTRACT(DAY FROM fridayOfPassion.date) AS day,
             EXTRACT(MONTH FROM fridayOfPassion.date) AS month
        FROM (SELECT (public.easter(year) - 2) AS date) AS fridayOfPassion
       UNION
      -- Domingo de páscoa
      SELECT 'Páscoa' AS name,
             EXTRACT(DAY FROM sundayOfEaster.date) AS day,
             EXTRACT(MONTH FROM sundayOfEaster.date) AS month
        FROM (SELECT (public.easter(year)) AS date) AS sundayOfEaster
       UNION
      -- Corpus Christi
      SELECT 'Corpus Christi' AS name,
             EXTRACT(DAY FROM corpusChristi.date) AS day,
             EXTRACT(MONTH FROM corpusChristi.date) AS month
        FROM (SELECT (public.easter(year) + 60) AS date) AS corpusChristi
      ) AS nationalHolidays
   UNION
  -- Feriado de Nossa Senhora da Penha em Vitória - ES
  SELECT 'Nossa Senhora da Penha' AS name,
         EXTRACT(DAY FROM holiday.date) AS day,
         EXTRACT(MONTH FROM holiday.date) AS month,
         'ES' AS state,
         882 AS cityID
    FROM (SELECT (public.easter(year) + 8) AS date) AS holiday;
$$ LANGUAGE sql;

-- SELECT erp.mobileHolidays(2023);

-- ---------------------------------------------------------------------
-- Obter feriados em um determinado ano para uma cidade
-- ---------------------------------------------------------------------
-- Função que recupera os feriados válidos para uma cidade num
-- determinado ano
-- ---------------------------------------------------------------------
CREATE TYPE erp.holiday AS
(
  id               integer,
  name             varchar(100),
  geographicScope  varchar(10),
  day              smallint,
  dayOfWeekName    varchar(50),
  month            smallint,
  monthName        varchar(50),
  fullDate         date
);

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
    -- Disparamos uma excessão
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
    holidayOnYear.name             = nextHoliday.name;
    holidayOnYear.geographicScope  = nextHoliday.geographicScope;
    holidayOnYear.day              = nextHoliday.day;
    holidayOnYear.dayOfWeekName    = nextHoliday.dayOfWeekName;
    holidayOnYear.month            = nextHoliday.month;
    holidayOnYear.monthName        = nextHoliday.monthName;
    holidayOnYear.fullDate         = nextHoliday.fullDate;

    RETURN NEXT holidayOnYear;
  END loop;
  IF (inquiredCityID = 882) THEN
    -- Acrescenta o feriado de Nossa Senhora da Penha em Vitória - ES
    holidayOnYear.id               = 0;
    holidayOnYear.name             = 'Nossa Senhora da Penha';
    holidayOnYear.geographicScope  = 'Municipal';
    holidayOnYear.day              = EXTRACT(DAY FROM public.easter(inquiredYear::int) + 8);
    holidayOnYear.dayOfWeekName    = public.DayOfWeekName(EXTRACT(DOW FROM public.easter(inquiredYear::int) + 8)::int);
    holidayOnYear.month            = EXTRACT(MONTH FROM public.easter(inquiredYear::int) + 8);
    holidayOnYear.monthName        = public.MonthName(EXTRACT(MONTH FROM public.easter(inquiredYear::int) + 8)::int);
    holidayOnYear.fullDate         = (public.easter(inquiredYear::int) + 8)::DATE;

    RETURN NEXT holidayOnYear;
  END IF;
END
$$
LANGUAGE 'plpgsql';

-- Ex: Recuperar os feriados da cidade de Guarulhos em 2017
-- SELECT * FROM erp.getHolidaysOnYear('2017', 4996);

-- ---------------------------------------------------------------------
-- Obter o próximo dia útil daqui a 'n' dias
-- ---------------------------------------------------------------------
-- Stored Procedure que recupera o 'n' dia útil a partir do dia corrente
-- levando em consideração os feriados na cidade informada.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getNextWorkday(n integer, inquiredCity integer)
RETURNS date AS
$$
DECLARE
  futureDay       date;
  holiday         RECORD;
  inquiredYear    char(4);
BEGIN
  -- Primeiramente soma a data atual a quantidade de dias informadas
  SELECT (now()::date + n)::date INTO futureDay;

  -- Incrementa a data futura até conseguir um dia de semana, caso o dia
  -- esteja num sábado ou domingo
  WHILE (date_part('dow', futureDay) = 0 OR date_part('dow', futureDay) = 6) LOOP
    -- Incrementa em um dia
    SELECT (futureDay + 1)::date INTO futureDay;
  END LOOP;
  
  -- Seleciona o ano corrente
  SELECT EXTRACT(YEAR FROM now())::char(4) INTO inquiredYear;
  
  -- Recupera os feriados existentes
  FOR holiday IN SELECT * FROM erp.getHolidaysOnYear(inquiredYear, inquiredCity)
  
  -- Pula qualquer feriado existente
  LOOP
    IF ((EXTRACT(DAY FROM futureDay) = holiday.day) AND
        (EXTRACT(MONTH FROM futureDay) = holiday.month)) THEN
      -- Incrementa em um dia
      SELECT (futureDay + 1)::date INTO futureDay;
    END IF;
  END LOOP;
  
  -- Incrementa a data futura até conseguir um dia de semana, pois o
  -- feriado pode estar numa sexta-feira, sendo necessário pular o
  -- sábado e domingo
  WHILE (date_part('dow', futureDay) = 0 OR date_part('dow', futureDay) = 6) LOOP
    -- Incrementa em um dia
    SELECT (futureDay + 1)::date INTO futureDay;
  END LOOP;

  RETURN futureDay;
END
$$
LANGUAGE 'plpgsql';

-- Ex: Recuperar o próximo dia útil na cidade de Guarulhos contados à
-- partir de hoje
-- SELECT erp.getNextWorkday(1, 4996);

-- ---------------------------------------------------------------------
-- Obter o próximo dia útil a partir de uma data informada
-- ---------------------------------------------------------------------
-- Stored Procedure que recupera o próximo dia útil a partir da data
-- informada, levando em consideração os feriados na cidade informada,
-- caso a data informada esteja num final de semana ou feriado.
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION erp.getNextWorkday(informedDate date, inquiredCity integer)
RETURNS date AS
$$
DECLARE
  futureDay       date;
  holiday         RECORD;
  inquiredYear    char(4);
BEGIN
  -- Primeiramente atribui a data informada
  futureDay = informedDate;

  -- Incrementa a data futura até conseguir um dia de semana, caso o dia
  -- esteja num sábado ou domingo
  WHILE (date_part('dow', futureDay) = 0 OR date_part('dow', futureDay) = 6) LOOP
    -- Incrementa em um dia
    SELECT (futureDay + 1)::date INTO futureDay;
  END LOOP;
  
  -- Seleciona o ano corrente
  SELECT EXTRACT(YEAR FROM now())::char(4) INTO inquiredYear;
  
  -- Recupera os feriados existentes
  FOR holiday IN SELECT * FROM erp.getHolidaysOnYear(inquiredYear, inquiredCity)
  
  -- Pula qualquer feriado existente
  LOOP
    IF ((EXTRACT(DAY FROM futureDay) = holiday.day) AND
        (EXTRACT(MONTH FROM futureDay) = holiday.month)) THEN
      -- Incrementa em um dia
      SELECT (futureDay + 1)::date INTO futureDay;
    END IF;
  END LOOP;
  
  -- Incrementa a data futura até conseguir um dia de semana, pois o
  -- feriado pode estar numa sexta-feira, sendo necessário pular o
  -- sábado e domingo
  WHILE (date_part('dow', futureDay) = 0 OR date_part('dow', futureDay) = 6) LOOP
    -- Incrementa em um dia
    SELECT (futureDay + 1)::date INTO futureDay;
  END LOOP;

  RETURN futureDay;
END
$$
LANGUAGE 'plpgsql';

-- Ex: Recuperar o próximo dia útil na cidade de guarulhos informando
--     o feriado do dia de tiradentes
-- SELECT erp.getNextWorkday('2017-04-21'::date, 4996);
