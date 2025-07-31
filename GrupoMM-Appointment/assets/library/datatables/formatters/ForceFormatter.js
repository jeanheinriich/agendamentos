// Um formatador (render) de coluna para exibir um ícone indicativo de
// que estamos ou não forçando o usuário a modificar sua senha na
// próxima autenticação
var forceFormatter = function ( value, type, row, meta ) {
  // Verifica se foi solicitada a exibição do valor
  if ( type === 'display' ) {
    if (value)
      return '<span data-position="left center"' +
                   'data-blue data-inverted ' +
                   'data-tooltip="O usuário deve trocar a senha ' +
                   'na próxima autenticação">' +
               '<i class="history darkorange icon">' +
               '</i>' +
             '</span>';
    
    return '<span data-position="left center"' +
                 'data-blue data-inverted ' +
                 'data-tooltip="O usuário troca a senha quando desejar">' +
             '<i class="history lightgrey icon">' +
             '</i>' +
           '</span>';
  }

  // Pesquisas, ordenamentos e tipos podem usar os dados originais
  return value;
};