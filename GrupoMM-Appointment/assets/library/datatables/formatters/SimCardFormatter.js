// Um formatador (render) de coluna para exibir um ícone com a quantidade
// de slots/Sim Cards disponíveis
var simcardFormatter = function( SimCardSVG ) {
  return function ( value, type, row, meta ) {
    // Verifica se foi solicitada a exibição do valor
    if ( type === 'display' ) {
      var blue1 = '#98ccfd';
      var blue2 = '#2a5b8a';
      var gray1 = '#cccccc';
      var gray2 = '#4d4d4d';
      
      if (value > 0) {
        return SimCardSVG
          .replace('<%=color1%>', blue1)
          .replace('<%=color2%>', blue2)
          .replace('<%=value%>', value);
      }
      
      return SimCardSVG
        .replace('<%=color1%>', gray1)
        .replace('<%=color2%>', gray2)
        .replace('<%=value%>', ' ');
    }

    // Pesquisas, ordenamentos e tipos podem usar os dados originais
    return value;
  }
};
