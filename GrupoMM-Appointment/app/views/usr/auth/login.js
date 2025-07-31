$(document).ready(function()
{
  $("input[name='username']")
    .mask({
      type: 'cpforcnpj',
      validate: true
    })
  ;

  $("i.toggle.icon").click(function() {
    $(this)
      .toggleClass("slash")
    ;
    
    var
      $input = $(this).prev()
    ;
    if ($input.attr("type") == "password") {
      $input
        .attr("type", "text")
      ;
    } else {
      $input
        .attr("type", "password")
      ;
    }
  });

  // Coloca o foco no primeiro campo
  $("input[name='username']")
    .focus()
  ;
});

// ================================================[ Handlers ]=====

function needHelp()
{
  $('#needHelp')
    .show()
  ;
  $('.backToTop')
    .show()
  ;
  $('html,body').animate({
      scrollTop: $('#needHelp').offset().top
    },
    'slow'
  );
}
